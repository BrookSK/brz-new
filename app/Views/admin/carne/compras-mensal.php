<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Compras Carnê — Mensal</h2>
        <p class="text-muted mb-0">Lista de compras de carnê separada por mês</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/carnes/compras-internas" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>Lista Completa</a>
        <a href="/admin/carnes" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </div>
</div>

<!-- Filtro -->
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
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Resumo por mês -->
<?php if (empty($porMes)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-inbox fs-2 mb-2 d-block"></i>
            Nenhuma compra de carnê encontrada.
        </div>
    </div>
<?php else: ?>
    <!-- Tabs de meses -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body pb-0">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez']; ?>
                <?php foreach ($porMes as $mesKey => $itens): ?>
                    <?php
                        $parts = explode('-', $mesKey);
                        $mesLabel = ($meses[$parts[1]] ?? $parts[1]) . '/' . $parts[0];
                        $totalItens = count($itens);
                        $pendentes = count(array_filter($itens, fn($i) => ($i['status'] ?? '') === 'aguardando_compra'));
                    ?>
                    <a href="#mes_<?= $mesKey ?>" class="btn btn-sm <?= $pendentes > 0 ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        <?= $mesLabel ?>
                        <span class="badge bg-light text-dark ms-1"><?= $totalItens ?></span>
                        <?php if ($pendentes > 0): ?>
                            <span class="badge bg-warning text-dark ms-1"><?= $pendentes ?> pend.</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tabelas por mês -->
    <?php foreach ($porMes as $mesKey => $itens):
        $parts = explode('-', $mesKey);
        $mesLabel = ($meses[$parts[1]] ?? $parts[1]) . '/' . $parts[0];
        $totalMes = count($itens);
        $pendMes = count(array_filter($itens, fn($i) => ($i['status'] ?? '') === 'aguardando_compra'));
        $compradoMes = count(array_filter($itens, fn($i) => ($i['status'] ?? '') === 'comprado'));
    ?>
    <div class="card border-0 shadow-sm mb-4" id="mes_<?= $mesKey ?>">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="fw-semibold">
                <i class="fas fa-calendar me-2"></i><?= $mesLabel ?>
                <span class="badge bg-light text-dark ms-2"><?= $totalMes ?> itens</span>
            </div>
            <div class="d-flex gap-2">
                <?php if ($pendMes > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $pendMes ?> aguardando</span>
                <?php endif; ?>
                <?php if ($compradoMes > 0): ?>
                    <span class="badge bg-success"><?= $compradoMes ?> comprados</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Total Carnê</th>
                            <th>Parcelas</th>
                            <th>Status Carnê</th>
                            <th>1ª Parcela</th>
                            <th>Status Compra</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $ci): ?>
                        <tr>
                            <td><a href="/admin/pedidos/detalhes/<?= (int) $ci['pedido_id'] ?>">#<?= (int) $ci['pedido_id'] ?></a></td>
                            <td><?= htmlspecialchars($ci['cliente_nome'] ?? '') ?></td>
                            <td>R$ <?= number_format((float) ($ci['total_geral'] ?? 0), 2, ',', '.') ?></td>
                            <td><?= (int) ($ci['quantidade_parcelas'] ?? 0) ?>x</td>
                            <td><span class="badge bg-primary"><?= ucfirst(str_replace('_', ' ', $ci['carne_status'] ?? '')) ?></span></td>
                            <td><span class="badge bg-<?= ($ci['status_primeira_parcela'] ?? '') === 'paga' ? 'success' : 'warning' ?>"><?= ucfirst(str_replace('_', ' ', $ci['status_primeira_parcela'] ?? 'pendente')) ?></span></td>
                            <td><span class="badge bg-<?= ($ci['status'] ?? '') === 'comprado' ? 'success' : (($ci['status'] ?? '') === 'recebido' ? 'info' : 'warning') ?>"><?= ucfirst(str_replace('_', ' ', $ci['status'] ?? '')) ?></span></td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($ci['created_at'] ?? '')) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="/admin/carnes/detalhes/<?= (int) ($ci['carne_id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm" title="Ver carnê"><i class="fas fa-eye"></i></a>
                                    <?php if (($ci['status'] ?? '') === 'aguardando_compra'): ?>
                                        <form method="POST" action="/admin/carnes/compras-internas/comprado/<?= (int) $ci['id'] ?>" class="d-inline">
                                            <button type="submit" class="btn btn-success btn-sm" title="Marcar comprado"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
