<?php
$stats = is_array($stats ?? null) ? $stats : ['total' => 0, 'sp_capital_total' => 0, 'por_uf' => [], 'por_cidade' => [], 'por_bairro' => []];

$view = strtolower(trim((string) ($view ?? ($_GET['view'] ?? 'stats'))));
if (!in_array($view, ['stats', 'missing', 'autofill'], true)) $view = 'stats';

$page = (int) ($page ?? ($_GET['page'] ?? 1));
if ($page <= 0) $page = 1;
$limite = (int) ($limite ?? ($_GET['limit'] ?? 50));
if ($limite <= 0) $limite = 50;
if ($limite > 200) $limite = 200;

$missingOrders = is_array($missingOrders ?? null) ? $missingOrders : [];
$missingTotal = (int) ($missingTotal ?? 0);
$missingPages = $limite > 0 ? (int) ceil($missingTotal / $limite) : 1;
if ($missingPages <= 0) $missingPages = 1;

$autofillOrders = is_array($autofillOrders ?? null) ? $autofillOrders : [];
$autofillTotal = (int) ($autofillTotal ?? 0);
$autofillPages = $limite > 0 ? (int) ceil($autofillTotal / $limite) : 1;
if ($autofillPages <= 0) $autofillPages = 1;
$erro = (string) ($erro ?? '');

$source = strtolower(trim((string) ($source ?? ($_GET['source'] ?? 'br'))));
$allowedSources = ['all', 'br', 'red', 'us'];
if (!in_array($source, $allowedSources, true)) $source = 'br';

$start = trim((string) ($startRaw ?? ($_GET['start'] ?? '')));
$end = trim((string) ($endRaw ?? ($_GET['end'] ?? '')));
$status = trim((string) ($statusRaw ?? ($_GET['status'] ?? '')));
$bairroCity = trim((string) ($bairroCity ?? ($_GET['bairro_city'] ?? '')));
$hideEmpty = (string) ($hideEmpty ?? ($_GET['hide_empty'] ?? '')) === '1';
$useBairroAutofill = (string) ($useBairroAutofill ?? ($_GET['use_bairro_autofill'] ?? '1')) === '1';
$debugBairro = (string) ($debugBairro ?? ($_GET['debug_bairro'] ?? '')) === '1';
$debugBairroInfo = $debugBairroInfo ?? null;
$missingField = strtolower(trim((string) ($missingField ?? ($_GET['missing_field'] ?? 'any'))));
if (!in_array($missingField, ['any', 'uf', 'cidade', 'bairro'], true)) $missingField = 'any';
$top = (int) ($top ?? ($_GET['top'] ?? 20));
if ($top <= 0) $top = 20;
if ($top > 200) $top = 200;

$knownStatuses = [
    '' => 'Todos os status',
    'wc-enviado' => 'wc-enviado',
    'wc-completed' => 'wc-completed',
    'wc-cancelled' => 'wc-cancelled',
    'wc-refunded' => 'wc-refunded',
    'wc-comprado' => 'wc-comprado',
    'wc-fatura-paga' => 'wc-fatura-paga',
    'wc-invoice-liberado' => 'wc-invoice-liberado',
    'wc-invoice-fechado' => 'wc-invoice-fechado',
    'wc-invoice-ct' => 'wc-invoice-ct',
];

$total = (int) ($stats['total'] ?? 0);
$spTotal = (int) ($stats['sp_capital_total'] ?? 0);
$spPct = $total > 0 ? round(($spTotal / $total) * 100, 2) : 0.0;

