/**
 * Base de Conhecimento Dinâmica — Recurso 9-B
 * Cache com TTL de 30 minutos, recarrega das páginas reais do site
 */
const axios = require('axios')
const cheerio = require('cheerio')

const BRAZILIANA_URL = process.env.BRAZILIANA_BASE_URL || 'https://brazilianashop.com.br'
const TTL_MS = parseInt(process.env.CACHE_DOCUMENTOS_TTL_MS || '1800000') // 30 min

const FONTES = [
  { chave: 'como_funciona', url: '/como-funciona', titulo: 'Como Funciona', seletores: ['main', '.content', 'article', '#conteudo', '.container'] },
  { chave: 'termos_uso', url: '/faq', titulo: 'Termos e Condições', seletores: ['main', '.content', 'article', '#conteudo', '.container'] },
  { chave: 'politica_privacidade', url: '/politica-privacidade', titulo: 'Política de Privacidade', seletores: ['main', '.content', 'article', '#conteudo', '.container'] },
  { chave: 'como_funciona_clube', url: '/como-funciona-clube', titulo: 'Clube Braziliana', seletores: ['main', '.content', 'article', '#conteudo', '.container'] }
]

const cache = {
  documentos: {},
  ttl_ms: TTL_MS
}

function extrairTextoDoHTML (html, seletores) {
  const $ = cheerio.load(html)
  // Remover scripts, styles, nav, footer
  $('script, style, nav, footer, header, .navbar, .sidebar').remove()

  for (const sel of seletores) {
    const el = $(sel)
    if (el.length && el.text().trim().length > 100) {
      return el.text().trim().replace(/\s+/g, ' ').substring(0, 8000)
    }
  }
  // Fallback: body inteiro
  return $('body').text().trim().replace(/\s+/g, ' ').substring(0, 8000)
}

function gerarHash (texto) {
  let hash = 0
  for (let i = 0; i < texto.length; i++) {
    const char = texto.charCodeAt(i)
    hash = ((hash << 5) - hash) + char
    hash |= 0
  }
  return hash.toString(36)
}

async function obterDocumento (chave) {
  const entrada = cache.documentos[chave]
  const expirado = !entrada || (Date.now() - entrada.atualizado_em > cache.ttl_ms)

  if (!expirado) return entrada.texto

  const fonte = FONTES.find(f => f.chave === chave)
  if (!fonte) return null

  try {
    const resp = await axios.get(`${BRAZILIANA_URL}${fonte.url}`, {
      timeout: parseInt(process.env.TIMEOUT_SITE_MS || '8000'),
      headers: { 'User-Agent': 'CopilotoBraziliana/1.0' }
    })
    const texto = extrairTextoDoHTML(resp.data, fonte.seletores)
    const hash = gerarHash(texto)

    cache.documentos[chave] = { texto, hash, atualizado_em: Date.now() }
    return texto
  } catch (err) {
    console.error(`[BaseConhecimento] Falha ao atualizar ${chave}:`, err.message)
    return entrada?.texto || null
  }
}

async function obterTodosDocumentos () {
  const docs = {}
  for (const fonte of FONTES) {
    docs[fonte.chave] = await obterDocumento(fonte.chave)
  }
  return docs
}

function invalidarCache (chave = null) {
  if (chave) {
    delete cache.documentos[chave]
  } else {
    cache.documentos = {}
  }
}

module.exports = { obterDocumento, obterTodosDocumentos, invalidarCache }
