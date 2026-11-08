<?php
ob_start();
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtKg = fn($g) => $g === null ? '—' : number_format(((float) $g) / 1000, 3, ',', '.') . ' kg';
$fmtCm = fn($v) => $v === null ? '?' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
$fmtDims = fn($c, $l, $a) => ($c === null && $l === null && $a === null) ? '—' : $fmtCm($c) . ' × ' . $fmtCm($l) . ' × ' . $fmtCm($a) . ' cm';
$fmtData = function ($v) { $t = $v ? strtotime((string) $v) : false; return $t ? date('d/m/Y H:i', $t) : '—'; };
$mesmo = fn($a, $b) => $a !== null && $b !== null && abs((float) $a - (float) $b) < 0.01;
$q = $q ?? '';
$linhas = $linhas ?? [];
$nivelUi = [
    'ok' => ['success', 'fa-check-circle', __('admin.labels_wp.conf_result_ok', 'Conferido')],
    'info' => ['info', 'fa-info-circle', __('admin.labels_wp.conf_result_info', 'Verificar')],
    'warning' => ['warning', 'fa-exclamation-triangle', __('admin.labels_wp.conf_result_warning', 'Atenção')],
    'danger' => ['danger', 'fa-times-circle', __('admin.labels_wp.conf_result_danger', 'Divergente')],
    'cancelado' => ['secondary', 'fa-ban', __('admin.labels_wp.cancelled', 'Cancelado')],
];
?>
<style>
    .conf-card{border:none;box-shadow:0 1px 3px rgba(0,0,0,.08);border-radius:8px}
    .conf-table td,.conf-table th{vertical-align:top;font-size:.85rem}
    .conf-table .conf-sub{font-size:.75rem;color:#6c757d}
    .conf-ok{color:#198754}.conf-bad{color:#dc3545;font-weight:600}.conf-warn{color:#997404}
    .conf-row-cancelado td{opacity:.55}
    .conf-itens{font-size:.75rem;margin:0}
    .conf-itens td{padding:2px 6px!important}
</style>
<div class="container-fluid px-2 px-md-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom flex-wrap gap-2">
        <h1 class="h4 mb-0"><i class="fas fa-clipboard-check me-2 text-primary"></i><?= __('admin.labels_wp.conf_title', 'Conferência de Etiquetas') ?></h1>
        <a href="/admin/etiquetas-wp" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><?= __('admin.labels_wp.conf_back', 'Voltar para Correio Internacional') ?></a>
    </div>

    <form method="get" action="/admin/etiquetas-wp/conferencia" class="mb-3">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" name="q" class="form-control" value="<?= $h($q) ?>" placeholder="<?= $h(__('admin.labels_wp.conf_search_placeholder', 'ID do pedido (ex.: 1587), código de rastreio ou nome do cliente')) ?>">
            <button class="btn btn-primary" type="submit"><?= __('admin.labels_wp.conf_search', 'Conferir') ?></button>
        </div>
        <div class="form-text"><?= __('admin.labels_wp.conf_help', 'Compara o peso, as medidas e os valores enviados aos Correios em cada etiqueta com o que o pedido tem hoje. Sem busca, mostra as últimas 30 etiquetas.') ?></div>
    </form>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger small"><?= __('admin.labels_wp.conf_wp_error', 'Não foi possível consultar o WordPress:') ?> <?= $h($erro) ?></div>
    <?php endif; ?>

    <div class="card conf-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 conf-table">
                    <thead class="table-light">
                        <tr>
                            <th><?= __('admin.labels_wp.col_order', 'Pedido') ?></th>
                            <th><?= __('admin.labels_wp.col_tracking', 'Tracking') ?></th>
                            <th><?= __('admin.labels_wp.conf_col_weight', 'Peso (enviado / pedido)') ?></th>
                            <th><?= __('admin.labels_wp.conf_col_dims', 'Medidas C×L×A (enviado / pedido)') ?></th>
                            <th><?= __('admin.labels_wp.conf_col_items', 'Itens declarados (PDF)') ?></th>
                            <th><?= __('admin.labels_wp.conf_col_result', 'Resultado') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($linhas)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-inbox me-1"></i><?= $q !== '' ? $h(__('admin.labels_wp.no_packages_for', 'Nenhum pacote encontrado para "{q}"', ['q' => $q])) : __('admin.labels_wp.conf_none', 'Nenhuma etiqueta encontrada.') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($linhas as $l):
                        [$cor, $icone, $rotulo] = $nivelUi[$l['nivel']] ?? $nivelUi['info'];
                        $pesoIgual = $l['env_peso_g'] !== null && $l['ped_peso_g'] !== null && abs($l['env_peso_g'] - $l['ped_peso_g']) <= 1;
                        $dimsIguais = $mesmo($l['env_c'], $l['ped_c']) && $mesmo($l['env_l'], $l['ped_l']) && $mesmo($l['env_a'], $l['ped_a']);
                    ?>
                        <tr class="<?= $l['cancelado'] ? 'conf-row-cancelado' : '' ?>">
                            <td>
                                <?php if ($l['pedido_id'] > 0): ?>
                                    <a href="/admin/pedidos/detalhes/<?= (int) $l['pedido_id'] ?>" target="_blank"><strong>#<?= str_pad((string) $l['pedido_id'], 6, '0', STR_PAD_LEFT) ?></strong></a>
                                <?php else: ?>
                                    <strong><?= $h($l['numero_pedido'] ?: '-') ?></strong>
                                <?php endif; ?>
                                <div class="conf-sub"><?= $h($l['cliente'] ?: '-') ?></div>
                                <?php if ($l['status_pedido'] !== ''): ?><div class="conf-sub"><?= $h($l['status_pedido']) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <code><?= $h($l['tracking'] ?: '-') ?></code>
                                <div class="mt-1">
                                    <?php if ($l['atual']): ?><span class="badge bg-primary"><?= __('admin.labels_wp.conf_current', 'Etiqueta atual') ?></span><?php endif; ?>
                                    <?php if ($l['cancelado']): ?><span class="badge bg-secondary"><?= __('admin.labels_wp.cancelled', 'Cancelado') ?></span><?php endif; ?>
                                    <?php if ($l['container_id'] !== '' && $l['container_id'] !== '0'): ?><span class="badge bg-light text-dark border"><?= __('admin.labels_wp.conf_in_container', 'Em container') ?></span><?php endif; ?>
                                    <?php if (!empty($l['impressao'])): ?>
                                        <span class="badge bg-success" title="<?= $h(__('admin.labels_wp.printed_times', 'Baixada {n}x, última em {d}', ['n' => $l['impressao']['vezes'], 'd' => $fmtData($l['impressao']['ultima'])])) ?>"><i class="fas fa-print me-1"></i><?= __('admin.labels_wp.printed', 'Impresso') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conf-sub mt-1"><?= __('admin.labels_wp.col_generated_at', 'Data gerada') ?>: <?= $h($fmtData($l['gerada_em'])) ?></div>
                            </td>
                            <td>
                                <div class="<?= $l['ped_peso_g'] === null ? '' : ($pesoIgual ? 'conf-ok' : 'conf-bad') ?>"><?= $h($fmtKg($l['env_peso_g'])) ?></div>
                                <div class="conf-sub"><?= __('admin.labels_wp.conf_order_now', 'Pedido') ?>: <?= $h($fmtKg($l['ped_peso_g'])) ?></div>
                            </td>
                            <td>
                                <div class="<?= ($l['env_c'] === null || $l['ped_c'] === null) ? '' : ($dimsIguais ? 'conf-ok' : 'conf-bad') ?>"><?= $h($fmtDims($l['env_c'], $l['env_l'], $l['env_a'])) ?></div>
                                <div class="conf-sub"><?= __('admin.labels_wp.conf_order_now', 'Pedido') ?>: <?= $h($fmtDims($l['ped_c'], $l['ped_l'], $l['ped_a'])) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($l['itens'])): ?>
                                    <table class="table table-borderless conf-itens">
                                        <?php foreach ($l['itens'] as $it): ?>
                                            <tr>
                                                <td><?= (int) $it['qtd'] ?>×</td>
                                                <td><?= $h(mb_strimwidth($it['descricao'], 0, 60, '…', 'UTF-8')) ?> <span class="text-muted"><?= $h($it['ncm']) ?></span></td>
                                                <td class="text-end text-nowrap <?= $it['valor'] <= 0.01 ? 'conf-bad' : '' ?>">US$ <?= number_format($it['valor'], 2, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="border-top"><td></td><td class="fw-semibold"><?= __('admin.labels_wp.conf_total_goods', 'Total mercadorias') ?></td><td class="text-end text-nowrap fw-semibold">US$ <?= number_format($l['total_declarado'], 2, ',', '.') ?></td></tr>
                                    </table>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="min-width:220px">
                                <span class="badge bg-<?= $cor ?><?= $cor === 'warning' || $cor === 'info' ? ' text-dark' : '' ?>"><i class="fas <?= $icone ?> me-1"></i><?= $h($rotulo) ?></span>
                                <?php if (!$l['cancelado']): ?>
                                    <?php foreach ($l['alertas'] as [$nv, $msg]): ?>
                                        <div class="small mt-1 <?= $nv === 'info' ? 'text-muted' : ($nv === 'warning' ? 'conf-warn' : 'text-danger') ?>">• <?= $h($msg) ?></div>
                                    <?php endforeach; ?>
                                    <?php if ($l['nivel'] === 'ok'): ?>
                                        <div class="small text-success mt-1"><?= __('admin.labels_wp.conf_ok_msg', 'Peso e medidas enviados batem com o pedido.') ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($l['wp_post_id'] > 0 && !$l['cancelado']): ?>
                                    <div class="mt-2"><a href="/admin/etiquetas-wp/pdf/pacote/<?= (int) $l['wp_post_id'] ?>" target="_blank" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf me-1"></i>PDF</a></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="small text-muted mt-2 mb-4">
        <?= __('admin.labels_wp.conf_footer', 'Peso e medidas "enviado" são os valores gravados no WordPress na criação da etiqueta (os mesmos mandados aos Correios). Os itens são os do PDF, que é atualizado a cada download.') ?>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
