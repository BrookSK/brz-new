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
      const iframePath = iframeWin.location.pathname + iframeWin.location.search;
      if (!iframePath.includes('embed=1') && !iframePath.includes('/bri/inicio')) {
        const sep = iframePath.includes('?') ? '&' : '?';
        frame.src = iframePath + sep + 'embed=1';
        return;
      }

      // Adicionar embed=1 a TODOS os links <a> que são navegação pura
      // NÃO tocar em botões, forms, ou elementos com onclick/AJAX
      iframeDoc.querySelectorAll('a[href]').forEach(a => {
        const href = a.getAttribute('href') || '';
        // Ignorar: links externos, anchors, javascript, links que já têm embed
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('http') || href.includes('embed=1')) return;
        // Ignorar: botões de ação (add to cart, remover, etc.)
        if (a.getAttribute('onclick')) return;
        if (a.closest('form')) return;
        if (a.classList.contains('btn-primary') || a.classList.contains('btn-danger') || a.classList.contains('btn-success')) return;
        if (href.includes('/adicionar') || href.includes('/remover') || href.includes('/toggle') || href.includes('/limpar')) return;
        // Adicionar embed=1
        a.setAttribute('href', href + (href.includes('?') ? '&' : '?') + 'embed=1');
      });

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
    // Mapa completo de comandos diretos → URLs
    const msgLower = msg.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    
    const navMap = [
      // Carrinho & Checkout
      { regex: /carrinho|meu carrinho|ver carrinho|cart/, url: () => '/carrinho?embed=1&_=' + Date.now(), reply: 'Ok, vamos lá! 🛒' },
      { regex: /^(checkout|finalizar compra|ir pro checkout|fechar pedido|finalizar)$/, url: '/carrinho/checkout?embed=1', reply: 'Te levo pro checkout! 🔒' },
      // Produtos & Catálogo
      { regex: /^(produtos|catalogo|ver produtos|todos os produtos|me mostr.*produtos)$/, url: '/produtos?embed=1', reply: 'Aqui estão os produtos! 🛍️' },
      { regex: /^(grupos|ver grupos|me mostr.*grupo)$/, url: '/grupos-compras?embed=1', reply: 'Aqui estão os grupos!' },
      // Conta & Dados
      { regex: /minha conta|meus dados|me mostr[ea] minha conta|me mostr[ea] meus dados|dados da minha conta|perfil/, url: '/meus-dados?embed=1', reply: 'Abrindo seus dados!' },
      { regex: /meus pedidos|ver pedidos|historico de pedidos|meus compras|me mostr.*pedidos/, url: '/meus-pedidos?embed=1', reply: 'Aqui estão seus pedidos!' },
      { regex: /meus enderecos|enderecos|ver enderecos/, url: '/meus-enderecos?embed=1', reply: 'Seus endereços!' },
      { regex: /meus tickets|tickets|suporte|atendimento/, url: '/meus-tickets?embed=1', reply: 'Seus tickets de suporte!' },
      // Clube
      { regex: /^(clube|recarga|meu saldo|ver clube|clube brasiliana|me mostr.*clube)$/, url: '/clube/recarga?embed=1', reply: 'Vamos pro Clube! 💎' },
      { regex: /como funciona o clube|sobre o clube/, url: '/como-funciona-clube?embed=1', reply: 'Explicando o Clube!' },
      { regex: /produtos do clube|produtos clube/, url: '/produtos-clube?embed=1', reply: 'Produtos exclusivos do Clube!' },
      // Informações
      { regex: /^(faq|perguntas frequentes|duvidas)$/, url: '/faq?embed=1', reply: 'Aqui está o FAQ!' },
      { regex: /como funciona|como comprar/, url: '/como-funciona?embed=1', reply: 'Veja como funciona!' },
      { regex: /^(contato|falar com alguem|fale conosco)$/, url: '/contato?embed=1', reply: 'Página de contato!' },
      { regex: /rastreamento|rastrear|rastreio|tracking/, url: '/rastreamento?embed=1', reply: 'Rastreamento de pedidos!' },
      { regex: /status.*(pedido|compra)|acompanhar pedido/, url: '/status-pedido?embed=1', reply: 'Status dos pedidos!' },
      { regex: /cobranca|calcular cobranca|simulador/, url: '/cobranca?embed=1', reply: 'Calculadora de cobrança!' },
      // Assessoria / Redirecionamento
      { regex: /assessoria|redirecionamento|redirecionar|compra por link/, url: '/assessoria?embed=1', reply: 'Abrindo a assessoria! 📦' },
      // Orçamento - instrução de como fazer
      { regex: /orcamento|orçamento|quanto fica|quanto custa tudo|simular|simulacao/, url: null, reply: 'Para montar seu orçamento é simples! 📋\n\n1. Me diga o produto que procura (ex: "tineco", "pipoca")\n2. Vou buscar pra você no painel ao lado\n3. Clique em "Add to cart" nos itens que quiser\n4. Repita até adicionar tudo\n5. Quando terminar, diga "carrinho" — lá terá o valor total com taxas e frete!\n\nQual produto quer buscar primeiro?' },
      // Políticas
      { regex: /politica.*(privacidade)|privacidade/, url: '/politica-privacidade?embed=1', reply: 'Política de privacidade.' },
      { regex: /termos.*(uso)|termos/, url: '/termos-uso?embed=1', reply: 'Termos de uso.' },
      // Home
      { regex: /^(home|inicio|pagina inicial)$/, url: '/?embed=1', reply: 'Voltando pro início!' },
    ];

    for (const nav of navMap) {
      if (nav.regex.test(msgLower)) {
        historico.push({ role: 'user', content: msg, time: getTime() });
        historico.push({ role: 'assistant', content: nav.reply, time: getTime() });
        salvarHistorico(); renderMensagens();
        if (nav.url) {
          navigatePainel(typeof nav.url === 'function' ? nav.url() : nav.url);
        }
        return;
      }
    }

    // Detecção de busca de produto: mensagens curtas (1-4 palavras) que parecem nomes de produto/marca
    const palavras = msg.trim().split(/\s+/);

    // Detectar perguntas sobre "qual grupo tem X" / "em qual grupo encontro X"
    const grupoMatch = msgLower.match(/(?:qual|em qual|que|quais)\s+grupo.*(?:tem|encontro|acho|vende|has|vejo)\s+(.+)/);
    const grupoMatch2 = msgLower.match(/(?:tem|encontro|acho)\s+(.+?)\s+(?:em qual|em que|qual)\s+grupo/);
    const grupoMatch3 = msgLower.match(/grupo.*(?:tem|vende|encontro)\s+(.+)/);
    const termoProdutoGrupo = grupoMatch ? grupoMatch[1].replace(/\?/g,'').trim() : (grupoMatch2 ? grupoMatch2[1].replace(/\?/g,'').trim() : (grupoMatch3 ? grupoMatch3[1].replace(/\?/g,'').trim() : null));
    
    if (termoProdutoGrupo) {
      const traducoes2 = {pipoca:'popcorn',pipocas:'popcorn',esponja:'sponge',sabonete:'soap',chocolate:'chocolate',vitamina:'vitamin',cafe:'coffee',biscoito:'cookie',banho:'bath',limpeza:'cleaning',vela:'candle'};
      let searchTerm = termoProdutoGrupo;
      for (const [pt, en] of Object.entries(traducoes2)) {
        if (termoProdutoGrupo.includes(pt)) { searchTerm = en; break; }
      }
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Buscando "' + termoProdutoGrupo + '" nos grupos... 🔍', time: getTime() });
      salvarHistorico(); renderMensagens();
      // Navegar para busca de produtos (inclui produtos de grupos ativos)
      navigatePainel('/produtos?search=' + encodeURIComponent(searchTerm) + '&ver_todos=1&embed=1');
      
      setTimeout(() => {
        historico.push({ role: 'assistant', content: 'Aqui estão os resultados! Os produtos ao lado são dos grupos de compras ativos. Clique em "Add to cart" para adicionar ao seu carrinho. 🛒', time: getTime() });
        salvarHistorico(); renderMensagens();
      }, 2500);
      return;
    }

    if (palavras.length <= 4 && !msgLower.match(/^(oi|ola|obrigad|como|porque|quando|onde|quem|ajuda|help)(\s|$)/)) {
      // Se não é uma pergunta/saudação, tratar como busca de produto
      const traducoes = {pipoca:'popcorn',pipocas:'popcorn',esponja:'sponge',panela:'pan',sabonete:'soap',detergente:'dish soap',aspirador:'vacuum',vitamina:'vitamin',fralda:'diaper',chocolate:'chocolate','cafe':'coffee',biscoito:'cookie',sorveteira:'ice cream maker',sorvete:'ice cream',liquidificador:'blender',batedeira:'mixer',cafeteira:'coffee maker',frigideira:'frying pan',airfryer:'air fryer',fritadeira:'air fryer',banho:'bath',corpo:'body',cabelo:'hair',rosto:'face',pele:'skin',limpeza:'cleaning',cozinha:'kitchen',banheiro:'bathroom',roupa:'laundry',bebe:'baby','bebê':'baby',perfume:'perfume',vela:'candle',toalha:'towel',sabao:'soap',creme:'cream',loção:'lotion'};
      let searchTerm = msg.trim();
      for (const [pt, en] of Object.entries(traducoes)) {
        if (msgLower.includes(pt)) { searchTerm = en; break; }
      }
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Buscando ' + msg + '... 🔍', time: getTime() });
      salvarHistorico(); renderMensagens();
      navigatePainel('/produtos?search=' + encodeURIComponent(searchTerm) + '&ver_todos=1&embed=1');

      // Busca inteligente: após 3s, verificar se o iframe encontrou resultados
      // Se não encontrou, chamar a IA para sugerir termos alternativos
      setTimeout(() => {
        try {
          const iframeDoc = frame.contentDocument || frame.contentWindow.document;
          const noResults = iframeDoc && (
            iframeDoc.querySelector('.text-muted')?.textContent?.includes('No products') ||
            iframeDoc.querySelector('.text-muted')?.textContent?.includes('Nenhum produto') ||
            iframeDoc.querySelectorAll('.product-card, .card').length === 0
          );
          if (noResults) {
            historico.push({ role: 'assistant', content: 'Não encontrei "' + msg + '" diretamente. Deixa eu pensar em alternativas... 🤔', time: getTime() });
            salvarHistorico(); renderMensagens();
            // Chamar IA para sugerir termos relacionados
            fetch('/api/copiloto/chat', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'same-origin',
              body: JSON.stringify({
                mensagem: 'O cliente buscou "' + msg + '" mas não encontrou. Sugira 3 termos de busca em inglês que possam encontrar produtos relacionados no catálogo. Responda APENAS com os termos separados por vírgula, sem explicação. Ex: ice cream maker,frozen dessert,ninja creami',
                historico: [],
                contexto: { pagina: 'home_ia_search_fallback', url_atual: '/home-ia' }
              })
            })
            .then(r => r.json())
            .then(data => {
              const sugestoes = (data.resposta || '').split(',').map(s => s.trim()).filter(s => s.length > 1);
              if (sugestoes.length > 0) {
                // Tentar o primeiro termo sugerido
                const melhorTermo = sugestoes[0];
                historico.push({ role: 'assistant', content: 'Encontrei uma possibilidade! Buscando por "' + melhorTermo + '"... 🔍', time: getTime() });
                salvarHistorico(); renderMensagens();
                navigatePainel('/produtos?search=' + encodeURIComponent(melhorTermo) + '&ver_todos=1&embed=1');
              } else {
                historico.push({ role: 'assistant', content: 'Não encontrei produtos similares. Tente outro termo ou navegue pelos grupos de compras!', time: getTime() });
                salvarHistorico(); renderMensagens();
              }
            })
            .catch(() => {});
          }
        } catch(e) { /* cross-origin or timing issue */ }
      }, 3000);

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
          usuario_logado: !!(document.querySelector('meta[name="bri-user-id"]')?.content > '0'),
          usuario_id_meta: parseInt(document.querySelector('meta[name="bri-user-id"]')?.content || '0'),
          moeda_atual: 'USD',
          iframe_url: frame ? frame.src : '',
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
        console.log('[BRI] Ação recebida:', data.acao_frontend.tipo, data.acao_frontend.parametros);
        handleAcao(data.acao_frontend);
      } else {
        console.log('[BRI] Sem ação. Tipo:', data.acao_frontend?.tipo);
        // Fallback: se a BRI disse que vai navegar mas não enviou ação
        const respL = resp.toLowerCase();
        if (respL.includes('meus dados') || respL.includes('minha conta')) {
          navigatePainel('/meus-dados?embed=1');
        } else if (respL.includes('carrinho') && (respL.includes('vou') || respL.includes('bora') || respL.includes('levo'))) {
          navigatePainel('/carrinho?embed=1');
        }
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
