<?php
/**
 * View PDF: Relatório Trimestral de Produtos Vendidos.
 * HTML standalone (sem layout admin), otimizado para Dompdf.
 */
$ano = (int) ($ano ?? date('Y'));
$moedaFilter = strtoupper(trim((string) ($moedaFilter ?? '')));
$consolidarBrl = (bool) ($consolidarBrl ?? false);
$relatorio = $relatorio ?? [];
$totalGeralPorMoeda = $totalGeralPorMoeda ?? [];
$taxaUsdBrl = (float) ($taxaUsdBrl ?? 5.5);

if (!function_exists('fmtTri')) {
    function fmtTri($v) { return number_format((float) ($v ?? 0), 2, ',', '.'); }
}
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
        return implode(' &middot; ', $parts);
    }
}
if (!function_exists('consolidadoBrlTri')) {
    function consolidadoBrlTri(array $porMoeda, float $taxa): float {
        $t = 0.0;
        foreach ($porMoeda as $moeda => $valor) {
            $t += (strtoupper($moeda) === 'BRL') ? (float) $valor : ((float) $valor * $taxa);
        }
        return $t;
    }
}

$moedaExibicao = $consolidarBrl ? 'BRL' : $moedaFilter;
$tituloRelatorio = __('admin.report_quarterly.pdf_title', 'Relatório Trimestral de Vendas');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($tituloRelatorio) ?> - <?= $ano ?></title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { color: #1f2937; font-size: 12px; margin: 0; padding: 0; }
        h1 { font-size: 18px; margin: 0 0 2px 0; }
        .subtitle { color: #6b7280; font-size: 11px; margin: 0 0 12px 0; }
        .meta { color: #374151; font-size: 11px; margin-bottom: 14px; }
        .meta span { display: inline-block; margin-right: 16px; }
        .grand {
            background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px;
            padding: 10px 14px; margin-bottom: 16px;
        }
        .grand .lbl { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .grand .val { font-size: 16px; font-weight: bold; }
        .grand .brl { color: #047857; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f9fafb; font-size: 10px; text-transform: uppercase; color: #6b7280; }
        td.num, th.num { text-align: right; }
        .tri-title {
            font-weight: bold; font-size: 13px; margin: 14px 0 6px 0;
            padding-bottom: 4px; border-bottom: 2px solid #3b82f6; color: #1e40af;
        }
        tfoot td { font-weight: bold; background: #f9fafb; }
        .brl-row td { color: #047857; font-weight: bold; border-bottom: none; }
        .footer { color: #9ca3af; font-size: 9px; margin-top: 18px; }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($tituloRelatorio) ?> — <?= $ano ?></h1>
    <p class="subtitle"><?= __('admin.report_quarterly.pdf_subtitle', 'Valor de produtos vendidos por mês e trimestre (itens de redirecionamento excluídos).') ?></p>

    <div class="meta">
        <span><strong><?= __('admin.report_quarterly.year', 'Ano') ?>:</strong> <?= $ano ?></span>
        <span><strong><?= __('admin.report_quarterly.currency_upper', 'Moeda') ?>:</strong>
            <?= $consolidarBrl ? 'BRL (consolidado)' : ($moedaFilter !== '' ? htmlspecialchars($moedaFilter) : __('admin.report_quarterly.all_f', 'Todas')) ?>
        </span>
        <span><strong>USD → BRL:</strong> <?= fmtTri($taxaUsdBrl) ?></span>
        <span><strong><?= __('admin.report_quarterly.pdf_generated_at', 'Gerado em') ?>:</strong> <?= date('d/m/Y H:i') ?></span>
    </div>

    <div class="grand">
        <div class="lbl"><?= __('admin.report_quarterly.grand_total', 'Total Consolidado') ?></div>
        <div class="val"><?= fmtMoedasTri($totalGeralPorMoeda, $moedaExibicao) ?></div>
        <?php if (!$consolidarBrl): ?>
            <div class="lbl" style="margin-top:6px;"><?= __('admin.report_quarterly.consolidated_brl', 'Equivalente em BRL') ?></div>
            <div class="val brl">R$ <?= fmtTri(consolidadoBrlTri($totalGeralPorMoeda, $taxaUsdBrl)) ?></div>
        <?php endif; ?>
    </div>

    <?php foreach ($relatorio as $tri): ?>
        <div class="tri-title"><?= htmlspecialchars($tri['nome']) ?></div>
        <table>
            <thead>
                <tr>
                    <th><?= __('admin.report_quarterly.th_month', 'Mês') ?></th>
                    <th class="num"><?= __('admin.report_quarterly.th_products_sold', 'Produtos Vendidos') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tri['meses'] as $mes): ?>
                    <tr>
                        <td><?= htmlspecialchars($mes['nome']) ?></td>
                        <td class="num"><?= fmtMoedasTri($mes['por_moeda'], $moedaExibicao) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><?= __('admin.report_quarterly.quarter_total', 'Total do Trimestre') ?></td>
                    <td class="num"><?= fmtMoedasTri($tri['total_por_moeda'], $moedaExibicao) ?></td>
                </tr>
                <?php if (!$consolidarBrl): ?>
                    <tr class="brl-row">
                        <td><?= __('admin.report_quarterly.quarter_total_brl', 'Equivalente em BRL') ?></td>
                        <td class="num">R$ <?= fmtTri(consolidadoBrlTri($tri['total_por_moeda'], $taxaUsdBrl)) ?></td>
                    </tr>
                <?php endif; ?>
            </tfoot>
        </table>
    <?php endforeach; ?>

    <div class="footer">
        <?= __('admin.report_quarterly.footnote', 'Valores somam o subtotal dos itens de produto dos pedidos (por data de criação do pedido), excluindo pedidos cancelados/excluídos e itens de redirecionamento.') ?>
        — Braziliana Admin
    </div>
</body>
</html>
