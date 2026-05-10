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

$regionalData = ['taxaUsdBrl'=>$taxaUsdBrl,'usd'=>$usd,'brl'=>$brl,'porStatus'=>$porStatus,'porMoeda'=>$porMoeda,'porPagamento'=>$porPagamento,'totais'=>$totais,'statusLabels'=>$statusList];

// Consolidar status
$statusLabels = $statusList;
$statusConsolidado = [];
$totalQtd=0;$totalSubtotal=0;$totalServicos=0;$totalImpostos=0;$totalFrete=0;$totalTotal=0;
foreach ($porStatus as $row) {
    $st=$row['status']??'N/A';
    if(!isset($statusConsolidado[$st]))$statusConsolidado[$st]=['status'=>$st,'qtd'=>0,'subtotal'=>0,'servicos'=>0,'impostos'=>0,'frete'=>0,'total'=>0];
    $fator=(strtoupper($row['moeda']??'USD')==='BRL')?1.0:$taxaUsdBrl;
    $statusConsolidado[$st]['qtd']+=(int)($row['qtd']??0);
    $statusConsolidado[$st]['subtotal']+=(float)($row['subtotal']??0)*$fator;
    $statusConsolidado[$st]['servicos']+=(float)($row['servicos']??0)*$fator;
    $statusConsolidado[$st]['impostos']+=(float)($row['impostos']??0)*$fator;
    $statusConsolidado[$st]['frete']+=(float)($row['frete']??0)*$fator;
    $statusConsolidado[$st]['total']+=(float)($row['total']??0)*$fator;
}
usort($statusConsolidado, function($a,$b){return $b['total']<=>$a['total'];});
foreach($statusConsolidado as $r){$totalQtd+=$r['qtd'];$totalSubtotal+=$r['subtotal'];$totalServicos+=$r['servicos'];$totalImpostos+=$r['impostos'];$totalFrete+=$r['frete'];$totalTotal+=$r['total'];}

$totalMoedaQtd=0;$totalMoedaTotal=0;
foreach($porMoeda as $r){$totalMoedaQtd+=(int)($r['qtd']??0);$totalMoedaTotal+=(float)($r['total']??0);}
$totalPagQtd=0;$totalPagTotal=0;
foreach($porPagamento as $r){$totalPagQtd+=(int)($r['qtd']??0);$totalPagTotal+=(float)($r['total']??0);}

$periodoLabel = date('d/m/Y', strtotime($dateStart)).' a '.date('d/m/Y', strtotime($dateEnd));

