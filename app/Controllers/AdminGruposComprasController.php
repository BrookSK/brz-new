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

        // Coluna banner (imagem do grupo)
        try {
            $pdo->exec("ALTER TABLE grupos_compras ADD COLUMN banner VARCHAR(500) NULL DEFAULT NULL");
        } catch (\Throwable $e) {}

        // Coluna clube_only (grupo exclusivo do Clube Braziliana)
        try {
            $pdo->exec("ALTER TABLE grupos_compras ADD COLUMN clube_only TINYINT(1) NOT NULL DEFAULT 0");
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

        // ── Sync em batch: associar loja a produtos antigos que estão em grupo mas sem loja ──
        try {
            $colsProd = $pdo->query("DESCRIBE produtos")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            if (in_array('loja_id', $colsProd, true) && in_array('grupo_compras_id', $colsProd, true)) {
                // Buscar produtos que têm grupo mas não têm loja
                $stOrfaos = $pdo->query("
                    SELECT DISTINCT p.grupo_compras_id, g.nome AS grupo_nome
                    FROM produtos p
                    INNER JOIN grupos_compras g ON g.id = p.grupo_compras_id
                    WHERE p.grupo_compras_id IS NOT NULL
                      AND (p.loja_id IS NULL OR p.loja_id = 0)
                ");
                $orfaos = $stOrfaos ? $stOrfaos->fetchAll(\PDO::FETCH_ASSOC) : [];

                foreach ($orfaos as $orf) {
                    $gNome = trim((string) ($orf['grupo_nome'] ?? ''));
                    $gId = (int) ($orf['grupo_compras_id'] ?? 0);
                    if ($gNome === '' || $gId <= 0) continue;

                    // Buscar ou criar loja com o nome do grupo
                    $stFL = $pdo->prepare("SELECT id FROM lojas WHERE LOWER(nome) = LOWER(?) LIMIT 1");
                    $stFL->execute([$gNome]);
                    $lojaId = (int) ($stFL->fetchColumn() ?: 0);

                    if ($lojaId <= 0) {
                        $sL = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $gNome), '-'));
                        if ($sL === '') $sL = 'loja-' . time();
                        $chk = $pdo->prepare("SELECT id FROM lojas WHERE slug = ? LIMIT 1");
                        $chk->execute([$sL]);
                        if ($chk->fetchColumn()) $sL .= '-' . bin2hex(random_bytes(2));
                        $pdo->prepare("INSERT INTO lojas (nome, slug, ativo, created_at) VALUES (?, ?, 1, NOW())")->execute([$gNome, $sL]);
                        $lojaId = (int) $pdo->lastInsertId();
                    }

                    if ($lojaId > 0) {
                        $pdo->prepare("UPDATE produtos SET loja_id = ? WHERE grupo_compras_id = ? AND (loja_id IS NULL OR loja_id = 0)")->execute([$lojaId, $gId]);
                    }
                }
            }
        } catch (\Throwable $e) {}

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
        $clubeOnly = $request->getParam('clube_only') ? 1 : 0;

        if ($nome === '') {
            echo json_encode(['ok' => false, 'msg' => 'Nome obrigatório.']);
            return;
        }

        // Upload do banner
        $bannerUrl = null;
        $bannerKeep = trim((string) $request->getParam('banner_keep', ''));
        if ($bannerKeep !== '') {
            $bannerUrl = $bannerKeep;
        }
        if (isset($_FILES['banner']) && is_array($_FILES['banner'])) {
            $fName = (string) ($_FILES['banner']['name'] ?? '');
            $fTmp  = (string) ($_FILES['banner']['tmp_name'] ?? '');
            $fErr  = (int) ($_FILES['banner']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fErr === UPLOAD_ERR_OK && $fTmp !== '' && $fName !== '') {
                $ext = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
                    $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                    $uploadDir = $docRoot . '/uploads/grupos/';
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                    if (is_dir($uploadDir) && is_writable($uploadDir)) {
                        $fileName = 'grupo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (@move_uploaded_file($fTmp, $uploadDir . $fileName)) {
                            $bannerUrl = '/uploads/grupos/' . $fileName;
                        }
                    }
                }
            }
        }

        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            if (session_status() === PHP_SESSION_NONE) session_start();
            $userId = (int)($_SESSION['usuario_id'] ?? 0);
            $userName = (string)($_SESSION['usuario_nome'] ?? '');

            $slug = $this->uniqueSlug($pdo, $this->slugify($nome), $id ?: null);

            if ($id > 0) {
                $st = $pdo->prepare("UPDATE grupos_compras SET nome=?, slug=?, descricao=?, cobra_imposto_eua=?, imposto_local_percent=?, ativo=?, banner=?, clube_only=?, updated_at=NOW() WHERE id=?");
                $st->execute([$nome, $slug, $descricao, $cobraImposto, $impostoLocalPercent, $ativo, $bannerUrl, $clubeOnly, $id]);
            } else {
                $st = $pdo->prepare("INSERT INTO grupos_compras (nome, slug, descricao, cobra_imposto_eua, imposto_local_percent, ativo, banner, clube_only, criado_por, criado_por_nome, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
                $st->execute([$nome, $slug, $descricao, $cobraImposto, $impostoLocalPercent, 1, $bannerUrl, $clubeOnly, $userId ?: null, $userName ?: null]);
                $id = (int)$pdo->lastInsertId();
            }

            $st2 = $pdo->prepare("SELECT * FROM grupos_compras WHERE id=?");
            $st2->execute([$id]);
            $grupo = $st2->fetch(\PDO::FETCH_ASSOC);

            // Sincronizar loja_id dos produtos deste grupo
            try {
                $colsProd = $pdo->query("DESCRIBE produtos")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                if (in_array('loja_id', $colsProd, true) && in_array('grupo_compras_id', $colsProd, true)) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS lojas (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL UNIQUE, ativo TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $stFL = $pdo->prepare("SELECT id FROM lojas WHERE LOWER(nome) = LOWER(?) LIMIT 1");
                    $stFL->execute([$nome]);
                    $lojaIdSync = (int) ($stFL->fetchColumn() ?: 0);
                    if ($lojaIdSync <= 0) {
                        $sL = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $nome), '-'));
                        if ($sL === '') $sL = 'loja-' . time();
                        $chk = $pdo->prepare("SELECT id FROM lojas WHERE slug = ? LIMIT 1");
                        $chk->execute([$sL]);
                        if ($chk->fetchColumn()) $sL .= '-' . bin2hex(random_bytes(2));
                        $pdo->prepare("INSERT INTO lojas (nome, slug, ativo, created_at) VALUES (?, ?, 1, NOW())")->execute([$nome, $sL]);
                        $lojaIdSync = (int) $pdo->lastInsertId();
                    }
                    if ($lojaIdSync > 0) {
                        $pdo->prepare("UPDATE produtos SET loja_id = ? WHERE grupo_compras_id = ?")->execute([$lojaIdSync, $id]);
                    }
                }
            } catch (\Throwable $e) {}

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

            // Verificar estado atual antes de alternar
            $stCheck = $pdo->prepare("SELECT ativo, nome FROM grupos_compras WHERE id=?");
            $stCheck->execute([$id]);
            $row = $stCheck->fetch(\PDO::FETCH_ASSOC);
            $eraAtivo = (int) ($row['ativo'] ?? 0);

            $st = $pdo->prepare("UPDATE grupos_compras SET ativo = 1 - ativo WHERE id=?");
            $st->execute([$id]);
            $st2 = $pdo->prepare("SELECT ativo FROM grupos_compras WHERE id=?");
            $st2->execute([$id]);
            $ativo = (int)$st2->fetchColumn();

            // Se estava ativo e agora desativou → criar snapshot para histórico (Receita Federal)
            if ($eraAtivo === 1 && $ativo === 0) {
                try {
                    $this->criarSnapshotGrupo($pdo, $id);
                } catch (\Throwable $e) {
                    error_log('[SNAPSHOT] Erro ao criar snapshot do grupo ' . $id . ': ' . $e->getMessage());
                }
            }

            echo json_encode(['ok' => true, 'ativo' => $ativo]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    private function criarSnapshotGrupo(\PDO $pdo, int $grupoId): void {
        // Criar tabela de snapshots se não existir
        $pdo->exec("CREATE TABLE IF NOT EXISTS grupo_compras_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grupo_id INT NOT NULL,
            grupo_nome VARCHAR(255) NOT NULL,
            grupo_slug VARCHAR(255) NOT NULL,
            periodo_inicio DATETIME NULL,
            periodo_fim DATETIME NOT NULL,
            produtos_json LONGTEXT NOT NULL,
            qtd_produtos INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_grupo_id (grupo_id),
            INDEX idx_periodo (periodo_fim)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Buscar dados do grupo
        $stG = $pdo->prepare("SELECT * FROM grupos_compras WHERE id = ? LIMIT 1");
        $stG->execute([$grupoId]);
        $grupo = $stG->fetch(\PDO::FETCH_ASSOC);
        if (!$grupo) return;

        // Buscar todos os produtos do grupo com preços atuais
        $stP = $pdo->prepare("SELECT id, name, sku, price, sale_price, cost_price, weight, stock, ncm, foto_principal, created_at, updated_at FROM produtos WHERE grupo_compras_id = ? ORDER BY id ASC");
        $stP->execute([$grupoId]);
        $produtos = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Determinar período de início: último snapshot deste grupo ou created_at do grupo
        $periodoInicio = null;
        try {
            $stLast = $pdo->prepare("SELECT periodo_fim FROM grupo_compras_snapshots WHERE grupo_id = ? ORDER BY id DESC LIMIT 1");
            $stLast->execute([$grupoId]);
            $lastFim = $stLast->fetchColumn();
            if ($lastFim) {
                $periodoInicio = $lastFim;
            }
        } catch (\Throwable $e) {}

        if (!$periodoInicio) {
            $periodoInicio = $grupo['created_at'] ?? date('Y-m-d H:i:s');
        }

        $stIns = $pdo->prepare("INSERT INTO grupo_compras_snapshots (grupo_id, grupo_nome, grupo_slug, periodo_inicio, periodo_fim, produtos_json, qtd_produtos, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stIns->execute([
            $grupoId,
            (string) ($grupo['nome'] ?? ''),
            (string) ($grupo['slug'] ?? ''),
            $periodoInicio,
            date('Y-m-d H:i:s'),
            json_encode($produtos, JSON_UNESCAPED_UNICODE),
            count($produtos),
            date('Y-m-d H:i:s'),
        ]);
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
                SELECT g.id, g.nome, g.slug, g.descricao, g.cobra_imposto_eua, g.imposto_local_percent, g.banner, g.clube_only,
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
        $busca = trim((string) $request->getParam('q', ''));
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

            $grupoInativo = false;

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

            // Detectar coluna de nome
            $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : 'name');

            $whereBusca = '';
            $buscaParams = [$grupo['id']];
            if ($busca !== '') {
                $whereBusca = " AND " . $nameCol . " LIKE ?";
                $buscaParams[] = '%' . $busca . '%';
            }

            $stCount = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE grupo_compras_id=?" . $whereAtivo . $whereBusca);
            $stCount->execute($buscaParams);
            $total = (int) $stCount->fetchColumn();

            $stP = $pdo->prepare("SELECT * FROM produtos WHERE grupo_compras_id=?" . $whereAtivo . $whereBusca . " ORDER BY id DESC LIMIT " . $limit . " OFFSET " . $offset);
            $stP->execute($buscaParams);
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

        // Verificar restrição do Clube Braziliana
        $clubeOnly = (int) ($grupo['clube_only'] ?? 0);
        $clubeAcessoLiberado = true;
        $clubeSaldoUsd = 0.0;
        $clubeMinimo = 39.00;
        $clubeLogado = false;

        if ($clubeOnly) {
            $clubeAcessoLiberado = false;
            $auth = new \App\Services\AuthService();
            if ($auth->estaLogado()) {
                $clubeLogado = true;
                $usuarioLogado = $auth->getUsuarioLogado();
                $uid = (int) ($usuarioLogado['id'] ?? 0);
                if ($uid > 0) {
                    try {
                        $stW = $pdo->prepare('SELECT saldo_usd FROM carteiras WHERE usuario_id = ? LIMIT 1');
                        $stW->execute([$uid]);
                        $clubeSaldoUsd = (float) ($stW->fetchColumn() ?: 0);
                        if ($clubeSaldoUsd >= $clubeMinimo) {
                            $clubeAcessoLiberado = true;
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

        ob_start();
        include __DIR__ . '/../Views/grupo-compras/pagina.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/main.php';
    }

    // ── API: listar snapshots de um grupo (para admin) ──────────────────────
    public function listarSnapshots(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $grupoId = (int) $request->getParam('id', 0);
        try {
            $pdo = $this->getPdo();

            // Criar tabela se não existir
            $pdo->exec("CREATE TABLE IF NOT EXISTS grupo_compras_snapshots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                grupo_id INT NOT NULL,
                grupo_nome VARCHAR(255) NOT NULL,
                grupo_slug VARCHAR(255) NOT NULL,
                periodo_inicio DATETIME NULL,
                periodo_fim DATETIME NOT NULL,
                produtos_json LONGTEXT NOT NULL,
                qtd_produtos INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_grupo_id (grupo_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $st = $pdo->prepare("SELECT id, grupo_nome, periodo_inicio, periodo_fim, qtd_produtos, created_at FROM grupo_compras_snapshots WHERE grupo_id = ? ORDER BY id DESC");
            $st->execute([$grupoId]);
            $snapshots = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Buscar slug do grupo
            $stSlug = $pdo->prepare("SELECT slug FROM grupos_compras WHERE id = ? LIMIT 1");
            $stSlug->execute([$grupoId]);
            $slug = (string) ($stSlug->fetchColumn() ?: '');

            echo json_encode(['ok' => true, 'snapshots' => $snapshots, 'slug' => $slug]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ── Página pública: snapshot para Receita Federal ─────────────────────────
    public function paginaReceitaFederal(Request $request) {
        $slug = (string) $request->getParam('slug', '');
        $snapshotId = (int) $request->getParam('snapshot_id', 0);

        try {
            $pdo = $this->getPdo();

            // Buscar grupo (qualquer status)
            $stG = $pdo->prepare("SELECT * FROM grupos_compras WHERE slug = ? LIMIT 1");
            $stG->execute([$slug]);
            $grupo = $stG->fetch(\PDO::FETCH_ASSOC);

            if (!$grupo) {
                http_response_code(404);
                $title = 'Grupo não encontrado';
                ob_start();
                echo '<div class="container py-5 text-center"><h2>Grupo não encontrado.</h2></div>';
                $content = ob_get_clean();
                include __DIR__ . '/../Views/layouts/main.php';
                return;
            }

            $grupoId = (int) $grupo['id'];

            // Se snapshot_id = 0, listar todos os snapshots disponíveis
            if ($snapshotId <= 0) {
                $stSnaps = $pdo->prepare("SELECT id, grupo_nome, periodo_inicio, periodo_fim, qtd_produtos, created_at FROM grupo_compras_snapshots WHERE grupo_id = ? ORDER BY id DESC");
                $stSnaps->execute([$grupoId]);
                $snapshots = $stSnaps->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                $title = htmlspecialchars($grupo['nome'] ?? 'Grupo') . ' — Histórico (Receita Federal)';
                ob_start();
                echo '<div class="container py-4">';
                echo '<h2 class="mb-1"><i class="fas fa-archive me-2"></i>' . htmlspecialchars($grupo['nome'] ?? '') . '</h2>';
                echo '<p class="text-muted mb-4">Histórico de períodos — Receita Federal</p>';

                if (empty($snapshots)) {
                    echo '<div class="alert alert-info">Nenhum histórico disponível para este grupo.</div>';
                } else {
                    echo '<div class="list-group">';
                    foreach ($snapshots as $snap) {
                        $inicio = !empty($snap['periodo_inicio']) ? date('d/m/Y H:i', strtotime($snap['periodo_inicio'])) : 'N/A';
                        $fim = date('d/m/Y H:i', strtotime($snap['periodo_fim']));
                        $qtd = (int) $snap['qtd_produtos'];
                        $link = '/receita-federal/grupo/' . htmlspecialchars($slug) . '/' . (int) $snap['id'];
                        echo '<a href="' . $link . '" class="list-group-item list-group-item-action">'
                            . '<div class="d-flex justify-content-between align-items-center">'
                            . '<div>'
                            . '<strong>Período: ' . $inicio . ' — ' . $fim . '</strong>'
                            . '<div class="small text-muted">' . $qtd . ' produto(s)</div>'
                            . '</div>'
                            . '<i class="fas fa-chevron-right text-muted"></i>'
                            . '</div>'
                            . '</a>';
                    }
                    echo '</div>';
                }

                echo '<div class="mt-4"><a href="/produtos" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Voltar</a></div>';
                echo '</div>';
                $content = ob_get_clean();
                include __DIR__ . '/../Views/layouts/main.php';
                return;
            }

            // Buscar snapshot específico
            $stSnap = $pdo->prepare("SELECT * FROM grupo_compras_snapshots WHERE id = ? AND grupo_id = ? LIMIT 1");
            $stSnap->execute([$snapshotId, $grupoId]);
            $snapshot = $stSnap->fetch(\PDO::FETCH_ASSOC);

            if (!$snapshot) {
                http_response_code(404);
                $title = 'Histórico não encontrado';
                ob_start();
                echo '<div class="container py-5 text-center"><h2>Histórico não encontrado.</h2></div>';
                $content = ob_get_clean();
                include __DIR__ . '/../Views/layouts/main.php';
                return;
            }

            $produtos = json_decode($snapshot['produtos_json'] ?? '[]', true) ?: [];
            $grupoInativo = true;
            $total = count($produtos);
            $totalPages = 1;
            $page = 1;
            $busca = '';

            $periodoInicio = !empty($snapshot['periodo_inicio']) ? date('d/m/Y H:i', strtotime($snapshot['periodo_inicio'])) : 'N/A';
            $periodoFim = date('d/m/Y H:i', strtotime($snapshot['periodo_fim']));
            $snapshotInfo = ['inicio' => $periodoInicio, 'fim' => $periodoFim, 'id' => $snapshotId];

            $title = htmlspecialchars($grupo['nome'] ?? 'Grupo') . ' — ' . $periodoInicio . ' a ' . $periodoFim;

        } catch (\Throwable $e) {
            $title = 'Erro';
            ob_start();
            echo '<div class="container py-5 text-center"><h2>Erro ao carregar histórico.</h2></div>';
            $content = ob_get_clean();
            include __DIR__ . '/../Views/layouts/main.php';
            return;
        }

        $clube_acesso = false;

        ob_start();
        include __DIR__ . '/../Views/grupo-compras/pagina.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/main.php';
    }
}
