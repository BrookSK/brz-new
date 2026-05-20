<?php $title = 'Log de Emails - Admin'; ?>
<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title">Log de Emails</h1>
        <div class="d-flex gap-2">
            <span class="badge bg-primary fs-6"><?= (int) ($stats['total'] ?? 0) ?> total</span>
            <span class="badge bg-success fs-6"><?= (int) ($stats['enviados'] ?? 0) ?> enviados</span>
            <span class="badge bg-danger fs-6"><?= (int) ($stats['erros'] ?? 0) ?> erros</span>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/emails" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($tipos as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $filtros['filtroTipo'] === $t ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $t))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="enviado" <?= $filtros['filtroStatus'] === 'enviado' ? 'selected' : '' ?>>Enviado</option>
                        <option value="erro" <?= $filtros['filtroStatus'] === 'erro' ? 'selected' : '' ?>>Erro</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Data início</label>
                    <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['filtroDataInicio']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Data fim</label>
                    <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['filtroDataFim']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Buscar (email/assunto)</label>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Email ou assunto..." value="<?= htmlspecialchars($filtros['filtroBusca']) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Destinatário</th>
                            <th>Assunto</th>
                            <th>Status</th>
                            <th>Erro</th>
                            <th>Ref.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum email encontrado.</td></tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap small"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['tipo']))) ?></span></td>
                            <td class="small">
                                <?= htmlspecialchars($log['destinatario_email']) ?>
                                <?php if (!empty($log['destinatario_nome'])): ?>
                                <br><span class="text-muted"><?= htmlspecialchars($log['destinatario_nome']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($log['assunto']) ?></td>
                            <td>
                                <span class="badge bg-<?= $log['status'] === 'enviado' ? 'success' : 'danger' ?>">
                                    <?= $log['status'] === 'enviado' ? '✓ Enviado' : '✗ Erro' ?>
                                </span>
                            </td>
                            <td class="small text-danger">
                                <?php if (!empty($log['erro_mensagem'])): ?>
                                <span title="<?= htmlspecialchars($log['erro_mensagem']) ?>"><?= htmlspecialchars(mb_substr($log['erro_mensagem'], 0, 50)) ?><?= mb_strlen($log['erro_mensagem']) > 50 ? '...' : '' ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <?php if (!empty($log['carne_id'])): ?>
                                <a href="/admin/carnes/detalhes/<?= (int) $log['carne_id'] ?>" class="text-decoration-none">Carnê #<?= (int) $log['carne_id'] ?></a>
                                <?php elseif (!empty($log['pedido_id'])): ?>
                                <a href="/admin/pedidos/detalhes/<?= (int) $log['pedido_id'] ?>" class="text-decoration-none">Pedido #<?= (int) $log['pedido_id'] ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <div class="card-footer">
            <?php
                $paginacao_atual = (int)$pagina;
                $paginacao_total = (int)$totalPaginas;
                $paginacao_url_fn = function($p) use ($filtros) {
                    return '?' . http_build_query(array_merge($filtros, ['pagina' => $p]));
                };
                include __DIR__ . '/../../partials/pagination.php';
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
