<?php
$totais = $totais ?? [];
$porStatus = $porStatus ?? [];
$porMoeda = $porMoeda ?? [];
$porPagamento = $porPagamento ?? [];
$statusList = $statusList ?? [];
$dateStart = $dateStart ?? date('Y-m-01');
$dateEnd = $dateEnd ?? date('Y-m-d');
$statusFilter = $statusFilter ?? '';
$moedaFilter = $moedaFilter ?? '';

function fmtVal($v, $moeda = '') {
    $n = (float)($v ?? 0);
    $sym = strtoupper($moeda) === 'BRL' ? 'R$' : '$';
    return $sym . ' ' . number_format($n, 2, ',', '.');
}
function fmtNum($v) { return number_format((float)($v ?? 0), 2, ',', '.'); }
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Relatório Geral</h4>
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
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($statusList as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $s))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold">Moeda</label>
                    <select name="moeda" class="form-select">
                        <option value="">Todas</option>
                        <option value="USD" <?= $moedaFilter === 'USD' ? 'selected' : '' ?>>USD</option>
                        <option value="BRL" <?= $moedaFilter === 'BRL' ? 'selected' : '' ?>>BRL</option>
                        <option value="EUR" <?= $moedaFilter === 'EUR' ? 'selected' : '' ?>>EUR</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-12">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards de Totais -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-receipt me-1"></i>Pedidos</div>
                    <div class="fs-3 fw-bold text-dark"><?= number_format((int)($totais['qtd_pedidos'] ?? 0), 0, '', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0d6efd!important">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-dollar-sign me-1"></i>Total Geral</div>
                    <div class="fs-4 fw-bold text-primary"><?= fmtNum($totais['total_geral'] ?? 0) ?></div>
                    <?php if (!empty($totais['total_geral_brl']) && (float)$totais['total_geral_brl'] > 0): ?>
                    <div class="text-muted small">BRL: R$ <?= fmtNum($totais['total_geral_brl']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-box me-1"></i>Subtotal Produtos</div>
                    <div class="fs-5 fw-bold"><?= fmtNum($totais['total_subtotal'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-concierge-bell me-1"></i>Taxa de Serviço</div>
                    <div class="fs-5 fw-bold text-info"><?= fmtNum($totais['total_servicos'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-landmark me-1"></i>Impostos</div>
                    <div class="fs-5 fw-bold text-warning"><?= fmtNum($totais['total_impostos'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <?php if (isset($totais['total_imposto_local']) && (float)$totais['total_imposto_local'] > 0): ?>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-flag me-1"></i>Imposto Local</div>
                    <div class="fs-5 fw-bold text-danger"><?= fmtNum($totais['total_imposto_local']) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="fas fa-truck me-1"></i>Frete</div>
                    <div class="fs-5 fw-bold text-success"><?= fmtNum($totais['total_frete'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Tabela por Status -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-list-alt me-2"></i>Por Status</h6>
                </div>
                <div class="card-body p-0">
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
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($porStatus)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">Nenhum dado</td></tr>
                                <?php else: ?>
                                <?php foreach ($porStatus as $row): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/relatorio-geral?date_start=<?= $dateStart ?>&date_end=<?= $dateEnd ?>&status=<?= urlencode($row['status'] ?? '') ?>&moeda=<?= urlencode($moedaFilter) ?>" class="text-decoration-none">
                                            <span class="badge bg-secondary bg-opacity-10 text-dark"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status'] ?? 'N/A'))) ?></span>
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
