<?php
$sidebarActive = 'redirecionamento-envios';
$title = 'Envios de Redirecionadores';
$envios = is_array($envios ?? null) ? $envios : [];
$redirecionadores = is_array($redirecionadores ?? null) ? $redirecionadores : [];
$filtroStatus = $filtroStatus ?? '';
$filtroRed = (int)($filtroRed ?? 0);
$filtroData = $filtroData ?? '';

$statusLabels = [
    'rascunho'=>['Rascunho','secondary'],
    'aguardando_pagamento'=>['Aguard. pagamento','warning'],
    'pago'=>['Pago','success'],
    'etiqueta_gerada'=>['Etiqueta gerada','info'],
    'coletado'=>['Coletado','primary'],
    'entregue'=>['Entregue','dark'],
    'divergencia'=>['Divergência','danger'],
    'cancelado'=>['Cancelado','secondary'],
];
$_perfilEnvios = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
$_isRedirecionador = ($_perfilEnvios === 'redirecionador');
$enderecoSede = trim((string)($enderecoSede ?? '1227 W Broad St, Saint Pauls, NC 28384'));
// Status em que faz sentido o redirecionador declarar que enviou o pacote à sede
$_statusEnviaSede = ['pago','etiqueta_gerada'];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Envios de Redirecionadores</h1>
            <div class="text-muted small"><?= count($envios) ?> envio(s) encontrado(s)</div>
        </div>
        <a class="btn btn-primary btn-sm" href="/admin/redirecionamento/envios/novo"><i class="fas fa-plus me-1"></i>Novo envio</a>
    </div>

    <?php if ($_isRedirecionador): ?>
    <div class="alert alert-info d-flex align-items-start gap-2 border-0 shadow-sm">
        <i class="fas fa-dolly fa-lg mt-1"></i>
        <div>
            <strong>Vai enviar o pacote você mesmo?</strong>
            Em vez de agendar coleta, despache para a nossa sede e clique em <strong>"Enviei à sede"</strong> no envio.
            <div class="mt-1"><i class="fas fa-map-marker-alt me-1 text-primary"></i>Endereço de recebimento: <strong><?= htmlspecialchars($enderecoSede, ENT_QUOTES, 'UTF-8') ?></strong></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="/admin/redirecionamento/envios" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select class="form-select form-select-sm" name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statusLabels as $k=>[$l,$c]): ?>
                        <option value="<?= $k ?>" <?= $filtroStatus===$k?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Redirecionador</label>
                    <select class="form-select form-select-sm" name="redirecionador_id">
                        <option value="">Todos</option>
                        <?php foreach ($redirecionadores as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $filtroRed==(int)$r['id']?'selected':'' ?>><?= htmlspecialchars($r['nome'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Data</label>
                    <input class="form-control form-control-sm" type="date" name="data" value="<?= htmlspecialchars($filtroData,ENT_QUOTES,'UTF-8') ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="fas fa-filter me-1"></i>Filtrar</button>
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/redirecionamento/envios">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>ID pedido cliente</th>
                            <th>Redirecionador</th>
                            <th>Cliente final</th>
                            <th>Peso inf. / real</th>
                            <th>Valor cobrado</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th>Rastreio</th>
                            <th class="pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($envios)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Nenhum envio encontrado.</td></tr>
                        <?php else: foreach ($envios as $e):
                            [$sl,$sc] = $statusLabels[$e['status']??'rascunho'] ?? ['?','secondary'];
                            $pagLabel = ['pendente'=>'Pendente','pago'=>'Pago','falhou'=>'Falhou','reembolsado'=>'Reembolsado'][$e['status_pagamento']??'pendente'] ?? '-';
                            $pagColor = ['pendente'=>'warning','pago'=>'success','falhou'=>'danger','reembolsado'=>'info'][$e['status_pagamento']??'pendente'] ?? 'secondary';
                        ?>
                        <tr>
                            <td class="ps-3"><?= (int)$e['id'] ?></td>
                            <td><?= htmlspecialchars($e['id_pedido_cliente']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($e['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($e['cliente_nome']??$e['destinatario_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= number_format((float)($e['peso_kg']??0),2,',','.') ?> / <?= $e['peso_real_kg']?number_format((float)$e['peso_real_kg'],2,',','.'):'—' ?> kg</td>
                            <td>US$ <?= number_format((float)($e['valor_cobrado_usd']??0),2,',','.') ?></td>
                            <td><span class="badge bg-<?= $pagColor ?> bg-opacity-10 text-<?= $pagColor ?> border border-<?= $pagColor ?> border-opacity-25"><?= $pagLabel ?></span></td>
                            <td><span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25"><?= $sl ?></span></td>
                            <td>
                                <?= htmlspecialchars($e['tracking_code']??'',ENT_QUOTES,'UTF-8') ?>
                                <?php if (!empty($e['etiqueta_request_json'])):
                                    $__rd = json_decode($e['etiqueta_request_json'], true);
                                    $__cs = (string) ($__rd['codigoServico'] ?? ($__rd['service_code'] ?? ''));
                                    $__ms = ['03220'=>'SEDEX','04162'=>'SEDEX','04014'=>'SEDEX','03298'=>'PAC','04510'=>'PAC','41106'=>'PAC','03158'=>'SEDEX 10','03140'=>'SEDEX 12','03204'=>'SEDEX Hoje'];
                                    $__lb = $__ms[$__cs] ?? '';
                                    if ($__lb !== ''):
                                        $__bc = (stripos($__lb,'SEDEX')!==false)?'danger':'primary';
                                ?>
                                <span class="badge bg-<?= $__bc ?> ms-1" style="font-size:.65rem"><?= $__lb ?></span>
                                <?php endif; endif; ?>
                            </td>
                            <td class="pe-3 text-nowrap">
                                <a class="btn btn-xs btn-outline-primary" href="/admin/redirecionamento/envios/<?= (int)$e['id'] ?>" style="font-size:.75rem;padding:2px 8px">Ver</a>
                                <?php if ($_isRedirecionador && in_array($e['status'] ?? '', $_statusEnviaSede, true) && empty($e['enviado_sede_em'])): ?>
                                <button type="button" class="btn btn-xs btn-outline-info btn-enviei-sede" data-id="<?= (int)$e['id'] ?>" style="font-size:.75rem;padding:2px 8px"><i class="fas fa-dolly me-1"></i>Enviei à sede</button>
                                <?php elseif (!empty($e['enviado_sede_em'])): ?>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" title="Enviado à sede em <?= htmlspecialchars((string)$e['enviado_sede_em'], ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-dolly me-1"></i>Enviado à sede</span>
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

<!-- Modal: marcar enviado à sede -->
<div class="modal fade" id="modalEnvieiSede" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-dolly me-2 text-info"></i>Enviei o pacote para a sede</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="esEnvioId">
                <p class="text-muted small mb-3">Confirme que você despachou este pacote para o nosso endereço de recebimento. Nossa equipe será notificada.</p>
                <div class="alert alert-light border small mb-3">
                    <i class="fas fa-map-marker-alt me-1 text-primary"></i><strong>Endereço:</strong> <?= htmlspecialchars($enderecoSede, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <label class="form-label">Código de rastreio (opcional)</label>
                <input type="text" class="form-control" id="esTracking" placeholder="Rastreio da transportadora que você usou">
                <div class="form-text">Se você tiver o rastreio do envio até a sede, informe para agilizarmos o recebimento.</div>
                <div id="msgEnvieiSede" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info text-white" id="btnConfirmarEnvieiSede">Confirmar envio</button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-enviei-sede').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('esEnvioId').value = btn.dataset.id;
        document.getElementById('esTracking').value = '';
        document.getElementById('msgEnvieiSede').innerHTML = '';
        new bootstrap.Modal(document.getElementById('modalEnvieiSede')).show();
    });
});

document.getElementById('btnConfirmarEnvieiSede')?.addEventListener('click', async () => {
    const id = document.getElementById('esEnvioId').value;
    if (!id) return;
    const fd = new FormData();
    fd.append('tracking_code', document.getElementById('esTracking').value);
    const r = await fetch('/admin/redirecionamento/envios/' + id + '/enviado-sede', {method:'POST', body:fd});
    const j = await r.json();
    if (j.ok) {
        location.reload();
    } else {
        document.getElementById('msgEnvieiSede').innerHTML = '<div class="alert alert-danger py-1 small">' + (j.msg || 'Erro') + '</div>';
    }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
