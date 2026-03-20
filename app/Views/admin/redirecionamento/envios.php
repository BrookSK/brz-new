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
                            <td><?= htmlspecialchars($e['tracking_code']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td class="pe-3">
                                <a class="btn btn-xs btn-outline-primary" href="/admin/redirecionamento/envios/<?= (int)$e['id'] ?>" style="font-size:.75rem;padding:2px 8px">Ver</a>
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
