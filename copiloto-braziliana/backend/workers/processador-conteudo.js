/**
 * Worker: Processador de Conteúdo de Referência
 * Extrai texto de PDF/DOCX/TXT/MD → chunking → embeddings → salva no banco
 * Roda como processo separado: npm run worker:conteudo
 * Ou chamado pelo cron a cada 60s para processar pendentes
 */
require('dotenv').config({ path: __dirname + '/../.env' })

const fs = require('fs')
const path = require('path')
const db = require('../services/db')
const { gerarEmbedding } = require('../services/embeddings')

const CHUNK_TOKENS = parseInt(process.env.CONTEUDO_CHUNK_TOKENS || '800')
const CHUNK_OVERLAP = parseInt(process.env.CONTEUDO_CHUNK_OVERLAP || '100')
// Approx 1 token ≈ 4 chars
const CHUNK_CHARS = CHUNK_TOKENS * 4
const OVERLAP_CHARS = CHUNK_OVERLAP * 4

/**
 * Extrair texto de arquivo baseado no tipo
 */
async function extrairTexto (filePath, tipo) {
  const fullPath = path.resolve(__dirname, '../../public', filePath.replace(/^\//, ''))

  if (!fs.existsSync(fullPath)) {
    throw new Error(`Arquivo não encontrado: ${fullPath}`)
  }

  const buffer = fs.readFileSync(fullPath)

  if (tipo === 'pdf') {
    const pdfParse = require('pdf-parse')
    const data = await pdfParse(buffer)
    return { texto: data.text, paginas: data.numpages }
  }

  if (tipo === 'docx') {
    const mammoth = require('mammoth')
    const result = await mammoth.extractRawText({ buffer })
    return { texto: result.value, paginas: null }
  }

  if (tipo === 'txt' || tipo === 'md') {
    return { texto: buffer.toString('utf-8'), paginas: null }
  }

  throw new Error(`Tipo não suportado: ${tipo}`)
}

/**
 * Dividir texto em chunks com sobreposição
 */
function dividirEmChunks (texto) {
  const chunks = []
  // Limpar texto
  const limpo = texto.replace(/\r\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim()

  if (limpo.length <= CHUNK_CHARS) {
    chunks.push(limpo)
    return chunks
  }

  let inicio = 0
  while (inicio < limpo.length) {
    let fim = inicio + CHUNK_CHARS

    // Tentar cortar em quebra de parágrafo ou frase
    if (fim < limpo.length) {
      const trecho = limpo.substring(inicio, fim + 200)
      const corteParagrafo = trecho.lastIndexOf('\n\n')
      const corteFrase = trecho.lastIndexOf('. ')
      const corteEspaco = trecho.lastIndexOf(' ')

      if (corteParagrafo > CHUNK_CHARS * 0.6) {
        fim = inicio + corteParagrafo + 2
      } else if (corteFrase > CHUNK_CHARS * 0.6) {
        fim = inicio + corteFrase + 2
      } else if (corteEspaco > CHUNK_CHARS * 0.6) {
        fim = inicio + corteEspaco + 1
      }
    }

    const chunk = limpo.substring(inicio, Math.min(fim, limpo.length)).trim()
    if (chunk.length > 20) {
      chunks.push(chunk)
    }

    // Próximo chunk começa com sobreposição
    inicio = fim - OVERLAP_CHARS
    if (inicio <= chunks.length > 0 ? inicio : 0) {
      inicio = fim // Evitar loop infinito
    }
  }

  return chunks
}

/**
 * Processar um arquivo pendente
 */
async function processarArquivo (arquivo) {
  const { id, arquivo_path, arquivo_tipo, titulo } = arquivo
  console.log(`[Worker] Processando: ${titulo} (ID: ${id})`)

  try {
    // 1. Extrair texto
    const { texto, paginas } = await extrairTexto(arquivo_path, arquivo_tipo)

    if (!texto || texto.trim().length < 50) {
      await db.query(
        "UPDATE copiloto_conteudo SET status = 'erro', atualizado_em = NOW() WHERE id = ?",
        [id]
      )
      console.error(`[Worker] Texto muito curto ou vazio para ID ${id}`)
      return
    }

    // 2. Dividir em chunks
    const chunks = dividirEmChunks(texto)
    console.log(`[Worker] ${chunks.length} chunks gerados para "${titulo}"`)

    // 3. Limpar chunks antigos
    await db.query('DELETE FROM copiloto_conteudo_chunks WHERE conteudo_id = ?', [id])

    // 4. Gerar embeddings e salvar cada chunk
    for (let i = 0; i < chunks.length; i++) {
      const chunkTexto = chunks[i]
      let embeddingBuffer = null

      try {
        const embedding = await gerarEmbedding(chunkTexto)
        if (embedding) {
          // Serializar float array como Buffer
          embeddingBuffer = Buffer.from(new Float32Array(embedding).buffer)
        }
      } catch (err) {
        console.warn(`[Worker] Embedding falhou para chunk ${i}: ${err.message}`)
        // Continua sem embedding — busca por texto ainda funciona
      }

      await db.query(
        'INSERT INTO copiloto_conteudo_chunks (conteudo_id, chunk_index, texto, embedding) VALUES (?, ?, ?, ?)',
        [id, i, chunkTexto, embeddingBuffer]
      )
    }

    // 5. Atualizar status do arquivo
    await db.query(
      "UPDATE copiloto_conteudo SET status = 'ativo', total_chunks = ?, total_paginas = ?, atualizado_em = NOW() WHERE id = ?",
      [chunks.length, paginas, id]
    )

    console.log(`[Worker] ✅ "${titulo}" processado: ${chunks.length} chunks, ${paginas || '?'} páginas`)
  } catch (err) {
    console.error(`[Worker] ❌ Erro processando ID ${id}:`, err.message)
    await db.query(
      "UPDATE copiloto_conteudo SET status = 'erro', atualizado_em = NOW() WHERE id = ?",
      [id]
    ).catch(() => {})
  }
}

/**
 * Processar todos os arquivos pendentes
 */
async function processarPendentes () {
  const pendentes = await db.query(
    "SELECT * FROM copiloto_conteudo WHERE status = 'processando' ORDER BY criado_em ASC LIMIT 5"
  )

  if (pendentes.length === 0) {
    return 0
  }

  console.log(`[Worker] ${pendentes.length} arquivo(s) pendente(s)`)

  for (const arquivo of pendentes) {
    await processarArquivo(arquivo)
  }

  return pendentes.length
}

// Se executado diretamente (npm run worker:conteudo)
if (require.main === module) {
  processarPendentes()
    .then(n => {
      console.log(`[Worker] Finalizado. ${n} arquivo(s) processado(s).`)
      process.exit(0)
    })
    .catch(err => {
      console.error('[Worker] Erro fatal:', err)
      process.exit(1)
    })
}

module.exports = { processarPendentes, processarArquivo, extrairTexto, dividirEmChunks }
