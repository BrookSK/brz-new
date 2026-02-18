<?php
$stats = is_array($stats ?? null) ? $stats : ['total' => 0, 'sp_capital_total' => 0, 'por_uf' => [], 'por_cidade' => [], 'por_bairro' => []];
$erro = (string) ($erro ?? '');

$source = strtolower(trim((string) ($source ?? ($_GET['source'] ?? 'br'))));
$allowedSources = ['all', 'br', 'red', 'us'];
if (!in_array($source, $allowedSources, true)) $source = 'br';

$start = trim((string) ($startRaw ?? ($_GET['start'] ?? '')));
$end = trim((string) ($endRaw ?? ($_GET['end'] ?? '')));
$status = trim((string) ($statusRaw ?? ($_GET['status'] ?? '')));
$hideEmpty = (string) ($hideEmpty ?? ($_GET['hide_empty'] ?? '')) === '1';
$top = (int) ($top ?? ($_GET['top'] ?? 20));
if ($top <= 0) $top = 20;
if ($top > 200) $top = 200;

$total = (int) ($stats['total'] ?? 0);
$spTotal = (int) ($stats['sp_capital_total'] ?? 0);
$spPct = $total > 0 ? round(($spTotal / $total) * 100, 2) : 0.0;

function fmtPct($v) {
    $v = is_numeric($v) ? (float) $v : 0.0;
    return number_format($v, 2, ',', '.') . '%';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Estatísticas (WP)</h1>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger">Erro ao carregar estatísticas: <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<form method="GET" class="row g-3 mb-4">
    <div class="col-md-3">
        <label class="form-label">Origem</label>
        <select class="form-select" name="source">
            <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>Todas</option>
            <option value="br" <?= $source === 'br' ? 'selected' : '' ?>>BR</option>
            <option value="red" <?= $source === 'red' ? 'selected' : '' ?>>RED</option>
            <option value="us" <?= $source === 'us' ? 'selected' : '' ?>>US</option>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Início</label>
        <input type="date" class="form-control" name="start" value="<?= htmlspecialchars($start) ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Fim</label>
        <input type="date" class="form-control" name="end" value="<?= htmlspecialchars($end) ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Status (WooCommerce)</label>
        <input type="text" class="form-control" name="status" value="<?= htmlspecialchars($status) ?>" placeholder="Ex: wc-completed,wc-processing">
    </div>

    <div class="col-md-2">
        <label class="form-label">Top</label>
        <input type="number" class="form-control" name="top" min="1" max="200" value="<?= (int) $top ?>">
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="hideEmpty" name="hide_empty" <?= $hideEmpty ? 'checked' : '' ?>>
            <label class="form-check-label" for="hideEmpty">
                Ocultar (vazio)
            </label>
        </div>
    </div>

    <div class="col-md-12">
        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="/admin/pedidos-wp/estatisticas" class="btn btn-outline-secondary">Limpar</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Total de pedidos (WP)</div>
                <div class="fs-3 fw-semibold"><?= (int) $total ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">São Paulo (Capital)</div>
                <div class="fs-3 fw-semibold"><?= (int) $spTotal ?></div>
                <div class="text-muted"><?= htmlspecialchars(fmtPct($spPct)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>Por UF</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>UF</th>
                                <th class="text-end">Qtd</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rows = is_array($stats['por_uf'] ?? null) ? $stats['por_uf'] : []; ?>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Sem dados</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $label = (string) ($r['label'] ?? '');
                                    $count = (int) ($r['total'] ?? 0);
                                    $pct = isset($r['pct']) ? (float) $r['pct'] : ($total > 0 ? round(($count / $total) * 100, 2) : 0.0);
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($label) ?></td>
                                        <td class="text-end"><?= (int) $count ?></td>
                                        <td class="text-end"><?= htmlspecialchars(fmtPct($pct)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>Por Cidade (Top <?= (int) $top ?>)</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Cidade</th>
                                <th class="text-end">Qtd</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rows = is_array($stats['por_cidade'] ?? null) ? $stats['por_cidade'] : []; ?>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Sem dados</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $label = (string) ($r['label'] ?? '');
                                    $count = (int) ($r['total'] ?? 0);
                                    $pct = isset($r['pct']) ? (float) $r['pct'] : ($total > 0 ? round(($count / $total) * 100, 2) : 0.0);
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($label) ?></td>
                                        <td class="text-end"><?= (int) $count ?></td>
                                        <td class="text-end"><?= htmlspecialchars(fmtPct($pct)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>Por Bairro (Top <?= (int) $top ?>)</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Bairro</th>
                                <th class="text-end">Qtd</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rows = is_array($stats['por_bairro'] ?? null) ? $stats['por_bairro'] : []; ?>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Sem dados</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $label = (string) ($r['label'] ?? '');
                                    $count = (int) ($r['total'] ?? 0);
                                    $pct = isset($r['pct']) ? (float) $r['pct'] : ($total > 0 ? round(($count / $total) * 100, 2) : 0.0);
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($label) ?></td>
                                        <td class="text-end"><?= (int) $count ?></td>
                                        <td class="text-end"><?= htmlspecialchars(fmtPct($pct)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small mt-2">
                    Obs: bairro depende do preenchimento no WooCommerce (meta <code>_shipping_neighborhood</code>/<code>_shipping_bairro</code>).
                </div>
            </div>
        </div>
    </div>
</div>
