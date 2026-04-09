/**
 * Cron Jobs do Co-Piloto Braziliana
 * Gerencia tarefas periódicas: processamento de conteúdo, indexação, limpeza
 * Executar: npm run cron (roda continuamente)
 */
require('dotenv').config({ path: __dirname + '/../.env' })

const cron = require('node-cron')
const db = require('../services/db')
const { processarPendentes } = require('./processador-conteudo')
const { indexarTodos } = require('./indexador-produtos')
const baseConhecimento = require('../services/base-conhecimento')

console.log('[Cron] Co-Piloto Braziliana — Cron Jobs iniciados')

// ============================================================
// JOB 1: Processar conteúdo pendente (a cada 60 segundos)
// Verifica se há arquivos com status='processando' e processa
// ============================================================
cron.schedule('* * * * *', async () => {
  try {
    const n = await processarPendentes()
    if (n > 0) {
      console.log(`[Cron] Conteúdo: ${n} arquivo(s) processado(s)`)
    }
  } catch (err) {
    console.error('[Cron] Erro no processamento de conteúdo:', err.message)
  }
})

// ============================================================
// JOB 2: Indexar produtos cross-grupo (a cada 6 horas)
// Faz scraping de todos os grupos e atualiza o índice
// ============================================================
cron.schedule('0 */6 * * *', async () => {
  console.log('[Cron] Iniciando indexação de produtos...')
  try {
    const n = await indexarTodos()
    console.log(`[Cron] Produtos: ${n} indexados`)
  } catch (err) {
    console.error('[Cron] Erro na indexação de produtos:', err.message)
  }
})

// ============================================================
// JOB 3: Atualizar base de conhecimento (a cada 30 minutos)
// Invalida cache e força recarga das páginas do site
// ============================================================
cron.schedule('*/30 * * * *', async () => {
  try {
    baseConhecimento.invalidarCache()
    await baseConhecimento.obterTodosDocumentos()
    console.log('[Cron] Base de conhecimento atualizada')
  } catch (err) {
    console.error('[Cron] Erro atualizando base de conhecimento:', err.message)
  }
})

// ============================================================
// JOB 4: Limpeza de dados antigos (diário às 3h)
// Remove sessões e mensagens com mais de 90 dias
// ============================================================
cron.schedule('0 3 * * *', async () => {
  console.log('[Cron] Iniciando limpeza de dados antigos...')
  try {
    // Mensagens com mais de 90 dias
    const resMsgs = await db.query(
      'DELETE FROM copiloto_mensagens WHERE criado_em < DATE_SUB(NOW(), INTERVAL 90 DAY)'
    )
    console.log(`[Cron] Limpeza: ${resMsgs.affectedRows || 0} mensagens antigas removidas`)

    // Sessões com mais de 90 dias
    const resSess = await db.query(
      'DELETE FROM copiloto_sessoes WHERE ultima_interacao < DATE_SUB(NOW(), INTERVAL 90 DAY)'
    )
    console.log(`[Cron] Limpeza: ${resSess.affectedRows || 0} sessões antigas removidas`)

    // Produtos indisponíveis há mais de 7 dias
    const resProd = await db.query(
      "DELETE FROM copiloto_produtos_index WHERE disponivel = 0 AND atualizado_em < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    ).catch(() => ({ affectedRows: 0 }))
    console.log(`[Cron] Limpeza: ${resProd.affectedRows || 0} produtos obsoletos removidos`)
  } catch (err) {
    console.error('[Cron] Erro na limpeza:', err.message)
  }
})

// ============================================================
// JOB 5: Agrupar pendências similares de aprendizado (diário às 4h)
// Incrementa frequência de pendências parecidas
// ============================================================
cron.schedule('0 4 * * *', async () => {
  try {
    const pendentes = await db.query(
      "SELECT id, resumo_problema FROM copiloto_aprendizado WHERE status = 'pendente'"
    )

    if (pendentes.length < 2) return

    const agrupados = new Set()

    for (let i = 0; i < pendentes.length; i++) {
      if (agrupados.has(pendentes[i].id)) continue

      for (let j = i + 1; j < pendentes.length; j++) {
        if (agrupados.has(pendentes[j].id)) continue

        const sim = calcularSimilaridadeTexto(
          pendentes[i].resumo_problema,
          pendentes[j].resumo_problema
        )

        if (sim > 0.75) {
          // Incrementar frequência do mais antigo, remover o mais novo
          await db.query(
            'UPDATE copiloto_aprendizado SET frequencia = frequencia + 1 WHERE id = ?',
            [pendentes[i].id]
          )
          await db.query(
            "UPDATE copiloto_aprendizado SET status = 'editada_e_aceita' WHERE id = ?",
            [pendentes[j].id]
          )
          agrupados.add(pendentes[j].id)
        }
      }
    }

    if (agrupados.size > 0) {
      console.log(`[Cron] Aprendizado: ${agrupados.size} pendências agrupadas`)
    }
  } catch (err) {
    console.error('[Cron] Erro agrupando pendências:', err.message)
  }
})

/**
 * Similaridade de texto simples (Jaccard sobre palavras)
 */
function calcularSimilaridadeTexto (a, b) {
  if (!a || !b) return 0
  const tokenize = t => new Set(
    t.toLowerCase()
      .replace(/[^a-záàâãéèêíïóôõöúçñ0-9\s]/gi, '')
      .split(/\s+/)
      .filter(w => w.length > 3)
  )
  const setA = tokenize(a)
  const setB = tokenize(b)
  if (setA.size === 0 || setB.size === 0) return 0

  let intersecao = 0
  for (const w of setA) {
    if (setB.has(w)) intersecao++
  }
  const uniao = setA.size + setB.size - intersecao
  return uniao === 0 ? 0 : intersecao / uniao
}

// Manter processo vivo
process.on('SIGINT', () => {
  console.log('[Cron] Encerrando...')
  process.exit(0)
})
