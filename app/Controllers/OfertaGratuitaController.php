<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\OfertaGratuita;
use App\Models\Carrinho;
use App\Models\Produto;
use Config\Database;

class OfertaGratuitaController extends Controller {

    /**
     * Verifica se deve exibir oferta e retorna dados do produto sorteado (AJAX)
     */
    public function verificar(Request $request) {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $isTestMode = false;

        // Modo teste: admin pode ativar com ?test_oferta=1 no carrinho
        if (!empty($_GET['test_oferta']) || !empty($request->getParam('test_oferta'))) {
            $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_role'] ?? ''))));
            if ($perfil === 'admin') {
                $_SESSION['oferta_gratuita_test_mode'] = true;
                $isTestMode = true;

                // Modo teste: remover item gratuito existente do carrinho para poder testar de novo
                $uid = (int) ($_SESSION['usuario_id'] ?? 0);
                if ($uid > 0) {
                    try {
                        $carrinhoModelClean = new Carrinho();
                        $cartClean = $carrinhoModelClean->getOrCreateCarrinho($uid, null, 'BRL');
                        $cartIdClean = is_array($cartClean) ? (int) ($cartClean['id'] ?? 0) : (int) $cartClean;
                        if ($cartIdClean > 0) {
                            $pdoClean = Database::getConnection();
                            $pdoClean->prepare('DELETE FROM carrinho_items WHERE carrinho_id = ? AND is_free_offer = 1')->execute([$cartIdClean]);
                            $carrinhoModelClean->atualizarTotais($cartIdClean);
                        }
                    } catch (\Exception $e) {}

                    // Remover da sessão também
                    if (isset($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])) {
                        $_SESSION['carrinho'] = array_values(array_filter($_SESSION['carrinho'], function($item) {
                            return empty($item['is_free_offer']);
                        }));
                    }
                }
            }
        }

        $model = new OfertaGratuita();
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($uid <= 0 || !$model->isSessaoOrganica(true) || !$model->isOfertaGlobalAtiva()) {
            $this->json([
                'show' => false,
                'reason' => 'sessao_ou_config',
                'debug' => [
                    'uid' => $uid,
                    'isTestMode' => $isTestMode,
                    'testModeSession' => !empty($_SESSION['oferta_gratuita_test_mode']),
                    'perfil' => strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_role'] ?? '')))),
                    'sessaoOrganica' => $model->isSessaoOrganica(true),
                    'globalAtiva' => $model->isOfertaGlobalAtiva(),
                ],
            ]);
            return;
        }

        if ($model->usuarioJaRecebeuOferta($uid, $isTestMode)) {
            $this->json([
                'show' => false,
                'reason' => 'ja_recebeu',
                'debug' => ['isTestMode' => $isTestMode],
            ]);
            return;
        }

        // Obter carrinho (excluir itens gratuitos da análise)
        $carrinho = $_SESSION['carrinho'] ?? [];
        if (!empty($carrinho) && is_array($carrinho)) {
            $carrinho = array_filter($carrinho, function($item) {
                return empty($item['is_free_offer']);
            });
        }
        if (empty($carrinho)) {
            try {
                $carrinhoModel = new Carrinho();
                $cart = $carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                if ($cartId > 0) {
                    $pdo = Database::getConnection();
                    $st = $pdo->prepare('SELECT produto_id, quantidade FROM carrinho_items WHERE carrinho_id = ? AND (is_free_offer = 0 OR is_free_offer IS NULL)');
                    $st->execute([$cartId]);
                    $carrinho = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                }
            } catch (\Exception $e) {
                $carrinho = [];
            }
        }

        if (empty($carrinho)) {
            $this->json(['show' => false, 'reason' => 'carrinho_vazio']);
            return;
        }

