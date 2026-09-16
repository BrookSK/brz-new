<?php
$statusLabels = [
    'aguardando_primeira_parcela' => ['label' => __('admin.installment.status_awaiting_first', 'Aguardando 1ª Parcela'), 'cor' => 'info'],
    'ativo' => ['label' => __('admin.installment.status_active', 'Ativo'), 'cor' => 'primary'],
    'em_andamento' => ['label' => __('admin.installment.status_in_progress', 'Em Andamento'), 'cor' => 'primary'],
    'com_atraso' => ['label' => __('admin.installment.status_overdue', 'Com Atraso'), 'cor' => 'danger'],
    'quitado' => ['label' => __('admin.installment.status_paid_off', 'Quitado'), 'cor' => 'success'],
    'inadimplente' => ['label' => __('admin.installment.status_defaulted', 'Inadimplente'), 'cor' => 'dark'],
    'liberado_envio' => ['label' => __('admin.installment.status_released_shipping', 'Liberado p/ Envio'), 'cor' => 'success'],
    'encerrado' => ['label' => __('admin.installment.status_closed', 'Encerrado'), 'cor' => 'secondary'],
    'cancelado' => ['label' => __('admin.installment.status_cancelled', 'Cancelado'), 'cor' => 'secondary'],
];
$carnes = $carnes ?? [];
$stats = $stats ?? [];
$comprasPendentes = $comprasPendentes ?? [];
$enviosPendentes = $enviosPendentes ?? [];
$cobrancas = $cobrancas ?? [];
$atividadeRecente = $atividadeRecente ?? [];
$filtros = $filtros ?? [];
$activeTab = $filtros['tab'] ?? 'carnes';

