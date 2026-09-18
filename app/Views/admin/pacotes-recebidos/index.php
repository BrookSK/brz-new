<?php ob_start(); ?>

<?php
// Traduz valores de "fornecedor" que são gerados pelo sistema (não digitados livremente).
// Valores livres (nomes de lojas) são exibidos como estão.
$traduzirFornecedor = function (?string $valor): string {
    $v = trim((string) $valor);
    if ($v === '') return '-';
    $key = strtolower($v);
    $mapa = [
        'redirecionamento' => __('admin.received_packages.supplier.forwarding', 'Redirecionamento'),
        'redirecionador'   => __('admin.received_packages.supplier.forwarding', 'Redirecionamento'),
    ];
    return $mapa[$key] ?? $v;
};
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-box me-2"></i><?= htmlspecialchars(__('admin.received_packages.title', 'Pacotes Recebidos'), ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= htmlspecialchars(__('common.back', 'Voltar'), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <a href="/admin/pacotes-recebidos/configuracoes" class="btn btn-outline-primary ms-2">
                <i class="fas fa-cog me-2"></i><?= htmlspecialchars(__('admin.received_packages.settings', 'Configurações'), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <a href="/admin/pacotes-recebidos/novo" class="btn btn-primary ms-2">
                <i class="fas fa-plus me-2"></i><?= htmlspecialchars(__('admin.received_packages.new_package', 'Novo Pacote'), ENT_QUOTES, 'UTF-8') ?>
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
                    <label class="form-label"><?= htmlspecialchars(__('admin.received_packages.filter.suite', 'Suite'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="number" name="suite" class="form-control" value="<?= htmlspecialchars($suite) ?>" placeholder="<?= htmlspecialchars(__('admin.received_packages.filter.suite_placeholder', 'Nº Suite'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('common.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= htmlspecialchars(__('common.all', 'Todos'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($statusList as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>"<?= ($status == $val) ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('admin.received_packages.filter.start_date', 'Data Inicial'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('admin.received_packages.filter.end_date', 'Data Final'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('common.search', 'Buscar'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" name="busca" class="form-control" placeholder="<?= htmlspecialchars(__('admin.received_packages.filter.search_placeholder', 'Nome, fornecedor...'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($busca) ?>">
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
            <span><strong><?= $total ?></strong> <?= htmlspecialchars(__('admin.received_packages.packages_found', 'pacote(s) encontrado(s)'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.suite', 'Suite'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.customer', 'Cliente'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.product', 'Produto'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.supplier', 'Fornecedor'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.weight', 'Peso (kg)'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.qty', 'Qtd'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.received', 'Recebido'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('common.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.received_packages.col.days', 'Dias'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('common.actions', 'Ações'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pacotes)): ?>
                            <tr><td colspan="11" class="text-center text-muted py-4"><?= htmlspecialchars(__('admin.received_packages.empty', 'Nenhum pacote encontrado.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
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
                                    <td><?= htmlspecialchars($traduzirFornecedor($p['fornecedor'] ?? '')) ?></td>
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
                                        <span class="badge bg-<?= $cor ?>"><?= htmlspecialchars($statusList[$p['status']] ?? $p['status']) ?></span>
                                    </td>
                                    <td><?= (int)$p['dias_armazenamento'] ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin/pacotes-recebidos/<?= $p['id'] ?>" class="btn btn-outline-primary" title="<?= htmlspecialchars(__('common.edit', 'Editar'), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($p['status'] === 'pendente'): ?>
                                            <button type="button" class="btn btn-outline-danger" title="<?= htmlspecialchars(__('common.delete', 'Excluir'), ENT_QUOTES, 'UTF-8') ?>" onclick="excluirPacote(<?= $p['id'] ?>)">
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
    if (!confirm('<?= htmlspecialchars(addslashes(__('admin.received_packages.confirm_delete', 'Tem certeza que deseja excluir este pacote?')), ENT_QUOTES, 'UTF-8') ?>')) return;
    const form = document.getElementById('formExcluir');
    form.action = '/admin/pacotes-recebidos/' + id + '/excluir';
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
$title = __('admin.received_packages.page_title', 'Pacotes Recebidos - Admin');
include __DIR__ . '/../../layouts/admin.php';
?>
