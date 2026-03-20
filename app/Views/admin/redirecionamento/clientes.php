<?php
$sidebarActive = 'redirecionamento-clientes';
$title = 'Redirecionamento - Clientes';
$clientes = is_array($clientes ?? null) ? $clientes : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Clientes dos Redirecionadores</h1>
            <div class="text-muted small">Cadastro de destinatários e endereços (placeholder)</div>
        </div>
        <a class="btn btn-sm btn-primary" href="/admin/redirecionamento/clientes">Cadastrar cliente (em breve)</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Endereços</th>
                            <th>Suite gerada</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="5" class="text-muted text-center">Nenhum cliente cadastrado/visível ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($c['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($c['cpf'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php
                                        $enderecosCount = $c['enderecos_count'] ?? null;
                                        if ($enderecosCount === null) {
                                            $enderecos = $c['enderecos'] ?? null;
                                            $enderecosCount = is_array($enderecos) ? count($enderecos) : '-';
                                        }
                                    ?>
                                    <td><?= htmlspecialchars((string) $enderecosCount, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($c['suite'] ?? ($c['suite_id'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" type="button" disabled>Ver/editar (em breve)</button>
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
        No próximo passo, implementamos cadastro de cliente por step (dados do cliente + múltiplos endereços).
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

