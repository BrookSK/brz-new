<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Produto;
use App\Models\ProdutoFoto;
use App\Models\Carrinho;

class ApiController extends Controller {
    private $produtoModel;
    private $produtoFotoModel;
    private $carrinhoModel;

    public function __construct() {
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
        $this->carrinhoModel = new Carrinho();
    }

    public function buscarProdutos(Request $request) {
        $search = $request->getParam('search');
        $categoria = $request->getParam('categoria');
        $limit = $request->getParam('limit', 20);
        
        if ($search) {
            $produtos = $this->produtoModel->search($search, $limit);
        } elseif ($categoria) {
            $produtos = $this->produtoModel->getByCategoriaId($categoria, $limit);
        } else {
            $produtos = $this->produtoModel->all($limit);
        }
        
        // Adicionar fotos aos produtos
        foreach ($produtos as &$produto) {
            $fotoPrincipal = $this->produtoFotoModel->getFotoPrincipal($produto['id']);
            $produto['foto_principal'] = $fotoPrincipal ? $fotoPrincipal['nome_arquivo'] : null;
        }
        
        $this->json([
            'success' => true,
            'produtos' => $produtos,
            'total' => count($produtos)
        ]);
    }

    public function produtosDestaque(Request $request) {
        $produtos = $this->produtoModel->getDestaque(8);
        
        // Adicionar fotos aos produtos
        foreach ($produtos as &$produto) {
            $fotoPrincipal = $this->produtoFotoModel->getFotoPrincipal($produto['id']);
            $produto['foto_principal'] = $fotoPrincipal ? $fotoPrincipal['nome_arquivo'] : null;
            
            // Adicionar categoria
            if ($produto['categoria_id']) {
                $categoria = $this->produtoModel->getCategoria($produto['categoria_id']);
                $produto['categoria'] = $categoria ? $categoria['nome'] : 'Não categorizado';
            }
        }
        
        $this->json([
            'success' => true,
            'produtos' => $produtos
        ]);
    }

    public function adicionarAoCarrinho(Request $request) {
        $produtoId = $request->getParam('produto_id');
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
        
        session_start();
        
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

    public function removerDoCarrinho(Request $request) {
        $produtoId = $request->getParam('produto_id');
        
        if (!$produtoId) {
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }
        
        session_start();
        
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

    public function atualizarCarrinho(Request $request) {
        $produtoId = $request->getParam('produto_id');
        $quantidade = $request->getParam('quantidade');
        
        if (!$produtoId || !$quantidade) {
            $this->json(['error' => 'Dados incompletos'], 400);
            return;
        }
        
        session_start();
        
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

    public function consultarCEP(Request $request) {
        $cep = $request->getParam('cep');
        
        if (!$cep) {
            $this->json(['error' => 'CEP não informado'], 400);
            return;
        }
        
        // Remover caracteres não numéricos
        $cep = preg_replace('/[^0-9]/', '', $cep);
        
        if (strlen($cep) !== 8) {
            $this->json(['error' => 'CEP inválido'], 400);
            return;
        }
        
        // Usar API ViaCEP (simulação)
        $url = "https://viacep.com.br/ws/{$cep}/json/";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            if (isset($data['erro'])) {
                $this->json(['error' => 'CEP não encontrado'], 404);
            } else {
                $this->json([
                    'success' => true,
                    'endereco' => [
                        'cep' => $data['cep'],
                        'logradouro' => $data['logradouro'],
                        'complemento' => $data['complemento'],
                        'bairro' => $data['bairro'],
                        'localidade' => $data['localidade'],
                        'uf' => $data['uf']
                    ]
                ]);
            }
        } else {
            $this->json(['error' => 'Erro ao consultar CEP'], 500);
        }
    }

    public function calcularFrete(Request $request) {
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
