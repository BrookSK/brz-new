<?php
$sidebarActive = 'redirecionamento-comprovantes';
$title = 'Comprovantes — Redirecionamento';
$comprovantes = is_array($comprovantes ?? null) ? $comprovantes : [];
$tipoLabels = ['envio'=>'Pagamento inicial','diferenca'=>'Diferença','reembolso'=>'Reembolso'];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Comprovantes</h1>
            <div class="text-muted small"><?= count($comprovantes) ?> comprovante(s)</div>
        </div>
    </div>

    <!-- Upload avulso -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3">Upload de comprovante</h5>
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">ID do envio</label><input class="form-control" type="number" id="upEnvioId" placeholder="Ex: 42"></div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="upTipo">
                        <option value="envio">Pagamento inicial</option>
                        <option value="diferenca">Diferença</option>
                        <option value="reembolso">Reembolso</option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Arquivo (JPG, PNG, PDF)</label><input class="form-control" type="file" id="upArquivo" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100" type="button" id="btnUpload"><i class="fas fa-upload me-1"></i>Enviar</button></div>
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
                            <th class="ps-3">Pag. #</th>
                            <th>Envio</th>
                            <th>Redirecionador</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th class="pe-3">Arquivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comprovantes)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum comprovante enviado.</td></tr>
                        <?php else: foreach ($comprovantes as $c): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$c['id'] ?></td>
                            <td><a href="/admin/redirecionamento/envios/<?= (int)$c['envio_id'] ?>">#<?= (int)$c['envio_id'] ?></a></td>
                            <td><?= htmlspecialchars($c['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= $tipoLabels[$c['tipo']??'envio'] ?? $c['tipo'] ?></td>
                            <td><?= ucfirst($c['status']??'') ?></td>
                            <td class="pe-3">
                                <a href="<?= htmlspecialchars($c['comprovante_url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 8px">
                                    <i class="fas fa-eye me-1"></i>Ver
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
document.getElementById('btnUpload')?.addEventListener('click', async () => {
    const envioId = document.getElementById('upEnvioId').value;
    const tipo = document.getElementById('upTipo').value;
    const file = document.getElementById('upArquivo').files[0];
    if (!envioId || !file) { document.getElementById('msgUpload').innerHTML='<div class="alert alert-danger py-1 small">Preencha o ID do envio e selecione um arquivo.</div>'; return; }
    const fd = new FormData(); fd.append('envio_id',envioId); fd.append('tipo',tipo); fd.append('comprovante',file);
    const r = await fetch('/admin/redirecionamento/comprovantes/upload',{method:'POST',body:fd});
    const j = await r.json();
    document.getElementById('msgUpload').innerHTML = j.ok ? '<div class="alert alert-success py-1 small">Comprovante enviado! <a href="'+j.url+'" target="_blank">Ver arquivo</a></div>' : '<div class="alert alert-danger py-1 small">'+(j.msg||'Erro')+'</div>';
    if (j.ok) setTimeout(()=>location.reload(),1500);
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
