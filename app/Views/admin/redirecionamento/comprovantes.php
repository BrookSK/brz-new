<?php
$sidebarActive = 'redirecionamento-comprovantes';
$title = 'Redirecionamento - Comprovantes';
$comprovantes = is_array($comprovantes ?? null) ? $comprovantes : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Comprovantes</h1>
            <div class="text-muted small">Uploads e anexos (placeholder)</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/comprovantes">Atualizar (em breve)</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h3 class="h5 mb-3">Upload de comprovante</h3>
            <form method="post" action="/admin/redirecionamento/comprovantes" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" disabled>
                        <option>Pagamento inicial</option>
                        <option>Diferença</option>
                        <option>Reembolso</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Arquivo</label>
                    <input class="form-control" type="file" disabled />
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-primary" type="button" disabled>Enviar comprovante (em breve)</button>
                </div>
            </form>
        </div>
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
                            <th>Status</th>
                            <th>Arquivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comprovantes)): ?>
                            <tr>
                                <td colspan="5" class="text-muted text-center">Nenhum comprovante registrado/visível ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($comprovantes as $c): ?>
                                <tr>
                                    <td><?= (int) ($c['id'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($c['tipo'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($c['envio_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($c['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="text-muted small">Link em breve</span>
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
        Esta tela já está criada, mas o fluxo de upload/validação e a persistência do arquivo ainda não foram ligados.
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

