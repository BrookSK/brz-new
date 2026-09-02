<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><?= __('admin.wp_packages.containers_title', 'Containers (Pacotes WP)') ?></h1>
        <div class="d-flex gap-2 align-items-center">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/pacotes-wordpress"><i class="fas fa-arrow-left me-1"></i><?= __('admin.wp_packages.back', 'Voltar') ?></a>
            <a class="btn btn-sm btn-success" href="/admin/pacotes-wordpress?action=container-novo"><i class="fas fa-plus me-1"></i><?= __('admin.wp_packages.new_container', 'Novo Container') ?></a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= __('admin.wp_packages.name', 'Nome') ?></th>
                            <th>Dispatch</th>
                            <th>Trackings</th>
                            <th>Status</th>
                            <th><?= __('admin.wp_packages.invoice', 'Fatura') ?></th>
                            <th><?= __('admin.wp_packages.date', 'Data') ?></th>
                            <th><?= __('admin.wp_packages.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $containers = isset($containers) && is_array($containers) ? $containers : []; ?>
                        <?php if (empty($containers)): ?>
                            <tr><td colspan="8" class="text-muted"><?= __('admin.wp_packages.no_containers', 'Nenhum container criado.') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($containers as $c): ?>
                                <?php
                                    $cid = (int) ($c['id'] ?? 0);
                                    $trackings = json_decode((string) ($c['tracking_numbers_json'] ?? '[]'), true) ?: [];
                                    $status = (string) ($c['status'] ?? 'created');
                                    $billId = (int) ($c['bill_id'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= $cid ?></td>
                                    <td><?= htmlspecialchars((string) ($c['nome'] ?? '-')) ?></td>
                                    <td><?= (int) ($c['dispatch_number'] ?? 0) ?></td>
                                    <td><span class="badge bg-secondary"><?= count($trackings) ?></span></td>
                                    <td>
                                        <?php if ($status === 'billed'): ?>
                                            <span class="badge bg-success"><?= __('admin.wp_packages.status_billed', 'Faturado') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><?= __('admin.wp_packages.status_open', 'Aberto') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($billId > 0): ?>
                                            <a href="/admin/pacotes-wordpress?action=faturas">#<?= $billId ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($c['created_at']) ? date('d/m/Y H:i', strtotime((string) $c['created_at'])) : '-' ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="/admin/pacotes-wordpress?action=container-detalhes&id=<?= $cid ?>"><i class="fas fa-eye"></i></a>
                                        <?php if ($status !== 'billed'): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletarContainer(<?= $cid ?>)" title="<?= htmlspecialchars(__('admin.wp_packages.delete', 'Excluir'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-trash"></i></button>
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
</div>

<script>
async function deletarContainer(id) {
    if (!confirm('<?= htmlspecialchars(__('admin.wp_packages.confirm_delete_container', 'Tem certeza que deseja excluir este container? As etiquetas serão liberadas.'), ENT_QUOTES, 'UTF-8') ?>')) return;
    try {
        const r = await fetch('/admin/pacotes-wordpress?action=container-deletar&id=' + id, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });
        const data = await r.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || '<?= htmlspecialchars(__('admin.wp_packages.error_delete', 'Erro ao excluir.'), ENT_QUOTES, 'UTF-8') ?>');
        }
    } catch (e) {
        alert('<?= htmlspecialchars(__('admin.wp_packages.error', 'Erro:'), ENT_QUOTES, 'UTF-8') ?> ' + e.message);
    }
}
</script>

<?php
$content = ob_get_clean();
$title = __('admin.wp_packages.containers_page_title', 'Containers - Pacotes WordPress');
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
