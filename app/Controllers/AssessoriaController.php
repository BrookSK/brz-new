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
        
        // Log da requisição
        if (headers_sent() === false) {
            header('X-ScrapingBee-Request-URL: ' . substr($fullUrl, 0, 200));
        }
        
        // Executa a requisição
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_TIMEOUT => 45,  // Aumentado para 45 segundos
            CURLOPT_CONNECTTIMEOUT => 10,  // Timeout de conexão 10 segundos
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Log da resposta
        if (headers_sent() === false) {
            header('X-ScrapingBee-HTTP-Code: ' . $httpCode);
            header('X-ScrapingBee-Response-Length: ' . strlen($response));
            header('X-ScrapingBee-Response-Prefix: ' . substr($response, 0, 200));
        }
        
        if ($curlError) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-CURL-Error: ' . $curlError);
            }
            
            // Mensagem amigável para timeout
            $errorMessage = 'Erro na requisição cURL: ' . $curlError;
            if (strpos($curlError, 'timeout') !== false) {
                $errorMessage = 'O servidor demorou muito para responder. Tente novamente ou use um link diferente.';
            }
            
            return [
                'success' => false,
                'error' => $errorMessage
            ];
        }
        
        if ($httpCode !== 200) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-HTTP-Error: ' . substr($response, 0, 500));
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
                header('X-ScrapingBee-Response-Raw: ' . substr($response, 0, 1000));
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
        
        // Preparar o prompt para o ChatGPT
        $prompt = $this->gerarPromptChatGPT($dadosBrutos, $urlOriginal);
        
        if (headers_sent() === false) {
            header('X-ChatGPT-Prompt-Length: ' . strlen($prompt));
        }
        
        // Fazer requisição para a API do ChatGPT
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $chatGptApiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um especialista em extração de dados de produtos de e-commerce. Analise os dados brutos fornecidos e extraia as informações necessárias no formato JSON exato solicitado.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 1000
            ]),
            CURLOPT_TIMEOUT => 45,  // Aumentado para 45 segundos
            CURLOPT_CONNECTTIMEOUT => 10  // Timeout de conexão 10 segundos
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
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
                header('X-ChatGPT-HTTP-Error: ' . substr($response, 0, 500));
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
            header('X-ChatGPT-Content-Prefix: ' . substr($content, 0, 200));
        }
        
        // Tentar fazer parse do JSON retornado pelo ChatGPT
        $produtoData = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (headers_sent() === false) {
                header('X-ChatGPT-Parse-Error: ' . json_last_error_msg());
                header('X-ChatGPT-Raw-Content: ' . $content);
            }
            throw new \Exception('ChatGPT não retornou JSON válido: ' . json_last_error_msg());
        }
        
        // Validar campos obrigatórios
        $camposObrigatorios = ['nome', 'valor', 'moeda', 'peso', 'descricao', 'imagens'];
        foreach ($camposObrigatorios as $campo) {
            if (!isset($produtoData[$campo]) || empty($produtoData[$campo])) {
                if (headers_sent() === false) {
                    header('X-ChatGPT-Missing-Field: ' . $campo);
                }
                throw new \Exception("Campo obrigatório '{$campo}' não encontrado ou vazio");
            }
        }
        
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

EU PRECISO QUE VOCÊ EXTRAIA AS INFORMAÇÕES DO PRODUTO E RETORNE APENAS JSON VÁLIDO COM ESTA ESTRUTURA EXATA:

{
    \"sku\": \"SKU do produto ou código único\",
    \"nome\": \"Nome completo do produto\",
    \"descricao\": \"Descrição detalhada do produto\",
    \"valor\": 99.99,
    \"moeda\": \"USD\",
    \"peso\": 1.5,
    \"comprimento\": 10.0,
    \"largura\": 8.0,
    \"altura\": 5.0,
    \"imagens\": [\"url1\", \"url2\"],
    \"url_original\": \"{$urlOriginal}\",
    \"data_scraping\": \"" . date('Y-m-d H:i:s') . "\",
    \"fonte\": \"chatgpt_analysis\"
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
