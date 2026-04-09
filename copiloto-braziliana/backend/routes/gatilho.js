/**
 * GET /api/copiloto/gatilho
 * Gera mensagem proativa baseada no contexto da página atual
 */
const express = require('express')
const router = express.Router()
const { calcularCustoTotal, obterImpostoLocal } = require('../services/calculo')

router.get('/', async (req, res) => {
  try {
    const { pagina, produto_preco_usd, produto_peso_kg, produto_grupo, carrinho_itens, grupo_nome } = req.query

    let mensagem = null

    if (pagina === 'produto' && produto_preco_usd && produto_peso_kg) {
      const imposto = obterImpostoLocal(produto_grupo || '')
      const calc = calcularCustoTotal(
        parseFloat(produto_preco_usd),
        parseFloat(produto_peso_kg),
        imposto
      )
      mensagem = `Esse produto fica ~R$ ${calc.total_brl.toFixed(0)} no total. Quer que eu calcule em detalhe?`
    } else if (pagina === 'carrinho' && carrinho_itens) {
      mensagem = 'Quer que eu revise seu pedido antes de fechar?'
    } else if (pagina === 'grupo' && grupo_nome) {
      mensagem = `Me fala o que você procura no ${grupo_nome} 😊`
    }

    return res.json({ mensagem })
  } catch (err) {
    console.error('[Gatilho] Erro:', err.message)
    return res.json({ mensagem: null })
  }
})

module.exports = router
