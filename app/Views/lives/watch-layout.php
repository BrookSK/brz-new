<?php
/** @var array $live */
/** @var array $products */
/** @var array $featuredHistory */
/** @var string $playbackUrl */
/** @var int $userId */
/** @var bool $isLoggedIn */
/** @var bool $hasCard */
/** @var string $title */
$liveId = $live['id'];
$isScheduled = $live['status'] === 'scheduled';
$isEnded = $live['status'] === 'ended';
ob_start();
?>
<div class="container py-4" style="max-width:900px">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Início</a></li>
            <li class="breadcrumb-item"><a href="/lives">Lives</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($live['title']) ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <!-- Capa / Player -->
            <div class="card border-0 shadow mb-4" style="border-radius:16px;overflow:hidden">
                <?php if ($isEnded && !empty($live['recording_url'])): ?>
                    <!-- Player de gravação -->
                    <div style="position:relative;padding-top:56.25%;background:#000">
                        <video id="liveVideo" controls playsinline style="position:absolute;top:0;left:0;width:100%;height:100%"></video>
                    </div>
                <?php elseif (!empty($live['cover_url'])): ?>
                    <img src="<?= htmlspecialchars($live['cover_url']) ?>" class="w-100" style="max-height:400px;object-fit:cover" alt="">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center" style="height:300px;background:linear-gradient(135deg,#1a1a2e,#16213e)">
                        <i class="fas fa-video fa-4x text-white-50"></i>
                    </div>
                <?php endif; ?>

                <div class="card-body">
                    <!-- Status -->
                    <?php if ($isScheduled): ?>
                        <span class="badge bg-info mb-2"><i class="fas fa-clock me-1"></i>Agendada</span>
                    <?php elseif ($isEnded): ?>
                        <span class="badge bg-secondary mb-2"><i class="fas fa-check me-1"></i>Encerrada</span>
                    <?php endif; ?>

                    <h2 class="h4 mb-2"><?= htmlspecialchars($live['title']) ?></h2>
                    
                    <?php if (!empty($live['description'])): ?>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($live['description'])) ?></p>
                    <?php endif; ?>

                    <!-- Info -->
                    <div class="d-flex flex-wrap gap-3 text-muted small mt-3">
                        <?php if ($isScheduled && $live['scheduled_at']): ?>
                            <span><i class="fas fa-calendar-alt me-1"></i><?= date('d/m/Y \à\s H:i', strtotime($live['scheduled_at'])) ?></span>
                        <?php endif; ?>
                        <?php if ($isEnded && $live['live_ended_at']): ?>
                            <span><i class="fas fa-calendar-alt me-1"></i>Realizada em <?= date('d/m/Y', strtotime($live['live_ended_at'])) ?></span>
                        <?php endif; ?>
                        <?php if ((int)($live['viewers_peak'] ?? 0) > 0): ?>
                            <span><i class="fas fa-eye me-1"></i><?= (int)$live['viewers_peak'] ?> viewers</span>
                        <?php endif; ?>
                        <?php if ((int)($live['likes_count'] ?? 0) > 0): ?>
                            <span><i class="fas fa-heart me-1"></i><?= (int)$live['likes_count'] ?> curtidas</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($isScheduled): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Esta live ainda não começou. Volte no horário agendado para assistir ao vivo!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Produtos da live -->
            <?php if (!empty($products)): ?>
                <div class="card border-0 shadow" style="border-radius:16px">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Produtos desta live</h5>
                    </div>
                    <div class="card-body pt-0">
                        <?php foreach ($products as $p): ?>
                            <div class="d-flex align-items-center py-2 <?= !empty($products[array_key_last($products)]) && $p !== end($products) ? 'border-bottom' : '' ?>">
                                <?php if (!empty($p['display_image'])): ?>
                                    <img src="<?= htmlspecialchars($p['display_image']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;margin-right:12px" alt="">
                                <?php else: ?>
                                    <div style="width:50px;height:50px;border-radius:8px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="fw-semibold small" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['display_name'] ?? '') ?></div>
                                    <div class="text-danger fw-bold small">R$ <?= number_format((float)($p['display_price'] ?? 0), 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isEnded && !empty($live['recording_url'])): ?>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var video = document.getElementById('liveVideo');
    var url = <?= json_encode($live['recording_url']) ?>;
    if (!video || !url) return;
    
    if (Hls.isSupported()) {
        var hls = new Hls();
        hls.loadSource(url);
        hls.attachMedia(video);
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = url;
    }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
