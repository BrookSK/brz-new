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
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="exportarDRE()"><i class="fas fa-download me-1"></i>Exportar</button>
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

    <!-- DRE - Demonstrativo de Resultado -->
    <?php
    $despesasResumo = $despesasResumo ?? ['total_brl' => 0, 'total_usd' => 0, 'total' => 0, 'pago_brl' => 0, 'pago_usd' => 0, 'pago' => 0, 'aberto' => 0, 'por_categoria' => []];
    $receitaBruta = $totalTotal; // Total de receitas convertido em BRL
    $totalDespesas = (float)($despesasResumo['total'] ?? 0);
    $lucroLiquido = $receitaBruta - $totalDespesas;
    $margemLucro = $receitaBruta > 0 ? round($lucroLiquido / $receitaBruta * 100, 1) : 0;
    $despUsd = (float)($despesasResumo['total_usd'] ?? 0);
    $despBrl = (float)($despesasResumo['total_brl'] ?? 0);
    $despPagoUsd = (float)($despesasResumo['pago_usd'] ?? 0);
    $despPagoBrl = (float)($despesasResumo['pago_brl'] ?? 0);
    ?>
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-top:3px solid #1e293b;">
                <div class="card-header bg-white border-0 pt-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-dark bg-opacity-10 d-flex align-items-center justify-content-center" style="width:32px;height:32px;"><i class="fas fa-file-invoice-dollar text-dark" style="font-size:12px;"></i></div>
                        <div><h6 class="fw-bold mb-0">DRE — Demonstrativo de Resultado</h6><span class="text-muted" style="font-size:10px;">Receitas vs Despesas no período</span></div>
                    </div>
                    <a href="/admin/despesas" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="fas fa-external-link-alt me-1"></i>Ver despesas</a>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Resumo DRE -->
                        <div class="col-lg-5">
                            <table class="table table-sm mb-0" style="font-size:13px;">
                                <tbody>
                                    <tr class="border-bottom"><td class="fw-bold text-success"><i class="fas fa-arrow-up me-1"></i>RECEITA BRUTA</td><td class="text-end fw-bold text-success fs-5"><?= fmtNum($receitaBruta) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Subtotal produtos</td><td class="text-end"><?= fmtNum($totalSubtotal) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Taxa de serviço</td><td class="text-end"><?= fmtNum($totalServicos) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Impostos cobrados</td><td class="text-end"><?= fmtNum($totalImpostos) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Frete</td><td class="text-end"><?= fmtNum($totalFrete) ?></td></tr>
                                    <tr class="border-top border-bottom"><td class="fw-bold text-danger"><i class="fas fa-arrow-down me-1"></i>DESPESAS TOTAIS</td><td class="text-end fw-bold text-danger fs-5"><?= fmtNum($totalDespesas) ?></td></tr>
                                    <?php if ($despUsd > 0): ?>
                                    <tr><td class="ps-3 text-muted">USD ($ <?= fmtNum($despUsd) ?> × <?= fmtNum($taxaUsdBrl) ?>)</td><td class="text-end">R$ <?= fmtNum($despUsd * $taxaUsdBrl) ?></td></tr>
                                    <?php endif; ?>
                                    <?php if ($despBrl > 0): ?>
                                    <tr><td class="ps-3 text-muted">BRL</td><td class="text-end">R$ <?= fmtNum($despBrl) ?></td></tr>
                                    <?php endif; ?>
                                    <tr><td class="ps-3 text-muted">Pagas no período</td><td class="text-end"><?= fmtNum($despesasResumo['pago']) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Em aberto</td><td class="text-end"><?= fmtNum($despesasResumo['aberto']) ?></td></tr>
                                    <tr class="border-top" style="background:#f8fafc;"><td class="fw-bold" style="font-size:14px;"><i class="fas fa-equals me-1"></i>RESULTADO LÍQUIDO</td><td class="text-end fw-bold fs-4 <?= $lucroLiquido >= 0 ? 'text-success' : 'text-danger' ?>"><?= fmtNum($lucroLiquido) ?></td></tr>
                                    <tr><td class="text-muted small">Margem</td><td class="text-end"><span class="badge <?= $margemLucro >= 0 ? 'bg-success' : 'bg-danger' ?>"><?= $margemLucro ?>%</span></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Despesas por categoria -->
                        <div class="col-lg-7">
                            <h6 class="fw-bold small mb-2"><i class="fas fa-chart-pie me-1 text-muted"></i>Despesas por Categoria</h6>
                            <?php if (empty($despesasResumo['por_categoria'])): ?>
                            <div class="text-center text-muted py-4 small"><i class="fas fa-inbox d-block mb-2" style="font-size:24px;opacity:.4;"></i>Nenhuma despesa registrada no período.<br><a href="/admin/despesas" class="mt-2 d-inline-block">Cadastrar despesas →</a></div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0" style="font-size:12px;">
                                    <thead class="table-light"><tr><th>Categoria</th><th>Grupo</th><th class="text-end">Qtd</th><th class="text-end">Total (R$)</th><th>%</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($despesasResumo['por_categoria'] as $dc):
                                        $pctCat = $totalDespesas > 0 ? round((float)$dc['total'] / $totalDespesas * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:<?= $dc['cor'] ?? '#6b7280' ?>;"></span><?= htmlspecialchars($dc['categoria'] ?? 'Sem categoria') ?></td>
                                        <td><span class="text-muted" style="font-size:10px;"><?= ucfirst(str_replace('_', ' ', $dc['grupo'] ?? '')) ?></span></td>
                                        <td class="text-end"><?= (int)($dc['qtd'] ?? 0) ?></td>
                                        <td class="text-end fw-bold"><?= fmtNum($dc['total'] ?? 0) ?></td>
                                        <td><div class="d-flex align-items-center gap-1"><div class="progress flex-grow-1" style="height:4px;width:60px;"><div class="progress-bar bg-danger" style="width:<?= $pctCat ?>%"></div></div><span class="text-muted" style="font-size:10px;"><?= $pctCat ?>%</span></div></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="fw-bold border-top"><td>Total</td><td></td><td class="text-end"><?= array_sum(array_column($despesasResumo['por_categoria'], 'qtd')) ?></td><td class="text-end"><?= fmtNum($totalDespesas) ?></td><td></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div><!-- /pane-geral -->
    <div class="tab-pane fade" id="pane-regional" role="tabpanel"><div id="regional-content"></div></div>
    </div>
</div>

<!-- Modal Regional -->
<div class="modal fade" id="modalRegional" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-globe me-2"></i>Relatório Regional</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-semibold small">Moeda de exibição</label><select id="regional-moeda" class="form-select"><option value="BRL">BRL (Real)</option><option value="USD">USD (Dólar)</option></select></div>
                <div><label class="form-label fw-semibold small">Idioma</label><select id="regional-idioma" class="form-select"><option value="pt">Português (PT-BR)</option><option value="en">English (EN)</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary btn-sm" onclick="gerarRelatorioRegional()"><i class="fas fa-check me-1"></i>Gerar</button></div>
        </div>
    </div>
</div>
<script>
(function(){
const DATA=<?= json_encode($regionalData, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK) ?>;
const i18n={pt:{title:'Relatório Regional',orders:'Total de Pedidos',total:'Total Geral',subtotal:'Subtotal Produtos',servicos:'Taxa de Serviço',impostos:'Impostos',imposto_local:'Imposto Local',frete:'Frete',byStatus:'Por Status',byPayment:'Por Forma de Pagamento',status:'Status',qty:'Qtd',form:'Forma',noData:'Nenhum dado',allConverted:'Todos os valores convertidos para',rate:'Taxa de conversão'},en:{title:'Regional Report',orders:'Total Orders',total:'Grand Total',subtotal:'Products Subtotal',servicos:'Service Fee',impostos:'Taxes',imposto_local:'Local Tax',frete:'Shipping',byStatus:'By Status',byPayment:'By Payment Method',status:'Status',qty:'Qty',form:'Method',noData:'No data',allConverted:'All values converted to',rate:'Conversion rate'}};
const enLabels={'pendente':'Pending','processando':'Processing','pago':'Paid','carne_pagando':'Installment Paying','carne_aguardando':'Installment Waiting','produto_consolidado':'Box Closed','etiqueta_gerada':'Label Generated','em_transporte':'In Transit','aguardando_liberacao_aduaneira':'Customs Clearance','enviado_ao_destinatario':'Sent to Recipient','entregue':'Delivered','cancelado':'Cancelled'};
function fmt(v,m){const n=parseFloat(v)||0;return m==='USD'?'$ '+n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}):'R$ '+n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
function cv(v,o,t,tx){const n=parseFloat(v)||0;if(o===t)return n;if(o==='USD'&&t==='BRL')return n*tx;if(o==='BRL'&&t==='USD')return n/tx;return n;}
window.abrirModalRegional=function(){new bootstrap.Modal(document.getElementById('modalRegional')).show();};
window.gerarRelatorioRegional=function(){
    const moeda=document.getElementById('regional-moeda').value,idioma=document.getElementById('regional-idioma').value,t=i18n[idioma]||i18n.pt,taxa=DATA.taxaUsdBrl,labels=idioma==='en'?enLabels:DATA.statusLabels;
    const uD=DATA.usd||{},bD=DATA.brl||{},keys=['total','subtotal','servicos','impostos','imposto_local','frete'],tots={};
    keys.forEach(k=>{tots[k]=cv(uD[k]||0,'USD',moeda,taxa)+cv(bD[k]||0,'BRL',moeda,taxa);});
    const sMap={};(DATA.porStatus||[]).forEach(r=>{const st=r.status||'N/A';if(!sMap[st])sMap[st]={status:st,qtd:0,subtotal:0,servicos:0,impostos:0,frete:0,total:0};const o=(r.moeda||'USD').toUpperCase();sMap[st].qtd+=parseInt(r.qtd)||0;sMap[st].subtotal+=cv(r.subtotal||0,o,moeda,taxa);sMap[st].servicos+=cv(r.servicos||0,o,moeda,taxa);sMap[st].impostos+=cv(r.impostos||0,o,moeda,taxa);sMap[st].frete+=cv(r.frete||0,o,moeda,taxa);sMap[st].total+=cv(r.total||0,o,moeda,taxa);});
    const sArr=Object.values(sMap).sort((a,b)=>b.total-a.total);
    const pMap={};(DATA.porPagamento||[]).forEach(r=>{const f=r.forma||'N/A';if(!pMap[f])pMap[f]={forma:f,qtd:0,total:0};pMap[f].qtd+=parseInt(r.qtd)||0;pMap[f].total+=parseFloat(r.total)||0;});
    const qtd=parseInt(DATA.totais.qtd_pedidos)||0;
    const rl=moeda==='BRL'?'1 USD = '+taxa.toFixed(2)+' BRL':'1 BRL = '+(1/taxa).toFixed(4)+' USD';
    let h='<div class="alert alert-info small mb-3"><i class="fas fa-info-circle me-1"></i>'+t.allConverted+' <strong>'+moeda+'</strong>. '+t.rate+': '+rl+'</div>';
    h+='<div class="row g-3 mb-4">';
    [{key:'total',label:t.total,color:'#3b82f6'},{key:'subtotal',label:t.subtotal,color:'#10b981'},{key:'servicos',label:t.servicos,color:'#06b6d4'},{key:'impostos',label:t.impostos,color:'#f59e0b'},{key:'imposto_local',label:t.imposto_local,color:'#ef4444'},{key:'frete',label:t.frete,color:'#64748b'}].forEach(c=>{const v=tots[c.key]||0;if(c.key==='imposto_local'&&v<=0)return;h+='<div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-top:3px solid '+c.color+';"><div class="card-body"><div class="text-muted small mb-1">'+c.label+'</div><div class="fs-4 fw-bold">'+fmt(v,moeda)+'</div></div></div></div>';});
    h+='</div>';
    h+='<div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#1e293b,#334155);"><div class="card-body d-flex align-items-center justify-content-between py-3"><div class="text-white fw-semibold">'+t.orders+'</div><span class="fs-2 fw-bold text-white">'+qtd.toLocaleString()+'</span></div></div>';
    h+='<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small mb-0">'+t.byStatus+'</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0" style="font-size:12px;"><thead class="table-light"><tr><th>'+t.status+'</th><th class="text-end">'+t.qty+'</th><th class="text-end">Subtotal</th><th class="text-end">Serviço</th><th class="text-end">Impostos</th><th class="text-end">Frete</th><th class="text-end">Total</th></tr></thead><tbody>';
    if(!sArr.length)h+='<tr><td colspan="7" class="text-center text-muted py-3">'+t.noData+'</td></tr>';
    else sArr.forEach(r=>{h+='<tr><td>'+(labels[r.status]||r.status.replace(/_/g,' '))+'</td><td class="text-end">'+r.qtd+'</td><td class="text-end">'+fmt(r.subtotal,moeda)+'</td><td class="text-end">'+fmt(r.servicos,moeda)+'</td><td class="text-end">'+fmt(r.impostos,moeda)+'</td><td class="text-end">'+fmt(r.frete,moeda)+'</td><td class="text-end fw-bold">'+fmt(r.total,moeda)+'</td></tr>';});
    h+='</tbody></table></div></div></div>';
    h+='<div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small mb-0">'+t.byPayment+'</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0" style="font-size:12px;"><thead class="table-light"><tr><th>'+t.form+'</th><th class="text-end">'+t.qty+'</th><th class="text-end">Total</th></tr></thead><tbody>';
    const pArr=Object.values(pMap).sort((a,b)=>b.total-a.total);
    if(!pArr.length)h+='<tr><td colspan="3" class="text-center text-muted py-3">'+t.noData+'</td></tr>';
    else pArr.forEach(r=>{h+='<tr><td>'+r.forma.replace(/_/g,' ')+'</td><td class="text-end">'+r.qtd+'</td><td class="text-end fw-bold">'+fmt(r.total,moeda)+'</td></tr>';});
    h+='</tbody></table></div></div></div>';
    document.getElementById('regional-content').innerHTML=h;
    bootstrap.Modal.getInstance(document.getElementById('modalRegional')).hide();
    const el=document.getElementById('tab-regional');el.setAttribute('data-bs-toggle','tab');el.setAttribute('data-bs-target','#pane-regional');new bootstrap.Tab(el).show();
};
})();
</script>

