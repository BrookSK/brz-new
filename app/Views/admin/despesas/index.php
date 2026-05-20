<?php
$tab = $tab ?? 'visao-geral';
$stats = $stats ?? [];
$despesas = $despesas ?? [];
$categorias = $categorias ?? [];
$recorrencias = $recorrencias ?? [];
$parcelamentos = $parcelamentos ?? [];
$comissoes = $comissoes ?? [];
$filtros = $filtros ?? [];
function fmtD($v) { return 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.'); }

$countAll = count($despesas);
$countVencidas = count(array_filter($despesas, fn($d) => ($d['status'] ?? '') === 'vencida'));
$countHoje = count(array_filter($despesas, fn($d) => ($d['vencimento'] ?? '') === date('Y-m-d') && ($d['status'] ?? '') !== 'paga'));
$countComissoes = count(array_filter($despesas, fn($d) => ($d['tipo'] ?? '') === 'comissao'));
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <div><h1 class="page-title">Despesas</h1><p class="page-subtitle">Centro de controle de saídas, recorrências, parcelas e comissões</p></div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="border rounded-pill px-3 py-1 d-none d-lg-flex align-items-center gap-2 bg-white small">
                <i class="fas fa-arrow-down text-danger" style="font-size:10px;"></i><span class="text-muted">Saídas hoje</span><span class="fw-bold"><?= fmtD($stats['vencido'] ?? 0) ?></span>
            </div>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="exportarDespesas()"><i class="fas fa-download me-1"></i><span class="d-none d-md-inline">Exportar</span></button>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalDespView"><i class="fas fa-globe me-1"></i><span class="d-none d-md-inline">Moeda</span></button>
            <button class="btn btn-dark btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalNovaDespesa"><i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline">Nova</span></button>
        </div>
    </div>

    <!-- Tabs: Mobile dropdown + Desktop tabs -->
    <div class="d-md-none mb-3">
        <select class="form-select" onchange="window.location.href=this.value">
            <option value="/admin/despesas?tab=visao-geral" <?= $tab==='visao-geral'?'selected':'' ?>>Visão Geral</option>
            <option value="/admin/despesas?tab=todas" <?= $tab==='todas'?'selected':'' ?>>Todas (<?= $countAll ?>)</option>
            <option value="/admin/despesas?tab=recorrentes" <?= $tab==='recorrentes'?'selected':'' ?>>Recorrentes (<?= count($recorrencias) ?>)</option>
            <option value="/admin/despesas?tab=parceladas" <?= $tab==='parceladas'?'selected':'' ?>>Parceladas (<?= count($parcelamentos) ?>)</option>
            <option value="/admin/despesas?tab=comissoes" <?= $tab==='comissoes'?'selected':'' ?>>Comissões (<?= $countComissoes ?>)</option>
            <option value="/admin/despesas?tab=categorias" <?= $tab==='categorias'?'selected':'' ?>>Categorias</option>
            <option value="/admin/despesas?tab=relatorios" <?= $tab==='relatorios'?'selected':'' ?>>Relatórios</option>
        </select>
    </div>
    <ul class="nav nav-tabs mb-4 d-none d-md-flex" role="tablist">
        <li class="nav-item"><a class="nav-link <?= $tab==='visao-geral'?'active':'' ?>" href="/admin/despesas?tab=visao-geral">Visão Geral</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='todas'?'active':'' ?>" href="/admin/despesas?tab=todas">Todas <span class="badge bg-secondary ms-1"><?= $countAll ?></span></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='recorrentes'?'active':'' ?>" href="/admin/despesas?tab=recorrentes">Recorrentes <span class="badge bg-secondary ms-1"><?= count($recorrencias) ?></span></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='parceladas'?'active':'' ?>" href="/admin/despesas?tab=parceladas">Parceladas <span class="badge bg-secondary ms-1"><?= count($parcelamentos) ?></span></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='comissoes'?'active':'' ?>" href="/admin/despesas?tab=comissoes">Comissões <span class="badge bg-secondary ms-1"><?= $countComissoes ?></span></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='categorias'?'active':'' ?>" href="/admin/despesas?tab=categorias">Categorias</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='relatorios'?'active':'' ?>" href="/admin/despesas?tab=relatorios">Relatórios</a></li>
    </ul>

    <?php if (!empty($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($_SESSION['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

    <?php if ($tab === 'visao-geral'): ?>
    <!-- VISÃO GERAL -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-top:3px solid #3b82f6;"><div class="card-body"><div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-chart-pie text-primary"></i><span class="small fw-semibold" data-desp-i18n="desp_mes">Despesas do mês</span></div><div class="fs-4 fw-bold desp-val" data-brl="<?= (float)$stats['total_mes'] ?>"><?= fmtD($stats['total_mes']) ?></div></div></div></div>
        <div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-top:3px solid #10b981;"><div class="card-body"><div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-check-circle text-success"></i><span class="small fw-semibold" data-desp-i18n="pago_mes">Pago no mês</span></div><div class="fs-4 fw-bold text-success desp-val" data-brl="<?= (float)$stats['pago_mes'] ?>"><?= fmtD($stats['pago_mes']) ?></div><div class="text-muted small"><?= ($stats['total_mes'] > 0) ? round($stats['pago_mes'] / $stats['total_mes'] * 100, 1) : 0 ?>% do total quitado</div></div></div></div>
        <div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-top:3px solid #f59e0b;"><div class="card-body"><div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-clock text-warning"></i><span class="small fw-semibold" data-desp-i18n="aberto">Em aberto</span></div><div class="fs-4 fw-bold text-warning desp-val" data-brl="<?= (float)$stats['aberto'] ?>"><?= fmtD($stats['aberto']) ?></div><div class="text-muted small"><?= $stats['qtd_aberto'] ?> lançamentos a vencer</div></div></div></div>
        <div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-top:3px solid #ef4444;"><div class="card-body"><div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-exclamation-triangle text-danger"></i><span class="small fw-semibold" data-desp-i18n="vencido">Vencido</span></div><div class="fs-4 fw-bold text-danger desp-val" data-brl="<?= (float)$stats['vencido'] ?>"><?= fmtD($stats['vencido']) ?></div><div class="text-muted small"><?= $stats['qtd_vencido'] ?> lançamentos em atraso</div></div></div></div>
        <div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-top:3px solid #6366f1;"><div class="card-body"><div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-calendar-alt text-indigo"></i><span class="small fw-semibold" data-desp-i18n="prox30">Próximos 30 dias</span></div><div class="fs-4 fw-bold desp-val" data-brl="<?= (float)$stats['proximos_30'] ?>"><?= fmtD($stats['proximos_30']) ?></div><div class="text-muted small"><?= $stats['qtd_proximos'] ?> compromissos previstos</div></div></div></div>
        <div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-top:3px solid #8b5cf6;"><div class="card-body"><div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-hand-holding-usd text-purple"></i><span class="small fw-semibold" data-desp-i18n="comissoes">Comissões a pagar</span></div><div class="fs-4 fw-bold desp-val" data-brl="<?= (float)$stats['comissoes'] ?>"><?= fmtD($stats['comissoes']) ?></div><div class="text-muted small"><?= $stats['qtd_comissoes'] ?> pendentes</div></div></div></div>
    </div>

    <!-- Recorrências e Parcelamentos lado a lado -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center"><h6 class="fw-bold mb-0"><i class="fas fa-sync-alt me-2 text-muted"></i>Recorrências ativas</h6><a href="/admin/despesas?tab=recorrentes" class="btn btn-sm btn-outline-primary">Ver todas</a></div>
                <div class="card-body p-0">
                    <?php if (empty($recorrencias)): ?>
                    <div class="text-center text-muted py-4 small">Nenhuma recorrência ativa</div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($recorrencias, 0, 5) as $r): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div><div class="fw-semibold small"><?= htmlspecialchars($r['descricao']) ?></div><div class="text-muted" style="font-size:10px;"><?= ucfirst($r['frequencia']) ?> · dia <?= $r['dia_vencimento'] ?> <?= $r['data_fim'] ? '· até ' . date('m/Y', strtotime($r['data_fim'])) : '· sem fim' ?></div></div>
                            <span class="fw-bold small"><?= fmtD($r['valor']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center"><h6 class="fw-bold mb-0"><i class="fas fa-layer-group me-2 text-muted"></i>Parcelas em andamento</h6><a href="/admin/despesas?tab=parceladas" class="btn btn-sm btn-outline-primary">Ver todas</a></div>
                <div class="card-body p-0">
                    <?php if (empty($parcelamentos)): ?>
                    <div class="text-center text-muted py-4 small">Nenhum parcelamento ativo</div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($parcelamentos, 0, 5) as $p): $pct = $p['quantidade_parcelas'] > 0 ? round($p['parcelas_pagas'] / $p['quantidade_parcelas'] * 100) : 0; ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><div class="fw-semibold small"><?= htmlspecialchars($p['descricao']) ?></div><div class="text-muted" style="font-size:10px;"><?= $p['parcelas_pagas'] ?>/<?= $p['quantidade_parcelas'] ?> · pago <?= fmtD($p['valor_total'] - $p['saldo_restante']) ?> / <?= fmtD($p['valor_total']) ?></div></div>
                                <span class="fw-bold small"><?= fmtD($p['valor_parcela']) ?><span class="text-muted fw-normal">/parcela</span></span>
                            </div>
                            <div class="progress mt-1" style="height:4px;"><div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'todas' || $tab === 'comissoes'): ?>
    <!-- FILTROS + TABELA -->
    <?php if ($tab === 'comissoes'): ?>
    <!-- COMISSÕES DINÂMICAS -->
    <?php
    $comData = $comissoes;
    $comVendedores = $comData['vendedores'] ?? [];
    $comTotal = (float)($comData['total'] ?? 0);
    $comPeriodo = $comData['periodo'] ?? date('Y-m');
    $comInicio = $comData['dataInicio'] ?? '';
    $comFim = $comData['dataFim'] ?? '';
    $comUsd = (float)($comData['usdToBrl'] ?? 5.85);
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-purple bg-opacity-10 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:#8b5cf620;"><i class="fas fa-hand-holding-usd" style="color:#8b5cf6;font-size:12px;"></i></div>
                <div><h6 class="fw-bold mb-0">Comissões · sincronizadas do sistema</h6><span class="text-muted" style="font-size:10px;">Geradas automaticamente a partir dos pedidos · Câmbio: 1 USD = R$ <?= number_format($comUsd, 2, ',', '.') ?></span></div>
            </div>
            <a href="/admin/comissoes-global?periodo=<?= htmlspecialchars($comPeriodo) ?>" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="fas fa-external-link-alt me-1"></i>Ver completo</a>
        </div>
        <div class="card-body">
            <!-- Período -->
            <form method="GET" action="/admin/despesas" class="d-flex align-items-end gap-3 mb-4">
                <input type="hidden" name="tab" value="comissoes">
                <div>
                    <label class="form-label small text-muted mb-1">Período de comissão</label>
                    <input type="month" name="competencia_de" class="form-control form-control-sm" value="<?= htmlspecialchars($comPeriodo) ?>">
                </div>
                <button type="submit" class="btn btn-dark btn-sm"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <span class="text-muted small">De <?= $comInicio ? date('d/m/Y', strtotime($comInicio)) : '' ?> até <?= $comFim ? date('d/m/Y', strtotime($comFim)) : '' ?></span>
            </form>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="border rounded p-3 text-center"><div class="text-muted small">Geradas no período</div><div class="fw-bold fs-5"><?= fmtD($comTotal) ?></div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 text-center"><div class="text-muted small">Vendedores</div><div class="fw-bold fs-5"><?= count($comVendedores) ?></div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 text-center"><div class="text-muted small">Pedidos</div><div class="fw-bold fs-5"><?= array_sum(array_column($comVendedores, 'pedidos')) ?></div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 text-center"><div class="text-muted small">Faturado (BRL)</div><div class="fw-bold fs-5"><?= fmtD(array_sum(array_column($comVendedores, 'faturado'))) ?></div></div></div>
            </div>

            <!-- Tabela -->
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                    <thead class="table-light"><tr><th>Vendedor</th><th class="text-end">Pedidos</th><th class="text-end">Bruto (R$)</th><th class="text-end">Custo Produto</th><th class="text-end">Impostos</th><th class="text-end">Líquido</th><th class="text-end">% Comissão</th><th class="text-end">Comissão Manual</th><th class="text-end">Comissão Proc.</th><th class="text-end fw-bold">Total Comissão</th></tr></thead>
                    <tbody>
                    <?php if (empty($comVendedores)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Nenhuma comissão no período.</td></tr>
                    <?php else: foreach ($comVendedores as $v): ?>
                    <tr>
                        <td><div class="fw-semibold"><?= htmlspecialchars($v['nome']) ?></div><div class="text-muted" style="font-size:10px;"><?= htmlspecialchars($v['email']) ?></div></td>
                        <td class="text-end"><?= $v['pedidos'] ?></td>
                        <td class="text-end"><?= fmtD($v['faturado']) ?></td>
                        <td class="text-end"><?= fmtD($v['custo']) ?></td>
                        <td class="text-end"><?= fmtD($v['impostos']) ?></td>
                        <td class="text-end"><?= fmtD($v['liquido']) ?></td>
                        <td class="text-end"><?= number_format($v['percentual'], 2, ',', '.') ?>%</td>
                        <td class="text-end"><?= fmtD($v['comissao_manual']) ?></td>
                        <td class="text-end"><?= fmtD($v['comissao_proc']) ?></td>
                        <td class="text-end fw-bold"><?= fmtD($v['total_comissao']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-dark fw-bold"><td colspan="9" class="text-end">Total Geral</td><td class="text-end"><?= fmtD($comTotal) ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="/admin/despesas?tab=todas" class="btn btn-sm <?= empty($filtros['rapido'])?'btn-dark':'btn-outline-secondary' ?> rounded-pill">Todas <?= $countAll ?></a>
                <a href="/admin/despesas?tab=todas&rapido=vencidas" class="btn btn-sm <?= ($filtros['rapido']??'')==='vencidas'?'btn-danger':'btn-outline-danger' ?> rounded-pill">Vencidas <?= $countVencidas ?></a>
                <a href="/admin/despesas?tab=todas&rapido=hoje" class="btn btn-sm <?= ($filtros['rapido']??'')==='hoje'?'btn-warning':'btn-outline-warning' ?> rounded-pill">Vencem hoje <?= $countHoje ?></a>
                <a href="/admin/despesas?tab=todas&rapido=7dias" class="btn btn-sm <?= ($filtros['rapido']??'')==='7dias'?'btn-info':'btn-outline-info' ?> rounded-pill">Próx. 7 dias</a>
                <a href="/admin/despesas?tab=todas&rapido=pagas" class="btn btn-sm <?= ($filtros['rapido']??'')==='pagas'?'btn-success':'btn-outline-success' ?> rounded-pill">Pagas</a>
                <a href="/admin/despesas?tab=todas&rapido=fixas" class="btn btn-sm <?= ($filtros['rapido']??'')==='fixas'?'btn-primary':'btn-outline-primary' ?> rounded-pill">Fixas</a>
                <a href="/admin/despesas?tab=todas&rapido=parcelas" class="btn btn-sm <?= ($filtros['rapido']??'')==='parcelas'?'btn-secondary':'btn-outline-secondary' ?> rounded-pill">Parcelas em aberto</a>
                <a href="/admin/despesas?tab=todas&rapido=comissoes" class="btn btn-sm <?= ($filtros['rapido']??'')==='comissoes'?'btn-purple':'btn-outline-secondary' ?> rounded-pill">Comissões</a>
            </div>
            <form method="GET" action="/admin/despesas" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="col-md-2"><label class="form-label small text-muted">Competência de</label><input type="month" name="competencia_de" class="form-control form-control-sm" value="<?= htmlspecialchars(substr($filtros['competencia_de'] ?? '', 0, 7)) ?>"></div>
                <div class="col-md-2"><label class="form-label small text-muted">Até</label><input type="month" name="competencia_ate" class="form-control form-control-sm" value="<?= htmlspecialchars(substr($filtros['competencia_ate'] ?? '', 0, 7)) ?>"></div>
                <div class="col-md-2"><label class="form-label small text-muted">Categoria</label><select name="categoria" class="form-select form-select-sm"><option value="">Todas</option><?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>" <?= ($filtros['categoria']??'')==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label small text-muted">Status</label><select name="status" class="form-select form-select-sm"><option value="">Todos</option><option value="prevista" <?= ($filtros['status']??'')==='prevista'?'selected':'' ?>>Prevista</option><option value="a_vencer" <?= ($filtros['status']??'')==='a_vencer'?'selected':'' ?>>A vencer</option><option value="vencida" <?= ($filtros['status']??'')==='vencida'?'selected':'' ?>>Vencida</option><option value="paga" <?= ($filtros['status']??'')==='paga'?'selected':'' ?>>Paga</option><option value="cancelada" <?= ($filtros['status']??'')==='cancelada'?'selected':'' ?>>Cancelada</option></select></div>
                <div class="col-md-2"><label class="form-label small text-muted">Tipo</label><select name="tipo" class="form-select form-select-sm"><option value="">Todos</option><option value="avulsa" <?= ($filtros['tipo']??'')==='avulsa'?'selected':'' ?>>Avulsa</option><option value="fixa" <?= ($filtros['tipo']??'')==='fixa'?'selected':'' ?>>Fixa</option><option value="recorrente" <?= ($filtros['tipo']??'')==='recorrente'?'selected':'' ?>>Recorrente</option><option value="parcelada" <?= ($filtros['tipo']??'')==='parcelada'?'selected':'' ?>>Parcelada</option><option value="comissao" <?= ($filtros['tipo']??'')==='comissao'?'selected':'' ?>>Comissão</option><option value="por_hora" <?= ($filtros['tipo']??'')==='por_hora'?'selected':'' ?>>Por Hora</option></select></div>
                <div class="col-md-2"><button type="submit" class="btn btn-dark btn-sm w-100"><i class="fas fa-filter me-1"></i>Filtrar</button></div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <!-- Mobile: Cards -->
            <div class="d-md-none p-3">
                <?php if (empty($despesas)): ?>
                    <div class="text-center text-muted py-4 small">Nenhuma despesa encontrada.</div>
                <?php else: ?>
                    <?php
                    $statusBadgeMobile = ['prevista'=>'bg-secondary','a_vencer'=>'bg-warning text-dark','vencida'=>'bg-danger','paga'=>'bg-success','parcialmente_paga'=>'bg-info','cancelada'=>'bg-dark bg-opacity-50'];
                    foreach ($despesas as $d):
                        $stClassMobile = $statusBadgeMobile[$d['status'] ?? ''] ?? 'bg-secondary';
                    ?>
                    <div class="border rounded p-2 mb-2 d-flex align-items-center gap-2">
                        <div class="flex-grow-1" style="min-width:0;overflow:hidden;">
                            <div class="fw-semibold text-truncate" style="font-size:12px;"><?= htmlspecialchars($d['descricao'] ?? '') ?></div>
                            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                <span class="fw-bold" style="font-size:11px;"><?= ($d['moeda'] ?? 'BRL') === 'USD' ? '$ ' : 'R$ ' ?><?= number_format((float)($d['valor'] ?? 0), 2, ',', '.') ?></span>
                                <span class="badge <?= $stClassMobile ?>" style="font-size:9px;"><?= ucfirst(str_replace('_', ' ', $d['status'] ?? '')) ?></span>
                            </div>
                            <div class="text-muted" style="font-size:10px;">Venc: <?= $d['vencimento'] ? date('d/m/Y', strtotime($d['vencimento'])) : '-' ?></div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                            <button type="button" class="btn btn-outline-primary py-0 px-1 btn-editar-despesa" style="font-size:11px;" title="Editar" data-id="<?= $d['id'] ?>" data-descricao="<?= htmlspecialchars($d['descricao'] ?? '') ?>" data-categoria="<?= (int)($d['categoria_id'] ?? 0) ?>" data-valor="<?= (float)($d['valor'] ?? 0) ?>" data-moeda="<?= htmlspecialchars($d['moeda'] ?? 'BRL') ?>" data-competencia="<?= htmlspecialchars(substr($d['competencia'] ?? '', 0, 7)) ?>" data-vencimento="<?= htmlspecialchars($d['vencimento'] ?? '') ?>" data-status="<?= htmlspecialchars($d['status'] ?? 'prevista') ?>" data-forma-pagamento="<?= htmlspecialchars($d['forma_pagamento'] ?? '') ?>" data-favorecido="<?= htmlspecialchars($d['favorecido'] ?? '') ?>" data-observacoes="<?= htmlspecialchars($d['observacoes'] ?? '') ?>" data-virtual="<?= !empty($d['is_virtual']) ? '1' : '0' ?>"><i class="fas fa-edit"></i></button>
                            <?php if (empty($d['is_virtual']) && $d['status'] !== 'paga' && $d['status'] !== 'cancelada'): ?>
                            <form method="POST" action="/admin/despesas/pagar/<?= $d['id'] ?>" class="d-inline" onsubmit="return confirm('Marcar como paga?')"><button type="submit" class="btn btn-outline-success py-0 px-1" style="font-size:11px;"><i class="fas fa-check"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Desktop: Table -->
            <div class="d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                    <thead class="table-light"><tr><th></th><th>Descrição</th><th>Categoria</th><th>Tipo</th><th>Competência</th><th>Vencimento</th><th class="text-end">Valor</th><th>Status</th><th>Origem</th><th>Ações</th></tr></thead>
                    <tbody>
                    <?php if (empty($despesas)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Nenhuma despesa encontrada.</td></tr>
                    <?php else: ?>
                    <?php
                    $statusBadge = ['prevista'=>'bg-secondary','a_vencer'=>'bg-warning text-dark','vencida'=>'bg-danger','paga'=>'bg-success','parcialmente_paga'=>'bg-info','cancelada'=>'bg-dark bg-opacity-50'];
                    $tipoBadge = ['avulsa'=>'Avulsa','fixa'=>'Fixa','recorrente'=>'Recorrente','parcelada'=>'Parcelada','comissao'=>'Comissão','por_hora'=>'Por Hora'];
                    foreach ($despesas as $d):
                        $stClass = $statusBadge[$d['status'] ?? ''] ?? 'bg-secondary';
                    ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input" value="<?= $d['id'] ?>"></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($d['descricao'] ?? '') ?></div>
                            <?php if ($d['favorecido']): ?><div class="text-muted" style="font-size:10px;"><?= htmlspecialchars($d['favorecido']) ?></div><?php endif; ?>
                        </td>
                        <td><?php if ($d['categoria_nome']): ?><span class="badge" style="background:<?= $d['categoria_cor'] ?? '#6b7280' ?>;font-size:10px;"><?= htmlspecialchars($d['categoria_nome']) ?></span><?php else: ?>-<?php endif; ?></td>
                        <td><span class="badge bg-light text-dark border" style="font-size:10px;"><?= $tipoBadge[$d['tipo'] ?? ''] ?? $d['tipo'] ?></span></td>
                        <td><?= $d['competencia'] ? date('m/Y', strtotime($d['competencia'])) : '-' ?></td>
                        <td><?= $d['vencimento'] ? date('d/m/Y', strtotime($d['vencimento'])) : '-' ?></td>
                        <td class="text-end fw-bold"><?= ($d['moeda'] ?? 'BRL') === 'USD' ? '$ ' : 'R$ ' ?><?= number_format((float)($d['valor'] ?? 0), 2, ',', '.') ?></td>
                        <td><span class="badge <?= $stClass ?>" style="font-size:10px;"><?= ucfirst(str_replace('_', ' ', $d['status'] ?? '')) ?></span></td>
                        <td><span class="text-muted" style="font-size:10px;"><?= ucfirst($d['origem'] ?? 'manual') ?></span></td>
                        <td>
                            <?php if (empty($d['is_virtual'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-editar-despesa" title="Editar" data-id="<?= $d['id'] ?>" data-descricao="<?= htmlspecialchars($d['descricao'] ?? '') ?>" data-categoria="<?= (int)($d['categoria_id'] ?? 0) ?>" data-valor="<?= (float)($d['valor'] ?? 0) ?>" data-moeda="<?= htmlspecialchars($d['moeda'] ?? 'BRL') ?>" data-competencia="<?= htmlspecialchars(substr($d['competencia'] ?? '', 0, 7)) ?>" data-vencimento="<?= htmlspecialchars($d['vencimento'] ?? '') ?>" data-status="<?= htmlspecialchars($d['status'] ?? 'prevista') ?>" data-forma-pagamento="<?= htmlspecialchars($d['forma_pagamento'] ?? '') ?>" data-favorecido="<?= htmlspecialchars($d['favorecido'] ?? '') ?>" data-observacoes="<?= htmlspecialchars($d['observacoes'] ?? '') ?>" data-virtual="0"><i class="fas fa-edit"></i></button>
                            <?php if ($d['status'] !== 'paga' && $d['status'] !== 'cancelada'): ?>
                            <form method="POST" action="/admin/despesas/pagar/<?= $d['id'] ?>" class="d-inline" onsubmit="return confirm('Marcar como paga?')"><button type="submit" class="btn btn-sm btn-outline-success" title="Pagar"><i class="fas fa-check"></i></button></form>
                            <form method="POST" action="/admin/despesas/cancelar/<?= $d['id'] ?>" class="d-inline ms-1" onsubmit="return confirm('Cancelar?')"><button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar"><i class="fas fa-times"></i></button></form>
                            <?php endif; ?>
                            <?php if ($d['status'] === 'cancelada'): ?>
                            <form method="POST" action="/admin/despesas/excluir/<?= $d['id'] ?>" class="d-inline ms-1" onsubmit="return confirm('Excluir permanentemente?')"><button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash"></i></button></form>
                            <?php endif; ?>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-editar-despesa" title="Editar recorrência" data-id="<?= $d['id'] ?>" data-descricao="<?= htmlspecialchars($d['descricao'] ?? '') ?>" data-categoria="<?= (int)($d['categoria_id'] ?? 0) ?>" data-valor="<?= (float)($d['valor'] ?? 0) ?>" data-moeda="<?= htmlspecialchars($d['moeda'] ?? 'BRL') ?>" data-competencia="<?= htmlspecialchars(substr($d['competencia'] ?? '', 0, 7)) ?>" data-vencimento="<?= htmlspecialchars($d['vencimento'] ?? '') ?>" data-status="<?= htmlspecialchars($d['status'] ?? 'prevista') ?>" data-forma-pagamento="<?= htmlspecialchars($d['forma_pagamento'] ?? '') ?>" data-favorecido="<?= htmlspecialchars($d['favorecido'] ?? '') ?>" data-observacoes="<?= htmlspecialchars($d['observacoes'] ?? '') ?>" data-virtual="1"><i class="fas fa-edit"></i></button>
                            <form method="POST" action="/admin/despesas/pagar-recorrencia/<?= $d['id'] ?>" class="d-inline" onsubmit="return confirm('Marcar como paga este mês?')"><button type="submit" class="btn btn-sm btn-outline-success" title="Pagar"><i class="fas fa-check"></i></button></form>
                            <form method="POST" action="/admin/despesas/cancelar-recorrencia/<?= $d['id'] ?>" class="d-inline ms-1" onsubmit="return confirm('Cancelar este mês?')"><button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar"><i class="fas fa-times"></i></button></form>
                            <?php endif; ?>
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
    <?php endif; // end comissoes else (todas) ?>
    <?php endif; // end tab todas||comissoes ?>

    <?php if ($tab === 'recorrentes'): ?>
    <!-- RECORRENTES -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="fas fa-sync-alt me-2 text-info"></i>Recorrências Ativas</h6>
            <button class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-target="#modalNovaDespesa" onclick="setTimeout(function(){document.getElementById('despesa-tipo').value='recorrente';toggleTipoFields();},300)"><i class="fas fa-plus me-1"></i>Nova</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                    <thead class="table-light"><tr><th>Descrição</th><th>Categoria</th><th>Frequência</th><th>Dia</th><th class="text-end">Valor</th><th>Próxima</th><th>Fim</th></tr></thead>
                    <tbody>
                    <?php if (empty($recorrencias)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma recorrência cadastrada.</td></tr>
                    <?php else: foreach ($recorrencias as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['descricao']) ?></td>
                        <td><?= !empty($r['categoria_nome']) ? '<span class="badge" style="background:'.($r['categoria_cor']??'#6b7280').';font-size:10px;">'.htmlspecialchars($r['categoria_nome']).'</span>' : '-' ?></td>
                        <td><?= ucfirst($r['frequencia'] ?? '') ?></td>
                        <td><?= $r['dia_vencimento'] ?? '-' ?></td>
                        <td class="text-end fw-bold"><?= fmtD($r['valor']) ?></td>
                        <td><?= $r['proxima_geracao'] ? date('d/m/Y', strtotime($r['proxima_geracao'])) : '-' ?></td>
                        <td><?= $r['data_fim'] ? date('m/Y', strtotime($r['data_fim'])) : 'Sem fim' ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'parceladas'): ?>
    <!-- PARCELADAS -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="fas fa-layer-group me-2 text-warning"></i>Parcelamentos em Andamento</h6>
            <button class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-target="#modalNovaDespesa" onclick="setTimeout(function(){document.getElementById('despesa-tipo').value='parcelada';toggleTipoFields();},300)"><i class="fas fa-plus me-1"></i>Novo</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                    <thead class="table-light"><tr><th>Descrição</th><th>Categoria</th><th class="text-end">Total</th><th>Parcelas</th><th class="text-end">Valor/Parc.</th><th class="text-end">Pago</th><th class="text-end">Saldo</th><th>Progresso</th></tr></thead>
                    <tbody>
                    <?php if (empty($parcelamentos)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Nenhum parcelamento ativo.</td></tr>
                    <?php else: foreach ($parcelamentos as $p): $pct = $p['quantidade_parcelas'] > 0 ? round($p['parcelas_pagas'] / $p['quantidade_parcelas'] * 100) : 0; ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($p['descricao']) ?></td>
                        <td><?= !empty($p['categoria_nome']) ? '<span class="badge" style="background:'.($p['categoria_cor']??'#6b7280').';font-size:10px;">'.htmlspecialchars($p['categoria_nome']).'</span>' : '-' ?></td>
                        <td class="text-end fw-bold"><?= fmtD($p['valor_total']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= $p['parcelas_pagas'] ?>/<?= $p['quantidade_parcelas'] ?></span></td>
                        <td class="text-end"><?= fmtD($p['valor_parcela']) ?></td>
                        <td class="text-end text-success"><?= fmtD($p['valor_total'] - $p['saldo_restante']) ?></td>
                        <td class="text-end text-danger"><?= fmtD($p['saldo_restante']) ?></td>
                        <td style="min-width:80px;"><div class="progress" style="height:5px;"><div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div></div><div class="text-muted text-center" style="font-size:9px;"><?= $pct ?>%</div></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'categorias'): ?>
    <!-- CATEGORIAS -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-tags me-2"></i>Categorias de Despesas</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Cor</th><th>Nome</th><th>Grupo</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($categorias as $cat): ?>
                    <tr>
                        <td><span class="d-inline-block rounded-circle" style="width:12px;height:12px;background:<?= $cat['cor'] ?? '#6b7280' ?>;"></span></td>
                        <td class="fw-semibold"><?= htmlspecialchars($cat['nome']) ?></td>
                        <td><span class="badge bg-light text-dark border" style="font-size:10px;"><?= ucfirst(str_replace('_', ' ', $cat['grupo'] ?? '')) ?></span></td>
                        <td><?= $cat['ativa'] ? '<span class="badge bg-success">Ativa</span>' : '<span class="badge bg-secondary">Inativa</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'relatorios'): ?>
    <!-- RELATÓRIOS -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2 text-primary"></i>Relatório por Categoria</h6>
                    <p class="text-muted small">Quanto foi gasto por tipo de despesa no período.</p>
                    <a href="/admin/relatorio-geral" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i>Ver no Financeiro</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="fas fa-calendar-alt me-2 text-info"></i>Relatório por Período</h6>
                    <p class="text-muted small">Despesas por mês com comparativo.</p>
                    <a href="/admin/despesas?tab=todas" class="btn btn-sm btn-outline-info"><i class="fas fa-filter me-1"></i>Filtrar por período</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="fas fa-sync-alt me-2 text-warning"></i>Relatório de Recorrências</h6>
                    <p class="text-muted small">Despesas fixas e previsão de custos futuros.</p>
                    <a href="/admin/despesas?tab=recorrentes" class="btn btn-sm btn-outline-warning"><i class="fas fa-list me-1"></i>Ver recorrências</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="fas fa-layer-group me-2 text-danger"></i>Relatório de Parcelamentos</h6>
                    <p class="text-muted small">Parcelas futuras, saldo total a pagar, compromissos.</p>
                    <a href="/admin/despesas?tab=parceladas" class="btn btn-sm btn-outline-danger"><i class="fas fa-list me-1"></i>Ver parcelamentos</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="fas fa-hand-holding-usd me-2 text-purple"></i>Relatório de Comissões</h6>
                    <p class="text-muted small">Comissão por pessoa, gerada, a pagar, paga.</p>
                    <a href="/admin/despesas?tab=comissoes" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list me-1"></i>Ver comissões</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="fas fa-chart-line me-2 text-success"></i>Fluxo de Caixa Previsto</h6>
                    <p class="text-muted small">Entradas previstas vs saídas previstas = saldo projetado.</p>
                    <a href="/admin/relatorio-geral" class="btn btn-sm btn-outline-success"><i class="fas fa-external-link-alt me-1"></i>Ver DRE no Financeiro</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Visualização Moeda/Idioma -->
<div class="modal fade" id="modalDespView" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-globe me-2"></i>Visualização</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-semibold small">Moeda de exibição</label><select id="desp-view-currency" class="form-select"><option value="BRL">BRL (Real)</option><option value="USD">USD (Dólar)</option></select></div>
                <div class="mb-0"><label class="form-label fw-semibold small">Idioma</label><select id="desp-view-lang" class="form-select"><option value="pt">Português (PT-BR)</option><option value="en">English (EN)</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary btn-sm" onclick="applyDespView();bootstrap.Modal.getInstance(document.getElementById('modalDespView')).hide();"><i class="fas fa-check me-1"></i>Aplicar</button></div>
        </div>
    </div>
</div>

<!-- Modal Nova Despesa -->
<div class="modal fade" id="modalNovaDespesa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/admin/despesas/criar">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Nova Despesa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label small fw-semibold">Descrição</label><input type="text" name="descricao" class="form-control" required placeholder="Ex: Aluguel sede comercial"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Tipo</label><select name="tipo" class="form-select" id="despesa-tipo" onchange="toggleTipoFields()"><option value="avulsa">Avulsa</option><option value="fixa">Fixa</option><option value="recorrente">Recorrente</option><option value="parcelada">Parcelada</option><option value="por_hora">Por Hora</option></select></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Categoria</label><select name="categoria_id" class="form-select"><option value="">Selecione</option><?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Moeda</label><select name="moeda" class="form-select" id="despesa-moeda" onchange="updateValorLabel()"><option value="BRL">BRL (Real)</option><option value="USD">USD (Dólar)</option></select></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold" id="valor-label">Valor (R$)</label><input type="number" name="valor" class="form-control" step="0.01" min="0" required></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Competência</label><input type="month" name="competencia" class="form-control" value="<?= date('Y-m') ?>"></div>
                    <div class="col-md-4" id="field-vencimento"><label class="form-label small fw-semibold">Vencimento</label><input type="date" name="vencimento" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Forma de pagamento</label><select name="forma_pagamento" class="form-select"><option value="">Selecione</option><option value="pix">Pix</option><option value="boleto">Boleto</option><option value="cartao_credito">Cartão de crédito</option><option value="transferencia">Transferência</option><option value="debito_automatico">Débito automático</option></select></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Favorecido</label><input type="text" name="favorecido" class="form-control" placeholder="Nome do fornecedor/beneficiário"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Status</label><select name="status" class="form-select"><option value="prevista">Prevista</option><option value="a_vencer">A vencer</option><option value="paga">Paga</option></select></div>
                    <!-- Campos recorrência -->
                    <div class="col-12 d-none" id="fields-recorrencia">
                        <div class="card border-info"><div class="card-body">
                            <h6 class="fw-bold small text-info mb-2"><i class="fas fa-sync-alt me-1"></i>Configuração da Recorrência</h6>
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label small">Frequência</label><select name="frequencia" class="form-select form-select-sm"><option value="mensal">Mensal</option><option value="semanal">Semanal</option><option value="quinzenal">Quinzenal</option><option value="anual">Anual</option></select></div>
                                <div class="col-md-4"><label class="form-label small">Dia do vencimento</label><input type="number" name="dia_vencimento" class="form-control form-control-sm" min="1" max="31" value="1"></div>
                                <div class="col-md-4"><label class="form-label small">Data início</label><input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                                <div class="col-md-4"><label class="form-label small">Data fim (opcional)</label><input type="date" name="data_fim" class="form-control form-control-sm"></div>
                            </div>
                        </div></div>
                    </div>
                    <!-- Campos por hora -->
                    <div class="col-md-4 d-none" id="fields-por-hora">
                        <label class="form-label small fw-semibold">Nome da pessoa</label><input type="text" name="pessoa_nome" class="form-control" placeholder="Ex: João Silva">
                    </div>
                    <div class="col-md-4 d-none" id="fields-por-hora-horas">
                        <label class="form-label small fw-semibold">Horas trabalhadas</label><input type="number" name="horas_trabalhadas" class="form-control" step="0.5" min="0.5" placeholder="Ex: 8">
                    </div>
                    <div class="col-md-4 d-none" id="fields-por-hora-valor">
                        <label class="form-label small fw-semibold" id="valor-hora-label">Valor por hora (R$)</label><input type="number" name="valor_hora" class="form-control" step="0.01" min="0" placeholder="Ex: 50.00">
                    </div>
                    <!-- Campos parcelamento -->
                    <div class="col-12 d-none" id="fields-parcelamento">
                        <div class="card border-warning"><div class="card-body">
                            <h6 class="fw-bold small text-warning mb-2"><i class="fas fa-layer-group me-1"></i>Configuração do Parcelamento</h6>
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label small">Valor total</label><input type="number" name="valor_total" class="form-control form-control-sm" step="0.01" min="0"></div>
                                <div class="col-md-4"><label class="form-label small">Quantidade de parcelas</label><input type="number" name="quantidade_parcelas" class="form-control form-control-sm" min="2" max="60" value="10"></div>
                                <div class="col-md-4"><label class="form-label small">Data 1ª parcela</label><input type="date" name="data_primeira_parcela" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                            </div>
                        </div></div>
                    </div>
                    <div class="col-12"><label class="form-label small fw-semibold">Observações</label><textarea name="observacoes" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-dark btn-sm px-4"><i class="fas fa-save me-1"></i>Salvar</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Despesa -->
<div class="modal fade" id="modalEditarDespesa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formEditarDespesa" action="">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Despesa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label small fw-semibold">Descrição</label><input type="text" name="descricao" id="edit-descricao" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Categoria</label><select name="categoria_id" id="edit-categoria" class="form-select"><option value="">Selecione</option><?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Moeda</label><select name="moeda" id="edit-moeda" class="form-select"><option value="BRL">BRL (Real)</option><option value="USD">USD (Dólar)</option></select></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Valor</label><input type="number" name="valor" id="edit-valor" class="form-control" step="0.01" min="0" required></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Competência</label><input type="month" name="competencia" id="edit-competencia" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Vencimento</label><input type="date" name="vencimento" id="edit-vencimento" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Status</label><select name="status" id="edit-status" class="form-select"><option value="prevista">Prevista</option><option value="a_vencer">A vencer</option><option value="vencida">Vencida</option><option value="paga">Paga</option><option value="cancelada">Cancelada</option></select></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Forma de pagamento</label><select name="forma_pagamento" id="edit-forma-pagamento" class="form-select"><option value="">Selecione</option><option value="pix">Pix</option><option value="boleto">Boleto</option><option value="cartao_credito">Cartão de crédito</option><option value="transferencia">Transferência</option><option value="debito_automatico">Débito automático</option></select></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Favorecido</label><input type="text" name="favorecido" id="edit-favorecido" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Observações</label><textarea name="observacoes" id="edit-observacoes" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-dark btn-sm px-4"><i class="fas fa-save me-1"></i>Salvar</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleTipoFields() {
    const tipo = document.getElementById('despesa-tipo').value;
    document.getElementById('fields-recorrencia').classList.toggle('d-none', tipo !== 'recorrente');
    document.getElementById('fields-parcelamento').classList.toggle('d-none', tipo !== 'parcelada');
    document.getElementById('fields-por-hora').classList.toggle('d-none', tipo !== 'por_hora');
    document.getElementById('fields-por-hora-horas').classList.toggle('d-none', tipo !== 'por_hora');
    document.getElementById('fields-por-hora-valor').classList.toggle('d-none', tipo !== 'por_hora');
    // Auto-calcular valor quando tipo é por_hora
    if (tipo === 'por_hora') {
        calcularValorHora();
    }
}
function calcularValorHora() {
    const horas = parseFloat(document.querySelector('input[name="horas_trabalhadas"]').value) || 0;
    const valorHora = parseFloat(document.querySelector('input[name="valor_hora"]').value) || 0;
    const total = horas * valorHora;
    if (total > 0) {
        document.querySelector('input[name="valor"]').value = total.toFixed(2);
    }
}
function updateValorLabel() {
    const moeda = document.getElementById('despesa-moeda').value;
    document.getElementById('valor-label').textContent = moeda === 'USD' ? 'Valor ($)' : 'Valor (R$)';
    document.getElementById('valor-hora-label').textContent = moeda === 'USD' ? 'Valor por hora ($)' : 'Valor por hora (R$)';
}
document.addEventListener('DOMContentLoaded', function() {
    const horasInput = document.querySelector('input[name="horas_trabalhadas"]');
    const valorHoraInput = document.querySelector('input[name="valor_hora"]');
    if (horasInput && valorHoraInput) {
        horasInput.addEventListener('input', calcularValorHora);
        valorHoraInput.addEventListener('input', calcularValorHora);
    }
});
function exportarDespesas() {
    window.location.href = '/admin/despesas?tab=todas&export=csv';
}

// Visualização moeda/idioma (apenas visual, não altera dados)
const DESP_TAXA = <?= (float)($taxaUsdBrl ?? 5.85) ?>;

function abrirEditarDespesa(btn) {
    var isVirtual = btn.dataset.virtual === '1';
    var actionUrl = isVirtual
        ? '/admin/despesas/editar-recorrencia/' + btn.dataset.id
        : '/admin/despesas/editar/' + btn.dataset.id;
    document.getElementById('formEditarDespesa').action = actionUrl;
    document.getElementById('edit-descricao').value = btn.dataset.descricao || '';
    document.getElementById('edit-categoria').value = btn.dataset.categoria || '';
    document.getElementById('edit-valor').value = btn.dataset.valor || '';
    document.getElementById('edit-moeda').value = btn.dataset.moeda || 'BRL';
    document.getElementById('edit-competencia').value = btn.dataset.competencia || '';
    document.getElementById('edit-vencimento').value = btn.dataset.vencimento || '';
    document.getElementById('edit-status').value = btn.dataset.status || 'prevista';
    document.getElementById('edit-forma-pagamento').value = btn.dataset.formaPagamento || '';
    document.getElementById('edit-favorecido').value = btn.dataset.favorecido || '';
    document.getElementById('edit-observacoes').value = btn.dataset.observacoes || '';
    new bootstrap.Modal(document.getElementById('modalEditarDespesa')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-editar-despesa').forEach(function(btn) {
        btn.addEventListener('click', function() { abrirEditarDespesa(this); });
    });
});
function applyDespView() {
    const cur = document.getElementById('desp-view-currency').value;
    const lang = document.getElementById('desp-view-lang').value;
    document.querySelectorAll('.desp-val').forEach(el => {
        const brl = parseFloat(el.getAttribute('data-brl')) || 0;
        if (cur === 'USD') el.textContent = '$ ' + (brl / DESP_TAXA).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
        else el.textContent = 'R$ ' + brl.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
    });
    const labels = lang === 'en' ? {desp_mes:'Month expenses',pago_mes:'Paid this month',aberto:'Outstanding',vencido:'Overdue',prox30:'Next 30 days',comissoes:'Commissions due'} : {desp_mes:'Despesas do mês',pago_mes:'Pago no mês',aberto:'Em aberto',vencido:'Vencido',prox30:'Próximos 30 dias',comissoes:'Comissões a pagar'};
    document.querySelectorAll('[data-desp-i18n]').forEach(el => { const k = el.getAttribute('data-desp-i18n'); if (labels[k]) el.textContent = labels[k]; });
}
</script>
