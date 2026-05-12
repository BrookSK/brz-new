<?php $title = 'Logs do Carnê - Admin'; ?>
<?php ob_start(); ?>
<?php
$tipoBadges = [
    'carne_criado' => 'success',
    'carne_erro' => 'danger',
    'pix_gerado' => 'primary',
    'pix_erro' => 'danger',
    'boleto_gerado' => 'primary',
    'boleto_erro' => 'danger',
    'pagamento_confirmado' => 'success',
    'pagamento_nao_encontrado' => 'warning',
    'webhook_recebido' => 'info',
    'info' => 'secondary',
];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title">Logs do Carnê</h1>
        <div>
            <a href="/admin/carnes" class="btn btn-outline-primary"><i class="fas fa-file-invoice-dollar me-1"></i> Carnês</a>
            <a href="/admin" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/carnes/logs" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Carnê ID</label>
                    <input type="number" name="carne_id" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['carne_id'] ?? '') ?>" placeholder="ID do carnê">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Pedido ID</label>
                    <input type="number" name="pedido_id" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['pedido_id'] ?? '') ?>" placeholder="ID do pedido">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($tipoBadges as $tipo => $cor): ?>
                            <option value="<?= $tipo ?>" <?= ($filtros['tipo'] ?? '') === $tipo ? 'selected' : '' ?>><?= $tipo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i> Filtrar</button>
                    <a href="/admin/carnes/logs" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times me-1"></i> Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Logs -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-1"></i> Logs (<?= count($logs) ?>)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                    Nenhum log encontrado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 150px;">Data</th>
                                <th style="width: 160px;">Tipo</th>
                                <th style="width: 80px;">Carnê</th>
                                <th style="width: 80px;">Pedido</th>
                                <th style="width: 80px;">Parcela</th>
                                <th>Mensagem</th>
                                <th style="width: 200px;">Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="small text-nowrap"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <?php $badgeCor = $tipoBadges[$log['tipo']] ?? 'secondary'; ?>
                                        <span class="badge bg-<?= $badgeCor ?>"><?= htmlspecialchars($log['tipo']) ?></span>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($log['carne_id'])): ?>
                                            <a href="/admin/carnes/detalhes/<?= (int) $log['carne_id'] ?>">#<?= (int) $log['carne_id'] ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($log['pedido_id'])): ?>
                                            #<?= (int) $log['pedido_id'] ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($log['parcela_id'])): ?>
                                            #<?= (int) $log['parcela_id'] ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($log['mensagem'] ?? '') ?></td>
                                    <td class="small">
                                        <?php if (!empty($log['detalhes'])): ?>
                                            <?php $detalheTruncado = mb_strlen($log['detalhes']) > 80 ? mb_substr($log['detalhes'], 0, 80) . '...' : $log['detalhes']; ?>
                                            <span class="log-detalhe-truncado" title="Clique para expandir" style="cursor: pointer;" onclick="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                                <?= htmlspecialchars($detalheTruncado) ?>
                                            </span>
                                            <span class="log-detalhe-completo" style="display: none; word-break: break-all;">
                                                <?= htmlspecialchars($log['detalhes']) ?>
                                                <a href="#" class="text-muted small" onclick="event.preventDefault(); this.parentElement.style.display='none'; this.parentElement.previousElementSibling.style.display='inline';">[recolher]</a>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../../layouts/admin.php'; ?>
