<?php
$busca = $busca ?? '';
$page = $page ?? 1;
$limite = $limite ?? 50;
$total = $total ?? 0;
$pedidos = is_array($pedidos ?? null) ? $pedidos : [];
$erro = (string) ($erro ?? '');
$source = strtolower(trim((string) ($source ?? ($_GET['source'] ?? 'br'))));
$allowedSources = ['all', 'br', 'red', 'us'];
if (!in_array($source, $allowedSources, true)) $source = 'br';

$totalPaginas = $limite > 0 ? (int) ceil($total / $limite) : 1;
if ($totalPaginas <= 0) $totalPaginas = 1;

function wpFormatMoney($v, $currency) {
    $currency = strtoupper(trim((string) $currency));
    if ($currency === '') $currency = 'BRL';
    $v = is_numeric($v) ? (float) $v : 0.0;
    $fmt = number_format($v, 2, ',', '.');
    if ($currency === 'BRL') return __('admin.orders.js.currency_brl', 'R$') . ' ' . $fmt;
    if ($currency === 'USD') return __('admin.orders.js.currency_usd', 'US$') . ' ' . $fmt;
    return $currency . ' ' . $fmt;
}

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('admin.orders_wp.title', 'Pedidos (WordPress)') ?> (<?= (int) $total ?>)</h1>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-success" data-bs-toggle="collapse" data-bs-target="#wpExportBox" aria-expanded="false" aria-controls="wpExportBox">
            <i class="fas fa-file-export"></i> Exportar pedidos
        </button>
    </div>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?= __('admin.orders_wp.error_load_prefix', 'Erro ao carregar pedidos do WordPress:') ?> <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<form method="GET" class="row g-3 mb-4">
    <div class="col-md-6">
        <input type="text" class="form-control" name="busca" placeholder="<?= htmlspecialchars(__('admin.orders_wp.search_placeholder', 'Buscar por ID, número, nome ou email...'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($busca) ?>">
    </div>
    <div class="col-md-2">
        <select class="form-select" name="source">
            <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>Todas</option>
            <option value="br" <?= $source === 'br' ? 'selected' : '' ?>>BR</option>
            <option value="red" <?= $source === 'red' ? 'selected' : '' ?>>RED</option>
            <option value="us" <?= $source === 'us' ? 'selected' : '' ?>>US</option>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" name="limit">
            <?php foreach ([25,50,100,200] as $l): ?>
                <option value="<?= $l ?>" <?= ((int)$limite === (int)$l) ? 'selected' : '' ?>><?= $l ?>/<?= __('common.page', 'página') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> <?= __('common.filter', 'Filtrar') ?></button>
    </div>
</form>

<div class="collapse mb-4" id="wpExportBox">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" action="/admin/pedidos-wp/export" class="row g-3" target="_blank">
                <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
                <div class="col-md-3">
                    <label class="form-label">Data inicial</label>
                    <input type="date" class="form-control" name="start" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data final</label>
                    <input type="date" class="form-control" name="end" required>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download"></i> Baixar CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= __('admin.orders.table.id', 'ID') ?></th>
                        <th><?= __('admin.orders_wp.table.source', 'Origem') ?></th>
                        <th><?= __('admin.orders_wp.table.date', 'Data') ?></th>
                        <th><?= __('common.status', 'Status') ?></th>
                        <th><?= __('admin.orders_wp.table.customer', 'Cliente') ?></th>
                        <th><?= __('common.total', 'Total') ?></th>
                        <th class="text-end"><?= __('common.actions', 'Ações') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted"><?= __('admin.orders_wp.empty', 'Nenhum pedido encontrado.') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                            <?php
                            $id = (int) ($p['id'] ?? 0);
                            $created = (string) ($p['created_at'] ?? '');
                            $status = (string) ($p['status'] ?? '');
                            $num = (string) ($p['numero_pedido'] ?? '');
                            $orderNumberDisplay = trim((string) ($p['order_number_display'] ?? ''));
                            $src = strtolower(trim((string) ($p['source'] ?? $source)));
                            if (!in_array($src, ['br','red','us'], true)) $src = 'br';
                            $totalV = $p['order_total'] ?? 0;
                            $curr = (string) ($p['currency'] ?? '');
                            $email = (string) ($p['billing_email'] ?? '');
                            $fn = (string) ($p['billing_first_name'] ?? '');
                            $ln = (string) ($p['billing_last_name'] ?? '');
                            $cliente = trim($fn . ' ' . $ln);
                            if ($cliente === '') $cliente = $email;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold">#<?= htmlspecialchars($orderNumberDisplay !== '' ? $orderNumberDisplay : (string) $id) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($num) ?></div>
                                    <?php if ($orderNumberDisplay !== '' && (string) $id !== $orderNumberDisplay): ?>
                                        <div class="text-muted small">ID: <?= (int) $id ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-dark"><?= htmlspecialchars(strtoupper($src)) ?></span></td>
                                <td><?= $created !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($created))) : '-' ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($status) ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($cliente ?: '-') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($email) ?></div>
                                </td>
                                <td class="fw-semibold text-primary"><?= htmlspecialchars(wpFormatMoney($totalV, $curr)) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/pedidos-wp/detalhes/<?= $id ?>?<?= http_build_query(['source' => $src]) ?>">
                                        <i class="fas fa-eye"></i> <?= __('common.view', 'Ver') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php
                    $params = $_GET;
                    $current = (int) $page;
                    if ($current <= 0) $current = 1;
                    $start = max(1, $current - 1);
                    $end = min($totalPaginas, $current + 1);

                    $params['page'] = 1;
                    $urlFirst = '/admin/pedidos-wp?' . http_build_query($params);
                    $params['page'] = max(1, $current - 1);
                    $urlPrev = '/admin/pedidos-wp?' . http_build_query($params);
                    $params['page'] = min($totalPaginas, $current + 1);
                    $urlNext = '/admin/pedidos-wp?' . http_build_query($params);
                    $params['page'] = $totalPaginas;
                    $urlLast = '/admin/pedidos-wp?' . http_build_query($params);
                    ?>

                    <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($urlFirst) ?>">&laquo;</a></li>
                    <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($urlPrev) ?>">&lsaquo;</a></li>

                    <?php for ($i = $start; $i <= $end; $i++):
                        $params['page'] = $i;
                        $url = '/admin/pedidos-wp?' . http_build_query($params);
                        $active = ($i === $current) ? 'active' : '';
                    ?>
                        <li class="page-item <?= $active ?>"><a class="page-link" href="<?= htmlspecialchars($url) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>

                    <li class="page-item <?= $current >= $totalPaginas ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($urlNext) ?>">&rsaquo;</a></li>
                    <li class="page-item <?= $current >= $totalPaginas ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($urlLast) ?>">&raquo;</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
