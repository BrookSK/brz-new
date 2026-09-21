<?php
/**
 * Remessa Internacional - Listagem flat de pedidos com filtros
 * Variáveis disponíveis: $pedidos, $janelas, $stats, $filtros, $usdToBrl
 */
$filtroJanela = $filtros['janela_id'] ?? '';
$filtroStatus = $filtros['janela_status'] ?? '';
$filtroEtiqueta = $filtros['etiqueta'] ?? '';
$filtroBusca = $filtros['busca'] ?? '';

// Mapa de tradução dos status da janela
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
    <h1 class="page-title"><?= htmlspecialchars(__('admin.intl_shipment.title', 'Remessa Internacional'), ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-info btn-sm" onclick="location.reload()">
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
                <a href="/admin/remessa-internacional" class="btn btn-sm btn-outline-secondary w-100"><?= htmlspecialchars(__('common.clear', 'Limpar'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Pedidos -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-list me-2"></i><?= htmlspecialchars(__('admin.intl_shipment.orders', 'Pedidos'), ENT_QUOTES, 'UTF-8') ?> (<?= count($pedidos) ?>)</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.customer', 'Cliente'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.entered_window', 'Entrou na Janela'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.total', 'Total'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.window', 'Janela'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.window_status', 'Status Janela'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.label', 'Etiqueta'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(__('admin.intl_shipment.col.actions', 'Ações'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><?= htmlspecialchars(__('admin.intl_shipment.empty', 'Nenhum pedido encontrado.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($pedidos as $p):
                        $pid = (int)($p['pedido_id'] ?? 0);
                        $et = (int)($p['etiqueta_gerada'] ?? 0);
                        $jId = (int)($p['janela_id'] ?? 0);
                        $jStatus = (string)($p['janela_status'] ?? '');
                        $jBadge = match($jStatus) { 'aberta' => 'success', 'atraso' => 'danger', 'remessa_gerada' => 'info', 'finalizada' => 'secondary', default => 'light' };
                        $dt = !empty($p['entrou_janela_em']) ? date('d/m/Y H:i', strtotime($p['entrou_janela_em'])) : '-';
                        $dtPedido = !empty($p['pedido_criado_em']) ? date('d/m/Y H:i', strtotime($p['pedido_criado_em'])) : '';
                        $moeda = strtoupper(trim((string)($p['moeda'] ?? ($p['currency'] ?? 'USD'))));
                        $total = (float)($p['total'] ?? 0);
                        if ($moeda === 'BRL' && $usdToBrl > 0) $total = $total / $usdToBrl;
                        $wxStatus = (string)($p['wexpress_status'] ?? '');
                        $wxTrack = (string)($p['wexpress_tracking_number'] ?? '');
                        $wxCourier = (string)($p['courier_tracking_number'] ?? '');
                        $wxShipId = (string)($p['wexpress_shipping_id'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <a href="/admin/pedidos/detalhes/<?= $pid ?>" class="fw-bold text-decoration-none">#<?= str_pad((string)$pid, 6, '0', STR_PAD_LEFT) ?></a>
                            <?php if ($dtPedido): ?><div class="text-muted small"><?= htmlspecialchars(__('admin.intl_shipment.order_date', 'Pedido'), ENT_QUOTES, 'UTF-8') ?>: <?= $dtPedido ?></div><?php endif; ?>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width:150px;"><?= htmlspecialchars($p['cliente_nome'] ?? 'N/A') ?></div>
                            <div class="text-muted small text-truncate" style="max-width:150px;"><?= htmlspecialchars($p['cliente_email'] ?? '') ?></div>
                        </td>
                        <td class="small"><?= $dt ?></td>
                        <td class="fw-semibold">US$ <?= number_format($total, 2, '.', ',') ?></td>
                        <td>
                            <a href="/admin/remessa-internacional/janela/<?= $jId ?>" class="badge bg-light text-dark border text-decoration-none">#<?= $jId ?></a>
                            <div class="text-muted small"><?= !empty($p['janela_inicio']) ? date('d/m', strtotime($p['janela_inicio'])) . '-' . date('d/m', strtotime($p['janela_fim'])) : '' ?></div>
                        </td>
                        <td><span class="badge bg-<?= $jBadge ?>"><?= htmlspecialchars($__labelJanelaStatus($jStatus), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <?php if ($et): ?>
                                <span class="badge bg-success"><?= htmlspecialchars(__('admin.intl_shipment.label.generated', 'Gerada'), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($wxStatus): ?><div class="small text-muted"><?= htmlspecialchars($wxStatus) ?></div><?php endif; ?>
                                <?php if ($wxCourier): ?><div class="small text-muted"><?= htmlspecialchars(__('admin.intl_shipment.tracking', 'Tracking'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($wxCourier) ?></div><?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><?= htmlspecialchars(__('admin.intl_shipment.label.pending', 'Pendente'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="/admin/remessa-internacional/janela/<?= $jId ?>/pedido/<?= $pid ?>" class="btn btn-outline-primary" title="<?= htmlspecialchars(__('admin.intl_shipment.action.details', 'Detalhes'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-eye"></i></a>
                                <?php if (!$et): ?>
                                <a href="/admin/remessa-internacional/janela/<?= $jId ?>/pedido/<?= $pid ?>" class="btn btn-outline-success" title="<?= htmlspecialchars(__('admin.intl_shipment.action.mark_label', 'Marcar etiqueta'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-tag"></i></a>
                                <?php endif; ?>
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
