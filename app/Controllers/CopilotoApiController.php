<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\CopilotoService;

// Forçar recarga do OPcache dos arquivos do copiloto
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__DIR__ . '/../Services/CopilotoService.php', true);
    @opcache_invalidate(__FILE__, true);
}

/**
 * CopilotoApiController — Endpoints da API do Co-Piloto (100% PHP)
 * Widget JS chama estas rotas via fetch() no mesmo domínio
 */
class CopilotoApiController extends Controller {

    private function validarCpf(string $cpf): bool {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) $d += $cpf[$c] * (($t + 1) - $c);
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        return true;
    }

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
        
        // Dados do perfil do usuário logado
        $perfil = [];
        $userId = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($userId > 0) {
            try {
                $pdo = \Config\Database::getConnection();
                $st = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
                $st->execute([$userId]);
                $u = $st->fetch(\PDO::FETCH_ASSOC);
                if ($u) {
                    $perfil = [
                        'nome' => $u['nome'] ?? $u['name'] ?? '',
                        'email' => $u['email'] ?? '',
                        'telefone' => $u['telefone'] ?? $u['phone'] ?? '',
                        'documento' => $u['documento'] ?? $u['cpf'] ?? '',
                        'data_nascimento' => $u['data_nascimento'] ?? $u['birth_date'] ?? '',
                    ];
                    // Buscar TODOS os endereços
                    try {
                        $stEnd = $pdo->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC");
                        $stEnd->execute([$userId]);
                        $enderecos = $stEnd->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        $perfil['enderecos'] = [];
                        foreach ($enderecos as $end) {
                            $perfil['enderecos'][] = [
                                'id' => $end['id'] ?? null,
                                'cep' => $end['cep'] ?? '',
                                'endereco' => $end['endereco'] ?? $end['logradouro'] ?? '',
                                'numero' => $end['numero'] ?? '',
                                'complemento' => $end['complemento'] ?? '',
                                'bairro' => $end['bairro'] ?? '',
                                'cidade' => $end['cidade'] ?? '',
                                'estado' => $end['estado'] ?? '',
                                'pais' => $end['pais'] ?? 'BR',
                                'principal' => !empty($end['principal']),
                            ];
                        }
                        if (!empty($perfil['enderecos'])) {
                            $perfil['endereco'] = $perfil['enderecos'][0]; // Principal
                        }
                    } catch (\Exception $e) {}
                }
            } catch (\Exception $e) {}
        }

        $this->responderJson([
            'usuario_logado' => !empty($_SESSION['logado']),
            'usuario_nome' => $_SESSION['usuario_nome'] ?? null,
            'moeda' => $_SESSION['moeda'] ?? 'BRL',
            'cambio' => (float) $service->getConfig('cambio_usd_brl', 5.80),
            'gatilho_tempo_ms' => (int) $service->getConfig('gatilho_tempo_ms', 30000),
            'qrcode_mensagem' => (string) $service->getConfig('qrcode_mensagem', ''),
            'perfil' => $perfil,
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
            $carrinho = new \App\Models\Carrinho();
            foreach ($cartIds as $cid) {
                $cid = (int)$cid;
                $pdo->prepare("DELETE FROM carrinho_items WHERE carrinho_id = ?")->execute([$cid]);
                $pdo->prepare("UPDATE carrinhos SET valor_total = 0, taxa_servico = 0, valor_impostos = 0, peso_total = 0, updated_at = NOW() WHERE id = ?")->execute([$cid]);
                // Recalcular para garantir consistência
                try { $carrinho->recalcularTotais($cid); } catch (\Throwable $e) {}
            }

            $this->responderJson(['success' => true, 'message' => 'Carrinho limpo']);
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro limpar carrinho: ' . $e->getMessage());
            $this->responderJson(['error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/copiloto/meucarrinho — Retorna itens do carrinho do usuário logado */
    public function meuCarrinho(Request $request) {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($userId <= 0) {
                $this->responderJson(['itens' => [], 'resumo' => null]);
            }

            $pdo = \Config\Database::getConnection();
            $carrinho = new \App\Models\Carrinho();
            $cart = $carrinho->getOrCreateCarrinho($userId, null, 'BRL');
            $cartId = is_array($cart) ? (int)($cart['id'] ?? 0) : (int)$cart;

            if ($cartId <= 0) {
                $this->responderJson(['itens' => [], 'resumo' => null]);
            }

            // Buscar itens com dados do produto
            $st = $pdo->prepare("SELECT ci.produto_id, ci.quantidade, ci.subtotal,
                p.name AS nome, p.price, p.weight
                FROM carrinho_items ci
                JOIN produtos p ON p.id = ci.produto_id
                WHERE ci.carrinho_id = ?");
            $st->execute([$cartId]);
            $itens = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Buscar resumo do carrinho com taxa de conversão
            $stCart = $pdo->prepare("SELECT valor_total, taxa_servico, valor_impostos, peso_total, taxa_conversao, moeda FROM carrinhos WHERE id = ?");
            $stCart->execute([$cartId]);
            $resumo = $stCart->fetch(\PDO::FETCH_ASSOC);
            
            $taxaConversao = (float)($resumo['taxa_conversao'] ?? 1);
            if ($taxaConversao <= 0) $taxaConversao = 1;
            $moeda = $resumo['moeda'] ?? 'BRL';

            $this->responderJson([
                'itens' => array_map(function($i) use ($taxaConversao) {
                    return [
                        'nome' => $i['nome'],
                        'preco' => (float)$i['price'],
                        'quantidade' => (int)$i['quantidade'],
                        'subtotal' => (float)$i['subtotal'],
                        'subtotal_brl' => round((float)$i['subtotal'] * $taxaConversao, 2),
                    ];
                }, $itens),
                'resumo' => $resumo ? [
                    'total_usd' => (float)($resumo['valor_total'] ?? 0),
                    'total_brl' => round((float)($resumo['valor_total'] ?? 0) * $taxaConversao, 2),
                    'taxa_servico_usd' => (float)($resumo['taxa_servico'] ?? 0),
                    'taxa_servico_brl' => round((float)($resumo['taxa_servico'] ?? 0) * $taxaConversao, 2),
                    'impostos_usd' => (float)($resumo['valor_impostos'] ?? 0),
                    'impostos_brl' => round((float)($resumo['valor_impostos'] ?? 0) * $taxaConversao, 2),
                    'peso' => (float)($resumo['peso_total'] ?? 0),
                    'cambio' => $taxaConversao,
                ] : null,
                'total_itens' => array_sum(array_column($itens, 'quantidade')),
            ]);
        } catch (\Throwable $e) {
            $this->responderJson(['itens' => [], 'resumo' => null, 'error' => $e->getMessage()]);
        }
    }

    /** GET /api/copiloto/buscarproduto — Busca produtos no banco incluindo grupos de compras */
    public function buscarProduto(Request $request) {
        $termo = trim((string) $request->getParam('q', ''));
        $precoMin = (float) $request->getParam('preco_min', 0);
        $precoMax = (float) $request->getParam('preco_max', 0);
        
        // Detectar busca por preço no próprio termo: "price:20", "price:15-25"
        if (preg_match('/^price:(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/', $termo, $m)) {
            $precoMin = (float) $m[1];
            $precoMax = (float) $m[2];
            $termo = '';
        } elseif (preg_match('/^price:(\d+(?:\.\d+)?)$/', $termo, $m)) {
            $precoAlvo = (float) $m[1];
            $precoMin = $precoAlvo * 0.7;
            $precoMax = $precoAlvo * 1.3;
            $termo = '';
        }
        
        // Limpar pontuação e caracteres especiais
        $termo = preg_replace('/[?!.,;:()"\'\[\]{}]/', '', $termo);
        $termo = trim($termo);
        
        $buscaPorPreco = ($precoMin > 0 || $precoMax > 0);
        
        if (!$buscaPorPreco && mb_strlen($termo) < 2) {
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

            // Filtros de visibilidade — mesma lógica da página de grupos
            $filtros = [];
            if (in_array('oculto', $cols, true)) {
                $filtros[] = "(p.oculto IS NULL OR p.oculto = 0)";
            }
            if (in_array('active', $cols, true)) {
                $filtros[] = "(p.active IS NULL OR p.active = 1)";
            }
            if (in_array('ativo', $cols, true)) {
                $filtros[] = "(p.ativo IS NULL OR p.ativo = 1)";
            }
            if (in_array('status', $cols, true)) {
                $filtros[] = "(p.status IS NULL OR LOWER(COALESCE(p.status,'')) NOT IN ('archived','deleted','trash','lixeira','draft','rascunho'))";
            }
            $filtroSQL = !empty($filtros) ? ' AND ' . implode(' AND ', $filtros) : '';

            $temFoto = in_array('foto_principal', $cols, true);
            $fotoSelect = $temFoto ? ", p.foto_principal" : ", NULL as foto_principal";
            $temStock = in_array('stock', $cols, true);
            $temClubeAtivo = in_array('clube_ativo', $cols, true);
            $stockSelect = $temStock ? ", p.stock" : "";
            $clubeAtivoSelect = $temClubeAtivo ? ", COALESCE(p.clube_ativo, 0) as produto_clube_ativo" : ", 0 as produto_clube_ativo";

            // Construir WHERE clause
            $whereClause = '';
            $params = [];
            
            if ($buscaPorPreco) {
                // Busca por faixa de preço
                if ($precoMin > 0 && $precoMax > 0) {
                    $whereClause = "p.{$colPreco} BETWEEN ? AND ?";
                    $params = [$precoMin, $precoMax];
                } elseif ($precoMin > 0) {
                    $whereClause = "p.{$colPreco} >= ?";
                    $params = [$precoMin];
                } elseif ($precoMax > 0) {
                    $whereClause = "p.{$colPreco} <= ?";
                    $params = [$precoMax];
                }
                // Se também tem termo de busca, combinar
                if (!empty($termo)) {
                    $whereClause .= " AND p.{$colNome} LIKE ?";
                    $params[] = '%' . $termo . '%';
                }
                $orderBy = "ABS(p.{$colPreco} - ?) ASC";
                $params[] = ($precoMin + $precoMax) / 2; // Ordenar pelo mais próximo do valor alvo
            } else {
                // Busca por nome (e SKU se existir)
                $temSku = in_array('sku', $cols, true);
                if ($temSku) {
                    $whereClause = "(p.{$colNome} LIKE ? OR p.sku LIKE ?)";
                    $params = ['%' . $termo . '%', '%' . $termo . '%'];
                } else {
                    $whereClause = "p.{$colNome} LIKE ?";
                    $params = ['%' . $termo . '%'];
                }
                $orderBy = "p.{$colNome} ASC";
            }

            if ($temGrupoComprasId) {
                $sql = "SELECT p.id, p.{$colNome} AS nome, p.{$colPreco} AS preco, p.{$colPeso} AS peso,
                    p.grupo_compras_id,
                    COALESCE(gc.nome, '') as grupo_nome, COALESCE(gc.slug, '') as grupo_slug,
                    COALESCE(gc.clube_only, 0) as clube_only,
                    COALESCE(gc.cobra_imposto_eua, 0) as cobra_imposto_eua,
                    COALESCE(gc.imposto_local_percent, 0) as imposto_local_percent,
                    COALESCE(gc.ativo, 1) as grupo_ativo
                    {$fotoSelect}{$stockSelect}{$clubeAtivoSelect}
                    FROM produtos p
                    LEFT JOIN grupos_compras gc ON gc.id = p.grupo_compras_id
                    WHERE {$whereClause}{$filtroSQL}
                    AND (p.grupo_compras_id IS NULL OR gc.ativo = 1 OR gc.ativo IS NULL)
                    ORDER BY {$orderBy} LIMIT 10";
            } else {
                $sql = "SELECT p.id, p.{$colNome} AS nome, p.{$colPreco} AS preco, p.{$colPeso} AS peso,
                    NULL as grupo_compras_id, '' as grupo_nome, '' as grupo_slug,
                    0 as clube_only, 0 as cobra_imposto_eua, 0 as imposto_local_percent, 1 as grupo_ativo
                    {$fotoSelect}{$stockSelect}{$clubeAtivoSelect}
                    FROM produtos p
                    WHERE {$whereClause}{$filtroSQL}
                    ORDER BY {$orderBy} LIMIT 10";
            }

            $st = $pdo->prepare($sql);
            $st->execute($params);
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

            // Verificar se o usuário tem clube ativo
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            $usuarioClubeAtivo = false;
            if ($userId > 0) {
                try {
                    $stClube = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) as total FROM carteira_recargas WHERE usuario_id = ? AND LOWER(COALESCE(status,'')) IN ('paid','approved','credited')");
                    $stClube->execute([$userId]);
                    $saldoClube = (float) ($stClube->fetchColumn() ?: 0);
                    $usuarioClubeAtivo = ($saldoClube > 0);
                } catch (\Exception $e) {}
            }

            $this->responderJson([
                'produtos' => array_map(function($p) use ($usuarioClubeAtivo) {
                    $foto = $p['foto_principal'] ?? null;
                    if ($foto && strpos($foto, 'http') !== 0 && strpos($foto, '/') !== 0) {
                        $foto = '/uploads/produtos/' . $foto;
                    }
                    $clubeOnly = (int) ($p['clube_only'] ?? 0);
                    $produtoClubeAtivo = (int) ($p['produto_clube_ativo'] ?? 0);
                    $stock = isset($p['stock']) ? (int) $p['stock'] : null;
                    $semEstoque = ($stock !== null && $stock <= 0);
                    $acessoRestrito = false;
                    if (($clubeOnly || $produtoClubeAtivo) && !$usuarioClubeAtivo) {
                        $acessoRestrito = true;
                    }
                    return [
                        'id' => $p['id'],
                        'nome' => $p['nome'],
                        'preco' => $p['preco'],
                        'peso' => $p['peso'],
                        'grupo' => $p['grupo_slug'] ?: null,
                        'grupo_nome' => $p['grupo_nome'] ?: null,
                        'foto' => $foto,
                        'clube_only' => $clubeOnly,
                        'acesso_restrito' => $acessoRestrito,
                        'sem_estoque' => $semEstoque,
                        'imposto_local_pct' => (float) ($p['imposto_local_percent'] ?? 0),
                        'cobra_imposto_eua' => (int) ($p['cobra_imposto_eua'] ?? 0),
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
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            
            $raw = file_get_contents('php://input');
            $body = json_decode($raw ?: '', true);
            if (!is_array($body)) $body = [];
            
            $assunto = trim((string) ($body['assunto'] ?? $request->getParam('assunto', '')));
            $mensagem = trim((string) ($body['mensagem'] ?? $request->getParam('mensagem', '')));
            $categoria = trim((string) ($body['categoria'] ?? 'duvidas_gerais'));
            $numeroPedido = trim((string) ($body['numero_pedido'] ?? ''));

            // Se assunto ou mensagem vazios, usar defaults
            if (empty($assunto)) $assunto = 'Dúvida via Co-Piloto';
            if (empty($mensagem)) $mensagem = 'Ticket aberto via Co-Piloto Braziliana.';

            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($userId <= 0) {
                $this->responderJson(['erro' => 'Você precisa estar logado para abrir um ticket.'], 401);
            }

            $pdo = \Config\Database::getConnection();

            // Criar ticket real no sistema
            $colsTickets = [];
            try {
                $st = $pdo->query('DESCRIBE support_tickets');
                $colsTickets = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $this->responderJson(['erro' => 'Sistema de tickets não disponível.'], 500);
            }

            $pedidoId = null;
            if ($numeroPedido) {
                // Tentar encontrar o pedido pelo número
                try {
                    $stPed = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? OR codigo = ? LIMIT 1");
                    $stPed->execute([$numeroPedido, $numeroPedido]);
                    $pedidoId = $stPed->fetchColumn() ?: null;
                } catch (\Exception $e) {}
            }

            $motivo = $categoria === 'suporte' ? 'Suporte do Pedido' : 'Dúvida Geral';

            if (in_array('motivo', $colsTickets, true)) {
                $stIns = $pdo->prepare("INSERT INTO support_tickets (usuario_id, pedido_id, assunto, motivo, status) VALUES (?, ?, ?, ?, 'open')");
                $stIns->execute([$userId, $pedidoId, $assunto, $motivo]);
            } else {
                $stIns = $pdo->prepare("INSERT INTO support_tickets (usuario_id, pedido_id, assunto, status) VALUES (?, ?, ?, 'open')");
                $stIns->execute([$userId, $pedidoId, $assunto]);
            }
            $ticketId = (int) $pdo->lastInsertId();

            // Adicionar mensagem inicial
            $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, ?, ?, ?)')
                ->execute([$ticketId, 'cliente', $userId, $mensagem]);

            $this->responderJson([
                'sucesso' => true,
                'numero_ticket' => '#' . $ticketId,
                'prazo_resposta' => 'em breve',
            ]);
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro ticket: ' . $e->getMessage());
            $this->responderJson(['erro' => $e->getMessage()], 500);
        }
    }

    /** POST /api/copiloto/atualizarperfil — Atualizar dados do perfil do usuário */
    public function atualizarPerfil(Request $request) {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($userId <= 0) {
                $this->responderJson(['error' => 'Não logado'], 401);
            }

            $raw = file_get_contents('php://input');
            $body = json_decode($raw ?: '', true);
            if (!is_array($body) || empty($body['campos'])) {
                $this->responderJson(['error' => 'Nenhum campo para atualizar'], 400);
            }

            $campos = $body['campos'];
            $pdo = \Config\Database::getConnection();

            // Validações
            $erros = [];
            
            // Validar CPF/Documento
            if (isset($campos['documento']) || isset($campos['cpf'])) {
                $doc = $campos['documento'] ?? $campos['cpf'] ?? '';
                $docLimpo = preg_replace('/\D/', '', $doc);
                if (strlen($docLimpo) === 11) {
                    // Validar CPF
                    if (!$this->validarCpf($docLimpo)) {
                        $erros[] = 'CPF inválido';
                    } else {
                        // Verificar se já está em uso por outro usuário
                        $stDoc = $pdo->prepare("SELECT id FROM usuarios WHERE (documento = ? OR documento = ?) AND id != ?");
                        $stDoc->execute([$doc, $docLimpo, $userId]);
                        if ($stDoc->fetchColumn()) $erros[] = 'CPF já está em uso por outro usuário';
                    }
                    // Formatar CPF
                    if (empty($erros)) {
                        $campos['documento'] = substr($docLimpo,0,3).'.'.substr($docLimpo,3,3).'.'.substr($docLimpo,6,3).'-'.substr($docLimpo,9,2);
                        unset($campos['cpf']);
                    }
                } elseif (strlen($docLimpo) === 14) {
                    // CNPJ — aceitar sem validação complexa
                    $campos['documento'] = $doc;
                } elseif (!empty($doc)) {
                    $erros[] = 'CPF deve ter 11 dígitos';
                }
            }

            // Validar email
            if (isset($campos['email'])) {
                $email = trim($campos['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $erros[] = 'Email inválido';
                } else {
                    $stEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                    $stEmail->execute([$email, $userId]);
                    if ($stEmail->fetchColumn()) $erros[] = 'Email já está em uso por outro usuário';
                }
            }

            // Validar telefone
            if (isset($campos['telefone'])) {
                $tel = preg_replace('/\D/', '', $campos['telefone']);
                if (strlen($tel) < 10 || strlen($tel) > 15) {
                    $erros[] = 'Telefone inválido (mínimo 10 dígitos)';
                }
            }

            // Validar data de nascimento
            if (isset($campos['data_nascimento'])) {
                $dt = $campos['data_nascimento'];
                // Aceitar dd/mm/yyyy ou yyyy-mm-dd
                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dt, $m)) {
                    $dt = $m[3] . '-' . $m[2] . '-' . $m[1];
                    $campos['data_nascimento'] = $dt;
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) || !strtotime($dt)) {
                    $erros[] = 'Data de nascimento inválida';
                }
            }

            if (!empty($erros)) {
                $this->responderJson(['error' => implode('. ', $erros)], 400);
            }

            // Mapear campos permitidos para colunas do banco
            $permitidos = [
                'nome' => 'nome', 'name' => 'nome',
                'email' => 'email',
                'telefone' => 'telefone', 'phone' => 'telefone',
                'documento' => 'documento', 'cpf' => 'documento',
                'data_nascimento' => 'data_nascimento', 'birth_date' => 'data_nascimento',
            ];

            // Verificar quais colunas existem na tabela
            $cols = [];
            try { $cols = $pdo->query('DESCRIBE usuarios')->fetchAll(\PDO::FETCH_COLUMN) ?: []; } catch (\Exception $e) {}

            $updates = [];
            $params = [];
            foreach ($campos as $campo => $valor) {
                $coluna = $permitidos[$campo] ?? null;
                if (!$coluna) continue;
                // Verificar se a coluna existe
                if (!in_array($coluna, $cols, true)) {
                    // Tentar alternativas
                    $alternativas = ['nome' => 'name', 'name' => 'nome', 'telefone' => 'phone', 'phone' => 'telefone', 'documento' => 'cpf', 'cpf' => 'documento'];
                    $coluna = $alternativas[$coluna] ?? $coluna;
                    if (!in_array($coluna, $cols, true)) continue;
                }
                $updates[] = "{$coluna} = ?";
                $params[] = trim((string) $valor);
            }

            if (empty($updates)) {
                $this->responderJson(['error' => 'Nenhum campo válido para atualizar'], 400);
            }

            $params[] = $userId;
            $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?";
            $pdo->prepare($sql)->execute($params);

            // Atualizar endereço se campos de endereço foram enviados
            $endCampos = ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'pais'];
            $endUpdates = [];
            $endParams = [];
            foreach ($endCampos as $c) {
                if (isset($campos[$c])) {
                    // Verificar se coluna existe na tabela enderecos
                    $colEnd = $c;
                    if ($c === 'endereco' && !in_array('endereco', $colsEnd ?? [], true)) $colEnd = 'logradouro';
                    $endUpdates[] = "{$colEnd} = ?";
                    $endParams[] = trim((string) $campos[$c]);
                }
            }
            if (!empty($endUpdates)) {
                try {
                    $colsEnd = $pdo->query('DESCRIBE enderecos')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                } catch (\Exception $e) { $colsEnd = []; }
                
                // Atualizar endereço principal
                $endParams[] = $userId;
                $sqlEnd = "UPDATE enderecos SET " . implode(', ', $endUpdates) . " WHERE usuario_id = ? ORDER BY principal DESC, id DESC LIMIT 1";
                $stEnd = $pdo->prepare($sqlEnd);
                $stEnd->execute($endParams);
                
                // Se não atualizou nenhum (não tem endereço), criar um novo
                if ($stEnd->rowCount() === 0) {
                    $insertCols = ['usuario_id'];
                    $insertVals = ['?'];
                    $insertParams = [$userId];
                    foreach ($endCampos as $c) {
                        if (isset($campos[$c])) {
                            $insertCols[] = $c;
                            $insertVals[] = '?';
                            $insertParams[] = trim((string) $campos[$c]);
                        }
                    }
                    $insertCols[] = 'principal';
                    $insertVals[] = '1';
                    $pdo->prepare("INSERT INTO enderecos (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertVals) . ")")->execute($insertParams);
                }
            }

            // Atualizar sessão se nome mudou
            if (isset($campos['nome'])) $_SESSION['usuario_nome'] = $campos['nome'];

            $this->responderJson(['success' => true, 'campos_atualizados' => array_keys($campos)]);
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro atualizar perfil: ' . $e->getMessage());
            $this->responderJson(['error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/copiloto/prepararcheckout — Setar sessão para permitir acesso ao checkout */
    public function prepararCheckout(Request $request) {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $_SESSION['checkout_from_cart_at'] = time();
        $this->responderJson(['success' => true]);
    }

    /** POST /api/copiloto/orcamento — Gerar orçamento de assessoria via copiloto */
    public function orcamento(Request $request) {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            
            $raw = file_get_contents('php://input');
            $body = json_decode($raw ?: '', true);
            if (!is_array($body)) $body = [];
            
            $links = $body['links'] ?? [];
            if (!is_array($links) || empty($links)) {
                $this->responderJson(['erro' => 'Nenhum link fornecido'], 400);
            }

            $cleanLinks = [];
            foreach ($links as $l) {
                $l = trim((string) $l);
                if ($l === '') continue;
                // Adicionar https:// se não tem protocolo
                if ($l !== '' && !preg_match('/^https?:\/\//i', $l)) {
                    $l = 'https://' . $l;
                }
                if (filter_var($l, FILTER_VALIDATE_URL)) {
                    $cleanLinks[] = $l;
                }
            }
            if (empty($cleanLinks)) {
                $this->responderJson(['erro' => 'Nenhum link válido'], 400);
            }

            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($userId <= 0) {
                // Tentar fallback via remember_token
                if (!empty($_COOKIE['remember_token'])) {
                    try {
                        $pdo = \Config\Database::getConnection();
                        $stUser = $pdo->prepare("SELECT id FROM usuarios WHERE remember_token = ? LIMIT 1");
                        $stUser->execute([$_COOKIE['remember_token']]);
                        $userId = (int) ($stUser->fetchColumn() ?: 0);
                        if ($userId > 0) {
                            $_SESSION['usuario_id'] = $userId;
                        }
                    } catch (\Exception $e) {}
                }
                if ($userId <= 0) {
                    $this->responderJson(['erro' => 'Você precisa estar logado para gerar um orçamento. Faça login e tente novamente.'], 401);
                }
            }

            // Chamar o AssessoriaController diretamente
            // Injetar links no $_POST para que getBody() os encontre
            $originalPost = $_POST;
            $originalContentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $_POST = ['links' => $cleanLinks];
            $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded'; // Forçar getBody a ler $_POST
            
            // Fechar sessão atual para que enfileirarLinks possa abrir
            session_write_close();
            
            ob_start();
            try {
                $assessoria = new \App\Controllers\AssessoriaController();
                $fakeRequest = new Request();
                $fakeRequest->setParam('links', $cleanLinks); // Backup
                $assessoria->enfileirarLinks($fakeRequest);
            } catch (\Throwable $e) {
                // Capturar exceções do controller
            }
            $output = ob_get_clean();
            
            // Restaurar
            $_POST = $originalPost;
            $_SERVER['CONTENT_TYPE'] = $originalContentType;

            $result = json_decode($output ?: '', true);

            if (!empty($result['success']) && !empty($result['data'])) {
                $data = $result['data'];
                $baseUrl = rtrim((string) ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'brazilianashop.com.br'), '/');
                $orcamentoUrl = $baseUrl . '/assessoria/orcamento?orcamento_id=' . (int)($data['orcamento_id'] ?? 0);
                
                $this->responderJson([
                    'sucesso' => true,
                    'job_id' => $data['job_id'] ?? null,
                    'orcamento_id' => $data['orcamento_id'] ?? null,
                    'orcamento_url' => $orcamentoUrl,
                    'total_links' => $data['total'] ?? count($cleanLinks),
                ]);
            } else {
                $this->responderJson([
                    'erro' => $result['message'] ?? 'Erro ao gerar orçamento',
                    'debug' => substr($output ?: '', 0, 500),
                ], 500);
            }
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro orçamento: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->responderJson(['erro' => $e->getMessage()], 500);
        }
    }

    /** GET /api/copiloto/meuspedidos — Lista pedidos do usuário logado com status */
    public function meusPedidos(Request $request) {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($userId <= 0) {
                $this->responderJson(['error' => 'Não logado', 'pedidos' => []], 401);
            }

            $pdo = \Config\Database::getConnection();
            
            // Descobrir colunas disponíveis
            $cols = [];
            try {
                $stCols = $pdo->query('DESCRIBE pedidos');
                $cols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) { $cols = []; }

            $pickCol = function(array $candidates) use ($cols) {
                foreach ($candidates as $c) { if (in_array($c, $cols, true)) return $c; }
                return '';
            };

            $colUsuario = $pickCol(['usuario_id', 'user_id', 'cliente_id']);
            $colStatus = $pickCol(['status', 'status_pedido', 'pedido_status']);
            $colTotal = $pickCol(['valor_total', 'total', 'amount', 'valor']);
            $colMoeda = $pickCol(['moeda', 'currency', 'order_currency']);
            $colTracking = $pickCol(['tracking_code', 'codigo_rastreio', 'rastreamento']);
            $colCodigo = $pickCol(['codigo_pedido', 'numero_pedido', 'codigo', 'order_number']);
            $colFormaPag = $pickCol(['forma_pagamento', 'payment_method']);
            $colCreatedAt = $pickCol(['created_at', 'criado_em', 'data_pedido']);

            if (!$colUsuario) {
                $this->responderJson(['error' => 'Tabela pedidos não configurada', 'pedidos' => []], 500);
            }

            // Buscar pedidos do usuário (últimos 20)
            $selectCols = ['p.id'];
            if ($colStatus) $selectCols[] = "p.{$colStatus} AS status";
            if ($colTotal) $selectCols[] = "p.{$colTotal} AS total";
            if ($colMoeda) $selectCols[] = "p.{$colMoeda} AS moeda";
            if ($colTracking) $selectCols[] = "p.{$colTracking} AS tracking";
            if ($colCodigo) $selectCols[] = "p.{$colCodigo} AS codigo";
            if ($colFormaPag) $selectCols[] = "p.{$colFormaPag} AS forma_pagamento";
            if ($colCreatedAt) $selectCols[] = "p.{$colCreatedAt} AS data_pedido";

            $filtroDeleted = in_array('deleted_at', $cols, true) ? ' AND (p.deleted_at IS NULL)' : '';
            $orderCol = $colCreatedAt ?: 'p.id';

            $sql = "SELECT " . implode(', ', $selectCols) . " FROM pedidos p WHERE p.{$colUsuario} = ?{$filtroDeleted} ORDER BY {$orderCol} DESC LIMIT 20";
            $st = $pdo->prepare($sql);
            $st->execute([$userId]);
            $pedidos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Buscar itens de cada pedido (resumo)
            $temPedidoItems = false;
            try {
                $pdo->query('SELECT 1 FROM pedido_items LIMIT 1');
                $temPedidoItems = true;
            } catch (\Exception $e) {}

            $resultado = [];
            foreach ($pedidos as $ped) {
                $pedidoId = (int) $ped['id'];
                $itens = [];
                if ($temPedidoItems) {
                    try {
                        $stItems = $pdo->prepare("SELECT pi.produto_nome, pi.quantidade, pi.preco_unitario FROM pedido_items pi WHERE pi.pedido_id = ? LIMIT 10");
                        $stItems->execute([$pedidoId]);
                        $itens = $stItems->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\Exception $e) {}
                }
                // Se não tem pedido_items, tentar carrinho_items via carrinho_id
                if (empty($itens) && in_array('carrinho_id', $cols, true)) {
                    try {
                        $stCart = $pdo->prepare("SELECT p.name AS produto_nome, ci.quantidade, ci.subtotal AS preco_unitario FROM carrinho_items ci JOIN produtos p ON p.id = ci.produto_id WHERE ci.carrinho_id = (SELECT carrinho_id FROM pedidos WHERE id = ? LIMIT 1) LIMIT 10");
                        $stCart->execute([$pedidoId]);
                        $itens = $stCart->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\Exception $e) {}
                }

                $resultado[] = [
                    'id' => $pedidoId,
                    'codigo' => $ped['codigo'] ?? ('#' . $pedidoId),
                    'status' => $ped['status'] ?? 'desconhecido',
                    'total' => isset($ped['total']) ? (float) $ped['total'] : null,
                    'moeda' => $ped['moeda'] ?? 'USD',
                    'forma_pagamento' => $ped['forma_pagamento'] ?? null,
                    'tracking' => $ped['tracking'] ?? null,
                    'data' => $ped['data_pedido'] ?? null,
                    'itens' => array_map(function($i) {
                        return [
                            'nome' => $i['produto_nome'] ?? '',
                            'quantidade' => (int) ($i['quantidade'] ?? 1),
                            'preco' => (float) ($i['preco_unitario'] ?? 0),
                        ];
                    }, $itens),
                    'link' => '/meus-pedidos/' . $pedidoId,
                ];
            }

            // Filtro por número de pedido se fornecido
            $numeroPedido = trim((string) $request->getParam('numero', ''));
            if ($numeroPedido) {
                $resultado = array_values(array_filter($resultado, function($p) use ($numeroPedido) {
                    return stripos((string) $p['id'], $numeroPedido) !== false 
                        || stripos((string) ($p['codigo'] ?? ''), $numeroPedido) !== false;
                }));
            }

            $this->responderJson([
                'pedidos' => $resultado,
                'total' => count($resultado),
            ]);
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro meus pedidos: ' . $e->getMessage());
            $this->responderJson(['error' => $e->getMessage(), 'pedidos' => []], 500);
        }
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

    /** POST /api/copiloto/admin/chat — Chat interno para admin/suporte */
    public function adminChat(Request $request) {
        // Suprimir erros HTML na resposta JSON
        $prevDisplay = ini_get('display_errors');
        @ini_set('display_errors', '0');
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_role'] ?? ''))));
            if (!in_array($perfil, ['admin', 'administrator', 'administrador', 'suporte', 'support', 'vendedor', 'seller'], true)) {
                $this->responderJson(['resposta' => 'Acesso negado. Perfil: ' . ($perfil ?: 'vazio')], 403);
            }

            $body = $request->getBody();
            $mensagem = $this->sanitizar((string) ($body['mensagem'] ?? ''));
            if ($mensagem === '' || mb_strlen($mensagem) > 2000) {
                $this->responderJson(['resposta' => 'Mensagem vazia ou muito longa.'], 400);
            }

            $historico = is_array($body['historico'] ?? null) ? $body['historico'] : [];

            $service = new CopilotoService();
            $resultado = $service->chamarClaudeAdmin($mensagem, $historico);

            @ini_set('display_errors', $prevDisplay);
            $this->responderJson([
                'resposta' => $resultado['texto'],
                'tokens_usados' => $resultado['tokens_usados'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            @ini_set('display_errors', $prevDisplay);
            error_log('[CoPiloto Admin] Erro: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->responderJson(['resposta' => 'Erro: ' . $e->getMessage()], 500);
        }
    }
}
