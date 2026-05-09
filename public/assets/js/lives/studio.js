/**
 * Live Studio — Admin (WebRTC/WHIP, controles, SSE)
 */

// ─── Tabs ───────────────────────────────────────────────────
document.querySelectorAll('.panel-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.panel-content').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('panel' + capitalize(this.dataset.panel)).classList.add('active');
    });
});

function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

// ─── Duração ────────────────────────────────────────────────
if (IS_LIVE && liveStartTime) {
    durationInterval = setInterval(updateDuration, 1000);
    updateDuration();
}

function updateDuration() {
    if (!liveStartTime) return;
    const elapsed = Math.floor(Date.now() / 1000) - liveStartTime;
    const h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
    const m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
    const s = String(elapsed % 60).padStart(2, '0');
    document.getElementById('liveDuration').textContent = `${h}:${m}:${s}`;
}

// ─── Iniciar Live ───────────────────────────────────────────
async function startLive() {
    const btn = document.getElementById('btnStart');
    if (btn) btn.disabled = true;

    try {
        const res = await fetch(`/admin/lives/${LIVE_ID}/start`, { method: 'POST' });
        const data = await res.json();

        if (!data.success) {
            alert(data.error || 'Erro ao iniciar');
            if (btn) btn.disabled = false;
            return;
        }

        // Se WebRTC, iniciar câmera e WHIP
        if (IS_WEBRTC && data.webrtc_url) {
            await startWebRTC(data.webrtc_url);
        }

        // Atualizar UI
        document.getElementById('liveIndicator').className = 'live-active';
        document.getElementById('liveStatusText').textContent = 'AO VIVO';
        liveStartTime = Math.floor(Date.now() / 1000);
        durationInterval = setInterval(updateDuration, 1000);

        // Trocar botão
        const controls = document.querySelector('.studio-controls');
        controls.innerHTML = `
            <button id="btnStop" class="btn-live btn-live-stop" onclick="stopLive()">
                <i class="fas fa-stop me-2"></i> ENCERRAR LIVE
            </button>
        `;

        // Se OBS, mostrar credenciais
        if (!IS_WEBRTC) {
            location.reload(); // Recarregar para mostrar credenciais RTMPS
        }

        // Iniciar SSE
        connectSSE();

    } catch (e) {
        alert('Erro de conexão');
        if (btn) btn.disabled = false;
    }
}

// ─── Encerrar Live ──────────────────────────────────────────
async function stopLive() {
    if (!confirm('Encerrar a live?')) return;

    const btn = document.getElementById('btnStop');
    if (btn) btn.disabled = true;

    try {
        const res = await fetch(`/admin/lives/${LIVE_ID}/stop`, { method: 'POST' });
        const data = await res.json();

        if (data.success) {
            // Parar WebRTC
            stopWebRTC();
            // Redirecionar
            alert(`Live encerrada! ${data.minutes_streamed} minutos transmitidos.`);
            location.href = '/admin/lives';
        } else {
            alert(data.error || 'Erro ao encerrar');
            if (btn) btn.disabled = false;
        }
    } catch (e) {
        alert('Erro de conexão');
        if (btn) btn.disabled = false;
    }
}

// ─── WebRTC / WHIP ──────────────────────────────────────────
let localStream = null;
let peerConnection = null;

async function startWebRTC(whipUrl) {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 1280 } },
            audio: true
        });

        const video = document.getElementById('localVideo');
        if (video) {
            video.srcObject = localStream;
            video.play();
        }

        const placeholder = document.getElementById('cameraPlaceholder');
        if (placeholder) placeholder.classList.add('d-none');

        // WHIP: criar PeerConnection e enviar SDP
        peerConnection = new RTCPeerConnection({
            iceServers: [{ urls: 'stun:stun.cloudflare.com:3478' }]
        });

        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });

        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);

        // Esperar ICE gathering
        await new Promise(resolve => {
            if (peerConnection.iceGatheringState === 'complete') {
                resolve();
            } else {
                peerConnection.addEventListener('icegatheringstatechange', () => {
                    if (peerConnection.iceGatheringState === 'complete') resolve();
                });
            }
        });

        // Enviar SDP para WHIP endpoint
        const response = await fetch(whipUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/sdp' },
            body: peerConnection.localDescription.sdp
        });

        if (response.ok) {
            const answerSdp = await response.text();
            await peerConnection.setRemoteDescription({
                type: 'answer',
                sdp: answerSdp
            });
        } else {
            console.error('WHIP error:', response.status);
        }

    } catch (e) {
        console.error('WebRTC error:', e);
        alert('Erro ao acessar câmera: ' + e.message);
    }
}

function stopWebRTC() {
    if (localStream) {
        localStream.getTracks().forEach(t => t.stop());
        localStream = null;
    }
    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }
}

// ─── Toggle Câmera e Microfone ──────────────────────────────
function toggleCamera() {
    if (!localStream) return;
    var tracks = localStream.getVideoTracks();
    if (tracks.length === 0) return;
    var videoTrack = tracks[0];
    videoTrack.enabled = !videoTrack.enabled;
    var btn = document.getElementById('btnToggleCam');
    if (btn) {
        btn.classList.toggle('off', !videoTrack.enabled);
        btn.innerHTML = videoTrack.enabled ? '<i class="fas fa-video"></i>' : '<i class="fas fa-video-slash"></i>';
    }
}

