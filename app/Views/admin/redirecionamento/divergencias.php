<?php
$sidebarActive = 'redirecionamento-divergencias';
$title = 'Redirecionamento - Divergências';
$divergencias = is_array($divergencias ?? null) ? $divergencias : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Divergências e Ajustes</h1>
            <div class="text-muted small">Operação essencial (placeholder)</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/divergencias">Atualizar (em breve)</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Envio</th>
                            <th>Valor pago</th>
                            <th>Valor correto</th>
                            <th>Diferença</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($divergencias)): ?>
                            <tr>
                                <td colspan="6" class="text-muted text-center">Nenhuma divergência encontrada ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($divergencias as $d): ?>
                                <tr>
                                    <td><?= (int) ($d['envio_id'] ?? 0) ?></td>
                                    <td>US$ <?= number_format((float) ($d['valor_pago_usd'] ?? 0), 2, ',', '.') ?></td>
                                    <td>US$ <?= number_format((float) ($d['valor_correto_usd'] ?? 0), 2, ',', '.') ?></td>
                                    <td>
                                        US$ <?= number_format((float) ($d['diferenca_usd'] ?? 0), 2, ',', '.') ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($d['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" type="button" disabled>Gerar link (em breve)</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-warning mt-4 mb-0">
        Fluxos de cobrança/reembolso e uploads de comprovante ainda serão implementados.
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

