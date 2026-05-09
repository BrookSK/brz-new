<?php
/** @var array $live */
/** @var array $products */
/** @var string $title */
ob_start();
?>
<div class="container py-4" style="max-width:800px">
    <!-- Capa -->
    <div class="mb-4" style="border-radius:16px;overflow:hidden;position:relative">
        <?php if (!empty($live['cover_url'])): ?>
            <img src="<?= htmlspecialchars($live['cover_url']) ?>" class="w-100" style="height:300px;object-fit:cover" alt="">
        <?php else: ?>
            <div style="height:300px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center">
                <i class="fas fa-video fa-4x text-white" style="opacity:0.5"></i>
            </div>
        <?php endif; ?>
        
        <?php if ($live['status'] === 'scheduled'): ?>
            <div class="position-absolute top-0 end-0 m-3">
                <span class="badge bg-info px-3 py-2"><i class="fas fa-clock me-1"></i> Agendada</span>
            </div>
        <?php elseif ($live['status'] === 'ended'): ?>
            <div class="position-absolute top-0 end-0 m-3">
                <span class="badge bg-secondary px-3 py-2"><i class="fas fa-check me-1"></i> Encerrada</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Info -->
    <h1 class="h2 mb-2"><?= htmlspecialchars($live['title']) ?></h1>
    
    <?php if ($live['status'] === 'scheduled' && !empty($live['scheduled_at'])): ?>
        <div class="alert alert-info d-flex align-items-center">
            <i class="fas fa-calendar-alt fa-lg me-3"></i>
            <div>
                <strong>Programada para:</strong><br>
                <?= date('d/m/Y', strtotime($live['scheduled_at'])) ?> às <?= date('H:i', strtotime($live['scheduled_at'])) ?>
            </div>
        </div>
    <?php elseif ($live['status'] === 'ended'): ?>
        <div class="alert alert-secondary d-flex align-items-center">
            <i class="fas fa-info-circle fa-lg me-3"></i>
            <div>
                Esta live já foi encerrada.
                <?php if ($live['live_ended_at']): ?>
                    <br><small>Realizada em <?= date('d/m/Y', strtotime($live['live_ended_at'])) ?></small>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($live['description'])): ?>
        <p class="text-muted mb-4"><?= nl2br(htmlspecialchars($live['description'])) ?></p>
    <?php endif; ?>

    <!-- Métricas (se encerrada) -->
    <?php if ($live['status'] === 'ended'): ?>
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="text-center p-3 bg-light rounded">
                    <div class="h5 mb-0"><?= (int)($live['viewers_peak'] ?? 0) ?></div>
                    <small class="text-muted">Viewers</small>
                </div>
            </div>
            <div class="col-4">
                <div class="text-center p-3 bg-light rounded">
                    <div class="h5 mb-0"><?= (int)($live['likes_count'] ?? 0) ?></div>
                    <small class="text-muted">Curtidas</small>
                </div>
            </div>
            <div class="col-4">
                <div class="text-center p-3 bg-light rounded">
                    <div class="h5 mb-0"><?= (int)($live['shares_count'] ?? 0) ?></div>
                    <small class="text-muted">Compartilhamentos</small>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Produtos da live -->
    <?php if (!empty($products)): ?>
        <h4 class="mb-3"><i class="fas fa-shopping-bag me-2"></i>Produtos desta live</h4>
        <div class="row g-3 mb-4">
            <?php foreach ($products as $p): ?>
                <div class="col-6 col-md-4">
                    <div class="card h-100 border-0 shadow-sm" style="border-radius:12px;overflow:hidden">
                        <?php if (!empty($p['display_image'])): ?>
                            <img src="<?= htmlspecialchars($p['display_image']) ?>" class="card-img-top" style="height:120px;object-fit:cover" alt="">
                        <?php else: ?>
                            <div class="card-img-top d-flex align-items-center justify-content-center" style="height:120px;background:#f8f9fa">
                                <i class="fas fa-image fa-2x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body p-2">
                            <small class="d-block fw-bold" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['display_name'] ?? '') ?></small>
                            <?php if ((float)($p['display_price'] ?? 0) > 0): ?>
                                <small class="text-danger fw-bold">R$ <?= number_format((float)$p['display_price'], 2, ',', '.') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a href="/lives" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Voltar para Lives
    </a>
</div>
<?php
$content = ob_get_clean();
$title = $live['title'] . ' - Lives - Braziliana';
include __DIR__ . '/../layouts/main.php';
?>