function fmtBrl($v) { return 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.'); }

// Contadores para filtros rápidos
$countAtrasados = count(array_filter($carnes, fn($c) => in_array($c['status'] ?? '', ['com_atraso','inadimplente'])));
$countAguardando1 = count(array_filter($carnes, fn($c) => ($c['status'] ?? '') === 'aguardando_primeira_parcela'));
$countQuitados = count(array_filter($carnes, fn($c) => ($c['status'] ?? '') === 'quitado'));
?>

<!-- Header -->
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-1"><?= __('admin.installment.title', 'Gestão de Carnês') ?></h4>
            <p class="text-muted small mb-0 d-none d-md-block"><?= __('admin.installment.subtitle', 'Painel completo do ciclo de carnê') ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/admin/carnes/arquivados" class="btn btn-outline-secondary btn-sm"><i class="fas fa-archive me-1"></i><span class="d-none d-md-inline"><?= __('admin.installment.archived', 'Arquivados') ?></span></a>
            <a href="/admin/carnes/logs" class="btn btn-outline-info btn-sm"><i class="fas fa-history me-1"></i><span class="d-none d-md-inline"><?= __('admin.installment.logs', 'Logs') ?></span></a>
            <a href="/admin/carnes/configuracoes" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cog me-1"></i><span class="d-none d-md-inline"><?= __('admin.installment.settings', 'Configurações') ?></span></a>
            <a href="/admin" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i><span class="d-none d-md-inline"><?= __('admin.installment.back', 'Voltar') ?></span></a>
        </div>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small"><?= __('admin.installment.total_financed', 'Total Financiado') ?></div><div class="fs-4 fw-bold"><?= fmtBrl($stats['total_financiado'] ?? 0) ?></div></div>
                        <i class="fas fa-coins fs-3 text-primary opacity-25"></i>
                    </div>
                    <div class="text-muted small mt-1"><?= __('admin.installment.plans_in_period', '{n} carnês no período', ['n' => (int)($stats['total'] ?? 0)]) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small"><?= __('admin.installment.already_received', 'Já Recebido') ?></div><div class="fs-4 fw-bold text-success"><?= fmtBrl($stats['total_recebido'] ?? 0) ?></div></div>
                        <i class="fas fa-check-circle fs-3 text-success opacity-25"></i>
                    </div>
                    <?php $pctRecebido = ($stats['total_financiado'] ?? 0) > 0 ? round(($stats['total_recebido'] ?? 0) / $stats['total_financiado'] * 100) : 0; ?>
                    <div class="progress mt-2" style="height:4px;"><div class="progress-bar bg-success" style="width:<?= $pctRecebido ?>%"></div></div>
                    <div class="text-muted small mt-1"><?= __('admin.installment.pct_of_total_received', '{n}% do total recebido', ['n' => $pctRecebido]) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small"><?= __('admin.installment.open_balance', 'Em Aberto') ?></div><div class="fs-4 fw-bold text-warning"><?= fmtBrl($stats['total_aberto'] ?? 0) ?></div></div>
                        <i class="fas fa-clock fs-3 text-warning opacity-25"></i>
                    </div>
                    <div class="text-muted small mt-1"><?= __('admin.installment.to_receive_next_months', 'a receber nos próximos meses') ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small"><?= __('admin.installment.overdue', 'Em Atraso') ?></div><div class="fs-4 fw-bold text-danger"><?= fmtBrl($stats['total_atraso'] ?? 0) ?></div></div>
                        <i class="fas fa-exclamation-triangle fs-3 text-danger opacity-25"></i>
                    </div>
                    <div class="text-muted small mt-1"><?= __('admin.installment.plans_delinquency', '{n} carnês · inadimplência {p}%', ['n' => $countAtrasados, 'p' => (($stats['total_financiado'] ?? 0) > 0 ? round(($stats['total_atraso'] ?? 0) / $stats['total_financiado'] * 100, 1) : 0)]) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mini stats row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 d-flex align-items-center justify-content-between"><div><div class="fw-bold fs-5"><?= $countAguardando1 ?></div><div class="text-muted small"><?= __('admin.installment.awaiting_first_short', 'Aguard. 1ª Parcela') ?></div></div><i class="fas fa-hourglass-half text-info"></i></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 d-flex align-items-center justify-content-between"><div><div class="fw-bold fs-5"><?= (int)($stats['vence_7_dias'] ?? 0) ?></div><div class="text-muted small"><?= __('admin.installment.due_in_7_days', 'Vencem em 7 dias') ?></div></div><i class="fas fa-calendar-day text-warning"></i></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 d-flex align-items-center justify-content-between"><div><div class="fw-bold fs-5"><?= (int)($stats['compras_pendentes'] ?? 0) ?></div><div class="text-muted small"><?= __('admin.installment.pending_purchases', 'Compras Pendentes') ?></div></div><i class="fas fa-shopping-cart text-primary"></i></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 d-flex align-items-center justify-content-between"><div><div class="fw-bold fs-5"><?= (int)($stats['envios_pendentes'] ?? 0) ?></div><div class="text-muted small"><?= __('admin.installment.pending_shipments', 'Envios Pendentes') ?></div></div><i class="fas fa-truck text-success"></i></div></div></div>
    </div>

    <!-- Tabs: Mobile dropdown + Desktop tabs -->
    <div class="d-md-none mb-3">
        <select class="form-select" onchange="switchCarneTab(this.value)">
            <option value="tab-carnes" <?= $activeTab === 'carnes' ? 'selected' : '' ?>><?= __('admin.installment.tab_plans', 'Carnês') ?> (<?= count($carnes) ?>)</option>
            <option value="tab-cobrancas" <?= $activeTab === 'cobrancas' ? 'selected' : '' ?>><?= __('admin.installment.tab_charges', 'Cobranças') ?> (<?= count($cobrancas) ?>)</option>
            <option value="tab-compras" <?= $activeTab === 'compras' ? 'selected' : '' ?>><?= __('admin.installment.tab_purchases', 'Compras') ?> (<?= count($comprasPendentes) ?>)</option>
            <option value="tab-envios" <?= $activeTab === 'envios' ? 'selected' : '' ?>><?= __('admin.installment.tab_shipments', 'Envios') ?> (<?= count($enviosPendentes) ?>)</option>
        </select>
    </div>
    <ul class="nav nav-tabs mb-0 d-none d-md-flex" role="tablist">
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'carnes' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-carnes" type="button"><?= __('admin.installment.tab_plans', 'Carnês') ?> <span class="badge bg-secondary ms-1"><?= count($carnes) ?></span></button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'cobrancas' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-cobrancas" type="button"><?= __('admin.installment.tab_charges', 'Cobranças') ?> <span class="badge bg-danger ms-1"><?= count($cobrancas) ?></span></button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'compras' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-compras" type="button"><?= __('admin.installment.tab_purchases', 'Compras') ?> <span class="badge bg-warning text-dark ms-1"><?= count($comprasPendentes) ?></span></button></li>
        <li class="nav-item"><button class="nav-link <?= $activeTab === 'envios' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-envios" type="button"><?= __('admin.installment.tab_shipments', 'Envios') ?> <span class="badge bg-success ms-1"><?= count($enviosPendentes) ?></span></button></li>
    </ul>
    <script>
    function switchCarneTab(tabId) {
        var btn = document.querySelector('[data-bs-target="#' + tabId + '"]');
        if (btn) btn.click();
    }
    </script>

    <div class="tab-content">
    <!-- TAB: Carnês -->
    <div class="tab-pane fade <?= $activeTab === 'carnes' ? 'show active' : '' ?>" id="tab-carnes" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-0 rounded-bottom">
            <div class="card-body">
                <!-- Filtros combinatórios -->
                <form method="GET" class="row g-2 mb-3" id="carneFilterForm">
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="" <?= empty($filtros['status']) && empty($filtros['com_atraso']) && empty($filtros['liberado_compra']) && empty($filtros['liberado_envio']) ? 'selected' : '' ?>><?= __('admin.installment.filter_all', 'Todos') ?> (<?= count($carnes) ?>)</option>
                            <option value="aguardando_primeira_parcela" <?= ($filtros['status'] ?? '') === 'aguardando_primeira_parcela' ? 'selected' : '' ?>><?= __('admin.installment.filter_awaiting_first', 'Aguardando 1ª') ?> (<?= $countAguardando1 ?>)</option>
                            <option value="em_andamento" <?= ($filtros['status'] ?? '') === 'em_andamento' ? 'selected' : '' ?>><?= __('admin.installment.status_in_progress', 'Em Andamento') ?></option>
                            <option value="quitado" <?= ($filtros['status'] ?? '') === 'quitado' ? 'selected' : '' ?>><?= __('admin.installment.filter_paid_off', 'Quitados') ?> (<?= $countQuitados ?>)</option>
                            <?php foreach ($statusLabels as $k => $v): if (in_array($k, ['aguardando_primeira_parcela','em_andamento','quitado'])) continue; ?>
                            <option value="<?= $k ?>" <?= ($filtros['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="filtro_rapido" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value=""><?= __('admin.installment.filter_situation', 'Situação') ?></option>
                            <option value="com_atraso" <?= !empty($filtros['com_atraso']) ? 'selected' : '' ?>><?= __('admin.installment.filter_overdue', 'Atrasados') ?> (<?= $countAtrasados ?>)</option>
                            <option value="liberado_compra" <?= !empty($filtros['liberado_compra']) ? 'selected' : '' ?>><?= __('admin.installment.filter_awaiting_purchase', 'Aguardando compra') ?></option>
                            <option value="liberado_envio" <?= !empty($filtros['liberado_envio']) ? 'selected' : '' ?>><?= __('admin.installment.filter_ready_shipping', 'Prontos p/ envio') ?></option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="text" name="cliente" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['cliente'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('admin.installment.search_customer', 'Buscar cliente...'), ENT_QUOTES, 'UTF-8') ?>" oninput="clearTimeout(this._t);this._t=setTimeout(()=>this.form.submit(),400)">
                    </div>
                    <div class="col-6 col-md-2">
                        <input type="text" name="pedido_id" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['pedido_id'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('admin.installment.order_hash', 'Pedido #'), ENT_QUOTES, 'UTF-8') ?>" oninput="clearTimeout(this._t);this._t=setTimeout(()=>this.form.submit(),400)">
                    </div>
                    <div class="col-6 col-md-2">
                        <a href="/admin/carnes" class="btn btn-sm btn-outline-secondary w-100"><?= __('admin.installment.clear', 'Limpar') ?></a>
                    </div>
                </form>

                <!-- Recriar carnê -->
                <div class="mb-3">
                    <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="collapse" data-bs-target="#recriarCarneCollapse"><i class="fas fa-plus-circle me-1"></i><?= __('admin.installment.recreate_plan', 'Recriar carnê') ?></button>
                </div>

                <!-- Recriar Carnê (collapse) -->
                <div class="collapse mb-3" id="recriarCarneCollapse">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small"><?= __('admin.installment.order_id', 'ID do Pedido') ?></label>
                                    <input type="number" id="recriar-pedido-id" class="form-control form-control-sm" placeholder="<?= htmlspecialchars(__('admin.installment.order_id_placeholder', 'Ex: 715'), ENT_QUOTES, 'UTF-8') ?>" min="1">
                                </div>
                                <div class="col-md-auto">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="buscarDadosPedidoCarne()"><i class="fas fa-search me-1"></i><?= __('admin.installment.search', 'Buscar') ?></button>
                                </div>
                            </div>
                            <div id="recriar-resultado" class="mt-3" style="display:none;">
                                <div id="recriar-info" class="alert alert-info small"></div>
                                <form method="POST" action="/admin/carnes/recriar" id="recriar-form" onsubmit="return validarCriacaoCarne()">
                                    <input type="hidden" name="pedido_id" id="recriar-form-pedido-id">
                                    <div class="row g-2 align-items-end" id="recriar-form-campos">
                                        <div class="col-md-3">
                                            <label class="form-label small"><?= __('admin.installment.installments', 'Parcelas') ?></label>
                                            <select name="quantidade_parcelas" id="recriar-parcelas" class="form-select form-select-sm">
                                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?= $i ?>" <?= $i === 4 ? 'selected' : '' ?>><?= $i ?>x</option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-auto">
                                            <button type="submit" id="recriar-btn-submit" class="btn btn-warning btn-sm"><i class="fas fa-plus me-1"></i><?= __('admin.installment.create_plan', 'Criar Carnê') ?></button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div id="recriar-erro" class="mt-2 alert alert-danger small" style="display:none;"></div>
                            <small class="text-muted d-block mt-2"><?= __('admin.installment.recreate_hint', 'Para pedidos com forma_pagamento = carne_braziliana que não tiveram o carnê criado automaticamente.') ?></small>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Carnês -->

                <!-- Mobile: Cards -->
                <div class="d-md-none p-3">
                    <?php if (empty($carnes)): ?>
                        <div class="text-center text-muted py-4 small"><?= __('admin.installment.none_found', 'Nenhum carnê encontrado.') ?></div>
                    <?php else: ?>
                        <?php foreach ($carnes as $c):
                            $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'cor' => 'secondary'];
                            $pagas = (int)($c['parcelas_pagas'] ?? 0);
                            $totalParcelas = (int)($c['quantidade_parcelas'] ?? 1);
                        ?>
                        <div class="border rounded p-2 mb-2 d-flex align-items-center gap-2">
                            <div class="flex-grow-1" style="min-width:0;overflow:hidden;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold" style="font-size:11px;">#<?= $c['id'] ?></span>
                                    <a href="/admin/pedidos/detalhes/<?= $c['pedido_id'] ?>" class="text-decoration-none fw-semibold" style="font-size:11px;">Ped #<?= $c['pedido_id'] ?></a>
                                </div>
                                <div class="text-truncate text-muted" style="font-size:11px;"><?= htmlspecialchars($c['cliente_nome'] ?? '') ?></div>
                                <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                    <span class="badge bg-light text-dark border" style="font-size:10px;"><?= $pagas ?>/<?= $totalParcelas ?></span>
                                    <?php if (($c['parcelas_atrasadas'] ?? 0) > 0): ?>
                                    <span class="badge bg-danger" style="font-size:9px;"><?= __('admin.installment.overdue_count', '{n} atraso', ['n' => $c['parcelas_atrasadas']]) ?></span>
                                    <?php endif; ?>
                                    <span class="badge bg-<?= $st['cor'] ?>" style="font-size:9px;"><?= $st['label'] ?></span>
                                </div>
                            </div>
                            <a href="/admin/carnes/detalhes/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary flex-shrink-0 py-0 px-1"><i class="fas fa-eye"></i></a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Desktop: Table -->
                <div class="d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0" id="carnesTable">
                        <thead class="table-light">
                            <tr>
                                <th class="sortable" data-sort="id" style="cursor:pointer;"><?= __('admin.installment.col_id', 'ID') ?> <i class="fas fa-sort text-muted ms-1" style="font-size:10px;"></i></th>
                                <th class="sortable" data-sort="cliente" style="cursor:pointer;"><?= __('admin.installment.col_order_customer', 'Pedido / Cliente') ?> <i class="fas fa-sort text-muted ms-1" style="font-size:10px;"></i></th>
                                <th class="text-end d-none d-lg-table-cell sortable" data-sort="total" style="cursor:pointer;"><?= __('admin.installment.col_total', 'Total') ?> <i class="fas fa-sort text-muted ms-1" style="font-size:10px;"></i></th>
                                <th class="text-end d-none d-lg-table-cell sortable" data-sort="pago" style="cursor:pointer;"><?= __('admin.installment.col_paid_balance', 'Pago / Saldo') ?> <i class="fas fa-sort text-muted ms-1" style="font-size:10px;"></i></th>
                                <th class="sortable" data-sort="parcelas" style="cursor:pointer;"><?= __('admin.installment.installments', 'Parcelas') ?> <i class="fas fa-sort text-muted ms-1" style="font-size:10px;"></i></th>
                                <th class="d-none d-md-table-cell sortable" data-sort="proximo_vencimento" style="cursor:pointer;"><?= __('admin.installment.col_next_due', 'Próx. Vencimento') ?> <i class="fas fa-sort text-muted ms-1" style="font-size:10px;"></i></th>
                                <th class="d-none d-md-table-cell sortable" data-sort="ultimo_vencimento" style="cursor:pointer;"><?= __('admin.installment.col_last_due', 'Último Venc.') ?> <i class="fas fa-sort text-muted ms-1" style="font-size:10px;"></i></th>
                                <th class="sortable" data-sort="status" style="cursor:pointer;"><?= __('admin.installment.col_status', 'Status') ?> <i class="fas fa-sort text-muted ms-1" style="font-size:10px;"></i></th>
                                <th class="d-none d-xl-table-cell"><?= __('admin.installment.col_purchase', 'Compra') ?></th><th class="d-none d-xl-table-cell"><?= __('admin.installment.col_shipping', 'Envio') ?></th><th><?= __('admin.installment.col_actions', 'Ações') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($carnes)): ?>
                            <tr><td colspan="11" class="text-center text-muted py-4"><?= __('admin.installment.none_found', 'Nenhum carnê encontrado.') ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($carnes as $c):
                                $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'cor' => 'secondary'];
                                $pagas = (int)($c['parcelas_pagas'] ?? 0);
                                $totalParcelas = (int)($c['quantidade_parcelas'] ?? 1);
                                $pago = ($stats['total_financiado'] ?? 0) > 0 ? ($c['total_geral'] * $pagas / max($totalParcelas, 1)) : 0;
                                $saldo = (float)$c['total_geral'] - $pago;
                            ?>
                            <tr data-id="<?= $c['id'] ?>"
                                data-cliente="<?= htmlspecialchars(strtolower($c['cliente_nome'] ?? '')) ?>"
                                data-total="<?= (float)$c['total_geral'] ?>"
                                data-pago="<?= (float)$pago ?>"
                                data-parcelas="<?= $pagas ?>"
                                data-proximo-vencimento="<?= $c['proximo_vencimento'] ?? '9999-12-31' ?>"
                                data-ultimo-vencimento="<?= $c['ultimo_vencimento'] ?? '9999-12-31' ?>"
                                data-status="<?= htmlspecialchars($c['status'] ?? '') ?>">
                                <td class="fw-semibold"><?= $c['id'] ?></td>
                                <td>
                                    <a href="/admin/pedidos/detalhes/<?= $c['pedido_id'] ?>" class="text-decoration-none fw-semibold">#<?= $c['pedido_id'] ?></a>
                                    <div class="text-muted small text-truncate" style="max-width:120px;"><?= htmlspecialchars($c['cliente_nome'] ?? '') ?></div>
                                </td>
                                <td class="text-end fw-semibold d-none d-lg-table-cell"><?= fmtBrl($c['total_geral']) ?></td>
                                <td class="text-end d-none d-lg-table-cell">
                                    <span class="text-success small"><?= fmtBrl($pago) ?></span>
                                    <div class="text-muted small"><?= fmtBrl($saldo) ?> <?= __('admin.installment.remaining', 'restante') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $pagas ?>/<?= $totalParcelas ?></span>
                                    <?php if (($c['parcelas_atrasadas'] ?? 0) > 0): ?>
                                    <span class="badge bg-danger ms-1"><?= $c['parcelas_atrasadas'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell"><?= !empty($c['proximo_vencimento']) ? date('d/m/Y', strtotime($c['proximo_vencimento'])) : '-' ?></td>
                                <td class="d-none d-md-table-cell"><?= !empty($c['ultimo_vencimento']) ? date('d/m/Y', strtotime($c['ultimo_vencimento'])) : '-' ?></td>
                                <td><span class="badge bg-<?= $st['cor'] ?>"><?= $st['label'] ?></span></td>
                                <td class="d-none d-xl-table-cell"><?= !empty($c['compra_interna_liberada']) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-clock text-muted"></i>' ?></td>
                                <td class="d-none d-xl-table-cell"><?= !empty($c['envio_liberado']) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-lock text-muted"></i>' ?></td>
                                <td><a href="/admin/carnes/detalhes/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars(__('admin.installment.details', 'Detalhes'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-eye"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>

                <!-- Script de ordenação da tabela -->
                <script>
                (function(){
                    var table = document.getElementById('carnesTable');
                    if (!table) return;
                    var headers = table.querySelectorAll('th.sortable');
                    var currentSort = '';
                    var currentDir = 'asc';

                    headers.forEach(function(th) {
                        th.addEventListener('click', function() {
                            var sortKey = this.getAttribute('data-sort');
                            if (currentSort === sortKey) {
                                currentDir = currentDir === 'asc' ? 'desc' : 'asc';
                            } else {
                                currentSort = sortKey;
                                currentDir = 'asc';
                            }

                            // Update icons
                            headers.forEach(function(h) {
                                var icon = h.querySelector('i');
                                if (icon) icon.className = 'fas fa-sort text-muted ms-1';
                            });
                            var activeIcon = this.querySelector('i');
                            if (activeIcon) activeIcon.className = 'fas fa-sort-' + (currentDir === 'asc' ? 'up' : 'down') + ' text-primary ms-1';

                            // Sort rows
                            var tbody = table.querySelector('tbody');
                            var rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
                            rows.sort(function(a, b) {
                                var aVal, bVal;
                                switch(sortKey) {
                                    case 'id':
                                        aVal = parseInt(a.getAttribute('data-id')) || 0;
                                        bVal = parseInt(b.getAttribute('data-id')) || 0;
                                        break;
                                    case 'cliente':
                                        aVal = a.getAttribute('data-cliente') || '';
                                        bVal = b.getAttribute('data-cliente') || '';
                                        return currentDir === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                                    case 'total':
                                        aVal = parseFloat(a.getAttribute('data-total')) || 0;
                                        bVal = parseFloat(b.getAttribute('data-total')) || 0;
                                        break;
                                    case 'pago':
                                        aVal = parseFloat(a.getAttribute('data-pago')) || 0;
                                        bVal = parseFloat(b.getAttribute('data-pago')) || 0;
                                        break;
                                    case 'parcelas':
                                        aVal = parseInt(a.getAttribute('data-parcelas')) || 0;
                                        bVal = parseInt(b.getAttribute('data-parcelas')) || 0;
                                        break;
                                    case 'proximo_vencimento':
                                        aVal = a.getAttribute('data-proximo-vencimento') || '9999-12-31';
                                        bVal = b.getAttribute('data-proximo-vencimento') || '9999-12-31';
                                        return currentDir === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                                    case 'ultimo_vencimento':
                                        aVal = a.getAttribute('data-ultimo-vencimento') || '9999-12-31';
                                        bVal = b.getAttribute('data-ultimo-vencimento') || '9999-12-31';
                                        return currentDir === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                                    case 'status':
                                        aVal = a.getAttribute('data-status') || '';
                                        bVal = b.getAttribute('data-status') || '';
                                        return currentDir === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                                    default:
                                        aVal = 0; bVal = 0;
                                }
                                if (currentDir === 'asc') return aVal - bVal;
                                return bVal - aVal;
                            });
                            rows.forEach(function(row) { tbody.appendChild(row); });
                        });
                    });
                })();
                </script>
            </div>
        </div>
    </div>

    <!-- TAB: Cobranças -->
    <div class="tab-pane fade <?= $activeTab === 'cobrancas' ? 'show active' : '' ?>" id="tab-cobrancas" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-0 rounded-bottom">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th><?= __('admin.installment.col_customer_order', 'Cliente / Pedido') ?></th><th class="text-end"><?= __('admin.installment.col_installment_value', 'Valor parcela') ?></th><th><?= __('admin.installment.col_due_date', 'Vencimento') ?></th><th><?= __('admin.installment.col_situation', 'Situação') ?></th><th><?= __('admin.installment.col_delay', 'Atraso') ?></th><th><?= __('admin.installment.col_actions', 'Ações') ?></th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cobrancas)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4"><?= __('admin.installment.no_pending_charges', 'Nenhuma cobrança pendente.') ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($cobrancas as $cob):
                                $venc = strtotime($cob['vencimento']);
                                $hoje = strtotime(date('Y-m-d'));
                                $diasAtraso = ($hoje > $venc) ? (int)(($hoje - $venc) / 86400) : 0;
                                $situacao = $diasAtraso > 0 ? __('admin.installment.overdue_label', 'Com atraso') : ($venc == $hoje ? __('admin.installment.due_today', 'Vence hoje') : __('admin.installment.due_in_days', 'Em {n} dias', ['n' => (int)(($venc - $hoje) / 86400)]));
                                $corSit = $diasAtraso > 0 ? 'danger' : ($venc == $hoje ? 'warning' : 'info');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($cob['cliente_nome'] ?? '') ?></div>
                                    <div class="text-muted small">#<?= $cob['pedido_id'] ?? '' ?> · parcela <?= $cob['numero_parcela'] ?? '' ?></div>
                                </td>
                                <td class="text-end fw-semibold"><?= fmtBrl($cob['valor_total'] ?? 0) ?></td>
                                <td><?= date('d/m/Y', $venc) ?></td>
                                <td><span class="badge bg-<?= $corSit ?>"><?= $situacao ?></span></td>
                                <td><?= $diasAtraso > 0 ? __('admin.installment.days_count', '{n} dias', ['n' => $diasAtraso]) : '—' ?></td>
                                <td>
                                    <form method="POST" action="/admin/carnes/enviar-cobranca/<?= $cob['id'] ?>" class="d-inline" onsubmit="return confirm('<?= htmlspecialchars(__('admin.installment.confirm_send_charge', 'Enviar cobrança?'), ENT_QUOTES, 'UTF-8') ?>')">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="<?= htmlspecialchars(__('admin.installment.send_charge', 'Enviar cobrança'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-paper-plane"></i></button>
                                    </form>
                                    <form method="POST" action="/admin/carnes/reemitir-boleto/<?= $cob['id'] ?>" class="d-inline ms-1">
                                        <button type="submit" class="btn btn-sm btn-outline-info" title="<?= htmlspecialchars(__('admin.installment.reissue_boleto', 'Reemitir boleto'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-redo"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: Compras Internas -->
    <div class="tab-pane fade <?= $activeTab === 'compras' ? 'show active' : '' ?>" id="tab-compras" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-0 rounded-bottom">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><?= __('admin.installment.pending_purchases', 'Compras Pendentes') ?></h6>
                    <a href="/admin/carnes/compras" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i><?= __('admin.installment.view_full', 'Ver completo') ?></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th><?= __('admin.installment.col_plan_order', 'Carnê / Pedido') ?></th><th><?= __('admin.installment.col_customer', 'Cliente') ?></th><th><?= __('admin.installment.col_status', 'Status') ?></th><th><?= __('admin.installment.col_created_at', 'Criado em') ?></th><th><?= __('admin.installment.col_actions', 'Ações') ?></th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($comprasPendentes)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><?= __('admin.installment.no_pending_purchases', 'Nenhuma compra pendente.') ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($comprasPendentes as $cp): ?>
                            <tr>
                                <td><span class="fw-semibold">#<?= $cp['carne_id'] ?></span> <span class="text-muted small">/ Pedido #<?= $cp['pedido_id'] ?? '' ?></span></td>
                                <td><?= htmlspecialchars($cp['cliente_nome'] ?? '') ?></td>
                                <td><span class="badge bg-warning text-dark"><?= __('admin.installment.filter_awaiting_purchase', 'Aguardando compra') ?></span></td>
                                <td class="small text-muted"><?= !empty($cp['created_at']) ? date('d/m/Y', strtotime($cp['created_at'])) : '-' ?></td>
                                <td>
                                    <form method="POST" action="/admin/carnes/marcar-comprado/<?= $cp['id'] ?>" class="d-inline" onsubmit="return confirm('<?= htmlspecialchars(__('admin.installment.confirm_mark_purchased', 'Marcar como comprado?'), ENT_QUOTES, 'UTF-8') ?>')">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="<?= htmlspecialchars(__('admin.installment.mark_purchased', 'Marcar comprado'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-check"></i></button>
                                    </form>
                                    <a href="/admin/carnes/detalhes/<?= $cp['carne_id'] ?>" class="btn btn-sm btn-outline-primary ms-1" title="<?= htmlspecialchars(__('admin.installment.view_plan', 'Ver carnê'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: Envios -->
    <div class="tab-pane fade <?= $activeTab === 'envios' ? 'show active' : '' ?>" id="tab-envios" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-0 rounded-bottom">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th><?= __('admin.installment.col_customer_order', 'Cliente / Pedido') ?></th><th class="text-end"><?= __('admin.installment.col_total', 'Total') ?></th><th><?= __('admin.installment.col_financial_status', 'Status Financ.') ?></th><th><?= __('admin.installment.col_shipping_status', 'Status Envio') ?></th><th><?= __('admin.installment.col_actions', 'Ações') ?></th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enviosPendentes)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><?= __('admin.installment.no_pending_shipments', 'Nenhum envio pendente.') ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($enviosPendentes as $env):
                                $stEnv = $statusLabels[$env['status']] ?? ['label' => $env['status'], 'cor' => 'secondary'];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($env['cliente_nome'] ?? '') ?></div>
                                    <div class="text-muted small">#<?= $env['pedido_id'] ?? '' ?></div>
                                </td>
                                <td class="text-end fw-semibold"><?= fmtBrl($env['total_geral'] ?? 0) ?></td>
                                <td><span class="badge bg-<?= $stEnv['cor'] ?>"><?= $stEnv['label'] ?></span></td>
                                <td><span class="badge bg-success"><?= __('admin.installment.ready_for_shipping', 'Pronto p/ envio') ?></span></td>
                                <td>
                                    <a href="/admin/carnes/detalhes/<?= $env['id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars(__('admin.installment.view_plan', 'Ver carnê'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-eye"></i></a>
                                    <a href="/admin/pedidos/detalhes/<?= $env['pedido_id'] ?>" class="btn btn-sm btn-outline-secondary ms-1" title="<?= htmlspecialchars(__('admin.installment.view_order', 'Ver pedido'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-box"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    </div><!-- /tab-content -->

    <!-- Atividade Recente -->
    <?php if (!empty($atividadeRecente)): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-stream me-2"></i><?= __('admin.installment.recent_activity', 'Atividade recente') ?></h6></div>
        <div class="card-body pt-0">
            <div class="list-group list-group-flush">
                <?php
                /**
                 * Traduz a descrição de uma atividade do carnê a partir do seu tipo.
                 * As descrições são gravadas em PT no banco; aqui reconstruímos a versão
                 * traduzida com base no código do tipo, extraindo números da descrição original.
                 */
                $translateAtividade = function(string $tipo, string $descricao): string {
                    // Captura sequências de dígitos da descrição original (ex.: numero da parcela / qtd)
                    preg_match_all('/\d+/', $descricao, $m);
                    $n = $m[0][0] ?? '';
                    switch ($tipo) {
                        case 'carne_criado':
                            return __('admin.installment.act.carne_criado', 'Carnê criado com {n} parcelas', ['n' => $n]);
                        case 'parcela_paga':
                            return __('admin.installment.act.parcela_paga', 'Parcela {n} quitada', ['n' => $n]);
                        case 'boleto_pago':
                            return __('admin.installment.act.boleto_pago', 'Boleto da parcela {n} pago', ['n' => $n]);
                        case 'pix_regerado':
                            return __('admin.installment.act.pix_regerado', 'PIX regerado para parcela {n}', ['n' => $n]);
                        case 'boleto_reemitido':
                            return __('admin.installment.act.boleto_reemitido', 'Boleto reemitido', ['n' => $n]);
                        case 'parcela_antecipada':
                            return __('admin.installment.act.parcela_antecipada', 'Parcela {n} antecipada pelo cliente', ['n' => $n]);
                        case 'pagamento_manual':
                            return __('admin.installment.act.pagamento_manual', 'Parcela {n} marcada como paga manualmente', ['n' => $n]);
                        case 'compra_liberada':
                            return __('admin.installment.act.compra_liberada', 'Primeira parcela paga. Compra interna liberada.');
                        case 'carne_quitado':
                            return __('admin.installment.act.carne_quitado', 'Todas as parcelas pagas. Envio liberado.');
                        case 'carne_cancelado':
                            return __('admin.installment.act.carne_cancelado', 'Carnê cancelado');
                        case 'aviso_cancelamento':
                            return __('admin.installment.act.aviso_cancelamento', 'Aviso de cancelamento enviado');
                        case 'produto_comprado':
                            return __('admin.installment.act.produto_comprado', 'Produto marcado como comprado internamente');
                        case 'produto_recebido':
                            return __('admin.installment.act.produto_recebido', 'Produto marcado como recebido internamente');
                        case 'compra_desfeita':
                            return __('admin.installment.act.compra_desfeita', 'Compra interna revertida para aguardando compra');
                        case 'envio_liberado':
                            return __('admin.installment.act.envio_liberado', 'Envio liberado pelo admin');
                        case 'credito_carteira':
                            return __('admin.installment.act.credito_carteira', 'Crédito gerado na carteira');
                        case 'cobranca_enviada':
                            return __('admin.installment.act.cobranca_enviada', 'E-mail de cobrança processado');
                        case 'reconciliacao':
                            return __('admin.installment.act.reconciliacao', 'Parcela {n} reconciliada', ['n' => $n]);
                        default:
                            return $descricao; // fallback: mantém texto original do banco
                    }
                };
                foreach ($atividadeRecente as $at):
                    $iconMap = ['parcela_paga' => 'fa-check-circle text-success', 'carne_criado' => 'fa-plus-circle text-primary', 'parcela_vencida' => 'fa-exclamation-circle text-danger', 'envio_liberado' => 'fa-truck text-success', 'compra_realizada' => 'fa-shopping-cart text-info'];
                    $icon = $iconMap[$at['tipo'] ?? ''] ?? 'fa-circle text-muted';
                    $atTipo = (string) ($at['tipo'] ?? '');
                    $atDesc = (string) ($at['descricao'] ?? '');
                ?>
                <div class="list-group-item px-0 border-0 d-flex align-items-start gap-3">
                    <i class="fas <?= $icon ?> mt-1"></i>
                    <div>
                        <div class="small fw-semibold"><?= htmlspecialchars($translateAtividade($atTipo, $atDesc)) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($at['cliente_nome'] ?? '') ?> · <?= __('admin.installment.order', 'Pedido') ?> #<?= $at['pedido_id'] ?? '' ?> · <?= !empty($at['created_at']) ? date('d/m H:i', strtotime($at['created_at'])) : '' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /container -->

<script>
function formatarBRL(valor) { return valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function atualizarOptionsParcelas(d) {
    var sel = document.getElementById('recriar-parcelas');
    var totalBrl = Number(d.total_brl);
    for (var i = 0; i < sel.options.length; i++) {
        var n = parseInt(sel.options[i].value);
        sel.options[i].text = n + 'x R$ ' + formatarBRL(totalBrl / n);
    }
}
function buscarDadosPedidoCarne() {
    var pid = document.getElementById('recriar-pedido-id').value;
    if (!pid || pid <= 0) { alert('<?= htmlspecialchars(__('admin.installment.js_inform_order_id', 'Informe o ID do pedido'), ENT_QUOTES, 'UTF-8') ?>'); return; }
    var info = document.getElementById('recriar-info');
    var resultado = document.getElementById('recriar-resultado');
    var erro = document.getElementById('recriar-erro');
    erro.style.display = 'none'; resultado.style.display = 'none';
    info.textContent = '<?= htmlspecialchars(__('admin.installment.js_searching', 'Buscando...'), ENT_QUOTES, 'UTF-8') ?>'; resultado.style.display = '';

    fetch('/admin/carnes/buscar-pedido?pedido_id=' + encodeURIComponent(pid))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.error) { resultado.style.display = 'none'; erro.textContent = d.error; erro.style.display = ''; return; }
            document.getElementById('recriar-form-pedido-id').value = d.pedido_id;
            if (d.parcelas_sugeridas) document.getElementById('recriar-parcelas').value = d.parcelas_sugeridas;
            window._dadosPedidoCarne = d;
            atualizarOptionsParcelas(d);
            var qtd = d.parcelas_sugeridas || parseInt(document.getElementById('recriar-parcelas').value) || 4;
            var html = '<strong><?= htmlspecialchars(__('admin.installment.js_order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?> #' + d.pedido_id + '</strong> — ' + d.cliente_nome + ' (' + d.cliente_email + ')<br>';
            html += '<?= htmlspecialchars(__('admin.installment.js_products', 'Produtos:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + Number(d.subtotal_brl).toFixed(2) + ' — <strong><?= htmlspecialchars(__('admin.installment.js_installment', 'Parcela:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + (Number(d.subtotal_brl)/qtd).toFixed(2) + '</strong><br>';
            html += '<?= htmlspecialchars(__('admin.installment.js_fees', 'Taxas:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + Number(d.taxas_brl).toFixed(2) + ' — <strong><?= htmlspecialchars(__('admin.installment.js_installment', 'Parcela:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + (Number(d.taxas_brl)/qtd).toFixed(2) + '</strong><br>';
            html += '<strong><?= htmlspecialchars(__('admin.installment.js_total', 'Total:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + Number(d.total_brl).toFixed(2) + ' — <?= htmlspecialchars(__('admin.installment.js_installment', 'Parcela:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + (Number(d.total_brl)/qtd).toFixed(2) + ' (' + qtd + 'x)</strong>';
            if (d.ja_tem_carne) html += '<br><span class="text-danger"><i class="fas fa-ban"></i> <?= htmlspecialchars(__('admin.installment.js_already_has_plan_id', 'Já tem carnê (ID: {id})'), ENT_QUOTES, 'UTF-8') ?>'.replace('{id}', d.carne_id) + '</span>';
            info.innerHTML = html;
            document.getElementById('recriar-form-campos').style.display = d.ja_tem_carne ? 'none' : '';
        })
        .catch(function(e) { resultado.style.display = 'none'; erro.textContent = '<?= htmlspecialchars(__('admin.installment.js_error_prefix', 'Erro:'), ENT_QUOTES, 'UTF-8') ?> ' + e.message; erro.style.display = ''; });
}
function validarCriacaoCarne() {
    var d = window._dadosPedidoCarne;
    if (d && d.ja_tem_carne) { alert('<?= htmlspecialchars(__('admin.installment.js_already_has_plan_id', 'Já tem carnê (ID: {id})'), ENT_QUOTES, 'UTF-8') ?>'.replace('{id}', d.carne_id)); return false; }
    return confirm('<?= htmlspecialchars(__('admin.installment.js_confirm_create_plan', 'Criar carnê para este pedido?'), ENT_QUOTES, 'UTF-8') ?>');
}
document.getElementById('recriar-parcelas').addEventListener('change', function() {
    var d = window._dadosPedidoCarne; if (!d) return;
    atualizarOptionsParcelas(d);
    var qtd = parseInt(this.value) || 1;
    var info = document.getElementById('recriar-info');
    var html = '<strong><?= htmlspecialchars(__('admin.installment.js_order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?> #' + d.pedido_id + '</strong> — ' + d.cliente_nome + '<br>';
    html += '<?= htmlspecialchars(__('admin.installment.js_products', 'Produtos:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + Number(d.subtotal_brl).toFixed(2) + ' — <?= htmlspecialchars(__('admin.installment.js_installment', 'Parcela:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + (Number(d.subtotal_brl)/qtd).toFixed(2) + '<br>';
    html += '<?= htmlspecialchars(__('admin.installment.js_fees', 'Taxas:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + Number(d.taxas_brl).toFixed(2) + ' — <?= htmlspecialchars(__('admin.installment.js_installment', 'Parcela:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + (Number(d.taxas_brl)/qtd).toFixed(2) + '<br>';
    html += '<strong><?= htmlspecialchars(__('admin.installment.js_total', 'Total:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + Number(d.total_brl).toFixed(2) + ' — <?= htmlspecialchars(__('admin.installment.js_installment', 'Parcela:'), ENT_QUOTES, 'UTF-8') ?> R$ ' + (Number(d.total_brl)/qtd).toFixed(2) + ' (' + qtd + 'x)</strong>';
    if (d.ja_tem_carne) html += '<br><span class="text-danger"><i class="fas fa-ban"></i> <?= htmlspecialchars(__('admin.installment.js_already_has_plan', 'Já tem carnê'), ENT_QUOTES, 'UTF-8') ?></span>';
    info.innerHTML = html;
});
</script>
