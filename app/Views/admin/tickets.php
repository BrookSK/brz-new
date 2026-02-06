<?php
$status = strtolower(trim((string) ($_GET['status'] ?? 'open')));
if (!in_array($status, ['open', 'closed', 'all'], true)) {
    $status = 'open';
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Tickets</h1>
        <div class="text-muted small">Atendimento e comunicação pelo site</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets?status=open">Abertos</a>
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets?status=closed">Fechados</a>
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets?status=all">Todos</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($tickets)): ?>
            <div class="text-muted">Nenhum ticket encontrado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Assunto</th>
                            <th>Status</th>
                            <th>Atualizado</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                            <?php
                                $st = (string) ($t['status'] ?? 'open');
                                $badge = ($st === 'open') ? 'bg-success' : 'bg-secondary';
                            ?>
                            <tr>
                                <td class="fw-semibold">#<?= (int) ($t['id'] ?? 0) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($t['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars((string) ($t['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><?= htmlspecialchars((string) ($t['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $badge ?>"><?= $st === 'open' ? 'Aberto' : 'Fechado' ?></span></td>
                                <td class="text-muted small"><?= htmlspecialchars((string) ($t['updated_at'] ?? $t['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/tickets/<?= (int) ($t['id'] ?? 0) ?>">
                                        <i class="fas fa-comments"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
