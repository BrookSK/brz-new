/**
 * Live Chat — Cliente
 */

// ─── Enviar Mensagem ────────────────────────────────────────
async function sendChat() {
    const input = document.getElementById('chatInput');
    if (!input) return;

    const content = input.value.trim();
    if (!content) return;

    input.value = '';
    input.disabled = true;

    try {
        const res = await fetch(`/api/live/${LIVE_ID}/chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.error || 'Erro ao enviar', 'error');
            input.value = content; // Restaurar
        }
    } catch (e) {
        showToast('Erro de conexão', 'error');
        input.value = content;
    }

    input.disabled = false;
    input.focus();
}

// ─── Renderizar Mensagem ────────────────────────────────────
function renderChatMessage(msg) {
    const container = document.getElementById('chatMessages');
    if (!container) return;

    const div = document.createElement('div');
    div.className = 'chat-msg';
    
    const userName = msg.user_name || msg.user_name_alt || 'Anônimo';
    div.innerHTML = `<span class="chat-user">${escapeHtml(userName)}</span>${escapeHtml(msg.content)}`;
    
    container.appendChild(div);

    // Auto-scroll
    container.scrollTop = container.scrollHeight;

    // Limitar mensagens visíveis (performance)
    while (container.children.length > 50) {
        container.removeChild(container.firstChild);
    }
}