<script>
// Dados para exportação DRE
const DRE_DATA = <?= json_encode([
    'periodo' => $periodoLabel,
    'dateStart' => $dateStart,
    'dateEnd' => $dateEnd,
    'taxaUsdBrl' => $taxaUsdBrl,
    'totais' => $totais,
    'usd' => $usd,
    'brl' => $brl,
    'porStatus' => $statusConsolidado,
    'porMoeda' => $porMoeda,
    'porPagamento' => $porPagamento,
    'statusLabels' => $statusLabels,
    'totalQtd' => $totalQtd,
    'totalSubtotal' => $totalSubtotal,
    'totalServicos' => $totalServicos,
    'totalImpostos' => $totalImpostos,
    'totalFrete' => $totalFrete,
    'totalTotal' => $totalTotal,
    'despesas' => $despesasResumo,
    'lucroLiquido' => $lucroLiquido ?? 0,
    'margemLucro' => $margemLucro ?? 0,
], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;

function exportarDRE() {
    const d = DRE_DATA;
    const sep = ';';
    const nl = '\r\n';
    let csv = '';

    // BOM para Excel reconhecer UTF-8
    csv += '\uFEFF';

    // === CABEÇALHO ===
    csv += 'DEMONSTRATIVO DE RESULTADO - BRAZILIANA SHOP' + nl;
    csv += 'Período: ' + d.periodo + nl;
    csv += 'Taxa USD→BRL: ' + (d.taxaUsdBrl || 0).toFixed(2) + nl;
    csv += 'Gerado em: ' + new Date().toLocaleString('pt-BR') + nl;
    csv += nl;

    // === RESUMO GERAL ===
    csv += '=== RESUMO GERAL ===' + nl;
    csv += 'Descrição' + sep + 'USD' + sep + 'BRL' + sep + 'Total em BRL' + nl;

    const fields = [
        {key:'total', label:'RECEITA BRUTA TOTAL'},
        {key:'subtotal', label:'(-) Subtotal Produtos'},
        {key:'servicos', label:'(-) Taxa de Serviço'},
        {key:'impostos', label:'(-) Impostos'},
        {key:'imposto_local', label:'(-) Imposto Local'},
        {key:'frete', label:'(-) Frete'},
    ];

    fields.forEach(f => {
        const vUsd = parseFloat(d.usd[f.key] || 0);
        const vBrl = parseFloat(d.brl[f.key] || 0);
        const totalBrl = vBrl + (vUsd * d.taxaUsdBrl);
        csv += f.label + sep + fmtCSV(vUsd) + sep + fmtCSV(vBrl) + sep + fmtCSV(totalBrl) + nl;
    });
    csv += nl;

    // === PEDIDOS ===
    csv += '=== TOTAL DE PEDIDOS ===' + nl;
    csv += 'Quantidade' + sep + (d.totais.qtd_pedidos || 0) + nl;
    csv += nl;

    // === POR STATUS ===
    csv += '=== RECEITA POR STATUS ===' + nl;
    csv += 'Status' + sep + 'Qtd' + sep + 'Subtotal' + sep + 'Serviço' + sep + 'Impostos' + sep + 'Frete' + sep + 'Total (R$)' + nl;
    (d.porStatus || []).forEach(r => {
        const label = d.statusLabels[r.status] || r.status.replace(/_/g, ' ');
        csv += label + sep + r.qtd + sep + fmtCSV(r.subtotal) + sep + fmtCSV(r.servicos) + sep + fmtCSV(r.impostos) + sep + fmtCSV(r.frete) + sep + fmtCSV(r.total) + nl;
    });
    csv += 'TOTAL' + sep + d.totalQtd + sep + fmtCSV(d.totalSubtotal) + sep + fmtCSV(d.totalServicos) + sep + fmtCSV(d.totalImpostos) + sep + fmtCSV(d.totalFrete) + sep + fmtCSV(d.totalTotal) + nl;
    csv += nl;

    // === POR MOEDA ===
    csv += '=== DISTRIBUIÇÃO POR MOEDA ===' + nl;
    csv += 'Moeda' + sep + 'Qtd' + sep + 'Total' + nl;
    (d.porMoeda || []).forEach(r => {
        csv += (r.moeda || 'N/A').toUpperCase() + sep + (r.qtd || 0) + sep + fmtCSV(parseFloat(r.total || 0)) + nl;
    });
    csv += nl;

    // === POR FORMA DE PAGAMENTO ===
    csv += '=== RECEITA POR FORMA DE PAGAMENTO ===' + nl;
    csv += 'Forma' + sep + 'Qtd' + sep + 'Total' + nl;
    (d.porPagamento || []).forEach(r => {
        csv += (r.forma || 'N/A').replace(/_/g, ' ') + sep + (r.qtd || 0) + sep + fmtCSV(parseFloat(r.total || 0)) + nl;
    });
    csv += nl;

    // === DESPESAS ===
    csv += '=== DESPESAS DO PERÍODO ===' + nl;
    csv += 'Descrição' + sep + 'Valor (R$)' + nl;
    const desp = d.despesas || {};
    csv += 'Total Despesas' + sep + fmtCSV(desp.total || 0) + nl;
    csv += 'Despesas Pagas' + sep + fmtCSV(desp.pago || 0) + nl;
    csv += 'Despesas em Aberto' + sep + fmtCSV(desp.aberto || 0) + nl;
    csv += nl;
    if (desp.por_categoria && desp.por_categoria.length > 0) {
        csv += 'Categoria' + sep + 'Grupo' + sep + 'Qtd' + sep + 'Total (R$)' + nl;
        desp.por_categoria.forEach(c => {
            csv += (c.categoria || 'Sem categoria') + sep + (c.grupo || '').replace(/_/g,' ') + sep + (c.qtd || 0) + sep + fmtCSV(parseFloat(c.total || 0)) + nl;
        });
        csv += nl;
    }

    // === DRE - RESULTADO ===
    csv += '=== DRE - RESULTADO ===' + nl;
    csv += 'Descrição' + sep + 'Valor (R$)' + nl;
    const receitaBruta = d.totalTotal || 0;
    const totalDesp = desp.total || 0;
    const lucro = receitaBruta - totalDesp;
    const margem = receitaBruta > 0 ? (lucro / receitaBruta * 100).toFixed(1) : '0.0';
    csv += 'RECEITA BRUTA' + sep + fmtCSV(receitaBruta) + nl;
    csv += '(-) DESPESAS TOTAIS' + sep + fmtCSV(totalDesp) + nl;
    csv += '(=) RESULTADO LÍQUIDO' + sep + fmtCSV(lucro) + nl;
    csv += 'Margem (%)' + sep + margem.replace('.', ',') + '%' + nl;
    csv += nl;

    // === CONCILIAÇÃO ===
    csv += '=== CONCILIAÇÃO ===' + nl;
    csv += 'Descrição' + sep + 'Valor (R$)' + nl;
    const totalGeralBrl = parseFloat(d.brl.total || 0) + (parseFloat(d.usd.total || 0) * d.taxaUsdBrl);
    csv += 'Receita Total (convertida BRL)' + sep + fmtCSV(totalGeralBrl) + nl;
    csv += 'Total Subtotal Produtos' + sep + fmtCSV(d.totalSubtotal) + nl;
    csv += 'Total Taxa de Serviço' + sep + fmtCSV(d.totalServicos) + nl;
    csv += 'Total Impostos' + sep + fmtCSV(d.totalImpostos) + nl;
    csv += 'Total Frete' + sep + fmtCSV(d.totalFrete) + nl;
    csv += 'Total Despesas' + sep + fmtCSV(totalDesp) + nl;
    csv += 'Resultado Líquido' + sep + fmtCSV(lucro) + nl;
    csv += 'Quantidade de Pedidos' + sep + (d.totais.qtd_pedidos || 0) + nl;

    // Download
    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'DRE_Braziliana_' + d.dateStart + '_' + d.dateEnd + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function fmtCSV(v) {
    return (parseFloat(v) || 0).toFixed(2).replace('.', ',');
}
</script>
