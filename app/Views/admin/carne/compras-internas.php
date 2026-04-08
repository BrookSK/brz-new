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
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Pedido</th><th>Cliente</th><th>Total Carnê</th><th>Parcelas</th><th>Status Carnê</th><th>1ª Parcela</th><th>Status Compra</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($compras)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-3">Nenhuma compra interna pendente.</td></tr>
                        <?php else: ?>
                            <?php foreach ($compras as $ci): ?>
                            <tr>
                                <td><a href="/admin/pedidos/detalhes/<?= $ci['pedido_id'] ?>">#<?= $ci['pedido_id'] ?></a></td>
                                <td><?= htmlspecialchars($ci['cliente_nome']) ?></td>
                                <td>R$ <?= number_format($ci['total_geral'], 2, ',', '.') ?></td>
                                <td><?= $ci['quantidade_parcelas'] ?>x</td>
                                <td><span class="badge bg-primary"><?= ucfirst(str_replace('_', ' ', $ci['carne_status'])) ?></span></td>
                                <td><span class="badge bg-<?= ($ci['status_primeira_parcela'] ?? '') === 'paga' ? 'success' : 'warning' ?>"><?= ucfirst(str_replace('_', ' ', $ci['status_primeira_parcela'] ?? 'pendente')) ?></span></td>
                                <td><span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $ci['status'])) ?></span></td>
                                <td>
                                    <a href="/admin/carnes/detalhes/<?= $ci['carne_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                    <?php if ($ci['status'] === 'aguardando_compra'): ?>
                                        <form method="POST" action="/admin/carnes/marcar-comprado/<?= $ci['id'] ?>" class="d-inline">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar Comprado"><i class="fas fa-check"></i></button>
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

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
