<?php
$totais = $totais ?? [];
$porStatus = $porStatus ?? [];
$porMoeda = $porMoeda ?? [];
$porPagamento = $porPagamento ?? [];
$totaisPorMoedaCards = $totaisPorMoedaCards ?? [];
$taxaUsdBrl = (float)($taxaUsdBrl ?? 5.5);
$statusList = $statusList ?? [];
$dateStart = $dateStart ?? date('Y-m-01');
$dateEnd = $dateEnd ?? date('Y-m-d');
$statusFilter = $statusFilter ?? [];
$moedaFilter = $moedaFilter ?? '';

$usd = $totaisPorMoedaCards['USD'] ?? [];
$brl = $totaisPorMoedaCards['BRL'] ?? [];

function fmtNum($v) { return number_format((float)($v ?? 0), 2, ',', '.'); }
function totalEmBrl($usdRow, $brlRow, $campo, $taxa) {
    return (float)($brlRow[$campo] ?? 0) + ((float)($usdRow[$campo] ?? 0) * $taxa);
}

$regionalData = [
    'taxaUsdBrl' => $taxaUsdBrl, 'usd' => $usd, 'brl' => $brl,
    'porStatus' => $porStatus, 'porMoeda' => $porMoeda, 'porPagamento' => $porPagamento,
    'totais' => $totais, 'statusLabels' => $statusList,
];

// Consolidar status para tabela
$statusLabels = $statusList;
$statusConsolidado = [];
$totalQtd = 0; $totalSubtotal = 0; $totalServicos = 0; $totalImpostos = 0; $totalFrete = 0; $totalTotal = 0;
foreach ($porStatus as $row) {
    $st = $row['status'] ?? 'N/A';
    if (!isset($statusConsolidado[$st])) {
        $statusConsolidado[$st] = ['status' => $st, 'qtd' => 0, 'subtotal' => 0, 'servicos' => 0, 'impostos' => 0, 'frete' => 0, 'total' => 0];
    }
    $moedaRow = strtoupper($row['moeda'] ?? 'USD');
    $fator = ($moedaRow === 'BRL') ? 1.0 : $taxaUsdBrl;
    $statusConsolidado[$st]['qtd'] += (int)($row['qtd'] ?? 0);
    $statusConsolidado[$st]['subtotal'] += (float)($row['subtotal'] ?? 0) * $fator;
    $statusConsolidado[$st]['servicos'] += (float)($row['servicos'] ?? 0) * $fator;
    $statusConsolidado[$st]['impostos'] += (float)($row['impostos'] ?? 0) * $fator;
    $statusConsolidado[$st]['frete'] += (float)($row['frete'] ?? 0) * $fator;
    $statusConsolidado[$st]['total'] += (float)($row['total'] ?? 0) * $fator;
}
usort($statusConsolidado, function($a, $b) { return $b['total'] <=> $a['total']; });
foreach ($statusConsolidado as $r) { $totalQtd += $r['qtd']; $totalSubtotal += $r['subtotal']; $totalServicos += $r['servicos']; $totalImpostos += $r['impostos']; $totalFrete += $r['frete']; $totalTotal += $r['total']; }

// Totais moeda
$totalMoedaQtd = 0; $totalMoedaTotal = 0;
foreach ($porMoeda as $r) { $totalMoedaQtd += (int)($r['qtd'] ?? 0); $totalMoedaTotal += (float)($r['total'] ?? 0); }
// Totais pagamento
$totalPagQtd = 0; $totalPagTotal = 0;
foreach ($porPagamento as $r) { $totalPagQtd += (int)($r['qtd'] ?? 0); $totalPagTotal += (float)($r['total'] ?? 0); }

