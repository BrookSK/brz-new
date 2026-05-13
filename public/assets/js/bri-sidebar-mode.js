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

  frame?.addEventListener('load', () => {
    hideLoader();
    try {
      const iframeDoc = frame.contentDocument || frame.contentWindow.document;
      const iframeWin = frame.contentWindow;
      if (!iframeDoc || !iframeWin) return;

      // Se a URL do iframe não tem embed=1, recarregar com embed=1
      const iframeUrl = iframeWin.location.href;
      const iframePath = iframeWin.location.pathname + iframeWin.location.search;
      if (!iframePath.includes('embed=1') && iframePath !== '/bri/inicio') {
        const sep = iframePath.includes('?') ? '&' : '?';
        frame.src = iframePath + sep + 'embed=1';
        return;
      }

      // Interceptar window.location changes dentro do iframe
      const origAssign = iframeWin.location.assign?.bind(iframeWin.location);
      Object.defineProperty(iframeWin, '__briNavigate', { value: function(url) {
        if (url && !url.includes('embed=1') && url.startsWith('/')) {
          url = url + (url.includes('?') ? '&' : '?') + 'embed=1';
        }
        frame.src = url;
      }, writable: false });

      // Override location.href setter via script injection
      const script = iframeDoc.createElement('script');
      script.textContent = `
        (function(){
          // Patch APENAS links de navegação que usam window.location.href
          // NÃO interceptar botões de add-to-cart, forms, ou ações de produto
          document.addEventListener('click', function(e) {
            var el = e.target.closest('a[onclick*="location.href"]');
            if (!el) return;
            // Não interceptar se é botão de carrinho/produto
            var onclick = el.getAttribute('onclick') || '';
            if (onclick.indexOf('carrinho') !== -1 || onclick.indexOf('cart') !== -1) return;
            if (onclick.indexOf('location.href') !== -1) {
              var match = onclick.match(/location\\.href\\s*=\\s*['"](.*?)['"]/);
              if (match && match[1] && match[1].indexOf('embed=1') === -1) {
                e.preventDefault();
                e.stopPropagation();
                var url = match[1];
                window.location.href = url + (url.indexOf('?') !== -1 ? '&' : '?') + 'embed=1';
              }
            }
          }, true);
        })();
      `;
      iframeDoc.head.appendChild(script);

      // Interceptar cliques em links de NAVEGAÇÃO (não botões de ação)
      iframeDoc.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('http')) return;
        // Não interceptar links de ação (add-to-cart, POST actions, etc.)
        if (link.closest('form') || link.getAttribute('onclick') || link.classList.contains('btn-primary') || href.includes('/adicionar') || href.includes('/add')) return;
        if (!href.includes('embed=1')) {
          e.preventDefault();
          e.stopPropagation();
          frame.src = href + (href.includes('?') ? '&' : '?') + 'embed=1';
        }
      }, true);

      // Interceptar form submits GET
      iframeDoc.addEventListener('submit', (e) => {
        const form = e.target;
        if (form && form.method.toUpperCase() !== 'POST' && !form.querySelector('input[name="embed"]')) {
          const input = iframeDoc.createElement('input');
          input.type = 'hidden'; input.name = 'embed'; input.value = '1';
          form.appendChild(input);
        }
      }, true);

    } catch(e) { /* cross-origin — ignore */ }
  });

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

    // === MODO NAVEGAÇÃO RÁPIDA ===
    // Apenas comandos CURTOS e DIRETOS são interceptados
    // Frases longas ou complexas vão para a IA
    const msgLower = msg.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const palavras = msg.trim().split(/\s+/).length;
    
    // Carrinho (apenas comandos diretos)
    if (msgLower.match(/^(carrinho|meu carrinho|ver carrinho|me mostr[ea] meu carrinho|abre? o? ?carrinho)$/)) {
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Ok, vamos lá! 🛒', time: getTime() });
      salvarHistorico(); renderMensagens();
      navigatePainel('/carrinho?embed=1');
      return;
    }
    // Checkout (apenas comandos diretos)
    if (msgLower.match(/^(checkout|finalizar compra|ir pro checkout|fechar pedido)$/)) {
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Te levo pro checkout! 🔒', time: getTime() });
      salvarHistorico(); renderMensagens();
      navigatePainel('/carrinho/checkout?embed=1');
      return;
    }
    // Produtos / Home (apenas comandos diretos)
    if (msgLower.match(/^(produtos|catalogo|ver produtos|home|inicio)$/)) {
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Aqui estão os produtos! 🛍️', time: getTime() });
      salvarHistorico(); renderMensagens();
      navigatePainel('/produtos?embed=1');
      return;
    }
    // Grupos de compras (apenas comandos diretos)
    if (msgLower.match(/^(grupos|grupos de compras|ver grupos)$/)) {
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Aqui estão os grupos disponíveis!', time: getTime() });
      salvarHistorico(); renderMensagens();
      navigatePainel('/grupos-compras?embed=1');
      return;
    }
    // Minha conta (apenas comandos diretos)
    if (msgLower.match(/^(minha conta|meus pedidos|meu perfil)$/)) {
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Abrindo sua conta!', time: getTime() });
      salvarHistorico(); renderMensagens();
      navigatePainel('/minha-conta?embed=1');
      return;
    }
    // Clube (apenas comandos diretos)
    if (msgLower.match(/^(clube|recarga|meu saldo|ver clube)$/)) {
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Vamos pro Clube! 💎', time: getTime() });
      salvarHistorico(); renderMensagens();
      navigatePainel('/clube/recarga?embed=1');
      return;
    }

    // === Para tudo que não é navegação direta, enviar à IA normalmente ===
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
      credentials: 'same-origin',
      body: JSON.stringify({
        mensagem: msg,
        historico: historico.filter(m => m.role === 'user' || m.role === 'assistant').slice(-10),
        contexto: {
          pagina: 'home_ia',
          url_atual: '/home-ia',
          usuario_logado: document.cookie.indexOf('PHPSESSID') !== -1,
          moeda_atual: 'USD',
          iframe_url: frame ? frame.src : '',
          // Informar à BRI que o iframe está ativo e pode navegar
          modo_sidebar: true
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

      // Processar ação no iframe — APENAS quando a API retorna ação explícita
      if (data.acao_frontend && data.acao_frontend.tipo && data.acao_frontend.tipo !== 'nenhuma') {
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
          credentials: 'same-origin',
          body: JSON.stringify({ produto_id: prodId, quantidade: p.quantidade || 1 })
        }).then(r => r.json()).then(d => {
          if (d.success && frame.src && frame.src.includes('/carrinho')) {
            frame.src = frame.src;
          }
        }).catch(() => {});
      },
      limpar_carrinho: () => {
        fetch('/api/copiloto/clearcart', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin'
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
          credentials: 'same-origin',
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
