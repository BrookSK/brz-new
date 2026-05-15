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
$totalQtd=0;$totalSubtotal=0;$totalServicos=0;$totalImpostos=0;$totalImpostoLocal=0;$totalFrete=0;$totalTotal=0;
foreach ($porStatus as $row) {
    $st=$row['status']??'N/A';
    if(!isset($statusConsolidado[$st]))$statusConsolidado[$st]=['status'=>$st,'qtd'=>0,'subtotal'=>0,'servicos'=>0,'impostos'=>0,'imposto_local'=>0,'frete'=>0,'total'=>0];
    $fator=(strtoupper($row['moeda']??'USD')==='BRL')?1.0:$taxaUsdBrl;
    $statusConsolidado[$st]['qtd']+=(int)($row['qtd']??0);
    $statusConsolidado[$st]['subtotal']+=(float)($row['subtotal']??0)*$fator;
    $statusConsolidado[$st]['servicos']+=(float)($row['servicos']??0)*$fator;
    $statusConsolidado[$st]['impostos']+=(float)($row['impostos']??0)*$fator;
    $statusConsolidado[$st]['imposto_local']+=(float)($row['imposto_local']??0)*$fator;
    $statusConsolidado[$st]['frete']+=(float)($row['frete']??0)*$fator;
    $statusConsolidado[$st]['total']+=(float)($row['total']??0)*$fator;
}
usort($statusConsolidado, function($a,$b){return $b['total']<=>$a['total'];});
foreach($statusConsolidado as $r){$totalQtd+=$r['qtd'];$totalSubtotal+=$r['subtotal'];$totalServicos+=$r['servicos'];$totalImpostos+=$r['impostos'];$totalImpostoLocal+=$r['imposto_local'];$totalFrete+=$r['frete'];$totalTotal+=$r['total'];}

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
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <div>
                <h1 class="page-title">Financeiro</h1>
                <p class="page-subtitle">Visão consolidada de pedidos, receitas e impostos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="border rounded-pill px-3 py-1 d-none d-lg-flex align-items-center gap-2 bg-white">
                <i class="fas fa-exchange-alt text-muted" style="font-size:11px;"></i>
                <span class="small">USD → BRL</span>
                <span class="fw-bold"><?= fmtNum($taxaUsdBrl) ?></span>
            </div>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalViewConfig"><i class="fas fa-globe me-1"></i><span class="d-none d-md-inline">Moeda</span></button>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="exportarDRE()"><i class="fas fa-download me-1"></i><span class="d-none d-md-inline">Exportar</span></button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-4">
        <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto" role="tablist" style="-webkit-overflow-scrolling:touch;">
            <li class="nav-item flex-shrink-0"><button class="nav-link active px-3 py-2" id="tab-geral" data-bs-toggle="tab" data-bs-target="#pane-geral" type="button"><i class="fas fa-chart-bar me-1"></i><span class="d-none d-sm-inline">Relatório </span>Geral</button></li>
            <li class="nav-item flex-shrink-0"><button class="nav-link px-3 py-2" id="tab-regional" type="button" onclick="abrirModalRegional()"><i class="fas fa-globe me-1"></i>Regional</button></li>
            <li class="nav-item flex-shrink-0"><button class="nav-link px-3 py-2" id="tab-dre" data-bs-toggle="tab" data-bs-target="#pane-dre" type="button"><i class="fas fa-file-invoice-dollar me-1"></i>DRE</button></li>
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
                    <div class="flex-shrink-0">
                        <label class="form-label small text-muted mb-1"><i class="far fa-calendar me-1"></i>DATA INÍCIO</label>
                        <input type="date" name="date_start" class="form-control form-control-sm" value="<?= htmlspecialchars($dateStart) ?>">
                    </div>
                    <div class="flex-shrink-0">
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
        ['key'=>'frete','label'=>'AWB & Transporte','icon'=>'fas fa-plane','color'=>'secondary'],
        ['key'=>'lastmile','label'=>'Last Mile Brasil','icon'=>'fas fa-truck','color'=>'danger'],
    ];
    $cardColors = ['primary'=>'#3b82f6','success'=>'#10b981','info'=>'#06b6d4','warning'=>'#f59e0b','danger'=>'#ef4444','secondary'=>'#64748b'];
    ?>
    <div class="row g-3 mb-4">
    <?php foreach ($campos as $c):
        $vUsd = (float)($usd[$c['key']] ?? 0);
        $vBrl = (float)($brl[$c['key']] ?? 0);
        // AWB & Transporte: calcular direto (peso * $4.80/kg)
        if ($c['key'] === 'frete') {
            if (!isset($_awbCalcDone)) {
                $_awbCalcDone = true;
                $_awbUsd = 0; $_lastMileBrl = 0;
                try {
                    $pdo = \Config\Database::getConnection();
                    $_it = null;
                    try { $pdo->query("SELECT 1 FROM pedido_itens LIMIT 1"); $_it = 'pedido_itens'; } catch (\Exception $e) { try { $pdo->query("SELECT 1 FROM pedido_items LIMIT 1"); $_it = 'pedido_items'; } catch (\Exception $e2) {} }
                    if ($_it) {
                        $_cP = []; try { $_s = $pdo->query("DESCRIBE produtos"); $_cP = $_s ? $_s->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                        $_cI = []; try { $_s = $pdo->query("DESCRIBE {$_it}"); $_cI = $_s ? $_s->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                        $_colsPed = []; try { $_s = $pdo->query("DESCRIBE pedidos"); $_colsPed = $_s ? $_s->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                        $_wt = in_array('weight', $_cP, true) ? 'weight' : (in_array('peso', $_cP, true) ? 'peso' : '');
                        $_qt = in_array('quantidade', $_cI, true) ? 'quantidade' : 'quantidade';
                        $_pi = in_array('produto_id', $_cI, true) ? 'produto_id' : 'product_id';
                        $_dl = in_array('deleted_at', $_colsPed, true) ? "AND p.deleted_at IS NULL" : "";
                        if ($_wt) {
                            $_sq = "SELECT COALESCE(SUM(prod.{$_wt} * i.{$_qt}), 0) FROM {$_it} i INNER JOIN pedidos p ON p.id = i.pedido_id INNER JOIN produtos prod ON prod.id = i.{$_pi} WHERE p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY) AND LOWER(COALESCE(p.status,'')) NOT IN ('apagado','deleted','lixeira','trash','cancelado','cancelled') {$_dl}";
                            $_st = $pdo->prepare($_sq); $_st->execute([$dateStart, $dateEnd]);
                            $_pesoAll = (float)($_st->fetchColumn() ?: 0);
                            $_awbUsd = $_pesoAll * 4.80;
                            $_cp2 = '';
                            foreach (['pais_entrega','pais','country','shipping_country','pais_destino'] as $_k) { if (in_array($_k, $_colsPed, true)) { $_cp2 = $_k; break; } }
                            if ($_cp2) {
                                $_sq2 = "SELECT COALESCE(SUM(prod.{$_wt} * i.{$_qt}), 0) FROM {$_it} i INNER JOIN pedidos p ON p.id = i.pedido_id INNER JOIN produtos prod ON prod.id = i.{$_pi} WHERE p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY) AND LOWER(COALESCE(p.status,'')) NOT IN ('apagado','deleted','lixeira','trash','cancelado','cancelled') {$_dl} AND UPPER(COALESCE(p.{$_cp2},'')) IN ('BR','BRASIL','BRAZIL')";
                                $_st2 = $pdo->prepare($_sq2); $_st2->execute([$dateStart, $dateEnd]);
                                $_lastMileBrl = (float)($_st2->fetchColumn() ?: 0) * 10.0;
                            }
                        }
                    }
                } catch (\Throwable $e) {}
            }
            $vUsd = $_awbUsd;
            $vBrl = 0;
        }
        // Last Mile Brasil
        if ($c['key'] === 'lastmile') {
            if (!isset($_awbCalcDone)) { $_lastMileBrl = 0; }
            $vUsd = 0;
            $vBrl = $_lastMileBrl ?? 0;
        }
        $convertidoBrl = ($c['key'] === 'frete') ? ($vUsd * $taxaUsdBrl) : (($c['key'] === 'lastmile') ? $vBrl : totalEmBrl($usd, $brl, $c['key'], $taxaUsdBrl));
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
                        <span class="fw-bold small text-uppercase" style="color:<?= $borderColor ?>;font-size:11px;" data-i18n="total_em_brl">Total em BRL</span>
                        <span class="fw-bold fs-5 fin-value" style="color:<?= $borderColor ?>;" data-value-brl="<?= $convertidoBrl ?>">R$ <?= fmtNum($convertidoBrl) ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- DRE - Demonstrativo de Resultado -->
    <?php
    $despesasResumo = $despesasResumo ?? ['total_brl' => 0, 'total_usd' => 0, 'total' => 0, 'pago_brl' => 0, 'pago_usd' => 0, 'pago' => 0, 'aberto' => 0, 'por_categoria' => []];
    $receitaBruta = $totalTotal;
    $totalDespesas = (float)($despesasResumo['total'] ?? 0);
    // Resultado = Receita - todos os custos
    $totalDeducoes = $totalSubtotal + $totalImpostos + $totalImpostoLocal + ($descontosTotal ?? 0) + (($_awbUsd ?? 0) * $taxaUsdBrl) + ($_lastMileBrl ?? 0) + ($comissoesTotal ?? 0) + $totalDespesas;
    $lucroLiquido = $receitaBruta - $totalDeducoes;
    $margemLucro = $receitaBruta > 0 ? round($lucroLiquido / $receitaBruta * 100, 1) : 0;
    $despUsd = (float)($despesasResumo['total_usd'] ?? 0);
    $despBrl = (float)($despesasResumo['total_brl'] ?? 0);
    $despPagoUsd = (float)($despesasResumo['pago_usd'] ?? 0);
    $despPagoBrl = (float)($despesasResumo['pago_brl'] ?? 0);
    ?>
    <div class="row g-4 mb-4">
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
                        <div class="col-lg-5">
                            <table class="table table-sm mb-0" style="font-size:13px;">
                                <tbody>
                                    <tr class="border-bottom"><td class="fw-bold text-success" data-i18n="receita_bruta"><i class="fas fa-arrow-up me-1"></i>RECEITA OPERACIONAL</td><td class="text-end fw-bold text-success fs-5 fin-value" data-value-brl="<?= $receitaBruta ?>"><?= fmtNum($receitaBruta) ?></td></tr>
                                    <tr class="border-top border-bottom"><td class="fw-bold text-danger" data-i18n="despesas_totais"><i class="fas fa-arrow-down me-1"></i>(-) DEDUÇÕES E CUSTOS</td><td class="text-end fw-bold text-danger fs-5 fin-value" data-value-brl="<?= $totalDeducoes ?>"><?= fmtNum($totalDeducoes) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Custo de Produtos</td><td class="text-end"><?= fmtNum($totalSubtotal) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Custo de Impostos Brasil</td><td class="text-end"><?= fmtNum($totalImpostos) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Custo de Imposto Local</td><td class="text-end"><?= fmtNum($totalImpostoLocal) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Descontos e Promoções</td><td class="text-end"><?= fmtNum($descontosTotal ?? 0) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">AWB & Transporte</td><td class="text-end"><?= fmtNum(($_awbUsd ?? 0) * $taxaUsdBrl) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Last Mile Brasil</td><td class="text-end"><?= fmtNum($_lastMileBrl ?? 0) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Comissões</td><td class="text-end"><?= fmtNum($comissoesTotal ?? 0) ?></td></tr>
                                    <tr><td class="ps-3 text-muted">Despesas Operacionais</td><td class="text-end fin-value" data-value-brl="<?= $totalDespesas ?>"><?= fmtNum($totalDespesas) ?></td></tr>
                                    <?php if ($despUsd > 0): ?>
                                    <tr><td class="ps-4 text-muted" style="font-size:11px;">USD ($ <?= fmtNum($despUsd) ?> × <?= fmtNum($taxaUsdBrl) ?>)</td><td class="text-end" style="font-size:11px;">R$ <?= fmtNum($despUsd * $taxaUsdBrl) ?></td></tr>
                                    <?php endif; ?>
                                    <?php if ($despBrl > 0): ?>
                                    <tr><td class="ps-4 text-muted" style="font-size:11px;">BRL</td><td class="text-end" style="font-size:11px;">R$ <?= fmtNum($despBrl) ?></td></tr>
                                    <?php endif; ?>
                                    <tr class="border-top" style="background:#f8fafc;"><td class="fw-bold" style="font-size:14px;" data-i18n="resultado"><i class="fas fa-equals me-1"></i>RESULTADO</td><td class="text-end fw-bold fs-4 <?= $lucroLiquido >= 0 ? 'text-success' : 'text-danger' ?> fin-value" data-value-brl="<?= $lucroLiquido ?>"><?= fmtNum($lucroLiquido) ?></td></tr>
                                    <tr><td class="text-muted small">Margem</td><td class="text-end"><span class="badge <?= $margemLucro >= 0 ? 'bg-success' : 'bg-danger' ?>"><?= $margemLucro ?>%</span></td></tr>
                                </tbody>
                            </table>
                        </div>
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

    <!-- Tabelas: Status, Moeda, Pagamento -->
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

    <!-- DRE COMPLETO -->
    <div class="tab-pane fade" id="pane-dre" role="tabpanel">
        <!-- Filtros de data próprios do DRE -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body py-3">
                <div class="d-flex align-items-end gap-3 flex-wrap">
                    <div><label class="form-label small text-muted mb-1"><i class="far fa-calendar me-1"></i>Data início</label><input type="date" id="dre-date-start" class="form-control form-control-sm" value="<?= htmlspecialchars($dateStart) ?>"></div>
                    <div><label class="form-label small text-muted mb-1"><i class="far fa-calendar me-1"></i>Data fim</label><input type="date" id="dre-date-end" class="form-control form-control-sm" value="<?= htmlspecialchars($dateEnd) ?>"></div>
                    <div><button class="btn btn-dark btn-sm px-4" onclick="dreApplyFilter()"><i class="fas fa-filter me-1"></i>Filtrar DRE</button></div>
                    <div class="ms-auto"><button class="btn btn-outline-secondary btn-sm" onclick="dreExport()"><i class="fas fa-download me-1"></i>Exportar CSV</button></div>
                </div>
            </div>
        </div>
        <div id="dre-completo-container">
            <div class="text-center py-5"><i class="fas fa-spinner fa-spin fs-3 text-muted"></i><div class="text-muted mt-2">Carregando DRE Completo...</div></div>
        </div>
    </div><!-- /pane-dre -->
    </div>
</div>

<!-- Modal Visualização Moeda/Idioma -->
<div class="modal fade" id="modalViewConfig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-globe me-2"></i>Visualização</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-semibold small">Moeda de exibição</label><select id="global-view-currency" class="form-select"><option value="BRL">BRL (Real)</option><option value="USD">USD (Dólar)</option></select></div>
                <div class="mb-0"><label class="form-label fw-semibold small">Idioma</label><select id="global-view-lang" class="form-select"><option value="pt">Português (PT-BR)</option><option value="en">English (EN)</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary btn-sm" onclick="applyGlobalView();bootstrap.Modal.getInstance(document.getElementById('modalViewConfig')).hide();"><i class="fas fa-check me-1"></i>Aplicar</button></div>
        </div>
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
    dreExport();
}

function fmtCSV(v) {
    return (parseFloat(v) || 0).toFixed(2).replace('.', ',');
}

// === GLOBAL VIEW: Moeda + Idioma ===
const TAXA_GLOBAL = <?= $taxaUsdBrl ?>;
const LABELS_PT = {financeiro:'Financeiro',visao:'Visão consolidada de pedidos, receitas e impostos',total_pedidos:'Total de pedidos',periodo:'Período',total_geral:'Total geral',subtotal:'Subtotal produtos',servicos:'Taxa de serviço',impostos:'Impostos',imposto_local:'Imposto local',frete:'Frete',por_status:'Por Status',por_moeda:'Por Moeda',por_pagamento:'Por Pagamento',receita_bruta:'RECEITA BRUTA',despesas_totais:'DESPESAS TOTAIS',resultado:'RESULTADO LÍQUIDO',margem:'Margem',pagas:'Pagas no período',aberto:'Em aberto',despesas_cat:'Despesas por Categoria',ver_despesas:'Ver despesas',sem_valores:'Sem valores no período',total_em_brl:'Total em BRL'};
const LABELS_EN = {financeiro:'Financial',visao:'Consolidated view of orders, revenue and taxes',total_pedidos:'Total orders',periodo:'Period',total_geral:'Grand total',subtotal:'Products subtotal',servicos:'Service fee',impostos:'Taxes',imposto_local:'Local tax',frete:'Shipping',por_status:'By Status',por_moeda:'By Currency',por_pagamento:'By Payment',receita_bruta:'GROSS REVENUE',despesas_totais:'TOTAL EXPENSES',resultado:'NET RESULT',margem:'Margin',pagas:'Paid in period',aberto:'Outstanding',despesas_cat:'Expenses by Category',ver_despesas:'View expenses',sem_valores:'No values in period',total_em_brl:'Total in BRL'};

function applyGlobalView() {
    const currency = document.getElementById('global-view-currency').value;
    const lang = document.getElementById('global-view-lang').value;
    const labels = lang === 'en' ? LABELS_EN : LABELS_PT;

    // Converter todos os valores monetários na página
    document.querySelectorAll('[data-value-brl]').forEach(el => {
        const brl = parseFloat(el.getAttribute('data-value-brl')) || 0;
        if (currency === 'USD') {
            el.textContent = '$ ' + (brl / TAXA_GLOBAL).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
        } else {
            el.textContent = 'R$ ' + brl.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        }
    });

    // Traduzir labels
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (labels[key]) el.textContent = labels[key];
    });
}

