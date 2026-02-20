<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Produto;
use App\Models\ProdutoFoto;
use App\Models\Carrinho;
use App\Services\AuthService;

class ApiController extends Controller {
    private $produtoModel;
    private $produtoFotoModel;
    private $carrinhoModel;
    private $authService;

    public function __construct() {
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
        $this->carrinhoModel = new Carrinho();
        $this->authService = new AuthService();
    }

    private function getCarrinhoSessionItems(): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $c = $_SESSION['carrinho'] ?? [];
        return is_array($c) ? $c : [];
    }

    private function setCarrinhoSessionItems(array $items): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['carrinho'] = $items;
    }

    private function getLoggedUserId(): int {
        $u = $this->authService->getUsuarioLogado();
        $uid = (int) ($u['id'] ?? 0);
        return $uid > 0 ? $uid : 0;
    }

    private function normalizeSessionTotals(array $sessionCarrinho): array {
        $totalItens = 0;
        $totalValor = 0.0;
        foreach ($sessionCarrinho as $it) {
            $totalItens += (int) ($it['quantidade'] ?? 0);
            $totalValor += (float) ($it['subtotal'] ?? 0);
        }
        return [$totalItens, $totalValor];
    }

    public function totaisCarrinho(Request $request) {
        $uid = $this->getLoggedUserId();
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                if ($cartId > 0) {
                    $stmt = $this->carrinhoModel->getConnection()->prepare('SELECT subtotal_produtos, valor_total FROM carrinhos WHERE id = ? LIMIT 1');
                    $stmt->execute([$cartId]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $subtotal = (float) ($row['subtotal_produtos'] ?? 0);
                    $total = (float) ($row['valor_total'] ?? 0);

                    $stCnt = $this->carrinhoModel->getConnection()->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                    $stCnt->execute([$cartId]);
                    $totalItens = (int) ($stCnt->fetchColumn() ?: 0);

                    $this->json([
                        'success' => true,
                        'total_itens' => $totalItens,
                        'subtotal' => $subtotal,
                        'total' => $total,
                    ]);
                    return;
                }
            } catch (\Exception $e) {
                // fallback session
            }
        }

        $carrinho = $this->getCarrinhoSessionItems();
        [$totalItens, $totalValor] = $this->normalizeSessionTotals($carrinho);
        $this->json([
            'success' => true,
            'total_itens' => $totalItens,
            'subtotal' => $totalValor,
            'total' => $totalValor,
        ]);
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
            if (empty($produto['foto_principal'])) {
                $fotoPrincipal = $this->produtoFotoModel->getFotoPrincipal($produto['id']);
                $produto['foto_principal'] = $fotoPrincipal ? $fotoPrincipal['nome_arquivo'] : null;
            }

            if (empty($produto['categoria'])) {
                if (!empty($produto['categoria_id'])) {
                    $categoria = $this->produtoModel->getCategoria($produto['categoria_id']);
                    $produto['categoria'] = $categoria ? ($categoria['nome'] ?? ($categoria['name'] ?? 'Não categorizado')) : 'Não categorizado';
                } else {
                    $produto['categoria'] = 'Não categorizado';
                }
            }

            // Padronizar chaves para o frontend (Home/index.php)
            if (!isset($produto['nome'])) {
                $produto['nome'] = $produto['name'] ?? ($produto['nome'] ?? '');
            }
            if (!isset($produto['valor'])) {
                $produto['valor'] = $produto['price'] ?? ($produto['valor'] ?? 0);
            }
            if (!isset($produto['estoque'])) {
                $produto['estoque'] = $produto['stock'] ?? ($produto['estoque'] ?? 0);
            }
            if (!isset($produto['moeda'])) {
                $produto['moeda'] = $produto['currency'] ?? ($produto['moeda'] ?? 'USD');
            }
        }

        unset($produto);
        
        $this->json([
            'success' => true,
            'produtos' => $produtos
        ]);
    }

    public function adicionarAoCarrinho(Request $request) {
        $produtoId = $request->getParam('produto_id');
        $quantidade = $request->getParam('quantidade', 1);
        $produtoVariacaoId = $request->getParam('produto_variacao_id', null);
        
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
        
        $uid = $this->getLoggedUserId();
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                $pvId = null;
                if ($produtoVariacaoId !== null && $produtoVariacaoId !== '') {
                    $tmp = (int) $produtoVariacaoId;
                    if ($tmp > 0) $pvId = $tmp;
                }
                $ok = $this->carrinhoModel->adicionarItem($cartId, (int) $produtoId, (int) $quantidade, $pvId, null);
                if (!$ok) {
                    $this->json(['error' => 'Não foi possível adicionar o item ao carrinho'], 400);
                    return;
                }

                $stCnt = $this->carrinhoModel->getConnection()->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                $stCnt->execute([$cartId]);
                $totalItens = (int) ($stCnt->fetchColumn() ?: 0);

                $stTot = $this->carrinhoModel->getConnection()->prepare('SELECT valor_total FROM carrinhos WHERE id = ? LIMIT 1');
                $stTot->execute([$cartId]);
                $totalValor = (float) ($stTot->fetchColumn() ?: 0);

                $this->json([
                    'success' => true,
                    'message' => 'Produto adicionado ao carrinho',
                    'total_itens' => $totalItens,
                    'total_valor' => $totalValor
                ]);
                return;
            } catch (\Exception $e) {
                // fallback session
            }
        }

        $carrinho = $this->getCarrinhoSessionItems();

        $precoBase = (float) ($produto['valor'] ?? ($produto['preco'] ?? 0));
        if ($precoBase < 0) $precoBase = 0.0;
        $precoPromo = (float) ($produto['preco_promocao'] ?? ($produto['sale_price'] ?? 0));
        if ($precoPromo < 0) $precoPromo = 0.0;
        $precoEfetivo = ($precoPromo > 0 && $precoPromo < $precoBase) ? $precoPromo : $precoBase;

        $itemKey = $produtoId;
        if (isset($carrinho[$itemKey])) {
            $carrinho[$itemKey]['quantidade'] += (int) $quantidade;
            $carrinho[$itemKey]['preco_unitario'] = $precoEfetivo;
            $carrinho[$itemKey]['subtotal'] = $carrinho[$itemKey]['quantidade'] * $precoEfetivo;
        } else {
            $carrinho[$itemKey] = [
                'produto_id' => $produtoId,
                'nome' => $produto['nome'],
                'preco_unitario' => $precoEfetivo,
                'quantidade' => (int) $quantidade,
                'subtotal' => ((int) $quantidade) * $precoEfetivo
            ];
        }
        $this->setCarrinhoSessionItems($carrinho);
        [$totalItens, $totalValor] = $this->normalizeSessionTotals($carrinho);
        
        $this->json([
            'success' => true,
            'message' => 'Produto adicionado ao carrinho',
            'total_itens' => $totalItens,
            'total_valor' => $totalValor
        ]);
    }

    public function removerDoCarrinho(Request $request) {
        $produtoId = $request->getParam('produto_id');
        $produtoVariacaoId = $request->getParam('produto_variacao_id', null);
        
        if (!$produtoId) {
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }
        
        $uid = $this->getLoggedUserId();
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                $pvId = null;
                if ($produtoVariacaoId !== null && $produtoVariacaoId !== '') {
                    $tmp = (int) $produtoVariacaoId;
                    if ($tmp > 0) $pvId = $tmp;
                }
                $this->carrinhoModel->removerItem($cartId, (int) $produtoId, $pvId);

                $stCnt = $this->carrinhoModel->getConnection()->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                $stCnt->execute([$cartId]);
                $totalItens = (int) ($stCnt->fetchColumn() ?: 0);

                $stTot = $this->carrinhoModel->getConnection()->prepare('SELECT valor_total FROM carrinhos WHERE id = ? LIMIT 1');
                $stTot->execute([$cartId]);
                $totalValor = (float) ($stTot->fetchColumn() ?: 0);

                $this->json([
                    'success' => true,
                    'message' => 'Produto removido do carrinho',
                    'total_itens' => $totalItens,
                    'total_valor' => $totalValor
                ]);
                return;
            } catch (\Exception $e) {
                // fallback session
            }
        }

        $carrinho = $this->getCarrinhoSessionItems();
        if (isset($carrinho[$produtoId])) {
            unset($carrinho[$produtoId]);
            $this->setCarrinhoSessionItems($carrinho);
            [$totalItens, $totalValor] = $this->normalizeSessionTotals($carrinho);
            $this->json([
                'success' => true,
                'message' => 'Produto removido do carrinho',
                'total_itens' => $totalItens,
                'total_valor' => $totalValor
            ]);
            return;
        }

        $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
    }

    public function atualizarCarrinho(Request $request) {
        $produtoId = $request->getParam('produto_id');
        $quantidade = $request->getParam('quantidade');
        $produtoVariacaoId = $request->getParam('produto_variacao_id', null);
        
        if (!$produtoId || !$quantidade) {
            $this->json(['error' => 'Dados incompletos'], 400);
            return;
        }
        
        $uid = $this->getLoggedUserId();
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;

                $pvId = null;
                if ($produtoVariacaoId !== null && $produtoVariacaoId !== '') {
                    $tmp = (int) $produtoVariacaoId;
                    if ($tmp > 0) $pvId = $tmp;
                }

                $produto = $this->produtoModel->find($produtoId);
                if ($produto && (int) ($produto['estoque'] ?? 0) < (int) $quantidade) {
                    $this->json(['error' => 'Estoque insuficiente'], 400);
                    return;
                }

                $ok = $this->carrinhoModel->setQuantidadeItem($cartId, (int) $produtoId, (int) $quantidade, $pvId);
                if (!$ok) {
                    $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
                    return;
                }

                $stItem = $this->carrinhoModel->getConnection()->prepare('SELECT subtotal FROM carrinho_items WHERE carrinho_id = ? AND produto_id = ? AND COALESCE(produto_variacao_id,0) = COALESCE(?,0) LIMIT 1');
                $stItem->execute([$cartId, (int) $produtoId, $pvId]);
                $itemSubtotal = (float) ($stItem->fetchColumn() ?: 0);

                $stCnt = $this->carrinhoModel->getConnection()->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                $stCnt->execute([$cartId]);
                $totalItens = (int) ($stCnt->fetchColumn() ?: 0);

                $stTot = $this->carrinhoModel->getConnection()->prepare('SELECT valor_total FROM carrinhos WHERE id = ? LIMIT 1');
                $stTot->execute([$cartId]);
                $totalValor = (float) ($stTot->fetchColumn() ?: 0);

                $this->json([
                    'success' => true,
                    'message' => 'Carrinho atualizado',
                    'item_subtotal' => $itemSubtotal,
                    'total_itens' => $totalItens,
                    'total_valor' => $totalValor
                ]);
                return;
            } catch (\Exception $e) {
                // fallback session
            }
        }

        $carrinho = $this->getCarrinhoSessionItems();
        if (isset($carrinho[$produtoId])) {
            $produto = $this->produtoModel->find($produtoId);
            if ($produto && (int) ($produto['estoque'] ?? 0) < (int) $quantidade) {
                $this->json(['error' => 'Estoque insuficiente'], 400);
                return;
            }

            $carrinho[$produtoId]['quantidade'] = (int) $quantidade;
            $carrinho[$produtoId]['subtotal'] = ((int) $quantidade) * (float) ($carrinho[$produtoId]['preco_unitario'] ?? 0);
            $this->setCarrinhoSessionItems($carrinho);
            [$totalItens, $totalValor] = $this->normalizeSessionTotals($carrinho);
            $this->json([
                'success' => true,
                'message' => 'Carrinho atualizado',
                'item_subtotal' => (float) ($carrinho[$produtoId]['subtotal'] ?? 0),
                'total_itens' => $totalItens,
                'total_valor' => $totalValor
            ]);
            return;
        }

        $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
    }

    public function limparCarrinho(Request $request) {
        $uid = $this->getLoggedUserId();
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                if ($cartId > 0) {
                    $this->carrinhoModel->limparCarrinho($cartId);
                }
                $this->json(['success' => true, 'message' => 'Carrinho limpo com sucesso']);
                return;
            } catch (\Exception $e) {
                // fallback session
            }
        }

        $this->setCarrinhoSessionItems([]);
        $this->json(['success' => true, 'message' => 'Carrinho limpo com sucesso']);
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
