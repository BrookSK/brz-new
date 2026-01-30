<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Produto;

class AssessoriaController extends Controller {
    
    /**
     * Exibe a página principal de Assessoria
     */
    public function index(Request $request) {
        session_start();
        $this->view('assessoria/index');
    }

    public function enfileirarLinks(Request $request) {
        header('Content-Type: application/json');
        session_start();

        try {
            $body = $request->getBody();
            $links = $body['links'] ?? [];
            if (!is_array($links) || empty($links)) {
                echo json_encode(['success' => false, 'message' => 'Nenhum link fornecido']);
                return;
            }

            $cleanLinks = [];
            foreach ($links as $l) {
                $l = trim((string) $l);
                if ($l === '' || !filter_var($l, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $cleanLinks[] = $l;
            }

            if (empty($cleanLinks)) {
                echo json_encode(['success' => false, 'message' => 'Nenhum link válido fornecido']);
                return;
            }

            $_SESSION['assessoria_orcamento'] = [
                'produtos' => [],
                'erros' => [],
                'data_criacao' => date('Y-m-d H:i:s')
            ];

            $jobId = bin2hex(random_bytes(16));
            $_SESSION['assessoria_job_id'] = $jobId;

            $job = [
                'job_id' => $jobId,
                'status' => 'queued',
                'total' => count($cleanLinks),
                'processed' => 0,
                'links' => $cleanLinks,
                'produtos' => [],
                'erros' => [],
                'started_at' => null,
                'finished_at' => null
            ];
            $this->writeJobFile($jobId, $job);

            session_write_close();

            echo json_encode([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'total' => count($cleanLinks)
                ]
            ]);

            $spawned = $this->trySpawnJobWorker($jobId);
            $job['spawned'] = $spawned ? true : false;
            $this->writeJobFile($jobId, $job);
            if ($spawned) {
                return;
            }

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            ignore_user_abort(true);
            @set_time_limit(0);

            $this->startBackgroundProcessing($cleanLinks, $jobId);
            return;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao iniciar processamento: ' . $e->getMessage()]);
            return;
        }
    }

    public function statusJob(Request $request) {
        header('Content-Type: application/json');
        session_start();

        $jobId = (string) $request->getParam('job_id', '');
        if ($jobId === '') {
            $jobId = (string) ($_SESSION['assessoria_job_id'] ?? '');
        }

        if ($jobId === '') {
            echo json_encode(['success' => false, 'message' => 'job_id não informado']);
            return;
        }

        $job = $this->readJobFile($jobId);
        if ($job === null) {
            echo json_encode(['success' => false, 'message' => 'Job não encontrado']);
            return;
        }

        if (($job['status'] ?? '') === 'done') {
            if (!isset($_SESSION['assessoria_orcamento']) || !is_array($_SESSION['assessoria_orcamento'])) {
                $_SESSION['assessoria_orcamento'] = [
                    'produtos' => [],
                    'erros' => [],
                    'data_criacao' => date('Y-m-d H:i:s')
                ];
            }

            $_SESSION['assessoria_orcamento']['produtos'] = $job['produtos'] ?? [];
            $_SESSION['assessoria_orcamento']['erros'] = $job['erros'] ?? [];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'job_id' => $jobId,
                'status' => (string) ($job['status'] ?? ''),
                'total' => (int) ($job['total'] ?? 0),
                'processed' => (int) ($job['processed'] ?? 0),
                'total_produtos' => is_array($job['produtos'] ?? null) ? count($job['produtos']) : 0,
                'total_erros' => is_array($job['erros'] ?? null) ? count($job['erros']) : 0
            ]
        ]);
    }

    private function headerSafeValue($value, int $maxLen = 200): string {
        $v = (string) $value;
        $v = preg_replace('/[\r\n]+/', ' ', $v);
        $v = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $v);
        $v = trim($v);
        if (strlen($v) > $maxLen) {
            $v = substr($v, 0, $maxLen);
        }
        return $v;
    }

    private function cleanJsonText(string $text): string {
        // Remove caracteres de controle que quebram json_decode
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    }

    private function extractFirstJsonObject(string $text): ?string {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        return substr($text, $start, $end - $start + 1);
    }

    private function normalizePossibleJson(string $text): string {
        $t = trim($text);

        // Remover code fences ```json ... ```
        $t = preg_replace('/^```(?:json)?\s*/i', '', $t);
        $t = preg_replace('/\s*```\s*$/', '', $t);

        // Aspas “inteligentes” que quebram JSON
        $t = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{00AB}", "\u{00BB}"], '"', $t);
        $t = str_replace(["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}"], "'", $t);

        // Remover caracteres de controle
        $t = $this->cleanJsonText($t);

        // Pegar apenas o objeto JSON (se houver texto extra)
        $obj = $this->extractFirstJsonObject($t);
        if ($obj !== null) {
            $t = $obj;
        }

        // Remover vírgulas finais antes de } ou ]
        $t = preg_replace('/,\s*([}\]])/', '$1', $t);

        return trim($t);
    }

    private function decodeJsonResilient(string $raw): array {
        $candidate = $this->normalizePossibleJson($raw);

        $data = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Caso: JSON veio como string escapada "{...}"
        $maybeString = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE && is_string($maybeString)) {
            $candidate2 = $this->normalizePossibleJson($maybeString);
            $data2 = json_decode($candidate2, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data2)) {
                return $data2;
            }
        }

        $err = json_last_error_msg();
        throw new \Exception('ChatGPT não retornou JSON válido: ' . $err);
    }

    private function truncateForPrompt($value, int $depth = 0) {
        if ($depth > 4) {
            return null;
        }

        if (is_array($value)) {
            $out = [];
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i >= 40) {
                    break;
                }
                $out[$k] = $this->truncateForPrompt($v, $depth + 1);
                $i++;
            }
            return $out;
        }

        if (is_string($value)) {
            $v = $this->cleanJsonText($value);
            if (strlen($v) > 800) {
                $v = substr($v, 0, 800);
            }
            return $v;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    private function reduceScrapingBeePayload(array $dadosBrutos): array {
        $picked = [];
        foreach (['title', 'name', 'product', 'product_name', 'price', 'prices', 'images', 'image', 'variants', 'variation', 'variations', 'offers', 'url'] as $k) {
            if (array_key_exists($k, $dadosBrutos)) {
                $picked[$k] = $dadosBrutos[$k];
            }
        }
        if (empty($picked)) {
            $picked = $dadosBrutos;
        }
        return (array) $this->truncateForPrompt($picked, 0);
    }

    private function getAssessoriaJobsDir(): string {
        $base = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $dir = $base . DIRECTORY_SEPARATOR . 'assessoria_jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function getJobFilePath(string $jobId): string {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId);
        return $this->getAssessoriaJobsDir() . DIRECTORY_SEPARATOR . 'job_' . $safe . '.json';
    }

    private function writeJobFile(string $jobId, array $data): void {
        $path = $this->getJobFilePath($jobId);
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        @flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data));
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function readJobFile(string $jobId): ?array {
        $path = $this->getJobFilePath($jobId);
        if (!file_exists($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }

    private function startBackgroundProcessing(array $links, string $jobId): void {
        $job = [
            'job_id' => $jobId,
            'status' => 'running',
            'total' => count($links),
            'processed' => 0,
            'links' => $links,
            'produtos' => [],
            'erros' => [],
            'started_at' => date('Y-m-d H:i:s'),
            'finished_at' => null
        ];
        $this->writeJobFile($jobId, $job);

        foreach ($links as $link) {
            try {
                $resultado = $this->processarLinkIndividual((string) $link);
                if (!empty($resultado['success'])) {
                    $job['produtos'][] = $resultado['data'];
                } else {
                    $job['erros'][] = [
                        'link' => (string) $link,
                        'error' => (string) ($resultado['error'] ?? 'Erro ao processar link')
                    ];
                }
            } catch (\Exception $e) {
                $job['erros'][] = [
                    'link' => (string) $link,
                    'error' => $e->getMessage()
                ];
            }

            $job['processed'] = (int) $job['processed'] + 1;
            $this->writeJobFile($jobId, $job);
        }

        $job['status'] = 'done';
        $job['finished_at'] = date('Y-m-d H:i:s');
        $this->writeJobFile($jobId, $job);
    }

    public function processarJobPorId(string $jobId): void {
        $job = $this->readJobFile($jobId);
        if ($job === null) {
            return;
        }
        $links = $job['links'] ?? [];
        if (!is_array($links) || empty($links)) {
            return;
        }
        $this->startBackgroundProcessing($links, $jobId);
    }

    private function trySpawnJobWorker(string $jobId): bool {
        $root = rtrim((string) dirname(__DIR__, 3), DIRECTORY_SEPARATOR);
        $worker = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'assessoria_worker.php';
        if (!file_exists($worker)) {
            return false;
        }

        $php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        $cmd = escapeshellcmd($php) . ' ' . escapeshellarg($worker) . ' ' . escapeshellarg($jobId);

        // Linux/Unix background
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $cmd .= ' > /dev/null 2>&1 &';
        }

        try {
            if (function_exists('proc_open')) {
                $descriptorspec = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ];
                $process = @proc_open($cmd, $descriptorspec, $pipes, $root);
                if (is_resource($process)) {
                    foreach ($pipes as $p) {
                        @fclose($p);
                    }
                    @proc_close($process);
                    return true;
                }
            }

            @exec($cmd);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Processa os links enviados via AJAX
     */
    public function processarLinks(Request $request) {
        header('Content-Type: application/json');
        
        try {
            $body = $request->getBody();
            $links = $body['links'] ?? [];
            
            if (empty($links)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Nenhum link fornecido'
                ]);
                return;
            }
            
            // Validação básica dos links
            foreach ($links as $link) {
                if (!filter_var($link, FILTER_VALIDATE_URL)) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Link inválido: {$link}"
                    ]);
                    return;
                }
            }
            
            // Processa cada link separadamente
            $resultados = [];
            $erros = [];
            
            foreach ($links as $index => $link) {
                try {
                    $resultado = $this->processarLinkIndividual($link);
                    if ($resultado['success']) {
                        $resultados[] = $resultado['data'];
                    } else {
                        $erros[] = [
                            'link' => $link,
                            'error' => $resultado['error']
                        ];
                    }
                } catch (\Exception $e) {
                    $erros[] = [
                        'link' => $link,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Armazena resultados na sessão para o checkout
            $_SESSION['assessoria_orcamento'] = [
                'produtos' => $resultados,
                'erros' => $erros,
                'data_criacao' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'produtos' => $resultados,
                    'erros' => $erros,
                    'total_produtos' => count($resultados),
                    'total_erros' => count($erros)
                ]
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao processar links: ' . $e->getMessage()
            ]);
        }
    }

    public function processarLinkUnico(Request $request) {
        header('Content-Type: application/json');
        session_start();

        try {
            $body = $request->getBody();
            $link = (string) ($body['link'] ?? '');
            $reset = (bool) ($body['reset'] ?? false);

            if ($reset || !isset($_SESSION['assessoria_orcamento'])) {
                $_SESSION['assessoria_orcamento'] = [
                    'produtos' => [],
                    'erros' => [],
                    'data_criacao' => date('Y-m-d H:i:s')
                ];
            }

            $link = trim($link);
            if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Link inválido'
                ]);
                return;
            }

            $resultado = $this->processarLinkIndividual($link);
            if ($resultado['success']) {
                $_SESSION['assessoria_orcamento']['produtos'][] = $resultado['data'];
            } else {
                $_SESSION['assessoria_orcamento']['erros'][] = [
                    'link' => $link,
                    'error' => $resultado['error']
                ];
            }

            $produtos = $_SESSION['assessoria_orcamento']['produtos'] ?? [];
            $erros = $_SESSION['assessoria_orcamento']['erros'] ?? [];

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_produtos' => count($produtos),
                    'total_erros' => count($erros)
                ]
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao processar link: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Processa um link individual via ScrapingBee
     */
    private function processarLinkIndividual(string $url): array {
        $scriptbeeApiKey = $this->getScriptBeeApiKey();
        
        if (!$scriptbeeApiKey) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-Error: API Key not configured');
            }
            return [
                'success' => false,
                'error' => 'API Key do ScrapingBee não configurada'
            ];
        }
        
        $requestUrl = 'https://app.scrapingbee.com/api/v1';

        $buildUrl = function(array $override = []) use ($requestUrl, $scriptbeeApiKey, $url) {
            $params = array_merge([
                'api_key' => $scriptbeeApiKey,
                'url' => $url,
                'stealth_proxy' => 'true',
                'country_code' => 'us',
                // Default mais rápido para evitar timeout no proxy
                'wait_browser' => 'domcontentloaded',
                'block_ads' => 'true',
                'ai_query' => 'Extract all available product information, including product name, image, base price, and all variations. Each variation must include size, weight, or any selectable attribute, its value, and price if different. Preserve measurement units and return missing data as null.'
            ], $override);
            return $requestUrl . '?' . http_build_query($params);
        };

        $fullUrl = $buildUrl();
        
        // Log da requisição
        if (headers_sent() === false) {
            header('X-ScrapingBee-Request-URL: ' . $this->headerSafeValue(substr($fullUrl, 0, 200), 200));
        }
        
        $doRequest = function(string $targetUrl, int $timeoutSeconds) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $targetUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            return [$response, $httpCode, $curlErrno, $curlError];
        };

        // 1 tentativa (até 60s) por produto
        [$response, $httpCode, $curlErrno, $curlError] = $doRequest($fullUrl, 60);
        
        // Log da resposta
        if (headers_sent() === false) {
            header('X-ScrapingBee-HTTP-Code: ' . $httpCode);
            header('X-ScrapingBee-Response-Length: ' . strlen($response));
            header('X-ScrapingBee-Response-Prefix: ' . $this->headerSafeValue(substr((string) $response, 0, 200), 200));
            header('X-ScrapingBee-CURL-Errno: ' . $curlErrno);
        }
        
        if ($curlError) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-CURL-Error: ' . $curlError);
            }
            
            // Mensagem amigável para timeout
            $errorMessage = 'Erro na requisição cURL: ' . $curlError;
            if ($curlErrno === 28 || strpos($curlError, 'timeout') !== false) {
                if (headers_sent() === false) {
                    header('X-ScrapingBee-Timeout: true');
                }
                $errorMessage = 'Timeout ao processar este site. Tente novamente (1 link por vez) ou use outro link.';
            }
            
            return [
                'success' => false,
                'error' => $errorMessage
            ];
        }
        
        if ($httpCode !== 200) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-HTTP-Error: ' . $this->headerSafeValue(substr((string) $response, 0, 500), 500));
            }
            return [
                'success' => false,
                'error' => "Erro HTTP {$httpCode}: " . substr($response, 0, 500)
            ];
        }
        
        if (empty($response)) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-Empty-Response: true');
            }
            return [
                'success' => false,
                'error' => 'Resposta vazia da API'
            ];
        }
        
        // Tentar decodificar JSON
        $decodedResponse = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-JSON-Error: ' . json_last_error_msg());
                header('X-ScrapingBee-Response-Raw: ' . $this->headerSafeValue(substr((string) $response, 0, 500), 500));
            }
            return [
                'success' => false,
                'error' => 'Resposta não é JSON válido: ' . json_last_error_msg()
            ];
        }
        
        // Log da estrutura do JSON
        if (headers_sent() === false) {
            header('X-ScrapingBee-JSON-Keys: ' . json_encode(array_keys(is_array($decodedResponse) ? $decodedResponse : [])));
            header('X-ScrapingBee-JSON-Type: ' . gettype($decodedResponse));
        }
        
        try {
            // Usar ChatGPT para analisar os dados brutos
            $produto = $this->analisarComChatGPT($decodedResponse, $url);
            
            return [
                'success' => true,
                'data' => $produto
            ];
        } catch (\Exception $e) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-Normalization-Error: ' . $e->getMessage());
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Normaliza os dados do produto vindos do ScrapingBee
     */
    private function normalizarDadosProduto(array $data, string $urlOriginal): array {
        // Extrai dados com fallbacks
        $titulo = $this->extrairCampo($data, ['title', 'name', 'product_name']);
        $preco = $this->extrairCampo($data, ['price', 'amount', 'value']);
        $imagem = $this->extrairCampo($data, ['image', 'images', 'picture', 'photo']);
        $descricao = $this->extrairCampo($data, ['description', 'details', 'summary']);
        $peso = $this->extrairCampo($data, ['weight', 'shipping_weight']);
        $sku = $this->extrairCampo($data, ['sku', 'model', 'item_number']);
        
        // Validações obrigatórias
        if (empty($titulo)) {
            throw new \Exception('Título do produto não encontrado');
        }
        
        if (empty($preco)) {
            throw new \Exception('Preço do produto não encontrado');
        }
        
        // Limpa e formata o preço
        $precoNumerico = $this->limparPreco($preco);
        if ($precoNumerico <= 0) {
            throw new \Exception('Preço inválido: ' . $preco);
        }
        
        // Gera SKU automático se não existir
        if (empty($sku)) {
            $sku = 'SCRAP-' . strtoupper(substr(md5($urlOriginal), 0, 8));
        }
        
        // Formata o peso (estimativa se não encontrado)
        $pesoNumerico = $this->extrairPesoNumerico($peso);
        if ($pesoNumerico <= 0) {
            $pesoNumerico = 0.5; // Padrão estimado
        }
        
        // Normaliza imagem para array
        $imagensArray = $this->normalizarImagens($imagem);
        
        return [
            'sku' => $sku,
            'nome' => trim($titulo),
            'descricao' => trim($descricao ?: 'Produto obtido via scraping'),
            'valor' => $precoNumerico,
            'moeda' => 'USD', // Padrão USD
            'peso' => $pesoNumerico,
            'imagens' => $imagensArray,
            'url_original' => $urlOriginal,
            'data_scraping' => date('Y-m-d H:i:s'),
            'fonte' => 'scrapingbee'
        ];
    }
    
    /**
     * Extrai campo de dados com múltiplos nomes possíveis
     */
    private function extrairCampo(array $data, array $possiveisNomes): ?string {
        foreach ($possiveisNomes as $nome) {
            if (isset($data[$nome])) {
                $valor = $data[$nome];
                if (is_array($valor)) {
                    // Pega o primeiro valor do array
                    $valor = reset($valor);
                }
                return is_string($valor) ? trim($valor) : (string) $valor;
            }
        }
        return null;
    }
    
    /**
     * Limpa e converte preço para número
     */
    private function limparPreco(string $preco): float {
        // Remove símbolos de moeda, espaços e formatação
        $precoLimpo = preg_replace('/[^0-9.,]/', '', $preco);
        $precoLimpo = str_replace(',', '.', preg_replace('/[,.](?=.*[,.])/', '', $precoLimpo));
        
        return floatval($precoLimpo);
    }
    
    /**
     * Extrai peso numérico de strings
     */
    private function extrairPesoNumerico(?string $peso): float {
        if (empty($peso)) {
            return 0;
        }
        
        // Procura por números seguidos de unidade (kg, g, lb, oz)
        if (preg_match('/(\d+\.?\d*)\s*(kg|g|lb|oz)/i', $peso, $matches)) {
            $valor = floatval($matches[1]);
            $unidade = strtolower($matches[2]);
            
            // Converte para kg
            switch ($unidade) {
                case 'g':
                    return $valor / 1000;
                case 'lb':
                    return $valor * 0.453592;
                case 'oz':
                    return $valor * 0.0283495;
                case 'kg':
                default:
                    return $valor;
            }
        }
        
        // Se não encontrar padrão, tenta extrair apenas números
        if (preg_match('/(\d+\.?\d*)/', $peso, $matches)) {
            return floatval($matches[1]);
        }
        
        return 0;
    }
    
    /**
     * Normaliza campo de imagens para array
     */
    private function normalizarImagens($imagem): array {
        if (empty($imagem)) {
            return [];
        }
        
        if (is_string($imagem)) {
            return [$imagem];
        }
        
        if (is_array($imagem)) {
            return array_values(array_filter($imagem));
        }
        
        return [];
    }
    
    /**
     * Obtém a API Key do ScrapingBee
     */
    private function getScriptBeeApiKey(): ?string {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['scrapingbee_api_key']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $apiKey = $row ? $row['valor'] : null;
            
            // Log no console via header (será capturado pelo JavaScript)
            if (headers_sent() === false) {
                header('X-ScrapingBee-Debug: API Key ' . ($apiKey ? 'found' : 'not found'));
                header('X-ScrapingBee-Data: ' . json_encode([
                    'api_key_found' => !empty($apiKey),
                    'api_key_length' => $apiKey ? strlen($apiKey) : 0
                ]));
            }
            
            return $apiKey;
        } catch (\Exception $e) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-Error: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Exibe a página de orçamento formalizado
     */
    public function orcamento(Request $request) {
        session_start();

        $jobId = (string) $request->getParam('job_id', '');
        if ($jobId === '') {
            $jobId = (string) ($_SESSION['assessoria_job_id'] ?? '');
        }

        if (!isset($_SESSION['assessoria_orcamento']) && $jobId === '') {
            header('Location: /assessoria');
            exit;
        }

        $job = null;
        if ($jobId !== '') {
            $job = $this->readJobFile($jobId);
            if (is_array($job) && (($job['status'] ?? '') === 'done')) {
                if (!isset($_SESSION['assessoria_orcamento']) || !is_array($_SESSION['assessoria_orcamento'])) {
                    $_SESSION['assessoria_orcamento'] = [
                        'produtos' => [],
                        'erros' => [],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
                $_SESSION['assessoria_orcamento']['produtos'] = $job['produtos'] ?? [];
                $_SESSION['assessoria_orcamento']['erros'] = $job['erros'] ?? [];
            }
        }

        $orcamento = $_SESSION['assessoria_orcamento'] ?? ['produtos' => [], 'erros' => [], 'data_criacao' => date('Y-m-d H:i:s')];
        
        // Calcula totais usando taxas existentes
        $totais = $this->calcularTotaisOrcamento($orcamento['produtos']);
        
        $this->view('assessoria/orcamento', [
            'orcamento' => $orcamento,
            'totais' => $totais,
            'job_id' => $jobId,
            'job' => $job
        ]);
    }
    
    /**
     * Calcula totais do orçamento reutilizando taxas existentes
     */
    private function calcularTotaisOrcamento(array $produtos): array {
        $subtotal = 0;
        $pesoTotal = 0;
        
        foreach ($produtos as $produto) {
            $subtotal += $produto['valor'];
            $pesoTotal += $produto['peso'];
        }
        
        // Reutiliza funções de cálculo existentes
        $taxaServico = $this->getTaxaServicoPorKg() * $pesoTotal;
        $frete = $this->calcularFrete($subtotal, $pesoTotal);
        $impostos = $this->calcularImpostos($subtotal);
        
        return [
            'subtotal' => $subtotal,
            'peso_total' => $pesoTotal,
            'taxa_servico' => $taxaServico,
            'frete' => $frete,
            'impostos' => $impostos,
            'total' => $subtotal + $taxaServico + $frete + $impostos
        ];
    }
    
    /**
     * Obtém taxa de serviço por kg (reutiliza lógica existente)
     */
    private function getTaxaServicoPorKg(): float {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['taxa_servico_usd_por_kg']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return floatval($row ? $row['valor'] : 39);
        } catch (\Exception $e) {
            return 39.0;
        }
    }
    
    /**
     * Calcula frete (reutiliza lógica do CarrinhoController)
     */
    private function calcularFrete(float $subtotal, float $pesoTotal): float {
        try {
            $db = \Config\Database::getConnection();
            
            // Verifica se cálculo automático está ativo
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['entrega_calcular_automatico']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $calcularAutomatico = ($row && ($row['valor'] === '1' || strtolower($row['valor']) === 'true'));
            
            if (!$calcularAutomatico) {
                return 0.0;
            }
            
            // Verifica frete grátis
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['entrega_frete_gratis_acima']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $freteGratisAcima = floatval($row ? $row['valor'] : 0);
            
            if ($freteGratisAcima > 0 && $subtotal >= $freteGratisAcima) {
                return 0.0;
            }
            
            // Calcula frete por kg
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['entrega_frete_padrao']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $fretePorKg = floatval($row ? $row['valor'] : 15);
            
            if ($fretePorKg <= 0) {
                return 0.0;
            }
            
            $pesoArredondado = ceil($pesoTotal);
            return $fretePorKg * $pesoArredondado;
            
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * Calcula impostos (reutiliza configurações existentes)
     */
    private function calcularImpostos(float $subtotal): float {
        try {
            $db = \Config\Database::getConnection();
            
            // ICMS
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['icms_aliquota']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $icms = floatval($row ? $row['valor'] : 60);
            
            // IPI
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['ipi_aliquota']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $ipi = floatval($row ? $row['valor'] : 20);
            
            return ($subtotal * $icms / 100) + ($subtotal * $ipi / 100);
            
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * Adiciona produtos do orçamento ao carrinho existente
     */
    public function adicionarAoCarrinho(Request $request) {
        header('Content-Type: application/json');
        
        session_start();
        
        if (!isset($_SESSION['assessoria_orcamento'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Orçamento não encontrado'
            ]);
            return;
        }
        
        $body = $request->getBody();
        $termosAceitos = $body['termos_aceitos'] ?? false;
        $produtosSelecionados = $body['produtos_selecionados'] ?? [];
        
        if (!$termosAceitos) {
            echo json_encode([
                'success' => false,
                'message' => 'É necessário aceitar os termos para prosseguir'
            ]);
            return;
        }
        
        if (empty($produtosSelecionados)) {
            echo json_encode([
                'success' => false,
                'message' => 'Nenhum produto selecionado'
            ]);
            return;
        }
        
        try {
            // Inicializa carrinho se não existir
            if (!isset($_SESSION['carrinho'])) {
                $_SESSION['carrinho'] = [];
            }
            
            $orcamento = $_SESSION['assessoria_orcamento'];
            $produtosAdicionados = 0;
            
            foreach ($produtosSelecionados as $produtoIndex) {
                if (isset($orcamento['produtos'][$produtoIndex])) {
                    $produto = $orcamento['produtos'][$produtoIndex];

                    $produtoId = $this->criarOuReutilizarProdutoNoSistema($produto);

                    $itemKey = (string) $produtoId;
                    $quantidade = 1;

                    if (isset($_SESSION['carrinho'][$itemKey])) {
                        $_SESSION['carrinho'][$itemKey]['quantidade'] += $quantidade;
                        $preco = floatval($_SESSION['carrinho'][$itemKey]['preco_unitario'] ?? 0);
                        $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $preco;
                    } else {
                        $preco = floatval($produto['valor'] ?? 0);
                        $_SESSION['carrinho'][$itemKey] = [
                            'produto_id' => $produtoId,
                            'nome' => (string) ($produto['nome'] ?? ''),
                            'preco_unitario' => $preco,
                            'quantidade' => $quantidade,
                            'subtotal' => $quantidade * $preco
                        ];
                    }
                    
                    $produtosAdicionados++;
                }
            }
            
            // Limpa orçamento da sessão
            unset($_SESSION['assessoria_orcamento']);
            
            echo json_encode([
                'success' => true,
                'message' => "{$produtosAdicionados} produtos adicionados ao carrinho",
                'redirect' => '/carrinho'
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao adicionar produtos ao carrinho: ' . $e->getMessage()
            ]);
        }
    }

    private function criarOuReutilizarProdutoNoSistema(array $produto): int {
        $db = \Config\Database::getConnection();

        $nome = trim((string) ($produto['nome'] ?? ''));
        if ($nome === '') {
            throw new \Exception('Produto sem nome');
        }

        $sku = trim((string) ($produto['sku'] ?? ''));
        if ($sku === '') {
            $sku = 'ASS-' . substr(md5($nome . '|' . ((string) ($produto['url_original'] ?? ''))), 0, 10);
        }

        try {
            $stmt = $db->prepare('SELECT id FROM produtos WHERE sku = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$sku]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                return (int) $existingId;
            }
        } catch (\Exception $e) {
        }

        $preco = floatval($produto['valor'] ?? 0);
        $peso = floatval($produto['peso'] ?? 0.5);
        $descricao = (string) ($produto['descricao'] ?? '');

        $produtoModel = new Produto();
        $newId = (int) $produtoModel->create([
            'name' => $nome,
            'sku' => $sku,
            'description' => $descricao,
            'price' => $preco,
            'weight' => $peso,
            'status' => 'published',
            'stock' => 999999,
            'category_id' => null,
            'images' => $produto['imagens'] ?? [],
            'attributes' => [
                'fonte' => 'assessoria',
                'url_original' => (string) ($produto['url_original'] ?? '')
            ]
        ]);

        if ($newId <= 0) {
            throw new \Exception('Falha ao criar produto no sistema');
        }

        return $newId;
    }
    
    /**
     * Analisa dados brutos do ScrapingBee usando ChatGPT
     */
    private function analisarComChatGPT(array $dadosBrutos, string $urlOriginal): array {
        $chatGptApiKey = $this->getChatGPTApiKey();
        
        if (!$chatGptApiKey) {
            if (headers_sent() === false) {
                header('X-ChatGPT-Error: API Key not configured');
            }
            throw new \Exception('API Key do ChatGPT não configurada');
        }
        
        $prompt = $this->gerarPromptChatGPT($this->reduceScrapingBeePayload($dadosBrutos), $urlOriginal);
        
        if (headers_sent() === false) {
            header('X-ChatGPT-Prompt-Length: ' . strlen($prompt));
        }
        
        $basePayload = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Retorne apenas JSON válido, sem comentários e sem marcações.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.0,
            'max_tokens' => 800
        ];

        $payloadWithFormat = $basePayload;
        $payloadWithFormat['response_format'] = ['type' => 'json_object'];

        $send = function(array $payload) use ($chatGptApiKey) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $chatGptApiKey
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 45,
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            return [$resp, $code, $err];
        };

        [$response, $httpCode, $curlError] = $send($payloadWithFormat);
        if ($httpCode === 400 && is_string($response) && (stripos($response, 'response_format') !== false || stripos($response, 'json_object') !== false)) {
            [$response, $httpCode, $curlError] = $send($basePayload);
        }
        
        if (headers_sent() === false) {
            header('X-ChatGPT-HTTP-Code: ' . $httpCode);
            header('X-ChatGPT-Response-Length: ' . strlen($response));
        }
        
        if ($curlError) {
            if (headers_sent() === false) {
                header('X-ChatGPT-CURL-Error: ' . $curlError);
            }
            
            // Mensagem amigável para timeout
            $errorMessage = 'Erro na requisição ChatGPT: ' . $curlError;
            if (strpos($curlError, 'timeout') !== false) {
                $errorMessage = 'O serviço de análise demorou muito para responder. Tente novamente.';
            }
            
            throw new \Exception($errorMessage);
        }
        
        if ($httpCode !== 200) {
            if (headers_sent() === false) {
                header('X-ChatGPT-HTTP-Error: ' . $this->headerSafeValue(substr((string) $response, 0, 500), 500));
            }
            throw new \Exception('Erro HTTP ChatGPT ' . $httpCode . ': ' . substr($response, 0, 500));
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (headers_sent() === false) {
                header('X-ChatGPT-JSON-Error: ' . json_last_error_msg());
            }
            throw new \Exception('Resposta ChatGPT não é JSON válido: ' . json_last_error_msg());
        }
        
        $content = $decodedResponse['choices'][0]['message']['content'] ?? '';
        
        if (headers_sent() === false) {
            header('X-ChatGPT-Content-Length: ' . strlen($content));
            header('X-ChatGPT-Content-Prefix: ' . $this->headerSafeValue(substr((string) $content, 0, 200), 200));
        }
        
        // Tentar fazer parse do JSON retornado pelo ChatGPT (com correções)
        try {
            $produtoData = $this->decodeJsonResilient((string) $content);
        } catch (\Exception $e) {
            if (headers_sent() === false) {
                header('X-ChatGPT-Parse-Error: ' . $this->headerSafeValue($e->getMessage(), 200));
                header('X-ChatGPT-Raw-Content: ' . $this->headerSafeValue(substr((string) $content, 0, 500), 500));
            }
            throw $e;
        }
        
        // Validar campos obrigatórios
        $camposObrigatorios = ['nome', 'valor', 'peso', 'descricao', 'imagens'];
        foreach ($camposObrigatorios as $campo) {
            if (!isset($produtoData[$campo]) || empty($produtoData[$campo])) {
                if (headers_sent() === false) {
                    header('X-ChatGPT-Missing-Field: ' . $campo);
                }
                throw new \Exception("Campo obrigatório '{$campo}' não encontrado ou vazio");
            }
        }

        // Garantir url_original e filtrar apenas campos essenciais para persistência
        if (!isset($produtoData['url_original']) || (string) $produtoData['url_original'] === '') {
            $produtoData['url_original'] = $urlOriginal;
        }
        if (!isset($produtoData['sku'])) {
            $produtoData['sku'] = '';
        }
        if (!isset($produtoData['imagens']) || !is_array($produtoData['imagens'])) {
            $produtoData['imagens'] = [];
        }

        $produtoData = [
            'sku' => (string) ($produtoData['sku'] ?? ''),
            'nome' => (string) ($produtoData['nome'] ?? ''),
            'descricao' => (string) ($produtoData['descricao'] ?? ''),
            'valor' => floatval($produtoData['valor'] ?? 0),
            'peso' => floatval($produtoData['peso'] ?? 0),
            'imagens' => $produtoData['imagens'] ?? [],
            'url_original' => (string) ($produtoData['url_original'] ?? $urlOriginal)
        ];
        
        if (headers_sent() === false) {
            header('X-ChatGPT-Success: true');
            header('X-ChatGPT-Product-Data: ' . json_encode([
                'nome' => $produtoData['nome'],
                'valor' => $produtoData['valor'],
                'peso' => $produtoData['peso'],
                'descricao' => $produtoData['descricao'],
                'imagens_count' => is_array($produtoData['imagens']) ? count($produtoData['imagens']) : 0
            ]));
        }
        
        return $produtoData;
    }
    
    /**
     * Gera o prompt para o ChatGPT
     */
    private function gerarPromptChatGPT(array $dadosBrutos, string $urlOriginal): string {
        return "Analise os dados brutos abaixo extraídos da URL: {$urlOriginal}

DADOS BRUTOS:
" . json_encode($dadosBrutos, JSON_PRETTY_PRINT) . "

EU PRECISO QUE VOCÊ EXTRAIA AS INFORMAÇÕES DO PRODUTO E RETORNE APENAS JSON VÁLIDO (SEM TEXTO, SEM MARKDOWN, SEM ```), COM ESTA ESTRUTURA EXATA E SOMENTE ESTES CAMPOS:

{
    \"sku\": \"SKU do produto ou código único (se não achar, pode retornar string vazia)\",
    \"nome\": \"Nome completo do produto\",
    \"descricao\": \"Descrição detalhada do produto\",
    \"valor\": 99.99,
    \"peso\": 1.5,
    \"imagens\": [\"url1\", \"url2\"],
    \"url_original\": \"{$urlOriginal}\"
}

REGRAS ESPECÍFICAS:

1. CAMPOS OBRIGATÓRIOS: nome, imagem, valor, peso, descricao
2. PESO (kg): 
   - Se não encontrar o peso exato, ESTIME com base no tipo de produto
   - Adicione 15% de margem de segurança sobre o peso estimado
   - Ex: se estimar 1kg, use 1.15kg
   - Use sempre casas decimais (ex: 1.15)

3. DESCRIÇÃO:
   - Se não encontrar descrição detalhada, CRIE uma baseada no nome e características
   - Inclua informações relevantes sobre o produto
   - Seja específico e útil para o cliente

4. IMAGEM:
   - Extraia todas as URLs de imagens disponíveis
   - Se não encontrar, use array vazio []

5. VALOR: Use número decimal com 2 casas (ex: 99.99)

6. NOME: Use o nome completo do produto

IMPORTANTE:
- Retorne APENAS o JSON, sem texto adicional
- Para peso, SEMPRE inclua a margem de 15% se precisar estimar
- Para descrição, CRIE uma se não encontrar
- SKU pode ser gerado baseado no nome se não existir
- Use kg para peso, cm para dimensões

RETORNE APENAS O JSON:";
    }
    
    /**
     * Obtém a API Key do ChatGPT
     */
    private function getChatGPTApiKey(): ?string {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['chatgpt_api_key']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $apiKey = $row ? $row['valor'] : null;
            
            // Log no console via header
            if (headers_sent() === false) {
                header('X-ChatGPT-Debug: API Key ' . ($apiKey ? 'found' : 'not found'));
                header('X-ChatGPT-Key-Length: ' . strlen($apiKey ?? ''));
            }
            
            return $apiKey;
        } catch (\Exception $e) {
            if (headers_sent() === false) {
                header('X-ChatGPT-Error: ' . $e->getMessage());
            }
            return null;
        }
    }
}