// Marcar valores para conversão após o DOM carregar
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar data-value-brl aos elementos de valor que já existem
    document.querySelectorAll('.fin-value').forEach(el => {
        if (!el.hasAttribute('data-value-brl')) {
            const text = el.textContent.replace(/[R$\s.]/g, '').replace(',', '.');
            const val = parseFloat(text) || 0;
            el.setAttribute('data-value-brl', val);
        }
    });
});

// === DRE COMPLETO ===
let dreLoaded = false;
document.getElementById('tab-dre').addEventListener('shown.bs.tab', function() {
    if (dreLoaded) return;
    dreLoaded = true;
    loadDreCompleto();
});

function loadDreCompleto() {
    const ds = document.getElementById('dre-date-start').value;
    const de = document.getElementById('dre-date-end').value;
    fetch('/admin/dre-completo/dados?date_start=' + ds + '&date_end=' + de)
        .then(r => r.json())
        .then(d => { if (d.success) renderDreCompleto(d); else document.getElementById('dre-completo-container').innerHTML = '<div class="alert alert-danger">Erro ao carregar dados</div>'; })
        .catch(e => { document.getElementById('dre-completo-container').innerHTML = '<div class="alert alert-danger">Erro: ' + e.message + '</div>'; });
}

function dreApplyFilter() {
    dreLoaded = false;
    dreCurrentPage = 1;
    dreCurrentStatus = '';
    loadDreCompletoWithParams();
}

