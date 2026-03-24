<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminGruposComprasController extends Controller {

    private function getPdo(): \PDO {
        return new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function ensureTables(\PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS grupos_compras (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            descricao TEXT NULL,
            cobra_imposto_eua TINYINT(1) NOT NULL DEFAULT 0,
            imposto_local_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_por INT NULL,
            criado_por_nome VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Coluna grupo_compras_id na tabela produtos
        try {
            $pdo->exec("ALTER TABLE produtos ADD COLUMN grupo_compras_id INT NULL DEFAULT NULL");
        } catch (\Throwable $e) {}

        // Coluna imposto_local_percent (migração)
        try {
            $pdo->exec("ALTER TABLE grupos_compras ADD COLUMN imposto_local_percent DECIMAL(5,2) NOT NULL DEFAULT 0");
        } catch (\Throwable $e) {}

        // Coluna imposto_local no pedido
        try {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN imposto_local DECIMAL(10,2) NOT NULL DEFAULT 0");
        } catch (\Throwable $e) {}
    }

    private function slugify(string $v): string {
        $v = mb_strtolower(trim($v));
        $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c'];
        $v = strtr($v, $map);
        $v = preg_replace('/[^a-z0-9\s\-]/', '', $v);
        $v = preg_replace('/[\s\-]+/', '-', $v);
        return trim($v, '-');
    }

    private function uniqueSlug(\PDO $pdo, string $base, ?int $excludeId = null): string {
        $slug = $base; $i = 2;
        while (true) {
            $st = $pdo->prepare("SELECT id FROM grupos_compras WHERE slug = ?" . ($excludeId ? " AND id != $excludeId" : ""));
            $st->execute([$slug]);
            if (!$st->fetchColumn()) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    // ── Admin: lista ──────────────────────────────────────────────────────────
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $grupos = [];
        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);
            $stmt = $pdo->query("
                SELECT g.*,
                    (SELECT COUNT(*) FROM produtos p WHERE p.grupo_compras_id = g.id) AS qtd_produtos,
                    (SELECT COUNT(*) FROM pedidos pd
                        INNER JOIN pedido_itens pi ON pi.pedido_id = pd.id
                        INNER JOIN produtos pr ON pr.id = pi.produto_id
                        WHERE pr.grupo_compras_id = g.id) AS qtd_pedidos
                FROM grupos_compras g ORDER BY g.created_at DESC
            ");
            $grupos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            try {
                $pdo = $this->getPdo();
                $this->ensureTables($pdo);
                $stmt = $pdo->query("SELECT * FROM grupos_compras ORDER BY created_at DESC");
                $grupos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($grupos as &$g) { $g['qtd_produtos'] = 0; $g['qtd_pedidos'] = 0; }
            } catch (\Throwable $e2) {}
        }

        $sidebarActive = 'grupos-compras';
        $title = 'Grupos de Compras';
        ob_start();
        include __DIR__ . '/../Views/admin/grupos-compras/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    // ── Admin: salvar (criar/editar) ──────────────────────────────────────────
    public function salvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $id = (int) $request->getParam('id', 0);
        $nome = trim((string) $request->getParam('nome', ''));
        $descricao = trim((string) $request->getParam('descricao', ''));
        $cobraImposto = $request->getParam('cobra_imposto_eua') ? 1 : 0;
        $impostoLocalPercent = (float) str_replace(',', '.', (string) $request->getParam('imposto_local_percent', '0'));
        if ($impostoLocalPercent < 0) $impostoLocalPercent = 0;
        if ($impostoLocalPercent > 99) $impostoLocalPercent = 99;
        $ativo = $request->getParam('ativo') !== null ? (int)$request->getParam('ativo') : 1;

        if ($nome === '') {
            echo json_encode(['ok' => false, 'msg' => 'Nome obrigatório.']);
            return;
        }

        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            if (session_status() === PHP_SESSION_NONE) session_start();
            $userId = (int)($_SESSION['usuario_id'] ?? 0);
            $userName = (string)($_SESSION['usuario_nome'] ?? '');

            $slug = $this->uniqueSlug($pdo, $this->slugify($nome), $id ?: null);

            if ($id > 0) {
                $st = $pdo->prepare("UPDATE grupos_compras SET nome=?, slug=?, descricao=?, cobra_imposto_eua=?, imposto_local_percent=?, ativo=?, updated_at=NOW() WHERE id=?");
                $st->execute([$nome, $slug, $descricao, $cobraImposto, $impostoLocalPercent, $ativo, $id]);
            } else {
                $st = $pdo->prepare("INSERT INTO grupos_compras (nome, slug, descricao, cobra_imposto_eua, imposto_local_percent, ativo, criado_por, criado_por_nome, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
                $st->execute([$nome, $slug, $descricao, $cobraImposto, $impostoLocalPercent, 1, $userId ?: null, $userName ?: null]);
                $id = (int)$pdo->lastInsertId();
            }

            $st2 = $pdo->prepare("SELECT * FROM grupos_compras WHERE id=?");
            $st2->execute([$id]);
            $grupo = $st2->fetch(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'grupo' => $grupo]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ── Admin: toggle ativo ───────────────────────────────────────────────────
    public function toggleAtivo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $id = (int) $request->getParam('id', 0);
        try {
            $pdo = $this->getPdo();
            $st = $pdo->prepare("UPDATE grupos_compras SET ativo = 1 - ativo WHERE id=?");
            $st->execute([$id]);
            $st2 = $pdo->prepare("SELECT ativo FROM grupos_compras WHERE id=?");
            $st2->execute([$id]);
            $ativo = (int)$st2->fetchColumn();
            echo json_encode(['ok' => true, 'ativo' => $ativo]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ── Admin: excluir ────────────────────────────────────────────────────────
    public function excluir(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $id = (int) $request->getParam('id', 0);
        try {
            $pdo = $this->getPdo();
            // Desvincula produtos antes de excluir
            $pdo->prepare("UPDATE produtos SET grupo_compras_id = NULL WHERE grupo_compras_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM grupos_compras WHERE id=?")->execute([$id]);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ── API: lista grupos (para cadastro rápido) ──────────────────────────────
    public function apiLista(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);
            $stmt = $pdo->query("SELECT id, nome, slug, cobra_imposto_eua, imposto_local_percent, ativo FROM grupos_compras ORDER BY nome ASC");
            $grupos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'grupos' => $grupos]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'grupos' => []]);
        }
    }

    // ── API: produtos do grupo ────────────────────────────────────────────────
    public function apiProdutos(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $id = (int) $request->getParam('id', 0);
        try {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT id, name AS nome, price AS preco, weight AS peso, stock AS estoque, foto_principal, status FROM produtos WHERE grupo_compras_id=? ORDER BY id DESC");
            $stmt->execute([$id]);
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'produtos' => $produtos]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'produtos' => []]);
        }
    }

    // ── API: remover produto do grupo ─────────────────────────────────────────
    public function apiRemoverProduto(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $produtoId = (int) $request->getParam('produto_id', 0);
        try {
            $pdo = $this->getPdo();
            $pdo->prepare("UPDATE produtos SET grupo_compras_id = NULL WHERE id=?")->execute([$produtoId]);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ── API: excluir produto completamente ────────────────────────────────────
    public function excluirProduto(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $produtoId = (int) $request->getParam('produto_id', 0);
        try {
            $pdo = $this->getPdo();
            $pdo->prepare("DELETE FROM produtos WHERE id=?")->execute([$produtoId]);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ── Página pública: todos os grupos ──────────────────────────────────────
    public function todosGrupos(Request $request) {
        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            $stmt = $pdo->query("
                SELECT g.id, g.nome, g.slug, g.descricao, g.cobra_imposto_eua, g.imposto_local_percent,
                    (SELECT COUNT(*) FROM produtos p WHERE p.grupo_compras_id = g.id) AS qtd_produtos
                FROM grupos_compras g
                WHERE g.ativo = 1
                ORDER BY g.nome ASC
            ");
            $grupos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $grupos = [];
        }

        $title = 'Grupos de Compras';
        ob_start();
        include __DIR__ . '/../Views/grupo-compras/todos.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/main.php';
    }

    // ── Página pública do grupo ───────────────────────────────────────────────
    public function paginaPublica(Request $request) {
        $slug = (string) $request->getParam('slug', '');
        $page = max(1, (int) $request->getParam('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            $st = $pdo->prepare("SELECT * FROM grupos_compras WHERE slug=? AND ativo=1 LIMIT 1");
            $st->execute([$slug]);
            $grupo = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$grupo) {
                http_response_code(404);
                $title = 'Grupo não encontrado';
                ob_start();
                echo '<div class="container py-5 text-center"><h2>Grupo não encontrado ou inativo.</h2><a href="/produtos" class="btn btn-primary mt-3">Ver produtos</a></div>';
                $content = ob_get_clean();
                include __DIR__ . '/../Views/layouts/main.php';
                return;
            }

            // Detectar coluna de status disponível
            $colsStmt = $pdo->query("DESCRIBE produtos");
            $cols = $colsStmt ? $colsStmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            $hasStatus = in_array('status', $cols, true);
            $hasAtivo  = in_array('ativo', $cols, true);
            $hasActive = in_array('active', $cols, true);

            $whereAtivo = '';
            if ($hasStatus)      $whereAtivo = " AND (status = 'published' OR status = 'active' OR status = '1')";
            elseif ($hasAtivo)   $whereAtivo = " AND ativo = 1";
            elseif ($hasActive)  $whereAtivo = " AND active = 1";

            $stCount = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE grupo_compras_id=?" . $whereAtivo);
            $stCount->execute([$grupo['id']]);
            $total = (int) $stCount->fetchColumn();

            $stP = $pdo->prepare("SELECT * FROM produtos WHERE grupo_compras_id=?" . $whereAtivo . " ORDER BY id DESC LIMIT " . $limit . " OFFSET " . $offset);
            $stP->execute([$grupo['id']]);
            $produtos = $stP->fetchAll(\PDO::FETCH_ASSOC);

            // Normalizar foto_principal
            foreach ($produtos as &$p) {
                $foto = trim((string) ($p['foto_principal'] ?? ''));
                if ($foto !== '' && !str_starts_with($foto, 'http')) {
                    $foto = '/' . ltrim($foto, '/');
                }
                $p['foto_principal'] = $foto ?: null;
                // Campos de compatibilidade
                $p['sale_price'] = $p['sale_price'] ?? 0;
                $p['short_description'] = $p['short_description'] ?? $p['excerpt'] ?? '';
                $p['featured'] = $p['featured'] ?? 0;
                $p['stock'] = $p['stock'] ?? $p['estoque'] ?? 0;
                $p['price'] = $p['price'] ?? $p['valor'] ?? 0;
                $p['name'] = $p['name'] ?? $p['nome'] ?? '';
                $p['categoria'] = $p['categoria'] ?? '';
                $p['is_variavel'] = false;
            }
            unset($p);

            // Verificar variações
            try {
                $ids = array_column($produtos, 'id');
                if (!empty($ids)) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $stV = $pdo->prepare("SELECT produto_id FROM produto_variacoes WHERE produto_id IN ($in) GROUP BY produto_id");
                    $stV->execute($ids);
                    $variaveis = array_flip($stV->fetchAll(\PDO::FETCH_COLUMN));
                    foreach ($produtos as &$p) {
                        $p['is_variavel'] = isset($variaveis[$p['id']]);
                    }
                    unset($p);
                }
            } catch (\Throwable $e) {}

        } catch (\Throwable $e) {
            http_response_code(500);
            $title = 'Erro';
            $errMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            ob_start();
            echo '<div class="container py-5 text-center"><h2>Erro ao carregar grupo.</h2><p class="text-muted small">' . $errMsg . '</p></div>';
            $content = ob_get_clean();
            include __DIR__ . '/../Views/layouts/main.php';
            return;
        }

        $totalPages = max(1, (int) ceil($total / $limit));
        $title = htmlspecialchars($grupo['nome'], ENT_QUOTES, 'UTF-8');

        ob_start();
        include __DIR__ . '/../Views/grupo-compras/pagina.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/main.php';
    }
}
