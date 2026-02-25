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
        $allowed = ['compra', 'pagamento', 'medicamento'];
        return in_array($tipo, $allowed, true) ? $tipo : null;
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

        $stAll = $this->connection->prepare('SELECT id, data_fim, status FROM remessa_wp_janelas WHERE source = ? ORDER BY data_inicio ASC');
        $stAll->execute([$source]);
        $all = $stAll->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($all as $j) {
            $dataFim = new \DateTime((string) $j['data_fim']);
            $status = (string) ($j['status'] ?? 'aberta');
            if ($status === 'aberta' && $dataFim < $now) {
                $stUp = $this->connection->prepare("UPDATE remessa_wp_janelas SET status = 'finalizada', updated_at = NOW() WHERE id = ?");
                $stUp->execute([(int) $j['id']]);
            }
        }

        $st = $this->connection->prepare('SELECT * FROM remessa_wp_janelas WHERE source = ? AND data_inicio <= ? AND data_fim >= ? ORDER BY id ASC');
        $st->execute([$source, $nowStr, $nowStr]);
        $current = $st->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$current) {
            $stLast = $this->connection->prepare('SELECT * FROM remessa_wp_janelas WHERE source = ? ORDER BY data_inicio DESC LIMIT 1');
            $stLast->execute([$source]);
            $last = $stLast->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($last) {
                $start = new \DateTime((string) $last['data_fim']);
                $start->modify('+1 second');
            } else {
                $start = new \DateTime($now->format('Y-m-d 00:00:00'));
            }

            while (true) {
                $end = (clone $start);
                $end->modify('+12 days');
                $end->setTime(23, 59, 59);

                $status = ($end < $now) ? 'finalizada' : 'aberta';

                $stExists = $this->connection->prepare('SELECT id FROM remessa_wp_janelas WHERE source = ? AND data_inicio = ? AND data_fim = ? LIMIT 1');
                $stExists->execute([$source, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
                $existingId = (int) ($stExists->fetchColumn() ?: 0);
                if ($existingId <= 0) {
                    $stIns = $this->connection->prepare('INSERT INTO remessa_wp_janelas (source, data_inicio, data_fim, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
                    $stIns->execute([
                        $source,
                        $start->format('Y-m-d H:i:s'),
                        $end->format('Y-m-d H:i:s'),
                        $status,
                    ]);
                }

                if ($start <= $now && $end >= $now) {
                    break;
                }

                $start = (clone $end);
                $start->modify('+1 second');
            }

            $st->execute([$source, $nowStr, $nowStr]);
            $current = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
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

        $stO = $wpPdo->prepare("SELECT ID FROM {$prefix}posts WHERE post_type = 'shop_order' AND post_status = 'wc-invoice-fechado' AND post_date >= ? AND post_date <= ?");
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

        $source = strtolower(trim((string) ($request->getParam('source') ?? ($_GET['source'] ?? 'br'))));
        if (!in_array($source, self::SOURCES, true)) $source = 'br';

        if (!$this->tableExists('remessa_wp_janelas') || !$this->tableExists('remessa_wp_janela_pedidos')) {
            echo '<div class="alert alert-danger">Tabelas de Remessa WP não encontradas. Rode a migration: database/migrations/047_create_remessa_wp_janelas.sql</div>';
            exit;
        }

        $errorMsg = null;
        try {
            $janelaAtual = $this->ensureJanelaAtual($source);
            $janelasAbertas = $this->getJanelasByStatus($source, ['aberta']);
            $janelasFinalizadas = $this->getJanelasByStatus($source, ['finalizada']);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $janelaAtual = [];
            $janelasAbertas = [];
            $janelasFinalizadas = [];
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

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
                            <option value="br"' . ($source === 'br' ? ' selected' : '') . '>BR</option>
                            <option value="red"' . ($source === 'red' ? ' selected' : '') . '>RED</option>
                            <option value="us"' . ($source === 'us' ? ' selected' : '') . '>US</option>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary">Filtrar</button>
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
                echo '<a class="list-group-item list-group-item-action" href="/admin/remessa-wp/janela/' . (int) $j['id'] . '?source=' . urlencode($source) . '">
                    <div class="d-flex justify-content-between">
                        <div><strong>Janela #' . (int) $j['id'] . '</strong> <span class="text-muted">(' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_fim']))) . ')</span></div>
                        <span class="badge bg-success">Aberta</span>
                    </div>
                </a>';
            }
            echo '</div>';
        }

        echo '    </div>
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
                echo '<a class="list-group-item list-group-item-action" href="/admin/remessa-wp/janela/' . (int) $j['id'] . '?source=' . urlencode($source) . '">
                    <div class="d-flex justify-content-between">
                        <div><strong>Janela #' . (int) $j['id'] . '</strong> <span class="text-muted">(' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $j['data_fim']))) . ')</span></div>
                        <span class="badge bg-secondary">Finalizada</span>
                    </div>
                </a>';
            }
            echo '</div>';
        }

        echo '    </div>
                </div>
            </div>
        </div>';

        echo '</main></div></div>
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

        $this->syncPedidosParaJanela($janelaId, $source);

        $stJ = $this->connection->prepare('SELECT * FROM remessa_wp_janelas WHERE id = ? AND source = ? LIMIT 1');
        $stJ->execute([$janelaId, $source]);
        $janela = $stJ->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$janela) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
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
        if ($orderIds) {
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
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remessa WP - Janela #' . (int) $janelaId . '</title>
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
                    <h1 class="h4 mb-0">Janela #' . (int) $janelaId . '</h1>
                    <div class="text-muted small">' . htmlspecialchars(date('d/m/Y', strtotime((string) $janela['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $janela['data_fim']))) . '</div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="/admin/remessa-wp?source=' . urlencode($source) . '">Voltar</a>
                    <button class="btn btn-outline-primary" type="button" onclick="location.reload()">Atualizar</button>
                    <button class="btn btn-danger" type="button" onclick="regerarEtiquetasMassa()">Regerar etiquetas (já geradas)</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Pedidos</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                    <th>Etiqueta</th>
                                    <th>Tracking</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (!$links) {
            echo '<tr><td colspan="6" class="text-center text-muted">Nenhum pedido nesta janela.</td></tr>';
        } else {
            foreach ($links as $lnk) {
                $oid = (int) ($lnk['order_id'] ?? 0);
                $o = $orders[$oid] ?? [];
                $status = (string) ($o['post_status'] ?? '');
                $date = (string) ($o['post_date'] ?? '');

                $etq = ((int) ($lnk['etiqueta_gerada'] ?? 0)) === 1;
                $trk = (string) ($lnk['courier_tracking_number'] ?? ($lnk['wexpress_tracking_number'] ?? ''));
                $labelUrl = (string) ($lnk['wexpress_label_url'] ?? '');

                echo '<tr>
                    <td><strong>#' . (int) $oid . '</strong></td>
                    <td>' . ($date !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($date))) : '-') . '</td>
                    <td><span class="badge bg-light text-dark">' . htmlspecialchars($status !== '' ? $status : '-') . '</span></td>
                    <td>' . ($etq ? '<span class="badge bg-success">Gerada</span>' : '<span class="badge bg-warning text-dark">Pendente</span>') . '</td>
                    <td>' . ($trk !== '' ? htmlspecialchars($trk) : '-') . '</td>
                    <td class="text-nowrap">'
                        . ($labelUrl !== '' ? ('<a class="btn btn-sm btn-outline-primary" target="_blank" href="' . htmlspecialchars($labelUrl) . '">Etiqueta</a> ') : '')
                        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/remessa-wp/janela/' . (int) $janelaId . '/pedido/' . (int) $oid . '?source=' . urlencode($source) . '">Detalhes</a> '
                        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/pedidos-wp/detalhes/' . (int) $oid . '?source=' . urlencode($source) . '">Pedido</a>'
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

    let idx = 0;
    const runNext = () => {
        if (idx >= targets.length) {
            alert("Concluído. Recarregando...");
            location.reload();
            return;
        }
        const id = targets[idx++];
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
            setTimeout(runNext, 350);
        })
        .catch(err => {
            console.warn("Erro ao regerar etiqueta do pedido", id, err);
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

        $janelaId = (int) $janelaId;
        $pedidoId = (int) $pedidoId;
        if ($janelaId <= 0 || $pedidoId <= 0) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
        }

        $this->syncPedidosParaJanela($janelaId, $source);

        $stJ = $this->connection->prepare('SELECT * FROM remessa_wp_janelas WHERE id = ? AND source = ? LIMIT 1');
        $stJ->execute([$janelaId, $source]);
        $janela = $stJ->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$janela) {
            header('Location: /admin/remessa-wp?source=' . urlencode($source));
            exit;
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
        try {
            $wp = $this->getWpPdo($this->connection, $source);
            $prefix = $wp['prefix'];
            $wpPdo = $wp['pdo'];
            $stO = $wpPdo->prepare("SELECT ID, post_date, post_status, post_title FROM {$prefix}posts WHERE ID = ? AND post_type = 'shop_order' LIMIT 1");
            $stO->execute([$pedidoId]);
            $wpOrder = $stO->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remessa WP - Pedido #' . (int) $pedidoId . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head>
<body>
<div class="container-fluid"><div class="row">';
        renderAdminSidebar('remessa-wp');

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

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h4 mb-0">Pedido #' . (int) $pedidoId . '</h1>
                    <div class="text-muted small">Janela #' . (int) $janelaId . ' (' . htmlspecialchars(date('d/m/Y', strtotime((string) $janela['data_inicio']))) . ' a ' . htmlspecialchars(date('d/m/Y', strtotime((string) $janela['data_fim']))) . ')</div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="/admin/remessa-wp/janela/' . (int) $janelaId . '?source=' . urlencode($source) . '">Voltar</a>
                    <a class="btn btn-outline-secondary" href="/admin/pedidos-wp/detalhes/' . (int) $pedidoId . '?source=' . urlencode($source) . '">Abrir pedido</a>
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
            $requirements = [
                'compra' => ['label' => 'Comprovante de compra', 'required' => true],
                'pagamento' => ['label' => 'Comprovante de pagamento', 'required' => true],
            ];
            if ($medicamento) {
                $requirements['medicamento'] = ['label' => 'Documento de medicamento', 'required' => true];
            }

            if (!$etq) {
                echo '<div class="alert alert-secondary">Uploads bloqueados até gerar etiqueta.</div>';
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
                        <div class="card-header"><strong>Recebimento</strong></div>
                        <div class="card-body">';

        if ($recebido) {
            echo '<div class="alert alert-success mb-0">
                    <div><strong>Recebimento confirmado.</strong></div>'
                    . ($recebidoEm !== '' ? ('<div class="small">Em: <strong>' . htmlspecialchars(date('d/m/Y H:i', strtotime($recebidoEm))) . '</strong></div>') : '')
                . '</div>';
        } else {
            echo '<form method="POST" action="/admin/remessa-wp/janela/' . (int) $janelaId . '/pedido/' . (int) $pedidoId . '/confirmar-recebimento?source=' . urlencode($source) . '" onsubmit="return confirm(\"Confirmar recebimento deste pedido?\")">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Confirmar recebimento</button>
                  </form>';
        }

        echo '           </div>
                    </div>
                </div>
            </div>';

        echo '</main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
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
}
