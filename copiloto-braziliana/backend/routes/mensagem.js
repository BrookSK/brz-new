/**
 * POST /api/copiloto/mensagem
 * Rota principal — classifica intenção + gera resposta + instrui ação
 * Conforme Recurso 3 do documento de arquitetura
 */
const express = require('express')
const router = express.Router()
const sanitizeHtml = require('sanitize-html')
const { classificarIntencao, gerarResposta } = require('../services/claude')
const { montarSystemPrompt } = require('../services/prompt')
const { compatibilizarContexto } = require('../services/compatibilizacao')
const { compararAlternativas, extrairQuantidadeDoNome } = require('../services/inteligencia-carrinho')

// Tentar carregar indexador (pode falhar se DB não configurado)
let buscarAlternativasCrossGrupo = null
let buscarProdutosParaOtimizarFaixa = null
try {
  const indexador = require('../workers/indexador-produtos')
  buscarAlternativasCrossGrupo = indexador.buscarAlternativasCrossGrupo
  buscarProdutosParaOtimizarFaixa = indexador.buscarProdutosParaOtimizarFaixa
} catch {}

router.post('/mensagem', async (req, res) => {
  try {
    const { mensagem, contexto: contextoRaw, historico, sessao_cookie } = req.body

    if (!mensagem || typeof mensagem !== 'string' || mensagem.trim().length === 0) {
      return res.status(400).json({ erro: 'Mensagem é obrigatória.' })
    }

    // Sanitizar input
    const mensagemLimpa = sanitizeHtml(mensagem.trim(), { allowedTags: [], allowedAttributes: {} })
    if (mensagemLimpa.length > 2000) {
      return res.status(400).json({ erro: 'Mensagem muito longa (máx 2000 caracteres).' })
    }

    // Compatibilizar contexto
    const contexto = compatibilizarContexto(contextoRaw || {})

    // Montar histórico (máx 10 mensagens)
    const maxHistorico = parseInt(process.env.MAX_HISTORICO_ENVIADO || '10')
    const historicoLimitado = Array.isArray(historico)
      ? historico.slice(-maxHistorico)
      : []

    // Adicionar mensagem atual ao histórico
    const mensagensParaClaude = [
      ...historicoLimitado,
      { role: 'user', content: mensagemLimpa }
    ]

    // Montar system prompt dinâmico
    const systemPrompt = await montarSystemPrompt(contexto, mensagemLimpa)

    // Enriquecer contexto com alternativas cross-grupo (se produto identificado)
    let alternativasTexto = ''
    try {
      if (buscarAlternativasCrossGrupo && contexto.produto_nome && contexto.produto_grupo) {
        const qtd = extrairQuantidadeDoNome(contexto.produto_nome)
        if (qtd > 0) {
          const alts = await buscarAlternativasCrossGrupo(contexto.produto_nome, contexto.produto_grupo, qtd)
          if (alts.length > 0) {
            alternativasTexto = '\n\nALTERNATIVAS CROSS-GRUPO ENCONTRADAS:\n' +
              alts.slice(0, 5).map(a =>
                `- ${a.nome} (${a.grupo_slug}) · US$ ${a.preco_usd} · ${a.unidades_no_kit} unid.`
              ).join('\n')
          }
        }
      }

      // Buscar produtos para otimizar faixa de peso do carrinho
      if (buscarProdutosParaOtimizarFaixa && contexto.carrinho_itens?.length > 0) {
        const { calcularCustoTotal } = require('../services/calculo')
        const pesoTotal = contexto.carrinho_itens.reduce((s, i) => s + (i.peso || 0) * (i.quantidade || 1), 0)
        if (pesoTotal > 0) {
          const calc = calcularCustoTotal(0, pesoTotal, 0)
          if (calc.espaco_restante_kg >= 0.3) {
            const opcoes = await buscarProdutosParaOtimizarFaixa(calc.espaco_restante_kg)
            if (opcoes.length > 0) {
              alternativasTexto += '\n\nPRODUTOS QUE CABEM NA FAIXA ATUAL (espaço: ' + calc.espaco_restante_kg + 'kg):\n' +
                opcoes.slice(0, 3).map(p =>
                  `- ${p.nome} · US$ ${p.preco_usd} · ${p.peso_kg}kg`
                ).join('\n')
            }
          }
        }
      }
    } catch (err) {
      console.warn('[Mensagem] Erro buscando alternativas:', err.message)
    }

    const systemPromptFinal = alternativasTexto
      ? systemPrompt + alternativasTexto
      : systemPrompt

    // Gerar resposta via Claude
    const resposta = await gerarResposta(systemPromptFinal, mensagensParaClaude, contexto)

    // Processar aprendizado (se houver pendência)
    if (resposta.aprendizado?.gerar_pendencia) {
      // Enviar para o sistema PHP via API (fire-and-forget)
      try {
        const axios = require('axios')
        await axios.post(`${process.env.BRAZILIANA_BASE_URL}/api/copiloto/aprendizado`, {
          ...resposta.aprendizado,
          sessao_id: sessao_cookie,
          mensagem_usuario: mensagemLimpa,
          resposta_bri: resposta.texto,
          pagina_origem: contexto.url_atual
        }, { timeout: 3000 }).catch(() => {})
      } catch {}
    }

    // Log da interação (fire-and-forget)
    try {
      const axios = require('axios')
      axios.post(`${process.env.BRAZILIANA_BASE_URL}/api/copiloto/log`, {
        sessao_id: sessao_cookie,
        mensagem_usuario: mensagemLimpa,
        resposta_bri: resposta.texto,
        acao: resposta.acao,
        parametros_acao: resposta.parametros,
        contexto_pagina: { pagina: contexto.pagina, url: contexto.url_atual },
        tokens_usados: resposta.tokens_usados
      }, { timeout: 3000 }).catch(() => {})
    } catch {}

    return res.json({
      resposta: resposta.texto,
      acao_frontend: {
        tipo: resposta.acao || 'nenhuma',
        parametros: resposta.parametros || {}
      },
      cards: resposta.sugestao_valor ? [{ tipo: 'comparacao', dados: resposta.sugestao_valor }] : [],
      calculo: {},
      requer_confirmacao: resposta.requer_confirmacao || false,
      mensagem_confirmacao: resposta.mensagem_confirmacao || null,
      max_tentativas_problema: resposta.max_tentativas_problema,
      oferecer_ticket: resposta.oferecer_ticket || false,
      tokens_usados: resposta.tokens_usados || 0
    })
  } catch (err) {
    console.error('[Mensagem] Erro:', err.message)
    return res.status(500).json({
      resposta: 'Desculpa, tive um problema técnico. Tenta de novo em alguns segundos?',
      acao_frontend: { tipo: 'nenhuma', parametros: {} },
      cards: [],
      calculo: {}
    })
  }
})

module.exports = router
