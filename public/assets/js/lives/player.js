/**
 * Live Player — Cliente (HLS + SSE + UI)
 */

let sseSource = null;
let heartbeatInterval = null;
let secondsWatched = 0;

// ─── Inicializar Player ──────────────────────────────────────
function initPlayer() {
    const video = document.getElementById('liveVideo');
    if (!video) return;

    var url = PLAYBACK_URL || RECORDING_URL;
    console.log('Player URL:', url);
    
    if (!url) {
        console.log('No playback URL available');
        return;
    }

    // Sempre usar HLS (funciona com qualquer stream do Cloudflare)
    if (typeof Hls !== 'undefined' && Hls.isSupported()) {
        var hls = new Hls({
            enableWorker: true,
            lowLatencyMode: true,
            liveSyncDurationCount: 3,
        });
        hls.loadSource(url);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, function() {
            console.log('HLS: Manifest parsed, playing...');
            video.play().catch(function() {
                video.muted = true;
                video.play();
            });
        });
        hls.on(Hls.Events.ERROR, function(event, data) {
            console.log('HLS error:', data.type, data.details);
            if (data.fatal) {
                console.log('HLS fatal error, retrying in 5s...');
                hls.destroy();
                setTimeout(function() { initPlayer(); }, 5000);
            }
        });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = url;
        video.addEventListener('loadedmetadata', function() {
            video.play().catch(function() { video.muted = true; video.play(); });
        });
    }

    video.addEventListener('click', function() { video.muted = !video.muted; });
}

// ─── WHEP Player (WebRTC playback) ──────────────────────────
async function initWHEPPlayer(video, whepUrl) {
    try {
        const pc = new RTCPeerConnection({
            iceServers: [{ urls: 'stun:stun.cloudflare.com:3478' }],
            bundlePolicy: 'max-bundle'
        });

        // Receber tracks
        pc.addTransceiver('video', { direction: 'recvonly' });
        pc.addTransceiver('audio', { direction: 'recvonly' });

        pc.ontrack = function(event) {
            if (event.streams && event.streams[0]) {
                video.srcObject = event.streams[0];
                video.play().catch(() => { video.muted = true; video.play(); });
            }
        };

        // Criar offer
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);

        // Usar proxy local (evita CORS)
        const proxyUrl = '/api/live/' + LIVE_ID + '/whep';

        const response = await fetch(proxyUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/sdp' },
            body: pc.localDescription.sdp
        });

        if (response.status === 201 || response.ok) {
            const answerSdp = await response.text();
            await pc.setRemoteDescription(new RTCSessionDescription({
                type: 'answer',
                sdp: answerSdp
            }));
            console.log('WHEP: Connected! Receiving stream...');
        } else {
            console.error('WHEP error:', response.status);
            // Retry em 5s
            setTimeout(function() { initWHEPPlayer(video, whepUrl); }, 5000);
        }
    } catch (e) {
        console.error('WHEP error:', e);
        setTimeout(function() { initWHEPPlayer(video, whepUrl); }, 5000);
    }
}

// ─── SSE removido — usar polling via watch.php inline ───────

// ─── Produto em Destaque ────────────────────────────────────
let currentFeaturedProduct = null;

function handleFeaturedUpdate(data) {
    const pill = document.getElementById('featuredPill');

    if (!data.product_id) {
        pill.style.display = 'none';
        currentFeaturedProduct = null;
        return;
    }

    currentFeaturedProduct = data;
    document.getElementById('featuredImg').src = data.image || '';
    document.getElementById('featuredName').textContent = data.name || '';
    document.getElementById('featuredPrice').textContent = 'R$ ' + formatPrice(data.price);

    pill.style.display = 'block';
    // Re-trigger animation
    pill.style.animation = 'none';
    pill.offsetHeight; // reflow
    pill.style.animation = '';
}

function openProductSheet() {
    if (!currentFeaturedProduct) return;
    showProductDetail(currentFeaturedProduct);
}

