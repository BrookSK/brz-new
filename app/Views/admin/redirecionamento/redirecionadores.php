<?php
$sidebarActive = 'redirecionamento-redirecionadores';
$title = 'Redirecionamento - Redirecionadores';
$redirecionadores = is_array($redirecionadores ?? null) ? $redirecionadores : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Redirecionadores</h1>
            <div class="text-muted small">Gestão de contas de redirecionamento (placeholder)</div>
        </div>
        <a class="btn btn-sm btn-primary" href="/admin/redirecionamento/redirecionadores">Criar novo (em breve)</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Suite vinculada</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($redirecionadores)): ?>
                            <tr>
                                <td colspan="5" class="text-muted text-center">Nenhum redirecionador cadastrado/visível ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($redirecionadores as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($r['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($r['telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($r['suite'] ?? ($r['suite_id'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($r['status'] ?? 'ativo'), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-warning mt-4 mb-0">
        A criação/edição/bloqueio de redirecionadores ainda não foi implementada neste commit.
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

