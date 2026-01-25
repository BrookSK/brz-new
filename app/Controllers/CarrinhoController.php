<?php
namespace App\Controllers;

use App\Core\Request;
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
            $produto = $this->produtoModel->find($item['produto_id']);
            
            if ($produto) {
                $fotoPrincipal = $this->produtoFotoModel->getFotoPrincipal($produto['id']);
                
                // Verificar e corrigir URL da foto
                $fotoUrl = null;
                if ($fotoPrincipal && !empty($fotoPrincipal['nome_arquivo'])) {
                    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($fotoPrincipal['nome_arquivo'], '/');
                    if (file_exists($filePath)) {
                        $fotoUrl = 'https://novobr.brazilianashop.com.br' . $fotoPrincipal['nome_arquivo'];
                    }
                }
                
                $itemPrice = floatval($produto['price']);
                $itemSubtotal = $item['quantidade'] * $itemPrice;
                
                $produtosDetalhados[] = [
                    'produto_id' => $item['produto_id'],
                    'sku' => $produto['sku'],
                    'name' => $produto['name'],
                    'description' => $produto['description'],
                    'price' => $itemPrice,
                    'weight' => $produto['weight'],
                    'quantidade' => $item['quantidade'],
                    'subtotal' => $itemSubtotal,
                    'foto_principal' => $fotoUrl,
                    'stock' => $produto['stock']
                ];
                
                $subtotal += $itemSubtotal;
                $pesoTotal += $item['quantidade'] * floatval($produto['weight']);
            }
        }
        
        // Calcular taxas e impostos com arredondamento
        $pesoArredondado = ceil($pesoTotal); // Arredondar para cima
        $taxaServico = $pesoArredondado * 39; // US$39 por kg arredondado
        $impostos = $subtotal * 0.80; // 80% de impostos
        $frete = $pesoArredondado * 15; // US$15 por kg arredondado
        
        $total = $subtotal + $taxaServico + $impostos + $frete;
        
        $this->view('carrinho/index', [
            'carrinho' => $produtosDetalhados,
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
        
        $produto = $this->produtoModel->find($produtoId);
        
        error_log("Produto encontrado: " . ($produto ? 'SIM' : 'NÃO'));
        if ($produto) {
            error_log("Dados do produto: " . json_encode($produto));
        }
        
        if (!$produto) {
            error_log("ERRO: Produto não encontrado no banco");
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        
        if ($produto['stock'] < $quantidade) {
            error_log("ERRO: Estoque insuficiente. Estoque: " . $produto['stock'] . ", Quantidade: " . $quantidade);
            $this->json(['error' => 'Estoque insuficiente'], 400);
            return;
        }
        
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
            error_log("Criando array de carrinho vazio");
        }
        
        $itemKey = $produtoId;
        
        $itemPrice = floatval($produto['price']);
        
        if (isset($_SESSION['carrinho'][$itemKey])) {
            $_SESSION['carrinho'][$itemKey]['quantidade'] += $quantidade;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $itemPrice;
            error_log("Atualizando item existente no carrinho");
        } else {
            $_SESSION['carrinho'][$itemKey] = [
                'produto_id' => $produtoId,
                'name' => $produto['name'],
                'price' => $itemPrice,
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
            
            if ($produto['stock'] < $quantidade) {
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
                $pesoTotal += floatval($produto['weight'] ?? 0.5) * $item['quantidade'];
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
