<?php $demandas = $demandas ?? []; ?>
<div class="container-fluid py-3">
    <h1 class="page-title">Demandas Concluídas</h1>
    <?php if (empty($demandas)): ?>
    <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="fas fa-inbox fs-2 d-block mb-2"></i>Nenhuma demanda concluída ainda.</div></div>
    <?php else: ?>
    <div class="card border-0 shadow-sm"><div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>#</th><th>Título</th><th>Solicitante</th><th>Concluído em</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($demandas as $d): ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($d['bloco1_titulo'] ?? $d['titulo'] ?? '') ?></td>
                <td><?= htmlspecialchars($d['solicitante'] ?? '') ?></td>
                <td><?= $d['concluido_em'] ? date('d/m/Y H:i', strtotime($d['concluido_em'])) : '-' ?></td>
                <td><a href="/admin/demandas/detalhe/<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>Ver</a><?php if ($d['status'] === 'concluido'): ?> <a href="/admin/demandas/pdf/<?= $d['id'] ?>" class="btn btn-sm btn-outline-dark" target="_blank"><i class="fas fa-file-pdf me-1"></i>PDF</a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div></div>
    <?php endif; ?>
</div>
