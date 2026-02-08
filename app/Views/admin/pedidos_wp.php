<?php
$busca = $busca ?? '';
$page = $page ?? 1;
$limite = $limite ?? 50;
$total = $total ?? 0;
$pedidos = is_array($pedidos ?? null) ? $pedidos : [];
$erro = (string) ($erro ?? '');

$totalPaginas = $limite > 0 ? (int) ceil($total / $limite) : 1;
if ($totalPaginas <= 0) $totalPaginas = 1;

function wpFormatMoney($v, $currency) {
    $currency = strtoupper(trim((string) $currency));
    if ($currency === '') $currency = 'BRL';
    $v = is_numeric($v) ? (float) $v : 0.0;
    $fmt = number_format($v, 2, ',', '.');
    if ($currency === 'BRL') return 'R$ ' . $fmt;
    if ($currency === 'USD') return 'US$ ' . $fmt;
    return $currency . ' ' . $fmt;
}

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Pedidos (WordPress) (<?= (int) $total ?>)</h1>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger">Erro ao carregar pedidos do WordPress: <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<form method="GET" class="row g-3 mb-4">
    <div class="col-md-6">
        <input type="text" class="form-control" name="busca" placeholder="Buscar por ID, número, nome ou email..." value="<?= htmlspecialchars($busca) ?>">
    </div>
    <div class="col-md-2">
        <select class="form-select" name="limit">
            <?php foreach ([25,50,100,200] as $l): ?>
                <option value="<?= $l ?>" <?= ((int)$limite === (int)$l) ? 'selected' : '' ?>><?= $l ?>/página</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Filtrar</button>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Nenhum pedido encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                            <?php
                            $id = (int) ($p['id'] ?? 0);
                            $created = (string) ($p['created_at'] ?? '');
                            $status = (string) ($p['status'] ?? '');
                            $num = (string) ($p['numero_pedido'] ?? '');
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
                                    <div class="fw-semibold">#<?= $id ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($num) ?></div>
                                </td>
                                <td><?= $created !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($created))) : '-' ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($status) ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($cliente ?: '-') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($email) ?></div>
                                </td>
                                <td class="fw-semibold text-primary"><?= htmlspecialchars(wpFormatMoney($totalV, $curr)) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/pedidos-wp/detalhes/<?= $id ?>">
                                        <i class="fas fa-eye"></i> Ver
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
                    for ($i = 1; $i <= $totalPaginas; $i++):
                        $params['page'] = $i;
                        $url = '/admin/pedidos-wp?' . http_build_query($params);
                        $active = ($i === (int) $page) ? 'active' : '';
                    ?>
                        <li class="page-item <?= $active ?>"><a class="page-link" href="<?= htmlspecialchars($url) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
