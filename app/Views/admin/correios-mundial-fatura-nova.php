<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Correios Mundial (PACKET) - Nova Fatura (CN38)</h1>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial/faturas">Voltar</a>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>

    <?php
        $balance = isset($balance) && is_array($balance) ? $balance : [];
        $balanceOk = !empty($balance['success']);
        $currentBalance = (int) ($balance['currentBalance'] ?? 0);
    ?>

    <div class="row mb-3">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Saldo atual</div>
                    <div class="h4 mb-0"><?= $balanceOk ? (int) $currentBalance : '-' ?></div>
                    <?php if (!$balanceOk): ?>
                        <div class="small text-danger mt-1"><?= htmlspecialchars((string) ($balance['error'] ?? 'Falha ao consultar saldo.')) ?></div>
                    <?php else: ?>
                        <div class="small text-muted mt-1">Precisa de saldo para gerar a fatura.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong>Selecione os containers (máx. 5000 trackings por fatura)</strong></div>
        <div class="card-body">
            <form method="post" action="/admin/correios-mundial/faturas/criar" onsubmit="return confirm('Gerar fatura CN38? Esta operação é irreversível e pode acarretar custos.');">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th></th>
                                <th>ID</th>
                                <th>Remessa</th>
                                <th>Unit Code</th>
                                <th>Trackings</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $containers = isset($containers) && is_array($containers) ? $containers : []; ?>
                            <?php if (empty($containers)): ?>
                                <tr><td colspan="6" class="text-muted">Nenhum container disponível para faturamento.</td></tr>
                            <?php else: ?>
                                <?php foreach ($containers as $c): ?>
                                    <?php $cid = (int) ($c['id'] ?? 0); ?>
                                    <?php $unitCode = (string) ($c['unit_code'] ?? ''); ?>
                                    <?php $dispatchNumber = (string) ($c['dispatch_number'] ?? ''); ?>
                                    <?php $tc = (int) ($c['tracking_count'] ?? 0); ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="containerIds[]" value="<?= $cid ?>" data-tracking-count="<?= $tc ?>" <?= (!$balanceOk || $currentBalance <= 0) ? 'disabled' : '' ?> />
                                        </td>
                                        <td>#<?= $cid ?></td>
                                        <td><?= htmlspecialchars($dispatchNumber) ?></td>
                                        <td><?= htmlspecialchars($unitCode) ?></td>
                                        <td><?= $tc ?></td>
                                        <td><?= !empty($c['created_at']) ? date('d/m/Y H:i', strtotime((string) $c['created_at'])) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6">
                                    <div class="d-flex justify-content-between">
                                        <div class="text-muted">
                                            Selecionados: <span id="cn38_selected_units">0</span> containers | <span id="cn38_selected_trackings">0</span> trackings
                                        </div>
                                        <div>
                                            <button type="submit" class="btn btn-primary" id="cn38_submit" <?= (!$balanceOk || $currentBalance <= 0) ? 'disabled' : '' ?>>Gerar fatura</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    function recalc() {
        var boxes = document.querySelectorAll('input[type="checkbox"][name="containerIds[]"]');
        var units = 0;
        var trackings = 0;
        boxes.forEach(function(b) {
            if (b.checked) {
                units++;
                var tc = parseInt(b.getAttribute('data-tracking-count') || '0', 10);
                if (!isNaN(tc)) trackings += tc;
            }
        });

        var uEl = document.getElementById('cn38_selected_units');
        var tEl = document.getElementById('cn38_selected_trackings');
        if (uEl) uEl.textContent = String(units);
        if (tEl) tEl.textContent = String(trackings);

        var btn = document.getElementById('cn38_submit');
        if (btn) {
            btn.disabled = (units <= 0 || trackings <= 0 || trackings > 5000);
        }
    }

    document.addEventListener('change', function(ev) {
        var t = ev.target;
        if (t && t.matches && t.matches('input[type="checkbox"][name="containerIds[]"]')) {
            recalc();
        }
    });

    recalc();
})();
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
