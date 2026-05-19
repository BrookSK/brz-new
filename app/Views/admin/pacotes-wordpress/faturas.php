<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Faturas CN38 (Pacotes WP)</h1>
        <div class="d-flex gap-2 align-items-center">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/pacotes-wordpress"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
            <a class="btn btn-sm btn-success" href="/admin/pacotes-wordpress?action=fatura-nova"><i class="fas fa-plus me-1"></i>Nova Fatura</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>CN38</th>
                            <th>Status</th>
                            <th>Containers</th>
                            <th>Trackings</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $faturas = isset($faturas) && is_array($faturas) ? $faturas : []; ?>
                        <?php if (empty($faturas)): ?>
                            <tr><td colspan="6" class="text-muted">Nenhuma fatura criada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($faturas as $f): ?>
                                <?php
                                    $fid = (int) ($f['id'] ?? 0);
                                    $cn38 = (string) ($f['cn38_code'] ?? '-');
                                    $status = (string) ($f['status'] ?? 'pending');
                                    $containersArr = json_decode((string) ($f['containers_json'] ?? '[]'), true) ?: [];
                                    $trackingCount = (int) ($f['tracking_count'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= $fid ?></td>
                                    <td><code><?= htmlspecialchars($cn38) ?></code></td>
                                    <td>
                                        <?php if ($status === 'completed'): ?>
                                            <span class="badge bg-success">Concluída</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pendente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= count($containersArr) ?></span></td>
                                    <td><?= $trackingCount ?></td>
                                    <td><?= !empty($f['created_at']) ? date('d/m/Y H:i', strtotime((string) $f['created_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Faturas CN38 - Pacotes WordPress';
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
