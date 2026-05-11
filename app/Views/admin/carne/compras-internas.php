<?php $title = 'Compras Internas - Carnê Braziliana'; ?>
<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-shopping-basket me-2"></i> Lista de Compras — Carnê</h1>
        <a href="/admin/carnes" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="aguardando_compra" <?= ($_GET['status'] ?? '') === 'aguardando_compra' ? 'selected' : '' ?>>Aguardando Compra</option>
                        <option value="comprado" <?= ($_GET['status'] ?? '') === 'comprado' ? 'selected' : '' ?>>Comprado</option>
                        <option value="recebido" <?= ($_GET['status'] ?? '') === 'recebido' ? 'selected' : '' ?>>Recebido</option>
                        <option value="produto_indisponivel" <?= ($_GET['status'] ?? '') === 'produto_indisponivel' ? 'selected' : '' ?>>Indisponível</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <!-- Mobile: Cards -->
            <div class="d-md-none p-3">
                <?php if (empty($compras)): ?>
                    <div class="text-center text-muted py-4 small">Nenhuma compra interna pendente.</div>
                <?php else: ?>
                    <?php foreach ($compras as $ci): ?>
                    <div class="border rounded p-2 mb-2 d-flex align-items-center gap-2">
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex align-items-center gap-2">
                                <a href="/admin/pedidos/detalhes/<?= $ci['pedido_id'] ?>" class="fw-semibold text-decoration-none" style="font-size:12px;">#<?= $ci['pedido_id'] ?></a>
                                <span class="badge bg-light text-dark border" style="font-size:10px;"><?= $ci['quantidade_parcelas'] ?>x</span>
                            </div>
                            <div class="text-truncate text-muted" style="font-size:11px;"><?= htmlspecialchars($ci['cliente_nome']) ?></div>
                            <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                <span class="badge bg-info" style="font-size:9px;"><?= ucfirst(str_replace('_', ' ', $ci['status'])) ?></span>
                                <span class="badge bg-<?= ($ci['status_primeira_parcela'] ?? '') === 'paga' ? 'success' : 'warning' ?>" style="font-size:9px;">1ª <?= ucfirst(str_replace('_', ' ', $ci['status_primeira_parcela'] ?? 'pendente')) ?></span>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                            <div class="btn-group btn-group-sm">
                                <a href="/admin/carnes/detalhes/<?= $ci['carne_id'] ?>" class="btn btn-outline-primary py-0 px-1"><i class="fas fa-eye"></i></a>
                                <?php if ($ci['status'] === 'aguardando_compra'): ?>
                                <form method="POST" action="/admin/carnes/marcar-comprado/<?= $ci['id'] ?>" class="d-inline">
                                    <button type="submit" class="btn btn-outline-success py-0 px-1"><i class="fas fa-check"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if ($ci['status'] !== 'aguardando_compra'): ?>
                                <form method="POST" action="/admin/carnes/desfazer-compra/<?= $ci['id'] ?>" class="d-inline">
                                    <button type="submit" class="btn btn-outline-warning py-0 px-1" onclick="return confirm('Reverter para aguardando compra?')"><i class="fas fa-undo"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Desktop: Table -->
            <div class="d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Pedido</th><th class="d-none d-md-table-cell">Cliente</th><th class="d-none d-lg-table-cell">Total Carnê</th><th>Parcelas</th><th class="d-none d-lg-table-cell">Status Carnê</th><th class="d-none d-md-table-cell">1ª Parcela</th><th>Status</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($compras)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-3">Nenhuma compra interna pendente.</td></tr>
                        <?php else: ?>
                            <?php foreach ($compras as $ci): ?>
                            <tr>
                                <td><a href="/admin/pedidos/detalhes/<?= $ci['pedido_id'] ?>">#<?= $ci['pedido_id'] ?></a></td>
                                <td class="d-none d-md-table-cell"><?= htmlspecialchars($ci['cliente_nome']) ?></td>
                                <td class="d-none d-lg-table-cell">R$ <?= number_format($ci['total_geral'], 2, ',', '.') ?></td>
                                <td><?= $ci['quantidade_parcelas'] ?>x</td>
                                <td class="d-none d-lg-table-cell"><span class="badge bg-primary"><?= ucfirst(str_replace('_', ' ', $ci['carne_status'])) ?></span></td>
                                <td class="d-none d-md-table-cell"><span class="badge bg-<?= ($ci['status_primeira_parcela'] ?? '') === 'paga' ? 'success' : 'warning' ?>"><?= ucfirst(str_replace('_', ' ', $ci['status_primeira_parcela'] ?? 'pendente')) ?></span></td>
                                <td><span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $ci['status'])) ?></span></td>
                                <td>
                                    <a href="/admin/carnes/detalhes/<?= $ci['carne_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                    <?php if ($ci['status'] === 'aguardando_compra'): ?>
                                        <form method="POST" action="/admin/carnes/marcar-comprado/<?= $ci['id'] ?>" class="d-inline">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar Comprado"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($ci['status'] !== 'aguardando_compra'): ?>
                                        <form method="POST" action="/admin/carnes/desfazer-compra/<?= $ci['id'] ?>" class="d-inline">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Desfazer" onclick="return confirm('Reverter para aguardando compra?')"><i class="fas fa-undo"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
