<?php $title = 'Carnê Braziliana - Admin'; ?>
<?php ob_start(); ?>
<?php
$statusLabels = [
    'aguardando_primeira_parcela' => ['label' => 'Aguardando 1ª Parcela', 'cor' => 'info'],
    'ativo' => ['label' => 'Ativo', 'cor' => 'primary'],
    'em_andamento' => ['label' => 'Em Andamento', 'cor' => 'primary'],
    'com_atraso' => ['label' => 'Com Atraso', 'cor' => 'danger'],
    'quitado' => ['label' => 'Quitado', 'cor' => 'success'],
    'inadimplente' => ['label' => 'Inadimplente', 'cor' => 'dark'],
    'liberado_envio' => ['label' => 'Liberado p/ Envio', 'cor' => 'success'],
    'encerrado' => ['label' => 'Encerrado', 'cor' => 'secondary'],
];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-file-invoice-dollar me-2"></i> Carnê Braziliana</h1>
        <div>
            <a href="/admin/carnes/compras-internas" class="btn btn-outline-warning"><i class="fas fa-shopping-basket me-1"></i> Compras Internas</a>
            <a href="/admin/carnes/configuracoes" class="btn btn-outline-secondary"><i class="fas fa-cog me-1"></i> Configurações</a>
            <a href="/admin" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Recriar Carnê -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-warning bg-opacity-10">
            <strong><i class="fas fa-plus-circle me-1"></i> Recriar Carnê (pedido sem carnê)</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="/admin/carnes/recriar" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">ID do Pedido</label>
                    <input type="number" name="pedido_id" class="form-control form-control-sm" placeholder="Ex: 715" required min="1">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Quantidade de Parcelas</label>
                    <select name="quantidade_parcelas" class="form-select form-select-sm">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i === 4 ? 'selected' : '' ?>><?= $i ?>x</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Tem certeza que deseja criar o carnê para este pedido?')">
                        <i class="fas fa-plus me-1"></i> Criar Carnê
                    </button>
                </div>
                <div class="col-md-12">
                    <small class="text-muted">Use para pedidos com forma_pagamento = carne_braziliana que não tiveram o carnê criado automaticamente.</small>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($statusLabels as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Cliente</label>
                    <input type="text" name="cliente" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['cliente'] ?? '') ?>" placeholder="Nome ou email">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Pedido #</label>
                    <input type="text" name="pedido_id" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['pedido_id'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <div class="form-check"><input type="checkbox" name="com_atraso" value="1" class="form-check-input" <?= !empty($_GET['com_atraso']) ? 'checked' : '' ?>><label class="form-check-label small">Atraso</label></div>
                </div>
                <div class="col-md-1">
                    <div class="form-check"><input type="checkbox" name="liberado_compra" value="1" class="form-check-input" <?= !empty($_GET['liberado_compra']) ? 'checked' : '' ?>><label class="form-check-label small">Compra</label></div>
                </div>
                <div class="col-md-1">
                    <div class="form-check"><input type="checkbox" name="liberado_envio" value="1" class="form-check-input" <?= !empty($_GET['liberado_envio']) ? 'checked' : '' ?>><label class="form-check-label small">Envio</label></div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th><th>Pedido</th><th>Cliente</th><th>Total</th><th>Parcelas</th>
                            <th>Pagas</th><th>Atraso</th><th>Próx. Venc.</th><th>Status</th><th>Envio</th><th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($carnes)): ?>
                            <tr><td colspan="11" class="text-center text-muted py-3">Nenhum carnê encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($carnes as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td><a href="/admin/pedidos/detalhes/<?= $c['pedido_id'] ?>">#<?= $c['pedido_id'] ?></a></td>
                                <td><?= htmlspecialchars($c['cliente_nome']) ?></td>
                                <td>R$ <?= number_format($c['total_geral'], 2, ',', '.') ?></td>
                                <td><?= $c['quantidade_parcelas'] ?>x</td>
                                <td><?= $c['parcelas_pagas'] ?? 0 ?></td>
                                <td><?php if (($c['parcelas_atrasadas'] ?? 0) > 0): ?><span class="badge bg-danger"><?= $c['parcelas_atrasadas'] ?></span><?php else: ?>-<?php endif; ?></td>
                                <td><?= !empty($c['proximo_vencimento']) ? date('d/m/Y', strtotime($c['proximo_vencimento'])) : '-' ?></td>
                                <td><?php $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'cor' => 'secondary']; ?><span class="badge bg-<?= $st['cor'] ?>"><?= $st['label'] ?></span></td>
                                <td><?= $c['envio_liberado'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-muted"></i>' ?></td>
                                <td><a href="/admin/carnes/detalhes/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
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
