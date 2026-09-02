<?php
$sidebarActive = 'redirecionamento-envios';
$title = __('admin.redirect.redirector_shipments', 'Envios de Redirecionadores');
$envios = is_array($envios ?? null) ? $envios : [];
$redirecionadores = is_array($redirecionadores ?? null) ? $redirecionadores : [];
$filtroStatus = $filtroStatus ?? '';
$filtroRed = (int)($filtroRed ?? 0);
$filtroData = $filtroData ?? '';

$statusLabels = [
    'rascunho'=>[__('admin.redirect.status_draft','Rascunho'),'secondary'],
    'aguardando_pagamento'=>[__('admin.redirect.status_awaiting_payment','Aguard. pagamento'),'warning'],
    'pago'=>[__('admin.redirect.status_paid','Pago'),'success'],
    'etiqueta_gerada'=>[__('admin.redirect.status_label_generated','Etiqueta gerada'),'info'],
    'coletado'=>[__('admin.redirect.status_collected','Coletado'),'primary'],
    'entregue'=>[__('admin.redirect.status_delivered','Entregue'),'dark'],
    'divergencia'=>[__('admin.redirect.status_divergence','Divergência'),'danger'],
    'cancelado'=>[__('admin.redirect.status_cancelled','Cancelado'),'secondary'],
];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><?= __('admin.redirect.redirector_shipments', 'Envios de Redirecionadores') ?></h1>
            <div class="text-muted small"><?= count($envios) ?> <?= __('admin.redirect.shipments_found_suffix', 'envio(s) encontrado(s)') ?></div>
        </div>
        <a class="btn btn-primary btn-sm" href="/admin/redirecionamento/envios/novo"><i class="fas fa-plus me-1"></i><?= __('admin.redirect.new_shipment_short', 'Novo envio') ?></a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="/admin/redirecionamento/envios" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small"><?= __('admin.redirect.status', 'Status') ?></label>
                    <select class="form-select form-select-sm" name="status">
                        <option value=""><?= __('admin.redirect.all', 'Todos') ?></option>
                        <?php foreach ($statusLabels as $k=>[$l,$c]): ?>
                        <option value="<?= $k ?>" <?= $filtroStatus===$k?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?= __('admin.redirect.redirector', 'Redirecionador') ?></label>
                    <select class="form-select form-select-sm" name="redirecionador_id">
                        <option value=""><?= __('admin.redirect.all', 'Todos') ?></option>
                        <?php foreach ($redirecionadores as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $filtroRed==(int)$r['id']?'selected':'' ?>><?= htmlspecialchars($r['nome'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?= __('admin.redirect.date', 'Data') ?></label>
                    <input class="form-control form-control-sm" type="date" name="data" value="<?= htmlspecialchars($filtroData,ENT_QUOTES,'UTF-8') ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="fas fa-filter me-1"></i><?= __('admin.redirect.filter', 'Filtrar') ?></button>
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/redirecionamento/envios"><?= __('admin.redirect.clear', 'Limpar') ?></a>
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
                            <th><?= __('admin.redirect.client_order_id', 'ID pedido cliente') ?></th>
                            <th><?= __('admin.redirect.redirector', 'Redirecionador') ?></th>
                            <th><?= __('admin.redirect.final_client', 'Cliente final') ?></th>
                            <th><?= __('admin.redirect.weight_declared_real', 'Peso inf. / real') ?></th>
                            <th><?= __('admin.redirect.charged_amount', 'Valor cobrado') ?></th>
                            <th><?= __('admin.redirect.payment', 'Pagamento') ?></th>
                            <th><?= __('admin.redirect.status', 'Status') ?></th>
                            <th><?= __('admin.redirect.tracking', 'Rastreio') ?></th>
                            <th class="pe-3"><?= __('admin.redirect.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($envios)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4"><?= __('admin.redirect.no_shipments_found', 'Nenhum envio encontrado.') ?></td></tr>
                        <?php else: foreach ($envios as $e):
                            [$sl,$sc] = $statusLabels[$e['status']??'rascunho'] ?? ['?','secondary'];
                            $pagLabel = ['pendente'=>__('admin.redirect.pay_status_pending','Pendente'),'pago'=>__('admin.redirect.pay_status_paid','Pago'),'falhou'=>__('admin.redirect.pay_status_failed','Falhou'),'reembolsado'=>__('admin.redirect.pay_status_refunded','Reembolsado')][$e['status_pagamento']??'pendente'] ?? '-';
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
                            <td class="pe-3">
                                <a class="btn btn-xs btn-outline-primary" href="/admin/redirecionamento/envios/<?= (int)$e['id'] ?>" style="font-size:.75rem;padding:2px 8px"><?= __('admin.redirect.view', 'Ver') ?></a>
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
