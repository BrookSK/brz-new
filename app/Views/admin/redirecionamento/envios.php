<?php
$sidebarActive = 'redirecionamento-envios';
$title = 'Redirecionamento - Envios';
$envios = is_array($envios ?? null) ? $envios : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Envios de Redirecionadores</h1>
            <div class="text-muted small">Tela operacional (placeholder)</div>
        </div>
        <div class="btn-group">
            <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/envios">Listagem</a>
            <a class="btn btn-sm btn-primary" href="/admin/redirecionamento/envios">Novo envio (em breve)</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="/admin/redirecionamento/envios" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Todos</option>
                        <option>rascunho</option>
                        <option>pago</option>
                        <option>etiqueta_gerada</option>
                        <option>coletado</option>
                        <option>entregue</option>
                        <option>divergencia</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Redirecionador</label>
                    <select class="form-select" name="redirecionador_id">
                        <option value="">Todos</option>
                        <option value="1">Redirecionador #1</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data</label>
                    <input class="form-control" type="date" name="data" />
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-filter me-2"></i>Filtrar
                    </button>
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
                            <th>ID interno</th>
                            <th>ID pedido (site do cliente)</th>
                            <th>Redirecionador</th>
                            <th>Cliente final</th>
                            <th>Peso informado vs peso real</th>
                            <th>Status pagamento</th>
                            <th>Status etiqueta</th>
                            <th>Código de rastreio</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($envios)): ?>
                            <tr>
                                <td colspan="9" class="text-muted text-center">Nenhum envio encontrado ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($envios as $e): ?>
                                <tr>
                                    <td><?= (int) ($e['id'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($e['id_pedido_cliente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($e['redirecionador_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($e['peso_info'] ?? '-') . ' / ' . ($e['peso_real'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($e['status_pagamento'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($e['status_etiqueta'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($e['tracking'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/envios">Ver detalhes</a>
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
        Nesta fase, a tela é estrutural. No próximo passo, conectamos a listagem ao banco e implementamos as ações.
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

