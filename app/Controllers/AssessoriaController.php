<?php
namespace App\Controllers;

use App\Core\Request;

class AssessoriaController extends Controller {
    
    /**
     * Exibe a página principal de Assessoria
     */
    public function index(Request $request) {
        session_start();
        $this->view('assessoria/index');
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
    
    /**
     * Processa um link individual usando ScrapingBee
     */
    private function processarLinkIndividual(string $url): array {
        try {
            // ===============================
            // BLOCO DE REQUEST SCRIPTBEE
            // COLE AQUI A REQUEST REAL
            // A URL DO PRODUTO VEM DO USUÁRIO
            // ===============================
            
            $scriptbeeApiKey = $this->getScriptBeeApiKey();
            if (empty($scriptbeeApiKey)) {
                throw new \Exception('API Key do ScrapingBee não configurada');
            }
            
            $requestUrl = 'https://app.scrapingbee.com/api/v1';
            $params = [
                'api_key' => $scriptbeeApiKey,
                'url' => $url,
                'stealth_proxy' => 'true',
                'country_code' => 'us',
                'wait_browser' => 'load',
                'block_ads' => 'true',
                'ai_query' => 'Extract all available product information, including product name, image, base price, and all variations. Each variation must include size, weight, or any selectable attribute, its value, and price if different. Preserve measurement units and return missing data as null.'
            ];
            
            $queryString = http_build_query($params);
            $fullUrl = $requestUrl . '?' . $queryString;
            
            // Executa a requisição
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $fullUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Tratamento de erros HTTP
            if ($curlError) {
                throw new \Exception("Erro de conexão: {$curlError}");
            }
            
            if ($httpCode !== 200) {
                throw new \Exception("Erro HTTP {$httpCode}: {$response}");
            }
            
            if (empty($response)) {
                throw new \Exception("Resposta vazia da API");
            }
            
            // Processa a resposta
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Resposta JSON inválida: " . json_last_error_msg());
            }
            
            // Verifica erros da API
            if (isset($data['error'])) {
                $errorMsg = is_string($data['error']) ? $data['error'] : 
                           (isset($data['error']['message']) ? $data['error']['message'] : 'Erro desconhecido');
                throw new \Exception("Erro API ScrapingBee: {$errorMsg}");
            }
            
            // Normaliza e valida os dados
            $produtoNormalizado = $this->normalizarDadosProduto($data, $url);
            
            return [
                'success' => true,
                'data' => $produtoNormalizado
            ];
            
        } catch (\Exception $e) {
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
     * Página de debug para analisar respostas do ScrapingBee
     */
    public function debug(Request $request) {
        try {
            session_start();
            
            // Obter logs da sessão
            $debugLogs = $_SESSION['assessoria_debug_logs'] ?? [];
            
            $this->view('assessoria/debug_simple', [
                'debugLogs' => $debugLogs,
                'currentConfig' => [
                    'api_key_configured' => !empty($this->getScriptBeeApiKey()),
                    'api_key_preview' => substr($this->getScriptBeeApiKey() ?? '', 0, 8) . '...' . substr($this->getScriptBeeApiKey() ?? '', -4)
                ]
            ]);
        } catch (\Exception $e) {
            // Em caso de erro, mostrar página simples com erro
            echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Debug Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h4>Erro no Debug</h4>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
            <a href="/assessoria" class="btn btn-primary">Voltar para Assessoria</a>
        </div>
    </div>
</body>
</html>';
        }
    }
    
    /**
     * Teste de debug para uma URL específica
     */
    public function debugTest(Request $request) {
        header('Content-Type: application/json');
        
        try {
            session_start();
            
            $body = $request->getBody();
            $url = $body['url'] ?? '';
            
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'URL inválida'
                ]);
                return;
            }
            
            // Fazer request simples
            $result = $this->processarLinkIndividualDebug($url);
            
            // Salvar log na sessão
            if (!isset($_SESSION['assessoria_debug_logs'])) {
                $_SESSION['assessoria_debug_logs'] = [];
            }
            
            $_SESSION['assessoria_debug_logs'][] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'url' => $url,
                'result' => $result
            ];
            
            // Manter apenas os últimos 10 logs
            if (count($_SESSION['assessoria_debug_logs']) > 10) {
                $_SESSION['assessoria_debug_logs'] = array_slice($_SESSION['assessoria_debug_logs'], -10);
            }
            
