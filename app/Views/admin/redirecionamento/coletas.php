<?php
$sidebarActive = 'redirecionamento-coletas';
$title = __('admin.redirect.collections_title', 'Coletas — Redirecionamento');
$coletas = is_array($coletas ?? null) ? $coletas : [];
$enviosDisponiveis = is_array($enviosDisponiveis ?? null) ? $enviosDisponiveis : [];
$statusColors = ['agendado'=>'warning','confirmado'=>'info','coletado'=>'success','cancelado'=>'secondary'];
$statusLabels = ['agendado'=>__('admin.redirect.collection_status_scheduled','Agendado'),'confirmado'=>__('admin.redirect.collection_status_confirmed','Confirmado'),'coletado'=>__('admin.redirect.collection_status_collected','Coletado'),'cancelado'=>__('admin.redirect.collection_status_cancelled','Cancelado')];
$_perfilColeta = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
$_isAdminColeta = in_array($_perfilColeta, ['admin', 'suporte'], true);

// Agrupar por mês para o calendário
$porDia = [];
foreach ($coletas as $c) {
    $dia = $c['data_agendada'] ?? '';
    if ($dia) $porDia[$dia][] = $c;
}
ksort($porDia);
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><?= __('admin.redirect.collections', 'Coletas') ?></h1>
            <div class="text-muted small"><?= __('admin.redirect.fabiana_schedule', 'Agenda da Fabiana') ?> — <?= count($coletas) ?> <?= __('admin.redirect.collections_count_suffix', 'coleta(s)') ?></div>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgendar"><i class="fas fa-calendar-plus me-1"></i><?= __('admin.redirect.schedule_collection', 'Agendar coleta') ?></button>
    </div>

    <!-- Calendário visual simples -->
    <?php if (!empty($porDia)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="fas fa-calendar me-2 text-primary"></i><?= __('admin.redirect.upcoming_collections', 'Próximas coletas') ?></h5>
            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($porDia as $dia => $items): ?>
                <div class="border rounded p-3" style="min-width:180px">
                    <div class="fw-bold text-primary mb-2"><?= date('d/m/Y', strtotime($dia)) ?></div>
                    <?php foreach ($items as $item): $sc = $statusColors[$item['status']??'agendado']??'secondary'; ?>
                    <div class="small mb-1">
                        <span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25"><?= $statusLabels[$item['status']??'agendado'] ?></span>
                        <?= date('H:i', strtotime($item['horario']??'00:00')) ?> — <?= htmlspecialchars($item['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th><?= __('admin.redirect.step_shipment', 'Envio') ?></th>
                            <th><?= __('admin.redirect.redirector', 'Redirecionador') ?></th>
                            <th><?= __('admin.redirect.scheduled_date', 'Data agendada') ?></th>
                            <th><?= __('admin.redirect.time', 'Horário') ?></th>
                            <th><?= __('admin.redirect.status', 'Status') ?></th>
                            <th class="pe-3 text-end"><?= __('admin.redirect.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coletas)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><?= __('admin.redirect.no_collections_scheduled', 'Nenhuma coleta agendada.') ?></td></tr>
                        <?php else: foreach ($coletas as $c):
                            $sc = $statusColors[$c['status']??'agendado'] ?? 'secondary';
                            $sl = $statusLabels[$c['status']??'agendado'] ?? '?';
                        ?>
                        <tr>
                            <td class="ps-3"><?= (int)$c['id'] ?></td>
                            <td><a href="/admin/redirecionamento/envios/<?= (int)$c['envio_id'] ?>">#<?= (int)$c['envio_id'] ?></a></td>
                            <td><?= htmlspecialchars($c['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= date('d/m/Y', strtotime($c['data_agendada'])) ?></td>
                            <td><?= date('H:i', strtotime($c['horario']??'00:00')) ?></td>
                            <td><span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25"><?= $sl ?></span></td>
                            <td class="pe-3 text-end d-flex gap-1 justify-content-end">
                                <?php if ($_isAdminColeta && ($c['status']??'agendado') === 'agendado'): ?>
                                <button type="button" class="btn btn-xs btn-outline-info btn-confirmar" data-id="<?= (int)$c['id'] ?>" style="font-size:.75rem;padding:2px 8px"><?= __('admin.redirect.confirm', 'Confirmar') ?></button>
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-reagendar" data-id="<?= (int)$c['id'] ?>" style="font-size:.75rem;padding:2px 8px"><?= __('admin.redirect.reschedule', 'Reagendar') ?></button>
                                <?php endif; ?>
                                <?php if ($_isAdminColeta && in_array($c['status']??'agendado',['agendado','confirmado'])): ?>
                                <button type="button" class="btn btn-xs btn-outline-success btn-coletado" data-id="<?= (int)$c['id'] ?>" style="font-size:.75rem;padding:2px 8px"><?= __('admin.redirect.collected', 'Coletado') ?></button>
                                <?php endif; ?>
                                <?php if (!$_isAdminColeta && ($c['status']??'agendado') === 'agendado'): ?>
                                <button type="button" class="btn btn-xs btn-outline-danger btn-cancelar-coleta" data-id="<?= (int)$c['id'] ?>" style="font-size:.75rem;padding:2px 8px"><?= __('admin.redirect.cancel', 'Cancelar') ?></button>
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

<!-- Modal agendar -->
<div class="modal fade" id="modalAgendar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= __('admin.redirect.schedule_collection', 'Agendar coleta') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label"><?= __('admin.redirect.step_shipment', 'Envio') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="agEnvioId">
                            <option value=""><?= __('admin.redirect.select_shipment', 'Selecione o envio...') ?></option>
                            <?php foreach ($enviosDisponiveis as $env): ?>
                            <option value="<?= (int)$env['id'] ?>">
                                #<?= (int)$env['id'] ?> — <?= __('admin.redirect.order_label', 'Pedido:') ?> <?= htmlspecialchars($env['id_pedido_cliente']??'',ENT_QUOTES,'UTF-8') ?>
                                <?php if (!empty($env['redirecionador_nome'])): ?> (<?= htmlspecialchars($env['redirecionador_nome'],ENT_QUOTES,'UTF-8') ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($enviosDisponiveis)): ?>
                        <div class="form-text text-muted"><?= __('admin.redirect.no_shipments_for_collection', 'Nenhum envio disponível para agendar coleta.') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.date', 'Data') ?> <span class="text-danger">*</span></label><input class="form-control" type="date" id="agData"></div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.time', 'Horário') ?> <span class="text-danger">*</span></label><input class="form-control" type="time" id="agHora"></div>
                    <div class="col-12"><label class="form-label"><?= __('admin.redirect.notes', 'Observações') ?></label><textarea class="form-control" id="agObs" rows="2"></textarea></div>
                </div>
                <div id="msgAgendar" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('admin.redirect.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" id="btnAgendar"><?= __('admin.redirect.schedule', 'Agendar') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal reagendar -->
<div class="modal fade" id="modalReagendar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= __('admin.redirect.reschedule_collection', 'Reagendar coleta') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="reId">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.new_date', 'Nova data') ?></label><input class="form-control" type="date" id="reData"></div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.new_time', 'Novo horário') ?></label><input class="form-control" type="time" id="reHora"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('admin.redirect.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" id="btnReagendar"><?= __('admin.redirect.save', 'Salvar') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnAgendar')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('envio_id', document.getElementById('agEnvioId').value);
    fd.append('data_agendada', document.getElementById('agData').value);
    fd.append('horario', document.getElementById('agHora').value);
    fd.append('observacoes', document.getElementById('agObs').value);
    const r = await fetch('/admin/redirecionamento/coletas/agendar',{method:'POST',body:fd});
    const j = await r.json();
    if (j.ok) location.reload();
    else document.getElementById('msgAgendar').innerHTML='<div class="alert alert-danger py-1 small">'+(j.msg||'<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>')+'</div>';
});

document.querySelectorAll('.btn-confirmar').forEach(btn => {
    btn.addEventListener('click', async () => {
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/redirecionamento/coletas/confirmar',{method:'POST',body:fd});
        const j = await r.json(); if (j.ok) location.reload();
    });
});

document.querySelectorAll('.btn-coletado').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('<?= htmlspecialchars(__('admin.redirect.confirm_mark_collected', 'Marcar como coletado?'), ENT_QUOTES, 'UTF-8') ?>')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/redirecionamento/coletas/coletado',{method:'POST',body:fd});
        const j = await r.json(); if (j.ok) location.reload();
    });
});

document.querySelectorAll('.btn-reagendar').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reId').value = btn.dataset.id;
        new bootstrap.Modal(document.getElementById('modalReagendar')).show();
    });
});

document.getElementById('btnReagendar')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('id', document.getElementById('reId').value);
    fd.append('data_agendada', document.getElementById('reData').value);
    fd.append('horario', document.getElementById('reHora').value);
    const r = await fetch('/admin/redirecionamento/coletas/reagendar',{method:'POST',body:fd});
    const j = await r.json(); if (j.ok) location.reload();
});

// Cancelar coleta (redirecionador)
document.querySelectorAll('.btn-cancelar-coleta').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('<?= htmlspecialchars(__('admin.redirect.confirm_cancel_collection', 'Cancelar esta coleta?'), ENT_QUOTES, 'UTF-8') ?>')) return;
        const fd = new FormData();
        fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/redirecionamento/coletas/cancelar', {method:'POST', body:fd});
        const j = await r.json();
        if (j.ok) location.reload();
        else alert(j.msg || '<?= htmlspecialchars(__('admin.redirect.error_cancelling', 'Erro ao cancelar'), ENT_QUOTES, 'UTF-8') ?>');
    });
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
