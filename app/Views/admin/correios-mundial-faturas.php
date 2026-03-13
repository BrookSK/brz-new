<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Correios Mundial (PACKET) - Faturas (CN38)</h1>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial">Voltar</a>
            <a class="btn btn-sm btn-primary" href="/admin/correios-mundial/faturas/nova">Nova fatura</a>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong>Faturas geradas</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>CN38</th>
                            <th>Request ID</th>
                            <th>Containers</th>
                            <th>Trackings</th>
                            <th>PDF</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $bills = isset($bills) && is_array($bills) ? $bills : []; ?>
                        <?php if (empty($bills)): ?>
                            <tr><td colspan="8" class="text-muted">Nenhuma fatura encontrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bills as $b): ?>
                                <?php $id = (int) ($b['id'] ?? 0); ?>
                                <?php $status = strtolower(trim((string) ($b['status'] ?? ''))); ?>
                                <?php $cn38 = (string) ($b['cn38_code'] ?? ''); ?>
                                <?php $req = (string) ($b['request_id'] ?? ''); ?>
                                <?php $containersCount = 0; ?>
                                <?php $raw = (string) ($b['containers_json'] ?? ''); ?>
                                <?php if ($raw !== '') { $tmp = json_decode($raw, true); if (is_array($tmp)) $containersCount = count($tmp); } ?>
                                <tr>
                                    <td>#<?= $id ?></td>
                                    <td><?= htmlspecialchars($status !== '' ? $status : '-') ?></td>
                                    <td><?= htmlspecialchars($cn38 !== '' ? $cn38 : '-') ?></td>
                                    <td style="max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($req !== '' ? $req : '-') ?></td>
                                    <td><?= (int) $containersCount ?></td>
                                    <td><?= (int) ($b['tracking_count'] ?? 0) ?></td>
                                    <td>
                                        <?php if ($id > 0 && $cn38 !== ''): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/fatura/<?= $id ?>.pdf" target="_blank">PDF</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($b['created_at']) ? date('d/m/Y H:i', strtotime((string) $b['created_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
