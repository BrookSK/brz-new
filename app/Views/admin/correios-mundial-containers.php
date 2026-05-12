<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Correios Mundial (PACKET) - Containers</h1>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial">Voltar</a>
            <a class="btn btn-sm btn-primary" href="/admin/correios-mundial/containers/novo">Novo container</a>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong>Containers criados</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Remessa</th>
                            <th>Unit Code</th>
                            <th>Status</th>
                            <th>Qtd pacotes</th>
                            <th>PDF</th>
                            <th>Ações</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $containers = isset($containers) && is_array($containers) ? $containers : []; ?>
                        <?php if (empty($containers)): ?>
                            <tr><td colspan="8" class="text-muted">Nenhum container criado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($containers as $c): ?>
                                <?php $cid = (int) ($c['id'] ?? 0); ?>
                                <?php $unitCode = (string) ($c['unit_code'] ?? ''); ?>
                                <?php $dispatchNumber = (string) ($c['dispatch_number'] ?? ''); ?>
                                <?php $status = strtolower(trim((string) ($c['status'] ?? ''))); ?>
                                <tr>
                                    <td>#<?= $cid ?></td>
                                    <td><?= htmlspecialchars($dispatchNumber) ?></td>
                                    <td><?= htmlspecialchars($unitCode) ?></td>
                                    <td><?= htmlspecialchars($status !== '' ? $status : '-') ?></td>
                                    <td><?= (int) ($c['packages_count'] ?? 0) ?></td>
                                    <td>
                                        <?php if ($cid > 0): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/container/<?= $cid ?>.pdf" target="_blank">PDF</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cid > 0): ?>
                                            <form method="post" action="/admin/correios-mundial/container/<?= $cid ?>/cancelar" style="display:inline-block" onsubmit="return confirm('Cancelar o despacho (dispatch) deste container?');">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" <?= $status === 'cancelled' ? 'disabled' : '' ?>>Cancelar</button>
                                            </form>
                                            <form method="post" action="/admin/correios-mundial/container/<?= $cid ?>/deletar" style="display:inline-block" onsubmit="return confirm('Deletar o container? Isso vai liberar os pacotes para uso em outro container.');">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" <?= $status === 'cancelled' ? '' : 'disabled' ?>>Deletar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($c['created_at']) ? date('d/m/Y H:i', strtotime((string) $c['created_at'])) : '-' ?></td>
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
