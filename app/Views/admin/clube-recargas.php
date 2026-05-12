<?php ob_start(); ?>
<?php
$recargas = isset($recargas) && is_array($recargas) ? $recargas : [];
$turboRows = array_filter($recargas, fn($r) => strtolower(trim((string) ($r['tipo_recarga'] ?? 'normal'))) === 'turbo');
$normalRows = array_filter($recargas, fn($r) => strtolower(trim((string) ($r['tipo_recarga'] ?? 'normal'))) !== 'turbo');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Recargas Clube (Checkout rápido)</h1>
    </div>

    <!-- Totais Gerais -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total registros</div>
                    <div class="h4 mb-0"><?= number_format((int) ($stats['total_registros'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total (USD)</div>
                    <div class="h4 mb-0">$ <?= number_format((float) ($stats['total_usd'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Pagos/creditados</div>
                    <div class="h4 mb-0"><?= number_format((int) ($stats['total_pago_registros'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total pago (USD)</div>
                    <div class="h4 mb-0">$ <?= number_format((float) ($stats['total_pago_usd'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Totais por Tipo -->
    <div class="row mb-3">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body">
                    <div class="fw-semibold mb-2" style="color:#b45309;"><i class="fas fa-bolt me-1"></i> Turbo</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="small text-muted">Registros</div>
                            <div class="fw-bold"><?= (int) ($stats['turbo_registros'] ?? 0) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Pagos</div>
                            <div class="fw-bold"><?= (int) ($stats['turbo_pago_registros'] ?? 0) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Total USD</div>
                            <div class="fw-bold">$ <?= number_format((float) ($stats['turbo_usd'] ?? 0), 2, ',', '.') ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Pago USD</div>
                            <div class="fw-bold">$ <?= number_format((float) ($stats['turbo_pago_usd'] ?? 0), 2, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body">
                    <div class="fw-semibold mb-2" style="color:#065f46;"><i class="fas fa-check-circle me-1"></i> Normal</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="small text-muted">Registros</div>
                            <div class="fw-bold"><?= (int) ($stats['normal_registros'] ?? 0) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Pagos</div>
                            <div class="fw-bold"><?= (int) ($stats['normal_pago_registros'] ?? 0) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Total USD</div>
                            <div class="fw-bold">$ <?= number_format((float) ($stats['normal_usd'] ?? 0), 2, ',', '.') ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Pago USD</div>
                            <div class="fw-bold">$ <?= number_format((float) ($stats['normal_pago_usd'] ?? 0), 2, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Teto de captação (BRL)</div>
                    <div class="h4 mb-0">R$ <?= number_format((float) ($stats['cap_brl'] ?? 150000), 2, ',', '.') ?></div>
                    <div class="small text-muted mt-1">Captado pago: R$ <?= number_format((float) ($stats['total_pago_brl'] ?? 0), 2, ',', '.') ?></div>
                    <?php if (!empty($stats['cap_reached'])): ?>
                        <div class="small fw-semibold mt-1" style="color:#b42318;">Limite atingido</div>
                    <?php else: ?>
                        <div class="small fw-semibold mt-1" style="color:#027a48;">Aberto</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Abas: Mobile dropdown + Desktop tabs -->
    <div class="d-md-none mb-3">
        <select class="form-select" onchange="switchClubeTab(this.value)">
            <option value="pane-todos" selected>Todos (<?= count($recargas) ?>)</option>
            <option value="pane-turbo">Turbo (<?= count($turboRows) ?>)</option>
            <option value="pane-normal">Normal (<?= count($normalRows) ?>)</option>
        </select>
    </div>
    <ul class="nav nav-tabs mb-0 d-none d-md-flex" id="clubeRecargasTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-todos" data-bs-toggle="tab" data-bs-target="#pane-todos" type="button" role="tab">
                Todos <span class="badge bg-secondary ms-1"><?= count($recargas) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-turbo" data-bs-toggle="tab" data-bs-target="#pane-turbo" type="button" role="tab">
                Turbo <span class="badge bg-warning text-dark ms-1"><?= count($turboRows) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-normal" data-bs-toggle="tab" data-bs-target="#pane-normal" type="button" role="tab">
                Normal <span class="badge bg-info ms-1"><?= count($normalRows) ?></span>
            </button>
        </li>
    </ul>
    <script>
    function switchClubeTab(tabId) {
        var btn = document.querySelector('[data-bs-target="#' + tabId + '"]');
        if (btn) btn.click();
    }
    </script>

    <div class="tab-content">

<?php
function renderRecargasTable(array $rows, string $emptyMsg = 'Nenhuma recarga encontrada.'): void {
    echo '<div class="card border-0 shadow-sm border-top-0" style="border-top-left-radius:0;border-top-right-radius:0;">';
    echo '<div class="card-body">';
    // Desktop: Table
    echo '<div class="table-responsive d-none d-md-block">';
    echo '<table class="table table-hover align-middle mb-0">';
    echo '<thead><tr>';
    echo '<th>ID</th><th>Usuário</th><th>Pagador</th><th>Tipo</th><th>Gateway</th>';
    echo '<th>Valor USD</th><th>Valor BRL</th><th>Status</th><th>Criado</th>';
    echo '</tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="9" class="text-muted">' . htmlspecialchars($emptyMsg) . '</td></tr>';
    } else {
        foreach ($rows as $r) {
            $status = strtolower(trim((string) ($r['status'] ?? 'pending')));
            $badge = 'secondary';
            if (in_array($status, ['paid', 'approved', 'credited'], true)) $badge = 'success';
            elseif (in_array($status, ['rejected', 'failed', 'canceled'], true)) $badge = 'danger';
            elseif ($status === 'pending') $badge = 'warning';

            $tipoRec = strtolower(trim((string) ($r['tipo_recarga'] ?? 'normal')));
            $tipoBadge = ($tipoRec === 'turbo') ? 'warning' : 'info';
            $tipoLabel = ($tipoRec === 'turbo') ? 'Turbo' : 'Normal';

            $gw = strtolower(trim((string) ($r['gateway'] ?? '')));
            $gwLabel = $gw === 'cambioreal' ? 'Câmbio Real' : ($gw === 'stripe' ? 'Stripe' : ($gw !== '' ? ucfirst($gw) : 'N/A'));

            echo '<tr>';
            echo '<td>#' . (int) ($r['id'] ?? 0) . '</td>';
            echo '<td>';
            $uNome  = htmlspecialchars((string)($r['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $uEmail = htmlspecialchars((string)($r['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8');
            $uSuite = (int)($r['usuario_suite'] ?? 0);
            $uId    = (int)($r['usuario_id'] ?? 0);
            if ($uNome !== '') {
                echo '<a href="/admin/usuarios/detalhes/' . $uId . '" class="fw-semibold text-decoration-none">' . $uNome . '</a>';
                if ($uSuite > 0) echo '<div class="text-muted small">Suite ' . $uSuite . '</div>';
                if ($uEmail !== '') echo '<div class="text-muted small">' . $uEmail . '</div>';
            } else {
                echo '<a href="/admin/usuarios/detalhes/' . $uId . '" class="text-muted">#' . $uId . '</a>';
            }
            echo '</td>';
            echo '<td><div class="fw-semibold">' . htmlspecialchars((string) ($r['pagador_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="text-muted small">' . htmlspecialchars((string) ($r['pagador_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div></td>';
            echo '<td><span class="badge bg-' . $tipoBadge . '">' . $tipoLabel . '</span></td>';
            echo '<td>' . htmlspecialchars($gwLabel) . '</td>';
            echo '<td>$ ' . number_format((float) ($r['valor'] ?? 0), 2, ',', '.') . '</td>';
            echo '<td>R$ ' . number_format((float) ($r['valor_brl'] ?? 0), 2, ',', '.') . '</td>';
            echo '<td><span class="badge bg-' . $badge . '">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span></td>';
            echo '<td class="text-muted small" style="white-space:nowrap;">' . (!empty($r['created_at']) ? date('d/m/Y', strtotime((string) $r['created_at'])) : '-') . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';

    // Mobile: Cards
    echo '<div class="d-md-none">';
    if (empty($rows)) {
        echo '<div class="text-muted small py-3">' . htmlspecialchars($emptyMsg) . '</div>';
    } else {
        foreach ($rows as $r) {
            $status = strtolower(trim((string) ($r['status'] ?? 'pending')));
            $badge = 'secondary';
            if (in_array($status, ['paid', 'approved', 'credited'], true)) $badge = 'success';
            elseif (in_array($status, ['rejected', 'failed', 'canceled'], true)) $badge = 'danger';
            elseif ($status === 'pending') $badge = 'warning';

            $tipoRec = strtolower(trim((string) ($r['tipo_recarga'] ?? 'normal')));
            $tipoBadge = ($tipoRec === 'turbo') ? 'warning' : 'info';
            $tipoLabel = ($tipoRec === 'turbo') ? 'Turbo' : 'Normal';

            $uNome  = htmlspecialchars((string)($r['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $uEmail = htmlspecialchars((string)($r['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8');
            $uSuite = (int)($r['usuario_suite'] ?? 0);

            echo '<div class="border-bottom py-2">';
            echo '<div class="d-flex justify-content-between align-items-start">';
            echo '<div style="min-width:0;flex:1;">';
            echo '<div class="fw-semibold small" style="word-break:break-word;">' . ($uNome !== '' ? $uNome : '#' . (int)($r['usuario_id'] ?? 0)) . '</div>';
            if ($uSuite > 0) echo '<span class="text-muted" style="font-size:11px;">Suite ' . $uSuite . '</span> ';
            if ($uEmail !== '') echo '<div class="text-muted" style="font-size:11px;word-break:break-all;">' . $uEmail . '</div>';
            echo '</div>';
            echo '<span class="badge bg-' . $badge . ' ms-2">' . $status . '</span>';
            echo '</div>';
            echo '<div class="d-flex flex-wrap gap-1 mt-1" style="font-size:11px;">';
            echo '<span class="text-muted">#' . (int)($r['id'] ?? 0) . '</span>';
            echo '<span class="badge bg-' . $tipoBadge . '">' . $tipoLabel . '</span>';
            echo '<span class="fw-bold">$ ' . number_format((float)($r['valor'] ?? 0), 2, ',', '.') . '</span>';
            if ((float)($r['valor_brl'] ?? 0) > 0) echo '<span class="text-muted">R$ ' . number_format((float)($r['valor_brl'] ?? 0), 2, ',', '.') . '</span>';
            echo '</div>';
            echo '</div>';
        }
    }
    echo '</div>';

    echo '</div></div>';
}
?>

        <!-- Tab: Todos -->
        <div class="tab-pane fade show active" id="pane-todos" role="tabpanel">
            <?php renderRecargasTable($recargas); ?>
        </div>

        <!-- Tab: Turbo -->
        <div class="tab-pane fade" id="pane-turbo" role="tabpanel">
            <?php renderRecargasTable($turboRows, 'Nenhuma recarga Turbo encontrada.'); ?>
        </div>

        <!-- Tab: Normal -->
        <div class="tab-pane fade" id="pane-normal" role="tabpanel">
            <?php renderRecargasTable($normalRows, 'Nenhuma recarga Normal encontrada.'); ?>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
