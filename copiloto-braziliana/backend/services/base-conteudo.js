/**
 * Base de Conteúdo de Referência — Busca Semântica
 * Recurso 10-C do documento de arquitetura
 * Busca os chunks mais relevantes para a mensagem do usuário
 */
const db = require('./db')
const { gerarEmbedding, similaridadeCosseno } = require('./embeddings')

const TOP_K = parseInt(process.env.CONTEUDO_TOP_K || '3')

/**
 * Buscar conteúdo de referência relevante para a mensagem
 * Usa busca semântica por similaridade de cosseno
 * @param {string} mensagemUsuario
 * @param {object} contextoPagina
 * @param {number} topK
 * @returns {Array<{titulo_arquivo, categoria, trecho}>}
 */
async function buscarConteudoRelevante (mensagemUsuario, contextoPagina, topK = TOP_K) {
  try {
    // Verificar se há conteúdo ativo
    const ativos = await db.query(
      "SELECT COUNT(*) as total FROM copiloto_conteudo WHERE status = 'ativo' AND ativo = 1"
    )
    if (!ativos[0] || ativos[0].total === 0) return []

    // Montar query de busca combinando mensagem + contexto
    const queryTexto = [
      mensagemUsuario,
      contextoPagina?.produto_nome || '',
      contextoPagina?.pagina || ''
    ].filter(Boolean).join(' ')

    // Gerar embedding da query
    const queryEmbedding = await gerarEmbedding(queryTexto)

    if (!queryEmbedding) {
      // Fallback: busca por texto (LIKE) se embedding falhar
      return await buscarPorTexto(mensagemUsuario, topK)
    }

    // Buscar todos os chunks ativos com embeddings
    const chunks = await db.query(`
      SELECT cc.id, cc.texto, cc.embedding, c.titulo, c.categoria, c.notas_ia
      FROM copiloto_conteudo_chunks cc
      JOIN copiloto_conteudo c ON c.id = cc.conteudo_id
      WHERE c.status = 'ativo' AND c.ativo = 1 AND cc.embedding IS NOT NULL
    `)

    if (chunks.length === 0) {
      return await buscarPorTexto(mensagemUsuario, topK)
    }

    // Calcular similaridade para cada chunk
    const scored = chunks.map(chunk => {
      let chunkEmbedding = null
      try {
        if (chunk.embedding && chunk.embedding.length > 0) {
          // Deserializar Buffer → Float32Array
          const buf = Buffer.isBuffer(chunk.embedding) ? chunk.embedding : Buffer.from(chunk.embedding)
          chunkEmbedding = Array.from(new Float32Array(buf.buffer, buf.byteOffset, buf.length / 4))
        }
      } catch {
        return null
      }

      if (!chunkEmbedding) return null

      const score = similaridadeCosseno(queryEmbedding, chunkEmbedding)
      return {
        titulo_arquivo: chunk.titulo,
        categoria: chunk.categoria,
        trecho: chunk.texto,
        notas_ia: chunk.notas_ia,
        score
      }
    }).filter(Boolean)

    // Ordenar por similaridade e retornar top K
    scored.sort((a, b) => b.score - a.score)

    return scored.slice(0, topK).filter(s => s.score > 0.1) // Threshold mínimo
  } catch (err) {
    console.error('[BaseConteudo] Erro na busca semântica:', err.message)
    return []
  }
}

/**
 * Fallback: busca por texto quando embeddings não estão disponíveis
 */
async function buscarPorTexto (mensagem, topK) {
  try {
    // Extrair palavras-chave da mensagem (>3 chars, sem stopwords)
    const stopwords = new Set(['como', 'para', 'que', 'com', 'uma', 'por', 'mais', 'esse', 'essa', 'isso', 'aqui', 'tem', 'ser', 'ter', 'não', 'sim', 'meu', 'minha'])
    const palavras = mensagem
      .toLowerCase()
      .replace(/[^a-záàâãéèêíïóôõöúçñ0-9\s]/gi, ' ')
      .split(/\s+/)
      .filter(p => p.length > 3 && !stopwords.has(p))
      .slice(0, 5)

    if (palavras.length === 0) return []

    // Buscar chunks que contenham alguma das palavras-chave
    const likeClauses = palavras.map(() => 'cc.texto LIKE ?').join(' OR ')
    const likeParams = palavras.map(p => `%${p}%`)

    const chunks = await db.query(`
      SELECT cc.texto, c.titulo, c.categoria, c.notas_ia
      FROM copiloto_conteudo_chunks cc
      JOIN copiloto_conteudo c ON c.id = cc.conteudo_id
      WHERE c.status = 'ativo' AND c.ativo = 1 AND (${likeClauses})
      LIMIT ?
    `, [...likeParams, topK])

    return chunks.map(c => ({
      titulo_arquivo: c.titulo,
      categoria: c.categoria,
      trecho: c.texto,
      notas_ia: c.notas_ia
    }))
  } catch (err) {
    console.error('[BaseConteudo] Erro na busca por texto:', err.message)
    return []
  }
}

module.exports = { buscarConteudoRelevante, buscarPorTexto }
