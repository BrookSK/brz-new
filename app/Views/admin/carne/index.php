<?php
$statusLabels = [
    'aguardando_primeira_parcela' => ['label' => 'Aguardando 1ª Parcela', 'cor' => 'info'],
    'ativo' => ['label' => 'Ativo', 'cor' => 'primary'],
    'em_andamento' => ['label' => 'Em Andamento', 'cor' => 'primary'],
    'com_atraso' => ['label' => 'Com Atraso', 'cor' => 'danger'],
    'quitado' => ['label' => 'Quitado', 'cor' => 'success'],
    'inadimplente' => ['label' => 'Inadimplente', 'cor' => 'dark'],
    'liberado_envio' => ['label' => 'Liberado p/ Envio', 'cor' => 'success'],
    'encerrado' => ['label' => 'Encerrado', 'cor' => 'secondary'],
    'cancelado' => ['label' => 'Cancelado', 'cor' => 'secondary'],
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
            <h4 class="fw-bold mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Gestão de Carnês</h4>
            <p class="text-muted small mb-0 d-none d-md-block">Braziliana · Painel completo do ciclo de carnê</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/admin/carnes/logs" class="btn btn-outline-info btn-sm"><i class="fas fa-history me-1"></i><span class="d-none d-md-inline">Logs</span></a>
            <a href="/admin/carnes/configuracoes" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cog me-1"></i><span class="d-none d-md-inline">Configurações</span></a>
            <a href="/admin" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i><span class="d-none d-md-inline">Voltar</span></a>
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
                        <div><div class="text-muted small">Total Financiado</div><div class="fs-4 fw-bold"><?= fmtBrl($stats['total_financiado'] ?? 0) ?></div></div>
                        <i class="fas fa-coins fs-3 text-primary opacity-25"></i>
                    </div>
                    <div class="text-muted small mt-1"><?= (int)($stats['total'] ?? 0) ?> carnês no período</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small">Já Recebido</div><div class="fs-4 fw-bold text-success"><?= fmtBrl($stats['total_recebido'] ?? 0) ?></div></div>
                        <i class="fas fa-check-circle fs-3 text-success opacity-25"></i>
                    </div>
                    <?php $pctRecebido = ($stats['total_financiado'] ?? 0) > 0 ? round(($stats['total_recebido'] ?? 0) / $stats['total_financiado'] * 100) : 0; ?>
                    <div class="progress mt-2" style="height:4px;"><div class="progress-bar bg-success" style="width:<?= $pctRecebido ?>%"></div></div>
                    <div class="text-muted small mt-1"><?= $pctRecebido ?>% do total recebido</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small">Em Aberto</div><div class="fs-4 fw-bold text-warning"><?= fmtBrl($stats['total_aberto'] ?? 0) ?></div></div>
                        <i class="fas fa-clock fs-3 text-warning opacity-25"></i>
                    </div>
                    <div class="text-muted small mt-1">a receber nos próximos meses</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small">Em Atraso</div><div class="fs-4 fw-bold text-danger"><?= fmtBrl($stats['total_atraso'] ?? 0) ?></div></div>
                        <i class="fas fa-exclamation-triangle fs-3 text-danger opacity-25"></i>
                    </div>
                    <div class="text-muted small mt-1"><?= $countAtrasados ?> carnês · inadimplência <?= ($stats['total_financiado'] ?? 0) > 0 ? round(($stats['total_atraso'] ?? 0) / $stats['total_financiado'] * 100, 1) : 0 ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mini stats row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 d-flex align-items-center justify-content-between"><div><div class="fw-bold fs-5"><?= $countAguardando1 ?></div><div class="text-muted small">Aguard. 1ª Parcela</div></div><i class="fas fa-hourglass-half text-info"></i></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 d-flex align-items-center justify-content-between"><div><div class="fw-bold fs-5"><?= (int)($stats['vence_7_dias'] ?? 0) ?></div><div class="text-muted small">Vencem em 7 dias</div></div><i class="fas fa-calendar-day text-warning"></i></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 d-flex align-items-center justify-content-between"><div><div class="fw-bold fs-5"><?= (int)($stats['compras_pendentes'] ?? 0) ?></div><div class="text-muted small">Compras Pendentes</div></div><i class="fas fa-shopping-cart text-primary"></i></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 d-flex align-items-center justify-content-between"><div><div class="fw-bold fs-5"><?= (int)($stats['envios_pendentes'] ?? 0) ?></div><div class="text-muted small">Envios Pendentes</div></div><i class="fas fa-truck text-success"></i></div></div></div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-0 flex-nowrap overflow-auto" role="tablist" style="-webkit-overflow-scrolling:touch;">
        <li class="nav-item flex-shrink-0"><button class="nav-link <?= $activeTab === 'carnes' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-carnes" type="button"><i class="fas fa-list me-1"></i>Carnês <span class="badge bg-secondary ms-1"><?= count($carnes) ?></span></button></li>
        <li class="nav-item flex-shrink-0"><button class="nav-link <?= $activeTab === 'cobrancas' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-cobrancas" type="button"><i class="fas fa-bell me-1"></i>Cobranças <span class="badge bg-danger ms-1"><?= count($cobrancas) ?></span></button></li>
        <li class="nav-item flex-shrink-0"><button class="nav-link <?= $activeTab === 'compras' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-compras" type="button"><i class="fas fa-shopping-basket me-1"></i>Compras <span class="badge bg-warning text-dark ms-1"><?= count($comprasPendentes) ?></span></button></li>
        <li class="nav-item flex-shrink-0"><button class="nav-link <?= $activeTab === 'envios' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-envios" type="button"><i class="fas fa-truck me-1"></i>Envios <span class="badge bg-success ms-1"><?= count($enviosPendentes) ?></span></button></li>
    </ul>

    <div class="tab-content">
    <!-- TAB: Carnês -->
    <div class="tab-pane fade <?= $activeTab === 'carnes' ? 'show active' : '' ?>" id="tab-carnes" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-0 rounded-bottom">
            <div class="card-body">
                <!-- Filtros rápidos -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="/admin/carnes" class="btn btn-sm <?= empty($filtros['status']) && empty($filtros['com_atraso']) ? 'btn-dark' : 'btn-outline-secondary' ?>">Todos <?= count($carnes) ?></a>
                    <a href="/admin/carnes?com_atraso=1" class="btn btn-sm <?= !empty($filtros['com_atraso']) ? 'btn-danger' : 'btn-outline-danger' ?>">Atrasados <?= $countAtrasados ?></a>
                    <a href="/admin/carnes?status=aguardando_primeira_parcela" class="btn btn-sm <?= ($filtros['status'] ?? '') === 'aguardando_primeira_parcela' ? 'btn-info' : 'btn-outline-info' ?>">Aguardando 1ª <?= $countAguardando1 ?></a>
                    <a href="/admin/carnes?liberado_compra=1" class="btn btn-sm <?= !empty($filtros['liberado_compra']) ? 'btn-warning' : 'btn-outline-warning' ?>">Aguardando compra</a>
                    <a href="/admin/carnes?liberado_envio=1" class="btn btn-sm <?= !empty($filtros['liberado_envio']) ? 'btn-success' : 'btn-outline-success' ?>">Prontos p/ envio</a>
                    <a href="/admin/carnes?status=quitado" class="btn btn-sm <?= ($filtros['status'] ?? '') === 'quitado' ? 'btn-success' : 'btn-outline-success' ?>">Quitados <?= $countQuitados ?></a>
                </div>

                <!-- Filtros avançados -->
                <div class="mb-3">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosAvancados"><i class="fas fa-sliders-h me-1"></i>Filtros avançados</button>
                    <button class="btn btn-sm btn-outline-warning ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#recriarCarneCollapse"><i class="fas fa-plus-circle me-1"></i>Recriar carnê</button>
                </div>

                <div class="collapse mb-3" id="filtrosAvancados">
                    <div class="card border">
                        <div class="card-body">
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label small">Status financeiro</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="">Todos</option>
                                        <?php foreach ($statusLabels as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($filtros['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Cliente</label>
                                    <input type="text" name="cliente" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['cliente'] ?? '') ?>" placeholder="Nome ou email">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Pedido #</label>
                                    <input type="text" name="pedido_id" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['pedido_id'] ?? '') ?>">
                                </div>
                                <div class="col-md-auto">
                                    <div class="form-check"><input type="checkbox" name="com_atraso" value="1" class="form-check-input" <?= !empty($filtros['com_atraso']) ? 'checked' : '' ?>><label class="form-check-label small">Com atraso</label></div>
                                </div>
                                <div class="col-md-auto">
                                    <div class="form-check"><input type="checkbox" name="liberado_compra" value="1" class="form-check-input" <?= !empty($filtros['liberado_compra']) ? 'checked' : '' ?>><label class="form-check-label small">Compra liberada</label></div>
                                </div>
                                <div class="col-md-auto">
                                    <div class="form-check"><input type="checkbox" name="liberado_envio" value="1" class="form-check-input" <?= !empty($filtros['liberado_envio']) ? 'checked' : '' ?>><label class="form-check-label small">Envio liberado</label></div>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Aplicar</button>
                                    <a href="/admin/carnes" class="btn btn-sm btn-outline-secondary ms-1">Limpar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Recriar Carnê (collapse) -->
                <div class="collapse mb-3" id="recriarCarneCollapse">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small">ID do Pedido</label>
                                    <input type="number" id="recriar-pedido-id" class="form-control form-control-sm" placeholder="Ex: 715" min="1">
                                </div>
                                <div class="col-md-auto">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="buscarDadosPedidoCarne()"><i class="fas fa-search me-1"></i>Buscar</button>
                                </div>
                            </div>
                            <div id="recriar-resultado" class="mt-3" style="display:none;">
                                <div id="recriar-info" class="alert alert-info small"></div>
                                <form method="POST" action="/admin/carnes/recriar" id="recriar-form" onsubmit="return validarCriacaoCarne()">
                                    <input type="hidden" name="pedido_id" id="recriar-form-pedido-id">
                                    <div class="row g-2 align-items-end" id="recriar-form-campos">
                                        <div class="col-md-3">
                                            <label class="form-label small">Parcelas</label>
                                            <select name="quantidade_parcelas" id="recriar-parcelas" class="form-select form-select-sm">
                                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?= $i ?>" <?= $i === 4 ? 'selected' : '' ?>><?= $i ?>x</option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-auto">
                                            <button type="submit" id="recriar-btn-submit" class="btn btn-warning btn-sm"><i class="fas fa-plus me-1"></i>Criar Carnê</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div id="recriar-erro" class="mt-2 alert alert-danger small" style="display:none;"></div>
                            <small class="text-muted d-block mt-2">Para pedidos com forma_pagamento = carne_braziliana que não tiveram o carnê criado automaticamente.</small>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Carnês -->
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th><th>Pedido / Cliente</th><th class="text-end d-none d-lg-table-cell">Total</th>
                                <th class="text-end d-none d-lg-table-cell">Pago / Saldo</th><th>Parcelas</th>
                                <th class="d-none d-md-table-cell">Próx. Vencimento</th><th>Status</th>
                                <th class="d-none d-xl-table-cell">Compra</th><th class="d-none d-xl-table-cell">Envio</th><th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($carnes)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">Nenhum carnê encontrado.</td></tr>
                            <?php else: ?>
                            <?php foreach ($carnes as $c):
                                $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'cor' => 'secondary'];
                                $pagas = (int)($c['parcelas_pagas'] ?? 0);
                                $totalParcelas = (int)($c['quantidade_parcelas'] ?? 1);
                                $pago = ($stats['total_financiado'] ?? 0) > 0 ? ($c['total_geral'] * $pagas / max($totalParcelas, 1)) : 0;
                                $saldo = (float)$c['total_geral'] - $pago;
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= $c['id'] ?></td>
                                <td>
                                    <a href="/admin/pedidos/detalhes/<?= $c['pedido_id'] ?>" class="text-decoration-none fw-semibold">#<?= $c['pedido_id'] ?></a>
                                    <div class="text-muted small text-truncate" style="max-width:120px;"><?= htmlspecialchars($c['cliente_nome'] ?? '') ?></div>
                                </td>
                                <td class="text-end fw-semibold d-none d-lg-table-cell"><?= fmtBrl($c['total_geral']) ?></td>
                                <td class="text-end d-none d-lg-table-cell">
                                    <span class="text-success small"><?= fmtBrl($pago) ?></span>
                                    <div class="text-muted small"><?= fmtBrl($saldo) ?> restante</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $pagas ?>/<?= $totalParcelas ?></span>
                                    <?php if (($c['parcelas_atrasadas'] ?? 0) > 0): ?>
                                    <span class="badge bg-danger ms-1"><?= $c['parcelas_atrasadas'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell"><?= !empty($c['proximo_vencimento']) ? date('d/m/Y', strtotime($c['proximo_vencimento'])) : '-' ?></td>
                                <td><span class="badge bg-<?= $st['cor'] ?>"><?= $st['label'] ?></span></td>
                                <td class="d-none d-xl-table-cell"><?= !empty($c['compra_interna_liberada']) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-clock text-muted"></i>' ?></td>
                                <td class="d-none d-xl-table-cell"><?= !empty($c['envio_liberado']) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-lock text-muted"></i>' ?></td>
                                <td><a href="/admin/carnes/detalhes/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Detalhes"><i class="fas fa-eye"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
                            <tr><th>Cliente / Pedido</th><th class="text-end">Valor parcela</th><th>Vencimento</th><th>Situação</th><th>Atraso</th><th>Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cobrancas)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma cobrança pendente.</td></tr>
                            <?php else: ?>
                            <?php foreach ($cobrancas as $cob):
                                $venc = strtotime($cob['vencimento']);
                                $hoje = strtotime(date('Y-m-d'));
                                $diasAtraso = ($hoje > $venc) ? (int)(($hoje - $venc) / 86400) : 0;
                                $situacao = $diasAtraso > 0 ? 'Com atraso' : ($venc == $hoje ? 'Vence hoje' : 'Em ' . (int)(($venc - $hoje) / 86400) . ' dias');
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
                                <td><?= $diasAtraso > 0 ? $diasAtraso . ' dias' : '—' ?></td>
                                <td>
                                    <form method="POST" action="/admin/carnes/enviar-cobranca/<?= $cob['id'] ?>" class="d-inline" onsubmit="return confirm('Enviar cobrança?')">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Enviar cobrança"><i class="fas fa-paper-plane"></i></button>
                                    </form>
                                    <form method="POST" action="/admin/carnes/reemitir-boleto/<?= $cob['id'] ?>" class="d-inline ms-1">
                                        <button type="submit" class="btn btn-sm btn-outline-info" title="Reemitir boleto"><i class="fas fa-redo"></i></button>
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
                    <h6 class="fw-bold mb-0">Compras Pendentes</h6>
                    <a href="/admin/carnes/compras" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i>Ver completo</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Carnê / Pedido</th><th>Cliente</th><th>Status</th><th>Criado em</th><th>Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($comprasPendentes)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma compra pendente.</td></tr>
                            <?php else: ?>
                            <?php foreach ($comprasPendentes as $cp): ?>
                            <tr>
                                <td><span class="fw-semibold">#<?= $cp['carne_id'] ?></span> <span class="text-muted small">/ Pedido #<?= $cp['pedido_id'] ?? '' ?></span></td>
                                <td><?= htmlspecialchars($cp['cliente_nome'] ?? '') ?></td>
                                <td><span class="badge bg-warning text-dark">Aguardando compra</span></td>
                                <td class="small text-muted"><?= !empty($cp['created_at']) ? date('d/m/Y', strtotime($cp['created_at'])) : '-' ?></td>
                                <td>
                                    <form method="POST" action="/admin/carnes/marcar-comprado/<?= $cp['id'] ?>" class="d-inline" onsubmit="return confirm('Marcar como comprado?')">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar comprado"><i class="fas fa-check"></i></button>
                                    </form>
                                    <a href="/admin/carnes/detalhes/<?= $cp['carne_id'] ?>" class="btn btn-sm btn-outline-primary ms-1" title="Ver carnê"><i class="fas fa-eye"></i></a>
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
                            <tr><th>Cliente / Pedido</th><th class="text-end">Total</th><th>Status Financ.</th><th>Status Envio</th><th>Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enviosPendentes)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Nenhum envio pendente.</td></tr>
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
                                <td><span class="badge bg-success">Pronto p/ envio</span></td>
                                <td>
                                    <a href="/admin/carnes/detalhes/<?= $env['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver carnê"><i class="fas fa-eye"></i></a>
                                    <a href="/admin/pedidos/detalhes/<?= $env['pedido_id'] ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Ver pedido"><i class="fas fa-box"></i></a>
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
        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-stream me-2"></i>Atividade recente</h6></div>
        <div class="card-body pt-0">
            <div class="list-group list-group-flush">
                <?php foreach ($atividadeRecente as $at):
                    $iconMap = ['parcela_paga' => 'fa-check-circle text-success', 'carne_criado' => 'fa-plus-circle text-primary', 'parcela_vencida' => 'fa-exclamation-circle text-danger', 'envio_liberado' => 'fa-truck text-success', 'compra_realizada' => 'fa-shopping-cart text-info'];
                    $icon = $iconMap[$at['tipo'] ?? ''] ?? 'fa-circle text-muted';
                ?>
                <div class="list-group-item px-0 border-0 d-flex align-items-start gap-3">
                    <i class="fas <?= $icon ?> mt-1"></i>
                    <div>
                        <div class="small fw-semibold"><?= htmlspecialchars($at['descricao'] ?? '') ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($at['cliente_nome'] ?? '') ?> · Pedido #<?= $at['pedido_id'] ?? '' ?> · <?= !empty($at['created_at']) ? date('d/m H:i', strtotime($at['created_at'])) : '' ?></div>
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
    if (!pid || pid <= 0) { alert('Informe o ID do pedido'); return; }
    var info = document.getElementById('recriar-info');
    var resultado = document.getElementById('recriar-resultado');
    var erro = document.getElementById('recriar-erro');
    erro.style.display = 'none'; resultado.style.display = 'none';
    info.textContent = 'Buscando...'; resultado.style.display = '';

    fetch('/admin/carnes/buscar-pedido?pedido_id=' + encodeURIComponent(pid))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.error) { resultado.style.display = 'none'; erro.textContent = d.error; erro.style.display = ''; return; }
            document.getElementById('recriar-form-pedido-id').value = d.pedido_id;
            if (d.parcelas_sugeridas) document.getElementById('recriar-parcelas').value = d.parcelas_sugeridas;
            window._dadosPedidoCarne = d;
            atualizarOptionsParcelas(d);
            var qtd = d.parcelas_sugeridas || parseInt(document.getElementById('recriar-parcelas').value) || 4;
            var html = '<strong>Pedido #' + d.pedido_id + '</strong> — ' + d.cliente_nome + ' (' + d.cliente_email + ')<br>';
            html += 'Produtos: R$ ' + Number(d.subtotal_brl).toFixed(2) + ' — <strong>Parcela: R$ ' + (Number(d.subtotal_brl)/qtd).toFixed(2) + '</strong><br>';
            html += 'Taxas: R$ ' + Number(d.taxas_brl).toFixed(2) + ' — <strong>Parcela: R$ ' + (Number(d.taxas_brl)/qtd).toFixed(2) + '</strong><br>';
            html += '<strong>Total: R$ ' + Number(d.total_brl).toFixed(2) + ' — Parcela: R$ ' + (Number(d.total_brl)/qtd).toFixed(2) + ' (' + qtd + 'x)</strong>';
            if (d.ja_tem_carne) html += '<br><span class="text-danger"><i class="fas fa-ban"></i> Já tem carnê (ID: ' + d.carne_id + ')</span>';
            info.innerHTML = html;
            document.getElementById('recriar-form-campos').style.display = d.ja_tem_carne ? 'none' : '';
        })
        .catch(function(e) { resultado.style.display = 'none'; erro.textContent = 'Erro: ' + e.message; erro.style.display = ''; });
}
function validarCriacaoCarne() {
    var d = window._dadosPedidoCarne;
    if (d && d.ja_tem_carne) { alert('Já possui carnê (ID: ' + d.carne_id + ')'); return false; }
    return confirm('Criar carnê para este pedido?');
}
document.getElementById('recriar-parcelas').addEventListener('change', function() {
    var d = window._dadosPedidoCarne; if (!d) return;
    atualizarOptionsParcelas(d);
    var qtd = parseInt(this.value) || 1;
    var info = document.getElementById('recriar-info');
    var html = '<strong>Pedido #' + d.pedido_id + '</strong> — ' + d.cliente_nome + '<br>';
    html += 'Produtos: R$ ' + Number(d.subtotal_brl).toFixed(2) + ' — Parcela: R$ ' + (Number(d.subtotal_brl)/qtd).toFixed(2) + '<br>';
    html += 'Taxas: R$ ' + Number(d.taxas_brl).toFixed(2) + ' — Parcela: R$ ' + (Number(d.taxas_brl)/qtd).toFixed(2) + '<br>';
    html += '<strong>Total: R$ ' + Number(d.total_brl).toFixed(2) + ' — Parcela: R$ ' + (Number(d.total_brl)/qtd).toFixed(2) + ' (' + qtd + 'x)</strong>';
    if (d.ja_tem_carne) html += '<br><span class="text-danger"><i class="fas fa-ban"></i> Já tem carnê</span>';
    info.innerHTML = html;
});
</script>