function toggleMic() {
    if (!localStream) return;
    var tracks = localStream.getAudioTracks();
    if (tracks.length === 0) {
        // Não tem audio track — pedir permissão de áudio
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function(audioStream) {
            var audioTrack = audioStream.getAudioTracks()[0];
            localStream.addTrack(audioTrack);
            // Mutar imediatamente para toggle funcionar
            audioTrack.enabled = true;
            var btn = document.getElementById('btnToggleMic');
            if (btn) { btn.classList.remove('off'); btn.innerHTML = '<i class="fas fa-microphone"></i>'; }
        }).catch(function() {});
        return;
    }
    var audioTrack = tracks[0];
    audioTrack.enabled = !audioTrack.enabled;
    var btn = document.getElementById('btnToggleMic');
    if (btn) {
        btn.classList.toggle('off', !audioTrack.enabled);
        btn.innerHTML = audioTrack.enabled ? '<i class="fas fa-microphone"></i>' : '<i class="fas fa-microphone-slash"></i>';
    }
}

// ─── Destacar Produto ───────────────────────────────────────
async function toggleFeature(productId) {
    const newId = (currentFeaturedId === productId) ? null : productId;

    try {
        const res = await fetch(`/admin/lives/${LIVE_ID}/feature`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: newId })
        });
        const data = await res.json();

        if (data.success) {
            currentFeaturedId = newId;
            updateFeaturedUI();
        }
    } catch (e) {
        console.error('Feature error:', e);
    }
}

function updateFeaturedUI() {
    document.querySelectorAll('.product-card').forEach(card => {
        const pid = parseInt(card.dataset.productId);
        const btn = card.querySelector('.btn-feature');
        if (pid === currentFeaturedId) {
            card.classList.add('featured');
            btn.classList.add('active');
        } else {
            card.classList.remove('featured');
            btn.classList.remove('active');
        }
    });
}

// ─── SSE (receber chat e métricas) ──────────────────────────
function connectSSE() {
    if (sseSource) sseSource.close();

    sseSource = new EventSource(`/api/live/${LIVE_ID}/events`);

    sseSource.addEventListener('chat', (e) => {
        const data = JSON.parse(e.data);
        if (data.messages) {
            data.messages.forEach(renderStudioChatMsg);
        }
    });

    sseSource.addEventListener('metrics', (e) => {
        const data = JSON.parse(e.data);
        document.getElementById('viewerCount').textContent = data.viewers || 0;
        document.getElementById('likeCount').textContent = data.likes || 0;
    });

    sseSource.addEventListener('ended', () => {
        sseSource.close();
    });

    sseSource.onerror = () => {
        setTimeout(connectSSE, 3000);
    };
}

function renderStudioChatMsg(msg) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'chat-msg-studio';
    div.innerHTML = `
        <span class="user">${escapeHtml(msg.user_name || 'Anônimo')}</span>
        <span class="text">${escapeHtml(msg.content)}</span>
        <span class="actions">
            <button onclick="hideMsg(${msg.id})" title="Ocultar"><i class="fas fa-eye-slash"></i></button>
            <button onclick="banUserChat(${msg.user_id})" title="Banir"><i class="fas fa-ban"></i></button>
        </span>
    `;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;

    // Badge
    const badge = document.getElementById('chatBadge');
    if (badge && !document.querySelector('[data-panel="chat"]').classList.contains('active')) {
        badge.classList.remove('d-none');
        badge.textContent = parseInt(badge.textContent || 0) + 1;
    }
}

// ─── Moderação ──────────────────────────────────────────────
async function sendAdminChat() {
    var input = document.getElementById('adminChatInput');
    if (!input) return;
    var content = input.value.trim();
    if (!content) return;
    input.disabled = true;

    try {
        await fetch('/api/live/' + LIVE_ID + '/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'content=' + encodeURIComponent(content)
        });
        input.value = '';
    } catch(e) {}
    input.disabled = false;
    input.focus();
}

async function hideMsg(msgId) {
    await fetch(`/admin/lives/${LIVE_ID}/chat/${msgId}/hide`, { method: 'POST' });
}

async function banUserChat(userId) {
    if (!confirm('Banir este usuário da live?')) return;
    await fetch(`/admin/lives/${LIVE_ID}/ban/${userId}`, { method: 'POST' });
}

// ─── Helpers ────────────────────────────────────────────────
function copyToClipboard(inputId) {
    const input = document.getElementById(inputId);
    navigator.clipboard.writeText(input.value);
    alert('Copiado!');
}

function toggleShow(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ─── Inicialização ──────────────────────────────────────────
if (IS_LIVE) {
    connectSSE();
    if (IS_WEBRTC) {
        // Tentar iniciar preview da câmera com áudio
        navigator.mediaDevices.getUserMedia({ video: true, audio: true })
            .then(stream => {
                const video = document.getElementById('localVideo');
                if (video) { video.srcObject = stream; video.play(); }
                const ph = document.getElementById('cameraPlaceholder');
                if (ph) ph.classList.add('d-none');
                localStream = stream;
            })
            .catch(() => {});
    }
}
