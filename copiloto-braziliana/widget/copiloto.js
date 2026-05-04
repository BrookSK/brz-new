/**
 * Co-Piloto Braziliana — Widget Frontend
 * Conforme Recurso 6 do documento de arquitetura
 * Injetado via <script> em todas as páginas do site
 */
;(function () {
  'use strict'

  // ========== CONFIG ==========
  const scriptTag = document.currentScript || document.querySelector('script[data-backend]')
  const CONFIG = {
    backend_url: (scriptTag && scriptTag.getAttribute('data-backend')) || 'https://copiloto.braziliana.com.br',
    gatilho_tempo_ms: 30000,
    max_historico_enviado: 10,
    whatsapp_suporte: '5517991098286',
    whatsapp_vendas: '5517996203062',
    rotas_idioma: { BRL: '/lang/pt', USD: '/lang/en' },
    storage_prefix: 'bz_copiloto_'
  }

  const STORAGE = {
    historico: CONFIG.storage_prefix + 'historico',
    gatilhos: CONFIG.storage_prefix + 'gatilhos',
    aberto: CONFIG.storage_prefix + 'aberto',
    estado_pendente: CONFIG.storage_prefix + 'estado_pendente',
    moeda: CONFIG.storage_prefix + 'moeda'
  }

  // ========== ESTADO ==========
  let widgetAberto = false
  let enviando = false
  let historico = []
  const estado_suporte = { tentativas: 0, problema_atual: null, max_tentativas: null }

  // ========== INICIALIZAÇÃO ==========
  function init () {
    injetarCSS()
    criarWidget()
    restaurarEstado()
    iniciarGatilhos(lerContextoPagina())
  }

  // ========== LEITURA DO DOM — Recurso 1 ==========
  function lerContextoPagina () {
    var url = window.location.pathname
    var ctx = {
      pagina: 'outra',
      url_atual: window.location.href,
      produto_id: null,
      produto_nome: null,
      produto_preco_usd: null,
      produto_peso_kg: null,
      produto_grupo: null,
      imposto_local_pct: 0,
      carrinho_itens: [],
      carrinho_subtotal: 0,
      usuario_logado: verificarLogin(),
      usuario_nome: null,
      moeda_atual: detectarMoeda()
    }

    // Detectar página
    if (url === '/') ctx.pagina = 'home'
    else if (url.match(/\/produto\/detalhes\/(\d+)/)) {
      ctx.pagina = 'produto'
      ctx.produto_id = parseInt(url.match(/\/produto\/detalhes\/(\d+)/)[1])
      ctx.produto_nome = qs('h1') ? qs('h1').textContent.trim() : null
      ctx.produto_preco_usd = parsearPreco(qs('[class*="preco"], [class*="price"]'))
      ctx.produto_peso_kg = parsearPeso()
      ctx.produto_grupo = extrairSlugDoGrupo()
      ctx.imposto_local_pct = lerImpostoLocal()
    } else if (url.match(/\/grupo\/([^/]+)/)) {
      ctx.pagina = 'grupo'
      ctx.produto_grupo = url.match(/\/grupo\/([^/]+)/)[1]
      ctx.imposto_local_pct = lerImpostoLocal()
    } else if (url === '/carrinho') {
      ctx.pagina = 'carrinho'
      ctx.carrinho_itens = lerItensCarrinho()
      ctx.carrinho_subtotal = parsearPreco(qs('.subtotal, [class*="subtotal"]'))
    } else if (url === '/checkout') ctx.pagina = 'checkout'
    else if (url === '/rastreamento') ctx.pagina = 'rastreamento'
    else if (url.match(/\/como-funciona/)) ctx.pagina = 'como-funciona'
    else if (url.match(/\/clube/)) ctx.pagina = 'clube'
    else if (url === '/meus-dados') ctx.pagina = 'minha-conta'
    else if (url === '/contato') ctx.pagina = 'contato'
    else if (url === '/faq') ctx.pagina = 'faq'
    else if (url === '/produtos') ctx.pagina = 'catalogo'

    // Mini-carrinho (disponível em todas as páginas)
    var badge = qs('[class*="carrinho"] [class*="count"], [class*="badge"], .cart-count')
    if (badge) ctx.carrinho_qtd = parseInt(badge.textContent) || 0

    // Nome do usuário
    var nomeEl = qs('[class*="user-name"], [class*="usuario-nome"], .dropdown-toggle .nome')
    if (nomeEl) ctx.usuario_nome = nomeEl.textContent.trim()

    return ctx
  }

  function verificarLogin () {
    return !!(
      document.cookie.includes('PHPSESSID') &&
      (qs('[href*="logout"], [href*="sair"]') || qs('[class*="minha-conta"], [class*="my-account"]'))
    )
  }

  function detectarMoeda () {
    if (window.location.href.includes('/lang/en')) return 'USD'
    var preco = qs('[class*="preco"]')
    if (preco && (preco.textContent.includes('US$') || preco.textContent.includes('$'))) return 'USD'
    return 'BRL'
  }

  function parsearPreco (el) {
    if (!el) return null
    if (typeof el === 'string') return parseFloat(el.replace(/[^0-9.]/g, '')) || null
    return parseFloat((el.textContent || '').replace(/[^0-9.]/g, '')) || null
  }

  function parsearPeso () {
    var rows = document.querySelectorAll('table tr, .spec-row')
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].textContent.toLowerCase().includes('peso') || rows[i].textContent.toLowerCase().includes('weight')) {
        var td = rows[i].querySelector('td:last-child, .spec-value')
        if (td) return parseFloat(td.textContent.replace(/[^0-9.]/g, '')) || null
      }
    }
    return null
  }

  function extrairSlugDoGrupo () {
    var ref = document.referrer || ''
    var m = ref.match(/\/grupo\/([^/?]+)/)
    if (m) return m[1]
    var link = qs('[data-grupo]')
    if (link) return link.dataset.grupo
    return null
  }

  function lerImpostoLocal () {
    var el = qs('[class*="imposto"], [class*="tax"]')
    if (el) {
      var m = el.textContent.match(/(\d+)%/)
      if (m) return parseInt(m[1])
    }
    var MAPA = { 'bath-and-body-works': 8, 'walmart': 8, 'trader-joes': 8, 'bjs': 8, 'achados-e-favoritos-da-fabi': 8 }
    var slug = extrairSlugDoGrupo()
    return MAPA[slug] || 0
  }

  function lerItensCarrinho () {
    var itens = []
    var els = document.querySelectorAll('.carrinho-item, [class*="cart-item"], tr[class*="item"]')
    els.forEach(function (el) {
      itens.push({
        nome: (qs('[class*="nome"], .product-name', el) || {}).textContent?.trim() || '',
        preco: parsearPreco(qs('[class*="preco"], .product-price', el)),
        quantidade: parseInt((qs('input[type="number"], [class*="quantidade"]', el) || {}).value || '1')
      })
    })
    return itens
  }

  function qs (sel, parent) { return (parent || document).querySelector(sel) }

  // ========== AÇÕES REAIS — Recurso 2 ==========
  function lerSessaoDoUsuario () {
    var cookies = document.cookie.split(';')
    for (var i = 0; i < cookies.length; i++) {
      var c = cookies[i].trim()
      if (c.startsWith('PHPSESSID=')) return c.split('=')[1]
    }
    return null
  }

  function salvarEstadoChat () {
    try {
      localStorage.setItem(STORAGE.estado_pendente, JSON.stringify({
        historico: historico,
        estava_aberto: widgetAberto,
        timestamp: Date.now()
      }))
    } catch (e) {}
  }

  async function executarAcao (acao, parametros) {
    var p = parametros || {}
    var acoes = {
      adicionar_carrinho: function () { return executarAdicionarCarrinho(p.produto_id, p.quantidade) },
      trocar_moeda_brl: function () { salvarEstadoChat(); window.location.href = '/lang/pt' },
      trocar_moeda_usd: function () { salvarEstadoChat(); window.location.href = '/lang/en' },
      consultar_status_pedido: function () { return executarConsultarStatusPedido(p.identificador || p.codigo) },
      abrir_whatsapp_vendas: function () {
        window.open('https://wa.me/' + CONFIG.whatsapp_vendas + (p.mensagem ? '?text=' + encodeURIComponent(p.mensagem) : ''), '_blank')
      },
      ir_para_checkout: function () { salvarEstadoChat(); window.location.href = '/checkout' },
      ir_para_contato: function () { navegarComPreenchimento('/contato', p) },
      ir_para_clube: function () { salvarEstadoChat(); window.location.href = '/clube/recarga' },
      ir_para_meus_dados: function () { salvarEstadoChat(); window.location.href = '/meus-dados' },
      buscar_produto: function () { salvarEstadoChat(); window.location.href = '/produtos?busca=' + encodeURIComponent(p.termo || p.busca || '') },
      ir_para_grupo: function () { salvarEstadoChat(); window.location.href = '/grupo/' + (p.slug || '') },
      criar_ticket_suporte: function () { return executarCriarTicket('suporte', p) },
      criar_ticket_duvida: function () { return executarCriarTicket('duvidas_gerais', p) },
      verificar_cancelamento: function () { return executarVerificarCancelamento(p.numero_pedido) },
      solicitar_cancelamento: function () { return executarSolicitarCancelamento(p.numero_pedido) },
      nenhuma: function () {}
    }
    var fn = acoes[acao] || acoes.nenhuma
    return await fn()
  }

  async function executarAdicionarCarrinho (produtoId, quantidade) {
    quantidade = quantidade || 1
    // Tentativa 1: clicar no botão real
    var btn = qs('[data-produto-id="' + produtoId + '"] button, button[onclick*="' + produtoId + '"], .btn-adicionar[data-id="' + produtoId + '"]')
    if (btn) { btn.click(); return { sucesso: true, metodo: 'dom_click' } }
    // Tentativa 2: função JS global
    if (typeof window.adicionarAoCarrinho === 'function') {
      window.adicionarAoCarrinho(produtoId, quantidade)
      return { sucesso: true, metodo: 'js_global' }
    }
    // Tentativa 3: via backend proxy
    try {
      var resp = await fetch(CONFIG.backend_url + '/api/copiloto/carrinho/adicionar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ produto_id: produtoId, quantidade: quantidade, sessao: lerSessaoDoUsuario() })
      })
      return await resp.json()
    } catch (e) {
      return { sucesso: false, erro: e.message }
    }
  }

  async function executarConsultarStatusPedido (identificador) {
    adicionarMensagemChat('assistant', '⏳ Consultando status...')
    try {
      var resp = await fetch(CONFIG.backend_url + '/api/copiloto/status-pedido', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identificador: identificador, sessao_id: lerSessaoDoUsuario() })
      })
      var dados = await resp.json()
      removerUltimaMensagem()
      adicionarMensagemChat('assistant', '📦 ' + (dados.status_texto || 'Status consultado'))
    } catch (e) {
      removerUltimaMensagem()
      adicionarMensagemChat('assistant', 'Não consegui consultar o status. Tenta informar o código de rastreio?')
    }
  }

  async function executarCriarTicket (categoria, params) {
    try {
      var resp = await fetch(CONFIG.backend_url + '/api/copiloto/ticket', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          categoria: categoria,
          assunto: params.assunto || 'Solicitação via Co-Piloto',
          mensagem: params.mensagem || params.descricao || '',
          numero_pedido: params.numero_pedido || null,
          nome: params.nome || '',
          email: params.email || '',
          sessao_id: lerSessaoDoUsuario()
        })
      })
      var dados = await resp.json()
      if (dados.sucesso) {
        adicionarMensagemChat('assistant', '✅ Chamado ' + dados.numero_ticket + ' aberto! Nossa equipe responde em até ' + dados.prazo_resposta + '.')
      }
    } catch (e) {
      adicionarMensagemChat('assistant', 'Não consegui abrir o chamado. Tenta novamente?')
    }
  }

  async function executarVerificarCancelamento (numeroPedido) {
    try {
      var resp = await fetch(CONFIG.backend_url + '/api/copiloto/cancelamento/verificar?numero_pedido=' + encodeURIComponent(numeroPedido) + '&sessao_id=' + (lerSessaoDoUsuario() || ''))
      return await resp.json()
    } catch (e) { return { erro: e.message } }
  }

  async function executarSolicitarCancelamento (numeroPedido) {
    try {
      var resp = await fetch(CONFIG.backend_url + '/api/copiloto/cancelamento/solicitar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ numero_pedido: numeroPedido, sessao_id: lerSessaoDoUsuario(), confirmado: true })
      })
      return await resp.json()
    } catch (e) { return { erro: e.message } }
  }

  function navegarComPreenchimento (rota, params) {
    salvarEstadoChat()
    var estado = JSON.parse(localStorage.getItem(STORAGE.estado_pendente) || '{}')
    estado.acao_pos_navegacao = { tipo: 'preencher_contato', dados: params }
    localStorage.setItem(STORAGE.estado_pendente, JSON.stringify(estado))
    window.location.href = rota
  }

  // ========== WIDGET UI ==========
  function criarWidget () {
    // Container principal
    var container = document.createElement('div')
    container.id = 'bz-copiloto'
    container.innerHTML = `
      <div id="bz-copiloto-launcher" role="button" tabindex="0" aria-label="Abrir Co-Piloto Braziliana">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.38 5.07L2 22l4.93-1.38C8.42 21.5 10.15 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z" fill="#fff"/>
          <circle cx="8" cy="12" r="1.5" fill="#0b1f3a"/>
          <circle cx="12" cy="12" r="1.5" fill="#0b1f3a"/>
          <circle cx="16" cy="12" r="1.5" fill="#0b1f3a"/>
        </svg>
        <span>Bri</span>
      </div>
      <div id="bz-copiloto-badge" style="display:none"></div>
      <div id="bz-copiloto-panel" style="display:none" role="dialog" aria-label="Co-Piloto Braziliana">
        <div id="bz-copiloto-header">
          <div class="bz-header-info">
            <strong>Bri</strong>
            <small>Co-Piloto Braziliana</small>
          </div>
          <button id="bz-copiloto-close" aria-label="Fechar">&times;</button>
        </div>
        <div id="bz-copiloto-messages"></div>
        <div id="bz-copiloto-disclaimer" style="padding:4px 12px;background:#fff8e1;border-top:1px solid #ffe082;font-size:11px;color:#8d6e00;text-align:center;line-height:1.3;">
          ⚠️ A Bri é uma assistente de IA e pode cometer erros. Confirme informações importantes.
        </div>
        <div id="bz-copiloto-input-area">
          <input type="text" id="bz-copiloto-input" placeholder="Fala comigo..." maxlength="2000" autocomplete="off" />
          <button id="bz-copiloto-send" aria-label="Enviar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z" fill="#fff"/></svg>
          </button>
        </div>
      </div>
    `
    document.body.appendChild(container)

    // Event listeners
    var launcher = document.getElementById('bz-copiloto-launcher')
    var panel = document.getElementById('bz-copiloto-panel')
    var closeBtn = document.getElementById('bz-copiloto-close')
    var input = document.getElementById('bz-copiloto-input')
    var sendBtn = document.getElementById('bz-copiloto-send')
    var badge = document.getElementById('bz-copiloto-badge')

    launcher.addEventListener('click', function () { toggleWidget() })
    launcher.addEventListener('keydown', function (e) { if (e.key === 'Enter') toggleWidget() })
    closeBtn.addEventListener('click', function () { fecharWidget() })
    sendBtn.addEventListener('click', function () { enviarMensagem() })
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensagem() } })

    // Fechar badge ao clicar
    badge.addEventListener('click', function () {
      badge.style.display = 'none'
      abrirWidget()
    })
  }

  function toggleWidget () {
    if (widgetAberto) fecharWidget()
    else abrirWidget()
  }

  function abrirWidget () {
    widgetAberto = true
    var panel = document.getElementById('bz-copiloto-panel')
    var badge = document.getElementById('bz-copiloto-badge')
    panel.style.display = 'flex'
    badge.style.display = 'none'
    localStorage.setItem(STORAGE.aberto, '1')

    // Mensagem de boas-vindas se vazio
    var msgs = document.getElementById('bz-copiloto-messages')
    if (msgs.children.length === 0 && historico.length === 0) {
      adicionarMensagemChat('assistant', 'Oi! Sou a Bri, sua copiloto de compras na Braziliana. 😊\nComo posso te ajudar?')
    }

    // Focus no input
    setTimeout(function () { document.getElementById('bz-copiloto-input').focus() }, 100)
    scrollToBottom()
  }

  function fecharWidget () {
    widgetAberto = false
    document.getElementById('bz-copiloto-panel').style.display = 'none'
    localStorage.setItem(STORAGE.aberto, '0')
  }

  function mostrarBadge (texto) {
    var badge = document.getElementById('bz-copiloto-badge')
    if (!badge || widgetAberto) return
    badge.textContent = texto
    badge.style.display = 'block'
  }

  function adicionarMensagemChat (role, texto) {
    var msgs = document.getElementById('bz-copiloto-messages')
    var div = document.createElement('div')
    div.className = 'bz-msg bz-msg-' + role
    div.innerHTML = '<div class="bz-msg-bubble">' + formatarTexto(texto) + '</div>'
    msgs.appendChild(div)
    historico.push({ role: role, content: texto })
    salvarHistorico()
    scrollToBottom()
  }

  function removerUltimaMensagem () {
    var msgs = document.getElementById('bz-copiloto-messages')
    if (msgs.lastChild) msgs.removeChild(msgs.lastChild)
    historico.pop()
  }

  function formatarTexto (texto) {
    return (texto || '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/\n/g, '<br>')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
  }

  function scrollToBottom () {
    var msgs = document.getElementById('bz-copiloto-messages')
    if (msgs) msgs.scrollTop = msgs.scrollHeight
  }

  // ========== ENVIO DE MENSAGEM ==========
  async function enviarMensagem () {
    if (enviando) return
    var input = document.getElementById('bz-copiloto-input')
    var texto = (input.value || '').trim()
    if (!texto) return

    input.value = ''
    enviando = true
    adicionarMensagemChat('user', texto)

    // Indicador de digitação
    var msgs = document.getElementById('bz-copiloto-messages')
    var typing = document.createElement('div')
    typing.className = 'bz-msg bz-msg-assistant bz-typing'
    typing.innerHTML = '<div class="bz-msg-bubble"><span class="bz-dot"></span><span class="bz-dot"></span><span class="bz-dot"></span></div>'
    msgs.appendChild(typing)
    scrollToBottom()

    try {
      var contexto = lerContextoPagina()
      var historicoEnviar = historico.slice(-(CONFIG.max_historico_enviado * 2))

      var resp = await fetch(CONFIG.backend_url + '/api/copiloto/mensagem', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mensagem: texto,
          contexto: contexto,
          historico: historicoEnviar,
          sessao_cookie: lerSessaoDoUsuario()
        })
      })

      // Remover indicador de digitação
      if (typing.parentNode) typing.parentNode.removeChild(typing)

      if (!resp.ok) throw new Error('Erro ' + resp.status)

      var dados = await resp.json()

      // Adicionar resposta da Bri
      adicionarMensagemChat('assistant', dados.resposta || 'Hmm, não consegui processar. Tenta de novo?')

      // Executar ação frontend se houver
      if (dados.acao_frontend && dados.acao_frontend.tipo && dados.acao_frontend.tipo !== 'nenhuma') {
        await executarAcao(dados.acao_frontend.tipo, dados.acao_frontend.parametros)
      }

      // Avaliar escalada para ticket
      if (dados.max_tentativas_problema !== null && dados.max_tentativas_problema !== undefined) {
        estado_suporte.max_tentativas = dados.max_tentativas_problema
      }
      if (dados.oferecer_ticket) {
        estado_suporte.tentativas = 999 // Forçar ticket
      }
    } catch (err) {
      if (typing.parentNode) typing.parentNode.removeChild(typing)
      adicionarMensagemChat('assistant', 'Ops, tive um problema de conexão. Tenta de novo em alguns segundos? 😅')
      console.error('[CoPiloto] Erro:', err)
    }

    enviando = false
  }

  // ========== PERSISTÊNCIA ==========
  function salvarHistorico () {
    try {
      var h = historico.slice(-50) // Manter últimas 50 mensagens
      localStorage.setItem(STORAGE.historico, JSON.stringify(h))
    } catch (e) {}
  }

  function restaurarEstado () {
    // Restaurar histórico
    try {
      var h = JSON.parse(localStorage.getItem(STORAGE.historico) || '[]')
      if (Array.isArray(h) && h.length > 0) {
        historico = h
        var msgs = document.getElementById('bz-copiloto-messages')
        h.forEach(function (m) {
          var div = document.createElement('div')
          div.className = 'bz-msg bz-msg-' + m.role
          div.innerHTML = '<div class="bz-msg-bubble">' + formatarTexto(m.content) + '</div>'
          msgs.appendChild(div)
        })
      }
    } catch (e) {}

    // Restaurar estado pendente (após navegação)
    try {
      var estado = JSON.parse(localStorage.getItem(STORAGE.estado_pendente) || 'null')
      if (estado) {
        localStorage.removeItem(STORAGE.estado_pendente)
        if (estado.historico && estado.historico.length > 0) {
          historico = estado.historico
          var msgs = document.getElementById('bz-copiloto-messages')
          msgs.innerHTML = ''
          historico.forEach(function (m) {
            var div = document.createElement('div')
            div.className = 'bz-msg bz-msg-' + m.role
            div.innerHTML = '<div class="bz-msg-bubble">' + formatarTexto(m.content) + '</div>'
            msgs.appendChild(div)
          })
        }
        if (estado.estava_aberto) abrirWidget()
        if (estado.acao_pos_navegacao) executarAcaoPosNavegacao(estado.acao_pos_navegacao)
      }
    } catch (e) {}

    // Restaurar estado aberto
    if (localStorage.getItem(STORAGE.aberto) === '1') {
      abrirWidget()
    }
  }

  function executarAcaoPosNavegacao (acao) {
    if (!acao) return
    setTimeout(function () {
      if (acao.tipo === 'preencher_contato') {
        preencherCampo('[name="nome"], #nome', acao.dados.nome)
        preencherCampo('[name="email"], #email', acao.dados.email)
        preencherCampo('[name="telefone"], #telefone', acao.dados.telefone)
        preencherCampo('[name="mensagem"], #mensagem, textarea', acao.dados.mensagem)
      }
    }, 500)
  }

  function preencherCampo (seletor, valor) {
    if (!valor) return
    var el = qs(seletor)
    if (el) {
      el.value = valor
      el.dispatchEvent(new Event('input', { bubbles: true }))
      el.dispatchEvent(new Event('change', { bubbles: true }))
    }
  }

  // ========== GATILHOS PROATIVOS ==========
  function iniciarGatilhos (contexto) {
    var gatilhosDisparados = JSON.parse(localStorage.getItem(STORAGE.gatilhos) || '{}')

    if (contexto.pagina === 'produto' && contexto.produto_id && !gatilhosDisparados[contexto.url_atual]) {
      setTimeout(function () {
        if (!widgetAberto) {
          var msg = 'Quer saber quanto fica esse produto no total?'
          if (contexto.produto_preco_usd && contexto.produto_peso_kg) {
            // Cálculo rápido local
            var faixas = [1,2,3,4,5,6,7,8,9,10,15,20,25,30]
            var faixa = faixas.find(function(f){return f >= contexto.produto_peso_kg}) || 30
            var taxa = faixa * 39
            var impostos = contexto.produto_preco_usd * 0.80
            var impLocal = contexto.produto_preco_usd * (contexto.imposto_local_pct / 100)
            var total = contexto.produto_preco_usd + impLocal + taxa + impostos
            var cambio = 5.80
            msg = 'Esse produto fica ~R$ ' + Math.round(total * cambio) + ' no total. Quer calcular em detalhe?'
          }
          mostrarBadge(msg)
          gatilhosDisparados[contexto.url_atual] = true
          localStorage.setItem(STORAGE.gatilhos, JSON.stringify(gatilhosDisparados))
        }
      }, CONFIG.gatilho_tempo_ms)
    }

    if (contexto.pagina === 'carrinho' && contexto.carrinho_itens && contexto.carrinho_itens.length > 0) {
      setTimeout(function () {
        if (!widgetAberto) mostrarBadge('Quer que eu revise seu pedido antes de fechar?')
      }, 5000)
    }

    if (contexto.pagina === 'grupo') {
      setTimeout(function () {
        if (!widgetAberto) {
          var nome = (qs('h1, .grupo-titulo') || {}).textContent?.trim() || 'esse grupo'
          mostrarBadge('Me fala o que você procura no ' + nome + ' 😊')
        }
      }, 3000)
    }
  }

  // ========== CSS ==========
  function injetarCSS () {
    var style = document.createElement('style')
    style.textContent = `
      #bz-copiloto { position: fixed; bottom: 24px; right: 24px; z-index: 99999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

      /* Launcher — horizontal/deitado conforme spec */
      #bz-copiloto-launcher {
        display: flex; align-items: center; gap: 8px;
        background: linear-gradient(135deg, #0b1f3a 0%, #1d4ed8 100%);
        color: #fff; border: none; border-radius: 28px;
        padding: 12px 22px 12px 16px; cursor: pointer;
        box-shadow: 0 6px 24px rgba(11,31,58,0.35);
        transition: transform 0.2s, box-shadow 0.2s;
        font-size: 15px; font-weight: 600; user-select: none;
      }
      #bz-copiloto-launcher:hover { transform: scale(1.04); box-shadow: 0 8px 32px rgba(11,31,58,0.45); }
      #bz-copiloto-launcher svg { flex-shrink: 0; }

      /* Badge proativo */
      #bz-copiloto-badge {
        position: absolute; bottom: 56px; right: 0;
        background: #fff; color: #0b1f3a; border-radius: 16px 16px 4px 16px;
        padding: 12px 16px; max-width: 280px; font-size: 14px; line-height: 1.4;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15); cursor: pointer;
        animation: bzBadgeIn 0.3s ease-out;
      }
      @keyframes bzBadgeIn { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }

      /* Painel do chat */
      #bz-copiloto-panel {
        position: absolute; bottom: 60px; right: 0;
        width: 380px; max-width: calc(100vw - 32px); height: 520px; max-height: calc(100vh - 120px);
        background: #fff; border-radius: 20px;
        box-shadow: 0 12px 48px rgba(0,0,0,0.18);
        display: flex; flex-direction: column; overflow: hidden;
        animation: bzPanelIn 0.25s ease-out;
      }
      @keyframes bzPanelIn { from { opacity:0; transform: translateY(16px) scale(0.96); } to { opacity:1; transform: translateY(0) scale(1); } }

      /* Header */
      #bz-copiloto-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 20px;
        background: linear-gradient(135deg, #0b1f3a 0%, #1d4ed8 100%);
        color: #fff;
      }
      .bz-header-info strong { display: block; font-size: 16px; }
      .bz-header-info small { opacity: 0.8; font-size: 12px; }
      #bz-copiloto-close {
        background: none; border: none; color: #fff; font-size: 24px;
        cursor: pointer; padding: 0 4px; line-height: 1; opacity: 0.8;
      }
      #bz-copiloto-close:hover { opacity: 1; }

      /* Mensagens */
      #bz-copiloto-messages {
        flex: 1; overflow-y: auto; padding: 16px;
        display: flex; flex-direction: column; gap: 10px;
      }
      .bz-msg { display: flex; }
      .bz-msg-user { justify-content: flex-end; }
      .bz-msg-assistant { justify-content: flex-start; }
      .bz-msg-bubble {
        max-width: 85%; padding: 10px 14px; border-radius: 16px;
        font-size: 14px; line-height: 1.5; word-wrap: break-word;
      }
      .bz-msg-user .bz-msg-bubble {
        background: #0b1f3a; color: #fff; border-bottom-right-radius: 4px;
      }
      .bz-msg-assistant .bz-msg-bubble {
        background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 4px;
      }

      /* Typing indicator */
      .bz-typing .bz-msg-bubble { display: flex; gap: 4px; padding: 14px 18px; }
      .bz-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #94a3b8;
        animation: bzDot 1.2s infinite;
      }
      .bz-dot:nth-child(2) { animation-delay: 0.2s; }
      .bz-dot:nth-child(3) { animation-delay: 0.4s; }
      @keyframes bzDot { 0%,80%,100% { transform: scale(0.6); opacity:0.4; } 40% { transform: scale(1); opacity:1; } }

      /* Input */
      #bz-copiloto-input-area {
        display: flex; align-items: center; gap: 8px;
        padding: 12px 16px; border-top: 1px solid #e2e8f0;
      }
      #bz-copiloto-input {
        flex: 1; border: 1px solid #e2e8f0; border-radius: 24px;
        padding: 10px 16px; font-size: 14px; outline: none;
        transition: border-color 0.2s;
      }
      #bz-copiloto-input:focus { border-color: #1d4ed8; }
      #bz-copiloto-send {
        width: 40px; height: 40px; border-radius: 50%;
        background: #0b1f3a; border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.2s;
      }
      #bz-copiloto-send:hover { background: #1d4ed8; }

      /* Mobile */
      @media (max-width: 480px) {
        #bz-copiloto { bottom: 16px; right: 12px; }
        #bz-copiloto-panel { width: calc(100vw - 24px); height: calc(100vh - 100px); bottom: 52px; right: -4px; }
        #bz-copiloto-launcher { padding: 10px 18px 10px 14px; font-size: 14px; }
      }

      /* Não atrapalhar botões fixos do mobile */
      @media (max-width: 991.98px) {
        #bz-copiloto { bottom: 80px; }
      }
    `
    document.head.appendChild(style)
  }

  // ========== MOVER WHATSAPP PARA A ESQUERDA ==========
  function moverWhatsApp () {
    var wa = document.getElementById('whatsapp-float')
    if (wa) {
      wa.style.right = 'auto'
      wa.style.left = '30px'
    }
  }

  // ========== BOOT ==========
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { moverWhatsApp(); init() })
  } else {
    moverWhatsApp()
    init()
  }
})()
