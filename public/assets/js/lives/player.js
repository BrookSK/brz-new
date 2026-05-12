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
    
    // Se a URL é WHEP (webRTC/play), usar player WHEP
    if (url && url.indexOf('/webRTC/play') !== -1) {
        console.log('Using WHEP player for:', url);
        initWHEPPlayer(video, url);
        return;
    }
    
    console.log('Player URL (HLS):', url);
    
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

        pc.addTransceiver('video', { direction: 'recvonly' });
        pc.addTransceiver('audio', { direction: 'recvonly' });

        pc.ontrack = function(event) {
            console.log('WHEP: Got track!', event.track.kind);
            if (event.streams && event.streams[0]) {
                video.srcObject = event.streams[0];
            } else if (event.track) {
                var stream = video.srcObject;
                if (!stream) {
                    stream = new MediaStream();
                    video.srcObject = stream;
                }
                stream.addTrack(event.track);
            }
            // Esconder overlay de "Conectando..."
            var overlays = document.querySelectorAll('.live-video-container > div[style*="position:absolute"]');
            overlays.forEach(function(el) { el.style.display = 'none'; });
            
            // Só dar play quando receber a track de vídeo (evita conflito)
            if (event.track.kind === 'video') {
                video.muted = true;
                // Workaround para tela preta: reatribuir srcObject após pequeno delay
                setTimeout(function() {
                    var s = video.srcObject;
                    video.srcObject = null;
                    video.srcObject = s;
                    video.play().then(function() {
                        console.log('WHEP: Video playing!');
                        // Segundo workaround: forçar repaint
                        video.style.opacity = '0.99';
                        setTimeout(function() { video.style.opacity = '1'; }, 50);
                    }).catch(function(e) {
                        console.log('WHEP: Play error:', e.message);
                    });
                }, 500);
            }
        };

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);

        // Chamar Cloudflare DIRETAMENTE (CF permite CORS em WHEP/play)
        const response = await fetch(whepUrl, {
            method: 'POST',
            mode: 'cors',
            headers: { 'Content-Type': 'application/sdp' },
            body: pc.localDescription.sdp
        });

        if (response.status === 201 || response.ok) {
            const answerSdp = await response.text();
            console.log('WHEP: Got answer from CF, length:', answerSdp.length);
            
            if (!answerSdp.startsWith('v=')) {
                console.error('WHEP: Invalid SDP');
                setTimeout(function() { initWHEPPlayer(video, whepUrl); }, 5000);
                return;
            }
            
            await pc.setRemoteDescription(new RTCSessionDescription({
                type: 'answer',
                sdp: answerSdp
            }));
            console.log('WHEP: Connected directly to CF!');
        } else {
            const errText = await response.text();
            console.error('WHEP direct error:', response.status, errText.substring(0, 100));
            // Se CORS falhar, tentar via proxy
            console.log('Trying WHEP via proxy...');
            initWHEPViaProxy(video, whepUrl);
        }
    } catch (e) {
        console.error('WHEP error:', e.message);
        // Se falhar (CORS), tentar via proxy
        if (e.message && e.message.indexOf('CORS') !== -1) {
            initWHEPViaProxy(video, whepUrl);
        } else {
            setTimeout(function() { initWHEPPlayer(video, whepUrl); }, 5000);
        }
    }
}

// Fallback: WHEP via proxy (se CORS bloquear)
async function initWHEPViaProxy(video, whepUrl) {
    try {
        const pc = new RTCPeerConnection({
            iceServers: [{ urls: 'stun:stun.cloudflare.com:3478' }],
            bundlePolicy: 'max-bundle'
        });

        pc.addTransceiver('video', { direction: 'recvonly' });
        pc.addTransceiver('audio', { direction: 'recvonly' });

        pc.ontrack = function(event) {
            console.log('WHEP proxy: Got track!', event.track.kind);
            if (event.streams && event.streams[0]) {
                video.srcObject = event.streams[0];
                video.play().catch(function() { video.muted = true; video.play(); });
            }
        };

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);

        const response = await fetch('/api/live/' + LIVE_ID + '/whep', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/sdp' },
            body: pc.localDescription.sdp
        });

        if (response.status === 201 || response.ok) {
            const answerSdp = await response.text();
            if (answerSdp.startsWith('v=')) {
                await pc.setRemoteDescription(new RTCSessionDescription({
                    type: 'answer',
                    sdp: answerSdp
                }));
                console.log('WHEP proxy: Connected!');
            }
        }
    } catch (e) {
        console.error('WHEP proxy error:', e);
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
    // Se está usando iframe do Cloudflare, não precisa do player JS
    var liveVideo = document.getElementById('liveVideo');
    if (liveVideo && liveVideo.tagName === 'IFRAME') {
        console.log('Using Cloudflare iframe player');
        return;
    }

    if (IS_ACTIVE || RECORDING_URL) {
        initPlayer();
    }
    if (IS_ACTIVE) {
        startHeartbeat();
    }
});
