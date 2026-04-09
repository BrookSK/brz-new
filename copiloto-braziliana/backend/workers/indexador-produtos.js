/**
 * Worker: Indexador Cross-Grupo de Produtos
 * Faz scraping de todos os grupos de compra e indexa produtos no banco
 * para permitir busca e comparação cross-grupo pela inteligência de carrinho
 * Roda como processo separado: npm run worker:produtos
 * Ou chamado pelo cron a cada 6 horas
 */
require('dotenv').config({ path: __dirname + '/../.env' })

const db = require('../services/db')
const { extrairProdutosDoGrupo } = require('../services/scraping')
const { extrairQuantidadeDoNome } = require('../services/inteligencia-carrinho')
const { MAPA_IMPOSTO_LOCAL } = require('../services/calculo')

// Todos os grupos confirmados do site
const GRUPOS = [
  'bath-and-body-works',
  'costco',
  'walmart',
  'sams-club',
  'trader-joes',
  'bjs',
  'desapegos-braziliana',
  'achados-e-favoritos-da-fabi'
]

/**
 * Garantir que a tabela de índice existe
 */
async function garantirTabela () {
  await db.query(`
    CREATE TABLE IF NOT EXISTS copiloto_produtos_index (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      produto_id VARCHAR(50) NULL,
      nome VARCHAR(500) NOT NULL,
      nome_normalizado VARCHAR(500) NOT NULL,
      preco_usd DECIMAL(10,2) NULL,
      peso_kg DECIMAL(8,3) NULL,
      grupo_slug VARCHAR(100) NOT NULL,
      imposto_local_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
      unidades_no_kit INT UNSIGNED NOT NULL DEFAULT 1,
      foto_url VARCHAR(500) NULL,
      disponivel TINYINT(1) NOT NULL DEFAULT 1,
      atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_grupo (grupo_slug),
      INDEX idx_nome_norm (nome_normalizado(100)),
      INDEX idx_preco (preco_usd)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `)
}

/**
 * Normalizar nome para busca
 */
