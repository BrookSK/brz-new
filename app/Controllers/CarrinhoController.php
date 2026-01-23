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
                
                $produtosDetalhados[] = [
                    'produto_id' => $item['produto_id'],
                    'sku' => $produto['sku'],
                    'nome' => $produto['nome'],
                    'descricao' => $produto['descricao'],
                    'valor' => $produto['valor'],
                    'moeda' => $produto['moeda'],
                    'peso' => $produto['peso'],
                    'quantidade' => $item['quantidade'],
                    'subtotal' => $item['quantidade'] * $produto['valor'],
                    'foto_principal' => $fotoPrincipal ? $fotoPrincipal['nome_arquivo'] : null,
                    'estoque' => $produto['estoque']
                ];
                
                $subtotal += $item['quantidade'] * $produto['valor'];
                $pesoTotal += $item['quantidade'] * $produto['peso'];
            }
        }
        
        // Calcular taxas e impostos
        $taxaServico = $pesoTotal * 39; // US$39 por kg
        $impostos = $subtotal * 0.80; // 80% de impostos (ICMS 60% + IPI 20%)
        $frete = 0; // Será calculado via AJAX
        
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
        $produtoId = $request->getParam('id');
        $quantidade = $request->getParam('quantidade', 1);
        
        if (!$produtoId) {
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }
        
        $produto = $this->produtoModel->find($produtoId);
        
        if (!$produto) {
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        
        if ($produto['estoque'] < $quantidade) {
            $this->json(['error' => 'Estoque insuficiente'], 400);
            return;
        }
        
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
        }
        
        $itemKey = $produtoId;
        
        if (isset($_SESSION['carrinho'][$itemKey])) {
            $_SESSION['carrinho'][$itemKey]['quantidade'] += $quantidade;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $produto['valor'];
        } else {
            $_SESSION['carrinho'][$itemKey] = [
                'produto_id' => $produtoId,
                'nome' => $produto['nome'],
                'preco_unitario' => $produto['valor'],
                'quantidade' => $quantidade,
                'subtotal' => $quantidade * $produto['valor']
            ];
        }
        
        $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
        $totalValor = array_sum(array_column($_SESSION['carrinho'], 'subtotal'));
        
        $this->json([
            'success' => true,
            'message' => 'Produto adicionado ao carrinho',
            'carrinho' => $_SESSION['carrinho'],
            'total_itens' => $totalItens,
            'total_valor' => $totalValor
        ]);
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
            $totalValor = array_sum(array_column($_SESSION['carrinho'], 'subtotal'));
            
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
            
            if ($produto['estoque'] < $quantidade) {
                $this->json(['error' => 'Estoque insuficiente'], 400);
                return;
            }
            
            $_SESSION['carrinho'][$produtoId]['quantidade'] = $quantidade;
            $_SESSION['carrinho'][$produtoId]['subtotal'] = $quantidade * $_SESSION['carrinho'][$produtoId]['preco_unitario'];
            
            $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
            $totalValor = array_sum(array_column($_SESSION['carrinho'], 'subtotal'));
            
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
        $cep = $request->getParam('cep');
        $peso = $request->getParam('peso', 0);
        $valor = $request->getParam('valor', 0);
        
        if (!$cep || !$peso) {
            $this->json(['error' => 'Dados incompletos'], 400);
            return;
        }
        
        // Simulação de cálculo de frete
        $frete = [
            'pac' => [
                'nome' => 'PAC',
                'valor' => max(15.00, $peso * 15.50),
                'prazo' => 15,
                'descricao' => 'Encomenda Econômica'
            ],
            'sedex' => [
                'nome' => 'SEDEX',
                'valor' => max(25.00, $peso * 22.50),
                'prazo' => 8,
                'descricao' => 'Encomenda Expressa'
            ]
        ];
        
        $this->json([
            'success' => true,
            'frete' => $frete,
            'cep_origem' => '04538-133',
            'cep_destino' => $cep
        ]);
    }
}
