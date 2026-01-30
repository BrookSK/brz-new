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
            $links = $request->getBody()['links'] ?? [];
            
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
     * Obtém a API Key do ScrapingBee (compatível com múltiplas estruturas)
     */
    private function getScriptBeeApiKey(): ?string {
        try {
            $db = \Config\Database::getConnection();
            
            // Tentar diferentes estruturas de tabela/colunas
            $queries = [
                'SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1',
                'SELECT valor_config FROM configuracoes_sistema WHERE nome_chave = ? LIMIT 1', 
                'SELECT config_value FROM config_assessoria WHERE config_key = ? LIMIT 1',
                'SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1',
                'SELECT value FROM settings WHERE `key` = ? LIMIT 1'
            ];
            
            foreach ($queries as $query) {
                try {
                    $stmt = $db->prepare($query);
                    $stmt->execute(['scrapingbee_api_key']);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row)) {
                        return reset($row); // Pega primeiro valor do array
                    }
                } catch (\Exception $e) {
                    continue; // Tenta próxima query
                }
            }
            
            return null;
        } catch (\Exception $e) {
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
        
        $termosAceitos = $request->getBody()['termos_aceitos'] ?? false;
        $produtosSelecionados = $request->getBody()['produtos_selecionados'] ?? [];
        
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
