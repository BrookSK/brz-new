<?php
$statusLabels = [
    'pendente'                       => __('admin.orders_report.status.pendente', 'Pendente'),
    'processando'                    => __('admin.orders_report.status.processando', 'Processando'),
    'pago'                           => __('admin.orders_report.status.pago', 'Pago'),
    'produto_consolidado'            => __('admin.orders_report.status.produto_consolidado', 'Caixa Fechada'),
    'itens_comprados'                => __('admin.orders_report.status.itens_comprados', 'Itens Comprados'),
    'etiqueta_gerada'                => __('admin.orders_report.status.etiqueta_gerada', 'Etiqueta Gerada'),
    'enviado'                        => __('admin.orders_report.status.etiqueta_gerada', 'Etiqueta Gerada'),
    'em_transporte'                  => __('admin.orders_report.status.em_transporte', 'Em Transporte'),
    'aguardando_liberacao_aduaneira' => __('admin.orders_report.status.aguardando_liberacao_aduaneira', 'Aguardando Liberação Aduaneira'),
    'enviado_ao_destinatario'        => __('admin.orders_report.status.enviado_ao_destinatario', 'Enviado ao Destinatário'),
    'entregue'                       => __('admin.orders_report.status.entregue', 'Entregue'),
    'cancelado'                      => __('admin.orders_report.status.cancelado', 'Cancelado'),
    'carne_pagando'                  => __('admin.orders_report.status.carne_pagando', 'Carnê em Pagamento'),
    'carne_aguardando'               => __('admin.orders_report.status.carne_aguardando', 'Carnê Aguardando'),
];
$statusColors = [
    'pendente'                       => 'warning',
    'processando'                    => 'primary',
    'pago'                           => 'success',
    'produto_consolidado'            => 'dark',
    'itens_comprados'                => 'info',
    'etiqueta_gerada'                => 'primary',
    'enviado'                        => 'primary',
    'em_transporte'                  => 'info',
    'aguardando_liberacao_aduaneira' => 'secondary',
    'enviado_ao_destinatario'        => 'info',
    'entregue'                       => 'success',
    'cancelado'                      => 'danger',
    'carne_pagando'                  => 'purple',
    'carne_aguardando'               => 'secondary',
];
?>

