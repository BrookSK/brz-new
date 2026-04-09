/**
 * Serviço de scraping — extrai dados reais do HTML do site
 * Conforme estratégia de integração do documento de arquitetura
 */
const axios = require('axios')
const cheerio = require('cheerio')

const BRAZILIANA_URL = process.env.BRAZILIANA_BASE_URL || 'https://brazilianashop.com.br'
const TIMEOUT = parseInt(process.env.TIMEOUT_SITE_MS || '8000')

async function extrairProdutosDoGrupo (slug) {
  try {
    const resp = await axios.get(`${BRAZILIANA_URL}/grupo/${slug}`, { timeout: TIMEOUT })
    const $ = cheerio.load(resp.data)
    const produtos = []

    $('[class*="produto"], .product-card, .card').each((i, el) => {
      const $el = $(el)
      const id = $el.data('id') || $el.find('[data-id]').data('id') || null
      const nome = $el.find('[class*="nome"], h3, h4, h5, .product-title').first().text().trim()
      const precoText = $el.find('[class*="preco"], [class*="price"], .product-price').first().text()
      const preco = parseFloat((precoText || '0').replace(/[^0-9.]/g, '')) || null

      if (nome) {
        produtos.push({ id, nome, preco, foto_url: $el.find('img').first().attr('src') || null })
      }
    })

    return produtos
  } catch (err) {
    console.error(`[Scraping] Erro ao extrair grupo ${slug}:`, err.message)
    return []
  }
}

async function extrairDadosProduto (produtoId) {
  try {
    const resp = await axios.get(`${BRAZILIANA_URL}/produto/detalhes/${produtoId}`, { timeout: TIMEOUT })
    const $ = cheerio.load(resp.data)

    const nome = $('h1').first().text().trim()
    const precoText = $('[class*="preco"], [class*="price"]').first().text()
    const preco = parseFloat((precoText || '0').replace(/[^0-9.]/g, '')) || null

    // Peso: buscar na tabela de especificações
    let peso = null
    $('table tr, .spec-row').each((i, el) => {
      const text = $(el).text().toLowerCase()
      if (text.includes('peso') || text.includes('weight')) {
        const pesoText = $(el).find('td:last-child, .spec-value').text()
        peso = parseFloat((pesoText || '0').replace(/[^0-9.]/g, '')) || null
      }
    })

    const disponivel = !$('[class*="indisponivel"], [class*="unavailable"]').length

    return { id: produtoId, nome, preco, peso, disponivel, foto_url: $('img.product-image, .produto-imagem img').first().attr('src') || null }
  } catch (err) {
    console.error(`[Scraping] Erro ao extrair produto ${produtoId}:`, err.message)
    return null
  }
}

async function extrairStatusPedido (identificador) {
  try {
    const resp = await axios.get(`${BRAZILIANA_URL}/rastreamento?codigo=${encodeURIComponent(identificador)}`, { timeout: TIMEOUT })
    const $ = cheerio.load(resp.data)

    const etapas = ['selecao', 'cobranca', 'despacho', 'transito', 'alfandega', 'entrega']
    const etapaAtual = null
    const statusTexto = $('[class*="status"], .tracking-status').text().trim()

    return {
      identificador,
      status_texto: statusTexto || 'Status não disponível',
      etapa_atual: etapaAtual,
      etapas: etapas.map(e => ({ slug: e, label: e.charAt(0).toUpperCase() + e.slice(1) }))
    }
  } catch (err) {
    console.error(`[Scraping] Erro ao extrair status:`, err.message)
    return { identificador, status_texto: 'Não foi possível consultar o status', etapa_atual: null }
  }
}

module.exports = { extrairProdutosDoGrupo, extrairDadosProduto, extrairStatusPedido }
