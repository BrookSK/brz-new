<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Models\Produto;
use App\Models\ProdutoFoto;

class ProdutoController extends Controller {
    private $produtoModel;
    private $produtoFotoModel;

    public function __construct() {
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
    }

    public function index(Request $request) {
        $search = $request->getParam('search');
        $categoria = $request->getParam('categoria');
        
        if ($search) {
            $produtos = $this->produtoModel->search($search);
        } elseif ($categoria) {
            $produtos = $this->produtoModel->getByCategoriaId($categoria);
        } else {
            // Usar método que inclui JOIN com categorias
            $produtos = $this->produtoModel->getAllWithCategoria();
        }
        
        // Adicionar foto de capa (produtos.foto_principal) com fallback para galeria
        foreach ($produtos as &$produto) {
            $capa = $produto['foto_principal'] ?? null;
            if (!empty($capa)) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim((string) $capa, '/');
                if (file_exists($filePath)) {
                    $produto['foto_principal'] = Url::absolute((string) $capa);
                    continue;
                }
            }

            $fotoGaleria = $this->produtoFotoModel->getFotoPrincipal($produto['id']);
            if ($fotoGaleria && !empty($fotoGaleria['nome_arquivo'])) {
                $fotoUrl = $fotoGaleria['nome_arquivo'];
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim((string) $fotoUrl, '/');
                if (file_exists($filePath)) {
                    $produto['foto_principal'] = Url::absolute((string) $fotoUrl);
                } else {
                    $produto['foto_principal'] = null;
                }
            } else {
                $produto['foto_principal'] = null;
            }
        }
        
        $categorias = $this->produtoModel->getCategorias();
        
        $this->view('produto/index_moderno', [
            'produtos' => $produtos,
            'categorias' => $categorias,
            'search' => $search,
            'categoriaSelecionada' => $categoria
        ]);
    }

    public function detalhes(Request $request) {
        $produtoId = $request->getParam('id');
        
        if (!$produtoId) {
            $this->redirect('/produtos');
        }
        
        $produto = $this->produtoModel->find($produtoId);
        
        if (!$produto) {
            $this->view('errors/404');
            return;
        }
        
        // Obter fotos do produto (galeria completa)
        $fotos = $this->produtoModel->getImagens($produtoId);

        // Foto principal no detalhe: priorizar capa do produto
        $fotoPrincipal = null;
        $capa = $produto['foto_principal'] ?? null;
        if (!empty($capa)) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim((string) $capa, '/');
            if (file_exists($filePath)) {
                $fotoPrincipal = ['nome_arquivo' => (string) $capa, 'principal' => true];

                // Garantir capa como primeira imagem da galeria (para miniaturas/carrossel)
                $jaExisteNaGaleria = false;
                foreach ($fotos as $f) {
                    if (!empty($f['nome_arquivo']) && (string) $f['nome_arquivo'] === (string) $capa) {
                        $jaExisteNaGaleria = true;
                        break;
                    }
                }
                if (!$jaExisteNaGaleria) {
                    array_unshift($fotos, [
                        'nome_arquivo' => (string) $capa,
                        'arquivo_original' => null,
                        'legenda' => 'Capa',
                        'ordem' => -1,
                        'principal' => true
                    ]);
                }
            }
        }

        // Fallback: usar principal da galeria (ou primeira)
        if (!$fotoPrincipal && !empty($fotos)) {
            foreach ($fotos as $foto) {
                if (!empty($foto['principal'])) {
                    $fotoPrincipal = $foto;
                    break;
                }
            }
            if (!$fotoPrincipal) {
                $fotoPrincipal = $fotos[0];
            }
        }
        
        // Obter produtos relacionados (mesma categoria)
        $produtosRelacionados = $this->produtoModel->getByCategoriaId($produto['categoria_id']);
        $produtosRelacionados = array_filter($produtosRelacionados, function($p) use ($produtoId) {
            return $p['id'] != $produtoId;
        });
        
        // Adicionar fotos principais aos relacionados
        foreach ($produtosRelacionados as &$relacionado) {
            $capaRel = $relacionado['foto_principal'] ?? null;
            if (!empty($capaRel)) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim((string) $capaRel, '/');
                if (file_exists($filePath)) {
                    $relacionado['foto_principal'] = Url::absolute((string) $capaRel);
                    continue;
                }
            }

            $fotoPrincipalRel = $this->produtoFotoModel->getFotoPrincipal($relacionado['id']);
            if ($fotoPrincipalRel && !empty($fotoPrincipalRel['nome_arquivo'])) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($fotoPrincipalRel['nome_arquivo'], '/');
                if (file_exists($filePath)) {
                    $relacionado['foto_principal'] = Url::absolute($fotoPrincipalRel['nome_arquivo']);
                } else {
                    $relacionado['foto_principal'] = null;
                }
            } else {
                $relacionado['foto_principal'] = null;
            }
        }
        
        $this->view('produto/detalhes', [
            'produto' => $produto,
            'fotos' => $fotos,
            'fotoPrincipal' => $fotoPrincipal,
            'produtosRelacionados' => array_slice($produtosRelacionados, 0, 4)
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
            $_SESSION['carrinho'][$itemKey]['preco_unitario'] = $produto['preco']; // Garantir campo correto
        } else {
            $_SESSION['carrinho'][$itemKey] = [
                'produto_id' => $produtoId,
                'nome' => $produto['nome'],
                'preco_unitario' => $produto['preco'], // Usar campo correto
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
