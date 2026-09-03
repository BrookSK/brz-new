<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-invoice-dollar me-2"></i><?= __('admin.additional_invoices.title', 'Faturas Adicionais') ?>
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= __('admin.additional_invoices.back', 'Voltar') ?>
            </a>
            <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#modalNovaFatura">
                <i class="fas fa-plus me-2"></i><?= __('admin.additional_invoices.new_invoice', 'Nova Fatura') ?>
            </button>
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
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.additional_invoices.order_id', 'Pedido ID') ?></label>
                    <input type="number" name="pedido_id" class="form-control" value="<?= htmlspecialchars($pedido_id) ?>" placeholder="<?= htmlspecialchars(__('admin.additional_invoices.order_id_ph', 'ID do Pedido'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.additional_invoices.status', 'Status') ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= __('admin.additional_invoices.status_all', 'Todos') ?></option>
                        <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>><?= __('admin.additional_invoices.status_pending', 'Pendente') ?></option>
                        <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>><?= __('admin.additional_invoices.status_paid', 'Pago') ?></option>
                        <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>><?= __('admin.additional_invoices.status_canceled', 'Cancelado') ?></option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> <?= __('admin.additional_invoices.filter', 'Filtrar') ?></button>
                    <a href="/admin/faturas-adicionais" class="btn btn-secondary"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <strong><?= $total ?></strong> <?= __('admin.additional_invoices.invoices_found', 'fatura(s) encontrada(s)') ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th><?= __('admin.additional_invoices.th_order', 'Pedido') ?></th>
                            <th><?= __('admin.additional_invoices.th_customer', 'Cliente') ?></th>
                            <th><?= __('admin.additional_invoices.th_reason', 'Motivo') ?></th>
                            <th><?= __('admin.additional_invoices.th_amount', 'Valor') ?></th>
                            <th><?= __('admin.additional_invoices.th_status', 'Status') ?></th>
                            <th><?= __('admin.additional_invoices.th_created_at', 'Criada em') ?></th>
                            <th><?= __('admin.additional_invoices.th_actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($faturas)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4"><?= __('admin.additional_invoices.none_found', 'Nenhuma fatura encontrada.') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($faturas as $f): ?>
                                <tr>
                                    <td><strong>#<?= $f['id'] ?></strong></td>
                                    <td>
                                        <a href="/admin/pedidos/<?= $f['pedido_id'] ?>">#<?= $f['pedido_id'] ?></a>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($f['usuario_nome'] ?? '-') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($f['motivo']) ?></td>
                                    <td><strong>US$ <?= number_format((float)$f['valor'], 2, ',', '.') ?></strong></td>
                                    <td>
                                        <?php
                                        $corFatura = ['pendente' => 'warning', 'pago' => 'success', 'cancelado' => 'secondary'];
                                        $labelFatura = [
                                            'pendente' => __('admin.additional_invoices.status_pending', 'Pendente'),
                                            'pago' => __('admin.additional_invoices.status_paid', 'Pago'),
                                            'cancelado' => __('admin.additional_invoices.status_canceled', 'Cancelado'),
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $corFatura[$f['status']] ?? 'secondary' ?>">
                                            <?= $labelFatura[$f['status']] ?? ucfirst($f['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($f['created_at'])) ?></td>
                                    <td>
                                        <?php if ($f['status'] === 'pendente'): ?>
                                        <form method="POST" action="/admin/faturas-adicionais/<?= $f['id'] ?>/cancelar" style="display:inline;" onsubmit="return confirm('<?= htmlspecialchars(__('admin.additional_invoices.confirm_cancel', 'Cancelar esta fatura?'), ENT_QUOTES, 'UTF-8') ?>')">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= htmlspecialchars(__('admin.additional_invoices.cancel', 'Cancelar'), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
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

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&pedido_id=<?= urlencode($pedido_id) ?>&status=<?= urlencode($status) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Modal Nova Fatura -->
<div class="modal fade" id="modalNovaFatura" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/faturas-adicionais/criar">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('admin.additional_invoices.modal_title', 'Nova Fatura Adicional') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= __('admin.additional_invoices.field_order_id', 'ID do Pedido') ?> *</label>
                        <input type="number" name="pedido_id" class="form-control" required min="1" value="<?= htmlspecialchars($pedido_id) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= __('admin.additional_invoices.field_reason', 'Motivo') ?> *</label>
                        <input type="text" name="motivo" class="form-control" required placeholder="<?= htmlspecialchars(__('admin.additional_invoices.field_reason_ph', 'Ex: Taxa adicional, ajuste de peso...'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= __('admin.additional_invoices.field_amount', 'Valor (USD)') ?> *</label>
                        <div class="input-group">
                            <span class="input-group-text">US$</span>
                            <input type="number" name="valor" class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('admin.additional_invoices.field_description', 'Descrição') ?></label>
                        <textarea name="descricao" class="form-control" rows="3" placeholder="<?= htmlspecialchars(__('admin.additional_invoices.field_description_ph', 'Detalhes adicionais (opcional)'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('admin.additional_invoices.cancel', 'Cancelar') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i><?= __('admin.additional_invoices.create_invoice', 'Criar Fatura') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Faturas Adicionais - Admin';
include __DIR__ . '/../../layouts/admin.php';
?>
