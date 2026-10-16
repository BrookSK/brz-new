<?php
/**
 * View: Relatório Trimestral de Produtos Vendidos (tela admin).
 * Variáveis recebidas do AdminRelatorioTrimestralController::index().
 */
$ano = (int) ($ano ?? date('Y'));
$moedaFilter = strtoupper(trim((string) ($moedaFilter ?? '')));
$consolidarBrl = (bool) ($consolidarBrl ?? false);
$relatorio = $relatorio ?? [];
$totalGeralPorMoeda = $totalGeralPorMoeda ?? [];
$taxaUsdBrl = (float) ($taxaUsdBrl ?? 5.5);
$subtotalDisponivel = (bool) ($subtotalDisponivel ?? true);

if (!function_exists('fmtTri')) {
    function fmtTri($v) { return number_format((float) ($v ?? 0), 2, ',', '.'); }
}

// Formata [moeda => valor] como "USD 1.234,56 · BRL 789,00".
if (!function_exists('fmtMoedasTri')) {
    function fmtMoedasTri(array $porMoeda, string $moedaFiltro): string {
        if (empty($porMoeda)) {
            $m = $moedaFiltro !== '' ? $moedaFiltro : 'USD';
            return $m . ' ' . fmtTri(0);
        }
        ksort($porMoeda);
        $parts = [];
        foreach ($porMoeda as $moeda => $valor) {
            $parts[] = htmlspecialchars($moeda) . ' ' . fmtTri($valor);
        }
        return implode(' <span class="text-muted">·</span> ', $parts);
    }
}

// Total consolidado em BRL (converte USD pela taxa).
if (!function_exists('consolidadoBrlTri')) {
    function consolidadoBrlTri(array $porMoeda, float $taxa): float {
        $t = 0.0;
        foreach ($porMoeda as $moeda => $valor) {
            $t += (strtoupper($moeda) === 'BRL') ? (float) $valor : ((float) $valor * $taxa);
        }
        return $t;
    }
}

$anoAtual = (int) date('Y');

