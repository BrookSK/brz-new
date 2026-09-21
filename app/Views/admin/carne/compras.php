<?php $title = __('admin.carne_purchases.title', 'Compras do Carnê'); ?>
<?php $allModals = []; ?>
<?php
// Mapa de tradução dos status (compra + carnê)
$__statusCompraLabels = [
    'aguardando_compra'      => __('admin.carne_purchases.purchase_status.awaiting', 'Aguardando compra'),
    'comprado'               => __('admin.carne_purchases.purchase_status.purchased', 'Comprado'),
    'recebido'               => __('admin.carne_purchases.purchase_status.received', 'Recebido'),
    'produto_indisponivel'   => __('admin.carne_purchases.purchase_status.unavailable', 'Produto indisponível'),
];
$__carneStatusLabels = [
    'aguardando_primeira_parcela' => __('admin.carne_purchases.plan_status.awaiting_first', 'Aguardando 1ª parcela'),
    'ativo'          => __('admin.carne_purchases.plan_status.active', 'Ativo'),
    'em_andamento'   => __('admin.carne_purchases.plan_status.in_progress', 'Em andamento'),
    'com_atraso'     => __('admin.carne_purchases.plan_status.overdue', 'Com atraso'),
    'quitado'        => __('admin.carne_purchases.plan_status.paid_off', 'Quitado'),
    'liberado_envio' => __('admin.carne_purchases.plan_status.released', 'Liberado p/ envio'),
    'cancelado'      => __('admin.carne_purchases.plan_status.cancelled', 'Cancelado'),
];
$__labelStatusCompra = static function (string $s) use ($__statusCompraLabels): string {
    return $__statusCompraLabels[$s] ?? ucfirst(str_replace('_', ' ', $s));
};
$__labelCarneStatus = static function (string $s) use ($__carneStatusLabels): string {
    return $s === '' ? '' : ($__carneStatusLabels[$s] ?? ucfirst(str_replace('_', ' ', $s)));
};
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title"><?= htmlspecialchars(__('admin.carne_purchases.title', 'Compras do Carnê'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="page-subtitle"><?= htmlspecialchars(__('admin.carne_purchases.subtitle', 'Produtos de pedidos via carnê agrupados por mês'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/carnes" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i><?= htmlspecialchars(__('common.back', 'Voltar'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center"><div class="fs-3 fw-bold"><?= (int) ($stats['total'] ?? 0) ?></div><div class="text-muted small"><?= htmlspecialchars(__('admin.carne_purchases.stats.total_items', 'Total Itens'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center"><div class="fs-3 fw-bold text-warning"><?= (int) ($stats['aguardando'] ?? 0) ?></div><div class="text-muted small"><?= htmlspecialchars(__('admin.carne_purchases.stats.awaiting', 'Aguardando'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center"><div class="fs-3 fw-bold text-success"><?= (int) ($stats['comprado'] ?? 0) ?></div><div class="text-muted small"><?= htmlspecialchars(__('admin.carne_purchases.stats.purchased', 'Comprados'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center"><div class="fs-3 fw-bold text-info"><?= (int) ($stats['recebido'] ?? 0) ?></div><div class="text-muted small"><?= htmlspecialchars(__('admin.carne_purchases.stats.received', 'Recebidos'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
    </div>

    <!-- Filtro -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small"><?= htmlspecialchars(__('admin.carne_purchases.filter.purchase_status', 'Status da Compra'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="status" class="form-select form-select-sm">
                        <option value=""><?= htmlspecialchars(__('common.all', 'Todos'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="aguardando_compra" <?= ($filtroStatus ?? '') === 'aguardando_compra' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.carne_purchases.purchase_status.awaiting', 'Aguardando Compra'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="comprado" <?= ($filtroStatus ?? '') === 'comprado' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.carne_purchases.purchase_status.purchased', 'Comprado'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="recebido" <?= ($filtroStatus ?? '') === 'recebido' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.carne_purchases.purchase_status.received', 'Recebido'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small"><?= htmlspecialchars(__('admin.carne_purchases.filter.type_first', 'Tipo / 1a Parcela'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value=""><?= htmlspecialchars(__('common.all', 'Todos'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="primeira_paga" <?= ($filtroTipo ?? '') === 'primeira_paga' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.carne_purchases.filter.first_paid', '1a parcela paga'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="primeira_pendente" <?= ($filtroTipo ?? '') === 'primeira_pendente' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.carne_purchases.filter.first_pending', '1a parcela pendente'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="quitado" <?= ($filtroTipo ?? '') === 'quitado' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.carne_purchases.filter.paid_off', 'Quitados'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="com_atraso" <?= ($filtroTipo ?? '') === 'com_atraso' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.carne_purchases.plan_status.overdue', 'Com atraso'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small"><?= htmlspecialchars(__('admin.carne_purchases.filter.installments', 'Parcelas'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="parcelas" class="form-select form-select-sm">
                        <option value=""><?= htmlspecialchars(__('common.all_f', 'Todas'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php for ($i = 2; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($filtroParcelas ?? '') == $i ? 'selected' : '' ?>><?= $i ?>x</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i><?= htmlspecialchars(__('admin.carne_purchases.filter.apply', 'Filtrar'), ENT_QUOTES, 'UTF-8') ?></button>
                    <a href="/admin/carnes/compras" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($porMes)): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="fas fa-inbox fs-2 mb-2 d-block"></i><?= htmlspecialchars(__('admin.carne_purchases.empty', 'Nenhuma compra de carnê encontrada.'), ENT_QUOTES, 'UTF-8') ?></div></div>
    <?php else: ?>
        <?php
        $__monthKeys = ['01'=>'jan','02'=>'feb','03'=>'mar','04'=>'apr','05'=>'may','06'=>'jun','07'=>'jul','08'=>'aug','09'=>'sep','10'=>'oct','11'=>'nov','12'=>'dec'];
        $__monthPt   = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
        $meses = [];
        foreach ($__monthPt as $__mk => $__mpt) {
            $meses[$__mk] = __('common.month_abbr.' . $__monthKeys[$__mk], $__mpt);
        }
        ?>

        <!-- Tabelas por mês -->
        <?php foreach ($porMes as $mesKey => $itens):
            $parts = explode('-', $mesKey);
            $mesLabel = ($meses[$parts[1]] ?? $parts[1]) . '/' . $parts[0];
        ?>
        <div class="card border-0 shadow-sm mb-4" id="mes_<?= $mesKey ?>">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-calendar me-2"></i><?= $mesLabel ?> <span class="badge bg-light text-dark ms-2"><?= count($itens) ?> <?= htmlspecialchars(__('admin.carne_purchases.items', 'itens'), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="card-body p-0">
                <!-- Mobile: Cards -->
                <div class="d-md-none p-3">
                    <?php foreach ($itens as $ci):
                        $img = trim((string) ($ci['produto_imagem'] ?? ''));
                        if ($img !== '' && !preg_match('#^https?://#i', $img) && $img[0] !== '/') {
                            $img = '/uploads/produtos/' . $img;
                        }
                        $prodNome = (string) ($ci['produto_nome'] ?? ($ci['item_nome'] ?? 'Produto'));
                        $statusCompra = (string) ($ci['status_compra'] ?? 'aguardando_compra');
                        $carneStatus = (string) ($ci['carne_status'] ?? '');
                        $modalId = 'modal_' . (int) ($ci['id'] ?? 0) . '_' . (int) ($ci['produto_id'] ?? 0);
                        $stP1 = strtolower(trim((string) ($ci['status_primeira_parcela'] ?? '')));
                        $p1ProdPago = (int) ($ci['primeira_parcela_produtos_pago'] ?? 0);
                        $p1TaxaPago = (int) ($ci['primeira_parcela_taxas_pago'] ?? 0);
                    ?>
                    <div class="border rounded p-2 mb-2 d-flex align-items-center gap-2">
                        <?php if ($img): ?>
                            <img src="<?= htmlspecialchars($img) ?>" class="rounded border flex-shrink-0" style="width:40px;height:40px;object-fit:cover;" onerror="this.style.display='none'">
                        <?php else: ?>
                            <div class="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;"><i class="fas fa-box text-muted"></i></div>
                        <?php endif; ?>
                        <div class="flex-grow-1" style="min-width:0;overflow:hidden;">
                            <div class="fw-semibold text-truncate" style="font-size:12px;"><?= htmlspecialchars($prodNome) ?></div>
                            <div class="d-flex align-items-center gap-1 flex-wrap" style="font-size:10px;">
                                <span class="text-muted text-truncate" style="max-width:100px;"><?= htmlspecialchars($ci['cliente_nome'] ?? '') ?></span>
                                <span class="badge bg-light text-dark border" style="font-size:9px;"><?= (int) ($ci['parcelas_pagas'] ?? 0) ?>/<?= (int) ($ci['quantidade_parcelas'] ?? 0) ?></span>
                                <?php if ($stP1 === 'paga'): ?><span class="text-success"><i class="fas fa-check-circle"></i></span><?php else: ?><span class="text-danger"><i class="fas fa-times-circle"></i></span><?php endif; ?>
                                <span class="badge bg-<?= $statusCompra === 'comprado' ? 'success' : ($statusCompra === 'recebido' ? 'info' : 'warning') ?>" style="font-size:9px;"><?= htmlspecialchars($__labelStatusCompra($statusCompra), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <div class="d-flex flex-shrink-0 gap-1">
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>"><i class="fas fa-eye"></i></button>
                            <?php if ($statusCompra === 'aguardando_compra'): ?>
                            <button type="button" class="btn btn-outline-success btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#comprar_<?= $modalId ?>"><i class="fas fa-check"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    $allModals[] = ['id' => $modalId, 'ci' => $ci, 'prodNome' => $prodNome, 'carneStatus' => $carneStatus, 'statusCompra' => $statusCompra];
                    ?>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop: Table -->
                <div class="d-none d-md-block table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= htmlspecialchars(__('admin.carne_purchases.col.product', 'Produto'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.carne_purchases.col.customer', 'Cliente'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.carne_purchases.col.plan', 'Carnê'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th class="d-none d-lg-table-cell"><?= htmlspecialchars(__('admin.carne_purchases.col.plan_status', 'Status Carnê'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th class="d-none d-xl-table-cell"><?= htmlspecialchars(__('admin.carne_purchases.col.start', 'Início'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th class="d-none d-xl-table-cell"><?= htmlspecialchars(__('admin.carne_purchases.col.estimated_end', 'Fim Estimado'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.carne_purchases.col.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th class="text-end"><?= htmlspecialchars(__('admin.carne_purchases.col.actions', 'Ações'), ENT_QUOTES, 'UTF-8') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $ci):
                                $img = trim((string) ($ci['produto_imagem'] ?? ''));
                                if ($img !== '' && !preg_match('#^https?://#i', $img) && $img[0] !== '/') {
                                    $img = '/uploads/produtos/' . $img;
                                }
                                $prodNome = (string) ($ci['produto_nome'] ?? ($ci['item_nome'] ?? 'Produto'));
                                $statusCompra = (string) ($ci['status_compra'] ?? 'aguardando_compra');
                                $carneStatus = (string) ($ci['carne_status'] ?? '');
                                $modalId = 'modal_' . (int) ($ci['id'] ?? 0) . '_' . (int) ($ci['produto_id'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($img): ?>
                                            <img src="<?= htmlspecialchars($img) ?>" class="rounded border" style="width:34px;height:34px;object-fit:cover;" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:34px;height:34px;"><i class="fas fa-box text-muted small"></i></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold" style="font-size:12px;"><?= htmlspecialchars($prodNome) ?></div>
                                            <div class="text-muted" style="font-size:10px;"><?= htmlspecialchars(__('admin.carne_purchases.qty', 'Qtd'), ENT_QUOTES, 'UTF-8') ?>: <?= (int) ($ci['quantidade'] ?? 1) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small"><?= htmlspecialchars($ci['cliente_nome'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark"><?= (int) ($ci['parcelas_pagas'] ?? 0) ?>/<?= (int) ($ci['quantidade_parcelas'] ?? 0) ?></span>
                                    <?php
                                        $stP1 = strtolower(trim((string) ($ci['status_primeira_parcela'] ?? '')));
                                        $p1ProdPago = (int) ($ci['primeira_parcela_produtos_pago'] ?? 0);
                                        $p1TaxaPago = (int) ($ci['primeira_parcela_taxas_pago'] ?? 0);
                                    ?>
                                    <?php if ($stP1 === 'paga'): ?>
                                        <div style="font-size:10px;" class="text-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars(__('admin.carne_purchases.first_paid', '1ª paga'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php elseif ($p1ProdPago || $p1TaxaPago): ?>
                                        <div style="font-size:10px;" class="text-warning"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars(__('admin.carne_purchases.first_partial', '1ª parcial'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php else: ?>
                                        <div style="font-size:10px;" class="text-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars(__('admin.carne_purchases.first_pending', '1ª pendente'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell"><span class="badge bg-<?= $carneStatus === 'quitado' ? 'success' : ($carneStatus === 'com_atraso' ? 'danger' : 'primary') ?>" style="font-size:10px;"><?= htmlspecialchars($__labelCarneStatus($carneStatus), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="d-none d-xl-table-cell small text-muted"><?= !empty($ci['data_inicio']) ? date('d/m/Y', strtotime($ci['data_inicio'])) : '-' ?></td>
                                <td class="d-none d-xl-table-cell small text-muted"><?= !empty($ci['data_fim_estimada']) ? date('d/m/Y', strtotime($ci['data_fim_estimada'])) : '-' ?></td>
                                <td><span class="badge bg-<?= $statusCompra === 'comprado' ? 'success' : ($statusCompra === 'recebido' ? 'info' : 'warning') ?>" style="font-size:10px;"><?= htmlspecialchars($__labelStatusCompra($statusCompra), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>" title="<?= htmlspecialchars(__('admin.carne_purchases.action.view_details', 'Ver detalhes'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-eye"></i></button>
                                        <?php if ($statusCompra === 'aguardando_compra'): ?>
                                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#comprar_<?= $modalId ?>" title="<?= htmlspecialchars(__('admin.carne_purchases.action.mark_purchased', 'Marcar comprado'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-check"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php
                            $allModals[] = ['id' => $modalId, 'ci' => $ci, 'prodNome' => $prodNome, 'carneStatus' => $carneStatus, 'statusCompra' => $statusCompra];
                            ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modais (fora da tabela) -->
<?php foreach ($allModals as $m): ?>
<!-- Modal Ver Detalhes -->
<div class="modal fade" id="<?= $m['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i><?= htmlspecialchars(__('admin.carne_purchases.modal.details', 'Detalhes'), ENT_QUOTES, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm mb-0">
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.modal.order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?></th><td><a href="/admin/pedidos/detalhes/<?= (int) $m['ci']['pedido_id'] ?>">#<?= (int) $m['ci']['pedido_id'] ?></a></td></tr>
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.col.customer', 'Cliente'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars($m['ci']['cliente_nome'] ?? '') ?></td></tr>
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.col.product', 'Produto'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars($m['prodNome']) ?> (<?= htmlspecialchars(__('admin.carne_purchases.qty', 'Qtd'), ENT_QUOTES, 'UTF-8') ?>: <?= (int) ($m['ci']['quantidade'] ?? 1) ?>)</td></tr>
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.modal.plan_total', 'Total Carnê'), ENT_QUOTES, 'UTF-8') ?></th><td>R$ <?= number_format((float) ($m['ci']['total_geral'] ?? 0), 2, ',', '.') ?></td></tr>
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.filter.installments', 'Parcelas'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars(__('admin.carne_purchases.modal.paid_of', '{paid} pagas de {total}', ['paid' => (int) ($m['ci']['parcelas_pagas'] ?? 0), 'total' => (int) ($m['ci']['quantidade_parcelas'] ?? 0)]), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.col.plan_status', 'Status Carnê'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars($__labelCarneStatus($m['carneStatus']), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.col.start', 'Início'), ENT_QUOTES, 'UTF-8') ?></th><td><?= !empty($m['ci']['data_inicio']) ? date('d/m/Y', strtotime($m['ci']['data_inicio'])) : '-' ?></td></tr>
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.col.estimated_end', 'Fim Estimado'), ENT_QUOTES, 'UTF-8') ?></th><td><?= !empty($m['ci']['data_fim_estimada']) ? date('d/m/Y', strtotime($m['ci']['data_fim_estimada'])) : '-' ?></td></tr>
                    <tr><th><?= htmlspecialchars(__('admin.carne_purchases.modal.purchase_status', 'Status Compra'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars($__labelStatusCompra($m['statusCompra']), ENT_QUOTES, 'UTF-8') ?></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <a href="/admin/carnes/detalhes/<?= (int) ($m['ci']['carne_id'] ?? 0) ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye me-1"></i><?= htmlspecialchars(__('admin.carne_purchases.modal.view_plan', 'Ver Carnê'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/admin/pedidos/detalhes/<?= (int) $m['ci']['pedido_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-external-link-alt me-1"></i><?= htmlspecialchars(__('admin.carne_purchases.modal.view_order', 'Ver Pedido'), ENT_QUOTES, 'UTF-8') ?></a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= htmlspecialchars(__('common.close', 'Fechar'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Compra -->
<?php if ($m['statusCompra'] === 'aguardando_compra'): ?>
<div class="modal fade" id="comprar_<?= $m['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2 text-success"></i><?= htmlspecialchars(__('admin.carne_purchases.modal.confirm_purchase', 'Confirmar Compra'), ENT_QUOTES, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="bg-light rounded p-3 mb-3">
                    <div class="fw-semibold"><?= htmlspecialchars($m['prodNome']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars(__('admin.carne_purchases.modal.quantity', 'Quantidade'), ENT_QUOTES, 'UTF-8') ?>: <?= (int) ($m['ci']['quantidade'] ?? 1) ?> · <?= htmlspecialchars(__('admin.carne_purchases.col.customer', 'Cliente'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($m['ci']['cliente_nome'] ?? '') ?></div>
                    <div class="small text-muted"><?= htmlspecialchars(__('admin.carne_purchases.modal.order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?> #<?= (int) $m['ci']['pedido_id'] ?> · <?= htmlspecialchars(__('admin.carne_purchases.filter.installments', 'Parcelas'), ENT_QUOTES, 'UTF-8') ?>: <?= (int) ($m['ci']['parcelas_pagas'] ?? 0) ?>/<?= (int) ($m['ci']['quantidade_parcelas'] ?? 0) ?></div>
                </div>
                <p class="mb-2 fw-semibold"><?= htmlspecialchars(__('admin.carne_purchases.modal.how_to_mark', 'Como deseja marcar?'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="d-flex flex-column gap-2">
                    <form method="POST" action="/admin/carnes/marcar-comprado/<?= (int) $m['ci']['id'] ?>">
                        <button type="submit" class="btn btn-success w-100"><i class="fas fa-check-double me-1"></i><?= htmlspecialchars(__('admin.carne_purchases.modal.full_purchase', 'Compra Total'), ENT_QUOTES, 'UTF-8') ?> (<?= (int) ($m['ci']['quantidade'] ?? 1) ?> <?= htmlspecialchars(__('admin.carne_purchases.modal.units', 'un.'), ENT_QUOTES, 'UTF-8') ?>)</button>
                    </form>
                    <form method="POST" action="/admin/carnes/marcar-comprado/<?= (int) $m['ci']['id'] ?>" class="border rounded p-2 bg-light">
                        <input type="hidden" name="parcial" value="1">
                        <div class="d-flex align-items-center gap-2">
                            <label class="small fw-semibold text-nowrap mb-0"><?= htmlspecialchars(__('admin.carne_purchases.modal.qty_purchased', 'Qtd comprada:'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input type="number" name="quantidade_comprada" class="form-control form-control-sm" style="max-width:80px;" min="1" max="<?= (int) ($m['ci']['quantidade'] ?? 1) ?>" value="1" required>
                            <span class="small text-muted text-nowrap"><?= htmlspecialchars(__('admin.carne_purchases.modal.of', 'de'), ENT_QUOTES, 'UTF-8') ?> <?= (int) ($m['ci']['quantidade'] ?? 1) ?></span>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 mt-2"><i class="fas fa-check me-1"></i><?= htmlspecialchars(__('admin.carne_purchases.modal.partial_purchase', 'Compra Parcial'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= htmlspecialchars(__('common.cancel', 'Cancelar'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>
