<?php
$carnes = $carnes ?? [];
$statusLabels = [
    'cancelado' => ['label' => 'Cancelado', 'cor' => 'secondary'],
    'aguardando_primeira_parcela' => ['label' => 'Aguardando 1ª Parcela', 'cor' => 'info'],
    'ativo' => ['label' => 'Ativo', 'cor' => 'primary'],
    'em_andamento' => ['label' => 'Em Andamento', 'cor' => 'primary'],
    'com_atraso' => ['label' => 'Com Atraso', 'cor' => 'danger'],
    'quitado' => ['label' => 'Quitado', 'cor' => 'success'],
    'inadimplente' => ['label' => 'Inadimplente', 'cor' => 'dark'],
    'encerrado' => ['label' => 'Encerrado', 'cor' => 'secondary'],
];
function fmtBrlArq($v) { return 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.'); }
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-archive me-2 text-muted"></i>Carnês Arquivados</h4>
            <p class="text-muted small mb-0">Carnês cancelados automaticamente (não pagamento da 1ª parcela, atraso, inadimplência)</p>
        </div>
        <a href="/admin/carnes" class="btn btn-outline-dark btn-sm"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
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
            Nenhum carnê arquivado.
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-2">
            <span class="text-muted small"><?= count($carnes) ?> carnê(s) arquivado(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Valor Total</th>
                            <th>Parcelas</th>
                            <th>Status</th>
                            <th>Motivo</th>
                            <th>Cancelado em</th>
                            <th>Ações</th>
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
                            <td class="small text-muted" style="max-width:200px;"><?= htmlspecialchars(mb_strimwidth($c['motivo_cancelamento'] ?? '-', 0, 60, '...')) ?></td>
                            <td class="small"><?= $c['cancelado_em'] ? date('d/m/Y H:i', strtotime($c['cancelado_em'])) : '-' ?></td>
                            <td class="d-flex gap-1">
                                <a href="/admin/carnes/detalhes/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalhes"><i class="fas fa-eye"></i></a>
                                <form method="POST" action="/admin/carnes/arquivar/<?= $c['id'] ?>">
                                    <input type="hidden" name="arquivar" value="0">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Desarquivar"><i class="fas fa-undo"></i></button>
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