function normalizarNome (nome) {
  return (nome || '')
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Remove acentos
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

/**
 * Indexar produtos de um grupo
 */
async function indexarGrupo (slug) {
  console.log(`[Indexador] Scraping grupo: ${slug}`)

  const produtos = await extrairProdutosDoGrupo(slug)
  if (produtos.length === 0) {
    console.warn(`[Indexador] Nenhum produto encontrado em ${slug}`)
    return 0
  }

  const impostoLocal = MAPA_IMPOSTO_LOCAL[slug] || 0
  let inseridos = 0

  for (const p of produtos) {
    const nomeNorm = normalizarNome(p.nome)
    const unidades = extrairQuantidadeDoNome(p.nome)

    try {
      // Upsert: se já existe com mesmo nome+grupo, atualiza
      await db.query(`
        INSERT INTO copiloto_produtos_index 
          (produto_id, nome, nome_normalizado, preco_usd, grupo_slug, imposto_local_pct, unidades_no_kit, foto_url, disponivel, atualizado_em)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
          preco_usd = VALUES(preco_usd),
          foto_url = VALUES(foto_url),
          disponivel = 1,
          unidades_no_kit = VALUES(unidades_no_kit),
          atualizado_em = NOW()
      `, [p.id, p.nome, nomeNorm, p.preco, slug, impostoLocal, unidades, p.foto_url])
      inseridos++
    } catch (err) {
      // Duplicate key sem ON DUPLICATE — inserir normalmente
      try {
        await db.query(`
          INSERT INTO copiloto_produtos_index 
            (produto_id, nome, nome_normalizado, preco_usd, grupo_slug, imposto_local_pct, unidades_no_kit, foto_url)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        `, [p.id, p.nome, nomeNorm, p.preco, slug, impostoLocal, unidades, p.foto_url])
        inseridos++
      } catch (err2) {
        console.warn(`[Indexador] Erro inserindo ${p.nome}: ${err2.message}`)
      }
    }
  }

  console.log(`[Indexador] ✅ ${slug}: ${inseridos}/${produtos.length} produtos indexados`)
  return inseridos
}

/**
 * Indexar todos os grupos
 */
async function indexarTodos () {
  await garantirTabela()

  let totalIndexados = 0
  for (const slug of GRUPOS) {
    try {
      const n = await indexarGrupo(slug)
      totalIndexados += n
      // Pausa entre grupos para não sobrecarregar o site
      await new Promise(r => setTimeout(r, 2000))
    } catch (err) {
      console.error(`[Indexador] Erro no grupo ${slug}:`, err.message)
    }
  }

  // Marcar produtos não atualizados há mais de 24h como indisponíveis
  await db.query(`
    UPDATE copiloto_produtos_index 
    SET disponivel = 0 
    WHERE atualizado_em < DATE_SUB(NOW(), INTERVAL 24 HOUR)
  `)

  console.log(`[Indexador] Total: ${totalIndexados} produtos indexados em ${GRUPOS.length} grupos`)
  return totalIndexados
}

/**
 * Buscar alternativas cross-grupo para um produto
 * Usado pela inteligência de carrinho
 */
async function buscarAlternativasCrossGrupo (nomeProduto, grupoAtual, quantidadeDesejada) {
  await garantirTabela()

  // Extrair palavras-chave do nome do produto
  const palavras = normalizarNome(nomeProduto)
    .split(' ')
    .filter(p => p.length > 3)
    .slice(0, 4)

  if (palavras.length === 0) return []

  // Buscar produtos similares em OUTROS grupos
  const likeClauses = palavras.map(() => 'nome_normalizado LIKE ?').join(' OR ')
  const likeParams = palavras.map(p => `%${p}%`)

  const alternativas = await db.query(`
    SELECT * FROM copiloto_produtos_index
    WHERE disponivel = 1
      AND grupo_slug != ?
      AND (${likeClauses})
      AND preco_usd IS NOT NULL
      AND unidades_no_kit >= ?
    ORDER BY 
      unidades_no_kit DESC,
      preco_usd ASC
    LIMIT 10
  `, [grupoAtual, ...likeParams, quantidadeDesejada])

  return alternativas.map(a => ({
    id: a.produto_id,
    nome: a.nome,
    preco_usd: parseFloat(a.preco_usd),
    peso_kg: parseFloat(a.peso_kg || 0),
    grupo_slug: a.grupo_slug,
    imposto_local_pct: a.imposto_local_pct,
    unidades_no_kit: a.unidades_no_kit,
    foto_url: a.foto_url
  }))
}

/**
 * Buscar produtos que otimizam a faixa de peso do carrinho
 */
async function buscarProdutosParaOtimizarFaixa (espacoRestanteKg, grupoAtual) {
  await garantirTabela()

  const produtos = await db.query(`
    SELECT * FROM copiloto_produtos_index
    WHERE disponivel = 1
      AND peso_kg IS NOT NULL
      AND peso_kg > 0
      AND peso_kg <= ?
      AND preco_usd IS NOT NULL
    ORDER BY peso_kg DESC, preco_usd ASC
    LIMIT 5
  `, [espacoRestanteKg])

  return produtos.map(p => ({
    id: p.produto_id,
    nome: p.nome,
    preco_usd: parseFloat(p.preco_usd),
    peso_kg: parseFloat(p.peso_kg),
    grupo_slug: p.grupo_slug,
    imposto_local_pct: p.imposto_local_pct,
    unidades_no_kit: p.unidades_no_kit
  }))
}

// Se executado diretamente
if (require.main === module) {
  indexarTodos()
    .then(n => {
      console.log(`[Indexador] Finalizado. ${n} produtos.`)
      process.exit(0)
    })
    .catch(err => {
      console.error('[Indexador] Erro fatal:', err)
      process.exit(1)
    })
}

module.exports = { indexarTodos, indexarGrupo, buscarAlternativasCrossGrupo, buscarProdutosParaOtimizarFaixa }
