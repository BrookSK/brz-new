/**
 * Live Player — Cliente (HLS + SSE + UI)
 */

let sseSource = null;
let heartbeatInterval = null;
let secondsWatched = 0;

// ─── Inicializar Player HLS ─────────────────────────────────
function initPlayer() {
    const video = document.getElementById('liveVideo');
    if (!video) return;

    const url = PLAYBACK_URL || RECORDING_URL;
    if (!url) return;

    if (Hls.isSupported()) {
        const hls = new Hls({
            enableWorker: true,
            lowLatencyMode: true,
        });
        hls.loadSource(url);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            video.play().catch(() => {
                // Autoplay bloqueado — mostrar botão de play
                video.muted = true;
                video.play();
            });
        });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Safari nativo
        video.src = url;
        video.addEventListener('loadedmetadata', () => {
            video.play().catch(() => { video.muted = true; video.play(); });
        });
    }

    // Unmute ao tocar
    video.addEventListener('click', () => {
        video.muted = !video.muted;
    });
}

// ─── SSE (Realtime) ─────────────────────────────────────────
function connectSSE() {
    if (sseSource) sseSource.close();

    sseSource = new EventSource(`/api/live/${LIVE_ID}/events`);

    sseSource.addEventListener('featured', (e) => {
        const data = JSON.parse(e.data);
        handleFeaturedUpdate(data);
    });

    sseSource.addEventListener('chat', (e) => {
        const data = JSON.parse(e.data);
        if (data.messages) {
            data.messages.forEach(renderChatMessage);
        }
    });

    sseSource.addEventListener('metrics', (e) => {
        const data = JSON.parse(e.data);
        document.getElementById('viewers').textContent = data.viewers || 0;
        document.getElementById('likeCount').textContent = data.likes || 0;
        document.getElementById('shareCount').textContent = data.shares || 0;
    });

    sseSource.addEventListener('ended', () => {
        sseSource.close();
        showToast('A live foi encerrada', 'info');
    });

    sseSource.addEventListener('reconnect', () => {
        sseSource.close();
        setTimeout(connectSSE, 2000);
    });

    sseSource.onerror = () => {
        sseSource.close();
        setTimeout(connectSSE, 3000);
    };
}

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

    // Animação de coração
    spawnHeart();

    // Enviar ao servidor
    fetch(`/api/live/${LIVE_ID}/like`, { method: 'POST' }).catch(() => {});

    // Throttle visual (não bloquear completamente)
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

// ─── Chat Toggle ────────────────────────────────────────────
function toggleChat() {
    const overlay = document.getElementById('chatOverlay');
    overlay.style.display = overlay.style.display === 'none' ? 'flex' : 'none';
}

// ─── Drawer de Produtos ─────────────────────────────────────
function showProductsDrawer() {
    document.getElementById('productsDrawer').classList.add('open');
}

function closeProductsDrawer() {
    document.getElementById('productsDrawer').classList.remove('open');
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
        connectSSE();
        startHeartbeat();
    }
});
