<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <?php $activePage = 'tickets'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4 user-page-header">
                <div>
                    <h2 class="mb-1">Meus Tickets</h2>
                    <p class="text-muted mb-0">Acompanhe suas conversas de suporte</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($tickets)): ?>
                        <div class="text-center py-5">
                            <div class="bg-light rounded-circle p-4 d-inline-block mb-3">
                                <i class="fas fa-life-ring text-muted fs-2"></i>
                            </div>
                            <h5 class="mb-2">Nenhum ticket</h5>
                            <p class="text-muted mb-0">Abra um ticket a partir de um pedido em “Meus Pedidos”.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
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
                                            <td><?= htmlspecialchars((string) ($t['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="badge <?= $badge ?>"><?= $st === 'open' ? 'Aberto' : 'Fechado' ?></span></td>
                                            <td class="text-muted small"><?= htmlspecialchars((string) ($t['updated_at'] ?? $t['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-primary" href="/meu-ticket/<?= (int) ($t['id'] ?? 0) ?>">
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
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
