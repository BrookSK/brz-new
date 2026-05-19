<?php
/**
 * Conferência de Remessa - Listagem flat de pedidos com filtros
 * Variáveis disponíveis: $pedidos, $janelas, $stats, $filtros
 */
$filtroJanela = $filtros['janela_id'] ?? '';
$filtroStatus = $filtros['janela_status'] ?? '';
$filtroEtiqueta = $filtros['etiqueta'] ?? '';
$filtroBusca = $filtros['busca'] ?? '';
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="page-title">Conferência de Remessa</h1>
    <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="location.reload()">
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
                <a href="/admin/remessa-conferencia" class="btn btn-sm btn-outline-secondary w-100">Limpar</a>
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
                        <th>Data/Hora</th>
                        <th>Cliente</th>
                        <th>ZIP/CEP</th>
                        <th>Qtd</th>
                        <th>Janela</th>
                        <th>Status Janela</th>
                        <th>Etiqueta</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Nenhum pedido encontrado.</td></tr>
                    <?php else: ?>
                    <?php foreach ($pedidos as $p):
                        $pid = (int)($p['pedido_id'] ?? 0);
                        $et = (int)($p['etiqueta_gerada'] ?? 0);
                        $jId = (int)($p['janela_id'] ?? 0);
                        $jStatus = (string)($p['janela_status'] ?? '');
                        $jBadge = match($jStatus) { 'aberta' => 'success', 'atraso' => 'danger', 'remessa_gerada' => 'info', 'finalizada' => 'secondary', default => 'light' };
                        $dt = !empty($p['created_at']) ? date('d/m/Y H:i', strtotime($p['created_at'])) : '-';
                        $cep = (string)($p['cep_entrega'] ?? '');
                        $qtd = $p['qtd_itens'] !== null ? (int)$p['qtd_itens'] : '-';
                        $wxStatus = (string)($p['wexpress_status'] ?? '');
                        $wxTrack = (string)($p['wexpress_tracking_number'] ?? '');
                        $wxCourier = (string)($p['courier_tracking_number'] ?? '');
                        $wxShipId = (string)($p['wexpress_shipping_id'] ?? '');
                    ?>
                    <tr>
                        <td><a href="/admin/pedidos/detalhes/<?= $pid ?>" class="fw-bold text-decoration-none">#<?= str_pad((string)$pid, 6, '0', STR_PAD_LEFT) ?></a></td>
                        <td class="small"><?= $dt ?></td>
                        <td>
                            <div class="text-truncate" style="max-width:140px;"><?= htmlspecialchars($p['cliente_nome'] ?? 'N/A') ?></div>
                        </td>
                        <td class="small"><?= htmlspecialchars($cep) ?></td>
                        <td><?= $qtd ?></td>
                        <td>
                            <a href="/admin/remessa-conferencia/janela/<?= $jId ?>" class="badge bg-light text-dark border text-decoration-none">#<?= $jId ?></a>
                        </td>
                        <td><span class="badge bg-<?= $jBadge ?>"><?= ucfirst(str_replace('_', ' ', $jStatus)) ?></span></td>
                        <td>
                            <?php if ($et): ?>
                                <span class="badge bg-success">Gerada</span>
                                <?php if ($wxCourier): ?><div class="small text-muted"><?= htmlspecialchars($wxCourier) ?></div><?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($et && $wxShipId): ?>
                                <a href="https://label.wexpress.me/wexpress-premium/?shipping_id=<?= rawurlencode($wxShipId) ?>" target="_blank" class="btn btn-outline-info" title="Etiqueta"><i class="fas fa-tag"></i></a>
                                <?php endif; ?>
                                <a href="/admin/remessa-conferencia/janela/<?= $jId ?>/pedido/<?= $pid ?>" class="btn btn-outline-primary" title="Detalhes"><i class="fas fa-eye"></i></a>
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
