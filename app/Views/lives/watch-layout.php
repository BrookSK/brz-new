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
$isActive = $live['status'] === 'live';
$isScheduled = $live['status'] === 'scheduled';
$isEnded = $live['status'] === 'ended';
ob_start();
?>
<style>
.live-page { max-width: 480px; margin: 0 auto; padding: 0; }
.live-player-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 9/16;
    max-height: 75vh;
    background: #000;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 20px;
}
.live-player-wrapper video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.live-waiting {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    color: #fff;
    padding: 20px;
}
.live-waiting img {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    object-fit: cover;
    opacity: 0.5;
}
.live-waiting-content { position: relative; z-index: 2; }

/* Overlays */
.live-overlay-top {
    position: absolute;
    top: 0; left: 0; right: 0;
    padding: 14px;
    background: linear-gradient(to bottom, rgba(0,0,0,0.6), transparent);
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 5;
}
.live-badge-ao-vivo {
    background: #ff2d55;
    color: #fff;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    animation: pulse 2s infinite;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.7} }
.live-viewers { color: rgba(255,255,255,0.8); font-size: 13px; }

.live-overlay-right {
    position: absolute;
    right: 12px;
    bottom: 100px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 18px;
    z-index: 5;
}
.live-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    color: #fff;
    font-size: 11px;
    background: none;
    border: none;
    cursor: pointer;
}
.live-action-btn i { font-size: 24px; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }

