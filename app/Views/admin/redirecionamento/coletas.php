<?php
$sidebarActive = 'redirecionamento-coletas';
$title = 'Redirecionamento - Coletas';
$coletas = is_array($coletas ?? null) ? $coletas : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Coletas</h1>
            <div class="text-muted small">Agenda da Fabiana (placeholder)</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/coletas">Ver agenda (em breve)</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Data sugerida</th>
                            <th>Envio</th>
                            <th>Redirecionador</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coletas)): ?>
                            <tr>
                                <td colspan="5" class="text-muted text-center">Nenhuma coleta agendada/visível ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coletas as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($c['data_sugerida'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int) ($c['envio_id'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($c['redirecionador_nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($c['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-success" type="button" disabled>Marcar como coletado</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        Nesta fase, esta tela serve como base para a agenda e notificações por e-mail.
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

