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
    $vUsd = (float)($usdRow[$campo] ?? 0);
    $vBrl = (float)($brlRow[$campo] ?? 0);
    return $vBrl + ($vUsd * $taxa);
}

// Dados para o Relatório Regional (JSON para JS)
$regionalData = [
    'taxaUsdBrl' => $taxaUsdBrl,
    'usd' => $usd,
    'brl' => $brl,
    'porStatus' => $porStatus,
    'porMoeda' => $porMoeda,
    'porPagamento' => $porPagamento,
    'totais' => $totais,
    'statusLabels' => $statusList,
];
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Financeiro</h4>
        <span class="text-muted small">Taxa USD→BRL: <strong><?= fmtNum($taxaUsdBrl) ?></strong></span>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="tab-geral" data-bs-toggle="tab" data-bs-target="#pane-geral" type="button" role="tab">Relatório Geral</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="tab-regional" type="button" role="tab" onclick="abrirModalRegional()">Relatório Regional</button>
        </li>
    </ul>

    <!-- Tab Geral -->
    <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-geral" role="tabpanel">

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/relatorio-geral" class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold">Data Início</label>
                    <input type="date" name="date_start" class="form-control" value="<?= htmlspecialchars($dateStart) ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold">Data Fim</label>
                    <input type="date" name="date_end" class="form-control" value="<?= htmlspecialchars($dateEnd) ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold">Status</label>
                    <div class="border rounded p-2" style="max-height:150px;overflow-y:auto;background:#fff;">
                        <?php foreach ($statusList as $key => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status[]" value="<?= htmlspecialchars($key) ?>" id="st_<?= htmlspecialchars($key) ?>" <?= in_array($key, $statusFilter) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="st_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold">Moeda</label>
                    <select name="moeda" class="form-select">
                        <option value="">Todas</option>
                        <option value="USD" <?= $moedaFilter === 'USD' ? 'selected' : '' ?>>USD</option>
                        <option value="BRL" <?= $moedaFilter === 'BRL' ? 'selected' : '' ?>>BRL</option>
                    </select>
                </div>
                <div class="col-md-1 col-sm-12">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pedidos -->
    <div class="row g-3 mb-2">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-receipt me-2 text-muted"></i><span class="fw-semibold">Total de Pedidos</span></div>
                    <span class="fs-4 fw-bold"><?= number_format((int)($totais['qtd_pedidos'] ?? 0), 0, '', '.') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards financeiros -->
    <?php
    $campos = [
        ['key' => 'total', 'label' => 'Total Geral', 'icon' => 'fas fa-dollar-sign', 'color' => 'primary', 'totaisKey' => 'total_geral'],
        ['key' => 'subtotal', 'label' => 'Subtotal Produtos', 'icon' => 'fas fa-box', 'color' => 'dark', 'totaisKey' => 'total_subtotal'],
        ['key' => 'servicos', 'label' => 'Taxa de Serviço', 'icon' => 'fas fa-concierge-bell', 'color' => 'info', 'totaisKey' => 'total_servicos'],
        ['key' => 'impostos', 'label' => 'Impostos', 'icon' => 'fas fa-landmark', 'color' => 'warning', 'totaisKey' => 'total_impostos'],
        ['key' => 'imposto_local', 'label' => 'Imposto Local', 'icon' => 'fas fa-flag', 'color' => 'danger', 'totaisKey' => 'total_imposto_local'],
        ['key' => 'frete', 'label' => 'Frete', 'icon' => 'fas fa-truck', 'color' => 'success', 'totaisKey' => 'total_frete'],
    ];
    ?>
    <div class="row g-3 mb-4">
        <?php foreach ($campos as $c):
            $totalGeral = (float)($totais[$c['totaisKey']] ?? 0);
            if ($c['key'] === 'imposto_local' && $totalGeral <= 0) continue;
            $vUsd = (float)($usd[$c['key']] ?? 0);
            $vBrl = (float)($brl[$c['key']] ?? 0);
            $convertidoBrl = totalEmBrl($usd, $brl, $c['key'], $taxaUsdBrl);
            $temDuasMoedas = ($vUsd > 0 && $vBrl > 0);
        ?>
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--bs-<?= $c['color'] ?>)!important">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small"><i class="<?= $c['icon'] ?> me-1"></i><?= $c['label'] ?></span>
                    </div>
                    <?php if ($vUsd > 0): ?>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <span class="text-muted small">USD</span>
                        <span class="fs-5 fw-bold">$ <?= fmtNum($vUsd) ?></span>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <span class="text-muted small" style="font-size:.75rem">≈ BRL</span>
                        <span class="text-muted small">R$ <?= fmtNum($vUsd * $taxaUsdBrl) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($vBrl > 0): ?>
                    <div class="d-flex align-items-baseline justify-content-between <?= $vUsd > 0 ? 'mt-1 pt-1 border-top' : '' ?>">
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
                    <?php if (!$vUsd && !$vBrl && $totalGeral > 0): ?>
                    <div class="fs-5 fw-bold"><?= fmtNum($totalGeral) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Tabela por Status -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-list-alt me-2"></i>Por Status <small class="text-muted fw-normal">(valores em R$)</small></h6>
                </div>
                <div class="card-body p-0">
                    <?php
                    $statusLabels = $statusList;
                    $statusConsolidado = [];
                    foreach ($porStatus as $row) {
                        $st = $row['status'] ?? 'N/A';
                        if (!isset($statusConsolidado[$st])) {
                            $statusConsolidado[$st] = ['status' => $st, 'qtd' => 0, 'subtotal' => 0, 'servicos' => 0, 'impostos' => 0, 'imposto_local' => 0, 'frete' => 0, 'total' => 0];
                        }
                        $moedaRow = strtoupper($row['moeda'] ?? 'USD');
                        $fator = ($moedaRow === 'BRL') ? 1.0 : $taxaUsdBrl;
                        $statusConsolidado[$st]['qtd'] += (int)($row['qtd'] ?? 0);
                        $statusConsolidado[$st]['subtotal'] += (float)($row['subtotal'] ?? 0) * $fator;
                        $statusConsolidado[$st]['servicos'] += (float)($row['servicos'] ?? 0) * $fator;
                        $statusConsolidado[$st]['impostos'] += (float)($row['impostos'] ?? 0) * $fator;
                        $statusConsolidado[$st]['imposto_local'] += (float)($row['imposto_local'] ?? 0) * $fator;
                        $statusConsolidado[$st]['frete'] += (float)($row['frete'] ?? 0) * $fator;
                        $statusConsolidado[$st]['total'] += (float)($row['total'] ?? 0) * $fator;
                    }
                    usort($statusConsolidado, function($a, $b) { return $b['total'] <=> $a['total']; });
                    ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th><th class="text-end">Qtd</th><th class="text-end">Subtotal</th>
                                    <th class="text-end">Serviço</th><th class="text-end">Impostos</th>
                                    <th class="text-end">Frete</th><th class="text-end">Total (R$)</th>
                                </tr>
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
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela por Moeda -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-coins me-2"></i>Por Moeda</h6>
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
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['moeda'] ?? 'N/A') ?></span></td>
                                    <td class="text-end"><?= (int)($row['qtd'] ?? 0) ?></td>
                                    <td class="text-end fw-bold"><?= fmtNum($row['total'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela por Forma de Pagamento -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-credit-card me-2"></i>Por Pagamento</h6>
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
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['forma'] ?? 'N/A'))) ?></td>
                                    <td class="text-end"><?= (int)($row['qtd'] ?? 0) ?></td>
                                    <td class="text-end fw-bold"><?= fmtNum($row['total'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

        </div><!-- /pane-geral -->

        <!-- Tab Regional (conteúdo gerado via JS) -->
        <div class="tab-pane fade" id="pane-regional" role="tabpanel">
            <div id="regional-content"></div>
        </div>
    </div><!-- /tab-content -->
</div>

<!-- Modal Relatório Regional -->
<div class="modal fade" id="modalRegional" tabindex="-1" aria-labelledby="modalRegionalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRegionalLabel"><i class="fas fa-globe me-2"></i>Relatório Regional</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Moeda de exibição</label>
                    <select id="regional-moeda" class="form-select">
                        <option value="BRL">BRL (Real)</option>
                        <option value="USD">USD (Dólar)</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold small">Idioma</label>
                    <select id="regional-idioma" class="form-select">
                        <option value="pt">Português (PT-BR)</option>
                        <option value="en">English (EN)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="gerarRelatorioRegional()"><i class="fas fa-check me-1"></i>Gerar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const DATA = <?= json_encode($regionalData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;

    const i18n = {
        pt: {
            title: 'Relatório Regional',
            orders: 'Total de Pedidos',
            total: 'Total Geral',
            subtotal: 'Subtotal Produtos',
            servicos: 'Taxa de Serviço',
            impostos: 'Impostos',
            imposto_local: 'Imposto Local',
            frete: 'Frete',
            byStatus: 'Por Status',
            byPayment: 'Por Forma de Pagamento',
            status: 'Status',
            qty: 'Qtd',
            form: 'Forma',
            noData: 'Nenhum dado',
            currency: 'Moeda',
            allConverted: 'Todos os valores convertidos para',
            rate: 'Taxa de conversão'
        },
        en: {
            title: 'Regional Report',
            orders: 'Total Orders',
            total: 'Grand Total',
            subtotal: 'Products Subtotal',
            servicos: 'Service Fee',
            impostos: 'Taxes',
            imposto_local: 'Local Tax',
            frete: 'Shipping',
            byStatus: 'By Status',
            byPayment: 'By Payment Method',
            status: 'Status',
            qty: 'Qty',
            form: 'Method',
            noData: 'No data',
            currency: 'Currency',
            allConverted: 'All values converted to',
            rate: 'Conversion rate'
        }
    };

    const statusLabelsEn = {
        'pendente': 'Pending',
        'processando': 'Processing',
        'pago': 'Paid',
        'carne_pagando': 'Installment Paying',
        'carne_aguardando': 'Installment Waiting',
        'produto_consolidado': 'Box Closed',
        'etiqueta_gerada': 'Label Generated',
        'em_transporte': 'In Transit',
        'aguardando_liberacao_aduaneira': 'Customs Clearance',
        'enviado_ao_destinatario': 'Sent to Recipient',
        'entregue': 'Delivered',
        'cancelado': 'Cancelled'
    };

    function fmt(v, moeda) {
        const n = parseFloat(v) || 0;
        if (moeda === 'USD') return '$ ' + n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
        return 'R$ ' + n.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    function convertToTarget(val, originalMoeda, targetMoeda, taxa) {
        const v = parseFloat(val) || 0;
        if (originalMoeda === targetMoeda) return v;
        if (originalMoeda === 'USD' && targetMoeda === 'BRL') return v * taxa;
        if (originalMoeda === 'BRL' && targetMoeda === 'USD') return v / taxa;
        return v;
    }

    window.abrirModalRegional = function() {
        const modal = new bootstrap.Modal(document.getElementById('modalRegional'));
        modal.show();
    };

    window.gerarRelatorioRegional = function() {
        const moeda = document.getElementById('regional-moeda').value;
        const idioma = document.getElementById('regional-idioma').value;
        const t = i18n[idioma] || i18n.pt;
        const taxa = DATA.taxaUsdBrl;
        const labels = idioma === 'en' ? statusLabelsEn : DATA.statusLabels;

        // Calcular totais unificados
        const usdData = DATA.usd || {};
        const brlData = DATA.brl || {};
        const keys = ['total','subtotal','servicos','impostos','imposto_local','frete'];
        const totaisUnificados = {};
        keys.forEach(k => {
            const fromUsd = convertToTarget(usdData[k] || 0, 'USD', moeda, taxa);
            const fromBrl = convertToTarget(brlData[k] || 0, 'BRL', moeda, taxa);
            totaisUnificados[k] = fromUsd + fromBrl;
        });

        // Por status unificado
        const statusMap = {};
        (DATA.porStatus || []).forEach(row => {
            const st = row.status || 'N/A';
            if (!statusMap[st]) statusMap[st] = {status:st, qtd:0, subtotal:0, servicos:0, impostos:0, frete:0, total:0};
            const orig = (row.moeda || 'USD').toUpperCase();
            statusMap[st].qtd += parseInt(row.qtd) || 0;
            statusMap[st].subtotal += convertToTarget(row.subtotal||0, orig, moeda, taxa);
            statusMap[st].servicos += convertToTarget(row.servicos||0, orig, moeda, taxa);
            statusMap[st].impostos += convertToTarget(row.impostos||0, orig, moeda, taxa);
            statusMap[st].frete += convertToTarget(row.frete||0, orig, moeda, taxa);
            statusMap[st].total += convertToTarget(row.total||0, orig, moeda, taxa);
        });
        const statusArr = Object.values(statusMap).sort((a,b) => b.total - a.total);

        // Por pagamento unificado
        const pagMap = {};
        (DATA.porPagamento || []).forEach(row => {
            const f = row.forma || 'N/A';
            if (!pagMap[f]) pagMap[f] = {forma:f, qtd:0, total:0};
            pagMap[f].qtd += parseInt(row.qtd) || 0;
            // porPagamento não tem moeda separada, assume moeda mista — converter tudo via BRL
            pagMap[f].total += parseFloat(row.total) || 0;
        });

        const qtdPedidos = parseInt(DATA.totais.qtd_pedidos) || 0;
        const sym = moeda === 'USD' ? '$' : 'R$';
        const rateLabel = moeda === 'BRL' ? ('1 USD = ' + taxa.toFixed(2) + ' BRL') : ('1 BRL = ' + (1/taxa).toFixed(4) + ' USD');

        let html = '<div class="alert alert-info small mb-3"><i class="fas fa-info-circle me-1"></i>' + t.allConverted + ' <strong>' + moeda + '</strong>. ' + t.rate + ': ' + rateLabel + '</div>';

        // Cards
        html += '<div class="row g-3 mb-4">';
        const cardDefs = [
            {key:'total', label:t.total, color:'primary', icon:'fas fa-dollar-sign'},
            {key:'subtotal', label:t.subtotal, color:'dark', icon:'fas fa-box'},
            {key:'servicos', label:t.servicos, color:'info', icon:'fas fa-concierge-bell'},
            {key:'impostos', label:t.impostos, color:'warning', icon:'fas fa-landmark'},
            {key:'imposto_local', label:t.imposto_local, color:'danger', icon:'fas fa-flag'},
            {key:'frete', label:t.frete, color:'success', icon:'fas fa-truck'},
        ];
        cardDefs.forEach(c => {
            const v = totaisUnificados[c.key] || 0;
            if (c.key === 'imposto_local' && v <= 0) return;
            html += '<div class="col-lg-4 col-md-6"><div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--bs-'+c.color+')!important"><div class="card-body">';
            html += '<div class="text-muted small mb-1"><i class="'+c.icon+' me-1"></i>'+c.label+'</div>';
            html += '<div class="fs-4 fw-bold">'+fmt(v, moeda)+'</div>';
            html += '</div></div></div>';
        });
        html += '</div>';

        // Pedidos
        html += '<div class="card border-0 shadow-sm mb-4"><div class="card-body d-flex align-items-center justify-content-between"><div><i class="fas fa-receipt me-2 text-muted"></i><span class="fw-semibold">'+t.orders+'</span></div><span class="fs-4 fw-bold">'+qtdPedidos.toLocaleString()+'</span></div></div>';

        // Tabela status
        html += '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-list-alt me-2"></i>'+t.byStatus+'</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>'+t.status+'</th><th class="text-end">'+t.qty+'</th><th class="text-end">'+t.subtotal+'</th><th class="text-end">'+t.servicos+'</th><th class="text-end">'+t.impostos+'</th><th class="text-end">'+t.frete+'</th><th class="text-end">Total</th></tr></thead><tbody>';
        if (statusArr.length === 0) {
            html += '<tr><td colspan="7" class="text-center text-muted py-3">'+t.noData+'</td></tr>';
        } else {
            statusArr.forEach(row => {
                const lbl = labels[row.status] || row.status.replace(/_/g,' ');
                html += '<tr><td><span class="badge bg-secondary bg-opacity-10 text-dark">'+lbl+'</span></td>';
                html += '<td class="text-end">'+row.qtd+'</td>';
                html += '<td class="text-end">'+fmt(row.subtotal, moeda)+'</td>';
                html += '<td class="text-end">'+fmt(row.servicos, moeda)+'</td>';
                html += '<td class="text-end">'+fmt(row.impostos, moeda)+'</td>';
                html += '<td class="text-end">'+fmt(row.frete, moeda)+'</td>';
                html += '<td class="text-end fw-bold">'+fmt(row.total, moeda)+'</td></tr>';
            });
        }
        html += '</tbody></table></div></div></div>';

        // Tabela pagamento
        html += '<div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-credit-card me-2"></i>'+t.byPayment+'</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>'+t.form+'</th><th class="text-end">'+t.qty+'</th><th class="text-end">Total</th></tr></thead><tbody>';
        const pagArr = Object.values(pagMap).sort((a,b) => b.total - a.total);
        if (pagArr.length === 0) {
            html += '<tr><td colspan="3" class="text-center text-muted py-3">'+t.noData+'</td></tr>';
        } else {
            pagArr.forEach(row => {
                html += '<tr><td>'+row.forma.replace(/_/g,' ')+'</td><td class="text-end">'+row.qtd+'</td><td class="text-end fw-bold">'+fmt(row.total, moeda)+'</td></tr>';
            });
        }
        html += '</tbody></table></div></div></div>';

        document.getElementById('regional-content').innerHTML = html;

        // Fechar modal e ativar tab
        bootstrap.Modal.getInstance(document.getElementById('modalRegional')).hide();
        const tabEl = document.getElementById('tab-regional');
        tabEl.setAttribute('data-bs-toggle', 'tab');
        tabEl.setAttribute('data-bs-target', '#pane-regional');
        new bootstrap.Tab(tabEl).show();
    };
})();
</script>
