<?php
/** @var array $orders */
?>
<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title"><?= htmlspecialchars(__('admin.lives.orders_title', 'Pedidos da Live'), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars(__('admin.lives.orders.col_order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.lives.orders.col_live', 'Live'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.lives.orders.col_customer', 'Cliente'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.lives.orders.col_product', 'Produto'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.lives.orders.col_total', 'Total'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.lives.orders.col_status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.lives.orders.col_date', 'Data'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4"><?= htmlspecialchars(__('admin.lives.orders.empty', 'Nenhum pedido de live ainda'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><a href="/admin/pedidos/<?= $o['order_id'] ?>">#<?= $o['order_id'] ?></a></td>
                                    <td><a href="/admin/lives/<?= $o['live_id'] ?>/report"><?= htmlspecialchars($o['live_title'] ?? __('admin.lives.orders.live_fallback', 'Live #') . $o['live_id']) ?></a></td>
                                    <td><?= htmlspecialchars($o['cliente_nome'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($o['produto_nome'] ?? '-') ?></td>
                                    <td>R$ <?= number_format((float)($o['pedido_total'] ?? 0), 2, ',', '.') ?></td>
                                    <td>
                                        <?php
                                        $status = $o['pedido_status'] ?? '';
                                        $badgeClass = 'secondary';
                                        if (in_array($status, ['paid','pago','aprovado'])) $badgeClass = 'success';
                                        elseif (in_array($status, ['pending','pendente'])) $badgeClass = 'warning';
                                        elseif (in_array($status, ['failed','falhou','cancelado'])) $badgeClass = 'danger';
                                        $__liveStatusMap = [
                                            'paid' => __('admin.order_status_list.pago', 'Pago'),
                                            'pago' => __('admin.order_status_list.pago', 'Pago'),
                                            'aprovado' => __('admin.lives.orders.status_approved', 'Aprovado'),
                                            'pending' => __('admin.order_status_list.pendente', 'Pendente'),
                                            'pendente' => __('admin.order_status_list.pendente', 'Pendente'),
                                            'failed' => __('admin.lives.orders.status_failed', 'Falhou'),
                                            'falhou' => __('admin.lives.orders.status_failed', 'Falhou'),
                                            'cancelado' => __('admin.order_status_list.cancelado', 'Cancelado'),
                                        ];
                                        $statusLabel = $status !== '' ? ($__liveStatusMap[$status] ?? ucfirst(str_replace('_', ' ', $status))) : '-';
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td><?= $o['created_at'] ? date('d/m/Y H:i', strtotime($o['created_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
