/**
 * POST /api/copiloto/ticket
 * Proxy para a rota existente de tickets do sistema Braziliana
 * Conforme Recurso 10 do documento de arquitetura
 */
const express = require('express')
const router = express.Router()
const axios = require('axios')

const BRAZILIANA_URL = process.env.BRAZILIANA_BASE_URL || 'https://brazilianashop.com.br'

router.post('/', async (req, res) => {
  try {
    const { categoria, assunto, mensagem, numero_pedido, nome, email, sessao_id } = req.body

    if (!assunto || !mensagem) {
      return res.status(400).json({ erro: 'Assunto e mensagem são obrigatórios.' })
    }

    // Submeter via formulário de contato real do site
    const resposta = await axios.post(`${BRAZILIANA_URL}/contato`, {
      nome: nome || 'Cliente (via Co-Piloto)',
      email: email || '',
      telefone: '',
      assunto: categoria === 'suporte' ? 'Sobre Pedido' : 'Dúvida',
      mensagem: `[Via Co-Piloto Braziliana]\n${numero_pedido ? `Pedido: ${numero_pedido}\n` : ''}Categoria: ${categoria}\n\n${mensagem}`
    }, {
      headers: {
        'Content-Type': 'application/json',
        'Cookie': sessao_id ? `PHPSESSID=${sessao_id}` : ''
      },
      timeout: 8000
    })

    return res.json({
      sucesso: true,
      numero_ticket: `TKT-${Date.now().toString(36).toUpperCase()}`,
      prazo_resposta: '48h úteis'
    })
  } catch (err) {
    console.error('[Ticket] Erro:', err.message)
    return res.status(500).json({
      sucesso: false,
      erro: 'Não foi possível criar o ticket. Tente novamente.'
    })
  }
})

module.exports = router
