<?php
$sidebarActive = 'redirecionamento-comprovantes';
$title = __('admin.fwd_receipts.title', 'Comprovantes') . ' — ' . __('admin.fwd_payments.module', 'Redirecionamento');
$comprovantes = is_array($comprovantes ?? null) ? $comprovantes : [];
$tipoLabels = ['envio'=>__('admin.fwd_receipts.type.initial_payment','Pagamento inicial'),'diferenca'=>__('admin.fwd_payments.type.difference','Diferença'),'reembolso'=>__('admin.fwd_payments.type.refund','Reembolso')];
$statusLabels = ['pendente'=>__('admin.fwd_payments.status.pending','Pendente'),'pago'=>__('admin.fwd_payments.status.paid','Pago'),'falhou'=>__('admin.fwd_payments.status.failed','Falhou'),'reembolsado'=>__('admin.fwd_payments.status.refunded','Reembolsado')];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><?= __('admin.fwd_receipts.title', 'Comprovantes') ?></h1>
            <div class="text-muted small"><?= count($comprovantes) ?> <?= __('admin.fwd_receipts.count', 'comprovante(s)') ?></div>
        </div>
    </div>

    <!-- Upload avulso -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3"><?= __('admin.fwd_receipts.upload_title', 'Upload de comprovante') ?></h5>
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label"><?= __('admin.fwd_receipts.shipment_id', 'ID do envio') ?></label><input class="form-control" type="number" id="upEnvioId" placeholder="<?= htmlspecialchars(__('admin.fwd_receipts.shipment_id_ph', 'Ex: 42'), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.fwd_payments.col.type', 'Tipo') ?></label>
                    <select class="form-select" id="upTipo">
                        <option value="envio"><?= __('admin.fwd_receipts.type.initial_payment', 'Pagamento inicial') ?></option>
                        <option value="diferenca"><?= __('admin.fwd_payments.type.difference', 'Diferença') ?></option>
                        <option value="reembolso"><?= __('admin.fwd_payments.type.refund', 'Reembolso') ?></option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label"><?= __('admin.fwd_receipts.file_label', 'Arquivo (JPG, PNG, PDF)') ?></label><input class="form-control" type="file" id="upArquivo" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100" type="button" id="btnUpload"><i class="fas fa-upload me-1"></i><?= __('admin.fwd_receipts.send', 'Enviar') ?></button></div>
            </div>
            <div id="msgUpload" class="mt-2"></div>
        </div>
    </div>

    <!-- Listagem -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3"><?= __('admin.fwd_receipts.col.payment_num', 'Pag. #') ?></th>
                            <th><?= __('admin.fwd_payments.col.shipment', 'Envio') ?></th>
                            <th><?= __('admin.fwd_payments.col.forwarder', 'Redirecionador') ?></th>
                            <th><?= __('admin.fwd_payments.col.type', 'Tipo') ?></th>
                            <th><?= __('admin.fwd_payments.col.status', 'Status') ?></th>
                            <th class="pe-3"><?= __('admin.fwd_receipts.col.file', 'Arquivo') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comprovantes)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><?= __('admin.fwd_receipts.empty', 'Nenhum comprovante enviado.') ?></td></tr>
                        <?php else: foreach ($comprovantes as $c): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$c['id'] ?></td>
                            <td><a href="/admin/redirecionamento/envios/<?= (int)$c['envio_id'] ?>">#<?= (int)$c['envio_id'] ?></a></td>
                            <td><?= htmlspecialchars($c['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= $tipoLabels[$c['tipo']??'envio'] ?? $c['tipo'] ?></td>
                            <td><?= $statusLabels[$c['status']??''] ?? ucfirst($c['status']??'') ?></td>
                            <td class="pe-3">
                                <a href="<?= htmlspecialchars($c['comprovante_url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 8px">
                                    <i class="fas fa-eye me-1"></i><?= __('admin.fwd_payments.view', 'Ver') ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
window.FWD_RECEIPTS_I18N = {
    fill_id_file: <?= json_encode(__('admin.fwd_receipts.js.fill_id_file', 'Preencha o ID do envio e selecione um arquivo.'), JSON_UNESCAPED_UNICODE) ?>,
    uploaded: <?= json_encode(__('admin.fwd_receipts.js.uploaded', 'Comprovante enviado!'), JSON_UNESCAPED_UNICODE) ?>,
    view_file: <?= json_encode(__('admin.fwd_receipts.js.view_file', 'Ver arquivo'), JSON_UNESCAPED_UNICODE) ?>,
    error: <?= json_encode(__('admin.fwd_receipts.js.error', 'Erro'), JSON_UNESCAPED_UNICODE) ?>
};
document.getElementById('btnUpload')?.addEventListener('click', async () => {
    const envioId = document.getElementById('upEnvioId').value;
    const tipo = document.getElementById('upTipo').value;
    const file = document.getElementById('upArquivo').files[0];
    if (!envioId || !file) { document.getElementById('msgUpload').innerHTML='<div class="alert alert-danger py-1 small">'+window.FWD_RECEIPTS_I18N.fill_id_file+'</div>'; return; }
    const fd = new FormData(); fd.append('envio_id',envioId); fd.append('tipo',tipo); fd.append('comprovante',file);
    const r = await fetch('/admin/redirecionamento/comprovantes/upload',{method:'POST',body:fd});
    const j = await r.json();
    document.getElementById('msgUpload').innerHTML = j.ok ? '<div class="alert alert-success py-1 small">'+window.FWD_RECEIPTS_I18N.uploaded+' <a href="'+j.url+'" target="_blank">'+window.FWD_RECEIPTS_I18N.view_file+'</a></div>' : '<div class="alert alert-danger py-1 small">'+(j.msg||window.FWD_RECEIPTS_I18N.error)+'</div>';
    if (j.ok) setTimeout(()=>location.reload(),1500);
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
