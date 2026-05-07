<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-gift me-2"></i>Histórico de Brindes</h1>
</div>

<!-- Estatísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <h4 class="mb-0 text-primary"><?= $stats['total_configurados'] ?></h4>
                <small class="text-muted">Brindes Configurados</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <h4 class="mb-0 text-success"><?= $stats['total_vendas'] ?></h4>
                <small class="text-muted">Vendas com Brinde</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <h4 class="mb-0 text-info">US$ <?= number_format($stats['valor_devolvido'], 2) ?></h4>
                <small class="text-muted">Impostos Devolvidos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <h4 class="mb-0 text-warning">US$ <?= number_format($stats['valor_pendente'], 2) ?></h4>
                <small class="text-muted">Devoluções Pendentes</small>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'configurados' ? 'active' : '' ?>" href="/admin/brindes?tab=configurados">
            <i class="fas fa-cog me-1"></i>Configurados
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'vendas' ? 'active' : '' ?>" href="/admin/brindes?tab=vendas">
            <i class="fas fa-shopping-cart me-1"></i>Vendas com Brinde
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'devolucoes' ? 'active' : '' ?>" href="/admin/brindes?tab=devolucoes">
            <i class="fas fa-wallet me-1"></i>Devoluções
        </a>
    </li>
</ul>

<?php if ($tab === 'configurados'): ?>
<!-- Tab: Brindes Configurados -->
<div class="card">
    <div class="card-header"><strong>Brindes Configurados (Histórico)</strong></div>
    <div class="card-body p-0">
        <?php if (empty($brindesConfigurados)): ?>
            <div class="p-4 text-muted text-center">Nenhum brinde configurado ainda.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Produto Principal</th>
                        <th>Brinde</th>
                        <th>Período</th>
                        <th>Status</th>
                        <th>Criado por</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $now = date('Y-m-d H:i:s');
                foreach ($brindesConfigurados as $b):
                    $ativoAgora = ($b['ativo'] && $b['data_inicio'] <= $now && $b['data_fim'] >= $now);
                    $expirado = ($b['data_fim'] < $now);
                ?>
                    <tr class="<?= $ativoAgora ? 'table-success' : ($expirado ? 'table-secondary' : '') ?>">
                        <td>
                            <strong><?= htmlspecialchars($b['produto_principal_nome'] ?: 'Produto #' . $b['produto_id']) ?></strong>
                            <br><small class="text-muted">ID: <?= $b['produto_id'] ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['brinde_nome'] ?: 'Produto #' . $b['brinde_produto_id']) ?>
                            <br><small class="text-muted">ID: <?= $b['brinde_produto_id'] ?></small>
                        </td>
                        <td>
                            <small><?= date('d/m/Y H:i', strtotime($b['data_inicio'])) ?></small><br>
                            <small>até <?= date('d/m/Y H:i', strtotime($b['data_fim'])) ?></small>
                        </td>
                        <td>
                            <?php if ($ativoAgora): ?>
                                <span class="badge bg-success">Ativo</span>
                            <?php elseif ($expirado): ?>
                                <span class="badge bg-secondary">Expirado</span>
                            <?php elseif (!$b['ativo']): ?>
                                <span class="badge bg-danger">Desativado</span>
                            <?php else: ?>
                                <span class="badge bg-info">Agendado</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= htmlspecialchars($b['criado_por_nome'] ?? '-') ?></small></td>
                        <td><small><?= date('d/m/Y', strtotime($b['created_at'])) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'vendas'): ?>
<!-- Tab: Vendas com Brinde -->
<div class="card">
    <div class="card-header"><strong>Pedidos com Brinde</strong></div>
    <div class="card-body p-0">
        <?php if (empty($vendasBrinde)): ?>
            <div class="p-4 text-muted text-center">Nenhuma venda com brinde registrada.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pedido</th>
                        <th>Produto Brinde</th>
                        <th>Qtd</th>
                        <th>Cliente</th>
                        <th>Status Pedido</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($vendasBrinde as $v): ?>
                    <tr>
                        <td><a href="/admin/pedidos/detalhes/<?= (int) $v['pedido_id'] ?>" class="fw-bold">#<?= $v['pedido_id'] ?></a></td>
                        <td><?= htmlspecialchars($v['produto_nome'] ?: 'Produto #' . $v['produto_id']) ?></td>
                        <td><?= (int) $v['quantidade'] ?></td>
                        <td>
                            <?= htmlspecialchars($v['cliente_nome'] ?? '') ?>
                            <?php if (!empty($v['cliente_email'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($v['cliente_email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $v['pedido_status'] ?? ''))) ?></span></td>
                        <td><small><?= !empty($v['data_venda']) ? date('d/m/Y H:i', strtotime($v['data_venda'])) : '-' ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'devolucoes'): ?>
<!-- Tab: Devoluções de Impostos -->
<div class="card">
    <div class="card-header"><strong>Devoluções de Impostos (Carteira)</strong></div>
    <div class="card-body p-0">
        <?php if (empty($devolucoes)): ?>
            <div class="p-4 text-muted text-center">Nenhuma devolução registrada.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pedido</th>
                        <th>Produto Brinde</th>
                        <th>Cliente</th>
                        <th>Valor (USD)</th>
                        <th>Status</th>
                        <th>Devolvido em</th>
                        <th>Criado em</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($devolucoes as $d): ?>
                    <tr class="<?= $d['status'] === 'devolvido' ? 'table-success' : ($d['status'] === 'pendente' ? 'table-warning' : 'table-secondary') ?>">
                        <td><a href="/admin/pedidos/detalhes/<?= (int) $d['pedido_id'] ?>" class="fw-bold">#<?= $d['pedido_id'] ?></a></td>
                        <td><?= htmlspecialchars($d['produto_nome'] ?? 'Produto #' . $d['produto_brinde_id']) ?></td>
                        <td>
                            <?= htmlspecialchars($d['cliente_nome'] ?? '') ?>
                            <?php if (!empty($d['cliente_email'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($d['cliente_email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold">US$ <?= number_format((float) $d['valor_imposto_devolvido'], 2) ?></td>
                        <td>
                            <?php if ($d['status'] === 'devolvido'): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Devolvido</span>
                            <?php elseif ($d['status'] === 'pendente'): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pendente</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= ucfirst($d['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= !empty($d['devolvido_em']) ? date('d/m/Y H:i', strtotime($d['devolvido_em'])) : '-' ?></small></td>
                        <td><small><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
