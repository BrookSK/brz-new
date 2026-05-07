/**
 * Cálculo de custo total — função única e definitiva
 * Fórmula correta da Receita Federal (Remessa Conforme)
 */

const FAIXAS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 25, 30]
const TAXA_POR_KG = 39 // US$ 39 por kg
const ALIQUOTA_ICMS = 0.17 // 17%
const CERTIFICADA_REMESSA_CONFORME = true // Braziliana é certificada

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

  // Imposto local EUA (sales tax)
  const impostoLocalUsd = precoUsd * (impostoLocalPct / 100)

  // Valor aduaneiro (produto + frete + seguro) — frete grátis, seguro 0
  const valorAduaneiro = precoUsd

  // Imposto de Importação (II) — Remessa Conforme
  let iiUsd = 0
  if (CERTIFICADA_REMESSA_CONFORME) {
    if (valorAduaneiro <= 50) {
      iiUsd = valorAduaneiro * 0.20
    } else {
      iiUsd = Math.max(0, (valorAduaneiro * 0.60) - 20)
    }
  } else {
    iiUsd = valorAduaneiro * 0.60
  }

  // ICMS (cálculo "por dentro")
  const baseIcms = (valorAduaneiro + iiUsd) / (1 - ALIQUOTA_ICMS)
  const icmsUsd = baseIcms * ALIQUOTA_ICMS

  // Total impostos brasileiros
  const totalImpostosUsd = iiUsd + icmsUsd

  const totalUsd = precoUsd + impostoLocalUsd + taxaServicoUsd + totalImpostosUsd
  const cambio = parseFloat(process.env.CAMBIO_USD_BRL || '5.80')
  const totalBrl = totalUsd * cambio

  // Espaço restante na faixa
  const espacoRestanteKg = parseFloat((faixaKg - pesoKg).toFixed(2))

  return {
    produto_usd: parseFloat(precoUsd.toFixed(2)),
    imposto_local_usd: parseFloat(impostoLocalUsd.toFixed(2)),
    taxa_servico_usd: parseFloat(taxaServicoUsd.toFixed(2)),
    valor_aduaneiro_usd: parseFloat(valorAduaneiro.toFixed(2)),
    ii_usd: parseFloat(iiUsd.toFixed(2)),
    icms_calculado_usd: parseFloat(icmsUsd.toFixed(2)),
    total_impostos_usd: parseFloat(totalImpostosUsd.toFixed(2)),
    // Manter campos antigos para compatibilidade com prompt
    icms_usd: parseFloat(iiUsd.toFixed(2)),
    ipi_usd: parseFloat(icmsUsd.toFixed(2)),
    impostos_br_usd: parseFloat(totalImpostosUsd.toFixed(2)),
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