function fmtPct($v) {
    $v = is_numeric($v) ? (float) $v : 0.0;
    return number_format($v, 2, ',', '.') . '%';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="page-title">Estatísticas (WP)</h1>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger">Erro ao carregar estatísticas: <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<?php if ($debugBairro): ?>
    <?php
    $dbgParams = $_GET;
    unset($dbgParams['debug_bairro']);
    $dbgOffUrl = '/admin/pedidos-wp/estatisticas?' . http_build_query($dbgParams);
    ?>
    <div class="alert alert-warning">
        <div class="d-flex justify-content-between align-items-center">
            <strong>Debug bairro</strong>
            <a class="btn btn-sm btn-outline-dark" href="<?= htmlspecialchars($dbgOffUrl) ?>">Desligar</a>
        </div>
        <pre class="mb-0 mt-2" style="max-height: 360px; overflow: auto;"><?php
            $x = $debugBairroInfo;
            if (!is_array($x)) $x = ['debugBairroInfo' => $x];
            echo htmlspecialchars(json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        ?></pre>
    </div>
<?php endif; ?>

<div class="mb-3">
    <?php
    $presetBase = [
        'wc-enviado' => 'Etiqueta gerada',
        'wc-completed' => 'Completed',
        'wc-comprado' => 'Comprado',
        'wc-fatura-paga' => 'Fatura paga',
        'wc-cancelled' => 'Cancelados',
        'wc-refunded' => 'Reembolsados',
    ];
    $baseParams = $_GET;
    unset($baseParams['status']);
    ?>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($presetBase as $st => $label): ?>
            <?php
            $p = $baseParams;
            $p['status'] = $st;
            $url = '/admin/pedidos-wp/estatisticas?' . http_build_query($p);
            $active = strtolower(trim($status)) === strtolower(trim($st));
            ?>
            <a class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
        <?php
        $pAll = $baseParams;
        $urlAll = '/admin/pedidos-wp/estatisticas?' . http_build_query($pAll);
        ?>
        <a class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= htmlspecialchars($urlAll) ?>">Todos os status</a>
    </div>
</div>

<?php
$tabParams = $_GET;
unset($tabParams['view'], $tabParams['page']);
$urlStats = '/admin/pedidos-wp/estatisticas?' . http_build_query(array_merge($tabParams, ['view' => 'stats']));
$urlMissing = '/admin/pedidos-wp/estatisticas?' . http_build_query(array_merge($tabParams, ['view' => 'missing']));
$urlAutofill = '/admin/pedidos-wp/estatisticas?' . http_build_query(array_merge($tabParams, ['view' => 'autofill']));
?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $view === 'stats' ? 'active' : '' ?>" href="<?= htmlspecialchars($urlStats) ?>">Estatísticas</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'missing' ? 'active' : '' ?>" href="<?= htmlspecialchars($urlMissing) ?>">Pedidos com campos vazios<?= $missingTotal > 0 ? ' (' . (int) $missingTotal . ')' : '' ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'autofill' ? 'active' : '' ?>" href="<?= htmlspecialchars($urlAutofill) ?>">Pedidos preenchidos automaticamente<?= $autofillTotal > 0 ? ' (' . (int) $autofillTotal . ')' : '' ?></a>
    </li>
</ul>

<form method="GET" class="row g-3 mb-4">
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
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
        <?php $isMulti = strpos($status, ',') !== false; ?>
        <?php if ($isMulti): ?>
            <input type="text" class="form-control" name="status" value="<?= htmlspecialchars($status) ?>">
            <div class="text-muted small mt-1">Múltiplos status ativos (separados por vírgula). Para usar o select, limpe e escolha um status.</div>
        <?php else: ?>
            <select class="form-select" name="status">
                <?php foreach ($knownStatuses as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $status === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

    <?php if ($view === 'stats'): ?>
        <div class="col-md-4">
            <label class="form-label">Cidade (para bairros)</label>
            <input type="text" class="form-control" name="bairro_city" value="<?= htmlspecialchars($bairroCity) ?>" placeholder="Ex: Sao paulo">
            <div class="text-muted small mt-1">Opcional. Afeta apenas o card "Por Bairro".</div>
        </div>
    <?php endif; ?>
    </div>

    <?php if ($view === 'missing'): ?>
        <div class="col-md-3">
            <label class="form-label">Campo faltando</label>
            <select class="form-select" name="missing_field">
                <option value="any" <?= $missingField === 'any' ? 'selected' : '' ?>>Qualquer (UF, Cidade ou Bairro)</option>
                <option value="uf" <?= $missingField === 'uf' ? 'selected' : '' ?>>Somente UF</option>
                <option value="cidade" <?= $missingField === 'cidade' ? 'selected' : '' ?>>Somente Cidade</option>
                <option value="bairro" <?= $missingField === 'bairro' ? 'selected' : '' ?>>Somente Bairro</option>
            </select>
        </div>
    <?php endif; ?>

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

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="useBairroAutofill" name="use_bairro_autofill" <?= $useBairroAutofill ? 'checked' : '' ?>>
            <label class="form-check-label" for="useBairroAutofill">
                Usar bairro auto-preenchido (CEP)
            </label>
        </div>
    </div>

    <div class="col-md-12">
        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="/admin/pedidos-wp/estatisticas" class="btn btn-outline-secondary">Limpar</a>
    </div>
</form>

<?php if ($view === 'autofill'): ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php
        $p = $_GET;
        $p['view'] = 'autofill';
        $postUrl = '/admin/pedidos-wp/autofill-bairro?' . http_build_query($p);
        ?>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="autofillBairro()">
            <i class="fas fa-magic"></i> Preencher bairro (interno) via CEP
        </button>
        <span class="text-muted small align-self-center">Não altera nada no WordPress. Apenas grava o preenchimento no histórico interno.</span>
    </div>

    <div class="card">
        <div class="card-header"><strong>Histórico de preenchimento automático (bairro)</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Origem</th>
                            <th>Pedido WP</th>
                            <th>Data pedido</th>
                            <th>Status</th>
                            <th>CEP</th>
                            <th>Bairro (novo)</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($autofillOrders)): ?>
                            <tr><td colspan="8" class="text-center text-muted">Nenhum preenchimento automático encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($autofillOrders as $r): ?>
                                <?php
                                $id = (int) ($r['id'] ?? 0);
                                $src = strtolower(trim((string) ($r['source'] ?? 'br')));
                                $wpId = (int) ($r['wp_order_id'] ?? 0);
                                $created = (string) ($r['wp_created_at'] ?? '');
                                $st = (string) ($r['wp_status'] ?? '');
                                $cep = (string) ($r['cep'] ?? '');
                                $new = (string) ($r['new_value'] ?? '');
                                if (!in_array($src, ['br','red','us'], true)) $src = 'br';
                                ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int) $id ?></td>
                                    <td><span class="badge bg-dark"><?= htmlspecialchars(strtoupper($src)) ?></span></td>
                                    <td class="fw-semibold">#<?= (int) $wpId ?></td>
                                    <td><?= $created !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($created))) : '-' ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($st) ?></span></td>
                                    <td><?= htmlspecialchars($cep !== '' ? $cep : '-') ?></td>
                                    <td><?= htmlspecialchars($new !== '' ? $new : '-') ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="/admin/pedidos-wp/detalhes/<?= (int) $wpId ?>?<?= http_build_query(['source' => $src]) ?>">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($autofillPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php
                        $params = $_GET;
                        for ($i = 1; $i <= $autofillPages; $i++):
                            $params['page'] = $i;
                            $url = '/admin/pedidos-wp/estatisticas?' . http_build_query($params);
                            $active = ($i === (int) $page) ? 'active' : '';
                        ?>
                            <li class="page-item <?= $active ?>"><a class="page-link" href="<?= htmlspecialchars($url) ?>"><?= (int) $i ?></a></li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function autofillBairro() {
            if (!confirm('Executar preenchimento interno de bairro via CEP para pedidos com bairro vazio?')) return;
            const url = <?= json_encode($postUrl) ?>;
            fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success) {
                        const lines = [];
                        lines.push('Processados: ' + (data.processed ?? 0));
                        lines.push('Tentados: ' + (data.attempted ?? 0));
                        lines.push('Preenchidos: ' + (data.filled ?? 0));
                        lines.push('Ignorados: ' + (data.skipped ?? 0));
                        lines.push('  - Sem CEP: ' + (data.skipped_no_cep ?? 0));
                        lines.push('  - Já preenchidos: ' + (data.skipped_already_filled ?? 0));
                        lines.push('  - Bairro não encontrado: ' + (data.skipped_bairro_not_found ?? 0));
                        lines.push('  - Fora do Brasil: ' + (data.skipped_outside_br ?? 0));
                        lines.push('  - Sem endereço p/ fallback: ' + (data.skipped_no_address ?? 0));
                        lines.push('  - Endereço ambíguo: ' + (data.skipped_ambiguous_address ?? 0));
                        lines.push('Fallback por endereço usado: ' + (data.fallback_used ?? 0));
                        lines.push('Erros: ' + (data.errors ?? 0));
                        alert(lines.join('\n'));
                        location.reload();
                    } else {
                        alert('Falha: ' + (data.error ?? 'Erro desconhecido'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Erro ao executar');
                });
        }
    </script>

    <?php return; ?>
<?php endif; ?>

<?php if ($view === 'missing'): ?>
    <div class="card">
        <div class="card-header"><strong>Pedidos com UF/Cidade/Bairro vazios (após fallback shipping/billing)</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Origem</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>UF</th>
                            <th>Cidade</th>
                            <th>Bairro</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($missingOrders)): ?>
                            <tr><td colspan="8" class="text-center text-muted">Nenhum pedido com campos vazios encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($missingOrders as $r): ?>
                                <?php
                                $id = (int) ($r['id'] ?? 0);
                                $created = (string) ($r['created_at'] ?? '');
                                $st = (string) ($r['status'] ?? '');
                                $src = strtolower(trim((string) ($r['source'] ?? 'br')));
                                $uf = (string) ($r['ship_state'] ?? '');
                                $cid = (string) ($r['ship_city'] ?? '');
                                $bai = (string) ($r['ship_neighborhood'] ?? '');
                                if (!in_array($src, ['br','red','us'], true)) $src = 'br';
                                ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int) $id ?></td>
                                    <td><span class="badge bg-dark"><?= htmlspecialchars(strtoupper($src)) ?></span></td>
                                    <td><?= $created !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($created))) : '-' ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($st) ?></span></td>
                                    <td><?= htmlspecialchars($uf !== '' ? $uf : '(vazio)') ?></td>
                                    <td><?= htmlspecialchars($cid !== '' ? $cid : '(vazio)') ?></td>
                                    <td><?= htmlspecialchars($bai !== '' ? $bai : '(vazio)') ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="/admin/pedidos-wp/detalhes/<?= (int) $id ?>?<?= http_build_query(['source' => $src]) ?>">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($missingPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php
                        $params = $_GET;
                        for ($i = 1; $i <= $missingPages; $i++):
                            $params['page'] = $i;
                            $url = '/admin/pedidos-wp/estatisticas?' . http_build_query($params);
                            $active = ($i === (int) $page) ? 'active' : '';
                        ?>
                            <li class="page-item <?= $active ?>"><a class="page-link" href="<?= htmlspecialchars($url) ?>"><?= (int) $i ?></a></li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php return; ?>
<?php endif; ?>

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

<?php $emptyBairroDiag = is_array($emptyBairroDiag ?? null) ? $emptyBairroDiag : []; ?>
<?php if (!empty($emptyBairroDiag)): ?>
    <div class="card mt-3">
        <div class="card-header"><strong>Diagnóstico dos vazios (bairro)</strong></div>
        <div class="card-body">
            <div class="text-muted small mb-2">
                Lista de até 100 pedidos que ainda estão com bairro vazio no WordPress, mostrando o último resultado do autofill interno.
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Origem</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>UF</th>
                            <th>Cidade</th>
                            <th>CEP</th>
                            <th>Logradouro</th>
                            <th>Último autofill</th>
                            <th>Motivo</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emptyBairroDiag as $r): ?>
                            <?php
                            $id = (int) ($r['id'] ?? 0);
                            $src = strtolower(trim((string) ($r['source'] ?? 'br')));
                            if (!in_array($src, ['br','red','us'], true)) $src = 'br';
                            $created = (string) ($r['created_at'] ?? '');
                            $st = (string) ($r['status'] ?? '');
                            $uf = (string) ($r['ship_state'] ?? '');
                            $cid = (string) ($r['ship_city'] ?? '');
                            $cep = (string) ($r['ship_postcode'] ?? '');
                            $addr1 = (string) ($r['ship_address_1'] ?? '');
                            $afNew = (string) ($r['autofill_new_value'] ?? '');
                            $afCep = (string) ($r['autofill_cep'] ?? '');
                            $afErr = (string) ($r['autofill_error'] ?? '');
                            $afAt = (string) ($r['autofill_updated_at'] ?? '');
                            ?>
                            <tr>
                                <td class="fw-semibold">#<?= (int) $id ?></td>
                                <td><span class="badge bg-dark"><?= htmlspecialchars(strtoupper($src)) ?></span></td>
                                <td><?= $created !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($created))) : '-' ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($st) ?></span></td>
                                <td><?= htmlspecialchars($uf !== '' ? $uf : '-') ?></td>
                                <td><?= htmlspecialchars($cid !== '' ? $cid : '-') ?></td>
                                <td><?= htmlspecialchars($cep !== '' ? $cep : '-') ?></td>
                                <td style="max-width: 280px;" class="text-truncate" title="<?= htmlspecialchars($addr1) ?>"><?= htmlspecialchars($addr1 !== '' ? $addr1 : '-') ?></td>
                                <td>
                                    <?php if ($afAt !== ''): ?>
                                        <span class="text-muted small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($afAt))) ?></span>
                                        <div class="small">CEP: <?= htmlspecialchars($afCep !== '' ? $afCep : '-') ?></div>
                                        <div class="small">Novo: <?= htmlspecialchars($afNew !== '' ? $afNew : '-') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 280px;" class="text-truncate" title="<?= htmlspecialchars($afErr) ?>"><?= htmlspecialchars($afErr !== '' ? $afErr : '-') ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/pedidos-wp/detalhes/<?= (int) $id ?>?<?= http_build_query(['source' => $src]) ?>">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
