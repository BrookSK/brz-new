/**
 * bri-sidebar-mode.js
 *
 * Módulo da interface /home-ia.
 * NÃO modifica nenhuma função do copiloto.js existente.
 * Responsabilidade: chat com a BRI + navegação no iframe.
 */

const BriSidebar = (() => {

  const frame   = document.getElementById('bri-frame');
  const loader  = document.getElementById('bri-painel-loader');
  const painel  = document.getElementById('bri-painel');
  const msgsEl  = document.getElementById('bri-mensagens');
  const input   = document.getElementById('bri-input');
  const sendBtn = document.getElementById('bri-send-btn');

  let historico = [];
  let enviando = false;

  // Restaurar histórico da sessão
  try { historico = JSON.parse(sessionStorage.getItem('bri_sidebar_hist') || '[]'); } catch(e) { historico = []; }

  // ── Loader ──────────────────────────────────────────
  function showLoader() { loader?.classList.add('visible'); }
  function hideLoader()  { loader?.classList.remove('visible'); }
  frame?.addEventListener('load', hideLoader);

  // ── Navegação no iframe ──────────────────────────────
  function navigatePainel(url) {
    if (!frame) return;
    showLoader();
    frame.src = url;
    if (window.innerWidth < 768) {
      painel?.classList.add('aberto');
    }
  }

  function fecharPainel() {
    painel?.classList.remove('aberto');
  }

  // ── Renderizar mensagens ────────────────────────────
  function renderMensagens() {
    if (!msgsEl) return;
    if (historico.length === 0) {
      msgsEl.innerHTML = '<div style="color:#94A3B8;text-align:center;padding:60px 20px;font-size:13px;">Olá! Sou a BRI, sua assistente.<br>Me pergunte qualquer coisa!</div>';
      return;
    }
    let html = '';
    historico.forEach(function(m) {
      const cls = m.role === 'user' ? 'user' : 'assistant';
      const time = m.time || '';
      html += '<div class="bri-bubble ' + cls + '">' + escHtml(m.content) + (time ? '<span class="bri-bubble-time">' + time + '</span>' : '') + '</div>';
    });
    msgsEl.innerHTML = html;
    scrollBottom();
  }

  function scrollBottom() {
    if (msgsEl) setTimeout(() => { msgsEl.scrollTop = msgsEl.scrollHeight; }, 50);
  }

  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function getTime() {
    const now = new Date();
    return now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
  }

  // ── Enviar mensagem ─────────────────────────────────
  function enviar() {
    if (enviando) return;
    const msg = (input.value || '').trim();
    if (!msg) return;
    input.value = '';
    input.style.height = 'auto';

    historico.push({ role: 'user', content: msg, time: getTime() });
    renderMensagens();

    // Typing indicator
    const typing = document.createElement('div');
    typing.className = 'bri-typing';
    typing.innerHTML = '<span></span><span></span><span></span>';
    msgsEl.appendChild(typing);
    scrollBottom();

    enviando = true;
    sendBtn.disabled = true;

    fetch('/api/copiloto/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mensagem: msg,
        historico: historico.filter(m => m.role === 'user' || m.role === 'assistant').slice(-10),
        contexto: {
          pagina: 'home_ia',
          url_atual: '/home-ia',
          usuario_logado: document.cookie.indexOf('PHPSESSID') !== -1,
          moeda_atual: 'USD',
          iframe_url: frame ? frame.src : ''
        }
      })
    })
    .then(r => {
      if (!r.ok) return r.json().then(d => { throw new Error(d.resposta || 'Erro ' + r.status); });
      return r.json();
    })
    .then(data => {
      typing.remove();
      const resp = data.resposta || 'Sem resposta.';
      historico.push({ role: 'assistant', content: resp, time: getTime() });
      salvarHistorico();
      renderMensagens();

      // Processar ação no iframe
      if (data.acao_frontend) {
        handleAcao(data.acao_frontend);
      }
    })
    .catch(err => {
      typing.remove();
      historico.push({ role: 'assistant', content: err.message || 'Erro de conexão.', time: getTime() });
      renderMensagens();
    })
    .finally(() => {
      enviando = false;
      sendBtn.disabled = false;
    });
  }

  function salvarHistorico() {
    try { sessionStorage.setItem('bri_sidebar_hist', JSON.stringify(historico.slice(-30))); } catch(e) {}
  }

  // ── Mapeamento de ações → URL do iframe ─────────────
  function handleAcao(acao) {
    if (!frame || !acao) return;
    const { tipo, parametros } = acao;
    if (!tipo || tipo === 'nenhuma') return;

    const p = parametros || {};

    // Ações silenciosas — executar via API sem mudar o iframe
    const acoesSilenciosas = {
      adicionar_carrinho: () => {
        const prodId = p.produto_id || p.id;
        if (!prodId) return;
        fetch('/api/copiloto/addcart', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ produto_id: prodId, quantidade: p.quantidade || 1 })
        }).then(r => r.json()).then(d => {
          if (d.success) {
            // Atualizar iframe se estiver no carrinho
            if (frame.src && frame.src.includes('/carrinho')) {
              frame.src = frame.src; // reload
            }
          }
        }).catch(() => {});
      },
      limpar_carrinho: () => {
        fetch('/api/copiloto/clearcart', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        }).then(r => r.json()).then(d => {
          if (d.success && frame.src && frame.src.includes('/carrinho')) {
            frame.src = frame.src;
          }
        }).catch(() => {});
      },
      criar_ticket_suporte: () => {
        fetch('/api/copiloto/ticket', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ assunto: p.assunto || 'Suporte via BRI', mensagem: p.mensagem || '' })
        }).catch(() => {});
      },
      trocar_moeda_brl: () => { /* handled by currency converter */ },
      trocar_moeda_usd: () => { /* handled by currency converter */ },
      abrir_whatsapp_vendas: () => {
        const whatsapp = '5517996203062';
        window.open('https://wa.me/' + whatsapp + (p.mensagem ? '?text=' + encodeURIComponent(p.mensagem) : ''), '_blank');
      }
    };

    if (acoesSilenciosas[tipo]) {
      acoesSilenciosas[tipo]();
      return;
    }

    // Ações de navegação — mudar o iframe
    const rotas = {
      buscar_produto: () => {
        const termo = p.termo || p.busca || '';
        const slug = p.grupo_slug || p.slug || '';
        if (slug) return '/grupo/' + slug + '?embed=1';
        // Buscar em produtos + grupos (a página /produtos exclui grupos, então usar /grupos-compras também)
        return '/produtos?search=' + encodeURIComponent(termo) + '&ver_todos=1&embed=1';
      },
      ir_para_carrinho: () => '/carrinho?embed=1',
      ir_para_checkout: () => '/carrinho/checkout?embed=1',
      ir_para_grupo: () => '/grupo/' + (p.slug || '') + '?embed=1',
      ir_para_clube: () => '/clube/recarga?embed=1',
      ir_para_contato: () => '/contato?embed=1',
      ir_para_meus_dados: () => '/meus-dados?embed=1',
      ir_para_assessoria: () => '/assessoria?embed=1',
      consultar_status_pedido: () => '/minha-conta?embed=1',
      gerar_orcamento: () => {
        const links = p.links || [];
        if (links.length > 0 && typeof links[0] === 'string') {
          return links[0] + (links[0].includes('?') ? '&' : '?') + 'embed=1';
        }
        return '/assessoria?embed=1';
      },
      navegar: () => {
        let url = p.url || p.pagina || '/';
        if (url.indexOf('/') !== 0) url = '/' + url;
        if (url.match(/^\/admin/i)) return null;
        return url + (url.includes('?') ? '&' : '?') + 'embed=1';
      }
    };

    if (rotas[tipo]) {
      const url = rotas[tipo]();
      if (url) navigatePainel(url);
    }
  }

  // ── Auto-resize textarea ────────────────────────────
  input?.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  });

  // Enter para enviar, Shift+Enter para nova linha
  input?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      enviar();
    }
  });

  sendBtn?.addEventListener('click', enviar);

  // ── Init ────────────────────────────────────────────
  renderMensagens();

  // Expor para uso externo
  function limparChat() {
    historico = [];
    try { sessionStorage.removeItem('bri_sidebar_hist'); } catch(e) {}
    renderMensagens();
  }

  return { handleAcao, fecharPainel, navigatePainel, limparChat };

})();
