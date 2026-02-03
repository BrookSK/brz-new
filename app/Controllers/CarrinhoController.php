<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Models\Produto;
use App\Models\ProdutoFoto;
use App\Services\AuthService;

class CarrinhoController extends Controller {
    private $produtoModel;
    private $produtoFotoModel;
    private $authService;

    private function getConfigValue(string $chave, $default = null) {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }
        return $default;
    }

    private function calcularFrete(float $subtotal, float $pesoTotal, string $moeda = 'USD'): float {
        $calcularAutomatico = $this->getConfigValue('entrega_calcular_automatico', '1');
        $calcularAutomatico = ($calcularAutomatico === '1' || strtolower((string) $calcularAutomatico) === 'true');
        if (!$calcularAutomatico) {
            return 0.0;
        }

        $freteGratisAcima = floatval($this->getConfigValue('entrega_frete_gratis_acima', '0'));
        if ($freteGratisAcima <= 0 || $subtotal >= $freteGratisAcima) {
            return 0.0;
        }

        $fretePorKg = floatval($this->getConfigValue('entrega_frete_padrao', '15'));
        if ($fretePorKg <= 0) {
            return 0.0;
        }

        $pesoArredondado = ceil($pesoTotal);
        return $fretePorKg * $pesoArredondado;
    }

    private function debugLog(string $message): void {
        $enabled = false;
        if (isset($_ENV['APP_DEBUG'])) {
            $enabled = ($_ENV['APP_DEBUG'] === '1' || strtolower((string) $_ENV['APP_DEBUG']) === 'true');
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $enabled = ($_SERVER['APP_DEBUG'] === '1' || strtolower((string) $_SERVER['APP_DEBUG']) === 'true');
        }

        if ($enabled) {
            error_log($message);
        }
    }

    public function __construct() {
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
        $this->authService = new AuthService();
    }

    public function index(Request $request) {
        session_start();
        $carrinho = $_SESSION['carrinho'] ?? [];
        
        if (empty($carrinho)) {
            $this->view('carrinho/vazio');
            return;
        }
        
        // Obter detalhes completos dos produtos
        $produtosDetalhados = [];
        $subtotal = 0;
        $pesoTotal = 0;

        $removedExpired = false;
        
        foreach ($carrinho as $k => $item) {
            $this->debugLog('[CARRINHO] Processando item: ' . json_encode($item));
            
            $produto = $this->produtoModel->find($item['produto_id']);
            
            if ($produto) {
                $this->debugLog('[CARRINHO] Produto encontrado: ' . json_encode($produto));
                
                $fotoPrincipal = $this->produtoFotoModel->getFotoPrincipal($produto['id']);
                
                // Verificar e corrigir URL da foto
                $fotoUrl = null;
                if ($fotoPrincipal && !empty($fotoPrincipal['nome_arquivo'])) {
                    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($fotoPrincipal['nome_arquivo'], '/');
                    if (file_exists($filePath)) {
                        $fotoUrl = Url::absolute($fotoPrincipal['nome_arquivo']);
                    }
                }
                
                // Usar campos mapeados do Model
                $itemPrice = floatval($produto['preco'] ?? 0);
                $itemStock = intval($produto['estoque'] ?? 0);
                $itemSubtotal = $item['quantidade'] * $itemPrice;
                
                $this->debugLog('[CARRINHO] Preco: ' . $itemPrice . ', Quantidade: ' . $item['quantidade'] . ', Subtotal: ' . $itemSubtotal);
                
                $produtosDetalhados[] = [
                    'produto_id' => $item['produto_id'],
                    'sku' => $produto['sku'],
                    'name' => $produto['nome'],
                    'description' => $produto['descricao'],
                    'price' => $itemPrice,
                    'weight' => $produto['peso'],
                    'quantidade' => $item['quantidade'],
                    'subtotal' => $itemSubtotal,
                    'foto_principal' => $fotoUrl,
                    'stock' => $itemStock
                ];
                
                $subtotal += $itemSubtotal;
                $pesoTotal += $item['quantidade'] * floatval($produto['peso'] ?? 0.5);
            } else {
                $this->debugLog('[CARRINHO] Produto nao encontrado: ' . $item['produto_id']);

                // Produto expirou/foi removido (ex.: temporário da assessoria). Remove do carrinho.
                if (isset($_SESSION['carrinho'][$k])) {
                    unset($_SESSION['carrinho'][$k]);
                    $removedExpired = true;
                }
            }
        }

        if ($removedExpired) {
            $_SESSION['message'] = 'Alguns itens do carrinho expiraram e foram removidos. Se eram itens da Assessoria, reprocessse o orçamento para gerar novos valores e produtos.';
            $_SESSION['message_type'] = 'warning';

            $carrinho = $_SESSION['carrinho'] ?? [];
            if (empty($carrinho)) {
                $this->view('carrinho/vazio');
                return;
            }
        }
        
        // Calcular taxas e impostos com arredondamento
        $pesoArredondado = ceil($pesoTotal); // Arredondar para cima
        $taxaServico = $pesoArredondado * 39; // US$39 por kg arredondado
        $impostos = $subtotal * 0.80; // 80% de impostos
        $frete = $this->calcularFrete($subtotal, $pesoTotal, 'USD');
        
        $total = $subtotal + $taxaServico + $impostos + $frete;
        
        $this->view('carrinho/index', [
            'carrinho' => $carrinho,
            'produtosDetalhados' => $produtosDetalhados,
            'subtotal' => $subtotal,
            'taxa_servico' => $taxaServico,
            'impostos' => $impostos,
            'frete' => $frete,
            'peso_total' => $pesoTotal,
            'total' => $total,
            'total_itens' => array_sum(array_column($carrinho, 'quantidade'))
        ]);
    }

    public function adicionar(Request $request) {
        $this->debugLog('=== DEPURACAO CARRINHO ADICIONAR ===');
        $this->debugLog('Metodo: ' . $request->getMethod());
        $this->debugLog('Parametros recebidos: ' . json_encode($request->getParams()));
        
        $produtoId = $request->getParam('id');
        $quantidade = $request->getParam('quantidade', 1);
        $produtoVariacaoId = $request->getParam('produto_variacao_id', null);
        $variacaoDescricao = $request->getParam('variacao_descricao', null);
        
        $this->debugLog("Produto ID: $produtoId");
        $this->debugLog("Quantidade: $quantidade");
        
        if (!$produtoId) {
            $this->debugLog('ERRO: Produto nao informado');
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }
        
        session_start();
        
        // Buscar produto
        $produto = $this->produtoModel->find($produtoId);
        
        
        if (!$produto) {
            $this->debugLog('ERRO: Produto nao encontrado no banco');
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        
        $produtoStock = intval($produto['estoque'] ?? 0);
        if ($produtoStock < $quantidade) {
            $this->debugLog('ERRO: Estoque insuficiente. Estoque: ' . $produtoStock . ', Quantidade: ' . $quantidade);
            $this->json(['error' => 'Estoque insuficiente'], 400);
            return;
        }
        
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
            $this->debugLog('Criando array de carrinho vazio');
        }
        
        $pvId = null;
        if ($produtoVariacaoId !== null && $produtoVariacaoId !== '') {
            $pvId = (int) $produtoVariacaoId;
            if ($pvId <= 0) {
                $pvId = null;
            }
        }

        $itemKey = ((string) $produtoId) . ':' . ((string) ($pvId ?? 0));
        
        $itemPrice = floatval($produto['preco'] ?? $produto['valor'] ?? 0);
        
        if (isset($_SESSION['carrinho'][$itemKey])) {
            $_SESSION['carrinho'][$itemKey]['quantidade'] += $quantidade;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $itemPrice;
            $_SESSION['carrinho'][$itemKey]['price'] = $itemPrice;
            $_SESSION['carrinho'][$itemKey]['preco_unitario'] = $itemPrice;
            $this->debugLog('Atualizando item existente no carrinho');
        } else {
            $_SESSION['carrinho'][$itemKey] = [
                'produto_id' => $produtoId,
                'produto_variacao_id' => $pvId,
                'variacao_descricao' => ($variacaoDescricao !== null && $variacaoDescricao !== '' ? (string) $variacaoDescricao : null),
                'nome' => $produto['nome'],
                'price' => $itemPrice,
                'preco_unitario' => $itemPrice,
                'quantidade' => $quantidade,
                'subtotal' => $quantidade * $itemPrice
            ];
            $this->debugLog('Adicionando novo item ao carrinho');
        }
        
        $this->debugLog('Carrinho atual: ' . json_encode($_SESSION['carrinho']));
        
        $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
        $totalValor = 0;
        foreach ($_SESSION['carrinho'] as $item) {
            $totalValor += floatval($item['subtotal']);
        }
        
        $this->debugLog("Total itens: $totalItens");
        $this->debugLog("Total valor: $totalValor");
        
        $response = [
            'success' => true,
            'message' => 'Produto adicionado ao carrinho',
            'carrinho' => $_SESSION['carrinho'],
            'total_itens' => $totalItens,
            'total_valor' => $totalValor
        ];
        
        $this->debugLog('Resposta JSON: ' . json_encode($response));
        
        $this->json($response);
    }

    public function remover(Request $request) {
        session_start();
        $produtoId = $request->getParam('id', null);
        $produtoIdFallback = $request->getParam('produto_id', null);
        
        if (($produtoId === null || $produtoId === '') && ($produtoIdFallback === null || $produtoIdFallback === '')) {
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }

        if (($produtoId === null || $produtoId === '') && ($produtoIdFallback !== null && $produtoIdFallback !== '')) {
            $produtoId = $produtoIdFallback;
        }
        
        $keyToRemove = null;
        if (isset($_SESSION['carrinho'][$produtoId])) {
            $keyToRemove = $produtoId;
        } else {
            foreach (($_SESSION['carrinho'] ?? []) as $k => $item) {
                if (is_array($item) && array_key_exists('produto_id', $item) && (string) $item['produto_id'] === (string) $produtoId) {
                    $keyToRemove = $k;
                    break;
                }
            }
        }

        if ($keyToRemove !== null) {
            unset($_SESSION['carrinho'][$keyToRemove]);
            
            $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
            $totalValor = 0;
            foreach ($_SESSION['carrinho'] as $item) {
                $totalValor += floatval($item['subtotal']);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Produto removido do carrinho',
                'total_itens' => $totalItens,
                'total_valor' => $totalValor
            ]);
        } else {
            $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
        }
    }

    public function atualizar(Request $request) {
        session_start();
        $produtoId = $request->getParam('id', null);
        $produtoIdFallback = $request->getParam('produto_id', null);
        $quantidade = $request->getParam('quantidade', null);
        
        if ((($produtoId === null || $produtoId === '') && ($produtoIdFallback === null || $produtoIdFallback === '')) || ($quantidade === null || $quantidade === '')) {
            $this->json(['error' => 'Dados incompletos'], 400);
            return;
        }

        if (($produtoId === null || $produtoId === '') && ($produtoIdFallback !== null && $produtoIdFallback !== '')) {
            $produtoId = $produtoIdFallback;
        }

        $quantidade = (int) $quantidade;
        if ($quantidade < 1) {
            $this->json(['error' => 'Quantidade inválida'], 400);
            return;
        }
        
        $itemKey = null;
        $produtoIdDb = $produtoId;
        if (isset($_SESSION['carrinho'][$produtoId])) {
            // Quando vier a chave real do item (ex: "123:VAR"), atualizar por ela
            $itemKey = $produtoId;
            $produtoIdDb = (string) ($_SESSION['carrinho'][$itemKey]['produto_id'] ?? $produtoId);
        } else {
            foreach (($_SESSION['carrinho'] ?? []) as $k => $item) {
                if (is_array($item) && array_key_exists('produto_id', $item) && (string) $item['produto_id'] === (string) $produtoId) {
                    $itemKey = $k;
                    $produtoIdDb = (string) ($item['produto_id'] ?? $produtoId);
                    break;
                }
            }
        }

        if ($itemKey !== null) {
            $produto = $this->produtoModel->find($produtoIdDb);
            
            $produtoStock = intval($produto['estoque'] ?? 0);
            if ($produtoStock < $quantidade) {
                $this->json(['error' => 'Estoque insuficiente'], 400);
                return;
            }

            // Garantir preço numérico (não depender de formatação)
            $itemPrice = 0.0;
            if ($produto) {
                $itemPrice = floatval($produto['preco'] ?? $produto['valor'] ?? 0);
            }
            if ($itemPrice <= 0) {
                $itemPrice = floatval($_SESSION['carrinho'][$itemKey]['price'] ?? $_SESSION['carrinho'][$itemKey]['preco_unitario'] ?? 0);
            }

            $_SESSION['carrinho'][$itemKey]['quantidade'] = $quantidade;
            $_SESSION['carrinho'][$itemKey]['price'] = $itemPrice;
            $_SESSION['carrinho'][$itemKey]['preco_unitario'] = $itemPrice;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $quantidade * $itemPrice;
            
            $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
            $totalValor = 0;
            foreach ($_SESSION['carrinho'] as $item) {
                $totalValor += floatval($item['subtotal']);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Carrinho atualizado',
                'item_subtotal' => $_SESSION['carrinho'][$itemKey]['subtotal'],
                'total_itens' => $totalItens,
                'total_valor' => $totalValor
            ]);
        } else {
            $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
        }
    }

    public function limpar(Request $request) {
        session_start();
        unset($_SESSION['carrinho']);
        
        $this->json([
            'success' => true,
            'message' => 'Carrinho limpo com sucesso'
        ]);
    }

    public function calcular(Request $request) {
        session_start();
        $carrinho = $_SESSION['carrinho'] ?? [];
        
        if (empty($carrinho)) {
            $this->json(['error' => 'Carrinho vazio'], 400);
            return;
        }
        
        // Calcular peso total
        $pesoTotal = 0;
        foreach ($carrinho as $item) {
            $produto = $this->produtoModel->find($item['produto_id']);
            if ($produto) {
                $pesoTotal += floatval($produto['peso'] ?? 0.5) * $item['quantidade'];
            }
        }
        
        // Arredondar peso para cima (ex: 1.7kg → 2kg)
        $pesoArredondado = ceil($pesoTotal);

        // Calcular subtotal
        $subtotal = 0;
        foreach ($carrinho as $item) {
            $subtotal += floatval($item['subtotal']);
        }
        
        // Taxas fixas
        $taxaServicoPorKg = 39; // USD por kg
        $taxaServico = $taxaServicoPorKg * $pesoArredondado;
        
        // Frete baseado no peso arredondado
        $frete = $this->calcularFrete($subtotal, $pesoTotal, 'USD');
        
        // Impostos (80% sobre subtotal + taxa de serviço)
        $impostos = ($subtotal + $taxaServico) * 0.8;
        
        // Total
        $total = $subtotal + $taxaServico + $impostos + $frete;
        
        $this->json([
            'peso_total' => $pesoTotal,
            'peso_arredondado' => $pesoArredondado,
            'taxa_servico' => $taxaServico,
            'frete' => $frete,
            'impostos' => $impostos,
            'subtotal' => $subtotal,
            'total' => $total
        ]);
    }
}
