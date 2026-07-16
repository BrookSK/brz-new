<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-invoice-dollar me-2"></i>Faturas Adicionais
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#modalNovaFatura">
                <i class="fas fa-plus me-2"></i>Nova Fatura
            </button>
        </div>
    </div>

    <!-- Mensagem Flash -->
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Pedido ID</label>
                    <input type="number" name="pedido_id" class="form-control" value="<?= htmlspecialchars($pedido_id) ?>" placeholder="ID do Pedido">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                    <a href="/admin/faturas-adicionais" class="btn btn-secondary"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <strong><?= $total ?></strong> fatura(s) encontrada(s)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Motivo</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Criada em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($faturas)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma fatura encontrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($faturas as $f): ?>
                                <tr>
                                    <td><strong>#<?= $f['id'] ?></strong></td>
                                    <td>
                                        <a href="/admin/pedidos/<?= $f['pedido_id'] ?>">#<?= $f['pedido_id'] ?></a>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($f['usuario_nome'] ?? '-') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($f['motivo']) ?></td>
                                    <td><strong>US$ <?= number_format((float)$f['valor'], 2, ',', '.') ?></strong></td>
                                    <td>
                                        <?php
                                        $corFatura = ['pendente' => 'warning', 'pago' => 'success', 'cancelado' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?= $corFatura[$f['status']] ?? 'secondary' ?>">
                                            <?= ucfirst($f['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($f['created_at'])) ?></td>
                                    <td>
                                        <?php if ($f['status'] === 'pendente'): ?>
                                        <form method="POST" action="/admin/faturas-adicionais/<?= $f['id'] ?>/cancelar" style="display:inline;" onsubmit="return confirm('Cancelar esta fatura?')">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar">
                                                <i class="fas fa-times"></i>
                                            </button>
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

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&pedido_id=<?= urlencode($pedido_id) ?>&status=<?= urlencode($status) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Modal Nova Fatura -->
<div class="modal fade" id="modalNovaFatura" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/faturas-adicionais/criar">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Fatura Adicional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ID do Pedido *</label>
                        <input type="number" name="pedido_id" class="form-control" required min="1" value="<?= htmlspecialchars($pedido_id) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Motivo *</label>
                        <input type="text" name="motivo" class="form-control" required placeholder="Ex: Taxa adicional, ajuste de peso...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Valor (USD) *</label>
                        <div class="input-group">
                            <span class="input-group-text">US$</span>
                            <input type="number" name="valor" class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3" placeholder="Detalhes adicionais (opcional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Criar Fatura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Faturas Adicionais - Admin';
include __DIR__ . '/../../layouts/admin.php';
?>
