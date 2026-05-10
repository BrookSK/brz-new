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

// Calcula total convertido para BRL
function totalEmBrl($usdRow, $brlRow, $campo, $taxa) {
    $vUsd = (float)($usdRow[$campo] ?? 0);
    $vBrl = (float)($brlRow[$campo] ?? 0);
    return $vBrl + ($vUsd * $taxa);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Relatório Geral</h4>
        <span class="text-muted small">Taxa USD→BRL: <strong><?= fmtNum($taxaUsdBrl) ?></strong></span>
    </div>

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
                <div class="col-md-2 col-sm-12">
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

    <!-- Cards financeiros com breakdown por moeda -->
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
                    // Mapa de labels canônicos para exibição na tabela
                    $statusLabels = $statusList;
                    // Consolidar porStatus (que vem agrupado por status+moeda) em uma linha por status, convertendo USD→BRL
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
                                    <th>Status</th>
                                    <th class="text-end">Qtd</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">Serviço</th>
                                    <th class="text-end">Impostos</th>
                                    <th class="text-end">Frete</th>
                                    <th class="text-end">Total (R$)</th>
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
                            <thead class="table-light">
                                <tr><th>Moeda</th><th class="text-end">Qtd</th><th class="text-end">Total</th></tr>
                            </thead>
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
                            <thead class="table-light">
                                <tr><th>Forma</th><th class="text-end">Qtd</th><th class="text-end">Total</th></tr>
                            </thead>
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
</div>
