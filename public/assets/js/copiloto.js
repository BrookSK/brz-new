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
  var ultimaBusca = [] // Guarda produtos da última busca para resolver IDs
  var ultimoProdutoAdicionado = null // Guarda o último produto adicionado com sucesso

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
      var subEl = qs('.subtotal-value')
      var totalEl = qs('.total-value')
      var taxaEl = qs('.taxa-servico-value')
      var impostosEl = qs('.impostos-value')
      var impostoLocalEl = qs('.imposto-local-value')
      var freteEl = qs('.frete-value')
      ctx.carrinho_subtotal_visivel = subEl ? subEl.textContent.trim() : null
      ctx.carrinho_total_visivel = totalEl ? totalEl.textContent.trim() : null
      ctx.carrinho_taxa_servico_visivel = taxaEl ? taxaEl.textContent.trim() : null
      ctx.carrinho_impostos_br_visivel = impostosEl ? impostosEl.textContent.trim() : null
      ctx.carrinho_imposto_local_visivel = impostoLocalEl ? impostoLocalEl.textContent.trim() : null
      ctx.carrinho_frete_visivel = freteEl ? freteEl.textContent.trim() : null
      var qtdMatch = (qs('.card-header h5, .card-header') || {}).textContent || ''
      var mQtd = qtdMatch.match(/(\d+)\s*iten/i)
      ctx.carrinho_total_itens = mQtd ? parseInt(mQtd[1]) : ctx.carrinho_itens.length
    } else if (url === '/checkout') ctx.pagina = 'checkout'
    else if (url === '/rastreamento') ctx.pagina = 'rastreamento'
    else if (url.match(/\/como-funciona/)) ctx.pagina = 'como-funciona'
    else if (url.match(/\/clube/)) ctx.pagina = 'clube'
    else if (url === '/meus-dados') ctx.pagina = 'minha-conta'
    else if (url === '/contato') ctx.pagina = 'contato'
    else if (url === '/faq') ctx.pagina = 'faq'
    else if (url === '/produtos') ctx.pagina = 'catalogo'
    else if (url.match(/\/assessoria\/orcamento/)) {
      ctx.pagina = 'orcamento'
      // Ler dados do orçamento da tela
      var subEl = qs('#subtotal')
      var taxaEl = qs('#taxaServico')
      var freteEl = qs('#frete')
      var impostosEl = qs('#impostos')
      var totalEl = qs('#total')
      ctx.orcamento_subtotal = subEl ? subEl.textContent.trim() : null
      ctx.orcamento_taxa_servico = taxaEl ? taxaEl.textContent.trim() : null
      ctx.orcamento_frete = freteEl ? freteEl.textContent.trim() : null
      ctx.orcamento_impostos = impostosEl ? impostosEl.textContent.trim() : null
      ctx.orcamento_total = totalEl ? totalEl.textContent.trim() : null
      // Ler produtos do orçamento
      ctx.orcamento_produtos = []
      document.querySelectorAll('[data-produto-id]').forEach(function(el) {
        ctx.orcamento_produtos.push({
          nome: (qs('h6, .fw-semibold', el) || {}).textContent?.trim() || '',
          preco: (qs('[class*="price"], .text-primary', el) || {}).textContent?.trim() || '',
        })
      })
      // Ler ID do orçamento da URL
      var mOrcId = url.match(/orcamento_id=(\d+)/)
      ctx.orcamento_id = mOrcId ? parseInt(mOrcId[1]) : null
    }
    var badge = qs('[class*="carrinho"] [class*="count"], .cart-count')
    if (badge) ctx.carrinho_qtd = parseInt(badge.textContent) || 0
    var nomeEl = qs('.user-menu .dropdown-toggle, #userDropdown, [class*="user-name"], [class*="usuario-nome"]')
    if (nomeEl) ctx.usuario_nome = nomeEl.textContent.trim().replace(/[\n\r]+/g, ' ').trim()
    return ctx
  }
  function verificarLogin () {
    // Cookie de sessão é httpOnly — não dá pra ler via JS
    // Detectar login pela presença de elementos de UI
    return !!(
      qs('[href*="logout"], [href*="sair"], a[href="/logout"]') ||
      qs('[class*="minha-conta"], [class*="my-account"], [href*="meus-dados"], [href*="meus-pedidos"]') ||
      qs('.user-menu, .dropdown-user, [class*="usuario-logado"]')
    )
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
        var pid = p.produto_id ? parseInt(p.produto_id) : 0
        var qtd = p.quantidade || 1
        if (pid > 0) return addToCart(pid, qtd)

        // Detectar "mais um", "de novo", "outro igual" → usar último produto adicionado
        var ultimaMsg = ''
        for (var ui = historico.length - 1; ui >= Math.max(0, historico.length - 3); ui--) {
          if (historico[ui].role === 'user') { ultimaMsg = historico[ui].content.toLowerCase(); break }
        }
        if (ultimoProdutoAdicionado && ultimaMsg.match(/mais\s*(um|1|uma|\d)|de\s*novo|outro\s*(igual|desse|dessa)|mesm[oa]|repet|novamente/i)) {
          return addToCart(ultimoProdutoAdicionado.id, qtd)
        }

        // Sem produto_id — resolver via busca na API
        // Extrair termo de busca: nome do Claude OU mensagens recentes do usuário
        var termo = (p.nome || p.produto_nome || '').trim()
        if (!termo) {
          // Pegar das últimas mensagens do usuário
          for (var ui = historico.length - 1; ui >= Math.max(0, historico.length - 6); ui--) {
            if (historico[ui].role === 'user') {
              var msg = historico[ui].content.toLowerCase()
              // Limpar palavras genéricas
              msg = msg.replace(/\b(coloca|adiciona|carrinho|meu|por favor|no|dele|dela|delas|deles|também|tambem|pode|quero|unidades?|mais|tem|temos|qual|quais|mostra|lista|que|o que)\b/gi, ' ').trim()
              // Pegar palavras com 3+ chars
              var palavras = msg.split(/\s+/).filter(function(w) { return w.replace(/[?!.,]/g,'').length >= 3 })
              if (palavras.length > 0) { termo = palavras.map(function(w){return w.replace(/[?!.,]/g,'')}).join(' '); break }
            }
          }
        }
        if (!termo || termo.length < 2) {
          adicionarMsg('assistant', '⚠️ Não consegui identificar o produto. Me diz o nome?')
          return
        }
        // Buscar na API
        return fetch('/api/copiloto/buscarproduto?q=' + encodeURIComponent(termo))
          .then(function(r) { return r.json() })
          .then(function(d) {
            if (d.produtos && d.produtos.length > 0) {
              ultimaBusca = d.produtos
              if (d.produtos.length === 1) {
                // Só 1 resultado → adicionar direto
                return addToCart(d.produtos[0].id, qtd)
              } else {
                // Múltiplos resultados → mostrar carrossel para o usuário escolher
                adicionarMsg('assistant', 'Encontrei ' + d.produtos.length + ' opções. Qual você quer?')
                renderizarCarrosselProdutos(d.produtos)
              }
            } else {
              adicionarMsg('assistant', '⚠️ Não encontrei "' + termo + '". Me diz o nome exato?')
            }
          }).catch(function() { adicionarMsg('assistant', '⚠️ Erro na busca. Tenta de novo?') })
      },
      trocar_moeda_brl: function () { salvarEstadoChat(); window.location.href = '/lang/pt' },
      trocar_moeda_usd: function () { salvarEstadoChat(); window.location.href = '/lang/en' },
      limpar_carrinho: function () {
        return fetch('/api/copiloto/clearcart', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          credentials: 'same-origin'
        }).then(function(r) { return r.json() }).then(function(d) {
          if (d.success) {
            adicionarMsg('assistant', '🗑️ Carrinho limpo!')
            var badge = qs('.cart-count, [class*="carrinho"] [class*="count"]')
            if (badge) badge.textContent = '0'
            if (window.location.pathname === '/carrinho') { salvarEstadoChat(); setTimeout(function(){ window.location.reload() }, 1000) }
          } else {
            adicionarMsg('assistant', '❌ ' + (d.error || 'Erro ao limpar'))
          }
        }).catch(function() {
          adicionarMsg('assistant', '❌ Não consegui limpar. Tenta pelo botão na página.')
        })
      },
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
      gerar_orcamento: function () { return gerarOrcamento(p.links || []) },
      aceitar_termos_assessoria: function () {
        // Chamar a API de adicionar ao carrinho da assessoria diretamente
        var orcamentoId = null
        var mOrcId = window.location.search.match(/orcamento_id=(\d+)/)
        if (mOrcId) orcamentoId = parseInt(mOrcId[1])
        // Também tentar pegar do job_id
        if (!orcamentoId) {
          var mJobId = window.location.search.match(/job_id=([a-f0-9]+)/)
          // Se só tem job_id, precisamos do orcamento_id — tentar do DOM
        }
        
        // Coletar produtos selecionados (checkboxes marcados)
        var selecionados = []
        document.querySelectorAll('.product-checkbox').forEach(function(cb, i) {
          if (!cb.checked) cb.checked = true // Marcar todos
          selecionados.push({ index: parseInt(cb.value || i), variacao_id: null })
        })
        
        // Marcar checkbox de termos
        var termosCheckbox = qs('#termosAceitos, input[type="checkbox"]')
        if (termosCheckbox && !termosCheckbox.checked) termosCheckbox.checked = true

        if (!orcamentoId && selecionados.length === 0) {
          adicionarMsg('assistant', '⚠️ Não encontrei o orçamento nesta página. Vai para a página do orçamento primeiro.')
          return
        }

        adicionarMsg('assistant', '🛒 Aceitando termos e adicionando ao carrinho...')
        
        return fetch('/assessoria/adicionar-ao-carrinho', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          credentials: 'same-origin',
          body: JSON.stringify({
            orcamento_id: orcamentoId,
            termos_aceitos: true,
            produtos_selecionados: selecionados
          })
        }).then(function(r) { return r.json() }).then(function(d) {
          var msgs = document.getElementById('bz-copiloto-messages')
          if (msgs.lastChild) msgs.removeChild(msgs.lastChild); historico.pop()
          
          if (d.success) {
            adicionarMsg('assistant', '✅ Termos aceitos e produtos adicionados ao carrinho!\nTe levo pro carrinho agora...')
            salvarEstadoChat()
            setTimeout(function() { window.location.href = d.redirect || '/carrinho' }, 1500)
          } else {
            adicionarMsg('assistant', '❌ ' + (d.message || 'Erro ao adicionar'))
            if (d.redirect) { salvarEstadoChat(); setTimeout(function() { window.location.href = d.redirect }, 2000) }
          }
        }).catch(function(err) {
          var msgs = document.getElementById('bz-copiloto-messages')
          if (msgs.lastChild) msgs.removeChild(msgs.lastChild); historico.pop()
          adicionarMsg('assistant', '❌ Erro: ' + (err.message || 'falha'))
        })
      },
      nenhuma: function () {}
    }
    var fn = acoes[acao] || acoes.nenhuma
    return await fn()
  }

  async function criarTicket (cat, p) {
    // Extrair assunto e mensagem do contexto da conversa se não fornecidos
    var assunto = p.assunto || ''
    var mensagem = p.mensagem || p.descricao || ''
    
    // Se mensagem vazia, montar a partir do histórico da conversa
    if (!mensagem) {
      var msgs = []
      for (var i = Math.max(0, historico.length - 6); i < historico.length; i++) {
        if (historico[i].role === 'user') msgs.push(historico[i].content)
      }
      mensagem = msgs.join('\n')
    }
    if (!assunto) assunto = 'Dúvida via Co-Piloto'
    if (!mensagem) mensagem = 'Ticket aberto via Co-Piloto.'

    try {
      var r = await fetch('/api/copiloto/ticket', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ 
          categoria: cat, 
          assunto: assunto, 
          mensagem: mensagem,
          numero_pedido: p.numero_pedido || null
        })
      })
      var d = await r.json()
      if (d.sucesso) adicionarMsg('assistant', '✅ Chamado ' + d.numero_ticket + ' aberto! Resposta em até ' + d.prazo_resposta + '.')
      else adicionarMsg('assistant', '❌ ' + (d.erro || 'Erro ao abrir ticket'))
    } catch (e) { adicionarMsg('assistant', 'Não consegui abrir o chamado. Tenta novamente?') }
  }

  function addToCart (produtoId, quantidade) {
    quantidade = quantidade || 1
    adicionarMsg('assistant', '🛒 Adicionando...')
    return fetch('/api/copiloto/addcart', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ produto_id: produtoId, quantidade: quantidade }),
      credentials: 'same-origin'
    }).then(function(r) { return r.json() }).then(function(d) {
      var msgs = document.getElementById('bz-copiloto-messages')
      if (msgs.lastChild) msgs.removeChild(msgs.lastChild); historico.pop()
      if (d.error) {
        adicionarMsg('assistant', '❌ ' + d.error)
      } else {
        ultimoProdutoAdicionado = { id: produtoId, nome: d.produto_nome || '', quantidade: quantidade }
        var badge = qs('.cart-count, [class*="carrinho"] [class*="count"]')
        if (badge && d.total_itens) badge.textContent = d.total_itens
        adicionarMsg('assistant', '✅ ' + (d.produto_nome || 'Produto') + ' adicionado ao carrinho!')
        if (window.location.pathname === '/carrinho') { salvarEstadoChat(); setTimeout(function(){ window.location.reload() }, 1500) }
      }
    }).catch(function(err) {
      var msgs = document.getElementById('bz-copiloto-messages')
      if (msgs.lastChild) msgs.removeChild(msgs.lastChild); historico.pop()
      adicionarMsg('assistant', '❌ Erro: ' + (err.message || 'falha'))
    })
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
      
      // Se não está na página do carrinho, buscar dados do carrinho via API
      if (ctx.pagina !== 'carrinho' && ctx.carrinho_itens.length === 0) {
        try {
          var cartResp = await fetch('/api/copiloto/meucarrinho', { credentials: 'same-origin' })
          var cartData = await cartResp.json()
          if (cartData.itens && cartData.itens.length > 0) {
            ctx.carrinho_itens = cartData.itens
            ctx.carrinho_total_itens = cartData.total_itens
            if (cartData.resumo) {
              ctx.carrinho_subtotal_visivel = 'R$ ' + cartData.itens.reduce(function(s,i){return s+(i.subtotal_brl||0)},0).toFixed(2).replace('.', ',')
              ctx.carrinho_total_visivel = 'R$ ' + (cartData.resumo.total_brl || 0).toFixed(2).replace('.', ',')
              ctx.carrinho_taxa_servico_visivel = 'R$ ' + (cartData.resumo.taxa_servico_brl || 0).toFixed(2).replace('.', ',')
              ctx.carrinho_impostos_br_visivel = 'R$ ' + (cartData.resumo.impostos_brl || 0).toFixed(2).replace('.', ',')
              ctx.carrinho_frete_visivel = 'Frete grátis'
            }
          }
        } catch (e) {}
      }

      var r = await fetch('/api/copiloto/chat', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ mensagem: texto, contexto: ctx, historico: historico.slice(-(CONFIG.max_historico_enviado * 2)) })
      })
      if (typing.parentNode) typing.parentNode.removeChild(typing)
      if (!r.ok) throw new Error('HTTP ' + r.status)
      var d = await r.json()

      // Para ações de carrinho: executar ANTES de mostrar a resposta do Claude
      // Assim não mostramos "Adicionei!" se a ação falhar
      var acaoTipo = (d.acao_frontend && d.acao_frontend.tipo) ? d.acao_frontend.tipo : 'nenhuma'
      var acaoParams = (d.acao_frontend && d.acao_frontend.parametros) ? d.acao_frontend.parametros : {}
      
      // Detectar se Claude disse que adicionou mas não mandou a ação
      var textoResp = d.resposta || ''
      if (acaoTipo === 'nenhuma' && textoResp.match(/(?:^|\n)\s*✅.*adicion/i)) {
        acaoTipo = 'adicionar_carrinho'
        var mId = textoResp.match(/ID[:\s]*(\d+)/i)
        if (mId) acaoParams.produto_id = parseInt(mId[1])
      }
      // Detectar se Claude disse que vai levar pro checkout mas não mandou a ação
      if (acaoTipo === 'nenhuma' && textoResp.match(/levo.*checkout|partiu.*checkout|levando.*checkout|bora.*checkout|finalizar.*compra/i)) {
        acaoTipo = 'ir_para_checkout'
      }
      // Detectar se Claude disse que limpou o carrinho
      if (acaoTipo === 'nenhuma' && textoResp.match(/carrinho.*limpo|carrinho.*zerado|limpo.*carrinho|zerado.*carrinho/i)) {
        acaoTipo = 'limpar_carrinho'
      }
      
      if (acaoTipo === 'adicionar_carrinho' || acaoTipo === 'limpar_carrinho') {
        // Executar ação primeiro — a função addToCart/limpar já mostra mensagem de sucesso/erro
        await executarAcao(acaoTipo, acaoParams)
        if (!textoResp.match(/adicion|✅|carrinho.*limpo|zerado/i)) {
          adicionarMsg('assistant', textoResp)
        }
      } else {
        adicionarMsg('assistant', textoResp || 'Hmm, não consegui processar. Tenta de novo?')
        if (acaoTipo !== 'nenhuma') {
          await executarAcao(acaoTipo, acaoParams)
        }
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
      var r = await fetch('/api/copiloto/buscarproduto?q=' + encodeURIComponent(termo))
      var d = await r.json()
      var msgs = document.getElementById('bz-copiloto-messages')
      if (msgs.lastChild) msgs.removeChild(msgs.lastChild)
      historico.pop()

      if (d.produtos && d.produtos.length > 0) {
        ultimaBusca = d.produtos
        adicionarMsg('assistant', 'Encontrei ' + d.produtos.length + ' resultado(s) para "' + termo + '":')
        // Renderizar carrossel de cards
        renderizarCarrosselProdutos(d.produtos)
      } else {
        adicionarMsg('assistant', 'Não encontrei "' + termo + '" no catálogo. Quer que eu te leve para os Grupos de Compras?')
      }
    } catch (e) {
      adicionarMsg('assistant', 'Não consegui buscar agora. Tenta de novo?')
    }
  }

  function renderizarCarrosselProdutos (produtos) {
    var msgs = document.getElementById('bz-copiloto-messages')
    var wrapper = document.createElement('div')
    wrapper.className = 'bz-carousel-wrapper'

    var track = document.createElement('div')
    track.className = 'bz-carousel-track'

    produtos.forEach(function (p) {
      var foto = p.foto || '/uploads/produtos/placeholder.jpg'
      if (foto && foto.indexOf('http') !== 0 && foto.indexOf('/') !== 0) foto = '/uploads/produtos/' + foto
      var preco = parseFloat(p.preco || 0)
      var precoBrl = (preco * 5.85).toFixed(2).replace('.', ',')

      var card = document.createElement('div')
      card.className = 'bz-product-card'
      card.innerHTML =
        '<div class="bz-pc-img" style="background-image:url(' + foto + ')"></div>' +
        '<div class="bz-pc-body">' +
          '<div class="bz-pc-name">' + (p.nome || '').substring(0, 50) + '</div>' +
          (p.grupo_nome ? '<div class="bz-pc-grupo">' + p.grupo_nome + '</div>' : '') +
          '<div class="bz-pc-price">R$ ' + precoBrl + '</div>' +
          '<div class="bz-pc-actions">' +
            '<button class="bz-pc-btn" onclick="window._bzAddCart(' + p.id + ')">🛒 Adicionar</button>' +
            '<a href="/produto/detalhes/' + p.id + '" class="bz-pc-link">Ver</a>' +
          '</div>' +
        '</div>'
      track.appendChild(card)
    })

    wrapper.appendChild(track)

    // Setas de navegação
    if (produtos.length > 1) {
      var btnLeft = document.createElement('button')
      btnLeft.className = 'bz-carousel-arrow bz-arrow-left'
      btnLeft.innerHTML = '‹'
      btnLeft.onclick = function () { track.scrollBy({ left: -180, behavior: 'smooth' }) }

      var btnRight = document.createElement('button')
      btnRight.className = 'bz-carousel-arrow bz-arrow-right'
      btnRight.innerHTML = '›'
      btnRight.onclick = function () { track.scrollBy({ left: 180, behavior: 'smooth' }) }

      wrapper.appendChild(btnLeft)
      wrapper.appendChild(btnRight)
    }

    msgs.appendChild(wrapper)
    scrollBottom()

    // Salvar no histórico como texto (para o Claude ter contexto)
    var textoHist = produtos.map(function (p) { return '[ID:' + p.id + '] ' + p.nome + ' US$' + p.preco }).join('\n')
    historico.push({ role: 'assistant', content: 'Produtos encontrados:\n' + textoHist })
    salvarHistorico()
  }

  // Função global para o onclick dos cards
  window._bzAddCart = function (produtoId) {
    addToCart(produtoId, 1)
  }

  async function gerarOrcamento (links) {
    // Se links não vieram dos parâmetros do Claude, extrair do histórico
    if (!links || links.length === 0) {
      links = []
      for (var i = 0; i < historico.length; i++) {
        var txt = historico[i].content || ''
        var urlMatches = txt.match(/https?:\/\/[^\s<>"']+/gi)
        if (urlMatches) {
          urlMatches.forEach(function (u) {
            // Ignorar URLs do próprio site
            if (u.indexOf('brazilianashop') < 0 && u.indexOf('wa.me') < 0) {
              links.push(u.replace(/[.,;:!?)]+$/, ''))
            }
          })
        }
      }
    }
    if (links.length === 0) {
      adicionarMsg('assistant', '⚠️ Não encontrei nenhum link de produto na conversa. Me manda os links dos produtos que você quer orçar!')
      return
    }
    adicionarMsg('assistant', '📋 Gerando orçamento com ' + links.length + ' link(s)... Isso pode levar alguns segundos.')
    try {
      var r = await fetch('/api/copiloto/orcamento', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ links: links })
      })
      var d = await r.json()
      var msgs = document.getElementById('bz-copiloto-messages')
      if (msgs.lastChild) msgs.removeChild(msgs.lastChild); historico.pop()

      if (d.sucesso || d.success) {
        var orcUrl = d.orcamento_url || (d.data && d.data.orcamento_url) || ''
        var totalLinks = d.total_links || (d.data && d.data.total) || links.length
        var orcId = d.orcamento_id || (d.data && d.data.orcamento_id) || ''
        var jobId = d.job_id || (d.data && d.data.job_id) || ''
        if (!orcUrl && orcId) orcUrl = '/assessoria/orcamento?orcamento_id=' + orcId
        
        adicionarMsg('assistant', '✅ Orçamento criado! Processando ' + totalLinks + ' produto(s)...\n\nVou te levar para a página do orçamento em instantes! ⏳')
        
        // Aguardar processamento e depois redirecionar
        salvarEstadoChat()
        setTimeout(function() {
          window.location.href = orcUrl
        }, 2000)
      } else {
        adicionarMsg('assistant', '❌ ' + (d.erro || 'Erro ao gerar orçamento'))
      }
    } catch (e) {
      adicionarMsg('assistant', '❌ Erro ao gerar orçamento. Tenta de novo?')
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
      '@media(max-width:991.98px){#bz-copiloto{bottom:76px}}' +
      // Carrossel de produtos
      '.bz-carousel-wrapper{position:relative;margin:4px 0;width:100%}' +
      '.bz-carousel-track{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding:4px 0}' +
      '.bz-carousel-track::-webkit-scrollbar{display:none}' +
      '.bz-product-card{flex:0 0 160px;scroll-snap-align:start;background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;font-size:12px}' +
      '.bz-pc-img{width:100%;height:100px;background-size:cover;background-position:center;background-color:#f1f5f9}' +
      '.bz-pc-body{padding:8px}' +
      '.bz-pc-name{font-weight:600;font-size:11px;line-height:1.3;height:28px;overflow:hidden;color:#1e293b}' +
      '.bz-pc-grupo{font-size:10px;color:#64748b;margin-top:2px}' +
      '.bz-pc-price{font-weight:700;color:#0b1f3a;font-size:13px;margin-top:4px}' +
      '.bz-pc-actions{display:flex;gap:4px;margin-top:6px}' +
      '.bz-pc-btn{flex:1;background:#0b1f3a;color:#fff;border:none;border-radius:6px;padding:5px 0;font-size:10px;cursor:pointer;font-weight:600}' +
      '.bz-pc-btn:hover{background:#1d4ed8}' +
      '.bz-pc-link{flex:0 0 auto;background:#f1f5f9;color:#0b1f3a;border-radius:6px;padding:5px 8px;font-size:10px;text-decoration:none;font-weight:600;text-align:center}' +
      '.bz-pc-link:hover{background:#e2e8f0}' +
      '.bz-carousel-arrow{position:absolute;top:50%;transform:translateY(-50%);width:24px;height:24px;border-radius:50%;background:rgba(11,31,58,.8);color:#fff;border:none;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2;line-height:1}' +
      '.bz-arrow-left{left:2px}' +
      '.bz-arrow-right{right:2px}'
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
