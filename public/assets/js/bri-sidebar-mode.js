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
      if (!iframePath.includes('embed=1') && !iframePath.includes('/bri/inicio') && !iframePath.includes('/checkout') && !iframePath.includes('/carrinho/checkout')) {
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
  }

  function fecharPainel() {
    // No-op on mobile (no sliding panel anymore)
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

    // === CAMADA 0: resposta curta em contexto ===
    // Se a BRI fez uma pergunta e o usuário responde curto, manda direto pra IA
    const _palavras = msgLower.trim().split(/\s+/).filter(Boolean);
    const _ehCurta = _palavras.length <= 3;
    const _ehConfirmacao = /^(sim|nao|não|ok|isso|esse|essa|aquele|aquela|o primeiro|o segundo|o terceiro|o ultimo|nenhum|todos|pode|vai|manda|beleza|fechou|uhum|isso ai|esse mesmo|nao quero|deixa pra la)$/i.test(msgLower.trim());
    // Não interceptar se contém palavras-chave de navegação
    const _temNavKeyword = /ticket|carrinho|checkout|produtos|grupos|conta|pedidos|enderecos|clube|assessoria|rastreamento|como funciona/.test(msgLower);
    if ((_ehCurta || _ehConfirmacao) && !_temNavKeyword) {
      const _ultimaBRI = [...historico].reverse().find(m => m.role === 'assistant');
      if (_ultimaBRI && /\?\s*$/.test((_ultimaBRI.content || _ultimaBRI.text || '').trim())) {
        // BRI fez pergunta no turno anterior — bypass cascata, manda pra IA com contexto
        historico.push({ role: 'user', content: msg, time: getTime() });
        addMsg(msg, 'user');
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
              modo_sidebar: true,
              resposta_curta_contexto: true
            }
          })
        }).then(r => r.json()).then(data => {
          typing.remove();
          enviando = false;
          sendBtn.disabled = false;
          if (data && data.resposta) {
            historico.push({ role: 'assistant', content: data.resposta, time: getTime() });
            addMsg(data.resposta, 'bot');
            if (data.acao && acoes[data.acao]) {
              const url = acoes[data.acao](data);
              if (url) navigatePainel(url);
            }
          }
        }).catch(() => { typing.remove(); enviando = false; sendBtn.disabled = false; });
        return;
      }
    }
    // === FIM CAMADA 0 ===

    // Safety: se enviando ficou true por mais de 30s, resetar
    if (enviando) { enviando = false; sendBtn.disabled = false; }
    
    const navMap = [
      // Carrinho & Checkout
      { regex: /^(carrinho|meu carrinho|ver(?: o| meu)? carrinho|abrir(?: o)? carrinho|mostra(?:r)?(?: o)? carrinho|cart)\??$/, url: () => '/carrinho?embed=1&_=' + Date.now(), reply: 'Ok, vamos lá! 🛒\n\nNo carrinho você vê todos os itens, valores com taxas e frete. Pode alterar quantidades ou remover itens.' },
      { regex: /^(checkout|finalizar compra|ir pro checkout|fechar pedido|finalizar)$/, url: '/carrinho/checkout?embed=1', reply: 'Te levo pro checkout! 🔒\n\nPreencha seus dados e escolha a forma de pagamento para finalizar.' },
      // Produtos & Catálogo
      { regex: /^(produtos|catalogo|ver produtos|todos os produtos|me mostr.*produtos)$/, url: '/produtos?embed=1', reply: 'Aqui estão os produtos! 🛍️\n\nNavegue, use o campo de busca ou filtre por categoria. Clique em "Add to cart" para adicionar.' },
      { regex: /^(grupos|ver grupos|me mostr.*grupo|grupos? de compras?|grupo de compra|me mostr.*grupos? abertos?|grupos? abertos?)$/, url: '/grupos-compras?embed=1', reply: 'Aqui estão os grupos! 🏪\n\nGrupos de compras são compras coletivas com preços especiais. Clique em "Ver produtos" para explorar cada grupo.' },
      // Conta & Dados
      { regex: /minha conta|meus dados|me mostr[ea] minha conta|me mostr[ea] meus dados|dados da minha conta|perfil/, url: '/meus-dados?embed=1', reply: 'Abrindo seus dados! 👤\n\nAqui você edita nome, email, telefone, CPF e foto de perfil.' },
      { regex: /meus pedidos|ver pedidos|historico de pedidos|meus compras|me mostr.*pedidos/, url: '/meus-pedidos?embed=1', reply: 'Aqui estão seus pedidos! 📦\n\nVeja o status de cada compra, rastreamento e detalhes.' },
      { regex: /meus enderecos|enderecos|ver enderecos/, url: '/meus-enderecos?embed=1', reply: 'Seus endereços! 📍\n\nAdicione ou edite endereços de entrega para agilizar suas compras.' },
      { regex: /meus tickets|tickets|suporte|atendimento|abrir.*ticket|como.*abr.*ticket|criar ticket|novo ticket/, url: '/meus-tickets?embed=1', reply: 'Para abrir um ticket de suporte:\n\n1. Acesse a página "Meus Tickets" (vou abrir ao lado)\n2. Clique no botão "Novo Ticket"\n3. Escolha o assunto e descreva sua dúvida\n4. Envie e aguarde — o time responde em até 24h\n\nSe preferir, pode me dizer sua dúvida aqui que eu tento resolver agora! 😊' },
      // Clube
      { regex: /^(clube|recarga|meu saldo|ver clube|clube brasiliana|me mostr.*clube)$/, url: '/clube/recarga?embed=1', reply: 'Vamos pro Clube! 💎\n\nFaça recargas para ter saldo e aproveitar benefícios exclusivos.' },
      { regex: /como funciona o clube|sobre o clube/, url: '/como-funciona-clube?embed=1', reply: 'Explicando o Clube! ℹ️\n\nVeja como funciona o sistema de recargas e benefícios.' },
      { regex: /produtos do clube|produtos clube/, url: '/produtos-clube?embed=1', reply: 'Produtos exclusivos do Clube! ⭐' },
      // Informações
      { regex: /^(faq|perguntas frequentes|duvidas)$/, url: '/faq?embed=1', reply: 'Aqui está o FAQ! ❓\n\nRespostas para as dúvidas mais comuns sobre compras, envio e pagamento.' },
      { regex: /^(como funciona|como comprar)\??$/, url: '/como-funciona?embed=1', reply: 'Veja como funciona! 📖\n\nPasso a passo de como comprar e receber seus produtos dos EUA.' },
      { regex: /^(contato|falar com alguem|fale conosco)$/, url: '/contato?embed=1', reply: 'Página de contato! 📞' },
      { regex: /rastreamento|rastrear|rastreio|tracking/, url: '/rastreamento?embed=1', reply: 'Rastreamento de pedidos! 🚚\n\nCole seu código de rastreio para acompanhar a entrega.' },
      { regex: /status.*(pedido|compra)|acompanhar pedido/, url: '/status-pedido?embed=1', reply: 'Status dos pedidos!' },
      { regex: /cobranca|calcular cobranca|simulador/, url: '/cobranca?embed=1', reply: 'Calculadora de cobrança!' },
      // Comprar de outros sites / Assessoria / Envio
      { regex: /comprar de outro|comprar.*outro site|posso comprar.*amazon|posso comprar.*walmart|posso comprar.*target|comprar.*eua|importar|trazer dos eua|mandar.*brasil/, url: '/assessoria?embed=1', reply: 'Sim! Você pode comprar de qualquer loja dos EUA! 🇺🇸\n\nNa página ao lado (Assessoria), cole os links dos produtos que quer e nós geramos um orçamento completo com frete e impostos.\n\nFunciona com Amazon, Walmart, Target, qualquer loja americana!' },
      // Perguntas sobre envio/entrega (apenas genéricas, sem produto específico)
      { regex: /^(voces enviam|vocês enviam|enviam pro brasil|entregam no brasil|fazem entrega|mandam pro brasil)\s*\??$/, url: null, reply: 'Sim! Enviamos praticamente tudo dos EUA para o Brasil! 📦\n\nO frete é calculado pelo peso total. Para saber o valor exato:\n1. Adicione os produtos ao carrinho\n2. Diga "carrinho" — lá mostra o valor total com frete\n\nOu use a "assessoria" para orçar produtos de qualquer loja americana!' },
      // Assessoria / Redirecionamento
      { regex: /assessoria|redirecionamento|redirecionar|compra por link/, url: '/assessoria?embed=1', reply: 'Abrindo a assessoria! 📦\n\nAqui você cola os links de produtos de qualquer loja dos EUA e nós calculamos o orçamento completo (produto + frete + impostos).' },
      // Orçamento - instrução de como fazer
      { regex: /orcamento|orçamento|quanto fica|quanto custa tudo|simular|simulacao/, url: null, reply: 'Para montar seu orçamento é simples! 📋\n\n1. Me diga o produto que procura (ex: "tineco", "pipoca")\n2. Vou buscar pra você no painel ao lado\n3. Clique em "Add to cart" nos itens que quiser\n4. Repita até adicionar tudo\n5. Quando terminar, diga "carrinho" — lá terá o valor total com taxas e frete!\n\nQual produto quer buscar primeiro?', guard: (m) => /^(orcamento|orçamento|quanto fica\??|quanto custa tudo|simular|simulacao)$/.test(m) },
      // Políticas
      { regex: /politica.*(privacidade)|privacidade/, url: '/politica-privacidade?embed=1', reply: 'Política de privacidade.' },
      { regex: /termos.*(uso)|termos/, url: '/termos-uso?embed=1', reply: 'Termos de uso.' },
      // Home
      { regex: /^(home|inicio|pagina inicial)$/, url: '/?embed=1', reply: 'Voltando pro início!' },
    ];

    for (const nav of navMap) {
      if (nav.regex.test(msgLower) && (!nav.guard || nav.guard(msgLower))) {
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
    
    // Ignorar se o termo extraído é genérico (não é um produto específico)
    const termosIgnorar = ['', 'grupo', 'grupos', 'compras', 'de compras', 'aberto', 'abertos', 'ativo', 'ativos', 'grupo de compras', 'grupos de compras'];
    if (termoProdutoGrupo && !termosIgnorar.includes(termoProdutoGrupo.toLowerCase())) {
      const traducoes2 = {pipoca:'popcorn',pipocas:'popcorn',esponja:'sponge',sabonete:'soap',chocolate:'chocolate',vitamina:'vitamin',cafe:'coffee',biscoito:'cookie',banho:'bath',limpeza:'cleaning',vela:'candle'};
      // Limpar artigos, preposições e pontuação do termo
      let searchTerm = termoProdutoGrupo.replace(/^(o|a|os|as|um|uma|uns|umas|do|da|dos|das|de|no|na|nos|nas|pro|pra|para|esse|essa|aquele|aquela)\s+/gi, '').replace(/\b(um|uma|o|a|os|as|do|da|de|no|na)\b/gi, '').replace(/\s+/g, ' ').replace(/[?!.,;:]+$/g, '').trim();
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

    // Detectar perguntas sobre preço/entrega de produto específico (ex: "o aspirador da tineco fica quantos p entrega")
    const precoMatch = msgLower.match(/(?:quanto|quantos?|fica|custa|sai|valor).*(?:entrega|envio|frete|brasil|br)\b/);
    const produtoNaFrase = msgLower.match(/(?:tineco|dyson|ninja|kitchenaid|vitamix|iphone|samsung|apple|costco|walmart)/i);
    if (precoMatch && produtoNaFrase) {
      let searchTerm = produtoNaFrase[0];
      historico.push({ role: 'user', content: msg, time: getTime() });
      historico.push({ role: 'assistant', content: 'Vou buscar os ' + searchTerm + ' disponíveis! Para ver o valor total com entrega, adicione ao carrinho e diga "carrinho" 🛒', time: getTime() });
      salvarHistorico(); renderMensagens();
      navigatePainel('/produtos?search=' + encodeURIComponent(searchTerm) + '&ver_todos=1&embed=1');
      return;
    }

    if (palavras.length <= 5 && !msgLower.match(/^(oi|ola|obrigad|como|porque|quando|onde|quem|ajuda|help)(\s|$)/) && !msgLower.match(/grupo|carrinho|checkout|pedido|conta|endereco|ticket/) && !msgLower.match(/\?$/) && !msgLower.match(/^(voces?|vcs|posso|consigo|da pra|tem como|e possivel|existe|funciona|aceita|entrega|envia|faz|fazem|pode|podem)(\s|$)/)) {
      // Se não é uma pergunta/saudação, tratar como busca de produto
      const traducoes = {pipoca:'popcorn',pipocas:'popcorn',esponja:'sponge',panela:'pan',sabonete:'soap',detergente:'dish soap',aspirador:'vacuum',vitamina:'vitamin',fralda:'diaper',chocolate:'chocolate','cafe':'coffee',biscoito:'cookie',sorveteira:'ice cream maker',sorvete:'ice cream',liquidificador:'blender',batedeira:'mixer',cafeteira:'coffee maker',frigideira:'frying pan',airfryer:'air fryer',fritadeira:'air fryer',banho:'bath',corpo:'body',cabelo:'hair',rosto:'face',pele:'skin',limpeza:'cleaning',cozinha:'kitchen',banheiro:'bathroom',roupa:'laundry',bebe:'baby',perfume:'perfume',vela:'candle',toalha:'towel',sabao:'soap',creme:'cream',menta:'mint',hortela:'mint',morango:'strawberry',limao:'lemon',laranja:'orange',lavanda:'lavender',canela:'cinnamon',baunilha:'vanilla',coco:'coconut',mel:'honey',manteiga:'butter',leite:'milk',aveia:'oat',amendoim:'peanut',cereal:'cereal',tempero:'seasoning',molho:'sauce',azeite:'olive oil',lenco:'tissue',papel:'paper',protetor:'sunscreen',desodorante:'deodorant',shampoo:'shampoo',condicionador:'conditioner',escova:'brush',secador:'dryer',maquiagem:'makeup',batom:'lipstick',hidratante:'moisturizer',proteina:'protein',whey:'whey',creatina:'creatine',melatonina:'melatonin',probiotico:'probiotic',colageno:'collagen'};
      // Limpar artigos, preposições e pontuação
      let searchTerm = msg.trim().replace(/^(o|a|os|as|um|uma|uns|umas|do|da|dos|das|de|no|na|nos|nas|pro|pra|para|esse|essa|aquele|aquela|me\s+mostr[ea]|quero|quero\s+comprar|tem|busca|procura|buscar|procurar|comprar)\s+/gi, '').replace(/[?!.,;:]+$/g, '').trim();
      // Remover frases de preço/orçamento e destino
      searchTerm = searchTerm.replace(/\b(quanto fica|quanto custa|qual o preco|qual o valor|pro brasil|para o brasil|pra brasil|no brasil|pro br)\b/gi, '').trim();
      // Remover artigos internos também
      searchTerm = searchTerm.replace(/\b(um|uma|o|a|os|as|do|da|de|no|na)\b/gi, '').replace(/\s+/g, ' ').trim();
      if (!searchTerm) searchTerm = msg.trim();
      for (const [pt, en] of Object.entries(traducoes)) {
        if (searchTerm.toLowerCase().includes(pt)) { searchTerm = en; break; }
      }
      historico.push({ role: 'user', content: msg, time: getTime() });
      const _isOrcamento = /quanto (fica|custa)|orcamento|orçamento/i.test(msg);
      const _buscaReply = 'Buscando ' + searchTerm + '... 🔍\n\nClique em "Add to cart" para adicionar ao carrinho.\nPode continuar buscando outros produtos — quando terminar, diga "carrinho" para ver o valor total do seu carrinho!\n\n✈️ Frete Grátis para o mundo todo';
      historico.push({ role: 'assistant', content: _buscaReply, time: getTime() });
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
            // Tentar tradução para inglês via IA se o termo não foi traduzido pelo mapa
            historico.push({ role: 'assistant', content: 'Hmm, deixa eu tentar de outra forma... 🔍', time: getTime() });
            salvarHistorico(); renderMensagens();
            
            // Chamar IA para traduzir o termo para inglês
            fetch('/api/copiloto/chat', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'same-origin',
              body: JSON.stringify({
                mensagem: 'Traduza para inglês o termo de produto: "' + msg + '". Responda APENAS com a tradução em inglês, uma única palavra ou frase curta, sem explicação. Se já está em inglês, repita o termo.',
                historico: [],
                contexto: { pagina: 'home_ia_translate', url_atual: '/home-ia' }
              })
            })
            .then(r => r.json())
            .then(data => {
              const traducao = (data.resposta || '').trim().replace(/["""'']/g, '').replace(/\.$/, '').trim().toLowerCase();
              if (traducao && traducao.length > 1 && traducao.length < 50 && traducao !== searchTerm.toLowerCase()) {
                historico.push({ role: 'assistant', content: 'Buscando por "' + traducao + '"... 🔍', time: getTime() });
                salvarHistorico(); renderMensagens();
                navigatePainel('/produtos?search=' + encodeURIComponent(traducao) + '&ver_todos=1&embed=1');
                // Verificar se encontrou após 3s
                setTimeout(() => {
                  try {
                    const iDoc3 = frame.contentDocument || frame.contentWindow.document;
                    const still404 = iDoc3 && (
                      iDoc3.querySelector('.text-muted')?.textContent?.includes('No products') ||
                      iDoc3.querySelector('.text-muted')?.textContent?.includes('Nenhum produto') ||
                      iDoc3.querySelectorAll('.product-card, .card').length === 0
                    );
                    if (still404) {
                      historico.push({ role: 'assistant', content: 'Não encontrei "' + msg + '" no catálogo. 😕\n\nMas você pode comprar de qualquer loja dos EUA via Assessoria! Diga "assessoria" para abrir.', time: getTime() });
                      salvarHistorico(); renderMensagens();
                    }
                  } catch(e) {}
                }, 3000);
              } else {
                historico.push({ role: 'assistant', content: 'Não encontrei "' + msg + '" no nosso catálogo. 😕\n\nMas você pode comprar de qualquer loja dos EUA! Diga "assessoria" para usar nossa compra por link.', time: getTime() });
                salvarHistorico(); renderMensagens();
              }
            })
            .catch(() => {
              historico.push({ role: 'assistant', content: 'Não encontrei "' + msg + '" no catálogo. Diga "assessoria" para comprar de qualquer loja dos EUA!', time: getTime() });
              salvarHistorico(); renderMensagens();
            });
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
      
      // Detectar resposta de erro/timeout da IA e fazer retry automático
      if (resp.match(/problema t[eé]cnico|tenta de novo|erro|timeout/i)) {
        historico.push({ role: 'assistant', content: 'Hmmm, estou olhando mais profundamente... 🔍', time: getTime() });
        salvarHistorico(); renderMensagens();
        // Retry após 2s
        setTimeout(() => {
          const lastUserMsg = historico.filter(m => m.role === 'user').pop();
          if (lastUserMsg) {
            input.value = lastUserMsg.content;
            enviando = false;
            sendBtn.disabled = false;
            enviar();
          }
        }, 2000);
        return;
      }
      
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
      historico.push({ role: 'assistant', content: 'Hmmm, estou processando... ⏳', time: getTime() });
      renderMensagens();
      // Retry após 2s
      setTimeout(() => {
        const lastUserMsg = historico.filter(m => m.role === 'user').pop();
        if (lastUserMsg && !lastUserMsg._retried) {
          lastUserMsg._retried = true;
          input.value = lastUserMsg.content;
          enviando = false;
          sendBtn.disabled = false;
          enviar();
        }
      }, 2000);
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
        // Não criar ticket automaticamente — apenas instruir o usuário
        navigatePainel('/meus-tickets?embed=1');
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
        // Corrigir URLs comuns que a IA pode errar
        const urlFixes = {
          '/grupos-abertos': '/grupos-compras',
          '/grupos': '/grupos-compras',
          '/grupo-compras': '/grupos-compras',
          '/grupo-de-compras': '/grupos-compras',
          '/meus-dados-pessoais': '/meus-dados',
          '/perfil': '/meus-dados',
          '/pedidos': '/meus-pedidos',
          '/enderecos': '/meus-enderecos',
          '/suporte': '/meus-tickets',
          '/clube': '/clube/recarga',
          '/cart': '/carrinho',
          '/products': '/produtos',
        };
        const urlLower = url.toLowerCase().split('?')[0];
        if (urlFixes[urlLower]) url = urlFixes[urlLower];
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
  // Wizard de boas-vindas (apenas na primeira visita, apenas desktop)
  if (historico.length === 0 && !sessionStorage.getItem('bri_wizard_done') && window.innerWidth >= 768) {
    showWizard();
  } else {
    renderMensagens();
  }

  function showWizard() {
    const wizardEl = document.createElement('div');
    wizardEl.id = 'bri-wizard';
    wizardEl.style.cssText = 'position:absolute;inset:0;background:var(--color-bg);z-index:10;display:flex;flex-direction:column;padding:20px;overflow-y:auto;';
    
    const steps = [
      { icon: 'bi-search', title: 'Buscar Produtos', desc: 'Digite o nome do produto (ex: "tineco", "pipoca") e eu busco pra você no painel ao lado.' },
      { icon: 'bi-cart-plus', title: 'Adicionar ao Carrinho', desc: 'Clique em "Add to cart" nos produtos que aparecem ao lado. Simples assim!' },
      { icon: 'bi-shop', title: 'Grupos de Compras', desc: 'Digite "grupos" para ver os grupos abertos. Ou pergunte "qual grupo tem X?"' },
      { icon: 'bi-person', title: 'Sua Conta', desc: 'Digite "minha conta", "meus pedidos" ou "meus endereços" para acessar seus dados.' },
      { icon: 'bi-calculator', title: 'Orçamento', desc: 'Busque os produtos, adicione ao carrinho e diga "carrinho" — lá terá o valor total!' },
    ];

    let html = '<div style="text-align:center;margin-bottom:20px;"><div style="width:48px;height:48px;border-radius:50%;background:var(--color-primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:10px;"><i class="bi bi-stars"></i></div><h3 style="font-size:18px;font-weight:700;color:var(--color-primary);margin:0 0 4px;">Olá! Eu sou a BRI</h3><p style="font-size:13px;color:var(--color-text-secondary);margin:0;">Sua assistente de navegação. Veja como posso te ajudar:</p></div>';
    
    html += '<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">';
    steps.forEach(s => {
      html += '<div style="display:flex;gap:12px;align-items:flex-start;padding:10px 12px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:10px;"><i class="bi ' + s.icon + '" style="font-size:18px;color:var(--color-primary);flex-shrink:0;margin-top:2px;"></i><div><div style="font-size:13px;font-weight:600;color:var(--color-text);">' + s.title + '</div><div style="font-size:12px;color:var(--color-text-secondary);margin-top:2px;">' + s.desc + '</div></div></div>';
    });
    html += '</div>';

    html += '<div style="text-align:center;margin-bottom:12px;"><p style="font-size:14px;font-weight:600;color:var(--color-primary);">Vamos começar? 🚀</p><p style="font-size:12px;color:var(--color-text-secondary);">Para onde gostaria de ir ou qual produto procura?</p></div>';
    html += '<div style="display:flex;gap:8px;"><input type="text" id="bri-wizard-input" placeholder="Ex: tineco, grupos, meus pedidos..." style="flex:1;border:1px solid var(--color-border-input);border-radius:var(--radius-input);padding:10px 12px;font-size:13px;font-family:inherit;outline:none;"><button id="bri-wizard-btn" style="background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-btn);padding:10px 16px;font-size:13px;font-weight:500;cursor:pointer;">Ir!</button></div>';

    wizardEl.innerHTML = html;
    msgsEl.parentElement.insertBefore(wizardEl, msgsEl);
    msgsEl.style.display = 'none';
    document.getElementById('bri-input-area').style.display = 'none';

    const wizInput = document.getElementById('bri-wizard-input');
    const wizBtn = document.getElementById('bri-wizard-btn');
    
    function finishWizard() {
      const val = (wizInput.value || '').trim();
      if (!val) return;
      sessionStorage.setItem('bri_wizard_done', '1');
      wizardEl.remove();
      msgsEl.style.display = '';
      document.getElementById('bri-input-area').style.display = '';
      // Inserir a mensagem do wizard como primeira mensagem do chat
      input.value = val;
      enviar();
    }

    wizBtn.addEventListener('click', finishWizard);
    wizInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') finishWizard(); });
    wizInput.focus();
  }

  // Expor para uso externo
  function limparChat() {
    historico = [];
    try { sessionStorage.removeItem('bri_sidebar_hist'); } catch(e) {}
    renderMensagens();
  }

  return { handleAcao, fecharPainel, navigatePainel, limparChat };

})();
