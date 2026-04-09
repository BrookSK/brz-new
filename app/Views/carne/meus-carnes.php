<?php $title = 'Meus Carnês - Carnê Braziliana'; ?>
<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <?php $activePage = 'carnes'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Meus Carnês</h2>
                    <p class="text-muted mb-0">Acompanhe seus parcelamentos via Carnê Braziliana</p>
                </div>
            </div>

            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <?php if (empty($carnes)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-invoice-dollar text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">Nenhum carnê encontrado</h5>
                        <p class="text-muted">Você ainda não possui parcelamentos via Carnê Braziliana.</p>
                        <a href="/produtos" class="btn btn-primary"><i class="fas fa-shopping-bag me-1"></i> Ver Produtos</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($carnes as $c):
                    $statusMap = [
                        'aguardando_primeira_parcela' => ['cor' => 'info', 'icon' => 'clock'],
                        'ativo' => ['cor' => 'primary', 'icon' => 'play-circle'],
                        'em_andamento' => ['cor' => 'primary', 'icon' => 'spinner'],
                        'com_atraso' => ['cor' => 'danger', 'icon' => 'exclamation-triangle'],
                        'quitado' => ['cor' => 'success', 'icon' => 'check-circle'],
                        'liberado_envio' => ['cor' => 'success', 'icon' => 'truck'],
                        'encerrado' => ['cor' => 'secondary', 'icon' => 'archive'],
                    ];
                    $st = $statusMap[$c['status']] ?? ['cor' => 'secondary', 'icon' => 'question'];
                    $pagas = (int) ($c['parcelas_pagas'] ?? 0);
                    $total = (int) $c['quantidade_parcelas'];
                    $progresso = $total > 0 ? round(($pagas / $total) * 100) : 0;
                ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-<?= $st['cor'] ?> me-2">
                                        <i class="fas fa-<?= $st['icon'] ?> me-1"></i><?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                                    </span>
                                    <span class="text-muted small">Pedido #<?= $c['pedido_id'] ?> — <?= date('d/m/Y', strtotime($c['created_at'])) ?></span>
                                </div>
                                <h5 class="mb-1">R$ <?= number_format($c['total_geral'], 2, ',', '.') ?></h5>
                                <p class="text-muted small mb-2"><?= $total ?>x de R$ <?= number_format($c['total_geral'] / max($total, 1), 2, ',', '.') ?></p>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $progresso >= 100 ? 'success' : 'primary' ?>" style="width: <?= $progresso ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $pagas ?> de <?= $total ?> parcelas pagas</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <?php if (!empty($c['proximo_vencimento'])): ?>
                                    <small class="text-muted d-block">Próximo vencimento</small>
                                    <span class="fw-bold"><?= date('d/m/Y', strtotime($c['proximo_vencimento'])) ?></span>
                                <?php elseif ($c['status'] === 'quitado' || $c['status'] === 'liberado_envio'): ?>
                                    <span class="text-success fw-bold"><i class="fas fa-check-circle"></i> Quitado</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2 text-end">
                                <a href="/meu-carne/<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Detalhes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