function dreExport() {
    const ds = document.getElementById('dre-date-start').value;
    const de = document.getElementById('dre-date-end').value;
    window.location.href = '/admin/dre-completo/exportar?date_start=' + ds + '&date_end=' + de;
}

function fmtR(v) { const n = parseFloat(v)||0; return 'R$ ' + n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function renderDreCompleto(d) {
    const r = d.resumo;
    const taxa = d.taxaUsdBrl;
    const gwNames = {'stripe':'Stripe','cambioreal':'Câmbio Real','cambioreal_taxas':'CR Taxas','mercadopago':'Mercado Pago','asaas':'Asaas','appmax':'AppMax','pagdev':'PagDev','sem_gateway':'Sem gateway','n/a':'N/A'};
    const gwColors = {'stripe':'#635bff','cambioreal':'#00a86b','cambioreal_taxas':'#00a86b','mercadopago':'#009ee3','asaas':'#1a1a2e','appmax':'#ff6b35','pagdev':'#6366f1','sem_gateway':'#94a3b8','n/a':'#94a3b8'};
    let h = '';

    // Cards resumo (8 cards)
    h += '<div class="row g-3 mb-4">';
    h += card('Receita Operacional','fas fa-arrow-up','#10b981',fmtR(r.total_entradas));
    h += card('Custo Produtos','fas fa-box','#3b82f6',fmtR(r.custo_produtos||0));
    h += card('Impostos Brasil','fas fa-landmark','#f59e0b',fmtR(r.custo_impostos_br||0));
    h += card('Imposto Local','fas fa-flag','#ef4444',fmtR(r.custo_imposto_local||0));
    if ((r.total_descontos||0) > 0) h += card('Descontos/Promoções','fas fa-tag','#f97316',fmtR(r.total_descontos||0));
    h += card('AWB & Transporte','fas fa-plane','#64748b',fmtR(r.total_awb||0));
    if ((r.total_comissoes||0) > 0) h += card('Comissões','fas fa-handshake','#8b5cf6',fmtR(r.total_comissoes||0));
    h += card('Despesas','fas fa-arrow-down','#dc2626',fmtR(r.total_despesas));
    h += card('Resultado','fas fa-equals',r.resultado>=0?'#10b981':'#ef4444',fmtR(r.resultado));
    h += card('Margem','fas fa-percentage',r.margem>=0?'#3b82f6':'#ef4444',r.margem+'%');
    h += '</div>';

    // DRE Profissional
    h += '<div class="card border-0 shadow-sm mb-4" style="border-top:3px solid #1e293b;"><div class="card-header bg-white border-0 pt-3"><div class="d-flex align-items-center gap-2"><div class="rounded-circle bg-dark bg-opacity-10 d-flex align-items-center justify-content-center" style="width:28px;height:28px;"><i class="fas fa-file-invoice-dollar text-dark" style="font-size:11px;"></i></div><div><h6 class="fw-bold mb-0">DRE — Balanço Financeiro</h6><span class="text-muted" style="font-size:10px;">Período: '+d.periodo.inicio+' a '+d.periodo.fim+'</span></div></div></div><div class="card-body"><table class="table table-sm mb-0" style="font-size:13px;"><tbody>';
    h += dreRow('RECEITA OPERACIONAL',fmtR(r.total_entradas),'fw-bold text-success','fs-5');
    h += '<tr class="border-top"><td class="fw-bold text-danger"><i class="fas fa-minus-circle me-1"></i>(-) DEDUÇÕES E CUSTOS</td><td class="text-end fw-bold text-danger fs-5">'+fmtR((r.custo_produtos||0)+(r.custo_impostos_br||0)+(r.custo_imposto_local||0)+(r.total_descontos||0)+(r.total_awb||0)+(r.total_lastmile||0)+(r.total_comissoes||0)+(r.total_despesas||0))+'</td></tr>';
    h += dreRow('  Custo de Produtos',fmtR(r.custo_produtos||0),'ps-3 text-muted');
    h += dreRow('  Custo de Impostos Brasil',fmtR(r.custo_impostos_br||0),'ps-3 text-muted');
    h += dreRow('  Custo de Imposto Local',fmtR(r.custo_imposto_local||0),'ps-3 text-muted');
    if ((r.total_descontos||0) > 0) h += dreRow('  Descontos e Promoções',fmtR(r.total_descontos),'ps-3 text-muted');
    h += dreRow('  AWB & Transporte',fmtR(r.total_awb||0),'ps-3 text-muted');
    h += dreRow('  Last Mile Brasil',fmtR(r.total_lastmile||0),'ps-3 text-muted');
    if ((r.total_comissoes||0) > 0) h += dreRow('  Comissões',fmtR(r.total_comissoes),'ps-3 text-muted');
    h += dreRow('  Despesas Operacionais',fmtR(r.total_despesas||0),'ps-3 text-muted');
    h += '<tr class="border-top" style="background:#f8fafc;"><td class="fw-bold" style="font-size:14px;">(=) RESULTADO</td><td class="text-end fw-bold fs-4 '+(r.resultado>=0?'text-success':'text-danger')+'">'+fmtR(r.resultado)+'</td></tr>';
    h += '<tr><td class="text-muted small">Margem</td><td class="text-end"><span class="badge '+(r.margem>=0?'bg-success':'bg-danger')+'">'+r.margem+'%</span></td></tr>';
    h += '</tbody></table></div></div>';

    // Resumo Mensal
    h += '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small mb-0"><i class="fas fa-calendar-alt me-2 text-muted"></i>Resumo Mensal</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0" style="font-size:12px;"><thead class="table-light"><tr><th>Mês</th><th class="text-end">Receita Op.</th><th class="text-end">Produtos</th><th class="text-end">Imp. BR</th><th class="text-end">Imp. Local</th><th class="text-end">Taxa Serv.</th><th class="text-end">Despesas</th><th class="text-end">Resultado</th></tr></thead><tbody>';
    d.meses.forEach(m => { h += '<tr><td class="fw-semibold">'+m.mes+'</td><td class="text-end">'+fmtR(m.entradas_total)+'</td><td class="text-end">'+fmtR(m.custo_produtos_total||0)+'</td><td class="text-end">'+fmtR(m.custo_impostos_br_total||0)+'</td><td class="text-end">'+fmtR(m.custo_imposto_local_total||0)+'</td><td class="text-end">'+fmtR(m.taxa_servico_total||0)+'</td><td class="text-end text-danger">'+fmtR(m.despesas_total)+'</td><td class="text-end fw-bold '+(m.resultado>=0?'text-success':'text-danger')+'">'+fmtR(m.resultado)+'</td></tr>'; });
    h += '<tr class="fw-bold border-top table-light"><td>ACUMULADO</td><td class="text-end">'+fmtR(r.total_entradas)+'</td><td class="text-end">'+fmtR(r.custo_produtos||0)+'</td><td class="text-end">'+fmtR(r.custo_impostos_br||0)+'</td><td class="text-end">'+fmtR(r.custo_imposto_local||0)+'</td><td class="text-end">'+fmtR(r.taxa_servico||0)+'</td><td class="text-end">'+fmtR(r.total_despesas)+'</td><td class="text-end '+(r.resultado>=0?'text-success':'text-danger')+'">'+fmtR(r.resultado)+'</td></tr>';
    h += '</tbody></table></div></div></div>';

    // Gateways
    h += '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small mb-0"><i class="fas fa-credit-card me-2 text-muted"></i>Processamento por Gateway</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0" style="font-size:12px;"><thead class="table-light"><tr><th>Gateway</th><th class="text-end">Qtd</th><th class="text-end">Total (R$)</th><th class="text-end">✓</th><th class="text-end">⏳</th><th class="text-end">✗</th><th class="text-end">↩</th></tr></thead><tbody>';
    const gwMap={};d.gateways.forEach(g=>{const k=g.gateway;if(!gwMap[k])gwMap[k]={gateway:k,qtd:0,total:0,aprovados:0,pendentes:0,rejeitados:0,estornados:0};gwMap[k].total+=(g.moeda==='USD'?(parseFloat(g.total)||0)*taxa:(parseFloat(g.total)||0));gwMap[k].qtd+=parseInt(g.qtd)||0;gwMap[k].aprovados+=parseInt(g.aprovados)||0;gwMap[k].pendentes+=parseInt(g.pendentes)||0;gwMap[k].rejeitados+=parseInt(g.rejeitados)||0;gwMap[k].estornados+=parseInt(g.estornados)||0;});
    Object.values(gwMap).sort((a,b)=>b.total-a.total).forEach(g=>{h+='<tr><td><span class="d-inline-block rounded me-1" style="width:10px;height:10px;background:'+(gwColors[g.gateway]||'#6b7280')+';"></span>'+(gwNames[g.gateway]||g.gateway)+'</td><td class="text-end">'+g.qtd+'</td><td class="text-end fw-bold">'+fmtR(g.total)+'</td><td class="text-end"><span class="badge bg-success">'+g.aprovados+'</span></td><td class="text-end"><span class="badge bg-warning text-dark">'+g.pendentes+'</span></td><td class="text-end"><span class="badge bg-danger">'+g.rejeitados+'</span></td><td class="text-end"><span class="badge bg-secondary">'+g.estornados+'</span></td></tr>';});
    h += '</tbody></table></div></div></div>';

    // Entradas de Pedidos (com "Ver mais" incremental)
    const pg = d.entradas_paginacao||{page:1,total:0,total_pages:1};
    const stFilter = d.status_filter_dre||'';
    h += '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3 d-flex align-items-center justify-content-between"><h6 class="fw-bold small mb-0"><i class="fas fa-shopping-cart me-2 text-muted"></i>Entradas de Pedidos <span class="badge bg-secondary ms-1">'+pg.total+'</span></h6>';
    h += '<div class="d-flex align-items-center gap-2"><select id="dre-status-filter" class="form-select form-select-sm" style="width:auto;font-size:11px;" onchange="dreFilterStatus(this.value)"><option value="">Todos os status</option>';
    ['pago','carne_pagando','etiqueta_gerada','produto_consolidado','em_transporte','enviado_ao_destinatario','entregue'].forEach(s=>{h+='<option value="'+s+'"'+(stFilter===s?' selected':'')+'>'+s.replace(/_/g,' ')+'</option>';});
    h += '</select></div></div>';
    h += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0" style="font-size:11px;"><thead class="table-light"><tr><th>#</th><th>Data</th><th>Status</th><th>Gateway</th><th>Moeda</th><th class="text-end">Subtotal</th><th class="text-end">Taxas</th><th class="text-end">Impostos</th><th class="text-end fw-bold">Total</th></tr></thead><tbody id="dre-pedidos-tbody">';
    (d.entradas_detalhadas||[]).forEach(p=>{h+='<tr><td class="fw-semibold">#'+p.id+'</td><td>'+(p.data_pedido||'').substring(0,10)+'</td><td><span class="badge bg-light text-dark border" style="font-size:9px;">'+(p.status||'')+'</span></td><td>'+(p.gateway||'-')+'</td><td>'+(p.moeda||'BRL')+'</td><td class="text-end">'+fmtR(p.subtotal)+'</td><td class="text-end">'+fmtR(p.servicos)+'</td><td class="text-end">'+fmtR(p.impostos)+'</td><td class="text-end fw-bold">'+fmtR(p.total)+'</td></tr>';});
    if(!(d.entradas_detalhadas||[]).length)h+='<tr><td colspan="9" class="text-center text-muted py-3">Nenhum pedido no período</td></tr>';
    h += '</tbody></table></div>';
    // Botão "Ver mais" (append, não substitui)
    if (pg.total > pg.page * pg.per_page) {
        h += '<div class="text-center" style="margin-top:10px;padding-bottom:12px;" id="dre-ver-mais-wrap"><button class="btn btn-sm btn-outline-primary rounded-pill px-4" onclick="dreLoadMorePedidos()" id="dre-ver-mais-btn"><i class="fas fa-chevron-down me-1"></i>Ver mais pedidos ('+pg.total+' total)</button></div>';
    }
    h += '</div></div>';
    window._dreNextPage = (pg.page||1) + 1;
    window._dreTotalPages = pg.total_pages||1;
    window._drePgTotal = pg.total||0;

    // Operacional por Pessoa/Mês
    h += '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small mb-0"><i class="fas fa-users me-2 text-muted"></i>Operacional — Pagamentos por Pessoa/Mês</h6></div><div class="card-body p-0"><div class="table-responsive">';
    const opData=d.operacional||[];
    if(!opData.length){h+='<div class="text-center text-muted py-4 small">Cadastre despesas com favorecido para visualizar.</div>';}
    else{const pessoas={},mesesOp=new Set();opData.forEach(r=>{pessoas[r.pessoa]=pessoas[r.pessoa]||{};pessoas[r.pessoa][r.mes]=(pessoas[r.pessoa][r.mes]||0)+parseFloat(r.total);mesesOp.add(r.mes);});const mesesArr=[...mesesOp].sort();h+='<table class="table table-sm table-hover mb-0" style="font-size:11px;"><thead class="table-light"><tr><th>Pessoa</th>';mesesArr.forEach(m=>{h+='<th class="text-end">'+m+'</th>';});h+='<th class="text-end fw-bold">Total</th></tr></thead><tbody>';const pArr=Object.entries(pessoas).map(([n,ms])=>({nome:n,meses:ms,total:Object.values(ms).reduce((s,v)=>s+v,0)})).sort((a,b)=>b.total-a.total);pArr.forEach(p=>{h+='<tr><td class="fw-semibold">'+p.nome+'</td>';mesesArr.forEach(m=>{h+='<td class="text-end">'+(p.meses[m]?fmtR(p.meses[m]):'-')+'</td>';});h+='<td class="text-end fw-bold">'+fmtR(p.total)+'</td></tr>';});h+='</tbody></table>';}
    h += '</div></div></div>';

    // Despesas por Categoria + Favorecido
    h += '<div class="row g-4 mb-4"><div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small mb-0"><i class="fas fa-tags me-2 text-muted"></i>Despesas por Categoria</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0" style="font-size:12px;"><thead class="table-light"><tr><th>Categoria</th><th>Grupo</th><th class="text-end">Total</th><th class="text-end">%</th></tr></thead><tbody>';
    const catMap={};(d.despesas_categoria||[]).forEach(c=>{const k=c.categoria||'Sem categoria';if(!catMap[k])catMap[k]={categoria:k,grupo:c.grupo||'',cor:c.cor||'#6b7280',total:0};catMap[k].total+=(c.moeda==='USD'?(parseFloat(c.total)||0)*taxa:(parseFloat(c.total)||0));});const catArr=Object.values(catMap).sort((a,b)=>b.total-a.total);const catTotal=catArr.reduce((s,c)=>s+c.total,0);
    catArr.forEach(c=>{const pct=catTotal>0?(c.total/catTotal*100).toFixed(1):'0.0';h+='<tr><td><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:'+c.cor+';"></span>'+c.categoria+'</td><td class="text-muted small">'+c.grupo.replace(/_/g,' ')+'</td><td class="text-end fw-bold">'+fmtR(c.total)+'</td><td class="text-end small text-muted">'+pct+'%</td></tr>';});
    h += '</tbody></table></div></div></div></div><div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small mb-0"><i class="fas fa-user me-2 text-muted"></i>Maiores Favorecidos</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0" style="font-size:12px;"><thead class="table-light"><tr><th>Favorecido</th><th class="text-end">Qtd</th><th class="text-end">Total</th></tr></thead><tbody>';
    const favMap={};(d.despesas_favorecido||[]).forEach(f=>{const k=f.favorecido;if(!favMap[k])favMap[k]={favorecido:k,total:0,qtd:0};favMap[k].total+=(f.moeda==='USD'?(parseFloat(f.total)||0)*taxa:(parseFloat(f.total)||0));favMap[k].qtd+=parseInt(f.qtd)||0;});Object.values(favMap).sort((a,b)=>b.total-a.total).slice(0,15).forEach(f=>{h+='<tr><td>'+f.favorecido+'</td><td class="text-end">'+f.qtd+'</td><td class="text-end fw-bold">'+fmtR(f.total)+'</td></tr>';});
    h += '</tbody></table></div></div></div></div>';

    // Conciliação
    h += '<div class="card border-0 shadow-sm" style="border-top:3px solid #1e293b;"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small mb-0"><i class="fas fa-balance-scale me-2"></i>Conciliação Financeira</h6></div><div class="card-body"><table class="table table-sm mb-0" style="font-size:13px;"><tbody>';
    h += '<tr><td>Receita Operacional (total pedidos)</td><td class="text-end fw-bold text-success">'+fmtR(d.conciliacao.total_creditos)+'</td></tr>';
    h += '<tr><td class="ps-3 text-muted">Custo de Produtos</td><td class="text-end">'+fmtR(r.custo_produtos||0)+'</td></tr>';
    h += '<tr><td class="ps-3 text-muted">Custo de Impostos Brasil</td><td class="text-end">'+fmtR(r.custo_impostos_br||0)+'</td></tr>';
    h += '<tr><td class="ps-3 text-muted">Custo de Imposto Local</td><td class="text-end">'+fmtR(r.custo_imposto_local||0)+'</td></tr>';
    if ((r.total_descontos||0) > 0) h += '<tr><td class="ps-3 text-muted">Descontos e Promoções</td><td class="text-end">'+fmtR(r.total_descontos)+'</td></tr>';
    h += '<tr><td class="ps-3 text-muted">AWB & Transporte</td><td class="text-end">'+fmtR(r.total_awb||0)+'</td></tr>';
    h += '<tr><td class="ps-3 text-muted">Last Mile Brasil</td><td class="text-end">'+fmtR(r.total_lastmile||0)+'</td></tr>';
    if ((r.total_comissoes||0) > 0) h += '<tr><td class="ps-3 text-muted">Comissões</td><td class="text-end">'+fmtR(r.total_comissoes)+'</td></tr>';
    h += '<tr><td class="ps-3 text-muted">Despesas Operacionais</td><td class="text-end">'+fmtR(r.total_despesas||0)+'</td></tr>';
    h += '<tr><td>(-) Total Deduções</td><td class="text-end fw-bold text-danger">'+fmtR(d.conciliacao.total_debitos)+'</td></tr>';
    h += '<tr class="border-top" style="background:#f8fafc;"><td class="fw-bold">Resultado</td><td class="text-end fw-bold fs-5 '+(d.conciliacao.saldo_final>=0?'text-success':'text-danger')+'">'+fmtR(d.conciliacao.saldo_final)+'</td></tr>';
    h += '<tr><td class="text-muted small">Quantidade de lançamentos</td><td class="text-end">'+d.conciliacao.qtd_lancamentos+'</td></tr>';
    h += '</tbody></table>';
    h += '<hr class="my-3">';
    h += '<div class="d-flex justify-content-between align-items-center mb-2"><h6 class="fw-bold small mb-0"><i class="fas fa-plug me-2"></i>Conciliação com Gateways (últimos 30 dias)</h6><div><button class="btn btn-sm btn-outline-dark me-1" onclick="carregarConciliacaoGateways(this,false)"><i class="fas fa-eye me-1"></i>Ver</button><button class="btn btn-sm btn-outline-primary" onclick="carregarConciliacaoGateways(this,true)"><i class="fas fa-sync me-1"></i>Forçar Atualização</button></div></div>';
    h += '<div id="conciliacao-gateways-container"><div class="text-muted small">Clique em "Ver" para carregar dados do cache ou "Forçar Atualização" para consultar as APIs em tempo real.</div></div>';
    h += '</div></div>';

    document.getElementById('dre-completo-container').innerHTML = h;
}

function card(label,icon,color,value) {
    return '<div class="col-lg-3 col-md-6"><div class="card border-0 shadow-sm" style="border-top:3px solid '+color+';"><div class="card-body py-3"><div class="d-flex align-items-center gap-2 mb-1"><i class="'+icon+'" style="color:'+color+';font-size:12px;"></i><span class="text-muted small">'+label+'</span></div><div class="fs-5 fw-bold">'+value+'</div></div></div></div>';
}
function dreRow(label,value,cls,valCls) {
    return '<tr><td class="'+(cls||'')+'">'+(label.startsWith('  ')?label:''+label)+'</td><td class="text-end '+(valCls||'')+'">'+value+'</td></tr>';
}

let dreCurrentPage = 1;
let dreCurrentStatus = '';

function dreChangePage(page) {
    dreCurrentPage = page;
    loadDreCompletoWithParams();
}
function dreFilterStatus(status) {
    dreCurrentStatus = status;
    dreCurrentPage = 1;
    loadDreCompletoWithParams();
}
function dreLoadMorePedidos() {
    const ds = document.getElementById('dre-date-start').value;
    const de = document.getElementById('dre-date-end').value;
    const btn = document.getElementById('dre-ver-mais-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Carregando...'; }
    let url = '/admin/dre-completo/dados?date_start=' + ds + '&date_end=' + de + '&page=' + window._dreNextPage;
    if (dreCurrentStatus) url += '&status_dre=' + encodeURIComponent(dreCurrentStatus);
    fetch(url).then(r=>r.json()).then(d=>{
        if (!d.success) return;
        const tbody = document.getElementById('dre-pedidos-tbody');
        if (tbody && d.entradas_detalhadas) {
            let rows = '';
            d.entradas_detalhadas.forEach(p=>{rows+='<tr><td class="fw-semibold">#'+p.id+'</td><td>'+(p.data_pedido||'').substring(0,10)+'</td><td><span class="badge bg-light text-dark border" style="font-size:9px;">'+(p.status||'')+'</span></td><td>'+(p.gateway||'-')+'</td><td>'+(p.moeda||'BRL')+'</td><td class="text-end">'+fmtR(p.subtotal)+'</td><td class="text-end">'+fmtR(p.servicos)+'</td><td class="text-end">'+fmtR(p.impostos)+'</td><td class="text-end fw-bold">'+fmtR(p.total)+'</td></tr>';});
            tbody.insertAdjacentHTML('beforeend', rows);
        }
        const pg2 = d.entradas_paginacao||{};
        window._dreNextPage = (pg2.page||1) + 1;
        // Esconder ou atualizar botão
        const wrap = document.getElementById('dre-ver-mais-wrap');
        if (wrap) {
            if (window._dreNextPage > (pg2.total_pages||1)) {
                wrap.innerHTML = '<span class="text-muted small">Todos os pedidos carregados</span>';
            } else {
                wrap.innerHTML = '<button class="btn btn-sm btn-outline-primary rounded-pill px-4" onclick="dreLoadMorePedidos()" id="dre-ver-mais-btn"><i class="fas fa-chevron-down me-1"></i>Ver mais pedidos ('+(pg2.total||0)+' total)</button>';
            }
        }
    }).catch(()=>{ if(btn){btn.disabled=false;btn.innerHTML='Erro, tente novamente';} });
}
function loadDreCompletoWithParams() {
    const ds = document.getElementById('dre-date-start').value;
    const de = document.getElementById('dre-date-end').value;
    let url = '/admin/dre-completo/dados?date_start=' + ds + '&date_end=' + de + '&page=' + dreCurrentPage;
    if (dreCurrentStatus) url += '&status_dre=' + encodeURIComponent(dreCurrentStatus);
    document.getElementById('dre-completo-container').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-muted"></i></div>';
    fetch(url).then(r=>r.json()).then(d=>{if(d.success)renderDreCompleto(d);}).catch(()=>{});
}

function carregarConciliacaoGateways(btn, force) {
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Carregando...';
    var container = document.getElementById('conciliacao-gateways-container');
    container.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin text-muted"></i><div class="text-muted small mt-1">'+(force?'Consultando APIs dos gateways...':'Carregando dados do cache...')+'</div></div>';

    fetch('/admin/dre-completo/conciliacao' + (force ? '?force=1' : ''))
        .then(function(r){return r.json()})
        .then(function(d){
            btn.disabled = false; btn.innerHTML = force ? '<i class="fas fa-sync me-1"></i>Forçar Atualização' : '<i class="fas fa-eye me-1"></i>Ver';
            var h = '';

            // Info do cache
            if (d._from_cache) {
                h += '<div class="alert alert-light small py-1 px-2 mb-3 border"><i class="fas fa-database me-1"></i>Dados do cache (atualizado em: <strong>'+(d._cache_age||'?')+'</strong>). Use "Forçar Atualização" para consultar ao vivo.</div>';
            } else {
                h += '<div class="alert alert-success small py-1 px-2 mb-3"><i class="fas fa-check-circle me-1"></i>Dados consultados em tempo real agora.</div>';
            }

            // Stripe
            var s = d.stripe || {};
            h += '<div class="border rounded p-3 mb-3">';
            h += '<div class="fw-bold small mb-2"><i class="fab fa-stripe me-1" style="color:#635bff;"></i>Stripe</div>';
            if (s.erro) { h += '<div class="text-danger small">'+s.erro+'</div>'; }
            else {
                if (s.saldo && s.saldo.length) {
                    h += '<div class="d-flex gap-3 mb-2">';
                    s.saldo.forEach(function(b){ h += '<div class="border rounded px-3 py-2 text-center"><div class="text-muted" style="font-size:10px;">'+b.moeda+'</div><div class="fw-bold text-success">'+b.disponivel.toLocaleString("pt-BR",{minimumFractionDigits:2})+'</div><div class="text-muted" style="font-size:10px;">Pendente: '+b.pendente.toLocaleString("pt-BR",{minimumFractionDigits:2})+'</div></div>'; });
                    h += '</div>';
                }
                h += '<div class="small text-muted">'+((s.transacoes||[]).length)+' transações nos últimos 30 dias</div>';
                if ((s.divergencias||[]).length > 0) {
                    h += '<div class="alert alert-warning small mt-2 mb-0 py-1 px-2"><i class="fas fa-exclamation-triangle me-1"></i><strong>'+(s.divergencias.length)+' divergência(s)</strong></div>';
                    h += '<div class="table-responsive mt-1"><table class="table table-sm mb-0" style="font-size:11px;"><thead><tr><th>Tipo</th><th>ID</th><th>Pedido</th><th>Msg</th></tr></thead><tbody>';
                    s.divergencias.forEach(function(dv){ h += '<tr><td><span class="badge bg-warning text-dark" style="font-size:9px;">'+dv.tipo+'</span></td><td class="text-truncate" style="max-width:100px;">'+dv.id+'</td><td>'+(dv.pedido_id||'-')+'</td><td class="small">'+dv.msg+'</td></tr>'; });
                    h += '</tbody></table></div>';
                } else { h += '<div class="text-success small mt-1"><i class="fas fa-check-circle me-1"></i>Sem divergências</div>'; }
            }
            h += '</div>';

            // Câmbio Real Produtos
            var cr = d.cambioreal || {};
            h += '<div class="border rounded p-3 mb-3">';
            h += '<div class="fw-bold small mb-2"><i class="fas fa-dollar-sign me-1" style="color:#0ea5e9;"></i>Câmbio Real (Produtos)</div>';
            if (cr.erro) { h += '<div class="text-danger small">'+cr.erro+'</div>'; }
            else {
                h += '<div class="d-flex gap-3 mb-2"><div class="border rounded px-3 py-2 text-center"><div class="text-muted" style="font-size:10px;">Recebido (USD)</div><div class="fw-bold text-success">'+(cr.total_recebido_usd||0).toLocaleString("pt-BR",{minimumFractionDigits:2})+'</div></div><div class="border rounded px-3 py-2 text-center"><div class="text-muted" style="font-size:10px;">Registros</div><div class="fw-bold">'+(cr.total_registros||0)+'</div></div><div class="border rounded px-3 py-2 text-center"><div class="text-muted" style="font-size:10px;">Consultados</div><div class="fw-bold">'+(cr.total_consultados||0)+'</div></div></div>';
                if ((cr.divergencias||[]).length > 0) {
                    h += '<div class="alert alert-warning small mt-2 mb-0 py-1 px-2"><i class="fas fa-exclamation-triangle me-1"></i><strong>'+(cr.divergencias.length)+' divergência(s)</strong></div>';
                    h += '<div class="table-responsive mt-1"><table class="table table-sm mb-0" style="font-size:11px;"><thead><tr><th>Tipo</th><th>Token</th><th>Pedido</th><th>Status GW</th><th>Status Local</th></tr></thead><tbody>';
                    cr.divergencias.forEach(function(dv){ h += '<tr><td><span class="badge bg-danger" style="font-size:9px;">'+dv.tipo+'</span></td><td class="text-truncate" style="max-width:80px;">'+(dv.token||'')+'</td><td>'+(dv.pedido_id||'-')+'</td><td>'+(dv.status_gateway||'-')+'</td><td>'+(dv.status_local||'-')+'</td></tr>'; });
                    h += '</tbody></table></div>';
                } else { h += '<div class="text-success small mt-1"><i class="fas fa-check-circle me-1"></i>Sem divergências</div>'; }
            }
            h += '</div>';

            // Câmbio Real Taxas
            var crt = d.cambioreal_taxas || {};
            h += '<div class="border rounded p-3">';
            h += '<div class="fw-bold small mb-2"><i class="fas fa-receipt me-1" style="color:#f59e0b;"></i>Câmbio Real (Taxas)</div>';
            if (crt.erro) { h += '<div class="text-danger small">'+crt.erro+'</div>'; }
            else {
                h += '<div class="d-flex gap-3 mb-2"><div class="border rounded px-3 py-2 text-center"><div class="text-muted" style="font-size:10px;">Recebido (USD)</div><div class="fw-bold text-success">'+(crt.total_recebido_usd||0).toLocaleString("pt-BR",{minimumFractionDigits:2})+'</div></div><div class="border rounded px-3 py-2 text-center"><div class="text-muted" style="font-size:10px;">Registros</div><div class="fw-bold">'+(crt.total_registros||0)+'</div></div><div class="border rounded px-3 py-2 text-center"><div class="text-muted" style="font-size:10px;">Consultados</div><div class="fw-bold">'+(crt.total_consultados||0)+'</div></div></div>';
                if ((crt.divergencias||[]).length > 0) {
                    h += '<div class="alert alert-warning small mt-2 mb-0 py-1 px-2"><i class="fas fa-exclamation-triangle me-1"></i><strong>'+(crt.divergencias.length)+' divergência(s)</strong></div>';
                    h += '<div class="table-responsive mt-1"><table class="table table-sm mb-0" style="font-size:11px;"><thead><tr><th>Tipo</th><th>Token</th><th>Pedido</th><th>Status GW</th><th>Status Local</th></tr></thead><tbody>';
                    crt.divergencias.forEach(function(dv){ h += '<tr><td><span class="badge bg-danger" style="font-size:9px;">'+dv.tipo+'</span></td><td class="text-truncate" style="max-width:80px;">'+(dv.token||'')+'</td><td>'+(dv.pedido_id||'-')+'</td><td>'+(dv.status_gateway||'-')+'</td><td>'+(dv.status_local||'-')+'</td></tr>'; });
                    h += '</tbody></table></div>';
                } else { h += '<div class="text-success small mt-1"><i class="fas fa-check-circle me-1"></i>Sem divergências</div>'; }
            }
            h += '</div>';

            container.innerHTML = h;
        })
        .catch(function(e){
            btn.disabled = false; btn.innerHTML = force ? '<i class="fas fa-sync me-1"></i>Forçar Atualização' : '<i class="fas fa-eye me-1"></i>Ver';
            container.innerHTML = '<div class="alert alert-danger small">Erro: '+e.message+'</div>';
        });
}
</script>
