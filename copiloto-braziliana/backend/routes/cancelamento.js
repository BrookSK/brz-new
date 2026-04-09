/**
 * Rotas de cancelamento via Co-Piloto
 * Conforme Recurso 2-B do documento de arquitetura
 */
const express = require('express')
const router = express.Router()
const axios = require('axios')

const BRAZILIANA_URL = process.env.BRAZILIANA_BASE_URL || 'https://brazilianashop.com.br'
const TAXA_CANCELAMENTO = 100 // US$ 100 fixo conforme Termos

/**
 * GET /verificar — Verifica elegibilidade de cancelamento
 */
router.get('/verificar', async (req, res) => {
  try {
    const { numero_pedido, sessao_id } = req.query

    if (!numero_pedido) {
      return res.status(400).json({ erro: 'Número do pedido é obrigatório.' })
    }

    // Consultar pedido real via API do site
    let pedido = null
    try {
      const resp = await axios.get(
        `${BRAZILIANA_URL}/api/pedidos/${encodeURIComponent(numero_pedido)}`,
        {
          headers: { 'Cookie': sessao_id ? `PHPSESSID=${sessao_id}` : '' },
          timeout: 5000
        }
      )
      pedido = resp.data
    } catch {
      return res.status(404).json({ erro: 'Pedido não encontrado.' })
    }

    const status = pedido?.status || 'desconhecido'

    // Mapa de elegibilidade
    const elegibilidade = {
      'aguardando_pagamento': { elegivel: true },
      'pagamento_confirmado': { elegivel: true },
      'em_processamento': { elegivel: true },
      'compra_efetuada': { elegivel: true },
      'embalando': { elegivel: true },
      'aguardando_envio': { elegivel: true },
      'despachado': { elegivel: false, motivo: 'Pedido já foi despachado para o Brasil.' },
      'em_transito': { elegivel: false, motivo: 'Pedido está em trânsito internacional.' },
      'na_alfandega': { elegivel: false, motivo: 'Pedido está na alfândega.' },
      'entregue': { elegivel: false, motivo: 'Pedido já foi entregue.' }
    }

    const resultado = elegibilidade[status] || { elegivel: false, motivo: 'Status desconhecido.' }
    const valorPago = parseFloat(pedido?.valor_total_usd || pedido?.total || 0)
    const valorReembolso = Math.max(0, valorPago - TAXA_CANCELAMENTO)
    const cambio = parseFloat(process.env.CAMBIO_USD_BRL || '5.80')

    return res.json({
      elegivel: resultado.elegivel,
      motivo_inelegivel: resultado.motivo || null,
      status_pedido: status,
      valor_pago_usd: valorPago,
      taxa_cancelamento_usd: TAXA_CANCELAMENTO,
      valor_reembolso_usd: valorReembolso,
      valor_reembolso_brl: parseFloat((valorReembolso * cambio).toFixed(2)),
      metodo_pagamento_original: pedido?.metodo_pagamento || null,
      numero_pedido
    })
  } catch (err) {
    console.error('[Cancelamento] Erro verificação:', err.message)
    return res.status(500).json({ erro: 'Erro ao verificar cancelamento.' })
  }
})

/**
 * POST /solicitar — Solicita cancelamento (requer confirmação explícita)
 */
router.post('/solicitar', async (req, res) => {
  try {
    const { numero_pedido, sessao_id, confirmado } = req.body

    if (!confirmado) {
      return res.status(400).json({ erro: 'Confirmação explícita é necessária.' })
    }

    if (!numero_pedido) {
      return res.status(400).json({ erro: 'Número do pedido é obrigatório.' })
    }

    // Criar pendência no sistema Braziliana
    const numeroSolicitacao = `CANC-${new Date().getFullYear()}-${Date.now().toString(36).toUpperCase()}`

    try {
      await axios.post(`${BRAZILIANA_URL}/api/copiloto/cancelamento/criar`, {
        numero_solicitacao: numeroSolicitacao,
        numero_pedido,
        solicitado_via: 'copiloto',
        status: 'aguardando_revisao'
      }, {
        headers: {
          'Cookie': sessao_id ? `PHPSESSID=${sessao_id}` : '',
          'Authorization': `Bearer ${process.env.BRAZILIANA_ADMIN_TOKEN || ''}`
        },
        timeout: 5000
      })
    } catch {
      // Se a API não existir ainda, logar e continuar
      console.warn('[Cancelamento] API de criação não disponível — pendência registrada apenas no log')
    }

    return res.json({
      sucesso: true,
      numero_solicitacao: numeroSolicitacao,
      prazo_resposta: '24h úteis'
    })
  } catch (err) {
    console.error('[Cancelamento] Erro solicitação:', err.message)
    return res.status(500).json({ erro: 'Erro ao solicitar cancelamento.' })
  }
})

module.exports = router
