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
ob_start();
?>

<div class="container py-4" style="max-width:900px">
    <!-- Player da Live -->
    <div class="live-player-wrapper mb-4">
        <div class="live-video-container position-relative" style="border-radius:16px;overflow:hidden;background:#000;aspect-ratio:9/16;max-height:70vh;margin:0 auto;max-width:400px">
            <?php if ($isActive && !empty($playbackUrl)): ?>
                <video id="liveVideo" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover"></video>
            <?php elseif ($live['status'] === 'ended' && !empty($live['recording_url'])): ?>
                <video id="liveVideo" controls playsinline style="width:100%;height:100%;object-fit:cover"></video>
            <?php elseif ($live['status'] === 'scheduled'): ?>
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-white text-center p-4" style="background:linear-gradient(135deg,#1a1a2e,#16213e)">
                    <?php if (!empty($live['cover_url'])): ?>
                        <img src="<?= htmlspecialchars($live['cover_url']) ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:0.4" alt="">
                    <?php endif; ?>
                    <div style="position:relative;z-index:2">
                        <i class="fas fa-calendar fa-3x mb-3"></i>
                        <h3><?= htmlspecialchars($live['title']) ?></h3>
                        <p><i class="fas fa-clock"></i> <?= date('d/m/Y \à\s H:i', strtotime($live['scheduled_at'])) ?></p>
                        <p class="text-white-50">A live ainda não começou</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-white text-center p-4" style="background:#1a1a2e">
                    <i class="fas fa-video fa-3x mb-3 text-white-50"></i>
                    <h4><?= htmlspecialchars($live['title']) ?></h4>
                    <p class="text-white-50">Live encerrada</p>
                </div>
            <?php endif; ?>

            <!-- Overlay superior -->
            <?php if ($isActive): ?>
                <div class="position-absolute top-0 start-0 end-0 p-3" style="background:linear-gradient(to bottom,rgba(0,0,0,0.5),transparent);z-index:5">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger" style="animation:pulse 2s infinite"><i class="fas fa-circle me-1" style="font-size:6px"></i> AO VIVO</span>
                        <span class="text-white small"><i class="fas fa-eye"></i> <span id="viewers">0</span></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Overlay lateral (ações) -->
            <div class="position-absolute end-0 d-flex flex-column align-items-center gap-3" style="bottom:100px;right:12px;z-index:5">
                <button class="btn btn-link text-white text-center p-0" id="btnLike" onclick="sendLike()" style="text-decoration:none">
                    <i class="fas fa-heart" style="font-size:28px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5))"></i>
                    <br><small id="likeCount">0</small>
                </button>
                <button class="btn btn-link text-white text-center p-0" onclick="shareModal()" style="text-decoration:none">
                    <i class="fas fa-share" style="font-size:24px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5))"></i>
                    <br><small id="shareCount">0</small>
                </button>
                <button class="btn btn-link text-white text-center p-0" onclick="toggleChat()" style="text-decoration:none">
                    <i class="fas fa-comment" style="font-size:24px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5))"></i>
                </button>
                <button class="btn btn-link text-white text-center p-0" data-bs-toggle="modal" data-bs-target="#productsModal" style="text-decoration:none">
                    <i class="fas fa-shopping-bag" style="font-size:24px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5))"></i>
                    <br><small><?= count($products) ?></small>
                </button>
            </div>

            <!-- Animação de corações -->
            <div id="heartsContainer" style="position:absolute;right:40px;bottom:100px;width:60px;height:200px;pointer-events:none;z-index:4;overflow:hidden"></div>

            <!-- Chat overlay -->
            <div class="position-absolute bottom-0 start-0 p-3" id="chatOverlay" style="right:70px;max-height:200px;z-index:5">
                <div id="chatMessages" style="overflow-y:auto;max-height:150px;display:flex;flex-direction:column;gap:4px;mask-image:linear-gradient(to bottom,transparent 0%,black 30%)"></div>
                <?php if ($isLoggedIn): ?>
                    <div class="d-flex gap-2 mt-2">
                        <input type="text" id="chatInput" class="form-control" placeholder="Enviar mensagem..." maxlength="500" style="background:rgba(255,255,255,0.2);border:none;color:#fff;border-radius:24px;font-size:14px;padding:10px 18px" onkeydown="if(event.key==='Enter')sendChat()">
                        <button onclick="sendChat()" class="btn btn-danger" style="border-radius:50%;width:42px;height:42px;padding:0;flex-shrink:0"><i class="fas fa-paper-plane"></i></button>
                    </div>
                <?php else: ?>
                    <a href="/login?redirect=/lives/<?= $liveId ?>" class="d-block text-center small text-white-50 mt-2" style="background:rgba(0,0,0,0.3);border-radius:20px;padding:8px">Faça login para participar do chat</a>
                <?php endif; ?>
            </div>

            <!-- Pílula "Falando agora" -->
            <div id="featuredPill" style="display:none;position:absolute;bottom:220px;left:12px;right:70px;z-index:6">
                <div onclick="openProductSheet()" style="display:flex;align-items:center;gap:10px;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);border-radius:12px;padding:8px 12px;cursor:pointer;border:1px solid rgba(255,255,255,0.1)">
                    <img id="featuredImg" src="" alt="" style="width:40px;height:40px;border-radius:8px;object-fit:cover">
                    <div style="flex:1;min-width:0">
                        <small style="color:rgba(255,255,255,0.6);font-size:10px">Falando agora:</small>
                        <strong id="featuredName" style="display:block;font-size:13px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></strong>
                    </div>
                    <span id="featuredPrice" style="font-weight:700;font-size:14px;color:#ff2d55;white-space:nowrap"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Produtos da live (modal, aparece ao clicar na sacolinha) -->
    <?php if (!empty($products)): ?>
        <div class="modal fade" id="productsModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content" style="border-radius:16px">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-shopping-bag me-2"></i>Produtos desta live</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <?php foreach ($products as $p): ?>
                                <div class="col-6">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius:12px;overflow:hidden">
                                        <?php if (!empty($p['display_image'])): ?>
                                            <img src="<?= htmlspecialchars($p['display_image']) ?>" class="card-img-top" style="height:120px;object-fit:cover;cursor:pointer" alt="" onclick="selectProduct(<?= $p['product_id'] ?>); bootstrap.Modal.getInstance(document.getElementById('productsModal')).hide();">
                                        <?php else: ?>
                                            <div class="card-img-top d-flex align-items-center justify-content-center" style="height:120px;background:#f5f5f5;cursor:pointer" onclick="selectProduct(<?= $p['product_id'] ?>); bootstrap.Modal.getInstance(document.getElementById('productsModal')).hide();"><i class="fas fa-image fa-2x text-muted"></i></div>
                                        <?php endif; ?>
                                        <div class="card-body p-2">
                                            <small class="d-block text-dark mb-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['display_name'] ?? '') ?></small>
                                            <strong class="text-danger d-block mb-2">R$ <?= number_format((float)($p['display_price'] ?? 0), 2, ',', '.') ?></strong>
                                            <a href="/carrinho/adicionar/<?= $p['product_id'] ?>" class="btn btn-sm btn-outline-danger w-100" style="border-radius:8px;font-size:12px">
                                                <i class="fas fa-cart-plus me-1"></i>Adicionar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Bottom Sheet: Produto -->
<div class="modal fade" id="productSheetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-body text-center p-4">
                <img id="sheetProductImg" src="" alt="" class="mb-3" style="max-height:200px;border-radius:12px;max-width:100%">
                <h5 id="sheetProductName"></h5>
                <p id="sheetProductDesc" class="text-muted small"></p>
                <div class="mb-3">
                    <span id="sheetProductPrice" class="h4 text-danger fw-bold"></span>
                </div>
                <?php if ($isLoggedIn): ?>
                    <button id="btnBuyNow" class="btn btn-danger btn-lg w-100" onclick="buyNow()" style="border-radius:12px">
                        <i class="fas fa-bolt me-2"></i>Comprar agora
                    </button>
                <?php else: ?>
                    <a href="/login?redirect=/lives/<?= $liveId ?>" class="btn btn-dark btn-lg w-100" style="border-radius:12px">
                        <i class="fas fa-user me-2"></i> Entrar para comprar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" style="position:fixed;top:80px;left:50%;transform:translateX(-50%) translateY(-100px);background:rgba(0,0,0,0.9);color:#fff;padding:12px 24px;border-radius:24px;font-size:14px;z-index:9999;transition:transform 0.3s ease;pointer-events:none"></div>

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.7} }
@keyframes floatUp {
    0% { transform: translateY(0) scale(0.5); opacity: 1; }
    50% { opacity: 1; }
    100% { transform: translateY(-180px) translateX(var(--drift)) scale(0.3); opacity: 0; }
}
.floating-heart {
    position: absolute;
    bottom: 0;
    font-size: 24px;
    animation: floatUp 2s ease-out forwards;
    opacity: 0;
}
.chat-msg {
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
    border-radius: 16px;
    padding: 4px 10px;
    font-size: 12px;
    color: #fff;
    max-width: 85%;
    word-break: break-word;
}
.chat-msg .chat-user { font-weight: 600; color: #69c9ff; margin-right: 4px; }
#toast.show { transform: translateX(-50%) translateY(0); }
</style>

<script>
const LIVE_ID = <?= $liveId ?>;
const IS_ACTIVE = <?= $isActive ? 'true' : 'false' ?>;
const PLAYBACK_URL = <?= json_encode($playbackUrl) ?>;
const RECORDING_URL = <?= json_encode($live['recording_url'] ?? '') ?>;
const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
const HAS_CARD = <?= $hasCard ? 'true' : 'false' ?>;
const USER_ID = <?= $userId ?>;
const PRODUCTS = <?= json_encode(array_map(function($p) {
    return [
        'id' => (int)$p['product_id'],
        'name' => $p['display_name'] ?? '',
        'price' => (float)($p['display_price'] ?? 0),
        'weight' => (float)($p['display_weight'] ?? 0),
        'image' => $p['display_image'] ?? '',
        'description' => $p['original_description'] ?? '',
    ];
}, $products)) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1"></script>
<script src="/assets/js/lives/player.js"></script>
<script src="/assets/js/lives/chat.js"></script>
<script src="/assets/js/lives/shopping.js"></script>
<script>
// Override para garantir que contadores funcionam
window.sendLike = function() {
    if (!IS_LOGGED_IN) return;
    var countEl = document.getElementById('likeCount');
    countEl.textContent = parseInt(countEl.textContent || 0) + 1;
    spawnHeart();
    fetch('/api/live/' + LIVE_ID + '/like', { method: 'POST' });
};

window.shareModal = function() {
    var countEl = document.getElementById('shareCount');
    countEl.textContent = parseInt(countEl.textContent || 0) + 1;
    if (navigator.share) {
        navigator.share({ title: document.title, url: window.location.href }).then(function() {
            fetch('/api/live/' + LIVE_ID + '/share', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({channel:'native'}) });
        }).catch(function(){});
    } else {
        navigator.clipboard.writeText(window.location.href).then(function() {
            showToast('Link copiado!', 'success');
            fetch('/api/live/' + LIVE_ID + '/share', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({channel:'link'}) });
        });
    }
};

window.spawnHeart = function() {
    var container = document.getElementById('heartsContainer');
    if (!container) return;
    var heart = document.createElement('span');
    heart.className = 'floating-heart';
    heart.textContent = ['❤️','💖','💕','💗','🩷'][Math.floor(Math.random()*5)];
    heart.style.left = Math.random()*40 + 'px';
    heart.style.setProperty('--drift', (Math.random()*40-20) + 'px');
    container.appendChild(heart);
    setTimeout(function(){ heart.remove(); }, 2000);
};

window.toggleChat = function() {
    var overlay = document.getElementById('chatOverlay');
    if (overlay) overlay.style.display = overlay.style.display === 'none' ? '' : 'none';
};

window.showToast = function(message, type) {
    var toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'show';
    setTimeout(function(){ toast.className = ''; }, 3000);
};

window.openProductSheet = function() {
    if (typeof currentFeaturedProduct !== 'undefined' && currentFeaturedProduct) {
        showProductDetail(currentFeaturedProduct);
    }
};
</script>

<?php
$content = ob_get_clean();
$title = $title ?? 'Live - Braziliana';
include __DIR__ . '/../layouts/main.php';
?>
