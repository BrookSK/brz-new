<?php
$sidebarActive = 'redirecionamento-envios-sede';
$title = 'Envios à Sede — Redirecionamento';
$registros = is_array($registros ?? null) ? $registros : [];
$enviosDisponiveis = is_array($enviosDisponiveis ?? null) ? $enviosDisponiveis : [];
$enderecoSede = trim((string)($enderecoSede ?? '1227 W Broad St, Saint Pauls, NC 28384'));
$statusColors = ['enviado'=>'info','recebido'=>'success','cancelado'=>'secondary'];
$statusLabels = ['enviado'=>'Enviado','recebido'=>'Recebido na sede','cancelado'=>'Cancelado'];
$_perfil = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
$_isAdmin = in_array($_perfil, ['admin', 'suporte'], true);
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Envios à Sede</h1>
            <div class="text-muted small">Pacotes que o redirecionador envia direto para o nosso endereço — <?= count($registros) ?> registro(s)</div>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRegistrar"><i class="fas fa-dolly me-1"></i>Registrar envio à sede</button>
    </div>

    <!-- Endereço de recebimento -->
    <div class="alert alert-info d-flex align-items-center gap-2 border-0 shadow-sm">
        <i class="fas fa-map-marker-alt fa-lg"></i>
        <div><strong>Endereço de recebimento:</strong> <?= htmlspecialchars($enderecoSede, ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Envio</th>
                            <th>Redirecionador</th>
                            <th>Transportadora</th>
                            <th>Rastreio</th>
                            <th>Data envio</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum envio à sede registrado.</td></tr>
                        <?php else: foreach ($registros as $r):
                            $sc = $statusColors[$r['status']??'enviado'] ?? 'secondary';
                            $sl = $statusLabels[$r['status']??'enviado'] ?? '?';
                        ?>
                        <tr>
                            <td class="ps-3"><?= (int)$r['id'] ?></td>
                            <td><a href="/admin/redirecionamento/envios/<?= (int)$r['envio_id'] ?>">#<?= (int)$r['envio_id'] ?></a><?php if (!empty($r['id_pedido_cliente'])): ?> <span class="text-muted small">(<?= htmlspecialchars($r['id_pedido_cliente'],ENT_QUOTES,'UTF-8') ?>)</span><?php endif; ?></td>
                            <td><?= htmlspecialchars($r['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['transportadora']??'—',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['tracking_code']??'—',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= !empty($r['data_envio']) ? date('d/m/Y', strtotime($r['data_envio'])) : '—' ?></td>
                            <td><span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25"><?= $sl ?></span></td>
                            <td class="pe-3 text-end d-flex gap-1 justify-content-end">
                                <?php if ($_isAdmin && ($r['status']??'enviado') === 'enviado'): ?>
                                <button type="button" class="btn btn-xs btn-outline-success btn-recebido" data-id="<?= (int)$r['id'] ?>" style="font-size:.75rem;padding:2px 8px">Marcar recebido</button>
                                <?php endif; ?>
                                <?php if (!$_isAdmin && ($r['status']??'enviado') === 'enviado'): ?>
                                <button type="button" class="btn btn-xs btn-outline-danger btn-cancelar-sede" data-id="<?= (int)$r['id'] ?>" style="font-size:.75rem;padding:2px 8px">Cancelar</button>
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

<!-- Modal registrar -->
<div class="modal fade" id="modalRegistrar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-dolly me-2 text-info"></i>Registrar envio à sede</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-light border small mb-3">
                    <i class="fas fa-map-marker-alt me-1 text-primary"></i><strong>Endereço:</strong> <?= htmlspecialchars($enderecoSede, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Envio <span class="text-danger">*</span></label>
                        <select class="form-select" id="rsEnvioId">
                            <option value="">Selecione o envio...</option>
                            <?php foreach ($enviosDisponiveis as $env): ?>
                            <option value="<?= (int)$env['id'] ?>">
                                #<?= (int)$env['id'] ?> — Pedido: <?= htmlspecialchars($env['id_pedido_cliente']??'',ENT_QUOTES,'UTF-8') ?>
                                <?php if (!empty($env['redirecionador_nome'])): ?> (<?= htmlspecialchars($env['redirecionador_nome'],ENT_QUOTES,'UTF-8') ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($enviosDisponiveis)): ?>
                        <div class="form-text text-muted">Nenhum envio disponível. Só é possível registrar envios pagos/com etiqueta que ainda não têm coleta ou envio à sede.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6"><label class="form-label">Transportadora</label><input class="form-control" type="text" id="rsTransp" placeholder="Ex: USPS, UPS, FedEx"></div>
                    <div class="col-md-6"><label class="form-label">Data do envio</label><input class="form-control" type="date" id="rsData" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-12"><label class="form-label">Código de rastreio</label><input class="form-control" type="text" id="rsTracking" placeholder="Rastreio da transportadora (opcional)"></div>
                    <div class="col-12"><label class="form-label">Observações</label><textarea class="form-control" id="rsObs" rows="2"></textarea></div>
                </div>
                <div id="msgRegistrar" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnRegistrar">Registrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnRegistrar')?.addEventListener('click', async () => {
    const envioId = document.getElementById('rsEnvioId').value;
    if (!envioId) { document.getElementById('msgRegistrar').innerHTML = '<div class="alert alert-danger py-1 small">Selecione o envio.</div>'; return; }
    const fd = new FormData();
    fd.append('envio_id', envioId);
    fd.append('transportadora', document.getElementById('rsTransp').value);
    fd.append('tracking_code', document.getElementById('rsTracking').value);
    fd.append('data_envio', document.getElementById('rsData').value);
    fd.append('observacoes', document.getElementById('rsObs').value);
    const r = await fetch('/admin/redirecionamento/envios-sede/registrar', {method:'POST', body:fd});
    const j = await r.json();
    if (j.ok) location.reload();
    else document.getElementById('msgRegistrar').innerHTML = '<div class="alert alert-danger py-1 small">'+(j.msg||'Erro')+'</div>';
});

document.querySelectorAll('.btn-recebido').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Confirmar que o pacote foi recebido na sede?')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/redirecionamento/envios-sede/recebido', {method:'POST', body:fd});
        const j = await r.json(); if (j.ok) location.reload();
    });
});

document.querySelectorAll('.btn-cancelar-sede').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Cancelar este envio à sede?')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/redirecionamento/envios-sede/cancelar', {method:'POST', body:fd});
        const j = await r.json();
        if (j.ok) location.reload();
        else alert(j.msg || 'Erro ao cancelar');
    });
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
