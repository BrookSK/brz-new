<?php $title = 'Compras do Carnê'; ?>
<?php ob_start(); ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-shopping-basket me-2"></i>Compras do Carnê</h2>
            <p class="text-muted mb-0">Produtos de pedidos via carnê agrupados por mês</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/carnes/compras-mensal" class="btn btn-outline-secondary btn-sm"><i class="fas fa-calendar-alt me-1"></i>Visão Mensal</a>
            <a href="/admin/carnes/compras-internas" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>Lista Completa</a>
            <a href="/admin/carnes" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm border-left-primary">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs text-uppercase text-muted fw-bold">Total Itens</div>
                            <div class="h4 mb-0 fw-bold"><?= (int) ($stats['total'] ?? 0) ?></div>
                        </div>
                        <i class="fas fa-boxes fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm border-left-warning">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs text-uppercase text-muted fw-bold">Aguardando</div>
                            <div class="h4 mb-0 fw-bold text-warning"><?= (int) ($stats['aguardando'] ?? 0) ?></div>
                        </div>
                        <i class="fas fa-clock fa-2x text-warning opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm border-left-success">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs text-uppercase text-muted fw-bold">Comprados</div>
                            <div class="h4 mb-0 fw-bold text-success"><?= (int) ($stats['comprado'] ?? 0) ?></div>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm border-left-info">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs text-uppercase text-muted fw-bold">Recebidos</div>
                            <div class="h4 mb-0 fw-bold text-info"><?= (int) ($stats['recebido'] ?? 0) ?></div>
                        </div>
                        <i class="fas fa-box-open fa-2x text-info opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtro -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Status da Compra</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="aguardando_compra" <?= ($filtroStatus ?? '') === 'aguardando_compra' ? 'selected' : '' ?>>Aguardando Compra</option>
                        <option value="comprado" <?= ($filtroStatus ?? '') === 'comprado' ? 'selected' : '' ?>>Comprado</option>
                        <option value="recebido" <?= ($filtroStatus ?? '') === 'recebido' ? 'selected' : '' ?>>Recebido</option>
                        <option value="produto_indisponivel" <?= ($filtroStatus ?? '') === 'produto_indisponivel' ? 'selected' : '' ?>>Indisponível</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

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
                            $pendentes = count(array_filter($itens, fn($i) => ($i['status_compra'] ?? '') === 'aguardando_compra'));
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
            $pendMes = count(array_filter($itens, fn($i) => ($i['status_compra'] ?? '') === 'aguardando_compra'));
            $compradoMes = count(array_filter($itens, fn($i) => ($i['status_compra'] ?? '') === 'comprado'));
            $recebidoMes = count(array_filter($itens, fn($i) => ($i['status_compra'] ?? '') === 'recebido'));
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
                    <?php if ($recebidoMes > 0): ?>
                        <span class="badge bg-info"><?= $recebidoMes ?> recebidos</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Produto</th>
                                <th>Cliente</th>
                                <th>Carnê (Parcelas)</th>
                                <th>Status Carnê</th>
                                <th>Início</th>
                                <th>Fim Estimado</th>
                                <th>Status Compra</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $ci): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php
                                            $img = $ci['produto_imagem'] ?? $ci['produto_foto'] ?? '';
                                            if ($img && !str_starts_with($img, 'http')) $img = '/uploads/produtos/' . $img;
                                        ?>
                                        <?php if ($img): ?>
                                            <img src="<?= htmlspecialchars($img) ?>" alt="" class="rounded" style="width:36px;height:36px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:36px;height:36px;"><i class="fas fa-box text-muted"></i></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold small"><?= htmlspecialchars($ci['produto_nome'] ?? 'Produto #' . ($ci['produto_id'] ?? '?')) ?></div>
                                            <div class="text-muted" style="font-size:11px;">Qtd: <?= (int) ($ci['quantidade'] ?? 1) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small"><?= htmlspecialchars($ci['cliente_nome'] ?? '') ?></div>
                                    <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($ci['cliente_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?= (int) ($ci['parcelas_pagas'] ?? 0) ?>/<?= (int) ($ci['quantidade_parcelas'] ?? 0) ?> pagas
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        $carneStatus = $ci['carne_status'] ?? '';
                                        $carneStatusClass = 'secondary';
                                        if ($carneStatus === 'ativo') $carneStatusClass = 'primary';
                                        elseif ($carneStatus === 'quitado') $carneStatusClass = 'success';
                                        elseif ($carneStatus === 'cancelado') $carneStatusClass = 'danger';
                                        elseif ($carneStatus === 'atrasado') $carneStatusClass = 'warning';
                                    ?>
                                    <span class="badge bg-<?= $carneStatusClass ?>"><?= ucfirst(str_replace('_', ' ', $carneStatus)) ?></span>
                                </td>
                                <td class="small text-muted"><?= !empty($ci['data_inicio']) ? date('d/m/Y', strtotime($ci['data_inicio'])) : '-' ?></td>
                                <td class="small text-muted"><?= !empty($ci['data_fim_estimada']) ? date('d/m/Y', strtotime($ci['data_fim_estimada'])) : '-' ?></td>
                                <td>
                                    <?php
                                        $statusCompra = $ci['status_compra'] ?? '';
                                        $statusCompraClass = 'warning';
                                        if ($statusCompra === 'comprado') $statusCompraClass = 'success';
                                        elseif ($statusCompra === 'recebido') $statusCompraClass = 'info';
                                        elseif ($statusCompra === 'produto_indisponivel') $statusCompraClass = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $statusCompraClass ?>"><?= ucfirst(str_replace('_', ' ', $statusCompra)) ?></span>
                                    <?php if (!empty($ci['comprado_em'])): ?>
                                        <div class="text-muted" style="font-size:10px;"><?= date('d/m/Y', strtotime($ci['comprado_em'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <!-- Ver detalhes do carnê -->
                                        <a href="/admin/carnes/detalhes/<?= (int) ($ci['carne_id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm" title="Ver carnê"><i class="fas fa-eye"></i></a>

                                        <!-- Ver pedido -->
                                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Ver pedido" data-bs-toggle="modal" data-bs-target="#modalPedido_<?= (int) ($ci['id'] ?? 0) ?>"><i class="fas fa-file-alt"></i></button>

                                        <!-- Marcar como comprado -->
                                        <?php if ($statusCompra === 'aguardando_compra'): ?>
                                            <button type="button" class="btn btn-outline-success btn-sm" title="Marcar comprado" data-bs-toggle="modal" data-bs-target="#modalComprar_<?= (int) ($ci['id'] ?? 0) ?>"><i class="fas fa-check"></i></button>
                                        <?php endif; ?>

                                        <!-- Desfazer -->
                                        <?php if ($statusCompra !== 'aguardando_compra'): ?>
                                            <form method="POST" action="/admin/carnes/desfazer-compra/<?= (int) $ci['id'] ?>" class="d-inline">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Desfazer" onclick="return confirm('Reverter para aguardando compra?')"><i class="fas fa-undo"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal: Ver Pedido/Carnê -->
                            <div class="modal fade" id="modalPedido_<?= (int) ($ci['id'] ?? 0) ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Detalhes do Pedido/Carnê</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <table class="table table-sm">
                                                <tr><th>Pedido</th><td><a href="/admin/pedidos/detalhes/<?= (int) $ci['pedido_id'] ?>">#<?= (int) $ci['pedido_id'] ?></a></td></tr>
                                                <tr><th>Cliente</th><td><?= htmlspecialchars($ci['cliente_nome'] ?? '') ?></td></tr>
                                                <tr><th>Produto</th><td><?= htmlspecialchars($ci['produto_nome'] ?? 'N/A') ?> (Qtd: <?= (int) ($ci['quantidade'] ?? 1) ?>)</td></tr>
                                                <tr><th>Total Carnê</th><td>R$ <?= number_format((float) ($ci['total_geral'] ?? 0), 2, ',', '.') ?></td></tr>
                                                <tr><th>Parcelas</th><td><?= (int) ($ci['parcelas_pagas'] ?? 0) ?> pagas de <?= (int) ($ci['quantidade_parcelas'] ?? 0) ?></td></tr>
                                                <tr><th>Status Carnê</th><td><span class="badge bg-<?= $carneStatusClass ?>"><?= ucfirst(str_replace('_', ' ', $carneStatus)) ?></span></td></tr>
                                                <tr><th>Início</th><td><?= !empty($ci['data_inicio']) ? date('d/m/Y', strtotime($ci['data_inicio'])) : '-' ?></td></tr>
                                                <tr><th>Fim Estimado</th><td><?= !empty($ci['data_fim_estimada']) ? date('d/m/Y', strtotime($ci['data_fim_estimada'])) : '-' ?></td></tr>
                                                <tr><th>Status Compra</th><td><span class="badge bg-<?= $statusCompraClass ?>"><?= ucfirst(str_replace('_', ' ', $statusCompra)) ?></span></td></tr>
                                                <?php if (!empty($ci['comprado_em'])): ?>
                                                    <tr><th>Comprado em</th><td><?= date('d/m/Y H:i', strtotime($ci['comprado_em'])) ?></td></tr>
                                                <?php endif; ?>
                                                <?php if (!empty($ci['recebido_em'])): ?>
                                                    <tr><th>Recebido em</th><td><?= date('d/m/Y H:i', strtotime($ci['recebido_em'])) ?></td></tr>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="/admin/carnes/detalhes/<?= (int) ($ci['carne_id'] ?? 0) ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye me-1"></i>Ver Carnê Completo</a>
                                            <a href="/admin/pedidos/detalhes/<?= (int) $ci['pedido_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-external-link-alt me-1"></i>Ver Pedido</a>
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal: Marcar como Comprado -->
                            <?php if ($statusCompra === 'aguardando_compra'): ?>
                            <div class="modal fade" id="modalComprar_<?= (int) ($ci['id'] ?? 0) ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="fas fa-check-circle me-2 text-success"></i>Confirmar Compra</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Deseja marcar o produto como <strong>comprado</strong>?</p>
                                            <div class="bg-light rounded p-3 mb-3">
                                                <div class="fw-semibold"><?= htmlspecialchars($ci['produto_nome'] ?? 'Produto') ?></div>
                                                <div class="small text-muted">Quantidade: <?= (int) ($ci['quantidade'] ?? 1) ?></div>
                                                <div class="small text-muted">Cliente: <?= htmlspecialchars($ci['cliente_nome'] ?? '') ?></div>
                                                <div class="small text-muted">Pedido: #<?= (int) $ci['pedido_id'] ?></div>
                                            </div>
                                            <div class="alert alert-info small mb-0">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Esta ação marcará o item como comprado e registrará a data no sistema.
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                            <form method="POST" action="/admin/carnes/marcar-comprado/<?= (int) $ci['id'] ?>" class="d-inline">
                                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Confirmar Compra</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