/* Chat overlay */
.live-chat-overlay {
    position: absolute;
    bottom: 60px;
    left: 12px;
    right: 70px;
    max-height: 180px;
    overflow-y: auto;
    z-index: 5;
    mask-image: linear-gradient(to bottom, transparent 0%, black 30%);
    -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 30%);
}
.live-chat-msg {
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
    border-radius: 14px;
    padding: 5px 10px;
    font-size: 12px;
    color: #fff;
    margin-bottom: 4px;
    max-width: 85%;
}
.live-chat-msg .user { font-weight: 600; color: #69c9ff; margin-right: 5px; }

.live-chat-input {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    display: flex;
    gap: 8px;
    z-index: 5;
}
.live-chat-input input {
    flex: 1;
    background: rgba(255,255,255,0.15);
    border: none;
    border-radius: 20px;
    padding: 8px 14px;
    color: #fff;
    font-size: 13px;
    outline: none;
    backdrop-filter: blur(4px);
}
.live-chat-input input::placeholder { color: rgba(255,255,255,0.5); }
.live-chat-input button {
    width: 34px; height: 34px;
    border-radius: 50%;
    border: none;
    background: #ff2d55;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

/* Pílula produto */
.live-featured-pill {
    position: absolute;
    bottom: 60px;
    left: 12px;
    right: 70px;
    z-index: 6;
    display: none;
}
.live-featured-pill.show { display: block; animation: slideIn .3s ease; }
@keyframes slideIn { from{transform:translateX(-100%);opacity:0} to{transform:translateX(0);opacity:1} }
.pill-inner {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(8px);
    border-radius: 12px;
    padding: 8px 12px;
    cursor: pointer;
    border: 1px solid rgba(255,255,255,0.1);
}
.pill-inner img { width: 38px; height: 38px; border-radius: 6px; object-fit: cover; }
.pill-inner .pill-info { flex: 1; min-width: 0; }
.pill-inner .pill-info small { display: block; font-size: 10px; color: rgba(255,255,255,0.6); }
.pill-inner .pill-info strong { display: block; font-size: 12px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pill-inner .pill-price { font-weight: 700; font-size: 13px; color: #ff2d55; white-space: nowrap; }

/* Seção de produtos abaixo */
.live-products-section { margin-bottom: 30px; }
.live-products-section h5 { font-size: 16px; margin-bottom: 12px; }
.live-product-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.live-product-item img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; margin-right: 12px; }
.live-product-item .no-img { width: 50px; height: 50px; border-radius: 8px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; margin-right: 12px; }
.live-product-item .info { flex: 1; min-width: 0; }
.live-product-item .info .name { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.live-product-item .info .price { font-size: 14px; font-weight: 700; color: #ff2d55; }

/* Info da live */
.live-info-section { margin-bottom: 20px; }
.live-info-section h3 { font-size: 20px; margin-bottom: 6px; }
.live-info-section .meta { font-size: 13px; color: #666; display: flex; flex-wrap: wrap; gap: 12px; }

@media (min-width: 768px) {
    .live-page { max-width: 480px; }
}
</style>

<div class="live-page">
    <!-- Player -->
    <div class="live-player-wrapper">
        <?php if ($isActive && !empty($playbackUrl)): ?>
            <video id="liveVideo" autoplay playsinline muted></video>
            <!-- Overlay top -->
            <div class="live-overlay-top">
                <span class="live-badge-ao-vivo">● AO VIVO</span>
                <span class="live-viewers"><i class="fas fa-eye"></i> <span id="viewers"><?= (int)($live['viewers_current'] ?? 0) ?></span></span>
            </div>
            <!-- Overlay right -->
            <div class="live-overlay-right">
                <button class="live-action-btn" id="btnLike"><i class="fas fa-heart"></i><span id="likeCount"><?= (int)($live['likes_count'] ?? 0) ?></span></button>
                <button class="live-action-btn"><i class="fas fa-share"></i><span><?= (int)($live['shares_count'] ?? 0) ?></span></button>
                <button class="live-action-btn"><i class="fas fa-shopping-bag"></i><span><?= count($products) ?></span></button>
            </div>
            <!-- Chat -->
            <div class="live-chat-overlay" id="chatMessages"></div>
            <?php if ($isLoggedIn): ?>
            <div class="live-chat-input">
                <input type="text" id="chatInput" placeholder="Enviar mensagem..." maxlength="500">
                <button onclick="sendChat()"><i class="fas fa-paper-plane"></i></button>
            </div>
            <?php endif; ?>
            <!-- Pílula produto -->
            <div class="live-featured-pill" id="featuredPill">
                <div class="pill-inner" onclick="openProductSheet()">
                    <img id="featuredImg" src="" alt="">
                    <div class="pill-info">
                        <small>Falando agora:</small>
                        <strong id="featuredName"></strong>
                    </div>
                    <span class="pill-price" id="featuredPrice"></span>
                </div>
            </div>
        <?php elseif ($isEnded && !empty($live['recording_url'])): ?>
            <video id="liveVideo" controls playsinline style="background:#000"></video>
        <?php else: ?>
            <div class="live-waiting">
                <?php if (!empty($live['cover_url'])): ?>
                    <img src="<?= htmlspecialchars($live['cover_url']) ?>" alt="">
                <?php endif; ?>
                <div class="live-waiting-content">
                    <i class="fas fa-video fa-3x mb-3" style="color:rgba(255,255,255,0.6)"></i>
                    <h3 style="color:#fff"><?= htmlspecialchars($live['title']) ?></h3>
                    <?php if ($isScheduled && $live['scheduled_at']): ?>
                        <p style="color:rgba(255,255,255,0.7)"><i class="fas fa-calendar"></i> <?= date('d/m/Y \à\s H:i', strtotime($live['scheduled_at'])) ?></p>
                    <?php endif; ?>
                    <p style="color:rgba(255,255,255,0.5)"><?= $isScheduled ? 'A live ainda não começou' : 'Live encerrada' ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="live-info-section">
        <h3><?= htmlspecialchars($live['title']) ?></h3>
        <?php if (!empty($live['description'])): ?>
            <p class="text-muted mb-2"><?= nl2br(htmlspecialchars($live['description'])) ?></p>
        <?php endif; ?>
        <div class="meta">
            <?php if ($isScheduled && $live['scheduled_at']): ?>
                <span><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y \à\s H:i', strtotime($live['scheduled_at'])) ?></span>
            <?php elseif ($isEnded && $live['live_ended_at']): ?>
                <span><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($live['live_ended_at'])) ?></span>
            <?php endif; ?>
            <?php if ((int)($live['viewers_peak'] ?? 0) > 0): ?>
                <span><i class="fas fa-eye"></i> <?= (int)$live['viewers_peak'] ?> viewers</span>
            <?php endif; ?>
            <?php if ((int)($live['likes_count'] ?? 0) > 0): ?>
                <span><i class="fas fa-heart"></i> <?= (int)$live['likes_count'] ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Produtos -->
    <?php if (!empty($products)): ?>
    <div class="live-products-section">
        <h5><i class="fas fa-shopping-bag me-2"></i>Produtos desta live</h5>
        <?php foreach ($products as $p): ?>
            <div class="live-product-item">
                <?php if (!empty($p['display_image'])): ?>
                    <img src="<?= htmlspecialchars($p['display_image']) ?>" alt="">
                <?php else: ?>
                    <div class="no-img"><i class="fas fa-image text-muted"></i></div>
                <?php endif; ?>
                <div class="info">
                    <div class="name"><?= htmlspecialchars($p['display_name'] ?? '') ?></div>
                    <div class="price">R$ <?= number_format((float)($p['display_price'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($isActive && !empty($playbackUrl)): ?>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1"></script>
<script>
const LIVE_ID = <?= $liveId ?>;
const PLAYBACK_URL = <?= json_encode($playbackUrl) ?>;
const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;

document.addEventListener('DOMContentLoaded', function() {
    var video = document.getElementById('liveVideo');
    if (video && PLAYBACK_URL) {
        if (Hls.isSupported()) {
            var hls = new Hls({ lowLatencyMode: true });
            hls.loadSource(PLAYBACK_URL);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function() { video.play().catch(function(){}); });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = PLAYBACK_URL;
            video.play().catch(function(){});
        }
        video.addEventListener('click', function() { video.muted = !video.muted; });
    }
});

function sendChat() {
    var input = document.getElementById('chatInput');
    if (!input || !input.value.trim()) return;
    var content = input.value.trim();
    input.value = '';
    fetch('/api/live/' + LIVE_ID + '/chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({content: content})
    });
}
document.getElementById('chatInput')?.addEventListener('keydown', function(e) { if(e.key==='Enter') sendChat(); });
</script>
<?php elseif ($isEnded && !empty($live['recording_url'])): ?>
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