        // Coletar IDs dos produtos já no carrinho para excluir do sorteio
        $produtoIdsNoCarrinho = [];
        foreach ($carrinho as $item) {
            $pid = (int) ($item['produto_id'] ?? 0);
            if ($pid > 0) $produtoIdsNoCarrinho[] = $pid;
        }

        $categoriaId = $model->getCategoriaPredominante($carrinho);
        if ($categoriaId === null) {
            $this->json(['show' => false, 'reason' => 'sem_categoria']);
            return;
        }

        $produto = $model->sortearProdutoGratuito($categoriaId, $produtoIdsNoCarrinho);
        if ($produto === null) {
            $this->json(['show' => false, 'reason' => 'sem_produto_elegivel', 'categoria_id' => $categoriaId, 'excluidos' => $produtoIdsNoCarrinho]);
            return;
        }

        // Buscar foto
        $fotoUrl = '';
        if (!empty($produto['foto_principal'])) {
            $fotoUrl = $produto['foto_principal'];
            if (strpos($fotoUrl, 'http') !== 0) {
                $fotoUrl = '/' . ltrim($fotoUrl, '/');
            }
        }

        // Calcular taxa de serviço teórica do item
        $peso = (float) ($produto['weight'] ?? 0.5);
        if ($peso <= 0) $peso = 0.5;
        $taxaServicoItem = 0;
        try {
            $carrinhoModel = new Carrinho();
            $taxaServicoItem = (float) $carrinhoModel->calcularTaxaServico($peso, 'USD', 1.0);
        } catch (\Exception $e) {}

        // Guardar na sessão para validar na aceitação
        $_SESSION['oferta_gratuita_produto_id'] = (int) $produto['id'];
        $_SESSION['oferta_gratuita_categoria_id'] = $categoriaId;

