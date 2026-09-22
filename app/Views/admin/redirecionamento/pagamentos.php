<?php
$sidebarActive = 'redirecionamento-pagamentos';
$title = __('admin.fwd_payments.title', 'Pagamentos') . ' — ' . __('admin.fwd_payments.module', 'Redirecionamento');
$pagamentos = is_array($pagamentos ?? null) ? $pagamentos : [];
$tipoLabels = ['envio'=>__('admin.fwd_payments.type.initial','Envio inicial'),'diferenca'=>__('admin.fwd_payments.type.difference','Diferença'),'reembolso'=>__('admin.fwd_payments.type.refund','Reembolso')];
$statusColors = ['pendente'=>'warning','pago'=>'success','falhou'=>'danger','reembolsado'=>'info'];
$statusLabels = ['pendente'=>__('admin.fwd_payments.status.pending','Pendente'),'pago'=>__('admin.fwd_payments.status.paid','Pago'),'falhou'=>__('admin.fwd_payments.status.failed','Falhou'),'reembolsado'=>__('admin.fwd_payments.status.refunded','Reembolsado')];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><?= __('admin.fwd_payments.title', 'Pagamentos') ?></h1>
            <div class="text-muted small"><?= count($pagamentos) ?> <?= __('admin.fwd_payments.records', 'registro(s)') ?></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th><?= __('admin.fwd_payments.col.shipment', 'Envio') ?></th>
                            <th><?= __('admin.fwd_payments.col.forwarder', 'Redirecionador') ?></th>
                            <th><?= __('admin.fwd_payments.col.type', 'Tipo') ?></th>
                            <th><?= __('admin.fwd_payments.col.value_usd', 'Valor (USD)') ?></th>
                            <th><?= __('admin.fwd_payments.col.status', 'Status') ?></th>
                            <th><?= __('admin.fwd_payments.col.paid_at', 'Pago em') ?></th>
                            <th class="pe-3"><?= __('admin.fwd_payments.col.receipt', 'Comprovante') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagamentos)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4"><?= __('admin.fwd_payments.empty', 'Nenhum pagamento registrado.') ?></td></tr>
                        <?php else: foreach ($pagamentos as $p):
                            $sc = $statusColors[$p['status']??'pendente'] ?? 'secondary';
                        ?>
                        <tr>
                            <td class="ps-3"><?= (int)$p['id'] ?></td>
                            <td><a href="/admin/redirecionamento/envios/<?= (int)$p['envio_id'] ?>">#<?= (int)$p['envio_id'] ?></a></td>
                            <td><?= htmlspecialchars($p['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= $tipoLabels[$p['tipo']??'envio'] ?? $p['tipo'] ?></td>
                            <td>US$ <?= number_format((float)($p['valor_usd']??0),2,',','.') ?></td>
                            <td><span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25"><?= $statusLabels[$p['status']??'pendente'] ?? ucfirst($p['status']??'pendente') ?></span></td>
                            <td><?= $p['pago_em'] ? date('d/m/Y H:i', strtotime($p['pago_em'])) : '—' ?></td>
                            <td class="pe-3">
                                <?php if (!empty($p['comprovante_url'])): ?>
                                <a href="<?= htmlspecialchars($p['comprovante_url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" class="btn btn-xs btn-outline-info" style="font-size:.75rem;padding:2px 8px"><?= __('admin.fwd_payments.view', 'Ver') ?></a>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
