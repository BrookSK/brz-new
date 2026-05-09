<?php
/** @var array $live */
/** @var array $products */
/** @var string $activePage */
/** @var string $title */
$liveId = $live['id'];
$isLive = $live['status'] === 'live';
$isWebRTC = $live['ingest_method'] === 'webrtc';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/lives/studio.css" rel="stylesheet">
</head>
<body class="studio-body">

<div class="studio-container">
    <!-- Header -->
    <header class="studio-header">
        <a href="/admin/lives" class="btn btn-sm btn-outline-light">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="studio-status">
            <span id="liveIndicator" class="<?= $isLive ? 'live-active' : 'live-inactive' ?>">
                <span class="dot"></span>
                <span id="liveStatusText"><?= $isLive ? 'AO VIVO' : 'OFFLINE' ?></span>
            </span>
            <span id="liveDuration" class="ms-2 text-white-50">00:00:00</span>
        </div>
        <div class="studio-metrics">
            <span><i class="fas fa-eye"></i> <span id="viewerCount"><?= (int)($live['viewers_current'] ?? 0) ?></span></span>
            <span><i class="fas fa-heart"></i> <span id="likeCount"><?= (int)($live['likes_count'] ?? 0) ?></span></span>
        </div>
    </header>

    <!-- Área esquerda (vídeo + controles) -->
    <div class="studio-left">
        <section class="studio-preview">
            <?php if ($isWebRTC): ?>
                <video id="localVideo" autoplay muted playsinline></video>
                <div id="cameraPlaceholder" class="camera-placeholder <?= $isLive ? 'd-none' : '' ?>">
                    <i class="fas fa-video fa-3x"></i>
                    <p>Câmera será ativada ao iniciar</p>
                </div>
            <?php else: ?>
                <div class="obs-instructions">
                    <h5><i class="fas fa-desktop me-2"></i>Transmissão via OBS</h5>
                    <?php if ($isLive): ?>
                        <div class="obs-credentials">
                            <div class="mb-2">
                                <label class="form-label text-white-50 small">URL do Servidor</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($live['cf_rtmps_url'] ?? '') ?>" readonly id="rtmpsUrl">
                                    <button class="btn btn-outline-light" onclick="copyToClipboard('rtmpsUrl')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="form-label text-white-50 small">Chave de Transmissão</label>
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control" value="<?= htmlspecialchars($live['cf_rtmps_key'] ?? '') ?>" readonly id="rtmpsKey">
                                    <button class="btn btn-outline-light" onclick="toggleShow('rtmpsKey')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-light" onclick="copyToClipboard('rtmpsKey')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-white-50">Inicie a live para obter as credenciais RTMPS</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="studio-controls">
            <?php if (!$isLive): ?>
                <button id="btnStart" class="btn-live btn-live-start" onclick="startLive()">
                    <i class="fas fa-play me-2"></i> INICIAR LIVE
                </button>
            <?php else: ?>
                <button id="btnToggleCam" class="btn-media-toggle" onclick="toggleCamera()" title="Câmera">
                    <i class="fas fa-video"></i>
                </button>
                <button id="btnToggleMic" class="btn-media-toggle" onclick="toggleMic()" title="Microfone">
                    <i class="fas fa-microphone"></i>
                </button>
                <button id="btnStop" class="btn-live btn-live-stop" onclick="stopLive()">
                    <i class="fas fa-stop me-2"></i> ENCERRAR LIVE
                </button>
            <?php endif; ?>
        </section>
    </div>

    <!-- Painéis (direita) -->
    <section class="studio-panels">
        <!-- Tabs -->
        <div class="panel-tabs">
            <button class="panel-tab active" data-panel="products">
                <i class="fas fa-box"></i> Produtos
            </button>
            <button class="panel-tab" data-panel="chat">
                <i class="fas fa-comments"></i> Chat
                <span id="chatBadge" class="badge bg-danger ms-1 d-none">0</span>
            </button>
        </div>

        <!-- Painel de Produtos -->
        <div id="panelProducts" class="panel-content active">
            <div class="products-list">
                <?php foreach ($products as $product): ?>
                    <div class="product-card <?= ($live['current_featured_product_id'] == $product['product_id']) ? 'featured' : '' ?>" 
                         data-product-id="<?= $product['product_id'] ?>">
                        <div class="product-img">
                            <?php if (!empty($product['display_image'])): ?>
                                <img src="<?= htmlspecialchars($product['display_image']) ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-image"></i>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <span class="product-name"><?= htmlspecialchars($product['display_name'] ?? 'Produto') ?></span>
                            <span class="product-price">R$ <?= number_format((float)($product['display_price'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <button class="btn-feature <?= ($live['current_featured_product_id'] == $product['product_id']) ? 'active' : '' ?>"
                                onclick="toggleFeature(<?= $product['product_id'] ?>)"
                                title="Estou falando deste agora">
                            <i class="fas fa-bullhorn"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <p class="text-center text-muted py-3">Nenhum produto adicionado a esta live</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Painel de Chat -->
        <div id="panelChat" class="panel-content">
            <div id="chatMessages" class="chat-messages">
                <?php if (!empty($chatMessages)): ?>
                    <?php foreach ($chatMessages as $msg): ?>
                        <div class="chat-msg-studio">
                            <span class="user"><?= htmlspecialchars($msg['user_name'] ?? $msg['user_name_alt'] ?? 'Anônimo') ?></span>
                            <span class="text"><?= htmlspecialchars($msg['content']) ?></span>
                            <span class="actions">
                                <button onclick="hideMsg(<?= $msg['id'] ?>)" title="Ocultar"><i class="fas fa-eye-slash"></i></button>
                                <button onclick="banUserChat(<?= $msg['user_id'] ?>)" title="Banir"><i class="fas fa-ban"></i></button>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="p-2" style="border-top:1px solid #333">
                <div class="d-flex gap-2">
                    <input type="text" id="adminChatInput" class="form-control form-control-sm" placeholder="Enviar mensagem..." maxlength="500" style="background:#2a2a2a;border:1px solid #444;color:#fff;border-radius:20px;font-size:13px" onkeydown="if(event.key==='Enter')sendAdminChat()">
                    <button onclick="sendAdminChat()" class="btn btn-sm btn-danger" style="border-radius:50%;width:32px;height:32px;padding:0;flex-shrink:0"><i class="fas fa-paper-plane" style="font-size:11px"></i></button>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
const LIVE_ID = <?= $liveId ?>;
const IS_LIVE = <?= $isLive ? 'true' : 'false' ?>;
const IS_WEBRTC = <?= $isWebRTC ? 'true' : 'false' ?>;
const WEBRTC_URL = <?= json_encode($live['cf_webrtc_url'] ?? '') ?>;
let currentFeaturedId = <?= $live['current_featured_product_id'] ?? 'null' ?>;
let liveStartTime = <?= $isLive && $live['live_started_at'] ? strtotime($live['live_started_at']) : 'null' ?>;
let sseSource = null;
let durationInterval = null;
</script>
<script src="/assets/js/lives/studio.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
