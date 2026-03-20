<?php
$sidebarActive = 'redirecionamento-pagamentos';
$title = 'Pagamentos — Redirecionamento';
$pagamentos = is_array($pagamentos ?? null) ? $pagamentos : [];
$tipoLabels = ['envio'=>'Envio inicial','diferenca'=>'Diferença','reembolso'=>'Reembolso'];
$statusColors = ['pendente'=>'warning','pago'=>'success','falhou'=>'danger','reembolsado'=>'info'];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Pagamentos</h1>
            <div class="text-muted small"><?= count($pagamentos) ?> registro(s)</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Envio</th>
                            <th>Redirecionador</th>
                            <th>Tipo</th>
                            <th>Valor (USD)</th>
                            <th>Status</th>
                            <th>Pago em</th>
                            <th class="pe-3">Comprovante</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagamentos)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum pagamento registrado.</td></tr>
                        <?php else: foreach ($pagamentos as $p):
                            $sc = $statusColors[$p['status']??'pendente'] ?? 'secondary';
                        ?>
                        <tr>
                            <td class="ps-3"><?= (int)$p['id'] ?></td>
                            <td><a href="/admin/redirecionamento/envios/<?= (int)$p['envio_id'] ?>">#<?= (int)$p['envio_id'] ?></a></td>
                            <td><?= htmlspecialchars($p['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= $tipoLabels[$p['tipo']??'envio'] ?? $p['tipo'] ?></td>
                            <td>US$ <?= number_format((float)($p['valor_usd']??0),2,',','.') ?></td>
                            <td><span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25"><?= ucfirst($p['status']??'pendente') ?></span></td>
                            <td><?= $p['pago_em'] ? date('d/m/Y H:i', strtotime($p['pago_em'])) : '—' ?></td>
                            <td class="pe-3">
                                <?php if (!empty($p['comprovante_url'])): ?>
                                <a href="<?= htmlspecialchars($p['comprovante_url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" class="btn btn-xs btn-outline-info" style="font-size:.75rem;padding:2px 8px">Ver</a>
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
