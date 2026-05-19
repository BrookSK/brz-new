<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Container #<?= (int) ($container['id'] ?? 0) ?></h1>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/pacotes-wordpress?action=containers"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php $container = isset($container) && is_array($container) ? $container : []; ?>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Nome</div>
                    <div class="fw-bold"><?= htmlspecialchars((string) ($container['nome'] ?? '-')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Dispatch Number</div>
                    <div class="fw-bold"><?= (int) ($container['dispatch_number'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Status</div>
                    <?php $status = (string) ($container['status'] ?? 'created'); ?>
                    <?php if ($status === 'billed'): ?>
                        <span class="badge bg-success">Faturado</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Aberto</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong>Etiquetas neste container</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Origem</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Tracking</th>
                            <th>Data</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $etiquetas = isset($etiquetas) && is_array($etiquetas) ? $etiquetas : []; ?>
                        <?php if (empty($etiquetas)): ?>
                            <tr><td colspan="6" class="text-muted">Nenhuma etiqueta neste container.</td></tr>
                        <?php else: ?>
                            <?php foreach ($etiquetas as $e): ?>
                                <?php
                                    $eid = (int) ($e['id'] ?? 0);
                                    $origem = strtoupper((string) ($e['origem'] ?? ''));
                                    $pedidoId = (int) ($e['pedido_id'] ?? 0);
                                    $trk = (string) ($e['tracking_number'] ?? '');
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $origem === 'BR' ? 'success' : ($origem === 'RED' ? 'warning' : 'info') ?>"><?= $origem ?></span></td>
                                    <td><?= $pedidoId > 0 ? '#' . $pedidoId : '-' ?></td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></td>
                                    <td><code class="small"><?= htmlspecialchars($trk) ?></code></td>
                                    <td><?= !empty($e['created_at']) ? date('d/m/Y H:i', strtotime((string) $e['created_at'])) : '-' ?></td>
                                    <td>
                                        <?php if ($trk !== ''): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="/wp-etiqueta?id=<?= $eid ?>" target="_blank"><i class="fas fa-file-pdf"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php
        $trackingsJson = $container['tracking_numbers_json'] ?? '[]';
        $trackings = json_decode((string) $trackingsJson, true) ?: [];
    ?>
    <?php if (!empty($trackings)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header"><strong>Tracking Numbers (<?= count($trackings) ?>)</strong></div>
            <div class="card-body">
                <div class="small" style="max-height:200px;overflow-y:auto;">
                    <?php foreach ($trackings as $t): ?>
                        <div><code><?= htmlspecialchars((string) $t) ?></code></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Container #' . (int) ($container['id'] ?? 0) . ' - Pacotes WordPress';
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
