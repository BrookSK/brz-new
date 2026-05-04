<!-- Co-Piloto Admin (chat interno) -->
<div id="adminCopiloto" style="position:fixed;bottom:20px;right:20px;z-index:9999;">
    <button id="adminCopBtn" onclick="toggleAdminCop()" style="width:52px;height:52px;border-radius:50%;border:none;background:#0b1f3a;color:#fff;font-size:20px;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;" title="Co-Piloto Admin">
        <i class="fas fa-robot"></i>
    </button>
    <div id="adminCopChat" style="display:none;position:absolute;bottom:62px;right:0;width:380px;max-height:520px;background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18);overflow:hidden;flex-direction:column;">
        <div style="background:#0b1f3a;color:#fff;padding:12px 16px;font-weight:600;font-size:14px;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-robot me-2"></i>Co-Piloto Admin</span>
            <button onclick="toggleAdminCop()" style="background:none;border:none;color:#fff;font-size:16px;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div id="adminCopMsgs" style="flex:1;overflow-y:auto;padding:12px;min-height:300px;max-height:380px;font-size:13px;display:flex;flex-direction:column;gap:8px;"></div>
        <div style="border-top:1px solid #e2e8f0;padding:10px 12px;display:flex;gap:8px;">
            <input id="adminCopInput" type="text" placeholder="Pergunte algo..." style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 12px;font-size:13px;outline:none;" onkeydown="if(event.key==='Enter')enviarAdminCop()">
            <button onclick="enviarAdminCop()" style="background:#0b1f3a;color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:13px;"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>
<script>
if (typeof window.toggleAdminCop === 'undefined') {
(function(){
    var aberto = false;
    var historico = [];
    var enviando = false;
    try { historico = JSON.parse(sessionStorage.getItem('adm_cop_hist') || '[]'); } catch(e){ historico = []; }

    window.toggleAdminCop = function(){
        var chat = document.getElementById('adminCopChat');
        aberto = !aberto;
        chat.style.display = aberto ? 'flex' : 'none';
        if (aberto) {
            renderMsgs();
            document.getElementById('adminCopInput').focus();
            scrollBottom();
        }
    };

    function renderMsgs(){
        var el = document.getElementById('adminCopMsgs');
        if (!el) return;
        if (historico.length === 0) {
            el.innerHTML = '<div style="color:#94a3b8;text-align:center;padding:40px 0;">Pergunte sobre produtos, regras, processos...</div>';
            return;
        }
        var html = '';
        historico.forEach(function(m){
            var isUser = m.role === 'user';
            html += '<div style="align-self:' + (isUser ? 'flex-end' : 'flex-start') + ';max-width:85%;padding:8px 12px;border-radius:10px;background:' + (isUser ? '#0b1f3a' : '#f1f5f9') + ';color:' + (isUser ? '#fff' : '#0f172a') + ';white-space:pre-wrap;word-break:break-word;line-height:1.4;">' + escHtml(m.content) + '</div>';
        });
        el.innerHTML = html;
    }

    function scrollBottom(){
        var el = document.getElementById('adminCopMsgs');
        if (el) setTimeout(function(){ el.scrollTop = el.scrollHeight; }, 50);
    }

    function escHtml(s){
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    window.enviarAdminCop = function(){
        if (enviando) return;
        var inp = document.getElementById('adminCopInput');
        var msg = (inp.value || '').trim();
        if (!msg) return;
        inp.value = '';

        historico.push({role:'user', content: msg});
        renderMsgs();
        scrollBottom();

        var msgsEl = document.getElementById('adminCopMsgs');
        var typing = document.createElement('div');
        typing.style.cssText = 'align-self:flex-start;padding:8px 12px;border-radius:10px;background:#f1f5f9;color:#94a3b8;font-size:12px;';
        typing.textContent = 'Pensando...';
        msgsEl.appendChild(typing);
        scrollBottom();

        enviando = true;
        fetch('/api/copiloto/admin/chat', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({mensagem: msg, historico: historico.slice(-10)})
        })
        .then(function(r){
            if (!r.ok) {
                return r.text().then(function(t){
                    var p; try { p = JSON.parse(t); } catch(e){ p = null; }
                    if (p && p.resposta) return p;
                    throw new Error('HTTP ' + r.status + ': ' + (t || '').substring(0, 200));
                });
            }
            return r.json();
        })
        .then(function(data){
            typing.remove();
            var resp = data.resposta || 'Sem resposta.';
            historico.push({role:'assistant', content: resp});
            try { sessionStorage.setItem('adm_cop_hist', JSON.stringify(historico.slice(-20))); } catch(e){}
            renderMsgs();
            scrollBottom();
        })
        .catch(function(err){
            typing.remove();
            historico.push({role:'assistant', content: err && err.message ? err.message : 'Erro de conexão. Tente novamente.'});
            renderMsgs();
            scrollBottom();
        })
        .finally(function(){ enviando = false; });
    };

    if (historico.length > 0) renderMsgs();
})();
}
</script>
