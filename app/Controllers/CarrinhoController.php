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
        
        foreach ($carrinho as $item) {
            error_log('🔍 [CARRINHO] Processando item: ' . json_encode($item));
            
            $produto = $this->produtoModel->find($item['produto_id']);
            
            if ($produto) {
                error_log('🔍 [CARRINHO] Produto encontrado: ' . json_encode($produto));
                
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
                
                error_log('🔍 [CARRINHO] Preço: ' . $itemPrice . ', Quantidade: ' . $item['quantidade'] . ', Subtotal: ' . $itemSubtotal);
                
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
                error_log('❌ [CARRINHO] Produto não encontrado: ' . $item['produto_id']);
            }
        }
        
        // Calcular taxas e impostos com arredondamento
        $pesoArredondado = ceil($pesoTotal); // Arredondar para cima
        $taxaServico = $pesoArredondado * 39; // US$39 por kg arredondado
        $impostos = $subtotal * 0.80; // 80% de impostos
        $frete = $pesoArredondado * 15; // US$15 por kg arredondado
        
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
        // LOG DE DEPURAÇÃO
        error_log("=== DEPURAÇÃO CARRINHO ADICIONAR ===");
        error_log("Método: " . $request->getMethod());
        error_log("Parâmetros recebidos: " . json_encode($request->getParams()));
        
        $produtoId = $request->getParam('id');
        $quantidade = $request->getParam('quantidade', 1);
        
        error_log("Produto ID: $produtoId");
        error_log("Quantidade: $quantidade");
        
        if (!$produtoId) {
            error_log("ERRO: Produto não informado");
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }
        
        session_start();
        
        // Buscar produto
        $produto = $this->produtoModel->find($produtoId);
        
        
        if (!$produto) {
            error_log("ERRO: Produto não encontrado no banco");
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        
        $produtoStock = intval($produto['estoque'] ?? 0);
        if ($produtoStock < $quantidade) {
            error_log("ERRO: Estoque insuficiente. Estoque: " . $produtoStock . ", Quantidade: " . $quantidade);
            $this->json(['error' => 'Estoque insuficiente'], 400);
            return;
        }
        
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
            error_log("Criando array de carrinho vazio");
        }
        
        $itemKey = $produtoId;
        
        $itemPrice = floatval($produto['preco'] ?? $produto['valor'] ?? 0);
        
        if (isset($_SESSION['carrinho'][$itemKey])) {
            $_SESSION['carrinho'][$itemKey]['quantidade'] += $quantidade;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $itemPrice;
            $_SESSION['carrinho'][$itemKey]['price'] = $itemPrice;
            $_SESSION['carrinho'][$itemKey]['preco_unitario'] = $itemPrice;
            error_log("Atualizando item existente no carrinho");
        } else {
            $_SESSION['carrinho'][$itemKey] = [
                'produto_id' => $produtoId,
                'nome' => $produto['nome'],
                'price' => $itemPrice,
                'preco_unitario' => $itemPrice,
                'quantidade' => $quantidade,
                'subtotal' => $quantidade * $itemPrice
            ];
            error_log("Adicionando novo item ao carrinho");
        }
        
        error_log("Carrinho atual: " . json_encode($_SESSION['carrinho']));
        
        $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
        $totalValor = 0;
        foreach ($_SESSION['carrinho'] as $item) {
            $totalValor += floatval($item['subtotal']);
        }
        
        error_log("Total itens: $totalItens");
        error_log("Total valor: $totalValor");
        
        $response = [
            'success' => true,
            'message' => 'Produto adicionado ao carrinho',
            'carrinho' => $_SESSION['carrinho'],
            'total_itens' => $totalItens,
            'total_valor' => $totalValor
        ];
        
        error_log("Resposta JSON: " . json_encode($response));
        
        $this->json($response);
    }

    public function remover(Request $request) {
        $produtoId = $request->getParam('id');
        
        if (!$produtoId) {
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }
        
        if (isset($_SESSION['carrinho'][$produtoId])) {
            unset($_SESSION['carrinho'][$produtoId]);
            
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
        $produtoId = $request->getParam('id');
        $quantidade = $request->getParam('quantidade');
        
        if (!$produtoId || !$quantidade) {
            $this->json(['error' => 'Dados incompletos'], 400);
            return;
        }
        
        if (isset($_SESSION['carrinho'][$produtoId])) {
            $produto = $this->produtoModel->find($produtoId);
            
            $produtoStock = intval($produto['estoque'] ?? 0);
            if ($produtoStock < $quantidade) {
                $this->json(['error' => 'Estoque insuficiente'], 400);
                return;
            }
            
            $_SESSION['carrinho'][$produtoId]['quantidade'] = $quantidade;
            $_SESSION['carrinho'][$produtoId]['subtotal'] = $quantidade * floatval($_SESSION['carrinho'][$produtoId]['price']);
            
            $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
            $totalValor = 0;
            foreach ($_SESSION['carrinho'] as $item) {
                $totalValor += floatval($item['subtotal']);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Carrinho atualizado',
                'item_subtotal' => $_SESSION['carrinho'][$produtoId]['subtotal'],
                'total_itens' => $totalItens,
                'total_valor' => $totalValor
            ]);
        } else {
            $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
        }
    }

    public function limpar(Request $request) {
        unset($_SESSION['carrinho']);
        
        $this->json([
            'success' => true,
            'message' => 'Carrinho limpo com sucesso'
        ]);
    }

    public function calcular(Request $request) {
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
        
        // Taxas fixas
        $taxaServicoPorKg = 39; // USD por kg
        $taxaServico = $taxaServicoPorKg * $pesoArredondado;
        
        // Frete baseado no peso arredondado
        $fretePorKg = 15; // USD por kg
        $frete = $fretePorKg * $pesoArredondado;
        
        // Calcular subtotal
        $subtotal = 0;
        foreach ($carrinho as $item) {
            $subtotal += floatval($item['subtotal']);
        }
        
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