<?php $selectedStatuses = is_array($statusFilter) ? $statusFilter : ($statusFilter !== '' ? [$statusFilter] : []); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title"><?= __('admin.orders_report.title', 'Relatório de Pedidos') ?></h1>
            <p class="page-subtitle"><?= __('admin.orders_report.subtitle', 'Análise detalhada dos pedidos') ?></p>
        </div>
        <button class="btn btn-outline-dark" onclick="imprimirTodos()"><i class="fas fa-print me-1"></i><?= __('admin.orders_report.print_all', 'Imprimir Tudo') ?></button>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.orders_report.date_start', 'Data Inicial') ?></label>
                    <input type="date" name="date_start" class="form-control" value="<?= htmlspecialchars($dateStart) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.orders_report.date_end', 'Data Final') ?></label>
                    <input type="date" name="date_end" class="form-control" value="<?= htmlspecialchars($dateEnd) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= __('common.status', 'Status') ?></label>
                    <div class="dropdown w-100">
                        <button class="btn btn-outline-secondary w-100 text-start d-flex justify-content-between align-items-center" type="button" id="statusDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <span id="statusLabel"><?= empty($selectedStatuses) ? __('admin.orders_report.all', 'Todos') : count($selectedStatuses) . ' ' . __('admin.orders_report.selected_count', 'selecionado(s)') ?></span>
                            <i class="fas fa-chevron-down ms-2"></i>
                        </button>
                        <div class="dropdown-menu w-100 p-2 shadow" style="max-height:280px;overflow-y:auto;" aria-labelledby="statusDropdown">
                            <div class="mb-2 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary flex-fill" onclick="toggleAllStatus(true)"><?= __('admin.orders_report.all', 'Todos') ?></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" onclick="toggleAllStatus(false)"><?= __('admin.orders_report.clear', 'Limpar') ?></button>
                            </div>
                            <?php
                            $selectedStatuses = is_array($statusFilter) ? $statusFilter : ($statusFilter !== '' ? [$statusFilter] : []);
                            foreach ($statusList as $s):
                                if (in_array($s, ['carne_pagando','carne_aguardando'], true)) continue;
                                $checked = in_array($s, $selectedStatuses, true) ? 'checked' : '';
                                $label = htmlspecialchars($statusLabels[$s] ?? ucfirst(str_replace('_', ' ', $s)));
                            ?>
                            <label class="dropdown-item d-flex align-items-center gap-2 rounded px-2 py-1" style="cursor:pointer;font-size:0.9rem;">
                                <input type="checkbox" name="status[]" value="<?= htmlspecialchars($s) ?>" <?= $checked ?> class="form-check-input m-0 status-cb" onchange="updateStatusLabel()">
                                <?= $label ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i><?= __('admin.orders_report.filter', 'Filtrar') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="small text-muted mb-3"><?= count($pedidos) ?> <?= __('admin.orders_report.orders_found', 'pedido(s) encontrado(s)') ?></div>

    <?php if (empty($pedidos)): ?>
        <div class="alert alert-info"><?= __('admin.orders_report.none_in_period', 'Nenhum pedido encontrado no período selecionado.') ?></div>
    <?php else: ?>
        <?php foreach ($pedidos as $p):
            $pid = (int)$p['id'];
            $st = strtolower(trim((string)($p['status'] ?? '')));
            $cor = $statusColors[$st] ?? 'secondary';
            $label = $statusLabels[$st] ?? ucfirst($st);
            $itens = $itensPorPedido[$pid] ?? [];
            $tracking = $rastreioPorPedido[$pid] ?? '';
            $moeda = strtoupper(trim((string)($p['moeda'] ?? 'BRL')));
            $imp = $impressoesPorPedido[$pid] ?? ['count'=>0,'by'=>''];
            $fmt = function($v) use ($moeda) {
                return $moeda === 'BRL' ? 'R$ ' . number_format((float)$v, 2, ',', '.') : 'US$ ' . number_format((float)$v, 2, '.', ',');
            };
        ?>
        <div class="card border-0 shadow-sm mb-2" style="<?= ($imp['count'] > 0) ? 'opacity: 0.5;' : '' ?>">
            <div class="card-header py-2 d-flex justify-content-between align-items-center" style="cursor:pointer;background:<?= $cor === 'purple' ? '#6f42c1' : '' ?>;" data-bs-toggle="collapse" data-bs-target="#pedido-<?= $pid ?>">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="badge bg-<?= $cor ?>"><?= $label ?></span>
                    <span class="fw-bold">#<?= str_pad($pid, 6, '0', STR_PAD_LEFT) ?></span>
                    <span class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></span>
                    <span class="fw-semibold"><?= htmlspecialchars($p['cliente_nome'] ?? __('admin.orders_report.no_name', 'Sem nome')) ?></span>
                    <span class="small text-muted"><?= htmlspecialchars($p['cliente_email'] ?? '') ?></span>
                    <span class="fw-bold text-primary"><?= $fmt($p['total'] ?? 0) ?></span>
                </div>
                <div class="d-flex align-items-center gap-1" onclick="event.stopPropagation()">
                    <?php if ($imp['count'] > 0): ?>
                        <span class="badge bg-success small me-1"><?= __('admin.orders_report.printed', 'Impresso') ?> <?= $imp['count'] ?>x <?= __('admin.orders_report.by', 'por') ?> <?= htmlspecialchars($imp['by']) ?></span>
                    <?php else: ?>
                        <span class="badge bg-secondary small me-1"><?= __('admin.orders_report.not_printed', 'Não impresso') ?></span>
                    <?php endif; ?>
                    <a href="/admin/relatorio-pedidos/imprimir/<?= $pid ?>" target="_blank" class="btn btn-sm btn-outline-dark" onclick="return confirmarImpressao(<?= $pid ?>, <?= (int)$imp['count'] ?>, '<?= htmlspecialchars(addslashes($imp['by']), ENT_QUOTES) ?>')"><i class="fas fa-print"></i></a>
                    <a href="/admin/pedidos/detalhes/<?= $pid ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                </div>
            </div>
            <div class="collapse" id="pedido-<?= $pid ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= __('admin.orders_report.customer', 'Cliente') ?></h6>
                            <table class="table table-sm table-bordered">
                                <tr><th style="width:35%"><?= __('admin.orders_report.name', 'Nome') ?></th><td><?= htmlspecialchars($p['cliente_nome'] ?? ($p['u_nome'] ?? '')) ?></td></tr>
                                <tr><th><?= __('admin.orders_report.email', 'Email') ?></th><td><?= htmlspecialchars($p['cliente_email'] ?? ($p['u_email'] ?? '')) ?></td></tr>
                                <tr><th>CPF</th><td><?= htmlspecialchars($p['cliente_cpf'] ?? ($p['cliente_documento'] ?? ($p['u_documento'] ?? ''))) ?></td></tr>
                                <tr><th><?= __('admin.orders_report.phone', 'Telefone') ?></th><td><?= htmlspecialchars($p['cliente_telefone'] ?? ($p['u_telefone'] ?? '')) ?></td></tr>
                                <tr><th><?= __('admin.orders_report.code', 'Código') ?></th><td><?= htmlspecialchars($p['codigo_pedido'] ?? '') ?></td></tr>
                                <?php if ($tracking): ?><tr><th><?= __('admin.orders_report.tracking', 'Rastreio') ?></th><td class="text-danger fw-bold"><?= htmlspecialchars($tracking) ?></td></tr><?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6><?= __('admin.orders_report.financial', 'Financeiro') ?></h6>
                            <table class="table table-sm table-bordered">
                                <tr><th style="width:35%"><?= __('admin.orders_report.subtotal', 'Subtotal') ?></th><td><?= $fmt($p['subtotal'] ?? 0) ?></td></tr>
                                <tr><th><?= __('admin.orders_report.service_fee', 'Taxa Serviço') ?></th><td><?= $fmt($p['servicos'] ?? 0) ?></td></tr>
                                <tr><th><?= __('admin.orders_report.taxes', 'Impostos') ?></th><td><?= $fmt($p['impostos'] ?? 0) ?></td></tr>
                                <tr><th><?= __('admin.orders_report.shipping', 'Frete') ?></th><td><?= ((float)($p['frete'] ?? 0)) <= 0 ? __('admin.orders_report.free', 'Grátis') : $fmt($p['frete']) ?></td></tr>
                                <tr><th><?= __('admin.orders_report.total', 'Total') ?></th><td class="fw-bold"><?= $fmt($p['total'] ?? 0) ?></td></tr>
                                <tr><th><?= __('admin.orders_report.payment', 'Pagamento') ?></th><td><?= htmlspecialchars($p['forma_pagamento'] ?? '') ?> <?= !empty($p['pago_em']) ? '— ' . date('d/m/Y H:i', strtotime($p['pago_em'])) : '' ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <?php if (!empty($itens)): ?>
                    <h6 class="mt-3"><?= __('admin.orders_report.items', 'Itens') ?> (<?= count($itens) ?>)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr><th style="width:60px"><?= __('admin.orders_report.photo', 'Foto') ?></th><th><?= __('admin.orders_report.product', 'Produto') ?></th><th><?= __('admin.orders_report.qty', 'Qtd') ?></th><th><?= __('admin.orders_report.unit_price', 'Preço Unit.') ?></th><th><?= __('admin.orders_report.subtotal', 'Subtotal') ?></th></tr>
                            </thead>
                            <tbody>
                            <?php
                            $pesoTotal = 0;
                            foreach ($itens as $it):
                                $nome = (string)($it['nome_produto'] ?? ($it['produto_nome_db'] ?? ''));
                                $foto = (string)($it['produto_foto'] ?? '');
                                $qtd = (int)($it['quantidade'] ?? 1);
                                $preco = (float)($it['preco_unitario'] ?? 0);
                                $sub = (float)($it['subtotal'] ?? ($preco * $qtd));
                                $peso = (float)($it['peso'] ?? 0);
                                $pesoTotal += $peso * $qtd;
                            ?>
                                <tr>
                                    <td><?= $foto ? '<img src="'.htmlspecialchars($foto).'" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">' : '' ?></td>
                                    <td><?= htmlspecialchars($nome) ?></td>
                                    <td><?= $qtd ?></td>
                                    <td><?= $fmt($preco) ?></td>
                                    <td><?= $fmt($sub) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($p['observacoes'])): ?>
                    <div class="alert alert-secondary small mt-2"><strong><?= __('admin.orders_report.notes', 'Observações') ?>:</strong> <?= htmlspecialchars($p['observacoes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
var ORDERS_REPORT_I18N = {
    all: <?= json_encode(__('admin.orders_report.all', 'Todos'), JSON_UNESCAPED_UNICODE) ?>,
    selected_count: <?= json_encode(__('admin.orders_report.selected_count', 'selecionado(s)'), JSON_UNESCAPED_UNICODE) ?>,
    printed_now: <?= json_encode(__('admin.orders_report.printed_now', '✓ Impresso agora'), JSON_UNESCAPED_UNICODE) ?>,
    reprint_confirm: <?= json_encode(__('admin.orders_report.reprint_confirm', '⚠️ Este pedido já foi impresso {n}x por {by}.\n\nDeseja imprimir novamente?'), JSON_UNESCAPED_UNICODE) ?>,
    none_to_print: <?= json_encode(__('admin.orders_report.none_to_print', 'Nenhum pedido para imprimir.'), JSON_UNESCAPED_UNICODE) ?>
};
function updateStatusLabel() {
    var checked = document.querySelectorAll('.status-cb:checked');
    var label = document.getElementById('statusLabel');
    if (checked.length === 0) {
        label.textContent = ORDERS_REPORT_I18N.all;
    } else if (checked.length === 1) {
        label.textContent = checked[0].parentElement.textContent.trim();
    } else {
        label.textContent = checked.length + ' ' + ORDERS_REPORT_I18N.selected_count;
    }
}
function toggleAllStatus(selectAll) {
    document.querySelectorAll('.status-cb').forEach(function(cb) { cb.checked = selectAll; });
    updateStatusLabel();
}

function registrarImpressao(pedidoId) {
    fetch('/admin/relatorio-pedidos/registrar-impressao', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'pedido_id=' + pedidoId
    }).then(function() {
        // Atualizar UI sem recarregar
        var card = document.querySelector('[data-bs-target="#pedido-' + pedidoId + '"]');
        if (card) {
            var cardContainer = card.closest('.card');
            if (cardContainer) {
                cardContainer.style.opacity = '0.5';
            }
            // Encontrar o badge de impressão e atualizar
            var badges = card.querySelectorAll('.badge.bg-secondary, .badge.bg-success');
            badges.forEach(function(badge) {
                if (badge.textContent.indexOf('impresso') !== -1 || badge.textContent.indexOf('Impresso') !== -1) {
                    badge.className = 'badge bg-success small me-1';
                    badge.textContent = ORDERS_REPORT_I18N.printed_now;
                }
            });
        }
    });
}

function confirmarImpressao(pedidoId, printCount, lastBy) {
    if (printCount > 0) {
        var msg = ORDERS_REPORT_I18N.reprint_confirm.replace('{n}', printCount).replace('{by}', lastBy);
        if (!confirm(msg)) {
            return false;
        }
    }
    registrarImpressao(pedidoId);
    return true;
}

function imprimirTodos() {
    var ids = [];
    document.querySelectorAll('[id^="pedido-"]').forEach(function(el) {
        ids.push(el.id.replace('pedido-', ''));
    });
    if (ids.length === 0) { alert(ORDERS_REPORT_I18N.none_to_print); return; }
    // Abrir todos em sequência
    ids.forEach(function(id) {
        registrarImpressao(id);
    });
    window.open('/admin/relatorio-pedidos/imprimir-lote?ids=' + ids.join(','), '_blank');
}
</script>
