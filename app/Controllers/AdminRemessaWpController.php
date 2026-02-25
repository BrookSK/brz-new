<?php
namespace App\Controllers;

use Config\Database;
use App\Services\AuthService;

class AdminRemessaWpController extends Controller {
    private \PDO $connection;

    private const SOURCES = ['br','red','us'];

    public function __construct() {
        $this->connection = Database::getConnection();
    }

    private function requireAccess(): void {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'conferente']);
    }

    private function now(): \DateTime {
        return new \DateTime('now');
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getUploadsDirRemessaWp(): array {
        $baseDir = realpath(__DIR__ . '/../../public');
        if (!$baseDir) {
            $baseDir = __DIR__ . '/../../public';
        }
        $absDir = rtrim((string) $baseDir, '/\\') . '/uploads/remessa-wp';
        $webDir = '/uploads/remessa-wp';
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        return ['abs' => $absDir, 'web' => $webDir];
    }

    private function normalizeDocTipo(string $tipo): ?string {
        $tipo = strtolower(trim($tipo));
        $allowed = ['pagamento', 'medicamento'];
        return in_array($tipo, $allowed, true) ? $tipo : null;
    }

    private function normalizeSource(string $source): string {
        $source = strtolower(trim($source));
        return in_array($source, self::SOURCES, true) ? $source : 'br';
    }

    private function formatTriageGroup(string $val): string {
        $v = trim($val);
        if ($v === '') return '';
        $digits = preg_replace('/\D+/', '', $v);
        if ($digits === '') return $v;

        $map = [
            '1' => '1 - São Paulo/SP',
            '2' => '2 - Valinhos/SP',
            '3' => '3 - Rio de Janeiro/RJ',
            '4' => '4 - Curitiba/PR',
            '5' => '5 - Curitiba/PR',
        ];
        return $map[$digits] ?? $v;
    }

    private function getWpPdo(\PDO $localPdo, string $source = 'br'): array {
        $source = strtolower(trim($source));
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'br';
        }

        $out = ['table_prefix' => 'wp_'];
        $cat = 'wordpress_' . $source;

        try {
            $st = $localPdo->prepare("SHOW TABLES LIKE 'configuracoes_sistema'");
            $st->execute();
            $has = (bool) $st->fetchColumn();
            if (!$has) {
                throw new \RuntimeException('Configurações do WordPress não encontradas.');
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Configure o banco WordPress em Admin > Configurações > WordPress');
        }

        $cols = [];
        try {
            $st = $localPdo->query('DESCRIBE configuracoes_sistema');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $hasCategoria = in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true);
        if ($hasCategoria) {
            $st = $localPdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
            foreach (['db_host','db_name','db_user','db_pass','table_prefix'] as $k) {
                $val = null;
                $st->execute([$cat, $k]);
                $v1 = $st->fetchColumn();
                if ($v1 !== false && $v1 !== null) {
                    $val = $v1;
                } elseif ($source === 'br') {
                    $st->execute(['wordpress', $k]);
                    $v2 = $st->fetchColumn();
                    if ($v2 !== false && $v2 !== null) {
                        $val = $v2;
                    }
                }
                if ($val !== null) {
                    $out[$k] = (string) $val;
                }
            }
        } else {
            $st = $localPdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            foreach (['db_host','db_name','db_user','db_pass','table_prefix'] as $k) {
                $val = null;
                $st->execute([$cat . '_' . $k]);
                $v1 = $st->fetchColumn();
                if ($v1 !== false && $v1 !== null) {
                    $val = $v1;
                } elseif ($source === 'br') {
                    $st->execute(['wordpress_' . $k]);
                    $v2 = $st->fetchColumn();
                    if ($v2 !== false && $v2 !== null) {
                        $val = $v2;
                    }
                }
                if ($val !== null) {
                    $out[$k] = (string) $val;
                }
            }
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
            throw new \RuntimeException('Configure o banco WordPress em Admin > Configurações > WordPress');
        }

        $dsn = 'mysql:host=' . $host . ';' . ($port ? ('port=' . $port . ';') : '') . 'dbname=' . $dbname . ';charset=utf8mb4';
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return ['pdo' => $pdo, 'prefix' => $prefix];
    }

    private function ensureJanelaAtual(string $source): array {
        $now = $this->now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $stAll = $this->connection->prepare('SELECT id, data_inicio, data_fim, status FROM remessa_wp_janelas WHERE source = ? AND (tipo IS NULL OR tipo = \'auto\') ORDER BY data_inicio ASC');
        $stAll->execute([$source]);
        $all = $stAll->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Fecha janelas automáticas fora do padrão (13 dias) e normaliza status por data.
        foreach ($all as $j) {
            $dataIni = new \DateTime((string) $j['data_inicio']);
            $dataFim = new \DateTime((string) $j['data_fim']);
            $status = (string) ($j['status'] ?? 'aberta');

            $diffDays = (int) $dataIni->diff($dataFim)->days;
            if ($diffDays !== 12 && $status !== 'finalizada') {
                $stUp = $this->connection->prepare("UPDATE remessa_wp_janelas SET status = 'finalizada', updated_at = NOW() WHERE id = ?");
                $stUp->execute([(int) $j['id']]);
                continue;
            }

            // normaliza status baseado nas datas
            if ($dataFim < $now) {
                if ($status !== 'finalizada') {
                    $stUp = $this->connection->prepare("UPDATE remessa_wp_janelas SET status = 'finalizada', updated_at = NOW() WHERE id = ?");
                    $stUp->execute([(int) $j['id']]);
                }
            } elseif ($dataIni <= $now && $dataFim >= $now) {
                if ($status !== 'aberta') {
                    $stUp = $this->connection->prepare("UPDATE remessa_wp_janelas SET status = 'aberta', updated_at = NOW() WHERE id = ?");
                    $stUp->execute([(int) $j['id']]);
                }
            }
        }

        // Mantém apenas 1 janela aberta vigente (a que cobre o "agora").
        $st = $this->connection->prepare("SELECT * FROM remessa_wp_janelas WHERE source = ? AND (tipo IS NULL OR tipo = 'auto') AND status = 'aberta' AND data_inicio <= ? AND data_fim >= ? ORDER BY id ASC");
        $st->execute([$source, $nowStr, $nowStr]);
        $currRows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $current = $currRows[0] ?? null;
        if (count($currRows) > 1) {
            foreach (array_slice($currRows, 1) as $extra) {
                $stUp = $this->connection->prepare("UPDATE remessa_wp_janelas SET status = 'finalizada', updated_at = NOW() WHERE id = ?");
                $stUp->execute([(int) ($extra['id'] ?? 0)]);
            }
        }

        if (!$current) {
            // Cria a janela vigente ancorada no dia de hoje.
            $start = new \DateTime($now->format('Y-m-d 00:00:00'));
            $end = (clone $start);
            $end->modify('+12 days');
            $end->setTime(23, 59, 59);

            $stExists = $this->connection->prepare('SELECT id FROM remessa_wp_janelas WHERE source = ? AND (tipo IS NULL OR tipo = \'auto\') AND data_inicio = ? AND data_fim = ? LIMIT 1');
            $stExists->execute([$source, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
            $existingId = (int) ($stExists->fetchColumn() ?: 0);
            if ($existingId <= 0) {
                $stIns = $this->connection->prepare('INSERT INTO remessa_wp_janelas (source, data_inicio, data_fim, status, created_at, updated_at) VALUES (?, ?, ?, \'aberta\', NOW(), NOW())');
                $stIns->execute([
                    $source,
                    $start->format('Y-m-d H:i:s'),
                    $end->format('Y-m-d H:i:s'),
                ]);
                $existingId = (int) $this->connection->lastInsertId();
            }

            $stOne = $this->connection->prepare('SELECT * FROM remessa_wp_janelas WHERE id = ? LIMIT 1');
            $stOne->execute([$existingId]);
            $current = $stOne->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        if ($current && !empty($current['id'])) {
            $this->syncPedidosParaJanela((int) $current['id'], $source);
        }

        return $current ?: [];
    }

    private function syncPedidosParaJanela(int $janelaId, string $source): void {
        $stJ = $this->connection->prepare('SELECT id, data_inicio, data_fim FROM remessa_wp_janelas WHERE id = ? AND source = ? LIMIT 1');
        $stJ->execute([$janelaId, $source]);
        $j = $stJ->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$j) return;

        $inicio = (string) $j['data_inicio'];
        $fim = (string) $j['data_fim'];

        $wp = $this->getWpPdo($this->connection, $source);
        $prefix = $wp['prefix'];
        $wpPdo = $wp['pdo'];

        // Pedidos que tiveram etiqueta gerada no período: usa post_modified como proxy da data de geração.
        $stO = $wpPdo->prepare(
            "SELECT DISTINCT p.ID
             FROM {$prefix}posts p
             INNER JOIN {$prefix}postmeta pm ON pm.post_id = p.ID
             WHERE p.post_type = 'shop_order'
               AND p.post_modified >= ? AND p.post_modified <= ?
               AND pm.meta_key IN ('wexpress_label_url','_wexpress_label_url','wp_wexpress_label_url','wexpress_shipping_id')
               AND TRIM(COALESCE(pm.meta_value,'')) <> ''"
        );
        $stO->execute([$inicio, $fim]);
        $ids = $stO->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        if (!$ids) return;

        $stIns = $this->connection->prepare('INSERT IGNORE INTO remessa_wp_janela_pedidos (janela_id, source, order_id, created_at) VALUES (?, ?, ?, NOW())');
        foreach ($ids as $oid) {
            $stIns->execute([(int) $janelaId, $source, (int) $oid]);
        }

        $stMeta = $wpPdo->prepare("SELECT post_id, meta_key, meta_value FROM {$prefix}postmeta WHERE post_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND meta_key IN ('wexpress_shipping_id','wexpress_tracking_number','courier_tracking_number','wexpress_status','wexpress_label_url','_wexpress_label_url','wp_wexpress_label_url')");
        $stMeta->execute(array_map('intval', $ids));
        $rows = $stMeta->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $byOrder = [];
        foreach ($rows as $r) {
            $oid = (int) ($r['post_id'] ?? 0);
            if ($oid <= 0) continue;
            $k = (string) ($r['meta_key'] ?? '');
            $v = (string) ($r['meta_value'] ?? '');
            if (!isset($byOrder[$oid])) $byOrder[$oid] = [];
            $byOrder[$oid][$k] = $v;
        }

        $stUp = $this->connection->prepare(
            'UPDATE remessa_wp_janela_pedidos
             SET
                etiqueta_gerada = ?,
                etiqueta_gerada_em = IF(?, COALESCE(etiqueta_gerada_em, NOW()), etiqueta_gerada_em),
                wexpress_shipping_id = ?,
                wexpress_tracking_number = ?,
                courier_tracking_number = ?,
                wexpress_status = ?,
                wexpress_label_url = ?,
                wexpress_updated_at = NOW()
             WHERE janela_id = ? AND source = ? AND order_id = ?'
        );

        foreach ($ids as $oid) {
            $m = $byOrder[(int) $oid] ?? [];
            $shipId = trim((string) ($m['wexpress_shipping_id'] ?? ''));
            $trk = trim((string) ($m['wexpress_tracking_number'] ?? ''));
            $courier = trim((string) ($m['courier_tracking_number'] ?? ''));
            $status = trim((string) ($m['wexpress_status'] ?? ''));
            $label = trim((string) ($m['wexpress_label_url'] ?? ($m['_wexpress_label_url'] ?? ($m['wp_wexpress_label_url'] ?? ''))));

            $hasLabel = ($shipId !== '' || $label !== '' || $status === 'LABEL_CREATED');
            $stUp->execute([
                $hasLabel ? 1 : 0,
                $hasLabel ? 1 : 0,
                $shipId !== '' ? $shipId : null,
                $trk !== '' ? $trk : null,
                $courier !== '' ? $courier : null,
                $status !== '' ? $status : null,
                $label !== '' ? $label : null,
                (int) $janelaId,
                $source,
                (int) $oid,
            ]);
        }
    }

    private function getJanelasByStatus(string $source, array $statuses): array {
        $statuses = array_values(array_filter(array_map('strval', $statuses)));
        if (!$statuses) return [];
        $ph = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT * FROM remessa_wp_janelas WHERE source = ? AND status IN ({$ph}) ORDER BY data_inicio DESC";
        $st = $this->connection->prepare($sql);
        $st->execute(array_merge([$source], $statuses));
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function index($request) {
        $this->requireAccess();

        $sourceRaw = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'all'))));
        $source = $sourceRaw;
        if ($source !== 'all' && !in_array($source, self::SOURCES, true)) {
            $source = 'all';
        }

        if (!$this->tableExists('remessa_wp_janelas') || !$this->tableExists('remessa_wp_janela_pedidos')) {
            echo '<div class="alert alert-danger">Tabelas de Remessa WP não encontradas. Rode a migration: database/migrations/047_create_remessa_wp_janelas.sql</div>';
            exit;
        }

        $errorMsg = null;
        $janelasAbertas = [];
        $janelasFinalizadas = [];
        $janelasPrimeiraRemessa = [];

        try {
            if ($source === 'all') {
                foreach (self::SOURCES as $src) {
                    try {
                        $this->ensureJanelaAtual($src);
                    } catch (\Exception $e) {
                    }
                }

                // Corrigir janela manual "Primeira remessa" caso esteja com datas antigas absurdas
                try {
                    $startFix = new \DateTime('now');
                    $startFix->setTime(0, 0, 0);
                    $endFix = new \DateTime('now');
                    $endFix->setTime(23, 59, 59);
                    $stFix = $this->connection->prepare(
                        "UPDATE remessa_wp_janelas
                         SET data_inicio = ?, data_fim = ?, status = 'finalizada', updated_at = NOW()
                         WHERE tipo = 'manual'
                           AND titulo = 'Primeira remessa'
                           AND (TIMESTAMPDIFF(DAY, data_inicio, data_fim) > 40 OR data_fim > DATE_ADD(NOW(), INTERVAL 40 DAY))"
                    );
                    $stFix->execute([$startFix->format('Y-m-d H:i:s'), $endFix->format('Y-m-d H:i:s')]);
                } catch (\Exception $e) {
                }

                $stA = $this->connection->prepare(
                    "SELECT *
                     FROM remessa_wp_janelas
                     WHERE status = 'aberta'
                       AND (tipo IS NULL OR tipo = 'auto')
                       AND TIMESTAMPDIFF(DAY, data_inicio, data_fim) = 12
                     ORDER BY data_inicio DESC"
                );
                $stA->execute();
                $janelasAbertas = $stA->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                $stF = $this->connection->prepare(
                    "SELECT *
                     FROM remessa_wp_janelas
                     WHERE status = 'finalizada'
                       AND (tipo IS NULL OR tipo = 'auto')
                       AND TIMESTAMPDIFF(DAY, data_inicio, data_fim) = 12
                     ORDER BY data_inicio DESC"
                );
                $stF->execute();
                $janelasFinalizadas = $stF->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                $stM = $this->connection->prepare("SELECT * FROM remessa_wp_janelas WHERE tipo = 'manual' AND titulo = 'Primeira remessa' ORDER BY id DESC");
                $stM->execute();
                $janelasPrimeiraRemessa = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } else {
                $this->ensureJanelaAtual($source);

                // Corrigir janela manual "Primeira remessa" caso esteja com datas antigas absurdas
                try {
                    $startFix = new \DateTime('now');
                    $startFix->setTime(0, 0, 0);
                    $endFix = new \DateTime('now');
                    $endFix->setTime(23, 59, 59);
                    $stFix = $this->connection->prepare(
                        "UPDATE remessa_wp_janelas
                         SET data_inicio = ?, data_fim = ?, status = 'finalizada', updated_at = NOW()
                         WHERE source = ?
                           AND tipo = 'manual'
                           AND titulo = 'Primeira remessa'
                           AND (TIMESTAMPDIFF(DAY, data_inicio, data_fim) > 40 OR data_fim > DATE_ADD(NOW(), INTERVAL 40 DAY))"
                    );
                    $stFix->execute([$startFix->format('Y-m-d H:i:s'), $endFix->format('Y-m-d H:i:s'), $source]);
                } catch (\Exception $e) {
                }

                $janelasAbertas = $this->getJanelasByStatus($source, ['aberta']);
                $janelasFinalizadas = $this->getJanelasByStatus($source, ['finalizada']);

                // filtra legadas (não-13-dias)
                $janelasAbertas = array_values(array_filter($janelasAbertas, function ($j) {
                    try {
                        $di = new \DateTime((string) ($j['data_inicio'] ?? ''));
                        $df = new \DateTime((string) ($j['data_fim'] ?? ''));
                        return ((int) $di->diff($df)->days) === 12;
                    } catch (\Exception $e) {
                        return false;
                    }
                }));
                $janelasFinalizadas = array_values(array_filter($janelasFinalizadas, function ($j) {
                    try {
                        $di = new \DateTime((string) ($j['data_inicio'] ?? ''));
                        $df = new \DateTime((string) ($j['data_fim'] ?? ''));
                        return ((int) $di->diff($df)->days) === 12;
                    } catch (\Exception $e) {
                        return false;
                    }
                }));

                $stM = $this->connection->prepare("SELECT * FROM remessa_wp_janelas WHERE source = ? AND tipo = 'manual' AND titulo = 'Primeira remessa' ORDER BY id DESC");
                $stM->execute([$source]);
                $janelasPrimeiraRemessa = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $perfilAtual = '';
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $perfilAtual = (string) ($_SESSION['usuario_perfil'] ?? '');
            if ($perfilAtual === '') {
                $perfilAtual = (string) ($_SESSION['usuario_role'] ?? '');
            }
        } catch (\Exception $e) {
            $perfilAtual = '';
        }
        $perfilAtual = strtolower(trim($perfilAtual));

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remessa WP - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
<div class="container-fluid">
  <div class="row">';

        renderAdminSidebar('remessa-wp');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h3 mb-0"><i class="fab fa-wordpress me-2"></i>Remessa WP</h1>
                <div class="d-flex gap-2">
                    <form method="GET" action="/admin/remessa-wp" class="d-flex gap-2">
                        <select class="form-select" name="source" style="max-width: 160px;">
                            <option value="all"' . ($source === 'all' ? ' selected' : '') . '>Todos</option>
                            <option value="br"' . ($source === 'br' ? ' selected' : '') . '>BR</option>
                            <option value="red"' . ($source === 'red' ? ' selected' : '') . '>RED</option>
                            <option value="us"' . ($source === 'us' ? ' selected' : '') . '>US</option>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary">Filtrar</button>
                    </form>
                    <form method="POST" action="/admin/remessa-wp/primeira-remessa/popular?source=' . urlencode($source) . '" class="d-inline" onsubmit="return confirm(' . "\"Adicionar TODOS os pedidos já etiquetados na janela 'Primeira remessa'?\"" . ')">
                        <button type="submit" class="btn btn-outline-dark"><i class="fas fa-layer-group me-1"></i>Primeira remessa</button>
                    </form>
                    <button type="button" class="btn btn-outline-primary" onclick="location.reload()"><i class="fas fa-sync me-1"></i>Atualizar</button>
                </div>
            </div>';

        if ($errorMsg) {
            echo '<div class="alert alert-danger"><strong>Erro:</strong> ' . htmlspecialchars((string) $errorMsg) . '</div>';
        }

        echo '<div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><strong>Janelas Abertas (13 dias)</strong></div>
                    <div class="card-body">';

        if (!$janelasAbertas) {
            echo '<div class="text-muted">Nenhuma janela aberta.</div>';
        } else {
            echo '<div class="list-group">';
            foreach ($janelasAbertas as $j) {
                $jSource = strtolower(trim((string) ($j['source'] ?? $source)));
                if (!in_array($jSource, self::SOURCES, true)) {
                    $jSource = 'br';
                }
                $srcBadge = ($source === 'all') ? (' <span class="badge bg-light text-dark">' . htmlspecialchars(strtoupper($jSource)) . '</span>') : '';
                echo '<a class="list-group-item list-group-item-action" href="/admin/remessa-wp/janela/' . (int) $j['id'] . '?source=' . urlencode($jSource) . '">
                    <div class="d-flex justify-content-between">
                        <div><strong>Janela #' . (int) $j['id'] . '</strong>' . $srcBadge . ' <span class="text-muted">(' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_fim']))) . ')</span></div>
                        <span class="badge bg-success">Aberta</span>
                    </div>
                </a>';
            }
            echo '</div>';
        }

        echo '        </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><strong>Janelas Finalizadas</strong></div>
                    <div class="card-body">';

        if (!$janelasFinalizadas) {
            echo '<div class="text-muted">Nenhuma janela finalizada.</div>';
        } else {
            echo '<div class="list-group">';
            foreach (array_slice($janelasFinalizadas, 0, 10) as $j) {
                $jSource = strtolower(trim((string) ($j['source'] ?? $source)));
                if (!in_array($jSource, self::SOURCES, true)) {
                    $jSource = 'br';
                }
                $srcBadge = ($source === 'all') ? (' <span class="badge bg-light text-dark">' . htmlspecialchars(strtoupper($jSource)) . '</span>') : '';
                echo '<a class="list-group-item list-group-item-action" href="/admin/remessa-wp/janela/' . (int) $j['id'] . '?source=' . urlencode($jSource) . '">
                    <div class="d-flex justify-content-between">
                        <div><strong>Janela #' . (int) $j['id'] . '</strong>' . $srcBadge . ' <span class="text-muted">(' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_fim']))) . ')</span></div>
                        <span class="badge bg-secondary">Finalizada</span>
                    </div>
                </a>';
            }
            echo '</div>';
        }

        echo '        </div>
                </div>
            </div>
        </div>';

        echo '<div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><strong>Primeira remessa</strong></div>
                    <div class="card-body">';

        if (!$janelasPrimeiraRemessa) {
            echo '<div class="text-muted">Nenhuma janela encontrada. Use o botão <strong>Primeira remessa</strong> acima para criar/popular.</div>';
        } else {
            echo '<div class="list-group">';
            foreach ($janelasPrimeiraRemessa as $j) {
                $title = trim((string) ($j['titulo'] ?? 'Primeira remessa'));
                if ($title === '') $title = 'Primeira remessa';
                $jSource = strtolower(trim((string) ($j['source'] ?? $source)));
                if (!in_array($jSource, self::SOURCES, true)) {
                    $jSource = 'br';
                }
                $srcBadge = ($source === 'all') ? (' <span class="badge bg-light text-dark">' . htmlspecialchars(strtoupper($jSource)) . '</span>') : '';
                echo '<a class="list-group-item list-group-item-action" href="/admin/remessa-wp/janela/' . (int) $j['id'] . '?source=' . urlencode($jSource) . '">
                    <div class="d-flex justify-content-between">
                        <div><strong>' . htmlspecialchars($title) . '</strong>' . $srcBadge . ' <span class="text-muted">(' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_fim']))) . ')</span></div>
                        <span class="badge bg-warning text-dark">Manual</span>
                    </div>
                </a>';
            }
            echo '</div>';
        }

        echo '        </div>
                </div>
            </div>
        </div>';

        echo '</main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function verJanela($request, $id) {
        $this->requireAccess();

        $source = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        if (!in_array($source, self::SOURCES, true)) $source = 'br';

        $janelaId = (int) $id;
        if ($janelaId <= 0) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }

        $stJ = $this->connection->prepare('SELECT * FROM remessa_wp_janelas WHERE id = ? AND source = ? LIMIT 1');
        $stJ->execute([$janelaId, $source]);
        $janela = $stJ->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$janela) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }

        $tipoJanela = strtolower(trim((string) ($janela['tipo'] ?? 'auto')));
        if ($tipoJanela === '') $tipoJanela = 'auto';
        if ($tipoJanela !== 'manual') {
            $this->syncPedidosParaJanela($janelaId, $source);
        }

        $st = $this->connection->prepare('SELECT * FROM remessa_wp_janela_pedidos WHERE janela_id = ? AND source = ? ORDER BY created_at DESC');
        $st->execute([$janelaId, $source]);
        $links = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $orderIds = array_values(array_filter(array_map(function ($r) {
            return (int) ($r['order_id'] ?? 0);
        }, $links), function ($v) {
            return $v > 0;
        }));

        $orders = [];
        $ordersMeta = [];
        $ordersQty = [];
        if ($orderIds) {
            try {
                $wp = $this->getWpPdo($this->connection, $source);
                $prefix = $wp['prefix'];
                $wpPdo = $wp['pdo'];

                $ph = implode(',', array_fill(0, count($orderIds), '?'));
                $sql = "SELECT ID, post_date, post_status, post_title FROM {$prefix}posts WHERE ID IN ({$ph})";
                $stO = $wpPdo->prepare($sql);
                $stO->execute($orderIds);
                $rows = $stO->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $orders[(int) $r['ID']] = $r;
                }

                // meta (nome e postcode)
                $metaKeys = [
                    '_billing_first_name', '_billing_last_name',
                    '_shipping_first_name', '_shipping_last_name',
                    '_shipping_postcode', '_billing_postcode',
                    'wexpress_shipping_id',
                    'wexpress_label_url', '_wexpress_label_url', 'wp_wexpress_label_url',
                ];
                $phK = implode(',', array_fill(0, count($metaKeys), '?'));
                $sqlM = "SELECT post_id, meta_key, meta_value FROM {$prefix}postmeta WHERE post_id IN ({$ph}) AND meta_key IN ({$phK})";
                $stM = $wpPdo->prepare($sqlM);
                $stM->execute(array_merge($orderIds, $metaKeys));
                $rowsM = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rowsM as $rm) {
                    $oid = (int) ($rm['post_id'] ?? 0);
                    $k = (string) ($rm['meta_key'] ?? '');
                    if ($oid <= 0 || $k === '') continue;
                    if (!isset($ordersMeta[$oid])) $ordersMeta[$oid] = [];
                    $ordersMeta[$oid][$k] = (string) ($rm['meta_value'] ?? '');
                }

                // quantidade total (soma de _qty)
                $sqlQ = "SELECT oi.order_id AS order_id, SUM(CAST(oim.meta_value AS DECIMAL(18,2))) AS qty
                    FROM {$prefix}woocommerce_order_items oi
                    INNER JOIN {$prefix}woocommerce_order_itemmeta oim
                        ON oim.order_item_id = oi.order_item_id
                       AND oim.meta_key = '_qty'
                    WHERE oi.order_item_type = 'line_item'
                      AND oi.order_id IN ({$ph})
                    GROUP BY oi.order_id";
                $stQ = $wpPdo->prepare($sqlQ);
                $stQ->execute($orderIds);
                $rowsQ = $stQ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rowsQ as $rq) {
                    $oid = (int) ($rq['order_id'] ?? 0);
                    if ($oid <= 0) continue;
                    $q = (float) ($rq['qty'] ?? 0);
                    $ordersQty[$oid] = (int) round($q);
                }
            } catch (\Exception $e) {
            }
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $perfilAtual = '';
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $perfilAtual = (string) ($_SESSION['usuario_perfil'] ?? '');
            if ($perfilAtual === '') {
                $perfilAtual = (string) ($_SESSION['usuario_role'] ?? '');
            }
        } catch (\Exception $e) {
            $perfilAtual = '';
        }
        $perfilAtual = strtolower(trim($perfilAtual));

        $tituloJanela = trim((string) ($janela['titulo'] ?? ''));
        $tituloLabel = $tituloJanela !== '' ? $tituloJanela : ('Janela #' . (int) $janelaId);
        $badge = ($tipoJanela === 'manual') ? '<span class="badge bg-warning text-dark">Manual</span>' : '<span class="badge bg-success">Automática</span>';
        $totalPedidosJanela = is_array($links) ? count($links) : 0;

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remessa WP - ' . htmlspecialchars($tituloLabel) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
<div class="container-fluid"><div class="row">';
        renderAdminSidebar('remessa-wp');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h4 mb-0">' . htmlspecialchars($tituloLabel) . ' ' . $badge . ' <span class="badge bg-light text-dark">Pedidos: ' . (int) $totalPedidosJanela . '</span></h1>
                    <div class="text-muted small">' . htmlspecialchars(date('d/m/Y', strtotime((string) $janela['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $janela['data_fim']))) . '</div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="/admin/remessa-wp?source=' . urlencode($source) . '">Voltar</a>
                    <button class="btn btn-outline-primary" type="button" onclick="location.reload()">Atualizar</button>
                    <button class="btn btn-danger" type="button" onclick="regerarEtiquetasMassa()">Regerar etiquetas (já geradas)</button>
                </div>
            </div>';

        if ($tipoJanela === 'manual') {
        }

        echo '<div class="card">
                <div class="card-header"><strong>Pedidos</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>ZIP/CEP</th>
                                    <th>Qtd</th>
                                    <th>Etiqueta</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (!$links) {
            echo '<tr><td colspan="7" class="text-center text-muted">Nenhum pedido nesta janela.</td></tr>';
        } else {
            foreach ($links as $lnk) {
                $oid = (int) ($lnk['order_id'] ?? 0);
                $o = $orders[$oid] ?? [];
                $date = (string) ($o['post_date'] ?? '');

                $m = $ordersMeta[$oid] ?? [];
                $nome = trim((string) (($m['_shipping_first_name'] ?? '') . ' ' . ($m['_shipping_last_name'] ?? '')));
                if ($nome === '') {
                    $nome = trim((string) (($m['_billing_first_name'] ?? '') . ' ' . ($m['_billing_last_name'] ?? '')));
                }
                $zip = trim((string) ($m['_shipping_postcode'] ?? ''));
                if ($zip === '') {
                    $zip = trim((string) ($m['_billing_postcode'] ?? ''));
                }

                $qtd = (int) ($ordersQty[$oid] ?? 0);

                $etq = ((int) ($lnk['etiqueta_gerada'] ?? 0)) === 1;

                // Etiqueta: preferir a URL mais recente do WordPress (meta), pois a remessa pode ter cache antigo
                $labelUrl = '';
                $shipId = trim((string) ($m['wexpress_shipping_id'] ?? ''));
                $labelUrl = trim((string) ($m['wexpress_label_url'] ?? ($m['_wexpress_label_url'] ?? ($m['wp_wexpress_label_url'] ?? ''))));
                if ($labelUrl === '' && $shipId !== '') {
                    $labelUrl = 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($shipId);
                }
                if ($labelUrl === '') {
                    $labelUrl = (string) ($lnk['wexpress_label_url'] ?? '');
                }

                echo '<tr>
                    <td><strong>#' . (int) $oid . '</strong></td>
                    <td>' . ($date !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($date))) : '-') . '</td>
                    <td>' . htmlspecialchars($nome !== '' ? $nome : '-') . '</td>
                    <td>' . htmlspecialchars($zip !== '' ? $zip : '-') . '</td>
                    <td>' . ($qtd > 0 ? (int) $qtd : '-') . '</td>
                    <td>' . ($etq ? '<span class="badge bg-success">Gerada</span>' : '<span class="badge bg-warning text-dark">Pendente</span>') . '</td>
                    <td class="text-nowrap">'
                        . ($labelUrl !== '' ? ('<a class="btn btn-sm btn-outline-primary" target="_blank" href="' . htmlspecialchars($labelUrl) . '">Etiqueta</a> ') : '')
                        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/remessa-wp/janela/' . (int) $janelaId . '/pedido/' . (int) $oid . '?source=' . urlencode($source) . '">Detalhes</a> '
                        . ($perfilAtual !== 'conferente' ? (' <a class="btn btn-sm btn-outline-secondary" href="/admin/pedidos-wp/detalhes/' . (int) $oid . '?source=' . urlencode($source) . '">Pedido</a>') : '')
                    . '</td>
                </tr>';
            }
        }

        echo '            </tbody></table></div>
                </div>
            </div>
        </main></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function regerarEtiquetasMassa() {
    if (!confirm("Regerar todas as etiquetas já geradas desta janela?")) return;

    const rows = ' . json_encode($links, JSON_UNESCAPED_UNICODE) . ';
    const source = ' . json_encode($source, JSON_UNESCAPED_UNICODE) . ';

    const targets = rows.filter(r => parseInt(r.etiqueta_gerada || 0) === 1).map(r => parseInt(r.order_id || 0)).filter(id => id > 0);
    if (!targets.length) {
        alert("Nenhuma etiqueta gerada para regerar nesta janela.");
        return;
    }

    const pad2 = (n) => String(n).padStart(2, "0");
    const fmt = (d) => pad2(d.getDate()) + "/" + pad2(d.getMonth() + 1) + "/" + d.getFullYear() + " " + pad2(d.getHours()) + ":" + pad2(d.getMinutes()) + ":" + pad2(d.getSeconds());
    const startedAt = new Date();

    let box = document.getElementById("wx-regerar-progress");
    if (!box) {
        box = document.createElement("div");
        box.id = "wx-regerar-progress";
        box.style.position = "fixed";
        box.style.right = "16px";
        box.style.bottom = "16px";
        box.style.width = "360px";
        box.style.zIndex = "9999";
        box.style.background = "#fff";
        box.style.border = "1px solid rgba(0,0,0,.15)";
        box.style.borderRadius = "8px";
        box.style.boxShadow = "0 10px 24px rgba(0,0,0,.15)";
        box.style.padding = "12px";
        box.innerHTML = "<div style=\"display:flex;justify-content:space-between;align-items:center;gap:8px;\">" +
            "<div style=\"font-weight:600;\">Regerando etiquetas</div>" +
            "<button type=\"button\" id=\"wx-regerar-close\" class=\"btn btn-sm btn-outline-secondary\">Ocultar</button>" +
        "</div>" +
        "<div class=\"mt-2\" style=\"font-size:12px;\">" +
            "<div><strong>Início:</strong> <span id=\"wx-regerar-start\"></span></div>" +
            "<div><strong>Última atualização:</strong> <span id=\"wx-regerar-last\">-</span></div>" +
            "<div><strong>Pedido atual:</strong> <span id=\"wx-regerar-current\">-</span></div>" +
            "<div><strong>Progresso:</strong> <span id=\"wx-regerar-count\">0/0</span></div>" +
        "</div>" +
        "<div class=\"progress mt-2\" style=\"height:10px;\"><div id=\"wx-regerar-bar\" class=\"progress-bar\" role=\"progressbar\" style=\"width:0%\"></div></div>" +
        "<div class=\"mt-2\" style=\"font-size:12px;color:#6c757d;\">Não feche a página enquanto estiver processando.</div>";
        document.body.appendChild(box);
        document.getElementById("wx-regerar-close").onclick = () => { box.style.display = "none"; };
    } else {
        box.style.display = "block";
    }

    const elStart = document.getElementById("wx-regerar-start");
    const elLast = document.getElementById("wx-regerar-last");
    const elCurrent = document.getElementById("wx-regerar-current");
    const elCount = document.getElementById("wx-regerar-count");
    const elBar = document.getElementById("wx-regerar-bar");
    if (elStart) elStart.textContent = fmt(startedAt);

    let idx = 0;
    const total = targets.length;
    const updateUi = () => {
        const done = Math.min(idx, total);
        if (elCount) elCount.textContent = done + "/" + total;
        if (elBar) elBar.style.width = (total ? Math.round((done / total) * 100) : 0) + "%";
        if (elLast) elLast.textContent = fmt(new Date());
    };

    const runNext = () => {
        if (idx >= targets.length) {
            updateUi();
            if (elCurrent) elCurrent.textContent = "Concluído";
            alert("Concluído. Recarregando...");
            location.reload();
            return;
        }
        const id = targets[idx++];
        if (elCurrent) elCurrent.textContent = "#" + id;
        updateUi();
        fetch("/admin/pedidos-wp/wexpress/regerar/" + id + "?source=" + encodeURIComponent(source), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({})
        })
        .then(r => r.json().catch(() => ({})).then(data => ({ ok: r.ok, data })))
        .then(({ok, data}) => {
            if (!ok || !data || !data.success) {
                console.warn("Erro ao regerar etiqueta do pedido", id, data);
            }
            updateUi();
            setTimeout(runNext, 350);
        })
        .catch(err => {
            console.warn("Erro ao regerar etiqueta do pedido", id, err);
            updateUi();
            setTimeout(runNext, 350);
        });
    };

    runNext();
}
</script>
</body>
</html>';

        exit;
    }

    public function detalhesPedidoJanela($request, $janelaId, $pedidoId) {
        $this->requireAccess();

        $source = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        if (!in_array($source, self::SOURCES, true)) $source = 'br';

        $printMode = (string) ($request->getParam('print') ?? ($_GET['print'] ?? ''));
        $printMode = $printMode === '1';

        $downloadMode = (string) ($request->getParam('download') ?? ($_GET['download'] ?? ''));
        $downloadMode = $downloadMode === '1';

        $pdfMode = $downloadMode;

        $janelaId = (int) $janelaId;
        $pedidoId = (int) $pedidoId;
        if ($janelaId <= 0 || $pedidoId <= 0) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }

        $stJ = $this->connection->prepare('SELECT * FROM remessa_wp_janelas WHERE id = ? AND source = ? LIMIT 1');
        $stJ->execute([$janelaId, $source]);
        $janela = $stJ->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$janela) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }

        $tipoJanela = strtolower(trim((string) ($janela['tipo'] ?? 'auto')));
        if ($tipoJanela === '') $tipoJanela = 'auto';
        if ($tipoJanela !== 'manual') {
            $this->syncPedidosParaJanela($janelaId, $source);
        }

        $stL = $this->connection->prepare('SELECT * FROM remessa_wp_janela_pedidos WHERE janela_id = ? AND source = ? AND order_id = ? LIMIT 1');
        $stL->execute([$janelaId, $source, $pedidoId]);
        $link = $stL->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$link) {
            $_SESSION['message'] = 'Pedido não pertence a esta janela.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/remessa-wp/janela/' . $janelaId . '?source=' . urlencode($source));
            exit;
        }

        $wpOrder = null;
        $wpMeta = [];
        $wpItems = [];
        $wpTotals = [];
        $wpSuite = '';
        $wpZona = '';
        $wpZonaDebug = ['package_id' => 0, 'container_id' => 0, 'zona_raw' => '', 'package_meta_key' => ''];
        try {
            $wp = $this->getWpPdo($this->connection, $source);
            $prefix = $wp['prefix'];
            $wpPdo = $wp['pdo'];
            $stO = $wpPdo->prepare("SELECT ID, post_date, post_status, post_title FROM {$prefix}posts WHERE ID = ? AND post_type = 'shop_order' LIMIT 1");
            $stO->execute([$pedidoId]);
            $wpOrder = $stO->fetch(\PDO::FETCH_ASSOC) ?: null;

            $stM = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ?");
            $stM->execute([$pedidoId]);
            $rowsMeta = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rowsMeta as $r) {
                $k = (string) ($r['meta_key'] ?? '');
                if ($k === '') continue;
                $wpMeta[$k] = $r['meta_value'] ?? '';
            }

            // Suite do cliente: usermeta 'suite' do user_id do pedido (_customer_user)
            $customerUserId = (int) ($wpMeta['_customer_user'] ?? 0);
            if ($customerUserId > 0) {
                try {
                    $stSuite = $wpPdo->prepare("SELECT meta_value FROM {$prefix}usermeta WHERE user_id = ? AND meta_key = 'suite' LIMIT 1");
                    $stSuite->execute([$customerUserId]);
                    $wpSuite = trim((string) ($stSuite->fetchColumn() ?: ''));
                } catch (\Exception $e) {
                }
            }

            // Zona (Grupo de Triagem do Container): package(_package_order_id) -> _container_id -> container _triage_group
            try {
                // 1) encontra o package e já tenta ler _container_id em uma query só
                $stPkg = $wpPdo->prepare(
                    "SELECT pm.post_id
                     FROM {$prefix}postmeta pm
                     INNER JOIN {$prefix}posts p ON p.ID = pm.post_id
                     WHERE pm.meta_key IN ('_package_order_id','_package_order','package_order_id','package_order')
                       AND (pm.meta_value = ? OR pm.meta_value LIKE ?)
                     ORDER BY pm.post_id DESC
                     LIMIT 1"
                );
                $stPkg->execute([(string) $pedidoId, '%' . (string) $pedidoId . '%']);
                $packageId = (int) ($stPkg->fetchColumn() ?: 0);

                // fallback: alguns installs salvam o vínculo com outra meta_key
                if ($packageId <= 0) {
                    $stPkg2 = $wpPdo->prepare(
                        "SELECT pm.post_id
                         FROM {$prefix}postmeta pm
                         INNER JOIN {$prefix}postmeta pmc
                           ON pmc.post_id = pm.post_id
                          AND pmc.meta_key LIKE '%container%'
                         WHERE pm.meta_key LIKE '%order%'
                           AND pm.meta_value LIKE ?
                         ORDER BY pm.post_id DESC
                         LIMIT 1"
                    );
                    $stPkg2->execute(['%' . (string) $pedidoId . '%']);
                    $packageId = (int) ($stPkg2->fetchColumn() ?: 0);
                }

                // fallback final: procura qualquer meta_value contendo o order_id em um post que tenha container_id
                if ($packageId <= 0) {
                    $stPkg3 = $wpPdo->prepare(
                        "SELECT pm.post_id, pm.meta_key
                         FROM {$prefix}postmeta pm
                         INNER JOIN {$prefix}postmeta pmc
                           ON pmc.post_id = pm.post_id
                          AND pmc.meta_key LIKE '%container%'
                         WHERE pm.meta_value LIKE ?
                         ORDER BY pm.post_id DESC
                         LIMIT 1"
                    );
                    $stPkg3->execute(['%' . (string) $pedidoId . '%']);
                    $rowPkg3 = $stPkg3->fetch(\PDO::FETCH_ASSOC) ?: null;
                    if ($rowPkg3) {
                        $packageId = (int) ($rowPkg3['post_id'] ?? 0);
                        $wpZonaDebug['package_meta_key'] = (string) ($rowPkg3['meta_key'] ?? '');
                    }
                }
                $wpZonaDebug['package_id'] = $packageId;

                // 2) pega o container_id do package
                $containerId = 0;
                $containerVal = '';
                if ($packageId > 0) {
                    $stCont = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key LIKE '%container%' ORDER BY meta_key ASC LIMIT 1");
                    $stCont->execute([$packageId]);
                    $containerVal = (string) ($stCont->fetchColumn() ?: '');
                    $containerDigits = preg_replace('/\D+/', '', $containerVal);
                    $containerId = (int) ($containerDigits !== '' ? $containerDigits : 0);
                    $wpZonaDebug['container_id'] = $containerId;
                }

                // 3) lê o grupo do container
                if ($containerId > 0) {
                    $stTriage = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key IN ('_triage_group','triage_group','_private_group','private_group') ORDER BY meta_key ASC LIMIT 1");
                    $stTriage->execute([$containerId]);
                    $wpZona = trim((string) ($stTriage->fetchColumn() ?: ''));
                    $wpZonaDebug['zona_raw'] = $wpZona;
                }

                // fallback: tentar achar container pelo tracking code Correios do PACKAGE (container meta _tracking_codes)
                if ($wpZona === '') {
                    $trkCorreios = '';
                    if ($packageId > 0) {
                        $stTrk = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key IN ('_correios_tracking_code','correios_tracking_code','_tracking_code','tracking_code') ORDER BY meta_key ASC LIMIT 1");
                        $stTrk->execute([$packageId]);
                        $trkCorreios = trim((string) ($stTrk->fetchColumn() ?: ''));
                    }

                    if ($trkCorreios !== '') {
                        $stC = $wpPdo->prepare("SELECT post_id FROM {$prefix}postmeta WHERE meta_key = '_tracking_codes' AND meta_value LIKE ? ORDER BY post_id DESC LIMIT 1");
                        $stC->execute(['%' . $trkCorreios . '%']);
                        $cid = (int) ($stC->fetchColumn() ?: 0);
                        if ($cid > 0) {
                            $stTriage2 = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key IN ('_triage_group','triage_group') ORDER BY meta_key ASC LIMIT 1");
                            $stTriage2->execute([$cid]);
                            $wpZona = trim((string) ($stTriage2->fetchColumn() ?: ''));
                            $wpZonaDebug['container_id'] = $cid;
                            $wpZonaDebug['zona_raw'] = $wpZona;
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            $stI = $wpPdo->prepare("SELECT order_item_id, order_item_name, order_item_type FROM {$prefix}woocommerce_order_items WHERE order_id = ? ORDER BY order_item_id ASC");
            $stI->execute([$pedidoId]);
            $orderItems = $stI->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $stIM = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}woocommerce_order_itemmeta WHERE order_item_id = ?");
            foreach ($orderItems as $oi) {
                if (strtolower((string) ($oi['order_item_type'] ?? '')) !== 'line_item') continue;
                $itemId = (int) ($oi['order_item_id'] ?? 0);
                if ($itemId <= 0) continue;

                $stIM->execute([$itemId]);
                $metaItemRows = $stIM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $m = [];
                foreach ($metaItemRows as $mr) {
                    $k = (string) ($mr['meta_key'] ?? '');
                    if ($k === '') continue;
                    $m[$k] = $mr['meta_value'] ?? '';
                }

                $productId = (int) ($m['_product_id'] ?? 0);
                $variationId = (int) ($m['_variation_id'] ?? 0);
                $qty = (int) ($m['_qty'] ?? 0);
                if ($qty <= 0) $qty = 1;

                $lineSubtotal = is_numeric($m['_line_subtotal'] ?? null) ? (float) $m['_line_subtotal'] : null;
                $lineTotal = is_numeric($m['_line_total'] ?? null) ? (float) $m['_line_total'] : 0.0;
                $unit = $qty > 0 ? round($lineTotal / $qty, 2) : 0.0;

                $declName = trim((string) ($m['_product_name'] ?? ''));
                $desc = $declName !== '' ? $declName : trim((string) ($oi['order_item_name'] ?? ''));
                if ($desc === '') $desc = 'item';

                $imageUrl = '';
                $lookupId = $variationId > 0 ? $variationId : $productId;
                if ($lookupId > 0) {
                    try {
                        $stThumb = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_thumbnail_id' LIMIT 1");
                        $stThumb->execute([(int) $lookupId]);
                        $thumbId = (int) ($stThumb->fetchColumn() ?: 0);
                        if ($thumbId > 0) {
                            $stGuid = $wpPdo->prepare("SELECT guid FROM {$prefix}posts WHERE ID = ? LIMIT 1");
                            $stGuid->execute([$thumbId]);
                            $imageUrl = trim((string) ($stGuid->fetchColumn() ?: ''));
                        }
                    } catch (\Exception $e) {
                    }
                }

                $wpItems[] = [
                    'description' => $desc,
                    'declaration_name' => $declName,
                    'qty' => $qty,
                    'unit' => $unit,
                    'total' => $lineTotal,
                    'subtotal' => $lineSubtotal,
                    'image_url' => $imageUrl,
                ];
            }

            $wpTotals = [
                'subtotal' => is_numeric($wpMeta['_order_subtotal'] ?? null) ? (float) $wpMeta['_order_subtotal'] : null,
                'shipping' => is_numeric($wpMeta['_order_shipping'] ?? null) ? (float) $wpMeta['_order_shipping'] : (is_numeric($wpMeta['_order_shipping_tax'] ?? null) ? (float) $wpMeta['_order_shipping_tax'] : null),
                'discount' => is_numeric($wpMeta['_cart_discount'] ?? null) ? (float) $wpMeta['_cart_discount'] : (is_numeric($wpMeta['_discount_total'] ?? null) ? (float) $wpMeta['_discount_total'] : null),
                'total' => is_numeric($wpMeta['_order_total'] ?? null) ? (float) $wpMeta['_order_total'] : null,
                'currency' => trim((string) ($wpMeta['_order_currency'] ?? '')),
            ];

            if ($wpTotals['subtotal'] === null) {
                $sumSubtotal = 0.0;
                $sumTotal = 0.0;
                foreach ($wpItems as $it) {
                    $sumTotal += (float) ($it['total'] ?? 0);
                    if (array_key_exists('subtotal', $it) && $it['subtotal'] !== null) {
                        $sumSubtotal += (float) $it['subtotal'];
                    }
                }
                if ($sumSubtotal > 0) {
                    $wpTotals['subtotal'] = $sumSubtotal;
                } elseif ($sumTotal > 0) {
                    $wpTotals['subtotal'] = $sumTotal;
                }
            }
        } catch (\Exception $e) {
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $nomeClientePdf = '';
        try {
            $nomeClientePdf = trim((string) (($wpMeta['_shipping_first_name'] ?? '') . ' ' . ($wpMeta['_shipping_last_name'] ?? '')));
            if ($nomeClientePdf === '') {
                $nomeClientePdf = trim((string) (($wpMeta['_billing_first_name'] ?? '') . ' ' . ($wpMeta['_billing_last_name'] ?? '')));
            }
        } catch (\Exception $e) {
            $nomeClientePdf = '';
        }
        $invoiceTitle = 'Invoice - Pedido #' . (int) $pedidoId;
        if ($nomeClientePdf !== '') {
            $invoiceTitle .= ' - ' . $nomeClientePdf;
        }

        if ($pdfMode) {
            $safeFile = preg_replace('/[^A-Za-z0-9 _\-#]/', '', $invoiceTitle);
            $safeFile = trim((string) $safeFile);
            if ($safeFile === '') {
                $safeFile = 'Invoice - Pedido #' . (int) $pedidoId;
            }

            $currency = (string) ($wpTotals['currency'] ?? ($wpMeta['_order_currency'] ?? 'BRL'));
            $fmtMoney = function (?float $v) use ($currency): string {
                if ($v === null) return '-';
                $val = (float) $v;
                if (strtoupper($currency) === 'BRL') {
                    return 'R$ ' . number_format($val, 2, ',', '.');
                }
                return strtoupper($currency) . ' ' . number_format($val, 2, '.', ',');
            };

            $safeText = function ($v): string {
                return htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
            };

            $paidDate = trim((string) ($wpMeta['_paid_date'] ?? ($wpMeta['_date_paid'] ?? '')));
            if ($paidDate !== '' && ctype_digit($paidDate)) {
                $paidDate = date('d/m/Y H:i', (int) $paidDate);
            } elseif ($paidDate !== '') {
                $ts = strtotime($paidDate);
                if ($ts) $paidDate = date('d/m/Y H:i', $ts);
            }

            $orderDate = trim((string) ($wpOrder['post_date'] ?? ''));
            $emitDate = $orderDate !== '' ? date('d/m/Y H:i', strtotime($orderDate)) : date('d/m/Y H:i');

            $statusWp = trim((string) ($wpOrder['post_status'] ?? ''));
            $medicamentoFlag = ((int) ($link['medicamento'] ?? 0)) === 1;

            $docsByTipo = [];
            if ($this->tableExists('remessa_wp_pedido_documentos')) {
                try {
                    $stD = $this->connection->prepare('SELECT * FROM remessa_wp_pedido_documentos WHERE janela_id = ? AND source = ? AND order_id = ?');
                    $stD->execute([$janelaId, $source, $pedidoId]);
                    $docs = $stD->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($docs as $d) {
                        $t = strtolower(trim((string) ($d['tipo'] ?? '')));
                        if ($t === '') continue;
                        $docsByTipo[$t] = $d;
                    }
                } catch (\Throwable $e) {
                }
            }

            $cpf = trim((string) ($wpMeta['_billing_cpf'] ?? ($wpMeta['_billing_cnpj'] ?? '')));
            $cnpj = trim((string) ($wpMeta['_billing_cnpj'] ?? ''));
            $email = trim((string) ($wpMeta['_billing_email'] ?? ''));
            $cel = trim((string) ($wpMeta['_billing_phone'] ?? ''));
            $ipCliente = trim((string) ($wpMeta['_customer_ip_address'] ?? ($wpMeta['_customer_user_ip'] ?? '')));

            $aceitaSubstRaw = strtolower(trim((string) ($wpMeta['_accept_product_replacement'] ?? '')));
            $aceitaSubst = $aceitaSubstRaw;
            if ($aceitaSubstRaw === 'yes') $aceitaSubst = 'Sim';
            if ($aceitaSubstRaw === 'no') $aceitaSubst = 'Não';

            $trkWx = trim((string) ($link['courier_tracking_number'] ?? ($link['wexpress_tracking_number'] ?? '')));
            $zona = $this->formatTriageGroup($wpZona);

            $paymentMethodTitle = trim((string) ($wpMeta['_payment_method_title'] ?? ($wpMeta['_payment_method'] ?? '')));

            $findWxFrete = function ($data): ?float {
                if ($data === null) return null;
                if (is_string($data)) {
                    $data = json_decode($data, true);
                }
                if (!is_array($data)) return null;

                // atalhos comuns (estrutura conhecida do wexpress_last_response_json: {create:..., get:{freight_value:...}})
                try {
                    $tryPaths = [
                        ['get', 'freight_value'],
                        ['get', 'freight'],
                        ['freight_value_returned'],
                        ['freight_value_sent'],
                        ['freight_value'],
                    ];
                    foreach ($tryPaths as $path) {
                        $node = $data;
                        foreach ($path as $k) {
                            if (!is_array($node) || !array_key_exists($k, $node)) {
                                $node = null;
                                break;
                            }
                            $node = $node[$k];
                        }
                        if ($node !== null) {
                            $sv = str_replace(',', '.', (string) $node);
                            if (is_numeric($sv) && (float) $sv > 0) {
                                return (float) $sv;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                }

                $candidates = [];
                $walk = function ($node, string $path) use (&$walk, &$candidates) {
                    if (is_array($node)) {
                        foreach ($node as $k => $v) {
                            $kk = strtolower((string) $k);
                            $p = $path === '' ? $kk : ($path . '.' . $kk);
                            if (is_string($v)) {
                                $vv = trim($v);
                                if ($vv !== '' && (($vv[0] ?? '') === '{' || ($vv[0] ?? '') === '[')) {
                                    $decoded = json_decode($vv, true);
                                    if (is_array($decoded)) {
                                        $walk($decoded, $p);
                                        continue;
                                    }
                                }
                            }
                            if (is_scalar($v)) {
                                $sv = (string) $v;
                                $sv2 = str_replace(',', '.', $sv);
                                if (is_numeric($sv2)) {
                                    if (strpos($kk, 'shipping') !== false || strpos($kk, 'frete') !== false || strpos($kk, 'freight') !== false) {
                                        if (strpos($kk, 'tax') === false) {
                                            $candidates[] = (float) $sv2;
                                        }
                                    }
                                    if ((strpos($kk, 'price') !== false || strpos($kk, 'cost') !== false || strpos($kk, 'amount') !== false)
                                        && (strpos($p, 'shipping') !== false || strpos($p, 'frete') !== false || strpos($p, 'freight') !== false)
                                    ) {
                                        $candidates[] = (float) $sv2;
                                    }
                                    if ($kk === 'value' && (strpos($p, 'shipping') !== false || strpos($p, 'frete') !== false || strpos($p, 'freight') !== false)) {
                                        $candidates[] = (float) $sv2;
                                    }
                                }
                            }
                            $walk($v, $p);
                        }
                    }
                };
                $walk($data, '');
                if (!$candidates) return null;
                $best = null;
                foreach ($candidates as $v) {
                    if ($v <= 0) continue;
                    if ($best === null || $v > $best) {
                        $best = $v;
                    }
                }
                return $best;
            };

            $wxFrete = $findWxFrete($link['wexpress_last_response_json'] ?? null);
            if ($wxFrete === null || $wxFrete <= 0) {
                $wxFrete = $findWxFrete($link['wexpress_last_request_json'] ?? null);
            }

            $freteFinal = $wxFrete !== null ? $wxFrete : (isset($wpTotals['shipping']) ? (float) $wpTotals['shipping'] : 0.0);
            if ($freteFinal <= 0 && isset($wpTotals['shipping']) && (float) $wpTotals['shipping'] > 0) {
                $freteFinal = (float) $wpTotals['shipping'];
            }

            $subTotal = isset($wpTotals['subtotal']) && $wpTotals['subtotal'] !== null ? (float) $wpTotals['subtotal'] : null;
            $discount = isset($wpTotals['discount']) && $wpTotals['discount'] !== null ? (float) $wpTotals['discount'] : null;
            $total = isset($wpTotals['total']) && $wpTotals['total'] !== null ? (float) $wpTotals['total'] : null;

            $billingName = trim((string) (($wpMeta['_billing_first_name'] ?? '') . ' ' . ($wpMeta['_billing_last_name'] ?? '')));
            $shippingName = trim((string) (($wpMeta['_shipping_first_name'] ?? '') . ' ' . ($wpMeta['_shipping_last_name'] ?? '')));
            if ($shippingName === '') $shippingName = $billingName;

            $b = [
                'name' => $billingName,
                'company' => (string) ($wpMeta['_billing_company'] ?? ''),
                'address1' => (string) ($wpMeta['_billing_address_1'] ?? ''),
                'address2' => (string) ($wpMeta['_billing_address_2'] ?? ''),
                'number' => (string) ($wpMeta['_billing_number'] ?? ''),
                'neighborhood' => (string) ($wpMeta['_billing_neighborhood'] ?? ''),
                'city' => (string) ($wpMeta['_billing_city'] ?? ''),
                'state' => (string) ($wpMeta['_billing_state'] ?? ''),
                'postcode' => (string) ($wpMeta['_billing_postcode'] ?? ''),
                'country' => (string) ($wpMeta['_billing_country'] ?? ''),
            ];
            $s = [
                'name' => $shippingName,
                'company' => (string) ($wpMeta['_shipping_company'] ?? ''),
                'address1' => (string) ($wpMeta['_shipping_address_1'] ?? ''),
                'address2' => (string) ($wpMeta['_shipping_address_2'] ?? ''),
                'number' => (string) ($wpMeta['_shipping_number'] ?? ''),
                'suite' => (string) ($wpSuite ?? ''),
                'neighborhood' => (string) ($wpMeta['_shipping_neighborhood'] ?? ''),
                'city' => (string) ($wpMeta['_shipping_city'] ?? ''),
                'state' => (string) ($wpMeta['_shipping_state'] ?? ''),
                'postcode' => (string) ($wpMeta['_shipping_postcode'] ?? ''),
                'country' => (string) ($wpMeta['_shipping_country'] ?? ''),
            ];

            $useful = [];
            $metaGet = function (string $key) use ($wpMeta) {
                return array_key_exists($key, $wpMeta) ? $wpMeta[$key] : null;
            };
            $useful['Chave do Pedido'] = $metaGet('_order_key');
            $useful['Usuário do Cliente'] = $metaGet('_customer_user');
            $useful['Método de Pagamento'] = $metaGet('_payment_method');
            $useful['Título do Método de Pagamento'] = $metaGet('_payment_method_title');
            $useful['Endereço IP do Cliente'] = $metaGet('_customer_ip_address');
            $useful['Agente do Usuário do Cliente'] = $metaGet('_customer_user_agent');
            $useful['Criado Via'] = $metaGet('_created_via');
            $useful['Hash do Carrinho'] = $metaGet('_cart_hash');
            $useful['Permissões de Download'] = $metaGet('_download_permissions_granted');
            $useful['Vendas Registradas'] = $metaGet('_recorded_sales');
            $useful['Contagens de Uso de Cupons Registradas'] = $metaGet('_recorded_coupon_usage_counts');
            $useful['Email de Novo Pedido Enviado'] = $metaGet('_new_order_email_sent');
            $useful['Estoque do Pedido Reduzido'] = $metaGet('_order_stock_reduced');
            $useful['Moeda do Pedido'] = $metaGet('_order_currency');
            $useful['Desconto do Carrinho'] = $metaGet('_cart_discount');
            $useful['Imposto do Desconto do Carrinho'] = $metaGet('_cart_discount_tax');
            $useful['Frete do Pedido'] = $metaGet('_order_shipping');
            $useful['Imposto do Frete do Pedido'] = $metaGet('_order_shipping_tax');
            $useful['Imposto do Pedido'] = $metaGet('_order_tax');
            $useful['Total do Pedido'] = $metaGet('_order_total');
            $useful['Versão do Pedido'] = $metaGet('_order_version');
            $useful['Preços Incluem Imposto'] = $metaGet('_prices_include_tax');
            $useful['Primeiro Nome de Cobrança'] = $metaGet('_billing_first_name');
            $useful['Sobrenome de Cobrança'] = $metaGet('_billing_last_name');
            $useful['Endereço de Cobrança 1'] = $metaGet('_billing_address_1');
            $useful['Billing Address 2'] = $metaGet('_billing_address_2');
            $useful['Cidade de Cobrança'] = $metaGet('_billing_city');
            $useful['Estado de Cobrança'] = $metaGet('_billing_state');
            $useful['CEP de Cobrança'] = $metaGet('_billing_postcode');
            $useful['País de Cobrança'] = $metaGet('_billing_country');
            $useful['Email de Cobrança'] = $metaGet('_billing_email');
            $useful['Telefone de Cobrança'] = $metaGet('_billing_phone');
            $useful['CPF de Cobrança'] = $metaGet('_billing_cpf');
            $useful['Data de Nascimento de Cobrança'] = $metaGet('_billing_birthdate');
            $useful['Número de Cobrança'] = $metaGet('_billing_number');
            $useful['Bairro de Cobrança'] = $metaGet('_billing_neighborhood');
            $useful['Endereço de Entrega 1'] = $metaGet('_shipping_address_1');
            $useful['Shipping Address 2'] = $metaGet('_shipping_address_2');
            $useful['Cidade de Entrega'] = $metaGet('_shipping_city');
            $useful['Estado de Entrega'] = $metaGet('_shipping_state');
            $useful['CEP de Entrega'] = $metaGet('_shipping_postcode');
            $useful['Bairro de Entrega'] = $metaGet('_shipping_neighborhood');
            $useful['Número de Entrega'] = $metaGet('_shipping_number');

            $cats = [
                'Metas adicionais (Pix/Mercado Pago)' => [],
                'Metas adicionais (Atribuição / UTM / Sessão)' => [],
                'Metas adicionais (WCCS / Câmbio)' => [],
                'Metas adicionais (TRP / Idioma)' => [],
                'Metas adicionais (TPUL / Visitor)' => [],
                'Metas adicionais (Checkout / Gateway)' => [],
            ];
            foreach ($wpMeta as $k => $v) {
                $ks = strtolower((string) $k);
                if ($ks === '') continue;
                if (strpos($ks, 'mp ') === 0 || strpos($ks, 'mp_') === 0 || strpos($ks, 'mercado') !== false || strpos($ks, 'pix') !== false) {
                    $cats['Metas adicionais (Pix/Mercado Pago)'][$k] = $v;
                    continue;
                }
                if (strpos($ks, 'utm_') !== false || strpos($ks, 'attribution') !== false || strpos($ks, 'referrer') !== false || strpos($ks, 'landing') !== false || strpos($ks, 'session') !== false) {
                    $cats['Metas adicionais (Atribuição / UTM / Sessão)'][$k] = $v;
                    continue;
                }
                if (strpos($ks, 'wccs') !== false || strpos($ks, 'currency_ratio') !== false || strpos($ks, 'base_currency') !== false) {
                    $cats['Metas adicionais (WCCS / Câmbio)'][$k] = $v;
                    continue;
                }
                if (strpos($ks, 'trp') !== false || strpos($ks, 'language') !== false || strpos($ks, 'idioma') !== false) {
                    $cats['Metas adicionais (TRP / Idioma)'][$k] = $v;
                    continue;
                }
                if (strpos($ks, 'tpul') !== false || strpos($ks, 'visitor') !== false) {
                    $cats['Metas adicionais (TPUL / Visitor)'][$k] = $v;
                    continue;
                }
                if (strpos($ks, 'checkout') !== false || strpos($ks, 'gateway') !== false || strpos($ks, 'used gateway') !== false || strpos($ks, 'blocks payment') !== false) {
                    $cats['Metas adicionais (Checkout / Gateway)'][$k] = $v;
                    continue;
                }
            }

            $formatUseful = function ($val) use ($safeText): string {
                if ($val === null) return '-';
                if (is_bool($val)) return $val ? 'true' : 'false';
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $s = trim((string) $val);
                if ($s === '') return '-';
                $safe = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
                $safe = str_replace(["\r\n", "\r", "\n"], "\n", $safe);
                $safe = nl2br($safe, false);
                return '<div style="white-space:pre-wrap;word-break:break-all;overflow-wrap:anywhere;">' . $safe . '</div>';
            };

            $embedImage = function (string $url): ?string {
                $url = trim($url);
                if ($url === '') return null;
                if (!preg_match('#^https?://#i', $url)) return null;

                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 12,
                        'follow_location' => 1,
                        'user_agent' => 'brz-new/1.0',
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);

                $raw = @file_get_contents($url, false, $ctx);
                if ($raw === false || $raw === '') return null;

                $mime = null;
                if (function_exists('finfo_open')) {
                    $fi = @finfo_open(FILEINFO_MIME_TYPE);
                    if ($fi) {
                        $mime = @finfo_buffer($fi, $raw);
                        @finfo_close($fi);
                    }
                }
                $mime = is_string($mime) ? trim($mime) : '';

                $allow = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
                if ($mime !== '' && array_key_exists($mime, $allow)) {
                    return 'data:' . $mime . ';base64,' . base64_encode($raw);
                }

                // Tentar converter WEBP -> PNG quando possível
                if ($mime === 'image/webp' || preg_match('/\.webp(\?|$)/i', $url)) {
                    try {
                        if (class_exists('Imagick')) {
                            $im = new \Imagick();
                            $im->readImageBlob($raw);
                            $im->setImageFormat('png');
                            $png = $im->getImagesBlob();
                            $im->clear();
                            $im->destroy();
                            if (is_string($png) && $png !== '') {
                                return 'data:image/png;base64,' . base64_encode($png);
                            }
                        }
                    } catch (\Throwable $e) {
                    }

                    try {
                        if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
                            $img = @imagecreatefromstring($raw);
                            if ($img !== false) {
                                ob_start();
                                imagepng($img);
                                $png = (string) ob_get_clean();
                                imagedestroy($img);
                                if ($png !== '') {
                                    return 'data:image/png;base64,' . base64_encode($png);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                }

                return null;
            };

            $rowsItems = '';
            $idx = 1;
            foreach ($wpItems as $it) {
                if (!is_array($it)) continue;
                $decl = trim((string) ($it['declaration_name'] ?? ''));
                if ($decl === '') $decl = trim((string) ($it['description'] ?? ''));
                $img = trim((string) ($it['image_url'] ?? ''));
                $imgData = $img !== '' ? ($embedImage($img) ?? '') : '';
                $qtd = (int) ($it['qty'] ?? 0);
                if ($qtd <= 0) $qtd = 1;
                $unit = isset($it['unit']) ? (float) $it['unit'] : 0.0;
                $tot = isset($it['total']) ? (float) $it['total'] : 0.0;
                $rowsItems .= '<tr>'
                    . '<td style="text-align:center;">' . $idx . '</td>'
                    . '<td style="text-align:center;">' . ($imgData !== '' ? ('<img src="' . $imgData . '" style="width:54px;height:54px;object-fit:contain;" />') : '-') . '</td>'
                    . '<td>' . $safeText($decl) . '</td>'
                    . '<td style="text-align:center;">' . $qtd . '</td>'
                    . '<td style="text-align:right;">' . $safeText($fmtMoney($unit)) . '</td>'
                    . '<td style="text-align:right;">' . $safeText($fmtMoney($tot)) . '</td>'
                . '</tr>';
                $idx++;
            }
            if ($rowsItems === '') {
                $rowsItems = '<tr><td colspan="6" style="text-align:center;padding:12px;">Nenhum item</td></tr>';
            }

            $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
                . '<style>'
                . '@page { margin: 22px 26px; }'
                . 'body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #111827; }'
                . '.title { font-size: 20px; font-weight: 900; }'
                . '.sub { font-size: 11px; color: #374151; margin-top: 3px; }'
                . '.section { border: 1px solid #111827; padding: 10px; margin-top: 10px; }'
                . '.section-h { font-weight: 900; margin-bottom: 7px; font-size: 14px; }'
                . '.grid { width: 100%; border-collapse: collapse; }'
                . '.grid td { vertical-align: top; }'
                . '.kv { width: 100%; border-collapse: collapse; }'
                . '.kv td { padding: 2px 0; vertical-align: top; word-break: break-all; overflow-wrap:anywhere; }'
                . '.kv td:first-child { width: 150px; color: #374151; }'
                . '.items { width: 100%; border-collapse: collapse; margin-top: 8px; }'
                . '.items th, .items td { border: 1px solid #111827; padding: 6px 7px; }'
                . '.items th { background: #f3f4f6; text-align: left; }'
                . '.totals { width: 100%; border-collapse: collapse; margin-top: 8px; }'
                . '.totals td { padding: 3px 0; }'
                . '.totals .label { color:#374151; }'
                . '.totals .value { text-align:right; }'
                . '.totals .grand { font-weight: 900; font-size: 13px; border-top: 2px solid #111827; padding-top: 6px; }'
                . '.muted { color:#6b7280; }'
                . '</style></head><body>'
                . '<div class="title">' . $safeText($invoiceTitle) . '</div>'
                . '<div class="sub">Data de emissão: <strong>' . $safeText($emitDate) . '</strong></div>'

                . '<table class="grid" style="margin-top:10px;"><tr>'
                . '<td style="width:50%;padding-right:8px;">'
                . '<div class="section"><div class="section-h">Dados do Cliente</div>'
                . '<table class="kv">'
                . '<tr><td>Nome:</td><td><strong>' . $safeText($nomeClientePdf) . '</strong></td></tr>'
                . '<tr><td>Suíte:</td><td>' . $safeText($wpSuite) . '</td></tr>'
                . '<tr><td>E-mail:</td><td>' . $safeText($email) . '</td></tr>'
                . '<tr><td>CPF:</td><td>' . $safeText($cpf) . '</td></tr>'
                . '<tr><td>CNPJ:</td><td>' . $safeText($cnpj) . '</td></tr>'
                . '<tr><td>Celular:</td><td>' . $safeText($cel) . '</td></tr>'
                . '<tr><td>IP:</td><td>' . $safeText($ipCliente) . '</td></tr>'
                . '<tr><td>Zona:</td><td>' . $safeText($zona) . '</td></tr>'
                . '<tr><td>Status WP:</td><td>' . $safeText($statusWp !== '' ? $statusWp : '-') . '</td></tr>'
                . '<tr><td>Aceita substituição:</td><td>' . $safeText($aceitaSubst) . '</td></tr>'
                . '<tr><td>Cód. rastreio:</td><td>' . $safeText($trkWx) . '</td></tr>'
                . '</table></div>'
                . '</td>'
                . '<td style="width:50%;">'
                . '<div class="section"><div class="section-h">Endereço de Cobrança</div>'
                . '<table class="kv">'
                . '<tr><td>Nome:</td><td><strong>' . $safeText($b['name']) . '</strong></td></tr>'
                . '<tr><td>Empresa:</td><td>' . $safeText($b['company']) . '</td></tr>'
                . '<tr><td>Rua:</td><td>' . $safeText($b['address1']) . '</td></tr>'
                . '<tr><td>Complemento:</td><td>' . $safeText($b['address2']) . '</td></tr>'
                . '<tr><td>Número:</td><td>' . $safeText($b['number']) . '</td></tr>'
                . '<tr><td>Bairro:</td><td>' . $safeText($b['neighborhood']) . '</td></tr>'
                . '<tr><td>Cidade:</td><td>' . $safeText($b['city']) . '</td></tr>'
                . '<tr><td>Estado:</td><td>' . $safeText($b['state']) . '</td></tr>'
                . '<tr><td>CEP:</td><td>' . $safeText($b['postcode']) . '</td></tr>'
                . '<tr><td>País:</td><td>' . $safeText($b['country']) . '</td></tr>'
                . '</table></div>'
                . '</td>'
                . '</tr></table>'

                . '<div class="section"><div class="section-h">Endereço de Entrega</div>'
                . '<table class="kv">'
                . '<tr><td>Nome:</td><td><strong>' . $safeText($s['name']) . '</strong></td></tr>'
                . '<tr><td>Empresa:</td><td>' . $safeText($s['company']) . '</td></tr>'
                . '<tr><td>Rua:</td><td>' . $safeText($s['address1']) . '</td></tr>'
                . '<tr><td>Complemento:</td><td>' . $safeText($s['address2']) . '</td></tr>'
                . '<tr><td>Número:</td><td>' . $safeText($s['number']) . '</td></tr>'
                . '<tr><td>Suíte:</td><td>' . $safeText($s['suite']) . '</td></tr>'
                . '<tr><td>Bairro:</td><td>' . $safeText($s['neighborhood']) . '</td></tr>'
                . '<tr><td>Cidade:</td><td>' . $safeText($s['city']) . '</td></tr>'
                . '<tr><td>Estado:</td><td>' . $safeText($s['state']) . '</td></tr>'
                . '<tr><td>CEP:</td><td>' . $safeText($s['postcode']) . '</td></tr>'
                . '<tr><td>País:</td><td>' . $safeText($s['country']) . '</td></tr>'
                . '</table></div>'

                . '<div class="section"><div class="section-h">Itens do Pedido</div>'
                . '<table class="items">'
                . '<thead><tr>'
                . '<th style="width:32px;text-align:center;">#</th>'
                . '<th style="width:66px;text-align:center;">Imagem</th>'
                . '<th>Declaração</th>'
                . '<th style="width:52px;text-align:center;">Qtd</th>'
                . '<th style="width:110px;text-align:right;">Preço unit.</th>'
                . '<th style="width:110px;text-align:right;">Total</th>'
                . '</tr></thead><tbody>' . $rowsItems . '</tbody></table>'
                . '</div>'

                . '<div class="section"><div class="section-h">Documentos / Uploads</div>'
                . '<table class="kv">'
                . '<tr><td>Medicamento?</td><td><strong>' . ($medicamentoFlag ? 'Sim' : 'Não') . '</strong></td></tr>'
                . (($source === 'red')
                    ? ('<tr><td>Redirecionamento?</td><td><strong>Sim</strong></td></tr>')
                    : ('<tr><td>Redirecionamento?</td><td><strong>Não</strong></td></tr>'))
                . (($medicamentoFlag)
                    ? ('<tr><td>Doc. medicamento:</td><td>'
                        . (!empty($docsByTipo['medicamento']['file_path'])
                            ? ('Enviado - ' . $safeText((string) ($docsByTipo['medicamento']['original_name'] ?? '')) . '<br><span class="muted">' . $safeText((string) ($docsByTipo['medicamento']['file_path'] ?? '')) . '</span>')
                            : '<span class="muted">Pendente</span>')
                        . '</td></tr>')
                    : '')
                . (($source === 'red')
                    ? ('<tr><td>Comp. pagamento:</td><td>'
                        . (!empty($docsByTipo['pagamento']['file_path'])
                            ? ('Enviado - ' . $safeText((string) ($docsByTipo['pagamento']['original_name'] ?? '')) . '<br><span class="muted">' . $safeText((string) ($docsByTipo['pagamento']['file_path'] ?? '')) . '</span>')
                            : '<span class="muted">Pendente</span>')
                        . '</td></tr>')
                    : '')
                . '</table></div>'

                . '<table class="grid" style="margin-top:10px;"><tr>'
                . '<td style="width:50%;padding-right:8px;">'
                . '<div class="section"><div class="section-h">Pagamento</div>'
                . '<table class="kv">'
                . '<tr><td>Valor pago:</td><td><strong>' . $safeText($fmtMoney($total)) . '</strong></td></tr>'
                . '<tr><td>Data de crédito:</td><td>' . $safeText($paidDate) . '</td></tr>'
                . '<tr><td>Método:</td><td>' . $safeText($paymentMethodTitle) . '</td></tr>'
                . '</table></div>'
                . '</td>'
                . '<td style="width:50%;">'
                . '<div class="section"><div class="section-h">Totais</div>'
                . '<table class="totals">'
                . '<tr><td class="label">Subtotal:</td><td class="value">' . $safeText($fmtMoney($subTotal)) . '</td></tr>'
                . '<tr><td class="label">Frete (WExpress):</td><td class="value">' . $safeText($fmtMoney($freteFinal)) . '</td></tr>'
                . '<tr><td class="label">Descontos/Subsídios:</td><td class="value">' . $safeText($fmtMoney($discount)) . '</td></tr>'
                . '<tr><td class="label grand">Total do pedido:</td><td class="value grand">' . $safeText($fmtMoney($total)) . '</td></tr>'
                . '</table></div>'
                . '</td>'
                . '</tr></table>'

                . '<div class="section"><div class="section-h">Informações Úteis</div>'
                . '<table class="kv">';

            foreach ($useful as $k => $v) {
                $html .= '<tr><td>' . $safeText($k) . ':</td><td>' . $formatUseful($v) . '</td></tr>';
            }
            foreach ($cats as $title => $items) {
                if (!$items) continue;
                $html .= '<tr><td colspan="2" class="muted" style="padding-top:8px;"><strong>' . $safeText($title) . '</strong></td></tr>';
                foreach ($items as $k => $v) {
                    $html .= '<tr><td>' . $safeText($k) . ':</td><td>' . $formatUseful($v) . '</td></tr>';
                }
            }

            $html .= '</table></div>'
                . '</body></html>';

            if (class_exists('Dompdf\\Dompdf')) {
                $dompdf = new \Dompdf\Dompdf([
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                ]);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $safeFile . '.pdf"');
                header('X-Content-Type-Options: nosniff');
                echo $dompdf->output();
                exit;
            }

            header('Content-Type: text/html; charset=utf-8');
            echo '<div style="padding:12px;border:1px solid #f59e0b;background:#fffbeb;color:#92400e;margin:12px;font-family:Arial;">'
                . '<strong>PDF indisponível:</strong> a biblioteca <code>dompdf/dompdf</code> não está instalada no servidor ainda. Rode <code>composer install</code>.'
                . '</div>';
            echo $html;
            exit;
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($invoiceTitle, ENT_QUOTES, 'UTF-8') . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '<style>
  .no-print { display: ' . ($pdfMode ? 'none' : 'initial') . '; }
@media print {
  .no-print { display: none !important; }
  body { background: #fff !important; }
  .card { break-inside: avoid; }
  details { display: block !important; }
  details > summary { display: none !important; }
}
</style>';
        echo '</head>
<body>
<div class="container-fluid"><div class="row">';

        if (!$printMode) {
            renderAdminSidebar('remessa-wp');
        }

        $recebido = ((int) ($link['recebido_confirmado'] ?? 0)) === 1;
        $recebidoEm = (string) ($link['recebido_confirmado_em'] ?? '');

        $etq = ((int) ($link['etiqueta_gerada'] ?? 0)) === 1;
        $medicamento = ((int) ($link['medicamento'] ?? 0)) === 1;

        $docsByTipo = [];
        if ($this->tableExists('remessa_wp_pedido_documentos')) {
            $stD = $this->connection->prepare('SELECT * FROM remessa_wp_pedido_documentos WHERE janela_id = ? AND source = ? AND order_id = ?');
            $stD->execute([$janelaId, $source, $pedidoId]);
            $docs = $stD->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($docs as $d) {
                $t = strtolower(trim((string) ($d['tipo'] ?? '')));
                if ($t === '') continue;
                $docsByTipo[$t] = $d;
            }
        }

        $mainColClass = ($printMode || $pdfMode) ? 'col-12 px-4' : 'col-md-9 ms-sm-auto col-lg-10 px-md-4';
        $topTitle = ($printMode || $pdfMode) ? $invoiceTitle : ('Pedido #' . (int) $pedidoId);
        echo '<main class="' . $mainColClass . '">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h4 mb-0">' . htmlspecialchars($topTitle, ENT_QUOTES, 'UTF-8') . '</h1>
                    <div class="text-muted small">Janela #' . (int) $janelaId . ' (' . htmlspecialchars(date('d/m/Y', strtotime((string) $janela['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $janela['data_fim']))) . ')</div>
                </div>
                <div class="d-flex gap-2 no-print">
                    <a class="btn btn-outline-secondary" href="/admin/remessa-wp/janela/' . (int) $janelaId . '?source=' . urlencode($source) . '">Voltar</a>
                    <a class="btn btn-outline-secondary" href="/admin/pedidos-wp/detalhes/' . (int) $pedidoId . '?source=' . urlencode($source) . '">Abrir pedido</a>
                    <a class="btn btn-primary" href="/admin/remessa-wp/janela/' . (int) $janelaId . '/pedido/' . (int) $pedidoId . '?source=' . urlencode($source) . '&download=1">Baixar Invoice (PDF)</a>
                    ' . ($recebido ? '' : ('<form method="POST" action="/admin/remessa-wp/janela/' . (int) $janelaId . '/pedido/' . (int) $pedidoId . '/confirmar-recebimento?source=' . urlencode($source) . '" style="display:inline;" onsubmit="return confirm(\"Confirmar recebimento deste pedido?\")">'
                        . '<button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Confirmar recebimento</button>'
                    . '</form>')) . '
                </div>
            </div>';

        echo '<div class="row g-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><strong>Informações</strong></div>
                        <div class="card-body">';

        $statusWp = (string) ($wpOrder['post_status'] ?? '');
        $dateWp = (string) ($wpOrder['post_date'] ?? '');
        echo '<div><strong>Status WP:</strong> ' . htmlspecialchars($statusWp !== '' ? $statusWp : '-') . '</div>';
        echo '<div><strong>Data:</strong> ' . ($dateWp !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($dateWp))) : '-') . '</div>';

        $trkWx = trim((string) ($link['courier_tracking_number'] ?? ($link['wexpress_tracking_number'] ?? '')));
        if ($trkWx !== '') {
            echo '<div class="mt-2"><strong>Tracking W-Express:</strong> ' . htmlspecialchars($trkWx) . '</div>';
        }

        echo '<div class="mt-2"><strong>Etiqueta:</strong> ' . ($etq ? '<span class="badge bg-success">Gerada</span>' : '<span class="badge bg-warning text-dark">Pendente</span>') . '</div>';

        $labelUrl = trim((string) ($link['wexpress_label_url'] ?? ''));
        if ($labelUrl !== '') {
            echo '<div class="mt-2"><a class="btn btn-sm btn-outline-primary" target="_blank" href="' . htmlspecialchars($labelUrl) . '">Abrir etiqueta</a></div>';
        }

        if ($this->tableExists('remessa_wp_pedido_documentos')) {
            echo '<hr>';
            echo '<div class="d-flex align-items-center justify-content-between">'
                . '<div><strong>Medicamento?</strong></div>'
                . '</div>';

            if (!$etq) {
                echo '<div class="text-muted small mt-1">A etiqueta precisa estar gerada para habilitar os uploads obrigatórios.</div>';
            }

            echo '<form method="POST" action="/admin/remessa-wp/janela/' . (int) $janelaId . '/pedido/' . (int) $pedidoId . '/medicamento?source=' . urlencode($source) . '" class="mt-2">'
                . '<input type="hidden" name="medicamento" value="' . ($medicamento ? '0' : '1') . '">' 
                . '<button type="submit" class="btn btn-sm ' . ($medicamento ? 'btn-warning' : 'btn-outline-warning') . '">' 
                . ($medicamento ? 'Marcar como não medicamento' : 'Marcar como medicamento')
                . '</button>'
                . '</form>';
        }

        echo '           </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><strong>Documentos</strong></div>
                        <div class="card-body">';

        if (!$this->tableExists('remessa_wp_pedido_documentos')) {
            echo '<div class="alert alert-warning mb-0">Tabela de documentos não encontrada. Rode a migration: database/migrations/048_add_docs_to_remessa_wp.sql</div>';
        } else {
            $requirements = [];
            if ($source === 'red') {
                $requirements['pagamento'] = ['label' => 'Comprovante de pagamento', 'required' => true];
            } else {
                echo '<div class="alert alert-info">Para pedidos ' . htmlspecialchars(strtoupper($source)) . ', o comprovante de pagamento é exibido nas informações do pedido (WordPress) e não exige upload.</div>';
            }
            if ($medicamento) {
                $requirements['medicamento'] = ['label' => 'Documento de medicamento', 'required' => true];
            }

            if (!$etq) {
                echo '<div class="alert alert-secondary">Uploads bloqueados até gerar etiqueta.</div>';
            }

            if (!$requirements) {
                echo '<div class="text-muted">Nenhum documento obrigatório para este pedido.</div>';
            }

            foreach ($requirements as $tipo => $cfg) {
                $doc = $docsByTipo[$tipo] ?? null;
                $has = is_array($doc) && !empty($doc['file_path']);

                echo '<div class="mb-3">'
                    . '<div class="d-flex justify-content-between align-items-center">'
                        . '<div><strong>' . htmlspecialchars($cfg['label']) . '</strong> ' 
                            . ($cfg['required'] ? '<span class="text-danger">*</span>' : '')
                        . '</div>'
                        . '<div>' . ($has ? '<span class="badge bg-success">Enviado</span>' : '<span class="badge bg-warning text-dark">Pendente</span>') . '</div>'
                    . '</div>';

                if ($has) {
                    echo '<div class="small text-muted">Arquivo: ' . htmlspecialchars((string) ($doc['original_name'] ?? '')) . '</div>';
                    echo '<div class="mt-2"><a class="btn btn-sm btn-outline-dark" href="' . htmlspecialchars((string) $doc['file_path']) . '" target="_blank" rel="noopener">Abrir</a></div>';
                }

                echo '<form method="POST" enctype="multipart/form-data" action="/admin/remessa-wp/janela/' . (int) $janelaId . '/pedido/' . (int) $pedidoId . '/documento/' . urlencode($tipo) . '?source=' . urlencode($source) . '" class="mt-2">'
                    . '<input class="form-control form-control-sm" type="file" name="arquivo" ' . ($etq ? '' : 'disabled') . ' required>'
                    . '<button class="btn btn-sm btn-primary mt-2" type="submit" ' . ($etq ? '' : 'disabled') . '>Enviar</button>'
                    . '</form>';

                echo '</div>';
            }
        }

        echo '           </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><strong>Pedido (WordPress)</strong></div>
                        <div class="card-body">';

        $nomeCliente = trim((string) (($wpMeta['_billing_first_name'] ?? '') . ' ' . ($wpMeta['_billing_last_name'] ?? '')));
        if ($nomeCliente === '') {
            $nomeCliente = trim((string) (($wpMeta['_shipping_first_name'] ?? '') . ' ' . ($wpMeta['_shipping_last_name'] ?? '')));
        }

        $ipCliente = trim((string) ($wpMeta['_customer_ip_address'] ?? ($wpMeta['_customer_user_ip'] ?? '')));
        $email = trim((string) ($wpMeta['_billing_email'] ?? ''));
        $cpf = trim((string) ($wpMeta['_billing_cpf'] ?? ($wpMeta['_billing_cnpj'] ?? '')));
        $cel = trim((string) ($wpMeta['_billing_phone'] ?? ''));
        $suite = $wpSuite;

        $zona = $this->formatTriageGroup($wpZona);
        $aceitaSubstRaw = strtolower(trim((string) ($wpMeta['_accept_product_replacement'] ?? '')));
        $aceitaSubst = $aceitaSubstRaw;
        if ($aceitaSubstRaw === 'yes') $aceitaSubst = 'Sim';
        if ($aceitaSubstRaw === 'no') $aceitaSubst = 'Não';
        $codigoRastreio = $trkWx;

        $debugZona = ((string) ($_GET['debug_zona'] ?? '')) === '1';

        $paidDate = trim((string) ($wpMeta['_paid_date'] ?? ($wpMeta['_date_paid'] ?? '')));
        if ($paidDate !== '' && ctype_digit($paidDate)) {
            $paidDate = date('d/m/Y H:i', (int) $paidDate);
        } elseif ($paidDate !== '') {
            $ts = strtotime($paidDate);
            if ($ts) $paidDate = date('d/m/Y H:i', $ts);
        }
        $paymentMethod = trim((string) ($wpMeta['_payment_method_title'] ?? ($wpMeta['_payment_method'] ?? '')));

        $findWxFreteUi = function ($data): ?float {
            if ($data === null) return null;
            if (is_string($data)) {
                $data = json_decode($data, true);
            }
            if (!is_array($data)) return null;

            try {
                $tryPaths = [
                    ['get', 'freight_value'],
                    ['get', 'freight'],
                    ['freight_value_returned'],
                    ['freight_value_sent'],
                    ['freight_value'],
                ];
                foreach ($tryPaths as $path) {
                    $node = $data;
                    foreach ($path as $k) {
                        if (!is_array($node) || !array_key_exists($k, $node)) {
                            $node = null;
                            break;
                        }
                        $node = $node[$k];
                    }
                    if ($node !== null) {
                        $sv = str_replace(',', '.', (string) $node);
                        if (is_numeric($sv) && (float) $sv > 0) {
                            return (float) $sv;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }

            $candidates = [];
            $walk = function ($node, string $path) use (&$walk, &$candidates) {
                if (is_array($node)) {
                    foreach ($node as $k => $v) {
                        $kk = strtolower((string) $k);
                        $p = $path === '' ? $kk : ($path . '.' . $kk);
                        if (is_string($v)) {
                            $vv = trim($v);
                            if ($vv !== '' && (($vv[0] ?? '') === '{' || ($vv[0] ?? '') === '[')) {
                                $decoded = json_decode($vv, true);
                                if (is_array($decoded)) {
                                    $walk($decoded, $p);
                                    continue;
                                }
                            }
                        }
                        if (is_scalar($v)) {
                            $sv = (string) $v;
                            $sv2 = str_replace(',', '.', $sv);
                            if (is_numeric($sv2)) {
                                if (strpos($kk, 'shipping') !== false || strpos($kk, 'frete') !== false || strpos($kk, 'freight') !== false) {
                                    if (strpos($kk, 'tax') === false) {
                                        $candidates[] = (float) $sv2;
                                    }
                                }
                                if ((strpos($kk, 'price') !== false || strpos($kk, 'cost') !== false || strpos($kk, 'amount') !== false)
                                    && (strpos($p, 'shipping') !== false || strpos($p, 'frete') !== false || strpos($p, 'freight') !== false)
                                ) {
                                    $candidates[] = (float) $sv2;
                                }
                                if ($kk === 'value' && (strpos($p, 'shipping') !== false || strpos($p, 'frete') !== false || strpos($p, 'freight') !== false)) {
                                    $candidates[] = (float) $sv2;
                                }
                            }
                        }
                        $walk($v, $p);
                    }
                }
            };
            $walk($data, '');
            if (!$candidates) return null;

            $best = null;
            foreach ($candidates as $v) {
                if ($v <= 0) continue;
                if ($best === null || $v > $best) {
                    $best = $v;
                }
            }
            return $best;
        };

        $wxFreteUi = $findWxFreteUi($link['wexpress_last_response_json'] ?? null);
        if ($wxFreteUi === null || $wxFreteUi <= 0) {
            $wxFreteUi = $findWxFreteUi($link['wexpress_last_request_json'] ?? null);
        }
        $wxFreteUi = ($wxFreteUi !== null && $wxFreteUi > 0) ? $wxFreteUi : null;

        echo '<div class="row g-3">'
            . '<div class="col-lg-4">'
                . '<div class="border rounded p-3 h-100">'
                    . '<div class="mb-2"><strong>Informações do Cliente</strong></div>'
                    . '<div class="small">'
                        . '<div><strong>Nome:</strong> ' . htmlspecialchars($nomeCliente !== '' ? $nomeCliente : '-') . '</div>'
                        . '<div><strong>Suite:</strong> ' . htmlspecialchars($suite !== '' ? $suite : '-') . '</div>'
                        . '<div><strong>E-mail:</strong> ' . htmlspecialchars($email !== '' ? $email : '-') . '</div>'
                        . '<div><strong>CPF/CNPJ:</strong> ' . htmlspecialchars($cpf !== '' ? $cpf : '-') . '</div>'
                        . '<div><strong>Celular:</strong> ' . htmlspecialchars($cel !== '' ? $cel : '-') . '</div>'
                        . '<div><strong>IP:</strong> ' . htmlspecialchars($ipCliente !== '' ? $ipCliente : '-') . '</div>'
                        . '<div><strong>Zona:</strong> ' . htmlspecialchars($zona !== '' ? $zona : '-') . '</div>'
                        . ($debugZona ? ('<div class="text-muted" style="font-size:12px;">debug_zona: package_id=' . (int) ($wpZonaDebug['package_id'] ?? 0) . ' package_meta_key=' . htmlspecialchars((string) ($wpZonaDebug['package_meta_key'] ?? '')) . ' container_id=' . (int) ($wpZonaDebug['container_id'] ?? 0) . ' zona_raw=' . htmlspecialchars((string) ($wpZonaDebug['zona_raw'] ?? '')) . '</div>') : '')
                        . '<div><strong>Aceitar substituição:</strong> ' . htmlspecialchars($aceitaSubst !== '' ? $aceitaSubst : '-') . '</div>'
                        . '<div><strong>Código de rastreio:</strong> ' . htmlspecialchars($codigoRastreio !== '' ? $codigoRastreio : '-') . '</div>'
                    . '</div>'
                . '</div>'
            . '</div>'
            . '<div class="col-lg-4">'
                . '<div class="border rounded p-3 h-100">'
                    . '<div class="mb-2"><strong>Endereço de Cobrança</strong></div>'
                    . '<div class="small">'
                        . '<div><strong>Nome:</strong> ' . htmlspecialchars(trim((string) (($wpMeta['_billing_first_name'] ?? '') . ' ' . ($wpMeta['_billing_last_name'] ?? ''))) ?: '-') . '</div>'
                        . '<div><strong>Empresa:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_company'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>Rua:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_address_1'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>Complemento:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_address_2'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>Número:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_number'] ?? ($wpMeta['billing_number'] ?? ''))) ?: '-') . '</div>'
                        . '<div><strong>Bairro:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_neighborhood'] ?? ($wpMeta['_billing_bairro'] ?? ($wpMeta['billing_bairro'] ?? '')))) ?: '-') . '</div>'
                        . '<div><strong>Cidade:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_city'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>Estado:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_state'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>CEP:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_postcode'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>País:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_billing_country'] ?? '')) ?: '-') . '</div>'
                    . '</div>'
                . '</div>'
            . '</div>'
            . '<div class="col-lg-4">'
                . '<div class="border rounded p-3 h-100">'
                    . '<div class="mb-2"><strong>Endereço de Entrega</strong></div>'
                    . '<div class="small">'
                        . '<div><strong>Nome:</strong> ' . htmlspecialchars(trim((string) (($wpMeta['_shipping_first_name'] ?? '') . ' ' . ($wpMeta['_shipping_last_name'] ?? ''))) ?: '-') . '</div>'
                        . '<div><strong>Empresa:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_company'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>Rua:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_address_1'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>Complemento:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_address_2'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>Número:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_number'] ?? ($wpMeta['shipping_number'] ?? ''))) ?: '-') . '</div>'
                        . '<div><strong>Suite:</strong> ' . htmlspecialchars($suite !== '' ? $suite : '-') . '</div>'
                        . '<div><strong>Bairro:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_neighborhood'] ?? ($wpMeta['_shipping_bairro'] ?? ($wpMeta['shipping_bairro'] ?? '')))) ?: '-') . '</div>'
                        . '<div><strong>Cidade:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_city'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>Estado:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_state'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>CEP:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_postcode'] ?? '')) ?: '-') . '</div>'
                        . '<div><strong>País:</strong> ' . htmlspecialchars(trim((string) ($wpMeta['_shipping_country'] ?? '')) ?: '-') . '</div>'
                    . '</div>'
                . '</div>'
            . '</div>'
        . '</div>';

        echo '<hr>';

        echo '<div class="row g-3">'
            . '<div class="col-12">'
                . '<div class="table-responsive">'
                    . '<table class="table table-sm align-middle">'
                        . '<thead><tr>'
                            . '<th style="width:60px;">Imagem</th>'
                            . '<th>Descrição</th>'
                            . '<th>Declaração</th>'
                            . '<th class="text-end">Qtd</th>'
                            . '<th class="text-end">Preço Unit.</th>'
                            . '<th class="text-end">Total</th>'
                        . '</tr></thead><tbody>';

        if (!$wpItems) {
            echo '<tr><td colspan="6" class="text-muted">Itens não encontrados no WordPress.</td></tr>';
        } else {
            foreach ($wpItems as $it) {
                $img = trim((string) ($it['image_url'] ?? ''));
                $desc = (string) ($it['description'] ?? '');
                $decl = (string) ($it['declaration_name'] ?? '');
                $qty = (int) ($it['qty'] ?? 0);
                $unit = (float) ($it['unit'] ?? 0);
                $tot = (float) ($it['total'] ?? 0);

                $imgHtml = $img !== '' ? ('<img src="' . htmlspecialchars($img) . '" style="max-width:48px;max-height:48px;object-fit:contain;">') : '<span class="text-muted">-</span>';
                echo '<tr>'
                    . '<td>' . $imgHtml . '</td>'
                    . '<td>' . htmlspecialchars($desc !== '' ? $desc : '-') . '</td>'
                    . '<td>' . htmlspecialchars($decl !== '' ? $decl : '-') . '</td>'
                    . '<td class="text-end">' . (int) $qty . '</td>'
                    . '<td class="text-end">' . htmlspecialchars(number_format($unit, 2, ',', '.')) . '</td>'
                    . '<td class="text-end">' . htmlspecialchars(number_format($tot, 2, ',', '.')) . '</td>'
                . '</tr>';
            }
        }

        echo '         </tbody></table>'
                . '</div>'
            . '</div>'
        . '</div>';

        echo '<hr>';

        $curr = trim((string) ($wpTotals['currency'] ?? ''));
        $currLabel = $curr !== '' ? (' (' . $curr . ')') : '';
        $currencyPrefix = ($curr === 'BRL') ? 'R$ ' : (($curr === 'USD') ? 'US$ ' : '');
        $creditAmount = (isset($wpTotals['total']) && $wpTotals['total'] !== null) ? ($currencyPrefix . number_format((float) $wpTotals['total'], 2, ',', '.')) : '';
        $creditAmountLabel = $creditAmount !== '' ? (' (' . $creditAmount . ')') : '';
        echo '<div class="row g-3">'
            . '<div class="col-lg-6">'
                . '<div class="border rounded p-3 h-100">'
                    . '<div class="mb-2"><strong>Pagamento</strong></div>'
                    . '<div class="small">'
                        . '<div><strong>Valor pago:</strong> ' . htmlspecialchars(isset($wpTotals['total']) && $wpTotals['total'] !== null ? number_format((float) $wpTotals['total'], 2, ',', '.') : '-') . $currLabel . '</div>'
                        . '<div><strong>Data de crédito:</strong> ' . htmlspecialchars($paidDate !== '' ? $paidDate : '-') . htmlspecialchars($creditAmountLabel) . '</div>'
                        . '<div><strong>Método:</strong> ' . htmlspecialchars($paymentMethod !== '' ? $paymentMethod : '-') . '</div>'
                    . '</div>'
                . '</div>'
            . '</div>'
            . '<div class="col-lg-6">'
                . '<div class="border rounded p-3 h-100">'
                    . '<div class="mb-2"><strong>Totais</strong></div>'
                    . '<div class="small">'
                        . '<div><strong>Subda pauta:</strong> ' . htmlspecialchars(isset($wpTotals['subtotal']) && $wpTotals['subtotal'] !== null ? number_format((float) $wpTotals['subtotal'], 2, ',', '.') : '-') . $currLabel . '</div>'
                        . '<div><strong>Frete (WExpress):</strong> ' . htmlspecialchars($wxFreteUi !== null ? number_format((float) $wxFreteUi, 2, ',', '.') : '-') . $currLabel . '</div>'
                        . '<div><strong>Frete (WooCommerce):</strong> ' . htmlspecialchars(isset($wpTotals['shipping']) && $wpTotals['shipping'] !== null ? number_format((float) $wpTotals['shipping'], 2, ',', '.') : '-') . $currLabel . '</div>'
                        . '<div><strong>Descontos/Subsídios:</strong> ' . htmlspecialchars(isset($wpTotals['discount']) && $wpTotals['discount'] !== null ? number_format((float) $wpTotals['discount'], 2, ',', '.') : '-') . $currLabel . '</div>'
                        . '<div><strong>Total do pedido:</strong> ' . htmlspecialchars(isset($wpTotals['total']) && $wpTotals['total'] !== null ? number_format((float) $wpTotals['total'], 2, ',', '.') : '-') . $currLabel . '</div>'
                    . '</div>'
                . '</div>'
            . '</div>'
        . '</div>';

        $formatUsefulValue = function ($val) use ($printMode) {
            if ($val === null) {
                return '-';
            }
            if (is_bool($val)) {
                return $val ? 'true' : 'false';
            }
            if (is_array($val) || is_object($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $s = trim((string) $val);
            if ($s === '') {
                return '-';
            }

            $safe = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

            if ($printMode) {
                return '<pre style="white-space:pre-wrap;word-break:break-word;margin:0;">' . $safe . '</pre>';
            }
            if (mb_strlen($s) <= 260) {
                return $safe;
            }

            $short = htmlspecialchars(mb_substr($s, 0, 240) . '...', ENT_QUOTES, 'UTF-8');
            return '<details><summary style="cursor:pointer;">' . $short . '</summary><pre class="mt-2" style="white-space:pre-wrap;word-break:break-word;">' . $safe . '</pre></details>';
        };

        $metaGet = function (string $key) use ($wpMeta) {
            return array_key_exists($key, $wpMeta) ? $wpMeta[$key] : null;
        };

        $useful = [];
        $useful['Chave do Pedido'] = $metaGet('_order_key');
        $useful['Usuário do Cliente'] = $metaGet('_customer_user');
        $useful['Método de Pagamento'] = $metaGet('_payment_method');
        $useful['Título do Método de Pagamento'] = $metaGet('_payment_method_title');
        $useful['Endereço IP do Cliente'] = $metaGet('_customer_ip_address');
        $useful['Agente do Usuário do Cliente'] = $metaGet('_customer_user_agent');
        $useful['Criado Via'] = $metaGet('_created_via');
        $useful['Hash do Carrinho'] = $metaGet('_cart_hash');
        $useful['Permissões de Download'] = $metaGet('_download_permissions_granted');
        $useful['Vendas Registradas'] = $metaGet('_recorded_sales');
        $useful['Contagens de Uso de Cupons Registradas'] = $metaGet('_recorded_coupon_usage_counts');
        $useful['Email de Novo Pedido Enviado'] = $metaGet('_new_order_email_sent');
        $useful['Estoque do Pedido Reduzido'] = $metaGet('_order_stock_reduced');
        $useful['Moeda do Pedido'] = $metaGet('_order_currency');
        $useful['Desconto do Carrinho'] = $metaGet('_cart_discount');
        $useful['Imposto do Desconto do Carrinho'] = $metaGet('_cart_discount_tax');
        $useful['Frete do Pedido'] = $metaGet('_order_shipping');
        $useful['Imposto do Frete do Pedido'] = $metaGet('_order_shipping_tax');
        $useful['Imposto do Pedido'] = $metaGet('_order_tax');
        $useful['Total do Pedido'] = $metaGet('_order_total');
        $useful['Versão do Pedido'] = $metaGet('_order_version');
        $useful['Preços Incluem Imposto'] = $metaGet('_prices_include_tax');

        $useful['Primeiro Nome de Cobrança'] = $metaGet('_billing_first_name');
        $useful['Sobrenome de Cobrança'] = $metaGet('_billing_last_name');
        $useful['Endereço de Cobrança 1'] = $metaGet('_billing_address_1');
        $useful['Billing Address 2'] = $metaGet('_billing_address_2');
        $useful['Cidade de Cobrança'] = $metaGet('_billing_city');
        $useful['Estado de Cobrança'] = $metaGet('_billing_state');
        $useful['CEP de Cobrança'] = $metaGet('_billing_postcode');
        $useful['País de Cobrança'] = $metaGet('_billing_country');
        $useful['Email de Cobrança'] = $metaGet('_billing_email');
        $useful['Telefone de Cobrança'] = $metaGet('_billing_phone');
        $useful['CPF de Cobrança'] = $metaGet('_billing_cpf');
        $useful['Data de Nascimento de Cobrança'] = $metaGet('_billing_birthdate');
        $useful['Número de Cobrança'] = $metaGet('_billing_number');
        $useful['Bairro de Cobrança'] = $metaGet('_billing_neighborhood');

        $useful['Endereço de Entrega 1'] = $metaGet('_shipping_address_1');
        $useful['Shipping Address 2'] = $metaGet('_shipping_address_2');
        $useful['Cidade de Entrega'] = $metaGet('_shipping_city');
        $useful['Estado de Entrega'] = $metaGet('_shipping_state');
        $useful['CEP de Entrega'] = $metaGet('_shipping_postcode');
        $useful['Bairro de Entrega'] = $metaGet('_shipping_neighborhood');
        $useful['Número de Entrega'] = $metaGet('_shipping_number');

        // Mercado Pago / Pix (quando existir)
        $useful['Used Gateway'] = $metaGet('used_gateway');
        $useful['Mercado Pago Payment IDs'] = $metaGet('mercado_pago_payment_ids');
        $useful['Mp Transaction Amount'] = $metaGet('mp_transaction_amount');
        $useful['Mp Pix Qr Code'] = $metaGet('mp_pix_qr_code');
        $useful['Checkout Pix Date Expiration'] = $metaGet('checkout_pix_date_expiration');
        $useful['Data Paga'] = $metaGet('data_paga');
        $useful['Data Concluída'] = $metaGet('data_concluida');

        // Complementos úteis também aparecem em paidDate / date paid, então deixamos explícito
        $useful['Data de crédito (calculada)'] = $paidDate;

        $mpExtras = [];
        $attributionExtras = [];
        $wccsExtras = [];
        $trpExtras = [];
        $tpulExtras = [];
        $checkoutExtras = [];

        foreach ($wpMeta as $k => $v) {
            $ks = strtolower((string) $k);
            if ($ks === '') continue;

            if (strpos($ks, 'mp ') === 0 || strpos($ks, 'mp_') === 0 || strpos($ks, 'mercado') !== false || strpos($ks, 'pix') !== false) {
                $mpExtras[$k] = $v;
                continue;
            }

            // Atribuição / UTM / Referrer
            if (strpos($ks, 'utm_') !== false || strpos($ks, 'attribution') !== false || strpos($ks, 'referrer') !== false || strpos($ks, 'landing') !== false || strpos($ks, 'session') !== false) {
                $attributionExtras[$k] = $v;
                continue;
            }

            // WCCS (câmbio / moeda)
            if (strpos($ks, 'wccs') !== false || strpos($ks, 'currency_ratio') !== false || strpos($ks, 'base_currency') !== false) {
                $wccsExtras[$k] = $v;
                continue;
            }

            // TRP / idioma
            if (strpos($ks, 'trp') !== false || strpos($ks, 'language') !== false || strpos($ks, 'idioma') !== false) {
                $trpExtras[$k] = $v;
                continue;
            }

            // TPUL / visitor
            if (strpos($ks, 'tpul') !== false || strpos($ks, 'visitor') !== false) {
                $tpulExtras[$k] = $v;
                continue;
            }

            // Checkout / gateway
            if (strpos($ks, 'checkout') !== false || strpos($ks, 'gateway') !== false || strpos($ks, 'used gateway') !== false || strpos($ks, 'blocks payment') !== false) {
                $checkoutExtras[$k] = $v;
                continue;
            }
        }

        echo '<hr>';
        echo '<div class="card">'
            . '<div class="card-header"><strong>Informações Úteis</strong></div>'
            . '<div class="card-body">'
                . '<div class="table-responsive">'
                    . '<table class="table table-sm align-middle">'
                        . '<thead><tr><th style="width: 320px;">Campo</th><th>Valor</th></tr></thead>'
                        . '<tbody>';

        foreach ($useful as $label => $val) {
            echo '<tr>'
                . '<td><strong>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</strong></td>'
                . '<td>' . $formatUsefulValue($val) . '</td>'
            . '</tr>';
        }

        $renderExtras = function (string $title, array $items) use ($formatUsefulValue) {
            if (empty($items)) {
                return;
            }
            echo '<tr><td colspan="2" class="text-muted small">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td></tr>';
            foreach ($items as $k => $v) {
                echo '<tr>'
                    . '<td><strong>' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') . '</strong></td>'
                    . '<td>' . $formatUsefulValue($v) . '</td>'
                . '</tr>';
            }
        };

        $renderExtras('Metas adicionais (Pix/Mercado Pago)', $mpExtras);
        $renderExtras('Metas adicionais (Atribuição / UTM / Sessão)', $attributionExtras);
        $renderExtras('Metas adicionais (WCCS / Câmbio)', $wccsExtras);
        $renderExtras('Metas adicionais (TRP / Idioma)', $trpExtras);
        $renderExtras('Metas adicionais (TPUL / Visitor)', $tpulExtras);
        $renderExtras('Metas adicionais (Checkout / Gateway)', $checkoutExtras);

        echo '           </tbody></table>'
                . '</div>'
            . '</div>'
        . '</div>';

        echo '           </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><strong>Recebimento</strong></div>
                        <div class="card-body">';

        if ($recebido) {
            echo '<div class="alert alert-success mb-0">
                    <div><strong>Recebimento confirmado.</strong></div>'
                    . ($recebidoEm !== '' ? ('<div class="small">Em: <strong>' . htmlspecialchars(date('d/m/Y H:i', strtotime($recebidoEm))) . '</strong></div>') : '')
                . '</div>';
        } else {
            echo '<div class="text-muted">Use o botão <strong>Confirmar recebimento</strong> no topo desta tela.</div>';
        }

        echo '           </div>
                    </div>
                </div>
            </div>';

        echo '</main></div></div>';

        echo '\n<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>'
            . ($printMode ? ('<script>(function(){try{document.title=' . json_encode($invoiceTitle, JSON_UNESCAPED_UNICODE) . ';window.focus();setTimeout(function(){window.print();},200);window.addEventListener("afterprint",function(){try{window.close();}catch(e){}});}catch(e){}})();</script>') : '')
            . '</body>\n</html>';
        exit;
    }

    public function confirmarRecebimento($request, $janelaId, $pedidoId) {
        $this->requireAccess();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $source = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        if (!in_array($source, self::SOURCES, true)) $source = 'br';

        $janelaId = (int) $janelaId;
        $pedidoId = (int) $pedidoId;
        if ($janelaId <= 0 || $pedidoId <= 0) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }

        try {
            $st = $this->connection->prepare(
                'UPDATE remessa_wp_janela_pedidos
                 SET recebido_confirmado = 1,
                     recebido_confirmado_em = COALESCE(recebido_confirmado_em, NOW())
                 WHERE janela_id = ? AND source = ? AND order_id = ?'
            );
            $st->execute([$janelaId, $source, $pedidoId]);
            $_SESSION['message'] = 'Recebimento confirmado.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao confirmar recebimento: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/remessa-wp/janela/' . $janelaId . '/pedido/' . $pedidoId . '?source=' . urlencode($source));
        exit;
    }

    public function salvarMedicamento($request, $janelaId, $pedidoId) {
        $this->requireAccess();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $source = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        if (!in_array($source, self::SOURCES, true)) $source = 'br';

        $janelaId = (int) $janelaId;
        $pedidoId = (int) $pedidoId;
        $val = (int) ($_POST['medicamento'] ?? 0);
        $val = ($val === 1) ? 1 : 0;

        try {
            if (!$this->tableExists('remessa_wp_janela_pedidos')) {
                throw new \RuntimeException('Tabela remessa_wp_janela_pedidos não encontrada.');
            }
            $st = $this->connection->prepare('UPDATE remessa_wp_janela_pedidos SET medicamento = ? WHERE janela_id = ? AND source = ? AND order_id = ?');
            $st->execute([$val, $janelaId, $source, $pedidoId]);
            $_SESSION['message'] = 'Atualizado.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/remessa-wp/janela/' . $janelaId . '/pedido/' . $pedidoId . '?source=' . urlencode($source));
        exit;
    }

    public function uploadDocumento($request, $janelaId, $pedidoId, $tipo) {
        $this->requireAccess();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $source = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        if (!in_array($source, self::SOURCES, true)) $source = 'br';

        $janelaId = (int) $janelaId;
        $pedidoId = (int) $pedidoId;
        $tipoNorm = $this->normalizeDocTipo((string) $tipo);
        if ($janelaId <= 0 || $pedidoId <= 0 || $tipoNorm === null) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }

        try {
            if (!$this->tableExists('remessa_wp_pedido_documentos')) {
                throw new \RuntimeException('Tabela de documentos não encontrada. Rode a migration 048_add_docs_to_remessa_wp.sql');
            }

            $stL = $this->connection->prepare('SELECT etiqueta_gerada, medicamento FROM remessa_wp_janela_pedidos WHERE janela_id = ? AND source = ? AND order_id = ? LIMIT 1');
            $stL->execute([$janelaId, $source, $pedidoId]);
            $lnk = $stL->fetch(\PDO::FETCH_ASSOC) ?: null;
            if (!$lnk) {
                throw new \RuntimeException('Pedido não encontrado na janela.');
            }
            if (((int) ($lnk['etiqueta_gerada'] ?? 0)) !== 1) {
                throw new \RuntimeException('Uploads bloqueados até gerar etiqueta.');
            }

            if ($tipoNorm === 'pagamento' && $source !== 'red') {
                throw new \RuntimeException('Comprovante de pagamento só é exigido para pedidos do redirecionamento (RED).');
            }

            $isMed = ((int) ($lnk['medicamento'] ?? 0)) === 1;
            if ($tipoNorm === 'medicamento' && !$isMed) {
                throw new \RuntimeException('Documento de medicamento só é exigido quando o pedido está marcado como medicamento.');
            }

            if (!isset($_FILES['arquivo']) || !is_array($_FILES['arquivo'])) {
                throw new \RuntimeException('Arquivo não enviado.');
            }
            if (($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Falha no upload.');
            }

            $tmp = (string) ($_FILES['arquivo']['tmp_name'] ?? '');
            $origName = (string) ($_FILES['arquivo']['name'] ?? '');
            $size = (int) ($_FILES['arquivo']['size'] ?? 0);
            if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
                throw new \RuntimeException('Arquivo inválido.');
            }

            $max = 20 * 1024 * 1024;
            if ($size > $max) {
                throw new \RuntimeException('Arquivo acima do limite de 20MB.');
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowedExt, true)) {
                throw new \RuntimeException('Extensão inválida. Permitidos: ' . implode(', ', $allowedExt));
            }

            $dirs = $this->getUploadsDirRemessaWp();
            $absDir = (string) ($dirs['abs'] ?? '');
            $webDir = (string) ($dirs['web'] ?? '/uploads/remessa-wp');
            if ($absDir === '') {
                throw new \RuntimeException('Diretório de upload não disponível.');
            }

            $fname = 'wp_' . $source . '_j' . (int) $janelaId . '_o' . (int) $pedidoId . '_' . $tipoNorm . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $abs = rtrim($absDir, '/\\') . DIRECTORY_SEPARATOR . $fname;
            $rel = rtrim($webDir, '/') . '/' . $fname;

            if (!@move_uploaded_file($tmp, $abs)) {
                throw new \RuntimeException('Não foi possível salvar o arquivo.');
            }

            $auth = new AuthService();
            $u = $auth->getUsuarioLogado();
            $uid = is_array($u) ? (int) ($u['id'] ?? 0) : 0;
            if ($uid <= 0) $uid = null;

            $st = $this->connection->prepare(
                'INSERT INTO remessa_wp_pedido_documentos (janela_id, source, order_id, tipo, status, file_path, original_name, mime, file_size, uploaded_by_user_id, uploaded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    file_path = VALUES(file_path),
                    original_name = VALUES(original_name),
                    mime = VALUES(mime),
                    file_size = VALUES(file_size),
                    uploaded_by_user_id = VALUES(uploaded_by_user_id),
                    uploaded_at = NOW()'
            );
            $st->execute([
                $janelaId,
                $source,
                $pedidoId,
                $tipoNorm,
                'ok',
                $rel,
                $origName,
                (string) ($_FILES['arquivo']['type'] ?? ''),
                $size,
                $uid,
            ]);

            $_SESSION['message'] = 'Documento enviado.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/remessa-wp/janela/' . $janelaId . '/pedido/' . $pedidoId . '?source=' . urlencode($source));
        exit;
    }

    public function criarJanelaTeste($request) {
        $this->requireAccess();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $source = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        if (!in_array($source, self::SOURCES, true)) $source = 'br';

        try {
            if (!$this->tableExists('remessa_wp_janelas')) {
                throw new \RuntimeException('Tabelas de Remessa WP não encontradas. Rode a migration 047_create_remessa_wp_janelas.sql');
            }

            $titulo = 'Janela de testes';
            $start = new \DateTime('now');
            $start->setTime(0, 0, 0);
            $end = (clone $start);
            $end->modify('+365 days');
            $end->setTime(23, 59, 59);

            $stFind = $this->connection->prepare("SELECT id FROM remessa_wp_janelas WHERE source = ? AND tipo = 'manual' AND titulo = ? AND status = 'aberta' ORDER BY id DESC LIMIT 1");
            $stFind->execute([$source, $titulo]);
            $id = (int) ($stFind->fetchColumn() ?: 0);

            if ($id <= 0) {
                $stIns = $this->connection->prepare("INSERT INTO remessa_wp_janelas (source, data_inicio, data_fim, status, tipo, titulo, created_at, updated_at) VALUES (?, ?, ?, 'aberta', 'manual', ?, NOW(), NOW())");
                $stIns->execute([$source, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $titulo]);
                $id = (int) $this->connection->lastInsertId();
            }

            $_SESSION['message'] = 'Janela de testes pronta.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/remessa-wp/janela/' . $id . '?source=' . urlencode($source));
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao criar janela de testes: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }
    }

    public function adicionarPedidoManual($request, $id) {
        $this->requireAccess();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $source = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        if (!in_array($source, self::SOURCES, true)) $source = 'br';

        $janelaId = (int) $id;
        $orderId = (int) ($_POST['order_id'] ?? 0);
        if ($janelaId <= 0 || $orderId <= 0) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }

        try {
            $stJ = $this->connection->prepare('SELECT tipo FROM remessa_wp_janelas WHERE id = ? AND source = ? LIMIT 1');
            $stJ->execute([$janelaId, $source]);
            $tipo = strtolower(trim((string) ($stJ->fetchColumn() ?: '')));
            if ($tipo !== 'manual') {
                throw new \RuntimeException('Só é permitido adicionar pedido manualmente em janelas manuais.');
            }

            $stIns = $this->connection->prepare('INSERT IGNORE INTO remessa_wp_janela_pedidos (janela_id, source, order_id, created_at) VALUES (?, ?, ?, NOW())');
            $stIns->execute([$janelaId, $source, $orderId]);

            // Preencher campos de etiqueta se já existir no WP
            $wp = $this->getWpPdo($this->connection, $source);
            $prefix = $wp['prefix'];
            $wpPdo = $wp['pdo'];

            $stMeta = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key IN ('wexpress_shipping_id','wexpress_tracking_number','courier_tracking_number','wexpress_status','wexpress_label_url','_wexpress_label_url','wp_wexpress_label_url')");
            $stMeta->execute([$orderId]);
            $rows = $stMeta->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $m = [];
            foreach ($rows as $r) {
                $k = (string) ($r['meta_key'] ?? '');
                if ($k === '') continue;
                $m[$k] = (string) ($r['meta_value'] ?? '');
            }

            $shipId = trim((string) ($m['wexpress_shipping_id'] ?? ''));
            $trk = trim((string) ($m['wexpress_tracking_number'] ?? ''));
            $courier = trim((string) ($m['courier_tracking_number'] ?? ''));
            $status = trim((string) ($m['wexpress_status'] ?? ''));
            $label = trim((string) ($m['wexpress_label_url'] ?? ($m['_wexpress_label_url'] ?? ($m['wp_wexpress_label_url'] ?? ''))));
            $hasLabel = ($shipId !== '' || $label !== '' || $status === 'LABEL_CREATED');

            $stUp = $this->connection->prepare(
                'UPDATE remessa_wp_janela_pedidos
                 SET etiqueta_gerada = ?,
                     etiqueta_gerada_em = IF(?, COALESCE(etiqueta_gerada_em, NOW()), etiqueta_gerada_em),
                     wexpress_shipping_id = ?,
                     wexpress_tracking_number = ?,
                     courier_tracking_number = ?,
                     wexpress_status = ?,
                     wexpress_label_url = ?,
                     wexpress_updated_at = NOW()
                 WHERE janela_id = ? AND source = ? AND order_id = ?'
            );
            $stUp->execute([
                $hasLabel ? 1 : 0,
                $hasLabel ? 1 : 0,
                $shipId !== '' ? $shipId : null,
                $trk !== '' ? $trk : null,
                $courier !== '' ? $courier : null,
                $status !== '' ? $status : null,
                $label !== '' ? $label : null,
                $janelaId,
                $source,
                $orderId,
            ]);

            $_SESSION['message'] = 'Pedido adicionado na janela de testes.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao adicionar pedido: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/remessa-wp/janela/' . $janelaId . '?source=' . urlencode($source));
        exit;
    }

    public function popularPrimeiraRemessa($request) {
        $this->requireAccess();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sourceRaw = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        $sources = [];
        if ($sourceRaw === 'all') {
            $sources = self::SOURCES;
        } else {
            $sources = [$this->normalizeSource($sourceRaw)];
        }

        try {
            if (!$this->tableExists('remessa_wp_janelas') || !$this->tableExists('remessa_wp_janela_pedidos')) {
                throw new \RuntimeException('Tabelas de Remessa WP não encontradas. Rode a migration 047_create_remessa_wp_janelas.sql');
            }

            $totalAdded = 0;
            $lastJanelaId = 0;

            foreach ($sources as $src) {
                $src = $this->normalizeSource($src);

                $titulo = 'Primeira remessa';
                $start = new \DateTime('now');
                $start->setTime(0, 0, 0);
                $end = new \DateTime('now');
                $end->setTime(23, 59, 59);

                $janelaId = 0;
                try {
                    $stFind = $this->connection->prepare("SELECT id FROM remessa_wp_janelas WHERE source = ? AND tipo = 'manual' AND titulo = ? ORDER BY id DESC LIMIT 1");
                    $stFind->execute([$src, $titulo]);
                    $janelaId = (int) ($stFind->fetchColumn() ?: 0);
                } catch (\Exception $e) {
                    $janelaId = 0;
                }

                if ($janelaId <= 0) {
                    $stIns = $this->connection->prepare("INSERT INTO remessa_wp_janelas (source, data_inicio, data_fim, status, tipo, titulo, created_at, updated_at) VALUES (?, ?, ?, 'finalizada', 'manual', ?, NOW(), NOW())");
                    $stIns->execute([$src, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $titulo]);
                    $janelaId = (int) $this->connection->lastInsertId();
                } else {
                    // mantém data coerente e fecha a janela (bucket histórico)
                    $stUpJ = $this->connection->prepare("UPDATE remessa_wp_janelas SET data_inicio = ?, data_fim = ?, status = 'finalizada', updated_at = NOW() WHERE id = ?");
                    $stUpJ->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $janelaId]);
                }

                $lastJanelaId = $janelaId;

                $wp = $this->getWpPdo($this->connection, $src);
                $prefix = $wp['prefix'];
                $wpPdo = $wp['pdo'];

                // pedidos com etiqueta (por meta)
                $metaKeys = [
                    'wexpress_shipping_id',
                    'wexpress_label_url',
                    'wp_wexpress_label_url',
                    '_wexpress_label_url',
                ];

                $ph = implode(',', array_fill(0, count($metaKeys), '?'));
                $sqlIds = "
                    SELECT DISTINCT pm.post_id
                    FROM {$prefix}postmeta pm
                    INNER JOIN {$prefix}posts p ON p.ID = pm.post_id
                    WHERE p.post_type = 'shop_order'
                      AND pm.meta_key IN ({$ph})
                      AND TRIM(COALESCE(pm.meta_value,'')) <> ''
                ";
                $stIds = $wpPdo->prepare($sqlIds);
                $stIds->execute($metaKeys);
                $orderIds = $stIds->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                $orderIds = array_values(array_filter(array_map('intval', $orderIds), function ($v) { return $v > 0; }));

                if (!$orderIds) {
                    continue;
                }

                $stInsLink = $this->connection->prepare('INSERT IGNORE INTO remessa_wp_janela_pedidos (janela_id, source, order_id, created_at) VALUES (?, ?, ?, NOW())');
                foreach ($orderIds as $oid) {
                    $stInsLink->execute([$janelaId, $src, (int) $oid]);
                }

                // carregar metas (em lote) para preencher campos no banco local
                $chunkSize = 800;
                $keysFetch = ['wexpress_shipping_id','wexpress_tracking_number','courier_tracking_number','wexpress_status','wexpress_label_url','_wexpress_label_url','wp_wexpress_label_url'];

                $stUp = $this->connection->prepare(
                    'UPDATE remessa_wp_janela_pedidos
                     SET
                        etiqueta_gerada = ?,
                        etiqueta_gerada_em = IF(?, COALESCE(etiqueta_gerada_em, NOW()), etiqueta_gerada_em),
                        wexpress_shipping_id = ?,
                        wexpress_tracking_number = ?,
                        courier_tracking_number = ?,
                        wexpress_status = ?,
                        wexpress_label_url = ?,
                        wexpress_updated_at = NOW()
                     WHERE janela_id = ? AND source = ? AND order_id = ?'
                );

                for ($i = 0; $i < count($orderIds); $i += $chunkSize) {
                    $chunk = array_slice($orderIds, $i, $chunkSize);
                    $phIds = implode(',', array_fill(0, count($chunk), '?'));
                    $phKeys = implode(',', array_fill(0, count($keysFetch), '?'));
                    $sqlMeta = "SELECT post_id, meta_key, meta_value FROM {$prefix}postmeta WHERE post_id IN ({$phIds}) AND meta_key IN ({$phKeys})";
                    $stMeta = $wpPdo->prepare($sqlMeta);
                    $stMeta->execute(array_merge(array_map('intval', $chunk), $keysFetch));
                    $rows = $stMeta->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                    $byOrder = [];
                    foreach ($rows as $r) {
                        $oid = (int) ($r['post_id'] ?? 0);
                        if ($oid <= 0) continue;
                        $k = (string) ($r['meta_key'] ?? '');
                        if ($k === '') continue;
                        $byOrder[$oid][$k] = (string) ($r['meta_value'] ?? '');
                    }

                    foreach ($chunk as $oid) {
                        $m = $byOrder[(int) $oid] ?? [];
                        $shipId = trim((string) ($m['wexpress_shipping_id'] ?? ''));
                        $trk = trim((string) ($m['wexpress_tracking_number'] ?? ''));
                        $courier = trim((string) ($m['courier_tracking_number'] ?? ''));
                        $status = trim((string) ($m['wexpress_status'] ?? ''));
                        $label = trim((string) ($m['wexpress_label_url'] ?? ($m['_wexpress_label_url'] ?? ($m['wp_wexpress_label_url'] ?? ''))));

                        $hasLabel = ($shipId !== '' || $label !== '' || $status === 'LABEL_CREATED');
                        if ($hasLabel) {
                            $totalAdded++;
                        }

                        $stUp->execute([
                            $hasLabel ? 1 : 0,
                            $hasLabel ? 1 : 0,
                            $shipId !== '' ? $shipId : null,
                            $trk !== '' ? $trk : null,
                            $courier !== '' ? $courier : null,
                            $status !== '' ? $status : null,
                            $label !== '' ? $label : null,
                            $janelaId,
                            $src,
                            (int) $oid,
                        ]);
                    }
                }
            }

            $_SESSION['message'] = 'Primeira remessa populada. Pedidos adicionados/atualizados: ' . (int) $totalAdded;
            $_SESSION['message_type'] = 'success';

            if (count($sources) === 1 && $lastJanelaId > 0) {
                header('Location: /admin/remessa-wp/janela/' . $lastJanelaId . '?source=' . urlencode($sources[0]));
                exit;
            }
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao popular primeira remessa: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/remessa-wp?source=' . urlencode($sourceRaw));
        exit;
    }
}
