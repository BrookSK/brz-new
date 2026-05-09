<?php
/** @var array|null $liveAtiva */
/** @var array $agendadas */
/** @var array $encerradas */
ob_start();
?>
<div class="container py-4" style="max-width:900px">
    <div class="text-center mb-4">
        <h1 class="h2"><i class="fas fa-video me-2 text-danger"></i>Lives</h1>
        <p class="text-muted">Assista ao vivo e compre com 1 clique</p>
    </div>

    <!-- Live ativa -->
    <?php if ($liveAtiva): ?>
        <a href="/lives/<?= $liveAtiva['id'] ?>" class="d-block mb-4 text-decoration-none">
            <div class="card border-0 shadow overflow-hidden" style="border-radius:16px">
                <div class="position-relative">
                    <?php if (!empty($liveAtiva['cover_url'])): ?>
                        <img src="<?= htmlspecialchars($liveAtiva['cover_url']) ?>" class="w-100" style="height:280px;object-fit:cover;opacity:0.85" alt="">
                    <?php else: ?>
                        <div style="height:280px;background:linear-gradient(135deg,#1a1a2e,#16213e)"></div>
                    <?php endif; ?>
                    <div class="position-absolute bottom-0 start-0 end-0 p-4" style="background:linear-gradient(to top,rgba(0,0,0,0.85),transparent)">
                        <span class="badge bg-danger mb-2" style="animation:pulse 2s infinite">
                            <i class="fas fa-circle me-1" style="font-size:8px"></i> AO VIVO AGORA
                        </span>
                        <h3 class="text-white mb-1"><?= htmlspecialchars($liveAtiva['title']) ?></h3>
                        <small class="text-white-50">
                            <i class="fas fa-eye"></i> <?= (int)($liveAtiva['viewers_current'] ?? 0) ?> assistindo
                            · <i class="fas fa-heart"></i> <?= (int)($liveAtiva['likes_count'] ?? 0) ?>
                        </small>
                    </div>
                </div>
            </div>
        </a>
    <?php endif; ?>

    <!-- Agendadas -->
    <?php if (!empty($agendadas)): ?>
        <h4 class="mb-3"><i class="fas fa-calendar me-2 text-info"></i>Próximas lives</h4>
        <div class="row g-3 mb-5">
            <?php foreach ($agendadas as $live): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="/lives/<?= $live['id'] ?>" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius:12px;overflow:hidden;transition:transform .2s">
                            <?php if (!empty($live['cover_url'])): ?>
                                <img src="<?= htmlspecialchars($live['cover_url']) ?>" class="card-img-top" style="height:150px;object-fit:cover" alt="">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height:150px;background:#f0f0f0">
                                    <i class="fas fa-video fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <span class="badge bg-info bg-opacity-10 text-info mb-2"><i class="fas fa-clock me-1"></i>Agendada</span>
                                <h6 class="card-title text-dark mb-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($live['title']) ?></h6>
                                <small class="text-muted">
                                    <?php if ($live['scheduled_at']): ?>
                                        <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($live['scheduled_at'])) ?>
                                        às <?= date('H:i', strtotime($live['scheduled_at'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Encerradas (com gravação) -->
    <?php if (!empty($encerradas)): ?>
        <h4 class="mb-3"><i class="fas fa-play-circle me-2 text-secondary"></i>Lives anteriores</h4>
        <div class="row g-3 mb-4">
            <?php foreach ($encerradas as $live): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="/lives/<?= $live['id'] ?>" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius:12px;overflow:hidden;transition:transform .2s">
                            <?php if (!empty($live['cover_url'])): ?>
                                <img src="<?= htmlspecialchars($live['cover_url']) ?>" class="card-img-top" style="height:150px;object-fit:cover" alt="">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height:150px;background:#f0f0f0">
                                    <i class="fas fa-play fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary mb-2"><i class="fas fa-play me-1"></i>Gravação</span>
                                <h6 class="card-title text-dark mb-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($live['title']) ?></h6>
                                <small class="text-muted">
                                    <?php if ($live['live_ended_at']): ?>
                                        <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($live['live_ended_at'])) ?>
                                    <?php endif; ?>
                                    · <i class="fas fa-eye"></i> <?= (int)($live['viewers_peak'] ?? 0) ?> viewers
                                </small>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Vazio -->
    <?php if (!$liveAtiva && empty($agendadas) && empty($encerradas)): ?>
        <div class="text-center py-5">
            <i class="fas fa-video fa-3x text-muted mb-3"></i>
            <p class="text-muted">Nenhuma live programada no momento.<br>Volte em breve!</p>
        </div>
    <?php endif; ?>
</div>

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.7} }
.card:hover { transform: translateY(-3px); }
</style>
<?php
$content = ob_get_clean();
$title = 'Lives - Braziliana';
include __DIR__ . '/../layouts/main.php';
?>
