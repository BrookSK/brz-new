/**
 * Co-Piloto Braziliana — Widget Frontend (100% PHP backend)
 * Conforme Recurso 6 do documento de arquitetura
 * Todas as chamadas vão para /api/copiloto/* no mesmo domínio
 */
;(function () {
  'use strict'

  // ========== CONFIG ==========
  var CONFIG = {
    api_base: '', // Mesmo domínio — sem backend externo
    gatilho_tempo_ms: 30000,
    max_historico_enviado: 10,
    whatsapp_vendas: '5517996203062',
    storage_prefix: 'bz_copiloto_'
  }

  var STORAGE = {
    historico: CONFIG.storage_prefix + 'historico',
    gatilhos: CONFIG.storage_prefix + 'gatilhos',
    aberto: CONFIG.storage_prefix + 'aberto',
    estado_pendente: CONFIG.storage_prefix + 'estado_pendente'
  }

  // ========== ESTADO ==========
  var widgetAberto = false
  var enviando = false
  var historico = []
  var estado_suporte = { tentativas: 0, max_tentativas: null }

  // ========== INIT ==========
  function init () {
    // Carregar config do servidor
    fetch('/api/copiloto/context').then(function (r) { return r.json() }).then(function (cfg) {
      if (cfg.gatilho_tempo_ms) CONFIG.gatilho_tempo_ms = cfg.gatilho_tempo_ms
    }).catch(function () {})

    injetarCSS()
    criarWidget()
    restaurarEstado()
    iniciarGatilhos(lerContextoPagina())
  }

  // ========== LEITURA DO DOM ==========
  function lerContextoPagina () {
    var url = window.location.pathname
    var ctx = {
      pagina: 'outra', url_atual: window.location.href,
      produto_id: null, produto_nome: null, produto_preco_usd: null,
      produto_peso_kg: null, produto_grupo: null, imposto_local_pct: 0,
      carrinho_itens: [], carrinho_subtotal: 0,
      usuario_logado: verificarLogin(), usuario_nome: null,
      moeda_atual: detectarMoeda()
    }
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
      // Ler subtotal do data-attribute real (USD interno)
      var subEl = qs('.subtotal-value')
      ctx.carrinho_subtotal = subEl ? (parseFloat(subEl.getAttribute('data-original-value')) || parsearPreco(subEl)) : null
      var totalEl = qs('.total-value')
      ctx.carrinho_total = totalEl ? (parseFloat(totalEl.getAttribute('data-original-value')) || parsearPreco(totalEl)) : null
      // Ler valores visíveis na tela (na moeda ativa do usuário)
      ctx.carrinho_subtotal_visivel = subEl ? subEl.textContent.trim() : null
      ctx.carrinho_total_visivel = totalEl ? totalEl.textContent.trim() : null
    } else if (url === '/checkout') ctx.pagina = 'checkout'
    else if (url === '/rastreamento') ctx.pagina = 'rastreamento'
    else if (url.match(/\/como-funciona/)) ctx.pagina = 'como-funciona'
    else if (url.match(/\/clube/)) ctx.pagina = 'clube'
    else if (url === '/meus-dados') ctx.pagina = 'minha-conta'
    else if (url === '/contato') ctx.pagina = 'contato'
    else if (url === '/faq') ctx.pagina = 'faq'
    else if (url === '/produtos') ctx.pagina = 'catalogo'
    var badge = qs('[class*="carrinho"] [class*="count"], .cart-count')
    if (badge) ctx.carrinho_qtd = parseInt(badge.textContent) || 0
    var nomeEl = qs('[class*="user-name"], [class*="usuario-nome"]')
    if (nomeEl) ctx.usuario_nome = nomeEl.textContent.trim()
    return ctx
  }
  function verificarLogin () {
    return !!(document.cookie.includes('PHPSESSID') && qs('[href*="logout"], [href*="sair"]'))
  }
  function detectarMoeda () {
    // Verificar seletor de moeda no header do site
    var moedaEl = qs('[class*="currency-select"], .moeda-selecionada, [data-moeda]')
    if (moedaEl) {
      var t = moedaEl.textContent.trim().toUpperCase()
      if (t.indexOf('USD') >= 0 || t === '$') return 'USD'
      if (t.indexOf('BRL') >= 0 || t.indexOf('R$') >= 0) return 'BRL'
    }
    // Verificar o texto "BRL" ou "USD" no header/navbar
    var navText = (qs('nav') || document.body).textContent || ''
    if (/\bBRL\b/.test(navText)) return 'BRL'
    if (/\bUSD\b/.test(navText)) return 'USD'
    // Verificar símbolo nos preços da página
    var precoEl = qs('.cart-item-subtotal, [class*="preco"], .product-price')
    if (precoEl) {
      var txt = precoEl.textContent || ''
      if (txt.indexOf('R$') >= 0) return 'BRL'
      if (txt.indexOf('US$') >= 0 || txt.indexOf('$') >= 0) return 'USD'
    }
    return 'BRL'
  }
  function parsearPreco (el) {
    if (!el) return null
    return parseFloat((el.textContent || '').replace(/[^0-9.]/g, '')) || null
  }
  function parsearPeso () {
    var rows = document.querySelectorAll('table tr, .spec-row')
    for (var i = 0; i < rows.length; i++) {
      var t = rows[i].textContent.toLowerCase()
      if (t.indexOf('peso') >= 0 || t.indexOf('weight') >= 0) {
        var td = rows[i].querySelector('td:last-child, .spec-value')
        if (td) return parseFloat(td.textContent.replace(/[^0-9.]/g, '')) || null
      }
    }
    return null
  }
  function extrairSlugDoGrupo () {
    var m = (document.referrer || '').match(/\/grupo\/([^/?]+)/)
    if (m) return m[1]
    var el = qs('[data-grupo]')
    return el ? el.dataset.grupo : null
  }
  function lerImpostoLocal () {
    var el = qs('[class*="imposto"], [class*="tax"]')
    if (el) { var m = el.textContent.match(/(\d+)%/); if (m) return parseInt(m[1]) }
    var MAPA = {'bath-and-body-works':8,'walmart':8,'trader-joes':8,'bjs':8,'achados-e-favoritos-da-fabi':8}
    return MAPA[extrairSlugDoGrupo()] || 0
  }
  function lerItensCarrinho () {
    var itens = []
    document.querySelectorAll('.cart-item').forEach(function (el) {
      var nomeEl = el.querySelector('h6')
      var precoEl = el.querySelector('.cart-item-subtotal')
      var qtdEl = el.querySelector('input[type="number"]')
      if (nomeEl) {
        itens.push({
          nome: nomeEl.textContent.trim(),
          preco: precoEl ? (parseFloat(precoEl.getAttribute('data-original-price')) || parsearPreco(precoEl)) : null,
          quantidade: qtdEl ? (parseInt(qtdEl.value) || 1) : 1
        })
      }
    })
    return itens
  }
  function qs (sel, parent) { return (parent || document).querySelector(sel) }

  // ========== AÇÕES REAIS ==========
  function salvarEstadoChat () {
    try {
      localStorage.setItem(STORAGE.estado_pendente, JSON.stringify({
        historico: historico, estava_aberto: widgetAberto, timestamp: Date.now()
      }))
    } catch (e) {}
  }

  async function executarAcao (acao, p) {
    p = p || {}
    var acoes = {
      adicionar_carrinho: function () {
        var pid = p.produto_id
        // Se Claude não mandou produto_id, tentar extrair do texto da última busca
        if (!pid && historico.length > 0) {
          for (var hi = historico.length - 1; hi >= 0; hi--) {
            var m = (historico[hi].content || '').match(/ID:(\d+)/)
            if (m) { pid = parseInt(m[1]); break }
          }
        }
        if (!pid) {
          adicionarMsg('assistant', '⚠️ Não consegui identificar o produto. Me diz o ID ou nome exato?')
          return
        }
        adicionarMsg('assistant', '🛒 Adicionando...')
        return fetch('/api/copiloto/add-cart', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ produto_id: pid, quantidade: p.quantidade || 1 }),
          credentials: 'same-origin'
        }).then(function(r) { return r.json() }).then(function(d) {
          var msgs = document.getElementById('bz-copiloto-messages')
          if (msgs.lastChild) msgs.removeChild(msgs.lastChild); historico.pop()
          if (d.error) {
            adicionarMsg('assistant', '❌ ' + d.error)
          } else {
            var badge = qs('.cart-count, [class*="carrinho"] [class*="count"]')
            if (badge && d.total_itens) badge.textContent = d.total_itens
            adicionarMsg('assistant', '✅ ' + (d.produto_nome || 'Produto') + ' adicionado ao carrinho!')
            if (window.location.pathname === '/carrinho') { salvarEstadoChat(); setTimeout(function(){ window.location.reload() }, 1500) }
          }
        }).catch(function(err) {
          var msgs = document.getElementById('bz-copiloto-messages')
          if (msgs.lastChild) msgs.removeChild(msgs.lastChild); historico.pop()
          adicionarMsg('assistant', '❌ Erro: ' + (err.message || 'falha na conexão'))
        })
      },
      trocar_moeda_brl: function () { salvarEstadoChat(); window.location.href = '/lang/pt' },
      trocar_moeda_usd: function () { salvarEstadoChat(); window.location.href = '/lang/en' },
      consultar_status_pedido: function () { adicionarMsg('assistant', '📦 Use a página de rastreamento: /rastreamento com seu código dos Correios.') },
      abrir_whatsapp_vendas: function () { window.open('https://wa.me/' + CONFIG.whatsapp_vendas + (p.mensagem ? '?text=' + encodeURIComponent(p.mensagem) : ''), '_blank') },
      ir_para_checkout: function () { salvarEstadoChat(); window.location.href = '/checkout' },
      ir_para_contato: function () { salvarEstadoChat(); window.location.href = '/contato' },
      ir_para_clube: function () { salvarEstadoChat(); window.location.href = '/clube/recarga' },
      ir_para_meus_dados: function () { salvarEstadoChat(); window.location.href = '/meus-dados' },
      buscar_produto: function () { return buscarProdutoInteligente(p.termo || p.busca || '') },
      ir_para_grupo: function () { salvarEstadoChat(); window.location.href = '/grupo/' + (p.slug || '') },
      criar_ticket_suporte: function () { return criarTicket('suporte', p) },
      criar_ticket_duvida: function () { return criarTicket('duvidas_gerais', p) },
      nenhuma: function () {}
    }
    var fn = acoes[acao] || acoes.nenhuma
    return await fn()
  }

  async function criarTicket (cat, p) {
    try {
      var r = await fetch('/api/copiloto/ticket', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ categoria: cat, assunto: p.assunto || 'Via Co-Piloto', mensagem: p.mensagem || p.descricao || '' })
      })
      var d = await r.json()
      if (d.sucesso) adicionarMsg('assistant', '✅ Chamado ' + d.numero_ticket + ' aberto! Resposta em até ' + d.prazo_resposta + '.')
    } catch (e) { adicionarMsg('assistant', 'Não consegui abrir o chamado. Tenta novamente?') }
  }

  // ========== ENVIO DE MENSAGEM ==========
  async function enviarMensagem () {
    if (enviando) return
    var input = document.getElementById('bz-copiloto-input')
    var texto = (input.value || '').trim()
    if (!texto) return
    input.value = ''
    enviando = true
    adicionarMsg('user', texto)

    var msgs = document.getElementById('bz-copiloto-messages')
    var typing = document.createElement('div')
    typing.className = 'bz-msg bz-msg-assistant bz-typing'
    typing.innerHTML = '<div class="bz-msg-bubble"><span class="bz-dot"></span><span class="bz-dot"></span><span class="bz-dot"></span></div>'
    msgs.appendChild(typing)
    scrollBottom()

    try {
      var ctx = lerContextoPagina()
      var r = await fetch('/api/copiloto/chat', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ mensagem: texto, contexto: ctx, historico: historico.slice(-(CONFIG.max_historico_enviado * 2)) })
      })
      if (typing.parentNode) typing.parentNode.removeChild(typing)
      if (!r.ok) throw new Error('HTTP ' + r.status)
      var d = await r.json()
      adicionarMsg('assistant', d.resposta || 'Hmm, não consegui processar. Tenta de novo?')
      if (d.acao_frontend && d.acao_frontend.tipo && d.acao_frontend.tipo !== 'nenhuma') {
        await executarAcao(d.acao_frontend.tipo, d.acao_frontend.parametros)
      }
      if (d.max_tentativas_problema != null) estado_suporte.max_tentativas = d.max_tentativas_problema
      if (d.oferecer_ticket) estado_suporte.tentativas = 999
    } catch (err) {
      if (typing.parentNode) typing.parentNode.removeChild(typing)
      adicionarMsg('assistant', 'Ops, tive um problema de conexão. Tenta de novo? 😅')
    }
    enviando = false
  }

  async function buscarProdutoInteligente (termo) {
    if (!termo) return
    adicionarMsg('assistant', '🔍 Buscando "' + termo + '"...')
    try {
      var r = await fetch('/api/copiloto/buscar-produto?q=' + encodeURIComponent(termo))
      var d = await r.json()
      // Remover mensagem de "buscando"
      var msgs = document.getElementById('bz-copiloto-messages')
      if (msgs.lastChild) msgs.removeChild(msgs.lastChild)
      historico.pop()

      if (d.produtos && d.produtos.length > 0) {
        var texto = 'Encontrei ' + d.produtos.length + ' resultado(s) para "' + termo + '":\n\n'
        d.produtos.forEach(function (p, i) {
          texto += '• ' + p.nome
          if (p.preco) texto += ' — US$ ' + parseFloat(p.preco).toFixed(2)
          if (p.grupo_nome) texto += ' (no grupo ' + p.grupo_nome + ')'
          texto += '\n'
        })
        if (d.grupos && d.grupos.length > 0) {
          texto += '\nEsse produto está disponível nos Grupos de Compras. '
          texto += 'Quer que eu te leve para algum deles?'
        }
        adicionarMsg('assistant', texto)
      } else {
        adicionarMsg('assistant', 'Não encontrei "' + termo + '" no catálogo. Pode ser que esteja em um Grupo de Compras que ainda não está ativo, ou com outro nome. Quer que eu te leve para a página de Grupos de Compras?')
      }
    } catch (e) {
      adicionarMsg('assistant', 'Não consegui buscar agora. Tenta ir em Grupos de Compras e procurar por lá.')
    }
  }

  // ========== WIDGET UI ==========
  function criarWidget () {
    var c = document.createElement('div'); c.id = 'bz-copiloto'
    c.innerHTML = '<div id="bz-copiloto-launcher" role="button" tabindex="0" aria-label="Abrir Co-Piloto Braziliana">' +
      '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.38 5.07L2 22l4.93-1.38C8.42 21.5 10.15 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z" fill="#fff"/><circle cx="8" cy="12" r="1.5" fill="#0b1f3a"/><circle cx="12" cy="12" r="1.5" fill="#0b1f3a"/><circle cx="16" cy="12" r="1.5" fill="#0b1f3a"/></svg>' +
      '<span>Bri</span></div>' +
      '<div id="bz-copiloto-badge" style="display:none"></div>' +
      '<div id="bz-copiloto-panel" style="display:none" role="dialog" aria-label="Co-Piloto Braziliana">' +
        '<div id="bz-copiloto-header"><div class="bz-header-info"><strong>Bri</strong><small>Co-Piloto Braziliana</small></div>' +
        '<button id="bz-copiloto-close" aria-label="Fechar">&times;</button></div>' +
        '<div id="bz-copiloto-messages"></div>' +
        '<div id="bz-copiloto-input-area">' +
          '<input type="text" id="bz-copiloto-input" placeholder="Fala comigo..." maxlength="2000" autocomplete="off" />' +
          '<button id="bz-copiloto-send" aria-label="Enviar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z" fill="#fff"/></svg></button>' +
        '</div></div>'
    document.body.appendChild(c)

    var launcher = document.getElementById('bz-copiloto-launcher')
    var closeBtn = document.getElementById('bz-copiloto-close')
    var sendBtn = document.getElementById('bz-copiloto-send')
    var input = document.getElementById('bz-copiloto-input')
    var badge = document.getElementById('bz-copiloto-badge')

    launcher.addEventListener('click', function () { toggle() })
    launcher.addEventListener('keydown', function (e) { if (e.key === 'Enter') toggle() })
    closeBtn.addEventListener('click', function () { fechar() })
    sendBtn.addEventListener('click', function () { enviarMensagem() })
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensagem() } })
    badge.addEventListener('click', function () { badge.style.display = 'none'; abrir() })
  }

  function toggle () { widgetAberto ? fechar() : abrir() }
  function abrir () {
    widgetAberto = true
    document.getElementById('bz-copiloto-panel').style.display = 'flex'
    document.getElementById('bz-copiloto-badge').style.display = 'none'
    localStorage.setItem(STORAGE.aberto, '1')
    var msgs = document.getElementById('bz-copiloto-messages')
    if (msgs.children.length === 0 && historico.length === 0) {
      adicionarMsg('assistant', 'Oi! Sou a Bri, sua copiloto de compras na Braziliana. 😊\nComo posso te ajudar?')
    }
    setTimeout(function () { document.getElementById('bz-copiloto-input').focus() }, 100)
    scrollBottom()
  }
  function fechar () {
    widgetAberto = false
    document.getElementById('bz-copiloto-panel').style.display = 'none'
    localStorage.setItem(STORAGE.aberto, '0')
  }
  function mostrarBadge (t) {
    var b = document.getElementById('bz-copiloto-badge')
    if (!b || widgetAberto) return
    b.textContent = t; b.style.display = 'block'
  }
  function adicionarMsg (role, texto) {
    var msgs = document.getElementById('bz-copiloto-messages')
    var div = document.createElement('div')
    div.className = 'bz-msg bz-msg-' + role
    div.innerHTML = '<div class="bz-msg-bubble">' + formatarTexto(texto) + '</div>'
    msgs.appendChild(div)
    historico.push({ role: role, content: texto })
    salvarHistorico(); scrollBottom()
  }
  function formatarTexto (t) {
    return (t || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>').replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
  }
  function scrollBottom () { var m = document.getElementById('bz-copiloto-messages'); if (m) m.scrollTop = m.scrollHeight }

  // ========== PERSISTÊNCIA ==========
  function salvarHistorico () { try { localStorage.setItem(STORAGE.historico, JSON.stringify(historico.slice(-50))) } catch(e){} }
  function restaurarEstado () {
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
    } catch(e){}
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
        if (estado.estava_aberto) abrir()
      }
    } catch(e){}
    if (localStorage.getItem(STORAGE.aberto) === '1') abrir()
  }

  // ========== GATILHOS PROATIVOS ==========
  function iniciarGatilhos (ctx) {
    var disparados = JSON.parse(localStorage.getItem(STORAGE.gatilhos) || '{}')
    if (ctx.pagina === 'produto' && ctx.produto_id && !disparados[ctx.url_atual]) {
      setTimeout(function () {
        if (!widgetAberto) {
          var msg = 'Quer saber quanto fica esse produto no total?'
          if (ctx.produto_preco_usd && ctx.produto_peso_kg) {
            var faixas = [1,2,3,4,5,6,7,8,9,10,15,20,25,30]
            var faixa = faixas.find(function(f){return f >= ctx.produto_peso_kg}) || 30
            var total = ctx.produto_preco_usd + ctx.produto_preco_usd*(ctx.imposto_local_pct/100) + faixa*39 + ctx.produto_preco_usd*0.80
            msg = 'Esse produto fica ~R$ ' + Math.round(total * 5.80) + ' no total. Quer calcular em detalhe?'
          }
          mostrarBadge(msg)
          disparados[ctx.url_atual] = true
          localStorage.setItem(STORAGE.gatilhos, JSON.stringify(disparados))
        }
      }, CONFIG.gatilho_tempo_ms)
    }
    if (ctx.pagina === 'carrinho' && ctx.carrinho_itens && ctx.carrinho_itens.length > 0) {
      setTimeout(function () { if (!widgetAberto) mostrarBadge('Quer que eu revise seu pedido antes de fechar?') }, 5000)
    }
    if (ctx.pagina === 'grupo') {
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
    var s = document.createElement('style')
    s.textContent = '#bz-copiloto{position:fixed;bottom:20px;right:20px;z-index:99999;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}' +
      '#bz-copiloto-launcher{display:flex;align-items:center;gap:6px;background:linear-gradient(135deg,#0b1f3a 0%,#1d4ed8 100%);color:#fff;border:none;border-radius:24px;padding:10px 18px 10px 14px;cursor:pointer;box-shadow:0 4px 16px rgba(11,31,58,.3);transition:transform .2s,box-shadow .2s;font-size:13px;font-weight:600;user-select:none;line-height:1}' +
      '#bz-copiloto-launcher:hover{transform:scale(1.04);box-shadow:0 6px 24px rgba(11,31,58,.4)}' +
      '#bz-copiloto-launcher svg{flex-shrink:0;width:20px;height:20px}' +
      '#bz-copiloto-badge{position:absolute;bottom:48px;right:0;background:#fff;color:#0b1f3a;border-radius:14px 14px 4px 14px;padding:10px 14px;max-width:260px;font-size:13px;line-height:1.4;box-shadow:0 6px 24px rgba(0,0,0,.12);cursor:pointer;animation:bzBadgeIn .3s ease-out}' +
      '@keyframes bzBadgeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}' +
      '#bz-copiloto-panel{position:absolute;bottom:50px;right:0;width:340px;max-width:calc(100vw - 24px);height:420px;max-height:calc(100vh - 140px);background:#fff;border-radius:18px;box-shadow:0 10px 40px rgba(0,0,0,.16);display:flex;flex-direction:column;overflow:hidden;animation:bzPanelIn .2s ease-out}' +
      '@keyframes bzPanelIn{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}' +
      '#bz-copiloto-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:linear-gradient(135deg,#0b1f3a 0%,#1d4ed8 100%);color:#fff}' +
      '.bz-header-info strong{display:block;font-size:14px}.bz-header-info small{opacity:.8;font-size:11px}' +
      '#bz-copiloto-close{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:0 2px;line-height:1;opacity:.8}#bz-copiloto-close:hover{opacity:1}' +
      '#bz-copiloto-messages{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px}' +
      '.bz-msg{display:flex}.bz-msg-user{justify-content:flex-end}.bz-msg-assistant{justify-content:flex-start}' +
      '.bz-msg-bubble{max-width:88%;padding:8px 12px;border-radius:14px;font-size:13px;line-height:1.45;word-wrap:break-word}' +
      '.bz-msg-user .bz-msg-bubble{background:#0b1f3a;color:#fff;border-bottom-right-radius:4px}' +
      '.bz-msg-assistant .bz-msg-bubble{background:#f1f5f9;color:#1e293b;border-bottom-left-radius:4px}' +
      '.bz-typing .bz-msg-bubble{display:flex;gap:4px;padding:12px 16px}' +
      '.bz-dot{width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:bzDot 1.2s infinite}' +
      '.bz-dot:nth-child(2){animation-delay:.2s}.bz-dot:nth-child(3){animation-delay:.4s}' +
      '@keyframes bzDot{0%,80%,100%{transform:scale(.6);opacity:.4}40%{transform:scale(1);opacity:1}}' +
      '#bz-copiloto-input-area{display:flex;align-items:center;gap:6px;padding:10px 12px;border-top:1px solid #e2e8f0}' +
      '#bz-copiloto-input{flex:1;border:1px solid #e2e8f0;border-radius:20px;padding:8px 14px;font-size:13px;outline:none;transition:border-color .2s}' +
      '#bz-copiloto-input:focus{border-color:#1d4ed8}' +
      '#bz-copiloto-send{width:36px;height:36px;border-radius:50%;background:#0b1f3a;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .2s}' +
      '#bz-copiloto-send:hover{background:#1d4ed8}' +
      '#bz-copiloto-send svg{width:16px;height:16px}' +
      '@media(max-width:480px){#bz-copiloto{bottom:14px;right:10px}#bz-copiloto-panel{width:calc(100vw - 20px);height:calc(100vh - 120px);bottom:46px;right:-2px}#bz-copiloto-launcher{padding:8px 14px 8px 10px;font-size:12px}}' +
      '@media(max-width:991.98px){#bz-copiloto{bottom:76px}}'
    document.head.appendChild(s)
  }

  // ========== MOVER WHATSAPP ==========
  function moverWhatsApp () {
    var wa = document.getElementById('whatsapp-float')
    if (wa) { wa.style.right = 'auto'; wa.style.left = '30px' }
  }

  // ========== BOOT ==========
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { moverWhatsApp(); init() })
  } else { moverWhatsApp(); init() }
})()