        $this->json([
            'show' => true,
            'produto' => [
                'id' => (int) $produto['id'],
                'nome' => (string) ($produto['name'] ?? ''),
                'preco_original' => (float) ($produto['price'] ?? 0),
                'foto' => $fotoUrl,
                'peso' => $peso,
                'taxa_servico' => round($taxaServicoItem, 2),
            ],
        ]);
    }

    /**
     * Cliente aceita a oferta — adiciona produto gratuito ao carrinho
     */
    public function aceitar(Request $request) {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $model = new OfertaGratuita();
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($uid <= 0 || !$model->isSessaoOrganica(true) || !$model->isOfertaGlobalAtiva()) {
            $this->json(['success' => false, 'error' => 'Oferta não disponível']);
            return;
        }

        if ($model->usuarioJaRecebeuOferta($uid, !empty($_SESSION['oferta_gratuita_test_mode']))) {
            $this->json(['success' => false, 'error' => 'Oferta já utilizada']);
            return;
        }

        $produtoId = (int) ($_SESSION['oferta_gratuita_produto_id'] ?? 0);
        $categoriaId = (int) ($_SESSION['oferta_gratuita_categoria_id'] ?? 0);

        if ($produtoId <= 0) {
            $this->json(['success' => false, 'error' => 'Produto inválido']);
            return;
        }

        // Verificar estoque novamente
        $pdo = Database::getConnection();
        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE produtos');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) { $cols = []; }

        $stProd = $pdo->prepare('SELECT id, name, price, weight, stock FROM produtos WHERE id = ? AND active = 1 AND stock > 0 LIMIT 1');
        $stProd->execute([$produtoId]);
        $produto = $stProd->fetch(\PDO::FETCH_ASSOC);

        if (!$produto) {
            $this->json(['success' => false, 'error' => 'Produto indisponível']);
            return;
        }

        // Adicionar ao carrinho do DB
        try {
            $carrinhoModel = new Carrinho();
            $cart = $carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
            $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;

            if ($cartId > 0) {
                // Verificar se já existe item gratuito no carrinho
                $stCheck = $pdo->prepare('SELECT id FROM carrinho_items WHERE carrinho_id = ? AND is_free_offer = 1 LIMIT 1');
                $stCheck->execute([$cartId]);
                if ($stCheck->fetch()) {
                    $this->json(['success' => false, 'error' => 'Já existe um item gratuito no carrinho']);
                    return;
                }

                $itemsCols = [];
                try {
                    $stC = $pdo->query('DESCRIBE carrinho_items');
                    $itemsCols = $stC ? ($stC->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) { $itemsCols = []; }

                $unitCol = in_array('preco_unitario', $itemsCols, true) ? 'preco_unitario' : 'valor_unitario';

                $insertCols = ['carrinho_id', 'produto_id', 'quantidade', $unitCol, 'subtotal', 'is_free_offer', 'free_offer_original_price', 'free_offer_exempt_tax'];
                $insertVals = ['?', '?', '?', '?', '?', '?', '?', '?'];
                $insertParams = [
                    $cartId,
                    $produtoId,
                    1,
                    0, // preço = 0 (gratuito)
                    0, // subtotal = 0
                    1, // is_free_offer
                    (float) ($produto['price'] ?? 0), // preço original
                    1, // isento de imposto
                ];

                $sql = 'INSERT INTO carrinho_items (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')';
                $st = $pdo->prepare($sql);
                $st->execute($insertParams);

                // Recalcular totais do carrinho (peso, taxa de serviço, etc.)
                $carrinhoModel->atualizarTotais($cartId);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro ao adicionar ao carrinho']);
            return;
        }

        // Adicionar à sessão do carrinho também
        $_SESSION['carrinho'][] = [
            'produto_id' => $produtoId,
            'quantidade' => 1,
            'price' => 0,
            'preco_unitario' => 0,
            'subtotal' => 0,
            'is_free_offer' => 1,
            'free_offer_original_price' => (float) ($produto['price'] ?? 0),
            'nome' => (string) ($produto['name'] ?? ''),
        ];

        // Registrar aceitação
        $model->registrarAcao($uid, $produtoId, $categoriaId, 'aceita', $cartId ?? null);

        // Limpar sessão da oferta
        unset($_SESSION['oferta_gratuita_produto_id'], $_SESSION['oferta_gratuita_categoria_id']);

        $this->json(['success' => true, 'message' => 'Produto gratuito adicionado ao carrinho']);
    }

    /**
     * Cliente recusa a oferta
     */
    public function recusar(Request $request) {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $model = new OfertaGratuita();
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);

        $produtoId = (int) ($_SESSION['oferta_gratuita_produto_id'] ?? 0);
        $categoriaId = (int) ($_SESSION['oferta_gratuita_categoria_id'] ?? 0);

        if ($uid > 0) {
            $model->registrarAcao($uid, $produtoId > 0 ? $produtoId : null, $categoriaId > 0 ? $categoriaId : null, 'recusada');
        }

        unset($_SESSION['oferta_gratuita_produto_id'], $_SESSION['oferta_gratuita_categoria_id']);

        $this->json(['success' => true]);
    }

    /**
     * Remover item gratuito do carrinho
     */
    public function removerDoCarrinho(Request $request) {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        $model = new OfertaGratuita();

        // Remover do DB
        try {
            $pdo = Database::getConnection();
            $carrinhoModel = new Carrinho();
            $cart = $carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
            $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;

            if ($cartId > 0) {
                $pdo->prepare('DELETE FROM carrinho_items WHERE carrinho_id = ? AND is_free_offer = 1')->execute([$cartId]);
            }
        } catch (\Exception $e) {}

        // Remover da sessão
        if (isset($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = array_values(array_filter($_SESSION['carrinho'], function($item) {
                return empty($item['is_free_offer']);
            }));
        }

        // Registrar remoção (oferta consumida, não pode ser reofertada)
        if ($uid > 0) {
            $model->registrarAcao($uid, null, null, 'removida');
        }

        $this->json(['success' => true]);
    }
}
