/**
 * Cálculo de custo total — função única e definitiva
 * Conforme Recurso 4 do documento de arquitetura
 */

const FAIXAS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 25, 30]
const TAXA_POR_KG = 39 // US$ 39 por kg

// Mapa de imposto local por grupo
const MAPA_IMPOSTO_LOCAL = {
  'bath-and-body-works': 8,
  'walmart': 8,
  'trader-joes': 8,
  'bjs': 8,
  'achados-e-favoritos-da-fabi': 8,
  'costco': 0,
  'sams-club': 0,
  'desapegos-braziliana': 0
}

// Mapa de grupo para loja
const MAPA_GRUPO_LOJA = {
  'bath-and-body-works': 'Bath & Body Works',
  'costco': 'Costco',
  'walmart': 'Walmart',
  'sams-club': "Sam's Club",
  'trader-joes': "Trader Joe's",
  'bjs': "BJ's",
  'desapegos-braziliana': 'Desapegos Braziliana',
  'achados-e-favoritos-da-fabi': 'Achados e Favoritos da Fabi'
}

function calcularCustoTotal (precoUsd, pesoKg, impostoLocalPct = 0) {
  const faixaKg = FAIXAS.find(f => f >= pesoKg) || 30
  const taxaServicoUsd = faixaKg * TAXA_POR_KG

  // Imposto local EUA
  const impostoLocalUsd = precoUsd * (impostoLocalPct / 100)

  // Impostos brasileiros (ICMS 60% + IPI 20%)
  const icmsUsd = precoUsd * 0.60
  const ipiUsd = precoUsd * 0.20
  const impostosBrUsd = icmsUsd + ipiUsd

  const totalUsd = precoUsd + impostoLocalUsd + taxaServicoUsd + impostosBrUsd
  const cambio = parseFloat(process.env.CAMBIO_USD_BRL || '5.80')
  const totalBrl = totalUsd * cambio

  // Espaço restante na faixa
  const espacoRestanteKg = parseFloat((faixaKg - pesoKg).toFixed(2))

  return {
    produto_usd: parseFloat(precoUsd.toFixed(2)),
    imposto_local_usd: parseFloat(impostoLocalUsd.toFixed(2)),
    taxa_servico_usd: parseFloat(taxaServicoUsd.toFixed(2)),
    icms_usd: parseFloat(icmsUsd.toFixed(2)),
    ipi_usd: parseFloat(ipiUsd.toFixed(2)),
    impostos_br_usd: parseFloat(impostosBrUsd.toFixed(2)),
    total_usd: parseFloat(totalUsd.toFixed(2)),
    total_brl: parseFloat(totalBrl.toFixed(2)),
    faixa_kg: faixaKg,
    espaco_restante_kg: espacoRestanteKg,
    cambio_usado: cambio
  }
}

function obterImpostoLocal (grupoSlug) {
  return MAPA_IMPOSTO_LOCAL[grupoSlug] || 0
}

function obterNomeLoja (grupoSlug) {
  return MAPA_GRUPO_LOJA[grupoSlug] || null
}

module.exports = {
  calcularCustoTotal,
  obterImpostoLocal,
  obterNomeLoja,
  FAIXAS,
  TAXA_POR_KG,
  MAPA_IMPOSTO_LOCAL,
  MAPA_GRUPO_LOJA
}