            echo json_encode([
                'success' => true,
                'debug' => $result['debug'] ?? null,
                'data' => $result['data'] ?? null
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Processa link individual com debug detalhado
     */
    private function processarLinkIndividualDebug(string $url): array {
        $scriptbeeApiKey = $this->getScriptBeeApiKey();
        
        if (!$scriptbeeApiKey) {
            return [
                'success' => false,
                'error' => 'API Key do ScrapingBee não configurada',
                'debug' => [
                    'api_key_check' => 'failed',
                    'api_key_value' => $scriptbeeApiKey
                ]
            ];
        }
        
        $requestUrl = 'https://app.scrapingbee.com/api/v1';
        $params = [
            'api_key' => $scriptbeeApiKey,
            'url' => $url,
            'stealth_proxy' => 'true',
            'country_code' => 'us',
            'wait_browser' => 'load',
            'block_ads' => 'true',
            'ai_query' => 'Extract all available product information, including product name, image, base price, and all variations. Each variation must include size, weight, or any selectable attribute, its value, and price if different. Preserve measurement units and return missing data as null.'
        ];
        
        $queryString = http_build_query($params);
        $fullUrl = $requestUrl . '?' . $queryString;
        
        // Executa a requisição
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $debugInfo = [
            'request_url' => $fullUrl,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'response_raw' => $response,
            'response_length' => strlen($response)
        ];
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'Erro na requisição cURL: ' . $curlError,
                'debug' => $debugInfo
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => "Erro HTTP {$httpCode}: " . substr($response, 0, 500),
                'debug' => $debugInfo
            ];
        }
        
        if (empty($response)) {
            return [
                'success' => false,
                'error' => 'Resposta vazia da API',
                'debug' => $debugInfo
            ];
        }
        
        // Tentar decodificar JSON
        $decodedResponse = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Resposta não é JSON válido: ' . json_last_error_msg(),
                'debug' => array_merge($debugInfo, [
                    'json_error' => $jsonError,
                    'json_error_message' => json_last_error_msg(),
                    'response_preview' => substr($response, 0, 1000)
                ])
            ];
        }
        
        // Analisar estrutura da resposta
        $analysis = $this->analyzeResponseStructure($decodedResponse);
        
        return [
            'success' => true,
            'data' => $decodedResponse,
            'debug' => array_merge($debugInfo, [
                'response_structure' => $analysis,
                'normalization_attempt' => $this->attemptNormalization($decodedResponse, $url)
            ])
        ];
    }
    
    /**
     * Analisa a estrutura da resposta para identificar campos disponíveis
     */
    private function analyzeResponseStructure($data): array {
        $analysis = [
            'top_level_keys' => array_keys(is_array($data) ? $data : []),
            'is_array' => is_array($data),
            'is_object' => is_object($data),
            'data_type' => gettype($data),
            'size' => is_array($data) ? count($data) : 0
        ];
        
        if (is_array($data)) {
            $analysis['sample_keys'] = array_slice(array_keys($data), 0, 10);
            
            // Procurar por campos comuns
            $commonFields = ['title', 'name', 'product', 'price', 'cost', 'image', 'photo', 'description', 'weight', 'size'];
            $foundFields = [];
            
            $this->searchFieldsRecursive($data, $commonFields, $foundFields, '');
            
            $analysis['found_common_fields'] = $foundFields;
        }
        
        return $analysis;
    }
    
    /**
     * Busca recursiva por campos comuns
     */
    private function searchFieldsRecursive($data, $commonFields, &$foundFields, $path = '') {
        if (!is_array($data)) return;
        
        foreach ($data as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : $key;
            
            // Verificar se o campo atual corresponde a algum campo comum
            foreach ($commonFields as $field) {
                if (stripos($key, $field) !== false) {
                    $foundFields[] = [
                        'path' => $currentPath,
                        'key' => $key,
                        'type' => gettype($value),
                        'value_preview' => is_string($value) ? substr($value, 0, 100) : (is_array($value) ? 'Array(' . count($value) . ')' : gettype($value)),
                        'matches_field' => $field
                    ];
                }
            }
            
            // Recursão para arrays aninhados
            if (is_array($value) && $path !== 'found_common_fields') {
                $this->searchFieldsRecursive($value, $commonFields, $foundFields, $currentPath);
            }
        }
    }
    
    /**
     * Tenta normalizar os dados com base na estrutura encontrada
     */
    private function attemptNormalization($data, string $url): array {
        try {
            $normalized = $this->normalizarDadosProduto($data, $url);
            return [
                'success' => true,
                'normalized_data' => $normalized,
                'message' => 'Normalização bem-sucedida'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Falha na normalização'
            ];
        }
    }
    private function getScriptBeeApiKey(): ?string {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['scrapingbee_api_key']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Debug temporário - remover após resolver
            error_log("ScrapingBee Debug - Row found: " . ($row ? 'yes' : 'no'));
            error_log("ScrapingBee Debug - Row data: " . json_encode($row));
            
            return $row ? $row['valor'] : null;
        } catch (\Exception $e) {
            // Debug temporário - remover após resolver
            error_log("ScrapingBee Debug - Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Exibe a página de orçamento formalizado
     */
    public function orcamento(Request $request) {
        session_start();
        
        if (!isset($_SESSION['assessoria_orcamento'])) {
            header('Location: /assessoria');
            exit;
        }
        
        $orcamento = $_SESSION['assessoria_orcamento'];
        
        // Calcula totais usando taxas existentes
        $totais = $this->calcularTotaisOrcamento($orcamento['produtos']);
        
        $this->view('assessoria/orcamento', [
            'orcamento' => $orcamento,
            'totais' => $totais
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
                    
                    // Cria ID temporário para o produto
                    $tempId = 'temp_' . uniqid();
                    
                    $_SESSION['carrinho'][] = [
                        'produto_id' => $tempId,
                        'sku' => $produto['sku'],
                        'nome' => $produto['nome'],
                        'descricao' => $produto['descricao'],
                        'valor' => $produto['valor'],
                        'moeda' => $produto['moeda'],
                        'peso' => $produto['peso'],
                        'quantidade' => 1,
                        'fonte' => 'assessoria',
                        'url_original' => $produto['url_original'],
                        'imagens' => $produto['imagens']
                    ];
                    
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
}
