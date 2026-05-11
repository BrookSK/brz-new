<?php $title = 'Compras do Carnê'; ?>
<?php $allModals = []; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-shopping-basket me-2"></i>Compras do Carnê</h2>
            <p class="text-muted mb-0">Produtos de pedidos via carnê agrupados por mês</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/carnes" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center"><div class="fs-3 fw-bold"><?= (int) ($stats['total'] ?? 0) ?></div><div class="text-muted small">Total Itens</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center"><div class="fs-3 fw-bold text-warning"><?= (int) ($stats['aguardando'] ?? 0) ?></div><div class="text-muted small">Aguardando</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center"><div class="fs-3 fw-bold text-success"><?= (int) ($stats['comprado'] ?? 0) ?></div><div class="text-muted small">Comprados</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center"><div class="fs-3 fw-bold text-info"><?= (int) ($stats['recebido'] ?? 0) ?></div><div class="text-muted small">Recebidos</div></div></div></div>
    </div>

    <!-- Filtro -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small">Status da Compra</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="aguardando_compra" <?= ($filtroStatus ?? '') === 'aguardando_compra' ? 'selected' : '' ?>>Aguardando Compra</option>
                        <option value="comprado" <?= ($filtroStatus ?? '') === 'comprado' ? 'selected' : '' ?>>Comprado</option>
                        <option value="recebido" <?= ($filtroStatus ?? '') === 'recebido' ? 'selected' : '' ?>>Recebido</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small">Tipo / 1a Parcela</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="primeira_paga" <?= ($filtroTipo ?? '') === 'primeira_paga' ? 'selected' : '' ?>>1a parcela paga</option>
                        <option value="primeira_pendente" <?= ($filtroTipo ?? '') === 'primeira_pendente' ? 'selected' : '' ?>>1a parcela pendente</option>
                        <option value="quitado" <?= ($filtroTipo ?? '') === 'quitado' ? 'selected' : '' ?>>Quitados</option>
                        <option value="com_atraso" <?= ($filtroTipo ?? '') === 'com_atraso' ? 'selected' : '' ?>>Com atraso</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">Parcelas</label>
                    <select name="parcelas" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php for ($i = 2; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($filtroParcelas ?? '') == $i ? 'selected' : '' ?>><?= $i ?>x</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                    <a href="/admin/carnes/compras" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($porMes)): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="fas fa-inbox fs-2 mb-2 d-block"></i>Nenhuma compra de carnê encontrada.</div></div>
    <?php else: ?>
        <?php $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez']; ?>

        <!-- Tabelas por mês -->
        <?php foreach ($porMes as $mesKey => $itens):
            $parts = explode('-', $mesKey);
            $mesLabel = ($meses[$parts[1]] ?? $parts[1]) . '/' . $parts[0];
        ?>
        <div class="card border-0 shadow-sm mb-4" id="mes_<?= $mesKey ?>">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-calendar me-2"></i><?= $mesLabel ?> <span class="badge bg-light text-dark ms-2"><?= count($itens) ?> itens</span></div>
            <div class="card-body p-0">
                <!-- Mobile: Cards -->
                <div class="d-md-none p-3">
                    <?php foreach ($itens as $ci):
                        $img = trim((string) ($ci['produto_imagem'] ?? ''));
                        if ($img !== '' && !preg_match('#^https?://#i', $img) && $img[0] !== '/') {
                            $img = '/uploads/produtos/' . $img;
                        }
                        $prodNome = (string) ($ci['produto_nome'] ?? ($ci['item_nome'] ?? 'Produto'));
                        $statusCompra = (string) ($ci['status_compra'] ?? 'aguardando_compra');
                        $carneStatus = (string) ($ci['carne_status'] ?? '');
                        $modalId = 'modal_' . (int) ($ci['id'] ?? 0) . '_' . (int) ($ci['produto_id'] ?? 0);
                        $stP1 = strtolower(trim((string) ($ci['status_primeira_parcela'] ?? '')));
                        $p1ProdPago = (int) ($ci['primeira_parcela_produtos_pago'] ?? 0);
                        $p1TaxaPago = (int) ($ci['primeira_parcela_taxas_pago'] ?? 0);
                    ?>
                    <div class="border rounded p-2 mb-2 d-flex align-items-center gap-2">
                        <?php if ($img): ?>
                            <img src="<?= htmlspecialchars($img) ?>" class="rounded border flex-shrink-0" style="width:40px;height:40px;object-fit:cover;" onerror="this.style.display='none'">
                        <?php else: ?>
                            <div class="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;"><i class="fas fa-box text-muted"></i></div>
                        <?php endif; ?>
                        <div class="flex-grow-1" style="min-width:0;overflow:hidden;">
                            <div class="fw-semibold text-truncate" style="font-size:12px;"><?= htmlspecialchars($prodNome) ?></div>
                            <div class="d-flex align-items-center gap-1 flex-wrap" style="font-size:10px;">
                                <span class="text-muted text-truncate" style="max-width:100px;"><?= htmlspecialchars($ci['cliente_nome'] ?? '') ?></span>
                                <span class="badge bg-light text-dark border" style="font-size:9px;"><?= (int) ($ci['parcelas_pagas'] ?? 0) ?>/<?= (int) ($ci['quantidade_parcelas'] ?? 0) ?></span>
                                <?php if ($stP1 === 'paga'): ?><span class="text-success"><i class="fas fa-check-circle"></i></span><?php else: ?><span class="text-danger"><i class="fas fa-times-circle"></i></span><?php endif; ?>
                                <span class="badge bg-<?= $statusCompra === 'comprado' ? 'success' : ($statusCompra === 'recebido' ? 'info' : 'warning') ?>" style="font-size:9px;"><?= ucfirst(str_replace('_', ' ', $statusCompra)) ?></span>
                            </div>
                        </div>
                        <div class="d-flex flex-shrink-0 gap-1">
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>"><i class="fas fa-eye"></i></button>
                            <?php if ($statusCompra === 'aguardando_compra'): ?>
                            <button type="button" class="btn btn-outline-success btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#comprar_<?= $modalId ?>"><i class="fas fa-check"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    $allModals[] = ['id' => $modalId, 'ci' => $ci, 'prodNome' => $prodNome, 'carneStatus' => $carneStatus, 'statusCompra' => $statusCompra];
                    ?>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop: Table -->
                <div class="d-none d-md-block table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Produto</th>
                                <th>Cliente</th>
                                <th>Carnê</th>
                                <th class="d-none d-lg-table-cell">Status Carnê</th>
                                <th class="d-none d-xl-table-cell">Início</th>
                                <th class="d-none d-xl-table-cell">Fim Estimado</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $ci):
                                $img = trim((string) ($ci['produto_imagem'] ?? ''));
                                if ($img !== '' && !preg_match('#^https?://#i', $img) && $img[0] !== '/') {
                                    $img = '/uploads/produtos/' . $img;
                                }
                                $prodNome = (string) ($ci['produto_nome'] ?? ($ci['item_nome'] ?? 'Produto'));
                                $statusCompra = (string) ($ci['status_compra'] ?? 'aguardando_compra');
                                $carneStatus = (string) ($ci['carne_status'] ?? '');
                                $modalId = 'modal_' . (int) ($ci['id'] ?? 0) . '_' . (int) ($ci['produto_id'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($img): ?>
                                            <img src="<?= htmlspecialchars($img) ?>" class="rounded border" style="width:34px;height:34px;object-fit:cover;" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:34px;height:34px;"><i class="fas fa-box text-muted small"></i></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold" style="font-size:12px;"><?= htmlspecialchars($prodNome) ?></div>
                                            <div class="text-muted" style="font-size:10px;">Qtd: <?= (int) ($ci['quantidade'] ?? 1) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small"><?= htmlspecialchars($ci['cliente_nome'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark"><?= (int) ($ci['parcelas_pagas'] ?? 0) ?>/<?= (int) ($ci['quantidade_parcelas'] ?? 0) ?></span>
                                    <?php
                                        $stP1 = strtolower(trim((string) ($ci['status_primeira_parcela'] ?? '')));
                                        $p1ProdPago = (int) ($ci['primeira_parcela_produtos_pago'] ?? 0);
                                        $p1TaxaPago = (int) ($ci['primeira_parcela_taxas_pago'] ?? 0);
                                    ?>
                                    <?php if ($stP1 === 'paga'): ?>
                                        <div style="font-size:10px;" class="text-success"><i class="fas fa-check-circle"></i> 1ª paga</div>
                                    <?php elseif ($p1ProdPago || $p1TaxaPago): ?>
                                        <div style="font-size:10px;" class="text-warning"><i class="fas fa-exclamation-circle"></i> 1ª parcial</div>
                                    <?php else: ?>
                                        <div style="font-size:10px;" class="text-danger"><i class="fas fa-times-circle"></i> 1ª pendente</div>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell"><span class="badge bg-<?= $carneStatus === 'quitado' ? 'success' : ($carneStatus === 'com_atraso' ? 'danger' : 'primary') ?>" style="font-size:10px;"><?= ucfirst(str_replace('_', ' ', $carneStatus)) ?></span></td>
                                <td class="d-none d-xl-table-cell small text-muted"><?= !empty($ci['data_inicio']) ? date('d/m/Y', strtotime($ci['data_inicio'])) : '-' ?></td>
                                <td class="d-none d-xl-table-cell small text-muted"><?= !empty($ci['data_fim_estimada']) ? date('d/m/Y', strtotime($ci['data_fim_estimada'])) : '-' ?></td>
                                <td><span class="badge bg-<?= $statusCompra === 'comprado' ? 'success' : ($statusCompra === 'recebido' ? 'info' : 'warning') ?>" style="font-size:10px;"><?= ucfirst(str_replace('_', ' ', $statusCompra)) ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>" title="Ver detalhes"><i class="fas fa-eye"></i></button>
                                        <?php if ($statusCompra === 'aguardando_compra'): ?>
                                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#comprar_<?= $modalId ?>" title="Marcar comprado"><i class="fas fa-check"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php
                            $allModals[] = ['id' => $modalId, 'ci' => $ci, 'prodNome' => $prodNome, 'carneStatus' => $carneStatus, 'statusCompra' => $statusCompra];
                            ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modais (fora da tabela) -->
<?php foreach ($allModals as $m): ?>
<!-- Modal Ver Detalhes -->
<div class="modal fade" id="<?= $m['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Detalhes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm mb-0">
                    <tr><th>Pedido</th><td><a href="/admin/pedidos/detalhes/<?= (int) $m['ci']['pedido_id'] ?>">#<?= (int) $m['ci']['pedido_id'] ?></a></td></tr>
                    <tr><th>Cliente</th><td><?= htmlspecialchars($m['ci']['cliente_nome'] ?? '') ?></td></tr>
                    <tr><th>Produto</th><td><?= htmlspecialchars($m['prodNome']) ?> (Qtd: <?= (int) ($m['ci']['quantidade'] ?? 1) ?>)</td></tr>
                    <tr><th>Total Carnê</th><td>R$ <?= number_format((float) ($m['ci']['total_geral'] ?? 0), 2, ',', '.') ?></td></tr>
                    <tr><th>Parcelas</th><td><?= (int) ($m['ci']['parcelas_pagas'] ?? 0) ?> pagas de <?= (int) ($m['ci']['quantidade_parcelas'] ?? 0) ?></td></tr>
                    <tr><th>Status Carnê</th><td><?= ucfirst(str_replace('_', ' ', $m['carneStatus'])) ?></td></tr>
                    <tr><th>Início</th><td><?= !empty($m['ci']['data_inicio']) ? date('d/m/Y', strtotime($m['ci']['data_inicio'])) : '-' ?></td></tr>
                    <tr><th>Fim Estimado</th><td><?= !empty($m['ci']['data_fim_estimada']) ? date('d/m/Y', strtotime($m['ci']['data_fim_estimada'])) : '-' ?></td></tr>
                    <tr><th>Status Compra</th><td><?= ucfirst(str_replace('_', ' ', $m['statusCompra'])) ?></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <a href="/admin/carnes/detalhes/<?= (int) ($m['ci']['carne_id'] ?? 0) ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye me-1"></i>Ver Carnê</a>
                <a href="/admin/pedidos/detalhes/<?= (int) $m['ci']['pedido_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-external-link-alt me-1"></i>Ver Pedido</a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Compra -->
<?php if ($m['statusCompra'] === 'aguardando_compra'): ?>
<div class="modal fade" id="comprar_<?= $m['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2 text-success"></i>Confirmar Compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="bg-light rounded p-3 mb-3">
                    <div class="fw-semibold"><?= htmlspecialchars($m['prodNome']) ?></div>
                    <div class="small text-muted">Quantidade: <?= (int) ($m['ci']['quantidade'] ?? 1) ?> · Cliente: <?= htmlspecialchars($m['ci']['cliente_nome'] ?? '') ?></div>
                    <div class="small text-muted">Pedido #<?= (int) $m['ci']['pedido_id'] ?> · Parcelas: <?= (int) ($m['ci']['parcelas_pagas'] ?? 0) ?>/<?= (int) ($m['ci']['quantidade_parcelas'] ?? 0) ?></div>
                </div>
                <p class="mb-2 fw-semibold">Como deseja marcar?</p>
                <div class="d-flex flex-column gap-2">
                    <form method="POST" action="/admin/carnes/marcar-comprado/<?= (int) $m['ci']['id'] ?>">
                        <button type="submit" class="btn btn-success w-100"><i class="fas fa-check-double me-1"></i>Compra Total (<?= (int) ($m['ci']['quantidade'] ?? 1) ?> un.)</button>
                    </form>
                    <form method="POST" action="/admin/carnes/marcar-comprado/<?= (int) $m['ci']['id'] ?>" class="border rounded p-2 bg-light">
                        <input type="hidden" name="parcial" value="1">
                        <div class="d-flex align-items-center gap-2">
                            <label class="small fw-semibold text-nowrap mb-0">Qtd comprada:</label>
                            <input type="number" name="quantidade_comprada" class="form-control form-control-sm" style="max-width:80px;" min="1" max="<?= (int) ($m['ci']['quantidade'] ?? 1) ?>" value="1" required>
                            <span class="small text-muted text-nowrap">de <?= (int) ($m['ci']['quantidade'] ?? 1) ?></span>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 mt-2"><i class="fas fa-check me-1"></i>Compra Parcial</button>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>
