<?php
/**
 * Remessa Internacional - Listagem flat de pedidos com filtros
 * Variáveis disponíveis: $pedidos, $janelas, $stats, $filtros, $usdToBrl
 */
$filtroJanela = $filtros['janela_id'] ?? '';
$filtroStatus = $filtros['janela_status'] ?? '';
$filtroEtiqueta = $filtros['etiqueta'] ?? '';
$filtroBusca = $filtros['busca'] ?? '';
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="page-title">Remessa Internacional</h1>
    <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-info btn-sm" onclick="location.reload()">
            <i class="fas fa-sync me-1"></i>Atualizar
        </button>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
            <div class="text-muted small">Total Pedidos</div>
            <div class="fs-4 fw-bold"><?= (int)($stats['total_pedidos'] ?? 0) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
            <div class="text-muted small">Etiquetas Geradas</div>
            <div class="fs-4 fw-bold text-success"><?= (int)($stats['etiquetas_geradas'] ?? 0) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
            <div class="text-muted small">Pendentes</div>
            <div class="fs-4 fw-bold text-warning"><?= (int)($stats['etiquetas_pendentes'] ?? 0) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
            <div class="text-muted small">Janelas em Atraso</div>
            <div class="fs-4 fw-bold text-danger"><?= (int)($stats['janelas_atraso'] ?? 0) ?></div>
        </div></div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Janela</label>
                <select name="janela_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($janelas as $j): ?>
                    <option value="<?= (int)$j['id'] ?>" <?= $filtroJanela == $j['id'] ? 'selected' : '' ?>>
                        #<?= $j['id'] ?> (<?= date('d/m', strtotime($j['data_inicio'])) ?>-<?= date('d/m', strtotime($j['data_fim'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Status Janela</label>
                <select name="janela_status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="aberta" <?= $filtroStatus === 'aberta' ? 'selected' : '' ?>>Aberta</option>
                    <option value="finalizada" <?= $filtroStatus === 'finalizada' ? 'selected' : '' ?>>Finalizada</option>
                    <option value="atraso" <?= $filtroStatus === 'atraso' ? 'selected' : '' ?>>Em Atraso</option>
                    <option value="remessa_gerada" <?= $filtroStatus === 'remessa_gerada' ? 'selected' : '' ?>>Remessa Gerada</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Etiqueta</label>
                <select name="etiqueta" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <option value="pendente" <?= $filtroEtiqueta === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="gerada" <?= $filtroEtiqueta === 'gerada' ? 'selected' : '' ?>>Gerada</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-0">Buscar</label>
                <input type="text" name="busca" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Pedido # ou cliente...">
            </div>
            <div class="col-6 col-md-2 col-lg-1">
                <a href="/admin/remessa-internacional" class="btn btn-sm btn-outline-secondary w-100">Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Pedidos -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-list me-2"></i>Pedidos (<?= count($pedidos) ?>)</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Entrou na Janela</th>
                        <th>Total</th>
                        <th>Janela</th>
                        <th>Status Janela</th>
                        <th>Etiqueta</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Nenhum pedido encontrado.</td></tr>
                    <?php else: ?>
                    <?php foreach ($pedidos as $p):
                        $pid = (int)($p['pedido_id'] ?? 0);
                        $et = (int)($p['etiqueta_gerada'] ?? 0);
                        $jId = (int)($p['janela_id'] ?? 0);
                        $jStatus = (string)($p['janela_status'] ?? '');
                        $jBadge = match($jStatus) { 'aberta' => 'success', 'atraso' => 'danger', 'remessa_gerada' => 'info', 'finalizada' => 'secondary', default => 'light' };
                        $dt = !empty($p['entrou_janela_em']) ? date('d/m/Y H:i', strtotime($p['entrou_janela_em'])) : '-';
                        $dtPedido = !empty($p['pedido_criado_em']) ? date('d/m/Y H:i', strtotime($p['pedido_criado_em'])) : '';
                        $moeda = strtoupper(trim((string)($p['moeda'] ?? ($p['currency'] ?? 'USD'))));
                        $total = (float)($p['total'] ?? 0);
                        if ($moeda === 'BRL' && $usdToBrl > 0) $total = $total / $usdToBrl;
                        $wxStatus = (string)($p['wexpress_status'] ?? '');
                        $wxTrack = (string)($p['wexpress_tracking_number'] ?? '');
                        $wxCourier = (string)($p['courier_tracking_number'] ?? '');
                        $wxShipId = (string)($p['wexpress_shipping_id'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <a href="/admin/pedidos/detalhes/<?= $pid ?>" class="fw-bold text-decoration-none">#<?= str_pad((string)$pid, 6, '0', STR_PAD_LEFT) ?></a>
                            <?php if ($dtPedido): ?><div class="text-muted small">Pedido: <?= $dtPedido ?></div><?php endif; ?>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width:150px;"><?= htmlspecialchars($p['cliente_nome'] ?? 'N/A') ?></div>
                            <div class="text-muted small text-truncate" style="max-width:150px;"><?= htmlspecialchars($p['cliente_email'] ?? '') ?></div>
                        </td>
                        <td class="small"><?= $dt ?></td>
                        <td class="fw-semibold">US$ <?= number_format($total, 2, '.', ',') ?></td>
                        <td>
                            <a href="/admin/remessa-internacional/janela/<?= $jId ?>" class="badge bg-light text-dark border text-decoration-none">#<?= $jId ?></a>
                            <div class="text-muted small"><?= !empty($p['janela_inicio']) ? date('d/m', strtotime($p['janela_inicio'])) . '-' . date('d/m', strtotime($p['janela_fim'])) : '' ?></div>
                        </td>
                        <td><span class="badge bg-<?= $jBadge ?>"><?= ucfirst(str_replace('_', ' ', $jStatus)) ?></span></td>
                        <td>
                            <?php if ($et): ?>
                                <span class="badge bg-success">Gerada</span>
                                <?php if ($wxStatus): ?><div class="small text-muted"><?= htmlspecialchars($wxStatus) ?></div><?php endif; ?>
                                <?php if ($wxCourier): ?><div class="small text-muted">Tracking: <?= htmlspecialchars($wxCourier) ?></div><?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="/admin/remessa-internacional/janela/<?= $jId ?>/pedido/<?= $pid ?>" class="btn btn-outline-primary" title="Detalhes"><i class="fas fa-eye"></i></a>
                                <?php if (!$et): ?>
                                <a href="/admin/remessa-internacional/janela/<?= $jId ?>/pedido/<?= $pid ?>" class="btn btn-outline-success" title="Marcar etiqueta"><i class="fas fa-tag"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
