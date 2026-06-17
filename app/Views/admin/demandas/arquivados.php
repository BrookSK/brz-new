<?php
$demandas = $demandas ?? [];
$statusLabels = $statusLabels ?? ['pendente'=>'Pendente','em_analise'=>'Em Análise','em_execucao'=>'Em Execução','em_teste'=>'Em Teste','recusado'=>'Recusado','concluido'=>'Concluído'];
$statusCores = $statusCores ?? ['pendente'=>'secondary','em_analise'=>'primary','em_execucao'=>'warning','em_teste'=>'info','recusado'=>'danger','concluido'=>'success'];
?>
<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="page-title"><i class="fas fa-archive me-2 text-muted"></i>Demandas Arquivadas</h1>
        <a href="/admin/demandas/painel" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="fas fa-arrow-left me-1"></i>Voltar ao Painel</a>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

    <?php if (empty($demandas)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-archive fs-2 d-block mb-2 opacity-50"></i>
            Nenhuma demanda arquivada.
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Solicitante</th>
                            <th>Status</th>
                            <th>Criada em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($demandas as $d): ?>
                        <tr>
                            <td><?= $d['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($d['bloco1_titulo'] ?? $d['titulo'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['solicitante'] ?? '') ?></td>
                            <td><span class="badge bg-<?= $statusCores[$d['status']] ?? 'secondary' ?>"><?= $statusLabels[$d['status']] ?? $d['status'] ?></span></td>
                            <td><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                            <td class="d-flex gap-1">
                                <a href="/admin/demandas/detalhe/<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                <form method="POST" action="/admin/demandas/arquivar/<?= $d['id'] ?>">
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
