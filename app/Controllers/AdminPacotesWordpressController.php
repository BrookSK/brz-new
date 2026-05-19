<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminPacotesWordpressController extends Controller {
    private \PDO $connection;
    private const SOURCES = ['br', 'red', 'us'];

    public function __construct() {
        $this->connection = Database::getConnection();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function ensureTables(): void {
        if ($this->tableExists('wp_packet_etiquetas')) {
            return;
        }
        $migrationFile = __DIR__ . '/../../database/migrations/190_create_wp_pacotes_schema.sql';
        if (is_file($migrationFile)) {
            $sql = file_get_contents($migrationFile);
            // Remove comments
            $sql = preg_replace('/--.*$/m', '', $sql);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if ($stmt !== '') {
                    try {
                        $this->connection->exec($stmt);
                    } catch (\Exception $e) {
                        // table may already exist
                    }
                }
            }
        }
    }

    private function connectWp(string $source): array {
        $source = strtolower(trim($source));
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'br';
        }

        $cat = 'wordpress_' . $source;
        $out = ['table_prefix' => 'wp_'];

        try {
            $cols = [];
            $st = $this->connection->query('DESCRIBE configuracoes_sistema');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            $hasCategoria = in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true);

            if ($hasCategoria) {
                $st = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
                foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'] as $k) {
                    $st->execute([$cat, $k]);
                    $v = $st->fetchColumn();
                    if ($v !== false && $v !== null) {
                        $out[$k] = (string) $v;
                    } elseif ($source === 'br') {
                        $st->execute(['wordpress', $k]);
                        $v2 = $st->fetchColumn();
                        if ($v2 !== false && $v2 !== null) {
                            $out[$k] = (string) $v2;
                        }
                    }
                }
            } else {
                $st = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'] as $k) {
                    $st->execute([$cat . '_' . $k]);
                    $v = $st->fetchColumn();
                    if ($v !== false && $v !== null) {
                        $out[$k] = (string) $v;
                    } elseif ($source === 'br') {
                        $st->execute(['wordpress_' . $k]);
                        $v2 = $st->fetchColumn();
                        if ($v2 !== false && $v2 !== null) {
                            $out[$k] = (string) $v2;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Configure o banco WordPress em Admin > Configurações > WordPress');
        }

        $host = trim((string) ($out['db_host'] ?? ''));
        $dbname = trim((string) ($out['db_name'] ?? ''));
        $user = trim((string) ($out['db_user'] ?? ''));
        $pass = (string) ($out['db_pass'] ?? '');
        $prefix = trim((string) ($out['table_prefix'] ?? 'wp_'));
        if ($prefix === '') $prefix = 'wp_';

        $port = null;
        if ($host !== '' && strpos($host, ':') !== false) {
            $parts = explode(':', $host, 2);
            $host = trim((string) ($parts[0] ?? ''));
            $portPart = trim((string) ($parts[1] ?? ''));
            if ($portPart !== '' && ctype_digit($portPart)) {
                $port = (int) $portPart;
            }
        }

        if ($host === '' || $dbname === '' || $user === '') {
            throw new \RuntimeException("Configure o banco WordPress ({$source}) em Admin > Configurações > WordPress");
        }

        $dsn = 'mysql:host=' . $host . ';' . ($port ? ('port=' . $port . ';') : '') . 'dbname=' . $dbname . ';charset=utf8mb4';
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return ['pdo' => $pdo, 'prefix' => $prefix];
    }

    // ─── Sincronização ─────────────────────────────────────────────────────────

    private function syncPackagesFromWp(string $source): array {
        $this->ensureTables();

        try {
            $wp = $this->connectWp($source);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $wpPdo = $wp['pdo'];
        $prefix = $wp['prefix'];

        // Buscar todos os packages publicados
        $sql = "
            SELECT p.ID, p.post_date, p.post_status
            FROM {$prefix}posts p
            WHERE p.post_type = 'package'
              AND p.post_status IN ('publish', 'draft')
            ORDER BY p.post_date DESC
            LIMIT 2000
        ";

        try {
            $st = $wpPdo->prepare($sql);
            $st->execute();
            $packages = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Erro ao consultar pacotes no WordPress: ' . $e->getMessage()];
        }

        if (empty($packages)) {
            return ['success' => true, 'synced' => 0, 'message' => 'Nenhum pacote encontrado.'];
        }

        $postIds = array_column($packages, 'ID');
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));

        // Buscar todos os postmeta de uma vez
        $metaSql = "SELECT post_id, meta_key, meta_value FROM {$prefix}postmeta WHERE post_id IN ({$placeholders})";
        $stMeta = $wpPdo->prepare($metaSql);
        $stMeta->execute($postIds);
        $allMeta = $stMeta->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Agrupar meta por post_id
        $metaByPost = [];
        foreach ($allMeta as $m) {
            $pid = (int) $m['post_id'];
            if (!isset($metaByPost[$pid])) $metaByPost[$pid] = [];
            $metaByPost[$pid][$m['meta_key']] = $m['meta_value'];
        }

        $synced = 0;
        $stUpsert = $this->connection->prepare("
            INSERT INTO wp_packet_etiquetas (origem, wp_post_id, pedido_id, cliente_nome, tracking_number, wp_container_post_id, meta_json, synced_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                pedido_id = VALUES(pedido_id),
                cliente_nome = VALUES(cliente_nome),
                tracking_number = VALUES(tracking_number),
                wp_container_post_id = VALUES(wp_container_post_id),
                meta_json = VALUES(meta_json),
                synced_at = NOW(),
                updated_at = NOW()
        ");

        foreach ($packages as $pkg) {
            $wpId = (int) $pkg['ID'];
            $meta = $metaByPost[$wpId] ?? [];

            $orderId = (int) ($meta['_package_order_id'] ?? 0);
            $tracking = (string) ($meta['_correios_tracking_code'] ?? '');
            $containerId = !empty($meta['_container_id']) ? (int) $meta['_container_id'] : null;
            $clienteNome = (string) ($meta['_recipient_name'] ?? '');

            // Se não tem recipient_name no meta, tentar buscar do pedido WooCommerce
            if ($clienteNome === '' && $orderId > 0) {
                $orderMeta = $metaByPost[$orderId] ?? [];
                $firstName = (string) ($orderMeta['_shipping_first_name'] ?? ($orderMeta['_billing_first_name'] ?? ''));
                $lastName = (string) ($orderMeta['_shipping_last_name'] ?? ($orderMeta['_billing_last_name'] ?? ''));
                $clienteNome = trim($firstName . ' ' . $lastName);
            }

            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $createdAt = $pkg['post_date'] ?? date('Y-m-d H:i:s');

            try {
                $stUpsert->execute([
                    $source,
                    $wpId,
                    $orderId > 0 ? $orderId : null,
                    $clienteNome !== '' ? substr($clienteNome, 0, 200) : null,
                    $tracking !== '' ? $tracking : null,
                    $containerId,
                    $metaJson,
                    $createdAt,
                ]);
                $synced++;
            } catch (\Exception $e) {
                // skip individual errors
            }
        }

        return ['success' => true, 'synced' => $synced, 'total' => count($packages)];
    }

    // ─── Dispatcher (nginx só repassa /admin/pacotes-wordpress) ───────────────

    public function dispatch(Request $request) {
        $action = trim((string) $request->getParam('action', ''));

        switch ($action) {
            case 'etiqueta-pdf':
                return $this->etiquetaPdf($request);
            case 'containers':
                return $this->containers($request);
            case 'container-novo':
                return $this->containerNovo($request);
            case 'container-detalhes':
                return $this->containerDetalhes($request);
            case 'faturas':
                return $this->faturas($request);
            case 'fatura-nova':
                return $this->faturaNova($request);
            default:
                return $this->index($request);
        }
    }

    public function dispatchPost(Request $request) {
        $action = trim((string) $request->getParam('action', ''));

        // Tentar ler do body JSON também
        if ($action === '') {
            $body = $request->getBody();
            $action = trim((string) ($body['action'] ?? ''));
        }

        switch ($action) {
            case 'sincronizar':
                return $this->sincronizar($request);
            case 'container-criar':
                return $this->containerCriar($request);
            case 'container-deletar':
                return $this->containerDeletar($request);
            case 'fatura-criar':
                return $this->faturaCriar($request);
            default:
                $this->json(['success' => false, 'error' => 'Ação não reconhecida.'], 400);
        }
    }

    // ─── Rotas Públicas (Views) ────────────────────────────────────────────────

    public function index(Request $request) {
        // DEBUG TEMPORÁRIO - remover após resolver
        if (isset($_GET['debug_action'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'GET' => $_GET,
                'action_from_request' => $request->getParam('action'),
                'action_from_get' => $_GET['action'] ?? null,
                'all_params' => $request->getParams(),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            ], JSON_PRETTY_PRINT);
            exit;
        }

        // Dispatch por action (fallback caso a rota aponte direto para index)
        $action = trim((string) $request->getParam('action', ''));
        
        // Fallback: ler direto do $_GET caso o Request não tenha capturado
        if ($action === '' && isset($_GET['action'])) {
            $action = trim((string) $_GET['action']);
        }
        
        if ($action !== '') {
            switch ($action) {
                case 'etiqueta-pdf':
                    $this->etiquetaPdf($request);
                    return;
                case 'containers':
                    $this->containers($request);
                    return;
                case 'container-novo':
                    $this->containerNovo($request);
                    return;
                case 'container-detalhes':
                    $this->containerDetalhes($request);
                    return;
                case 'faturas':
                    $this->faturas($request);
                    return;
                case 'fatura-nova':
                    $this->faturaNova($request);
                    return;
                case 'sincronizar':
                    $this->sincronizar($request);
                    return;
                case 'container-criar':
                    $this->containerCriar($request);
                    return;
                case 'container-deletar':
                    $this->containerDeletar($request);
                    return;
                case 'fatura-criar':
                    $this->faturaCriar($request);
                    return;
            }
        }

        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureTables();

        $filtroOrigem = strtolower(trim((string) $request->getParam('origem', '')));
        $filtroPedido = trim((string) $request->getParam('pedido', ''));
        $filtroTracking = trim((string) $request->getParam('tracking', ''));

        $where = '1=1';
        $params = [];

        if ($filtroOrigem !== '' && in_array($filtroOrigem, self::SOURCES, true)) {
            $where .= ' AND e.origem = ?';
            $params[] = $filtroOrigem;
        }
        if ($filtroPedido !== '') {
            $where .= ' AND e.pedido_id = ?';
            $params[] = (int) preg_replace('/\D/', '', $filtroPedido);
        }
        if ($filtroTracking !== '') {
            $where .= ' AND e.tracking_number LIKE ?';
            $params[] = '%' . $filtroTracking . '%';
        }

        $page = max(1, (int) $request->getParam('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // Total
        $stCount = $this->connection->prepare("SELECT COUNT(*) FROM wp_packet_etiquetas e WHERE {$where}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Dados
        $sql = "SELECT e.* FROM wp_packet_etiquetas e WHERE {$where} ORDER BY e.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $st = $this->connection->prepare($sql);
        $st->execute($params);
        $etiquetas = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Última sincronização
        $lastSync = null;
        try {
            $stSync = $this->connection->query("SELECT MAX(synced_at) FROM wp_packet_etiquetas");
            $lastSync = $stSync ? $stSync->fetchColumn() : null;
        } catch (\Exception $e) {}

        $this->view('admin/pacotes-wordpress/index', [
            'etiquetas' => $etiquetas,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'filtroOrigem' => $filtroOrigem,
            'filtroPedido' => $filtroPedido,
            'filtroTracking' => $filtroTracking,
            'lastSync' => $lastSync,
            'sidebarActive' => 'pacotes-wordpress',
        ]);
    }

    public function sincronizar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $origem = strtolower(trim((string) $request->getParam('origem', '')));
        $sources = ($origem !== '' && in_array($origem, self::SOURCES, true)) ? [$origem] : self::SOURCES;

        $results = [];
        foreach ($sources as $src) {
            $results[$src] = $this->syncPackagesFromWp($src);
        }

        $totalSynced = 0;
        $errors = [];
        foreach ($results as $src => $r) {
            if (!empty($r['success'])) {
                $totalSynced += (int) ($r['synced'] ?? 0);
            } else {
                $errors[] = strtoupper($src) . ': ' . ($r['error'] ?? 'Erro desconhecido');
            }
        }

        $this->json([
            'success' => empty($errors),
            'synced' => $totalSynced,
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    // ─── Containers ────────────────────────────────────────────────────────────

    public function containers(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureTables();

        $st = $this->connection->query("SELECT * FROM wp_packet_containers ORDER BY created_at DESC LIMIT 200");
        $containers = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];

        $this->view('admin/pacotes-wordpress/containers', [
            'containers' => $containers,
            'sidebarActive' => 'pacotes-wordpress',
        ]);
    }

    public function containerNovo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $this->ensureTables();

        // Buscar etiquetas sem container
        $st = $this->connection->prepare("
            SELECT e.*
            FROM wp_packet_etiquetas e
            WHERE e.container_id IS NULL
              AND e.tracking_number IS NOT NULL
              AND e.tracking_number != ''
            ORDER BY e.created_at DESC
            LIMIT 500
        ");
        $st->execute();
        $disponiveis = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->view('admin/pacotes-wordpress/container-novo', [
            'disponiveis' => $disponiveis,
            'sidebarActive' => 'pacotes-wordpress',
        ]);
    }

    public function containerCriar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $this->ensureTables();

        $nome = trim((string) $request->getParam('nome', ''));
        $trackings = $request->getParam('trackings', []);
        if (is_string($trackings)) {
            $trackings = array_filter(array_map('trim', preg_split('/[\n\r,;]+/', $trackings)));
        }

        if (empty($trackings)) {
            $this->json(['success' => false, 'error' => 'Selecione ao menos um tracking number.'], 400);
            return;
        }

        // Buscar próximo dispatch_number
        $stMax = $this->connection->query("SELECT COALESCE(MAX(dispatch_number), 0) + 1 FROM wp_packet_containers");
        $dispatchNumber = (int) ($stMax ? $stMax->fetchColumn() : 1);

        $trackingsJson = json_encode(array_values($trackings));

        $stInsert = $this->connection->prepare("
            INSERT INTO wp_packet_containers (nome, dispatch_number, tracking_numbers_json, status, created_at)
            VALUES (?, ?, ?, 'created', NOW())
        ");
        $stInsert->execute([
            $nome !== '' ? $nome : 'Container #' . $dispatchNumber,
            $dispatchNumber,
            $trackingsJson,
        ]);
        $containerId = (int) $this->connection->lastInsertId();

        // Atualizar etiquetas com o container_id
        $placeholders = implode(',', array_fill(0, count($trackings), '?'));
        $stUpdate = $this->connection->prepare("
            UPDATE wp_packet_etiquetas SET container_id = ?, updated_at = NOW()
            WHERE tracking_number IN ({$placeholders}) AND container_id IS NULL
        ");
        $stUpdate->execute(array_merge([$containerId], $trackings));

        $this->json([
            'success' => true,
            'container_id' => $containerId,
            'dispatch_number' => $dispatchNumber,
            'tracking_count' => count($trackings),
        ]);
    }

    public function containerDetalhes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureTables();

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            header('Location: /admin/pacotes-wordpress/containers');
            exit;
        }

        $st = $this->connection->prepare("SELECT * FROM wp_packet_containers WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $container = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$container) {
            header('Location: /admin/pacotes-wordpress/containers');
            exit;
        }

        // Buscar etiquetas deste container
        $stEtiq = $this->connection->prepare("SELECT * FROM wp_packet_etiquetas WHERE container_id = ? ORDER BY created_at DESC");
        $stEtiq->execute([$id]);
        $etiquetas = $stEtiq->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->view('admin/pacotes-wordpress/container-detalhes', [
            'container' => $container,
            'etiquetas' => $etiquetas,
            'sidebarActive' => 'pacotes-wordpress',
        ]);
    }

    public function containerDeletar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $this->ensureTables();

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID inválido.'], 400);
            return;
        }

        // Liberar etiquetas
        $this->connection->prepare("UPDATE wp_packet_etiquetas SET container_id = NULL, updated_at = NOW() WHERE container_id = ?")->execute([$id]);
        // Deletar container
        $this->connection->prepare("DELETE FROM wp_packet_containers WHERE id = ?")->execute([$id]);

        $this->json(['success' => true]);
    }

    // ─── Faturas (CN38) ────────────────────────────────────────────────────────

    public function faturas(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureTables();

        $st = $this->connection->query("SELECT * FROM wp_packet_bills ORDER BY created_at DESC LIMIT 100");
        $faturas = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];

        $this->view('admin/pacotes-wordpress/faturas', [
            'faturas' => $faturas,
            'sidebarActive' => 'pacotes-wordpress',
        ]);
    }

    public function faturaNova(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $this->ensureTables();

        // Containers sem fatura
        $st = $this->connection->prepare("SELECT * FROM wp_packet_containers WHERE bill_id IS NULL AND status = 'created' ORDER BY created_at DESC");
        $st->execute();
        $containers = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->view('admin/pacotes-wordpress/fatura-nova', [
            'containers' => $containers,
            'sidebarActive' => 'pacotes-wordpress',
        ]);
    }

    public function faturaCriar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $this->ensureTables();

        $containerIds = $request->getParam('container_ids', []);
        if (is_string($containerIds)) {
            $containerIds = array_filter(array_map('intval', explode(',', $containerIds)));
        }

        if (empty($containerIds)) {
            $this->json(['success' => false, 'error' => 'Selecione ao menos um container.'], 400);
            return;
        }

        // Contar trackings
        $placeholders = implode(',', array_fill(0, count($containerIds), '?'));
        $stCount = $this->connection->prepare("SELECT SUM(JSON_LENGTH(tracking_numbers_json)) FROM wp_packet_containers WHERE id IN ({$placeholders})");
        $stCount->execute($containerIds);
        $trackingCount = (int) ($stCount->fetchColumn() ?: 0);

        $containersJson = json_encode(array_values(array_map('intval', $containerIds)));

        // Gerar CN38 code placeholder
        $cn38Code = 'CN38-WP-' . date('Ymd') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $stInsert = $this->connection->prepare("
            INSERT INTO wp_packet_bills (cn38_code, status, containers_json, tracking_count, created_at)
            VALUES (?, 'pending', ?, ?, NOW())
        ");
        $stInsert->execute([$cn38Code, $containersJson, $trackingCount]);
        $billId = (int) $this->connection->lastInsertId();

        // Atualizar containers com bill_id
        $stUp = $this->connection->prepare("UPDATE wp_packet_containers SET bill_id = ?, status = 'billed', updated_at = NOW() WHERE id IN ({$placeholders})");
        $stUp->execute(array_merge([$billId], $containerIds));

        $this->json([
            'success' => true,
            'bill_id' => $billId,
            'cn38_code' => $cn38Code,
            'tracking_count' => $trackingCount,
        ]);
    }

    // ─── Download de etiqueta PDF (proxy do WP) ────────────────────────────────

    public function etiquetaPdf(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ID de etiqueta inválido.';
            exit;
        }

        $this->ensureTables();

        $st = $this->connection->prepare("SELECT * FROM wp_packet_etiquetas WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $etiqueta = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$etiqueta) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Etiqueta #' . $id . ' não encontrada na tabela local. Sincronize os pacotes primeiro.';
            exit;
        }

        // Tentar buscar o PDF diretamente do WordPress
        $origem = (string) ($etiqueta['origem'] ?? 'br');
        $wpPostId = (int) ($etiqueta['wp_post_id'] ?? 0);
        $tracking = (string) ($etiqueta['tracking_number'] ?? '');

        if ($tracking === '') {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Etiqueta #' . $id . ' não possui tracking number.';
            exit;
        }

        // Tentar buscar o PDF do WordPress via conexão direta
        try {
            $wp = $this->connectWp($origem);
            $wpPdo = $wp['pdo'];
            $prefix = $wp['prefix'];

            // Buscar _label_data do postmeta no WordPress
            $stLabel = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_label_data' LIMIT 1");
            $stLabel->execute([$wpPostId]);
            $labelData = (string) ($stLabel->fetchColumn() ?: '');

            if ($labelData !== '') {
                $pdfContent = base64_decode($labelData);
                if ($pdfContent !== false && strlen($pdfContent) > 100) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="etiqueta-' . $tracking . '.pdf"');
                    header('Content-Length: ' . strlen($pdfContent));
                    echo $pdfContent;
                    exit;
                }
            }

            // Tentar _correios_label_data
            $stLabel2 = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_correios_label_data' LIMIT 1");
            $stLabel2->execute([$wpPostId]);
            $labelData2 = (string) ($stLabel2->fetchColumn() ?: '');

            if ($labelData2 !== '') {
                $pdfContent = base64_decode($labelData2);
                if ($pdfContent !== false && strlen($pdfContent) > 100) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="etiqueta-' . $tracking . '.pdf"');
                    header('Content-Length: ' . strlen($pdfContent));
                    echo $pdfContent;
                    exit;
                }
            }
        } catch (\Exception $e) {
            // Falha ao conectar no WP, tentar do cache local
        }

        // Fallback: tentar do meta_json local
        $meta = json_decode((string) ($etiqueta['meta_json'] ?? '{}'), true) ?: [];
        $labelData = $meta['_label_data'] ?? ($meta['_correios_label_data'] ?? '');

        if ($labelData !== '') {
            $pdfContent = base64_decode($labelData);
            if ($pdfContent !== false && strlen($pdfContent) > 100) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="etiqueta-' . $tracking . '.pdf"');
                header('Content-Length: ' . strlen($pdfContent));
                echo $pdfContent;
                exit;
            }
        }

        // Se não tem PDF local, redirecionar para URL do WP se disponível
        $pdfUrl = (string) ($etiqueta['label_pdf_url'] ?? '');
        if ($pdfUrl !== '') {
            header('Location: ' . $pdfUrl);
            exit;
        }

        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'PDF da etiqueta não disponível para tracking ' . htmlspecialchars($tracking) . '. Gere a etiqueta no WordPress primeiro.';
        exit;
    }
}
