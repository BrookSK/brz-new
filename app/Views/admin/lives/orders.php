<?php
/** @var array $orders */
?>
<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-shopping-bag me-2"></i>Pedidos da Live</h1>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Live</th>
                            <th>Cliente</th>
                            <th>Produto</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum pedido de live ainda</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><a href="/admin/pedidos/<?= $o['order_id'] ?>">#<?= $o['order_id'] ?></a></td>
                                    <td><a href="/admin/lives/<?= $o['live_id'] ?>/report"><?= htmlspecialchars($o['live_title'] ?? 'Live #' . $o['live_id']) ?></a></td>
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
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?>"><?= $status ?: '-' ?></span>
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
