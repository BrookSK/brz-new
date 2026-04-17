<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\CopilotoService;

/**
 * CopilotoApiController — Endpoints da API do Co-Piloto (100% PHP)
 * Widget JS chama estas rotas via fetch() no mesmo domínio
 */
class CopilotoApiController extends Controller {

    private function responderJson(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function sanitizar(string $texto): string {
        return trim(strip_tags(html_entity_decode($texto, ENT_QUOTES, 'UTF-8')));
    }

    /** POST /api/copiloto/chat */
    public function chat(Request $request) {
        try {
            $body = $request->getBody();
            $mensagem = $this->sanitizar((string) ($body['mensagem'] ?? ''));
            if ($mensagem === '' || mb_strlen($mensagem) > 2000) {
                $this->responderJson(['resposta' => 'Mensagem vazia ou muito longa.', 'acao_frontend' => ['tipo' => 'nenhuma', 'parametros' => []]], 400);
            }

            if (session_status() === PHP_SESSION_NONE) @session_start();
            $cacheKey = 'cop_rate_' . (session_id() ?: 'x');
            $agora = time();
            if ($agora - (int)($_SESSION[$cacheKey.'_w'] ?? 0) > 60) {
                $_SESSION[$cacheKey.'_c'] = 1;
                $_SESSION[$cacheKey.'_w'] = $agora;
            } else {
                $_SESSION[$cacheKey.'_c'] = ($_SESSION[$cacheKey.'_c'] ?? 0) + 1;
                if ($_SESSION[$cacheKey.'_c'] > 20) {
                    $this->responderJson(['resposta' => 'Muitas mensagens. Aguarde 1 minuto.', 'acao_frontend' => ['tipo' => 'nenhuma', 'parametros' => []]], 429);
                }
            }

            $contexto = is_array($body['contexto'] ?? null) ? $body['contexto'] : [];
            $historico = is_array($body['historico'] ?? null) ? $body['historico'] : [];

            $service = new CopilotoService();
            $resultado = $service->chamarClaude($mensagem, $contexto, $historico);

            $this->responderJson([
                'resposta' => $resultado['texto'],
                'acao_frontend' => ['tipo' => $resultado['acao'] ?? 'nenhuma', 'parametros' => $resultado['parametros'] ?? []],
                'requer_confirmacao' => $resultado['requer_confirmacao'] ?? false,
                'mensagem_confirmacao' => $resultado['mensagem_confirmacao'] ?? null,
                'max_tentativas_problema' => $resultado['max_tentativas_problema'] ?? null,
                'oferecer_ticket' => $resultado['oferecer_ticket'] ?? false,
                'tokens_usados' => $resultado['tokens_usados'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->responderJson(['resposta' => 'Desculpa, tive um problema técnico. Tenta de novo?', 'acao_frontend' => ['tipo' => 'nenhuma', 'parametros' => []]], 500);
        }
    }

    /** GET /api/copiloto/context */
    public function context(Request $request) {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $service = new CopilotoService();
        $this->responderJson([
            'usuario_logado' => !empty($_SESSION['logado']),
            'usuario_nome' => $_SESSION['usuario_nome'] ?? null,
            'moeda' => $_SESSION['moeda'] ?? 'BRL',
            'cambio' => (float) $service->getConfig('cambio_usd_brl', 5.80),
            'gatilho_tempo_ms' => (int) $service->getConfig('gatilho_tempo_ms', 30000),
        ]);
    }

    /** POST /api/copiloto/calculo */
    public function calculo(Request $request) {
        $body = $request->getBody();
        $preco = (float) ($body['preco_usd'] ?? 0);
        $peso = (float) ($body['peso_kg'] ?? 0);
        $imposto = (float) ($body['imposto_local_pct'] ?? 0);
        if ($preco <= 0 || $peso <= 0) {
            $this->responderJson(['erro' => 'Preço e peso são obrigatórios.'], 400);
        }
        $service = new CopilotoService();
        $this->responderJson($service->calcularCustoTotal($preco, $peso, $imposto));
    }

    /** POST /api/copiloto/add-cart — Adicionar produto ao carrinho via copiloto */
    public function carrinhoAdicionar(Request $request) {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            
            $raw = file_get_contents('php://input');
            $body = json_decode($raw ?: '', true);
            if (!is_array($body)) $body = [];
            $produtoId = (int) ($body['produto_id'] ?? $request->getParam('produto_id', 0));
            $quantidade = max(1, (int) ($body['quantidade'] ?? $request->getParam('quantidade', 1)));

            if ($produtoId <= 0) {
                $this->responderJson(['error' => 'produto_id é obrigatório'], 400);
            }

            $pdo = \Config\Database::getConnection();
            $userId = (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? $_SESSION['logado_id'] ?? 0);
            // Fallback: tentar buscar pelo remember_token se sessão não tem user
            if ($userId <= 0 && !empty($_COOKIE['remember_token'])) {
                try {
                    $stUser = $pdo->prepare("SELECT id FROM usuarios WHERE remember_token = ? LIMIT 1");
                    $stUser->execute([$_COOKIE['remember_token']]);
                    $userId = (int) ($stUser->fetchColumn() ?: 0);
                } catch (\Exception $e) {}
            }
            if ($userId <= 0) {
                $this->responderJson(['error' => 'Não logado', 'debug_session_keys' => array_keys($_SESSION ?? [])], 401);
            }

            // Verificar produto
            $st = $pdo->prepare("SELECT id, name, price, weight, stock FROM produtos WHERE id = ? LIMIT 1");
            $st->execute([$produtoId]);
            $produto = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$produto) {
                $this->responderJson(['error' => 'Produto não encontrado'], 404);
            }

            // Buscar o carrinho CORRETO do usuário (mesma lógica do CarrinhoController)
            $stCarts = $pdo->prepare('SELECT id FROM carrinhos WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 10');
            $stCarts->execute([$userId]);
            $cartIds = $stCarts->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            
            $cartId = 0;
            foreach ($cartIds as $cid) {
                $stCnt = $pdo->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                $stCnt->execute([(int)$cid]);
                if ((int)$stCnt->fetchColumn() > 0) { $cartId = (int)$cid; break; }
            }
            if ($cartId <= 0 && !empty($cartIds)) $cartId = (int)$cartIds[0];
            if ($cartId <= 0) {
                $carrinho = new \App\Models\Carrinho();
                $cart = $carrinho->getOrCreateCarrinho($userId, null, 'BRL');
                $cartId = is_array($cart) ? (int)($cart['id'] ?? 0) : (int)$cart;
            }

            if ($cartId <= 0) {
                $this->responderJson(['error' => 'Erro ao encontrar carrinho'], 500);
            }

            // Adicionar item — respeita estoque do cadastro do produto
            $carrinho = new \App\Models\Carrinho();
            $ok = $carrinho->adicionarItem($cartId, $produtoId, $quantidade, null, null);
            if (!$ok) {
                $this->responderJson(['error' => 'Produto indisponível ou sem estoque no momento'], 400);
            }

            // Contar itens
            $stCnt = $pdo->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
            $stCnt->execute([$cartId]);
            $totalItens = (int) ($stCnt->fetchColumn() ?: 0);

            $this->responderJson([
                'success' => true,
                'produto_nome' => $produto['name'] ?? '',
                'total_itens' => $totalItens
            ]);
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro carrinho: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->responderJson(['error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/copiloto/clear-cart — Limpar carrinho via copiloto */
    public function carrinhoLimpar(Request $request) {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($userId <= 0) {
                $this->responderJson(['error' => 'Não logado'], 401);
            }

            $pdo = \Config\Database::getConnection();
            // Buscar o carrinho correto (mesma lógica do site)
            $stCarts = $pdo->prepare('SELECT id FROM carrinhos WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 10');
            $stCarts->execute([$userId]);
            $cartIds = $stCarts->fetchAll(\PDO::FETCH_COLUMN) ?: [];

            // Limpar TODOS os carrinhos do usuário
            foreach ($cartIds as $cid) {
                $pdo->prepare("DELETE FROM carrinho_items WHERE carrinho_id = ?")->execute([(int)$cid]);
                $pdo->prepare("UPDATE carrinhos SET valor_total = 0 WHERE id = ?")->execute([(int)$cid]);
            }

            $this->responderJson(['success' => true, 'message' => 'Carrinho limpo']);
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro limpar carrinho: ' . $e->getMessage());
            $this->responderJson(['error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/copiloto/buscar-produto — Busca produtos no banco incluindo grupos de compras */
    public function buscarProduto(Request $request) {
        $termo = trim((string) $request->getParam('q', ''));
        if (mb_strlen($termo) < 2) {
            $this->responderJson(['produtos' => [], 'grupos' => []]);
        }
        try {
            $pdo = \Config\Database::getConnection();
            $like = '%' . $termo . '%';

            // Verificar quais colunas existem na tabela produtos
            $cols = [];
            try {
                $stCols = $pdo->query('DESCRIBE produtos');
                $cols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) { $cols = []; }

            $temGrupoComprasId = in_array('grupo_compras_id', $cols, true);
            $colNome = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : 'name');
            $colPreco = in_array('price', $cols, true) ? 'price' : (in_array('preco', $cols, true) ? 'preco' : 'price');
            $colPeso = in_array('weight', $cols, true) ? 'weight' : (in_array('peso', $cols, true) ? 'peso' : 'weight');

            // Filtros de visibilidade (mesma lógica do Produto model)
            $filtros = [];
            if (in_array('status', $cols, true)) {
                $filtros[] = "(p.status IS NULL OR LOWER(COALESCE(p.status,'')) IN ('published','publish','publicado','ativo','active'))";
            }
            if (in_array('active', $cols, true)) {
                $filtros[] = "(p.active = 1 OR LOWER(COALESCE(p.active,'')) IN ('true','yes','sim','ativo','active'))";
            } elseif (in_array('ativo', $cols, true)) {
                $filtros[] = "(p.ativo = 1 OR LOWER(COALESCE(p.ativo,'')) IN ('true','yes','sim','ativo','active'))";
            }
            if (in_array('oculto', $cols, true)) {
                $filtros[] = "(p.oculto IS NULL OR p.oculto = 0)";
            }
            $filtroSQL = !empty($filtros) ? ' AND ' . implode(' AND ', $filtros) : '';

            $temFoto = in_array('foto_principal', $cols, true);
            $fotoSelect = $temFoto ? ", p.foto_principal" : ", NULL as foto_principal";

            if ($temGrupoComprasId) {
                $sql = "SELECT p.id, p.{$colNome} AS nome, p.{$colPreco} AS preco, p.{$colPeso} AS peso,
                    p.grupo_compras_id,
                    COALESCE(gc.nome, '') as grupo_nome, COALESCE(gc.slug, '') as grupo_slug
                    {$fotoSelect}
                    FROM produtos p
                    LEFT JOIN grupos_compras gc ON gc.id = p.grupo_compras_id
                    WHERE p.{$colNome} LIKE ?{$filtroSQL}
                    ORDER BY p.{$colNome} ASC LIMIT 10";
            } else {
                $sql = "SELECT p.id, p.{$colNome} AS nome, p.{$colPreco} AS preco, p.{$colPeso} AS peso,
                    NULL as grupo_compras_id, '' as grupo_nome, '' as grupo_slug
                    {$fotoSelect}
                    FROM produtos p
                    WHERE p.{$colNome} LIKE ?{$filtroSQL}
                    ORDER BY p.{$colNome} ASC LIMIT 10";
            }

            $st = $pdo->prepare($sql);
            $st->execute([$like]);
            $produtos = $st->fetchAll(\PDO::FETCH_ASSOC);

            // Identificar grupos que contêm o produto
            $grupos = [];
            $gruposVistos = [];
            foreach ($produtos as $p) {
                if (!empty($p['grupo_slug']) && !isset($gruposVistos[$p['grupo_slug']])) {
                    $grupos[] = ['nome' => $p['grupo_nome'], 'slug' => $p['grupo_slug']];
                    $gruposVistos[$p['grupo_slug']] = true;
                }
            }

            $this->responderJson([
                'produtos' => array_map(function($p) {
                    $foto = $p['foto_principal'] ?? null;
                    if ($foto && strpos($foto, 'http') !== 0 && strpos($foto, '/') !== 0) {
                        $foto = '/uploads/produtos/' . $foto;
                    }
                    return [
                        'id' => $p['id'],
                        'nome' => $p['nome'],
                        'preco' => $p['preco'],
                        'peso' => $p['peso'],
                        'grupo' => $p['grupo_slug'] ?: null,
                        'grupo_nome' => $p['grupo_nome'] ?: null,
                        'foto' => $foto,
                    ];
                }, $produtos),
                'grupos' => $grupos,
                'total' => count($produtos),
            ]);
        } catch (\Exception $e) {
            error_log('[CoPiloto] Erro busca produto: ' . $e->getMessage());
            $this->responderJson(['produtos' => [], 'grupos' => [], 'erro' => $e->getMessage()]);
        }
    }

    /** POST /api/copiloto/ticket */
    public function ticket(Request $request) {
        $body = $request->getBody();
        $assunto = $this->sanitizar((string) ($body['assunto'] ?? ''));
        $mensagem = $this->sanitizar((string) ($body['mensagem'] ?? ''));
        if (empty($assunto) || empty($mensagem)) {
            $this->responderJson(['erro' => 'Assunto e mensagem são obrigatórios.'], 400);
        }
        $numero = 'TKT-' . strtoupper(base_convert((string) time(), 10, 36));
        $this->responderJson(['sucesso' => true, 'numero_ticket' => $numero, 'prazo_resposta' => '48h úteis']);
    }

    /** GET /api/copiloto/cron */
    public function cron(Request $request) {
        $token = $request->getParam('token', '');
        $service = new CopilotoService();
        $apiKey = $service->getConfig('api_key_claude', '');
        $expectedToken = substr(md5('copiloto_cron_' . $apiKey), 0, 16);
        if ($token !== $expectedToken && !empty($apiKey)) {
            $this->responderJson(['erro' => 'Token inválido.'], 403);
        }
        $processados = $service->processarConteudoPendente();
        $limpeza = $service->limparDadosAntigos();
        $this->responderJson(['success' => true, 'conteudo_processado' => $processados, 'limpeza' => $limpeza, 'timestamp' => date('Y-m-d H:i:s')]);
    }
}