// Monta a URL de PDF preservando os filtros atuais
$pdfParams = ['ano' => $ano, 'format' => 'pdf'];
if ($moedaFilter !== '') $pdfParams['moeda'] = $moedaFilter;
if ($consolidarBrl) $pdfParams['consolidar'] = 'brl';
$pdfUrl = '/admin/relatorio-trimestral?' . http_build_query($pdfParams);
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title"><?= __('admin.report_quarterly.page_title', 'Relatório Trimestral de Vendas') ?></h1>
            <p class="page-subtitle"><?= __('admin.report_quarterly.page_subtitle', 'Valor de produtos vendidos (subtotal dos itens) por mês e trimestre. Itens de redirecionamento não entram no cálculo.') ?></p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="border rounded-pill px-3 py-1 d-none d-lg-flex align-items-center gap-2 bg-white">
                <i class="fas fa-exchange-alt text-muted" style="font-size:11px;"></i>
                <span class="small">USD → BRL</span>
                <span class="fw-bold"><?= fmtTri($taxaUsdBrl) ?></span>
            </div>
            <a href="<?= htmlspecialchars($pdfUrl) ?>" class="btn btn-danger btn-sm px-3" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf me-1"></i><?= __('admin.report_quarterly.download_pdf', 'Baixar PDF') ?>
            </a>
        </div>
    </div>

    <?php if (!$subtotalDisponivel): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <?= __('admin.report_quarterly.no_subtotal_col', 'A tabela de itens do pedido não possui coluna de subtotal neste ambiente, não é possível gerar o relatório.') ?>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/relatorio-trimestral">
                <div class="d-flex align-items-end flex-wrap gap-3">
                    <div class="flex-shrink-0">
                        <label class="form-label small text-muted mb-1"><i class="far fa-calendar me-1"></i><?= __('admin.report_quarterly.year', 'ANO') ?></label>
                        <select name="ano" class="form-select form-select-sm" style="min-width:120px;">
                            <?php for ($y = $anoAtual; $y >= $anoAtual - 6; $y--): ?>
                                <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="flex-shrink-0">
                        <label class="form-label small text-muted mb-1"><i class="fas fa-coins me-1"></i><?= __('admin.report_quarterly.currency_upper', 'MOEDA') ?></label>
                        <select name="moeda" class="form-select form-select-sm" style="min-width:120px;" <?= $consolidarBrl ? 'disabled' : '' ?>>
                            <option value=""><?= __('admin.report_quarterly.all_f', 'Todas') ?></option>
                            <option value="BRL" <?= $moedaFilter === 'BRL' ? 'selected' : '' ?>>BRL — Real</option>
                            <option value="USD" <?= $moedaFilter === 'USD' ? 'selected' : '' ?>>USD — Dólar</option>
                        </select>
                    </div>

                    <!-- Toggle: converter dólar em real e consolidar -->
                    <div class="flex-shrink-0">
                        <label class="form-label small text-muted mb-1"><i class="fas fa-right-left me-1"></i><?= __('admin.report_quarterly.consolidate_label', 'CONVERSÃO') ?></label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="consolidarBrl" name="consolidar" value="brl" <?= $consolidarBrl ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="consolidarBrl">
                                <?= __('admin.report_quarterly.consolidate_brl', 'Converter dólar em real (consolidar em BRL)') ?>
                            </label>
                        </div>
                    </div>

                    <div class="flex-shrink-0">
                        <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-filter me-1"></i><?= __('admin.report_quarterly.apply', 'Aplicar') ?></button>
                    </div>
                    <div class="flex-shrink-0">
                        <a href="/admin/relatorio-trimestral" class="text-muted small text-decoration-none"><i class="fas fa-times me-1"></i><?= __('admin.report_quarterly.clear', 'Limpar') ?></a>
                    </div>
                </div>
                <?php if ($consolidarBrl): ?>
                    <div class="form-text mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        <?= __('admin.report_quarterly.consolidate_note', 'Valores em dólar convertidos para real pela taxa atual') ?>
                        (USD → BRL <?= fmtTri($taxaUsdBrl) ?>).
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Card de total geral consolidado -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="text-muted small text-uppercase"><?= __('admin.report_quarterly.grand_total', 'Total Consolidado') ?> — <?= $ano ?></div>
                    <div class="fs-4 fw-bold"><?= fmtMoedasTri($totalGeralPorMoeda, $consolidarBrl ? 'BRL' : $moedaFilter) ?></div>
                </div>
                <?php if (!$consolidarBrl): ?>
                <div class="text-end">
                    <div class="text-muted small text-uppercase"><?= __('admin.report_quarterly.consolidated_brl', 'Equivalente em BRL') ?></div>
                    <div class="fs-4 fw-bold text-success">R$ <?= fmtTri(consolidadoBrlTri($totalGeralPorMoeda, $taxaUsdBrl)) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Trimestres -->
    <div class="row g-4">
        <?php foreach ($relatorio as $tri): ?>
            <div class="col-12 col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <span class="fw-semibold"><i class="fas fa-calendar-week text-primary me-2"></i><?= htmlspecialchars($tri['nome']) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3"><?= __('admin.report_quarterly.th_month', 'Mês') ?></th>
                                    <th class="text-end pe-3"><?= __('admin.report_quarterly.th_products_sold', 'Produtos Vendidos') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tri['meses'] as $mes): ?>
                                    <tr>
                                        <td class="ps-3"><?= htmlspecialchars($mes['nome']) ?></td>
                                        <td class="text-end pe-3"><?= fmtMoedasTri($mes['por_moeda'], $consolidarBrl ? 'BRL' : $moedaFilter) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td class="ps-3"><?= __('admin.report_quarterly.quarter_total', 'Total do Trimestre') ?></td>
                                    <td class="text-end pe-3"><?= fmtMoedasTri($tri['total_por_moeda'], $consolidarBrl ? 'BRL' : $moedaFilter) ?></td>
                                </tr>
                                <?php if (!$consolidarBrl): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= __('admin.report_quarterly.quarter_total_brl', 'Equivalente em BRL') ?></td>
                                    <td class="text-end pe-3 text-success fw-semibold">R$ <?= fmtTri(consolidadoBrlTri($tri['total_por_moeda'], $taxaUsdBrl)) ?></td>
                                </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-muted small mt-4 mb-0">
        <i class="fas fa-info-circle me-1"></i>
        <?= __('admin.report_quarterly.footnote', 'Valores somam o subtotal dos itens de produto dos pedidos (por data de criação do pedido), excluindo pedidos cancelados/excluídos e itens de redirecionamento. Quando há mais de uma moeda, cada uma é somada separadamente e o equivalente em BRL usa a taxa USD→BRL atual.') ?>
    </p>
</div>

<script>
// Ao marcar "converter em real", a seleção de moeda deixa de fazer sentido
// (consolidamos tudo em BRL). Desabilitamos o seletor para evitar confusão.
document.addEventListener('DOMContentLoaded', function () {
    var cb = document.getElementById('consolidarBrl');
    if (!cb) return;
    var form = cb.closest('form');
    var sel = form ? form.querySelector('select[name="moeda"]') : null;
    cb.addEventListener('change', function () {
        if (sel) sel.disabled = cb.checked;
    });
});
</script>
