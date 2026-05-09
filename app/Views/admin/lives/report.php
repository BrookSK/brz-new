<?php
/** @var array $live */
/** @var array $orders */
/** @var array $conversion */
/** @var array $featuredHistory */
?>
<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-chart-bar me-2"></i>Relatório: <?= htmlspecialchars($live['title']) ?></h1>
        <a href="/admin/lives" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <!-- Métricas gerais -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0"><?= (int)($live['viewers_peak'] ?? 0) ?></div>
                    <small class="text-muted">Pico de Viewers</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0"><?= (int)($live['likes_count'] ?? 0) ?></div>
                    <small class="text-muted">Curtidas</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0"><?= (int)($live['shares_count'] ?? 0) ?></div>
                    <small class="text-muted">Compartilhamentos</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0"><?= count($orders) ?></div>
                    <small class="text-muted">Pedidos</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Duração -->
    <?php if ($live['live_started_at'] && $live['live_ended_at']): ?>
        <?php $durMin = (int)ceil((strtotime($live['live_ended_at']) - strtotime($live['live_started_at'])) / 60); ?>
        <div class="alert alert-light">
            <i class="fas fa-clock me-2"></i>
            Duração: <strong><?= $durMin ?> minutos</strong>
            (<?= date('d/m H:i', strtotime($live['live_started_at'])) ?> — <?= date('H:i', strtotime($live['live_ended_at'])) ?>)
        </div>
    <?php endif; ?>

    <!-- Conversão por produto -->
    <div class="card mb-4">
        <div class="card-header">Conversão por Produto</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="text-center">Pedidos</th>
                            <th class="text-center">Pagos</th>
                            <th class="text-end">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conversion)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Nenhum pedido nesta live</td></tr>
                        <?php else: ?>
                            <?php $totalFat = 0; foreach ($conversion as $c): $totalFat += (float)($c['faturamento'] ?? 0); ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['produto_nome'] ?? 'Produto #' . $c['product_id']) ?></td>
                                    <td class="text-center"><?= (int)$c['total_pedidos'] ?></td>
                                    <td class="text-center"><?= (int)$c['pedidos_pagos'] ?></td>
                                    <td class="text-end">R$ <?= number_format((float)($c['faturamento'] ?? 0), 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td>Total</td>
                                <td class="text-center"><?= array_sum(array_column($conversion, 'total_pedidos')) ?></td>
                                <td class="text-center"><?= array_sum(array_column($conversion, 'pedidos_pagos')) ?></td>
                                <td class="text-end">R$ <?= number_format($totalFat, 2, ',', '.') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Histórico de destaques -->
    <div class="card mb-4">
        <div class="card-header">Produtos Destacados (timeline)</div>
        <div class="card-body">
            <?php if (empty($featuredHistory)): ?>
                <p class="text-muted mb-0">Nenhum produto foi destacado nesta live</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($featuredHistory as $fe): ?>
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <small class="text-muted"><?= date('H:i:s', strtotime($fe['started_at'])) ?></small>
                                <?php if ($fe['ended_at']): ?>
                                    <br><small class="text-muted">→ <?= date('H:i:s', strtotime($fe['ended_at'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($fe['product_image'])): ?>
                                <img src="<?= htmlspecialchars($fe['product_image']) ?>" class="me-2" style="width:40px;height:40px;object-fit:cover;border-radius:6px" alt="">
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($fe['product_name'] ?? '') ?></strong>
                                <br><small class="text-muted">R$ <?= number_format((float)($fe['product_price'] ?? 0), 2, ',', '.') ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gravação -->
    <?php if (!empty($live['recording_url'])): ?>
        <div class="card">
            <div class="card-header">Gravação</div>
            <div class="card-body">
                <a href="<?= htmlspecialchars($live['recording_url']) ?>" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-download me-2"></i> Baixar gravação
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
