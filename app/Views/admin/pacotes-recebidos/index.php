<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-box me-2"></i><?= __('admin.received_packages.title', 'Pacotes Recebidos') ?>
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= __('admin.received_packages.back', 'Voltar') ?>
            </a>
            <a href="/admin/pacotes-recebidos/configuracoes" class="btn btn-outline-primary ms-2">
                <i class="fas fa-cog me-2"></i><?= __('admin.received_packages.settings', 'Configurações') ?>
            </a>
            <a href="/admin/pacotes-recebidos/novo" class="btn btn-primary ms-2">
                <i class="fas fa-plus me-2"></i><?= __('admin.received_packages.new_package', 'Novo Pacote') ?>
            </a>
        </div>
    </div>

    <!-- Mensagem Flash -->
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label"><?= __('admin.received_packages.suite', 'Suite') ?></label>
                    <input type="number" name="suite" class="form-control" value="<?= htmlspecialchars($suite) ?>" placeholder="<?= htmlspecialchars(__('admin.received_packages.suite_number', 'Nº Suite'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('admin.received_packages.status', 'Status') ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= __('admin.received_packages.all', 'Todos') ?></option>
                        <?php foreach ($statusList as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>"<?= ($status == $val) ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('admin.received_packages.start_date', 'Data Inicial') ?></label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('admin.received_packages.end_date', 'Data Final') ?></label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('admin.received_packages.search', 'Buscar') ?></label>
                    <input type="text" name="busca" class="form-control" placeholder="<?= htmlspecialchars(__('admin.received_packages.name_supplier_placeholder', 'Nome, fornecedor...'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($busca) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="/admin/pacotes-recebidos" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><strong><?= $total ?></strong> <?= __('admin.received_packages.packages_found_suffix', 'pacote(s) encontrado(s)') ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th><?= __('admin.received_packages.suite', 'Suite') ?></th>
                            <th><?= __('admin.received_packages.client', 'Cliente') ?></th>
                            <th><?= __('admin.received_packages.product', 'Produto') ?></th>
                            <th><?= __('admin.received_packages.supplier', 'Fornecedor') ?></th>
                            <th><?= __('admin.received_packages.weight_kg', 'Peso (kg)') ?></th>
                            <th><?= __('admin.received_packages.qty', 'Qtd') ?></th>
                            <th><?= __('admin.received_packages.received', 'Recebido') ?></th>
                            <th><?= __('admin.received_packages.status', 'Status') ?></th>
                            <th><?= __('admin.received_packages.days', 'Dias') ?></th>
                            <th><?= __('admin.received_packages.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pacotes)): ?>
                            <tr><td colspan="11" class="text-center text-muted py-4"><?= __('admin.received_packages.no_packages_found', 'Nenhum pacote encontrado.') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($pacotes as $p): ?>
                                <tr>
                                    <td><strong>#<?= $p['id'] ?></strong></td>
                                    <td><span class="badge bg-info"><?= $p['numero_suite'] ?></span></td>
                                    <td>
                                        <small><?= htmlspecialchars($p['usuario_nome'] ?? '-') ?></small><br>
                                        <small class="text-muted"><?= htmlspecialchars($p['usuario_email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['foto_url'])): ?>
                                            <img src="<?= htmlspecialchars($p['foto_url']) ?>" alt="" style="width:30px;height:30px;object-fit:cover;border-radius:4px;" class="me-1">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($p['nome']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['fornecedor']) ?></td>
                                    <td><?= number_format((float)$p['peso_kg'], 3, ',', '.') ?></td>
                                    <td><?= $p['quantidade'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['data_recebimento'])) ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pendente' => 'warning',
                                            'pedido_criado' => 'primary',
                                            'invoice_liberado' => 'info',
                                            'invoice_confirmado' => 'success',
                                            'invoice_contestado' => 'danger',
                                            'enviado' => 'success',
                                            'fatura_pendente' => 'warning',
                                            'fatura_paga' => 'success',
                                            'descartado' => 'dark',
                                        ];
                                        $cor = $statusColors[$p['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $cor ?>"><?= $statusList[$p['status']] ?? $p['status'] ?></span>
                                    </td>
                                    <td><?= (int)$p['dias_armazenamento'] ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin/pacotes-recebidos/<?= $p['id'] ?>" class="btn btn-outline-primary" title="<?= htmlspecialchars(__('admin.received_packages.edit', 'Editar'), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($p['status'] === 'pendente'): ?>
                                            <button type="button" class="btn btn-outline-danger" title="<?= htmlspecialchars(__('admin.received_packages.delete', 'Excluir'), ENT_QUOTES, 'UTF-8') ?>" onclick="excluirPacote(<?= $p['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&suite=<?= urlencode($suite) ?>&status=<?= urlencode($status) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>&busca=<?= urlencode($busca) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Form oculto para exclusão -->
<form id="formExcluir" method="POST" action="" style="display:none;"></form>

<script>
function excluirPacote(id) {
    if (!confirm('<?= htmlspecialchars(__('admin.received_packages.confirm_delete_package', 'Tem certeza que deseja excluir este pacote?'), ENT_QUOTES, 'UTF-8') ?>')) return;
    const form = document.getElementById('formExcluir');
    form.action = '/admin/pacotes-recebidos/' + id + '/excluir';
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
$title = __('admin.received_packages.title', 'Pacotes Recebidos') . ' - ' . __('admin.received_packages.admin_suffix', 'Admin');
include __DIR__ . '/../../layouts/admin.php';
?>
