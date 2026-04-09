/**
 * POST /api/copiloto/status-pedido
 * Consulta status do pedido via API ou scraping
 * Exibe resultado NO CHAT — não navega para /rastreamento
 */
const express = require('express')
const router = express.Router()
const axios = require('axios')
const { extrairStatusPedido } = require('../services/scraping')

const BRAZILIANA_URL = process.env.BRAZILIANA_BASE_URL || 'https://brazilianashop.com.br'

router.post('/', async (req, res) => {
  try {
    const { identificador, sessao_id } = req.body

    if (!identificador) {
      return res.status(400).json({ erro: 'Identificador do pedido é obrigatório.' })
    }

    // Tentativa 1: API do site (se existir)
    try {
      const status = await axios.get(
        `${BRAZILIANA_URL}/api/pedidos/${encodeURIComponent(identificador)}/status`,
        {
          headers: { 'Cookie': sessao_id ? `PHPSESSID=${sessao_id}` : '' },
          timeout: 5000
        }
      )
      if (status.data) {
        return res.json(status.data)
      }
    } catch {
      // API não disponível — fallback para scraping
    }

    // Tentativa 2: Scraping da página de rastreamento
    const statusExtraido = await extrairStatusPedido(identificador)
    return res.json(statusExtraido)
  } catch (err) {
    console.error('[StatusPedido] Erro:', err.message)
    return res.status(500).json({ erro: 'Não foi possível consultar o status.' })
  }
})

module.exports = router
