<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Produto;

class ProdutoController extends Controller {
    private $produtoModel;

    public function __construct() {
        $this->produtoModel = new Produto();
    }

    public function index(Request $request) {
        $search = $request->getParam('search');
        $categoria = $request->getParam('categoria');
        
        if ($search) {
            $produtos = $this->produtoModel->search($search);
        } elseif ($categoria) {
            $produtos = $this->produtoModel->getByCategoria($categoria);
        } else {
            $produtos = $this->produtoModel->all();
        }
        
        $categorias = $this->produtoModel->getCategorias();
        
        $this->view('produto/index', [
            'produtos' => $produtos,
            'categorias' => $categorias,
            'search' => $search,
            'categoriaSelecionada' => $categoria
        ]);
    }

    public function selecionar(Request $request) {
        $produtoId = $request->getParam('id');
        $quantidade = $request->getParam('quantidade', 1);
        
        if (!$produtoId) {
            $this->json(['error' => 'Produto não informado'], 400);
        }
        
        $produto = $this->produtoModel->find($produtoId);
        
        if (!$produto) {
            $this->json(['error' => 'Produto não encontrado'], 404);
        }
        
        if ($produto['estoque'] < $quantidade) {
            $this->json(['error' => 'Estoque insuficiente'], 400);
        }
        
        session_start();
        
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
        }
        
        $itemKey = $produtoId;
        
        if (isset($_SESSION['carrinho'][$itemKey])) {
            $_SESSION['carrinho'][$itemKey]['quantidade'] += $quantidade;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $produto['preco'];
        } else {
            $_SESSION['carrinho'][$itemKey] = [
                'produto_id' => $produtoId,
                'nome' => $produto['nome'],
                'preco_unitario' => $produto['preco'],
                'quantidade' => $quantidade,
                'subtotal' => $quantidade * $produto['preco']
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

    public function adicionarAoCarrinho(Request $request) {
        $this->selecionar($request);
    }
}
