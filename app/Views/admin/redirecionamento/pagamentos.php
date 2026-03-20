<?php
$sidebarActive = 'redirecionamento-pagamentos';
$title = 'Redirecionamento - Pagamentos';
$pagamentos = is_array($pagamentos ?? null) ? $pagamentos : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Pagamentos</h1>
            <div class="text-muted small">Histórico e status (placeholder)</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/pagamentos">Exportar (em breve)</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Envio</th>
                            <th>Moeda</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagamentos)): ?>
                            <tr>
                                <td colspan="7" class="text-muted text-center">Nenhum pagamento registrado/visível ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pagamentos as $p): ?>
                                <tr>
                                    <td><?= (int) ($p['id'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($p['tipo'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($p['envio_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($p['moeda'] ?? 'USD'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>US$ <?= number_format((float) ($p['valor_usd'] ?? $p['valor'] ?? 0), 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars((string) ($p['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($p['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        Quando houver integração com Stripe, aqui vamos mostrar pagamentos de envio inicial e diferenças.
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