$periodoLabel = date('d/m/Y', strtotime($dateStart)) . ' a ' . date('d/m/Y', strtotime($dateEnd));
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-chart-line me-2"></i>Financeiro</h4>
            <p class="text-muted small mb-0">Visão consolidada de pedidos, receitas e impostos</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-2 bg-light rounded-pill px-3 py-2">
                <i class="fas fa-exchange-alt text-muted small"></i>
                <span class="small text-muted">USD → BRL</span>
                <span class="fw-bold"><?= fmtNum($taxaUsdBrl) ?></span>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="tab-geral" data-bs-toggle="tab" data-bs-target="#pane-geral" type="button" role="tab"><i class="fas fa-chart-bar me-1"></i>Relatório Geral</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="tab-regional" type="button" role="tab" onclick="abrirModalRegional()"><i class="fas fa-globe me-1"></i>Relatório Regional</button>
        </li>
    </ul>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="pane-geral" role="tabpanel">

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2"><i class="fas fa-sliders-h text-muted"></i><span class="fw-semibold small">Filtros do relatório</span></div>
                <a href="/admin/relatorio-geral" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Limpar filtros</a>
            </div>
            <form method="GET" action="/admin/relatorio-geral" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted"><i class="far fa-calendar me-1"></i>Data início</label>
                    <input type="date" name="date_start" class="form-control form-control-sm" value="<?= htmlspecialchars($dateStart) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><i class="far fa-calendar me-1"></i>Data fim</label>
                    <input type="date" name="date_end" class="form-control form-control-sm" value="<?= htmlspecialchars($dateEnd) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><i class="fas fa-tag me-1"></i>Status do pedido</label>
                    <div class="border rounded p-2" style="max-height:120px;overflow-y:auto;background:#fff;">
                        <?php foreach ($statusList as $key => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status[]" value="<?= htmlspecialchars($key) ?>" id="st_<?= htmlspecialchars($key) ?>" <?= in_array($key, $statusFilter) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="st_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><i class="fas fa-coins me-1"></i>Moeda</label>
                    <select name="moeda" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="BRL" <?= $moedaFilter === 'BRL' ? 'selected' : '' ?>>BRL — Real</option>
                        <option value="USD" <?= $moedaFilter === 'USD' ? 'selected' : '' ?>>USD — Dólar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Total de Pedidos -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="fas fa-receipt text-primary"></i>
                </div>
                <div>
                    <div class="fw-semibold">Total de pedidos</div>
                    <div class="text-muted small">Período: <?= $periodoLabel ?></div>
                </div>
            </div>
            <div class="text-end">
                <span class="fs-3 fw-bold"><?= number_format((int)($totais['qtd_pedidos'] ?? 0), 0, '', '.') ?></span>
            </div>
        </div>
    </div>

    <!-- Cards Financeiros -->
    <?php
    $campos = [
        ['key' => 'total', 'label' => 'Total geral', 'icon' => 'fas fa-dollar-sign', 'color' => 'primary'],
        ['key' => 'subtotal', 'label' => 'Subtotal produtos', 'icon' => 'fas fa-box', 'color' => 'dark'],
        ['key' => 'servicos', 'label' => 'Taxa de serviço', 'icon' => 'fas fa-concierge-bell', 'color' => 'info'],
        ['key' => 'impostos', 'label' => 'Impostos', 'icon' => 'fas fa-landmark', 'color' => 'warning'],
        ['key' => 'imposto_local', 'label' => 'Imposto local', 'icon' => 'fas fa-flag', 'color' => 'danger'],
        ['key' => 'frete', 'label' => 'Frete', 'icon' => 'fas fa-truck', 'color' => 'success'],
    ];
    ?>
    <div class="row g-3 mb-4">
    <?php foreach ($campos as $c):
        $vUsd = (float)($usd[$c['key']] ?? 0);
        $vBrl = (float)($brl[$c['key']] ?? 0);
        $convertidoBrl = totalEmBrl($usd, $brl, $c['key'], $taxaUsdBrl);
        $temDuasMoedas = ($vUsd > 0 && $vBrl > 0);
        $temAlgo = ($vUsd > 0 || $vBrl > 0);
    ?>
    <div class="col-lg-4 col-md-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--bs-<?= $c['color'] ?>)!important">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="rounded-circle bg-<?= $c['color'] ?> bg-opacity-10 d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                        <i class="<?= $c['icon'] ?> text-<?= $c['color'] ?> small"></i>
                    </div>
                    <span class="text-muted small fw-semibold"><?= $c['label'] ?></span>
                </div>

                <?php if (!$temAlgo): ?>
                <div class="text-center py-2">
                    <i class="fas fa-minus-circle text-muted mb-1"></i>
                    <div class="text-muted small">Sem valores no período</div>
                </div>
                <?php else: ?>
                    <?php if ($vUsd > 0): ?>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <span class="text-muted small">USD</span>
                        <span class="fs-5 fw-bold">$ <?= fmtNum($vUsd) ?></span>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <span class="text-muted small" style="font-size:.72rem">≈ BRL</span>
                        <span class="text-muted small">R$ <?= fmtNum($vUsd * $taxaUsdBrl) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($vBrl > 0): ?>
                    <div class="d-flex align-items-baseline justify-content-between <?= $vUsd > 0 ? 'mt-2 pt-2 border-top' : '' ?>">
                        <span class="text-muted small">BRL</span>
                        <span class="fs-5 fw-bold">R$ <?= fmtNum($vBrl) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($temDuasMoedas): ?>
                    <div class="mt-2 pt-2 border-top d-flex align-items-baseline justify-content-between">
                        <span class="fw-semibold small text-<?= $c['color'] ?>">Total em BRL</span>
                        <span class="fw-bold text-<?= $c['color'] ?>">R$ <?= fmtNum($convertidoBrl) ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- Tabelas -->
    <div class="row g-4">
        <!-- Por Status (full width) -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:32px;height:32px;"><i class="fas fa-list-alt text-secondary small"></i></div>
                        <div><h6 class="fw-bold mb-0">Por Status</h6><span class="text-muted small">Valores agrupados em R$</span></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Status</th><th class="text-end">Qtd</th><th class="text-end">Subtotal</th><th class="text-end">Serviço</th><th class="text-end">Impostos</th><th class="text-end">Frete</th><th class="text-end">Total (R$)</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($statusConsolidado)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">Nenhum dado</td></tr>
                            <?php else: ?>
                                <?php foreach ($statusConsolidado as $row): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/relatorio-geral?date_start=<?= $dateStart ?>&date_end=<?= $dateEnd ?>&status[]=<?= urlencode($row['status'] ?? '') ?>&moeda=<?= urlencode($moedaFilter) ?>" class="text-decoration-none">
                                            <span class="badge bg-secondary bg-opacity-10 text-dark"><?= htmlspecialchars($statusLabels[$row['status']] ?? ucfirst(str_replace('_', ' ', $row['status'] ?? 'N/A'))) ?></span>
                                        </a>
                                    </td>
                                    <td class="text-end"><?= (int)($row['qtd'] ?? 0) ?></td>
                                    <td class="text-end"><?= fmtNum($row['subtotal'] ?? 0) ?></td>
                                    <td class="text-end"><?= fmtNum($row['servicos'] ?? 0) ?></td>
                                    <td class="text-end"><?= fmtNum($row['impostos'] ?? 0) ?></td>
                                    <td class="text-end"><?= fmtNum($row['frete'] ?? 0) ?></td>
                                    <td class="text-end fw-bold"><?= fmtNum($row['total'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light fw-bold">
                                    <td>Total</td>
                                    <td class="text-end"><?= $totalQtd ?></td>
                                    <td class="text-end"><?= fmtNum($totalSubtotal) ?></td>
                                    <td class="text-end"><?= fmtNum($totalServicos) ?></td>
                                    <td class="text-end"><?= fmtNum($totalImpostos) ?></td>
                                    <td class="text-end"><?= fmtNum($totalFrete) ?></td>
                                    <td class="text-end"><?= fmtNum($totalTotal) ?></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Por Moeda -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:32px;height:32px;"><i class="fas fa-coins text-info small"></i></div>
                        <div><h6 class="fw-bold mb-0">Por Moeda</h6><span class="text-muted small">Distribuição cambial</span></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Moeda</th><th class="text-end">Qtd</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                            <?php if (empty($porMoeda)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">N/A</td></tr>
                            <?php else: ?>
                                <?php foreach ($porMoeda as $row): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(strtoupper($row['moeda'] ?? 'N/A')) ?></span></td>
                                    <td class="text-end"><?= (int)($row['qtd'] ?? 0) ?></td>
                                    <td class="text-end fw-bold"><?= fmtNum($row['total'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light fw-bold">
                                    <td>Total</td>
                                    <td class="text-end"><?= $totalMoedaQtd ?></td>
                                    <td class="text-end"><?= fmtNum($totalMoedaTotal) ?></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Por Pagamento -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:32px;height:32px;"><i class="fas fa-credit-card text-success small"></i></div>
                        <div><h6 class="fw-bold mb-0">Por Pagamento</h6><span class="text-muted small">Forma de pagamento</span></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Forma</th><th class="text-end">Qtd</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                            <?php if (empty($porPagamento)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">N/A</td></tr>
                            <?php else: ?>
                                <?php foreach ($porPagamento as $row): ?>
                                <tr>
                                    <td><i class="fas fa-circle text-muted me-1" style="font-size:6px;vertical-align:middle;"></i><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['forma'] ?? 'N/A'))) ?></td>
                                    <td class="text-end"><?= (int)($row['qtd'] ?? 0) ?></td>
                                    <td class="text-end fw-bold"><?= fmtNum($row['total'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light fw-bold">
                                    <td>Total</td>
                                    <td class="text-end"><?= $totalPagQtd ?></td>
                                    <td class="text-end"><?= fmtNum($totalPagTotal) ?></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div><!-- /pane-geral -->
    <div class="tab-pane fade" id="pane-regional" role="tabpanel"><div id="regional-content"></div></div>
    </div><!-- /tab-content -->
</div>

<!-- Modal Relatório Regional -->
<div class="modal fade" id="modalRegional" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-globe me-2"></i>Relatório Regional</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-semibold small">Moeda de exibição</label><select id="regional-moeda" class="form-select"><option value="BRL">BRL (Real)</option><option value="USD">USD (Dólar)</option></select></div>
                <div class="mb-0"><label class="form-label fw-semibold small">Idioma</label><select id="regional-idioma" class="form-select"><option value="pt">Português (PT-BR)</option><option value="en">English (EN)</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary btn-sm" onclick="gerarRelatorioRegional()"><i class="fas fa-check me-1"></i>Gerar</button></div>
        </div>
    </div>
</div>

<script>
(function(){
const DATA = <?= json_encode($regionalData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
const i18n = {
    pt: {title:'Relatório Regional',orders:'Total de Pedidos',total:'Total Geral',subtotal:'Subtotal Produtos',servicos:'Taxa de Serviço',impostos:'Impostos',imposto_local:'Imposto Local',frete:'Frete',byStatus:'Por Status',byPayment:'Por Forma de Pagamento',status:'Status',qty:'Qtd',form:'Forma',noData:'Nenhum dado',allConverted:'Todos os valores convertidos para',rate:'Taxa de conversão'},
    en: {title:'Regional Report',orders:'Total Orders',total:'Grand Total',subtotal:'Products Subtotal',servicos:'Service Fee',impostos:'Taxes',imposto_local:'Local Tax',frete:'Shipping',byStatus:'By Status',byPayment:'By Payment Method',status:'Status',qty:'Qty',form:'Method',noData:'No data',allConverted:'All values converted to',rate:'Conversion rate'}
};
const statusLabelsEn = {'pendente':'Pending','processando':'Processing','pago':'Paid','carne_pagando':'Installment Paying','carne_aguardando':'Installment Waiting','produto_consolidado':'Box Closed','etiqueta_gerada':'Label Generated','em_transporte':'In Transit','aguardando_liberacao_aduaneira':'Customs Clearance','enviado_ao_destinatario':'Sent to Recipient','entregue':'Delivered','cancelado':'Cancelled'};

function fmt(v, moeda) {
    const n = parseFloat(v)||0;
    if (moeda==='USD') return '$ '+n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    return 'R$ '+n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
}
function conv(val, orig, target, taxa) {
    const v=parseFloat(val)||0; if(orig===target) return v;
    if(orig==='USD'&&target==='BRL') return v*taxa;
    if(orig==='BRL'&&target==='USD') return v/taxa;
    return v;
}

window.abrirModalRegional = function() { new bootstrap.Modal(document.getElementById('modalRegional')).show(); };

window.gerarRelatorioRegional = function() {
    const moeda=document.getElementById('regional-moeda').value;
    const idioma=document.getElementById('regional-idioma').value;
    const t=i18n[idioma]||i18n.pt;
    const taxa=DATA.taxaUsdBrl;
    const labels=idioma==='en'?statusLabelsEn:DATA.statusLabels;
    const uD=DATA.usd||{}, bD=DATA.brl||{};
    const keys=['total','subtotal','servicos','impostos','imposto_local','frete'];
    const tots={};
    keys.forEach(k=>{tots[k]=conv(uD[k]||0,'USD',moeda,taxa)+conv(bD[k]||0,'BRL',moeda,taxa);});

    const sMap={};
    (DATA.porStatus||[]).forEach(r=>{const st=r.status||'N/A';if(!sMap[st])sMap[st]={status:st,qtd:0,subtotal:0,servicos:0,impostos:0,frete:0,total:0};const o=(r.moeda||'USD').toUpperCase();sMap[st].qtd+=parseInt(r.qtd)||0;sMap[st].subtotal+=conv(r.subtotal||0,o,moeda,taxa);sMap[st].servicos+=conv(r.servicos||0,o,moeda,taxa);sMap[st].impostos+=conv(r.impostos||0,o,moeda,taxa);sMap[st].frete+=conv(r.frete||0,o,moeda,taxa);sMap[st].total+=conv(r.total||0,o,moeda,taxa);});
    const sArr=Object.values(sMap).sort((a,b)=>b.total-a.total);

    const pMap={};
    (DATA.porPagamento||[]).forEach(r=>{const f=r.forma||'N/A';if(!pMap[f])pMap[f]={forma:f,qtd:0,total:0};pMap[f].qtd+=parseInt(r.qtd)||0;pMap[f].total+=parseFloat(r.total)||0;});

    const qtd=parseInt(DATA.totais.qtd_pedidos)||0;
    const rateLabel=moeda==='BRL'?('1 USD = '+taxa.toFixed(2)+' BRL'):('1 BRL = '+(1/taxa).toFixed(4)+' USD');

    let h='<div class="alert alert-info small mb-3"><i class="fas fa-info-circle me-1"></i>'+t.allConverted+' <strong>'+moeda+'</strong>. '+t.rate+': '+rateLabel+'</div>';
    h+='<div class="row g-3 mb-4">';
    [{key:'total',label:t.total,color:'primary',icon:'fas fa-dollar-sign'},{key:'subtotal',label:t.subtotal,color:'dark',icon:'fas fa-box'},{key:'servicos',label:t.servicos,color:'info',icon:'fas fa-concierge-bell'},{key:'impostos',label:t.impostos,color:'warning',icon:'fas fa-landmark'},{key:'imposto_local',label:t.imposto_local,color:'danger',icon:'fas fa-flag'},{key:'frete',label:t.frete,color:'success',icon:'fas fa-truck'}].forEach(c=>{
        const v=tots[c.key]||0;if(c.key==='imposto_local'&&v<=0)return;
        h+='<div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--bs-'+c.color+')!important"><div class="card-body"><div class="text-muted small mb-1"><i class="'+c.icon+' me-1"></i>'+c.label+'</div><div class="fs-4 fw-bold">'+fmt(v,moeda)+'</div></div></div></div>';
    });
    h+='</div>';
    h+='<div class="card border-0 shadow-sm mb-4"><div class="card-body d-flex align-items-center justify-content-between"><div><i class="fas fa-receipt me-2 text-muted"></i><span class="fw-semibold">'+t.orders+'</span></div><span class="fs-3 fw-bold">'+qtd.toLocaleString()+'</span></div></div>';

    h+='<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-list-alt me-2"></i>'+t.byStatus+'</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>'+t.status+'</th><th class="text-end">'+t.qty+'</th><th class="text-end">Subtotal</th><th class="text-end">Serviço</th><th class="text-end">Impostos</th><th class="text-end">Frete</th><th class="text-end">Total</th></tr></thead><tbody>';
    if(!sArr.length){h+='<tr><td colspan="7" class="text-center text-muted py-3">'+t.noData+'</td></tr>';}
    else{sArr.forEach(r=>{h+='<tr><td><span class="badge bg-secondary bg-opacity-10 text-dark">'+(labels[r.status]||r.status.replace(/_/g,' '))+'</span></td><td class="text-end">'+r.qtd+'</td><td class="text-end">'+fmt(r.subtotal,moeda)+'</td><td class="text-end">'+fmt(r.servicos,moeda)+'</td><td class="text-end">'+fmt(r.impostos,moeda)+'</td><td class="text-end">'+fmt(r.frete,moeda)+'</td><td class="text-end fw-bold">'+fmt(r.total,moeda)+'</td></tr>';});}
    h+='</tbody></table></div></div></div>';

    h+='<div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-credit-card me-2"></i>'+t.byPayment+'</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>'+t.form+'</th><th class="text-end">'+t.qty+'</th><th class="text-end">Total</th></tr></thead><tbody>';
    const pArr=Object.values(pMap).sort((a,b)=>b.total-a.total);
    if(!pArr.length){h+='<tr><td colspan="3" class="text-center text-muted py-3">'+t.noData+'</td></tr>';}
    else{pArr.forEach(r=>{h+='<tr><td>'+r.forma.replace(/_/g,' ')+'</td><td class="text-end">'+r.qtd+'</td><td class="text-end fw-bold">'+fmt(r.total,moeda)+'</td></tr>';});}
    h+='</tbody></table></div></div></div>';

    document.getElementById('regional-content').innerHTML=h;
    bootstrap.Modal.getInstance(document.getElementById('modalRegional')).hide();
    const tabEl=document.getElementById('tab-regional');
    tabEl.setAttribute('data-bs-toggle','tab');tabEl.setAttribute('data-bs-target','#pane-regional');
    new bootstrap.Tab(tabEl).show();
};
})();
</script>