// Status colors for badges
$statusColors = ['pendente'=>'secondary','processando'=>'primary','pago'=>'success','carne_pagando'=>'info','carne_aguardando'=>'warning','produto_consolidado'=>'dark','etiqueta_gerada'=>'primary','em_transporte'=>'info','aguardando_liberacao_aduaneira'=>'warning','enviado_ao_destinatario'=>'dark','entregue'=>'success','cancelado'=>'danger'];
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                <i class="fas fa-chart-line text-white"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0">Financeiro</h4>
                <p class="text-muted small mb-0">Visão consolidada de pedidos, receitas e impostos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="border rounded-pill px-3 py-1 d-flex align-items-center gap-2 bg-white">
                <i class="fas fa-exchange-alt text-muted" style="font-size:11px;"></i>
                <span class="small">USD → BRL</span>
                <span class="fw-bold"><?= fmtNum($taxaUsdBrl) ?></span>
            </div>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="fas fa-download me-1"></i>Exportar</button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-4">
        <ul class="nav nav-pills gap-2" role="tablist">
            <li class="nav-item"><button class="nav-link active px-3 py-2" id="tab-geral" data-bs-toggle="tab" data-bs-target="#pane-geral" type="button"><i class="fas fa-chart-bar me-1"></i>Relatório Geral</button></li>
            <li class="nav-item"><button class="nav-link px-3 py-2" id="tab-regional" type="button" onclick="abrirModalRegional()"><i class="fas fa-globe me-1"></i>Relatório Regional</button></li>
        </ul>
    </div>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="pane-geral" role="tabpanel">

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2"><i class="fas fa-sliders-h text-muted"></i><span class="fw-semibold">Filtros do relatório</span></div>
                <a href="/admin/relatorio-geral" class="text-muted small text-decoration-none"><i class="fas fa-times me-1"></i>Limpar filtros</a>
            </div>
            <form method="GET" action="/admin/relatorio-geral">
                <div class="d-flex align-items-end flex-wrap gap-3">
                    <!-- Datas -->
                    <div>
                        <label class="form-label small text-muted mb-1"><i class="far fa-calendar me-1"></i>DATA INÍCIO</label>
                        <input type="date" name="date_start" class="form-control form-control-sm" value="<?= htmlspecialchars($dateStart) ?>">
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-1"><i class="far fa-calendar me-1"></i>DATA FIM</label>
                        <input type="date" name="date_end" class="form-control form-control-sm" value="<?= htmlspecialchars($dateEnd) ?>">
                    </div>

                    <!-- Status como chips/checkboxes inline -->
                    <div class="flex-grow-1">
                        <label class="form-label small text-muted mb-1"><i class="fas fa-tag me-1"></i>STATUS DO PEDIDO</label>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($statusList as $key => $label): ?>
                            <label class="btn btn-sm <?= in_array($key, $statusFilter) ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-2 py-1" style="font-size:11px;cursor:pointer;">
                                <input type="checkbox" name="status[]" value="<?= htmlspecialchars($key) ?>" <?= in_array($key, $statusFilter) ? 'checked' : '' ?> class="d-none" onchange="this.closest('label').classList.toggle('btn-primary');this.closest('label').classList.toggle('btn-outline-secondary');">
                                <?= htmlspecialchars($label) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Moeda -->
                    <div>
                        <label class="form-label small text-muted mb-1"><i class="fas fa-coins me-1"></i>MOEDA</label>
                        <select name="moeda" class="form-select form-select-sm" style="min-width:100px;">
                            <option value="">Todas</option>
                            <option value="BRL" <?= $moedaFilter === 'BRL' ? 'selected' : '' ?>>BRL — Real</option>
                            <option value="USD" <?= $moedaFilter === 'USD' ? 'selected' : '' ?>>USD — Dólar</option>
                        </select>
                    </div>

                    <!-- Filtrar -->
                    <div>
                        <button type="submit" class="btn btn-dark btn-sm px-4 py-2"><i class="fas fa-filter me-1"></i>Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Total de Pedidos -->
    <div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#1e293b 0%,#334155 100%);">
        <div class="card-body d-flex align-items-center justify-content-between py-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <i class="fas fa-receipt text-white"></i>
                </div>
                <div>
                    <div class="text-white fw-semibold text-uppercase small">Total de pedidos</div>
                    <div class="text-white text-opacity-75 small">Período: <?= $periodoLabel ?></div>
                </div>
            </div>
            <div class="text-end">
                <span class="fs-2 fw-bold text-white"><?= number_format((int)($totais['qtd_pedidos'] ?? 0), 0, '', '.') ?></span>
            </div>
        </div>
    </div>

    <!-- Cards Financeiros -->
    <?php
    $campos = [
        ['key'=>'total','label'=>'Total geral','icon'=>'fas fa-dollar-sign','color'=>'primary'],
        ['key'=>'subtotal','label'=>'Subtotal produtos','icon'=>'fas fa-box','color'=>'success'],
        ['key'=>'servicos','label'=>'Taxa de serviço','icon'=>'fas fa-concierge-bell','color'=>'info'],
        ['key'=>'impostos','label'=>'Impostos','icon'=>'fas fa-landmark','color'=>'warning'],
        ['key'=>'imposto_local','label'=>'Imposto local','icon'=>'fas fa-flag','color'=>'danger'],
        ['key'=>'frete','label'=>'Frete','icon'=>'fas fa-truck','color'=>'secondary'],
    ];
    $cardColors = ['primary'=>'#3b82f6','success'=>'#10b981','info'=>'#06b6d4','warning'=>'#f59e0b','danger'=>'#ef4444','secondary'=>'#64748b'];
    ?>
    <div class="row g-3 mb-4">
    <?php foreach ($campos as $c):
        $vUsd = (float)($usd[$c['key']] ?? 0);
        $vBrl = (float)($brl[$c['key']] ?? 0);
        $convertidoBrl = totalEmBrl($usd, $brl, $c['key'], $taxaUsdBrl);
        $temAlgo = ($vUsd > 0 || $vBrl > 0);
        $borderColor = $cardColors[$c['color']] ?? '#6b7280';
    ?>
    <div class="col-lg-4 col-md-6">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid <?= $borderColor ?>;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;background:<?= $borderColor ?>15;">
                            <i class="<?= $c['icon'] ?>" style="font-size:12px;color:<?= $borderColor ?>;"></i>
                        </div>
                        <span class="fw-semibold small"><?= $c['label'] ?></span>
                    </div>
                    <i class="fas fa-info-circle text-muted" style="font-size:12px;"></i>
                </div>

                <?php if (!$temAlgo): ?>
                <div class="text-center py-3">
                    <i class="fas fa-box-open text-muted d-block mb-2" style="font-size:24px;opacity:.4;"></i>
                    <div class="text-muted small fst-italic">Sem valores de <?= strtolower($c['label']) ?> no período</div>
                </div>
                <?php else: ?>
                    <?php if ($vUsd > 0): ?>
                    <div class="d-flex align-items-baseline justify-content-between mb-1">
                        <span class="text-muted small">USD</span>
                        <span class="fs-5 fw-bold">$ <?= fmtNum($vUsd) ?></span>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <span class="text-muted" style="font-size:11px;">≈ BRL</span>
                        <span class="text-muted small">R$ <?= fmtNum($vUsd * $taxaUsdBrl) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($vBrl > 0): ?>
                    <div class="d-flex align-items-baseline justify-content-between <?= $vUsd > 0 ? 'pt-2 border-top' : '' ?>">
                        <span class="text-muted small">BRL</span>
                        <span class="fs-5 fw-bold">R$ <?= fmtNum($vBrl) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($vUsd > 0 || $vBrl > 0): ?>
                    <div class="mt-3 pt-2 border-top d-flex align-items-baseline justify-content-between">
                        <span class="fw-bold small text-uppercase" style="color:<?= $borderColor ?>;font-size:11px;">Total em BRL</span>
                        <span class="fw-bold fs-5" style="color:<?= $borderColor ?>;">R$ <?= fmtNum($convertidoBrl) ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- Tabelas: Status (esquerda), Moeda + Pagamento (direita) -->
    <div class="row g-4">
        <!-- Por Status -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:28px;height:28px;"><i class="fas fa-list-alt text-secondary" style="font-size:11px;"></i></div>
                        <div><div class="fw-bold small">Por Status</div><div class="text-muted" style="font-size:10px;">Valores agrupados em R$</div></div>
                    </div>
                    <span class="text-muted small">Ver detalhes →</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size:12px;">
                            <thead class="table-light"><tr><th>Status</th><th class="text-end">Qtd</th><th class="text-end">Subtotal</th><th class="text-end">Serviço</th><th class="text-end">Impostos</th><th class="text-end">Frete</th><th class="text-end">Total (R$)</th></tr></thead>
                            <tbody>
                            <?php if (empty($statusConsolidado)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">Nenhum dado</td></tr>
                            <?php else: ?>
                                <?php foreach ($statusConsolidado as $row):
                                    $cor = $statusColors[$row['status']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:var(--bs-<?= $cor ?>);"></span><a href="/admin/relatorio-geral?date_start=<?= $dateStart ?>&date_end=<?= $dateEnd ?>&status[]=<?= urlencode($row['status']??'') ?>&moeda=<?= urlencode($moedaFilter) ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($statusLabels[$row['status']] ?? ucfirst(str_replace('_',' ',$row['status']??'N/A'))) ?></a></td>
                                    <td class="text-end"><?= (int)($row['qtd']??0) ?></td>
                                    <td class="text-end"><?= fmtNum($row['subtotal']??0) ?></td>
                                    <td class="text-end"><?= fmtNum($row['servicos']??0) ?></td>
                                    <td class="text-end"><?= fmtNum($row['impostos']??0) ?></td>
                                    <td class="text-end"><?= fmtNum($row['frete']??0) ?></td>
                                    <td class="text-end fw-bold"><?= fmtNum($row['total']??0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="fw-bold border-top"><td>Total</td><td class="text-end"><?= $totalQtd ?></td><td class="text-end"><?= fmtNum($totalSubtotal) ?></td><td class="text-end"><?= fmtNum($totalServicos) ?></td><td class="text-end"><?= fmtNum($totalImpostos) ?></td><td class="text-end"><?= fmtNum($totalFrete) ?></td><td class="text-end"><?= fmtNum($totalTotal) ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Por Moeda -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:28px;height:28px;"><i class="fas fa-coins text-info" style="font-size:11px;"></i></div>
                        <div><div class="fw-bold small">Por Moeda</div><div class="text-muted" style="font-size:10px;">Distribuição cambial</div></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size:12px;">
                            <thead class="table-light"><tr><th>Moeda</th><th class="text-end">Qtd</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                            <?php if (empty($porMoeda)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">N/A</td></tr>
                            <?php else: ?>
                                <?php foreach ($porMoeda as $row):
                                    $moedaColor = strtoupper($row['moeda']??'')==='BRL' ? '#10b981' : '#3b82f6';
                                ?>
                                <tr>
                                    <td><span class="badge text-white px-2 py-1" style="background:<?= $moedaColor ?>;font-size:10px;"><?= htmlspecialchars(strtoupper($row['moeda']??'N/A')) ?></span></td>
                                    <td class="text-end"><?= (int)($row['qtd']??0) ?></td>
                                    <td class="text-end fw-bold"><?= fmtNum($row['total']??0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="fw-bold border-top"><td>Total</td><td class="text-end"><?= $totalMoedaQtd ?></td><td class="text-end"><?= fmtNum($totalMoedaTotal) ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Por Pagamento -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:28px;height:28px;"><i class="fas fa-credit-card text-success" style="font-size:11px;"></i></div>
                        <div><div class="fw-bold small">Por Pagamento</div><div class="text-muted" style="font-size:10px;">Forma de pagamento</div></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size:12px;">
                            <thead class="table-light"><tr><th>Forma</th><th class="text-end">Qtd</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                            <?php if (empty($porPagamento)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">N/A</td></tr>
                            <?php else: ?>
                                <?php
                                $pagIcons = ['pix'=>'fas fa-qrcode text-success','cartao'=>'fas fa-credit-card text-primary','credit'=>'fas fa-credit-card text-primary','carne'=>'fas fa-file-invoice text-info','boleto'=>'fas fa-barcode text-dark'];
                                foreach ($porPagamento as $row):
                                    $forma = strtolower($row['forma']??'');
                                    $icon = 'fas fa-circle text-muted';
                                    foreach ($pagIcons as $k=>$v) { if (strpos($forma,$k)!==false) { $icon=$v; break; } }
                                ?>
                                <tr>
                                    <td><i class="<?= $icon ?> me-1" style="font-size:10px;"></i><?= htmlspecialchars(ucfirst(str_replace('_',' ',$row['forma']??'N/A'))) ?></td>
                                    <td class="text-end"><?= (int)($row['qtd']??0) ?></td>
                                    <td class="text-end fw-bold"><?= fmtNum($row['total']??0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="fw-bold border-top"><td>Total</td><td class="text-end"><?= $totalPagQtd ?></td><td class="text-end text-success"><?= fmtNum($totalPagTotal) ?></td></tr>
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
    </div>
</div>
