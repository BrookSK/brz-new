/**
 * Serviço de Embeddings — gera vetores para busca semântica
 * Suporta OpenAI (padrão) com fallback para embedding local simples
 */

const PROVIDER = process.env.EMBEDDING_PROVIDER || 'openai'
const DIMENSAO = parseInt(process.env.VETOR_DIMENSAO || '1536')

/**
 * Gerar embedding para um texto
 * @returns {number[]|null} vetor de floats ou null se falhar
 */
async function gerarEmbedding (texto) {
  if (!texto || texto.trim().length < 10) return null

  if (PROVIDER === 'openai') {
    return await gerarEmbeddingOpenAI(texto)
  }

  // Fallback: embedding local baseado em TF-IDF simplificado
  return gerarEmbeddingLocal(texto)
}

/**
 * OpenAI text-embedding-3-small (1536 dimensões)
 */
async function gerarEmbeddingOpenAI (texto) {
  const apiKey = process.env.OPENAI_API_KEY
  if (!apiKey) {
    console.warn('[Embeddings] OPENAI_API_KEY não configurada, usando fallback local')
    return gerarEmbeddingLocal(texto)
  }

  const OpenAI = require('openai')
  const client = new OpenAI({ apiKey })

  const resp = await client.embeddings.create({
    model: 'text-embedding-3-small',
    input: texto.substring(0, 8000) // Limite de tokens
  })

  return resp.data[0].embedding
}

/**
 * Embedding local simplificado (bag-of-words com hash)
 * Não é tão bom quanto OpenAI, mas funciona sem API externa
 * Dimensão fixa de 256 para manter leve
 */
function gerarEmbeddingLocal (texto) {
  const DIM = 256
  const vetor = new Array(DIM).fill(0)

  // Tokenizar e normalizar
  const tokens = texto
    .toLowerCase()
    .replace(/[^a-záàâãéèêíïóôõöúçñ0-9\s]/gi, ' ')
    .split(/\s+/)
    .filter(t => t.length > 2)

  if (tokens.length === 0) return null

  // Hash cada token para uma posição no vetor
  for (const token of tokens) {
    let hash = 0
    for (let i = 0; i < token.length; i++) {
      hash = ((hash << 5) - hash + token.charCodeAt(i)) | 0
    }
    const pos = Math.abs(hash) % DIM
    vetor[pos] += 1
  }

  // Normalizar (L2 norm)
  const magnitude = Math.sqrt(vetor.reduce((sum, v) => sum + v * v, 0))
  if (magnitude === 0) return null

  return vetor.map(v => v / magnitude)
}

/**
 * Calcular similaridade de cosseno entre dois vetores
 */
function similaridadeCosseno (a, b) {
  if (!a || !b) return 0
  const minLen = Math.min(a.length, b.length)
  let dotProduct = 0
  let normA = 0
  let normB = 0

  for (let i = 0; i < minLen; i++) {
    dotProduct += a[i] * b[i]
    normA += a[i] * a[i]
    normB += b[i] * b[i]
  }

  const denominator = Math.sqrt(normA) * Math.sqrt(normB)
  return denominator === 0 ? 0 : dotProduct / denominator
}

module.exports = { gerarEmbedding, similaridadeCosseno, gerarEmbeddingLocal }