// ─── Likes ──────────────────────────────────────────────────
let likeThrottle = false;

function sendLike() {
    if (!IS_LOGGED_IN) return;
    if (likeThrottle) return;

    // Atualizar contador imediatamente (otimista)
    var countEl = document.getElementById('likeCount');
    countEl.textContent = parseInt(countEl.textContent || 0) + 1;

    // Animação de coração
    spawnHeart();

    // Enviar ao servidor
    fetch(`/api/live/${LIVE_ID}/like`, { method: 'POST' }).catch(() => {});

    // Throttle visual
    likeThrottle = true;
    setTimeout(() => { likeThrottle = false; }, 200);

    // Feedback visual no botão
    const btn = document.getElementById('btnLike');
    btn.classList.add('liked');
    setTimeout(() => btn.classList.remove('liked'), 300);
}

function spawnHeart() {
    const container = document.getElementById('heartsContainer');
    const heart = document.createElement('span');
    heart.className = 'floating-heart';
    heart.textContent = ['❤️', '💖', '💕', '💗', '🩷'][Math.floor(Math.random() * 5)];
    heart.style.left = Math.random() * 40 + 'px';
    heart.style.setProperty('--drift', (Math.random() * 40 - 20) + 'px');
    container.appendChild(heart);
    setTimeout(() => heart.remove(), 2000);
}

// ─── Compartilhar ───────────────────────────────────────────
function shareModal() {
    // Atualizar contador imediatamente
    var countEl = document.getElementById('shareCount');
    countEl.textContent = parseInt(countEl.textContent || 0) + 1;

    if (navigator.share) {
        navigator.share({
            title: document.title,
            url: window.location.href
        }).then(() => {
            fetch(`/api/live/${LIVE_ID}/share`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ channel: 'native' })
            });
        }).catch(() => {});
    } else {
        // Fallback: copiar link
        navigator.clipboard.writeText(window.location.href).then(() => {
            showToast('Link copiado!', 'success');
            fetch(`/api/live/${LIVE_ID}/share`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ channel: 'link' })
            });
        });
    }
}

// ─── Heartbeat (Freemium) ───────────────────────────────────
function startHeartbeat() {
    if (!IS_LOGGED_IN) return;

    heartbeatInterval = setInterval(async () => {
        secondsWatched += 10;
        try {
            const res = await fetch(`/api/live/${LIVE_ID}/heartbeat`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ seconds_watched: secondsWatched })
            });
            const data = await res.json();

            if (data.can_continue === false) {
                // Paywall
                clearInterval(heartbeatInterval);
                showPaywall(data);
            }
        } catch (e) {}
    }, 10000);
}

function showPaywall(data) {
    // TODO: Implementar UI de paywall
    showToast(`Tempo grátis esgotado. Desbloqueie por R$ ${formatPrice(data.unlock_price)}`, 'info');
}

// ─── Toggle Produtos ────────────────────────────────────────
function toggleProducts() {
    var section = document.getElementById('productsSection');
    if (section) {
        if (section.style.display === 'none') {
            section.style.display = 'block';
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            section.style.display = 'none';
        }
    }
}

// ─── Chat Toggle ────────────────────────────────────────────
function toggleChat() {
    const overlay = document.getElementById('chatOverlay');
    overlay.style.display = overlay.style.display === 'none' ? 'flex' : 'none';
}

function selectProduct(productId, el) {
    const product = PRODUCTS.find(p => p.id === productId);
    if (product) {
        showProductDetail(product);
    }
}

// ─── Helpers ────────────────────────────────────────────────
function formatPrice(value) {
    return parseFloat(value || 0).toFixed(2).replace('.', ',');
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast-notification show ' + type;
    setTimeout(() => { toast.className = 'toast-notification'; }, 3000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ─── Inicialização ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (IS_ACTIVE || RECORDING_URL) {
        initPlayer();
    }
    if (IS_ACTIVE) {
        startHeartbeat();
    }
});
