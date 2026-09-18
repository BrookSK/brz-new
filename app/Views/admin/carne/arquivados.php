<?php
$carnes = $carnes ?? [];
$statusLabels = [
    'cancelado' => ['label' => __('admin.carne_archived.status.cancelled', 'Cancelado'), 'cor' => 'secondary'],
    'aguardando_primeira_parcela' => ['label' => __('admin.carne_archived.status.awaiting_first', 'Aguardando 1ª Parcela'), 'cor' => 'info'],
    'ativo' => ['label' => __('admin.carne_archived.status.active', 'Ativo'), 'cor' => 'primary'],
    'em_andamento' => ['label' => __('admin.carne_archived.status.in_progress', 'Em Andamento'), 'cor' => 'primary'],
    'com_atraso' => ['label' => __('admin.carne_archived.status.overdue', 'Com Atraso'), 'cor' => 'danger'],
    'quitado' => ['label' => __('admin.carne_archived.status.paid_off', 'Quitado'), 'cor' => 'success'],
    'inadimplente' => ['label' => __('admin.carne_archived.status.defaulted', 'Inadimplente'), 'cor' => 'dark'],
    'encerrado' => ['label' => __('admin.carne_archived.status.closed', 'Encerrado'), 'cor' => 'secondary'],
];
function fmtBrlArq($v) { return 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.'); }

// Traduz o motivo de cancelamento (gravado em pt-BR no banco) preservando as partes dinâmicas.
$traduzirMotivoCancelamento = function (?string $motivo): string {
    $m = trim((string) $motivo);
    if ($m === '' || $m === '-') return '-';
    if ($m === 'Primeira parcela não paga dentro do prazo') {
        return __('admin.carne_archived.reason.first_unpaid', 'Primeira parcela não paga dentro do prazo');
    }
    if (preg_match('/^Cancelado por atraso de (\d+) dias na parcela #(\d+)$/u', $m, $x)) {
        return __('admin.carne_archived.reason.overdue_days', 'Cancelado por atraso de {days} dias na parcela #{n}', ['days' => $x[1], 'n' => $x[2]]);
    }
    if (preg_match('/^Cancelado automaticamente por inadimplência \((\d+) meses sem pagamento\)$/u', $m, $x)) {
        return __('admin.carne_archived.reason.default_months', 'Cancelado automaticamente por inadimplência ({months} meses sem pagamento)', ['months' => $x[1]]);
    }
    return $m;
};
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-archive me-2 text-muted"></i><?= htmlspecialchars(__('admin.carne_archived.title', 'Carnês Arquivados'), ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="text-muted small mb-0"><?= htmlspecialchars(__('admin.carne_archived.subtitle', 'Carnês cancelados automaticamente (não pagamento da 1ª parcela, atraso, inadimplência)'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a href="/admin/carnes" class="btn btn-outline-dark btn-sm"><i class="fas fa-arrow-left me-1"></i><?= htmlspecialchars(__('common.back', 'Voltar'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if (empty($carnes)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-archive fs-2 d-block mb-2 opacity-50"></i>
            <?= htmlspecialchars(__('admin.carne_archived.empty', 'Nenhum carnê arquivado.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-2">
            <span class="text-muted small"><?= count($carnes) ?> <?= htmlspecialchars(__('admin.carne_archived.count', 'carnê(s) arquivado(s)'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th><?= htmlspecialchars(__('admin.carne_archived.col.order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.carne_archived.col.customer', 'Cliente'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.carne_archived.col.total', 'Valor Total'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.carne_archived.col.installments', 'Parcelas'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('common.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.carne_archived.col.reason', 'Motivo'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('admin.carne_archived.col.cancelled_at', 'Cancelado em'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('common.actions', 'Ações'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($carnes as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td><a href="/admin/pedidos/detalhes/<?= $c['pedido_id'] ?>" class="text-decoration-none">#<?= $c['pedido_id'] ?></a></td>
                            <td><?= htmlspecialchars($c['cliente_nome'] ?? '') ?></td>
                            <td class="fw-semibold"><?= fmtBrlArq($c['total_geral']) ?></td>
                            <td><?= (int)($c['parcelas_pagas'] ?? 0) ?>/<?= (int)($c['quantidade_parcelas'] ?? 0) ?></td>
                            <td><span class="badge bg-<?= $statusLabels[$c['status']]['cor'] ?? 'secondary' ?>"><?= $statusLabels[$c['status']]['label'] ?? $c['status'] ?></span></td>
                            <td class="small text-muted" style="max-width:200px;"><?= htmlspecialchars(mb_strimwidth($traduzirMotivoCancelamento($c['motivo_cancelamento'] ?? '-'), 0, 60, '...')) ?></td>
                            <td class="small"><?= $c['cancelado_em'] ? date('d/m/Y H:i', strtotime($c['cancelado_em'])) : '-' ?></td>
                            <td class="d-flex gap-1">
                                <a href="/admin/carnes/detalhes/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars(__('admin.carne_archived.view_details', 'Ver detalhes'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-eye"></i></a>
                                <form method="POST" action="/admin/carnes/arquivar/<?= $c['id'] ?>">
                                    <input type="hidden" name="arquivar" value="0">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="<?= htmlspecialchars(__('admin.carne_archived.unarchive', 'Desarquivar'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-undo"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
