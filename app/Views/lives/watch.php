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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/lives/player.css" rel="stylesheet">
</head>
<body class="live-player-body">

<div class="live-container">
    <!-- Vídeo full-bleed -->
    <div class="video-wrapper">
        <?php if ($isActive && !empty($playbackUrl)): ?>
            <video id="liveVideo" autoplay playsinline muted></video>
        <?php elseif ($live['status'] === 'ended' && !empty($live['recording_url'])): ?>
            <video id="liveVideo" controls playsinline></video>
        <?php elseif ($live['status'] === 'scheduled'): ?>
            <div class="waiting-screen">
                <?php if (!empty($live['cover_url'])): ?>
                    <img src="<?= htmlspecialchars($live['cover_url']) ?>" alt="" class="waiting-cover">
                <?php endif; ?>
                <div class="waiting-info">
                    <h2><?= htmlspecialchars($live['title']) ?></h2>
                    <p><i class="fas fa-calendar"></i> <?= date('d/m/Y \à\s H:i', strtotime($live['scheduled_at'])) ?></p>
                    <p class="text-white-50">A live ainda não começou</p>
                </div>
            </div>
        <?php else: ?>
            <div class="waiting-screen">
                <div class="waiting-info">
                    <i class="fas fa-video fa-3x mb-3"></i>
                    <h2><?= htmlspecialchars($live['title']) ?></h2>
                    <p class="text-white-50">Live encerrada</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Overlay superior -->
        <div class="overlay-top">
            <div class="live-info">
                <a href="/" class="btn-back"><i class="fas fa-arrow-left"></i></a>
                <?php if ($isActive): ?>
                    <span class="live-badge-pill"><span class="dot-red"></span> AO VIVO</span>
                <?php endif; ?>
                <span class="viewer-count"><i class="fas fa-eye"></i> <span id="viewers">0</span></span>
            </div>
        </div>

        <!-- Overlay lateral direita (ações) -->
        <div class="overlay-right">
            <button class="action-btn" id="btnLike" onclick="sendLike()">
                <i class="fas fa-heart"></i>
                <span id="likeCount">0</span>
            </button>
            <button class="action-btn" onclick="shareModal()">
                <i class="fas fa-share"></i>
                <span id="shareCount">0</span>
            </button>
            <button class="action-btn" onclick="toggleChat()">
                <i class="fas fa-comment"></i>
            </button>
            <button class="action-btn" onclick="showProductsDrawer()">
                <i class="fas fa-shopping-bag"></i>
                <span><?= count($products) ?></span>
            </button>
        </div>

        <!-- Animação de corações -->
        <div id="heartsContainer" class="hearts-container"></div>

        <!-- Chat overlay (inferior) -->
        <div class="chat-overlay" id="chatOverlay">
            <div class="chat-messages" id="chatMessages"></div>
            <?php if ($isLoggedIn): ?>
                <div class="chat-input-wrapper">
                    <input type="text" id="chatInput" placeholder="Enviar mensagem..." maxlength="500"
                           onkeydown="if(event.key==='Enter')sendChat()">
                    <button onclick="sendChat()" class="btn-send">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            <?php else: ?>
                <a href="/login?redirect=/lives/<?= $liveId ?>" class="chat-login-prompt">
                    Faça login para participar do chat
                </a>
            <?php endif; ?>
        </div>

        <!-- Pílula "Falando agora" -->
        <div class="featured-pill" id="featuredPill" style="display:none">
            <div class="pill-content" onclick="openProductSheet()">
                <img id="featuredImg" src="" alt="" class="pill-img">
                <div class="pill-text">
                    <small>Falando agora:</small>
                    <strong id="featuredName"></strong>
                </div>
                <span class="pill-price" id="featuredPrice"></span>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Sheet: Produto -->
<div class="bottom-sheet" id="productSheet">
    <div class="sheet-backdrop" onclick="closeProductSheet()"></div>
    <div class="sheet-content">
        <div class="sheet-handle"></div>
        <div class="sheet-body">
            <img id="sheetProductImg" src="" alt="" class="sheet-product-img">
            <h4 id="sheetProductName"></h4>
            <p id="sheetProductDesc" class="text-muted"></p>
            <div class="sheet-price">
                <span id="sheetProductPrice" class="price-main"></span>
            </div>
            <?php if ($isLoggedIn): ?>
                <button id="btnBuyNow" class="btn-buy-now" onclick="buyNow()">
                    <i class="fas fa-bolt me-2"></i>
                    Comprar agora
                </button>
                <p id="buyCardInfo" class="buy-card-info">
                    <?php if ($hasCard): ?>
                        <!-- Será preenchido via JS -->
                    <?php else: ?>
                        <a href="#" onclick="showCardForm(); return false;">Cadastrar cartão para compra rápida</a>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <a href="/login?redirect=/lives/<?= $liveId ?>" class="btn-buy-now btn-buy-login">
                    <i class="fas fa-user me-2"></i> Entrar para comprar
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom Sheet: Todos os Produtos -->
<div class="bottom-sheet" id="productsDrawer">
    <div class="sheet-backdrop" onclick="closeProductsDrawer()"></div>
    <div class="sheet-content sheet-tall">
        <div class="sheet-handle"></div>
        <div class="sheet-body">
            <h5 class="mb-3">Produtos desta live</h5>
            <div class="products-grid">
                <?php foreach ($products as $p): ?>
                    <div class="product-mini-card" data-product-id="<?= $p['product_id'] ?>"
                         onclick="selectProduct(<?= $p['product_id'] ?>, this)">
                        <img src="<?= htmlspecialchars($p['display_image'] ?? '') ?>" alt="" class="mini-img">
                        <div class="mini-info">
                            <span class="mini-name"><?= htmlspecialchars($p['display_name'] ?? '') ?></span>
                            <span class="mini-price">R$ <?= number_format((float)($p['display_price'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Sheet: Confirmação de compra -->
<div class="bottom-sheet" id="confirmSheet">
    <div class="sheet-backdrop" onclick="closeConfirmSheet()"></div>
    <div class="sheet-content">
        <div class="sheet-handle"></div>
        <div class="sheet-body text-center">
            <h5>Confirmar pedido</h5>
            <p id="confirmDetails"></p>
            <button id="btnConfirm" class="btn-buy-now" onclick="confirmPurchase()">
                <i class="fas fa-check me-2"></i> Confirmar
            </button>
            <button class="btn-cancel" onclick="closeConfirmSheet()">Cancelar</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="toast-notification"></div>

<script>
const LIVE_ID = <?= $liveId ?>;
const IS_ACTIVE = <?= $isActive ? 'true' : 'false' ?>;
const PLAYBACK_URL = <?= json_encode($playbackUrl) ?>;
const RECORDING_URL = <?= json_encode($live['recording_url'] ?? '') ?>;
const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
const HAS_CARD = <?= $hasCard ? 'true' : 'false' ?>;
const USER_ID = <?= $userId ?>;

// Dados dos produtos para JS
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
</body>
</html>
