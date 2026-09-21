<?php
/**
 * Conferência de Remessa - Listagem flat de pedidos com filtros
 * Variáveis disponíveis: $pedidos, $janelas, $stats, $filtros
 */
$filtroJanela = $filtros['janela_id'] ?? '';
$filtroStatus = $filtros['janela_status'] ?? '';
$filtroEtiqueta = $filtros['etiqueta'] ?? '';
$filtroBusca = $filtros['busca'] ?? '';

// Mapa de tradução dos status da janela (reutiliza chaves da remessa internacional)
$__janelaStatusLabels = [
    'aberta'         => __('admin.intl_shipment.window_status.open', 'Aberta'),
    'finalizada'     => __('admin.intl_shipment.window_status.finished', 'Finalizada'),
    'atraso'         => __('admin.intl_shipment.window_status.overdue', 'Atraso'),
    'remessa_gerada' => __('admin.intl_shipment.window_status.shipment_generated', 'Remessa Gerada'),
];
$__labelJanelaStatus = static function (string $s) use ($__janelaStatusLabels): string {
    return $__janelaStatusLabels[$s] ?? ucfirst(str_replace('_', ' ', $s));
};
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="page-title"><?= htmlspecialchars(__('admin.shipment_check.title', 'Conferência de Remessa'), ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="location.reload()">
            <i class="fas fa-sync me-1"></i><?= htmlspecialchars(__('admin.intl_shipment.refresh', 'Atualizar'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
            <div class="text-muted small"><?= htmlspecialchars(__('admin.intl_shipment.stats.total_orders', 'Total Pedidos'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="fs-4 fw-bold"><?= (int)($stats['total_pedidos'] ?? 0) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
            <div class="text-muted small"><?= htmlspecialchars(__('admin.intl_shipment.stats.labels_generated', 'Etiquetas Geradas'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="fs-4 fw-bold text-success"><?= (int)($stats['etiquetas_geradas'] ?? 0) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
            <div class="text-muted small"><?= htmlspecialchars(__('admin.intl_shipment.stats.pending', 'Pendentes'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="fs-4 fw-bold text-warning"><?= (int)($stats['etiquetas_pendentes'] ?? 0) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
            <div class="text-muted small"><?= htmlspecialchars(__('admin.intl_shipment.stats.overdue_windows', 'Janelas em Atraso'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="fs-4 fw-bold text-danger"><?= (int)($stats['janelas_atraso'] ?? 0) ?></div>
        </div></div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0"><?= htmlspecialchars(__('admin.intl_shipment.filter.window', 'Janela'), ENT_QUOTES, 'UTF-8') ?></label>
                <select name="janela_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value=""><?= htmlspecialchars(__('common.all_f', 'Todas'), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php foreach ($janelas as $j): ?>
                    <option value="<?= (int)$j['id'] ?>" <?= $filtroJanela == $j['id'] ? 'selected' : '' ?>>
                        #<?= $j['id'] ?> (<?= date('d/m', strtotime($j['data_inicio'])) ?>-<?= date('d/m', strtotime($j['data_fim'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0"><?= htmlspecialchars(__('admin.intl_shipment.filter.window_status', 'Status Janela'), ENT_QUOTES, 'UTF-8') ?></label>
                <select name="janela_status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value=""><?= htmlspecialchars(__('common.all', 'Todos'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="aberta" <?= $filtroStatus === 'aberta' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.intl_shipment.window_status.open', 'Aberta'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="finalizada" <?= $filtroStatus === 'finalizada' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.intl_shipment.window_status.finished', 'Finalizada'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="atraso" <?= $filtroStatus === 'atraso' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.intl_shipment.window_status.overdue_filter', 'Em Atraso'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="remessa_gerada" <?= $filtroStatus === 'remessa_gerada' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.intl_shipment.window_status.shipment_generated', 'Remessa Gerada'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0"><?= htmlspecialchars(__('admin.intl_shipment.filter.label', 'Etiqueta'), ENT_QUOTES, 'UTF-8') ?></label>
                <select name="etiqueta" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value=""><?= htmlspecialchars(__('common.all_f', 'Todas'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="pendente" <?= $filtroEtiqueta === 'pendente' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.intl_shipment.label.pending', 'Pendente'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="gerada" <?= $filtroEtiqueta === 'gerada' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.intl_shipment.label.generated', 'Gerada'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-0"><?= htmlspecialchars(__('admin.intl_shipment.filter.search', 'Buscar'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" name="busca" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="<?= htmlspecialchars(__('admin.intl_shipment.search_placeholder', 'Pedido # ou cliente...'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-6 col-md-2 col-lg-1">
                <a href="/admin/remessa-conferencia" class="btn btn-sm btn-outline-secondary w-100"><?= htmlspecialchars(__('common.clear', 'Limpar'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Pedidos -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-list me-2"></i><?= htmlspecialchars(__('admin.intl_shipment.orders', 'Pedidos'), ENT_QUOTES, 'UTF-8') ?> (<?= count($pedidos) ?>)</strong>
        <button type="button" class="btn btn-sm btn-outline-success" id="btnBaixarMassa" disabled onclick="baixarDocumentosMassa()">
            <i class="fas fa-file-archive me-1"></i><?= htmlspecialchars(__('admin.shipment_check.download_documents', 'Baixar documentos'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:30px"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.shipment_check.col.datetime', 'Data/Hora'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.customer', 'Cliente'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.shipment_check.col.zip', 'ZIP/CEP'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.shipment_check.col.qty', 'Qtd'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.window', 'Janela'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.window_status', 'Status Janela'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.label', 'Etiqueta'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.actions', 'Ações'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4"><?= htmlspecialchars(__('admin.intl_shipment.empty', 'Nenhum pedido encontrado.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($pedidos as $p):
                        $pid = (int)($p['pedido_id'] ?? 0);
                        $et = (int)($p['etiqueta_gerada'] ?? 0);
                        $jId = (int)($p['janela_id'] ?? 0);
                        $jStatus = (string)($p['janela_status'] ?? '');
                        $jBadge = match($jStatus) { 'aberta' => 'success', 'atraso' => 'danger', 'remessa_gerada' => 'info', 'finalizada' => 'secondary', default => 'light' };
                        $dt = !empty($p['created_at']) ? date('d/m/Y H:i', strtotime($p['created_at'])) : '-';
                        $cep = (string)($p['cep_entrega'] ?? '');
                        $qtd = $p['qtd_itens'] !== null ? (int)$p['qtd_itens'] : '-';
                        $wxStatus = (string)($p['wexpress_status'] ?? '');
                        $wxTrack = (string)($p['wexpress_tracking_number'] ?? '');
                        $wxCourier = (string)($p['courier_tracking_number'] ?? '');
                        $wxShipId = (string)($p['wexpress_shipping_id'] ?? '');
                    ?>
                    <tr>
                        <td><input type="checkbox" class="pedido-check" value="<?= $pid ?>" data-janela="<?= $jId ?>" onchange="updateBtnBaixar()"></td>
                        <td><a href="/admin/pedidos/detalhes/<?= $pid ?>" class="fw-bold text-decoration-none">#<?= str_pad((string)$pid, 6, '0', STR_PAD_LEFT) ?></a></td>
                        <td class="small"><?= $dt ?></td>
                        <td>
                            <div class="text-truncate" style="max-width:140px;"><?= htmlspecialchars($p['cliente_nome'] ?? 'N/A') ?></div>
                        </td>
                        <td class="small"><?= htmlspecialchars($cep) ?></td>
                        <td><?= $qtd ?></td>
                        <td>
                            <a href="/admin/remessa-conferencia/janela/<?= $jId ?>" class="badge bg-light text-dark border text-decoration-none">#<?= $jId ?></a>
                        </td>
                        <td><span class="badge bg-<?= $jBadge ?>"><?= htmlspecialchars($__labelJanelaStatus($jStatus), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <?php if ($et): ?>
                                <span class="badge bg-success"><?= htmlspecialchars(__('admin.intl_shipment.label.generated', 'Gerada'), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($wxCourier): ?><div class="small text-muted"><?= htmlspecialchars($wxCourier) ?></div><?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><?= htmlspecialchars(__('admin.intl_shipment.label.pending', 'Pendente'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($et && $wxShipId): ?>
                                <a href="https://label.wexpress.me/wexpress-premium/?shipping_id=<?= rawurlencode($wxShipId) ?>" target="_blank" class="btn btn-outline-info" title="<?= htmlspecialchars(__('admin.intl_shipment.filter.label', 'Etiqueta'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-tag"></i></a>
                                <?php endif; ?>
                                <a href="/admin/remessa-conferencia/janela/<?= $jId ?>/pedido/<?= $pid ?>" class="btn btn-outline-primary" title="<?= htmlspecialchars(__('admin.intl_shipment.action.details', 'Detalhes'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-eye"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.SHIPMENT_CHECK_I18N = {
    download_documents: <?= json_encode(__('admin.shipment_check.download_documents', 'Baixar documentos'), JSON_UNESCAPED_UNICODE) ?>,
    generating_zip: <?= json_encode(__('admin.shipment_check.generating_zip', 'Gerando ZIP...'), JSON_UNESCAPED_UNICODE) ?>,
    select_at_least_one: <?= json_encode(__('admin.shipment_check.select_at_least_one', 'Selecione ao menos um pedido.'), JSON_UNESCAPED_UNICODE) ?>
};
function toggleAll(el) {
    document.querySelectorAll('.pedido-check').forEach(function(cb) { cb.checked = el.checked; });
    updateBtnBaixar();
}
function updateBtnBaixar() {
    var checked = document.querySelectorAll('.pedido-check:checked');
    var btn = document.getElementById('btnBaixarMassa');
    var lbl = window.SHIPMENT_CHECK_I18N.download_documents;
    if (btn) {
        btn.disabled = checked.length === 0;
        btn.innerHTML = checked.length > 0
            ? '<i class="fas fa-file-archive me-1"></i>' + lbl + ' (' + checked.length + ')'
            : '<i class="fas fa-file-archive me-1"></i>' + lbl;
    }
}
function baixarDocumentosMassa() {
    var checked = document.querySelectorAll('.pedido-check:checked');
    if (checked.length === 0) { alert(window.SHIPMENT_CHECK_I18N.select_at_least_one); return; }
    var ids = [];
    var janelaId = 0;
    checked.forEach(function(cb) {
        ids.push(cb.value);
        if (!janelaId) janelaId = cb.getAttribute('data-janela') || '0';
    });
    var btn = document.getElementById('btnBaixarMassa');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + window.SHIPMENT_CHECK_I18N.generating_zip; }
    window.location.href = '/admin/remessa-conferencia/exportar-documentos?pedidos=' + encodeURIComponent(ids.join(','));
    setTimeout(function() { if (btn) { btn.disabled = false; updateBtnBaixar(); } }, 8000);
}
</script>
