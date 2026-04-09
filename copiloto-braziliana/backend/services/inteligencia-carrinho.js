/**
 * Inteligência de Valor do Carrinho — Recurso 4-B
 * Compara alternativas por custo/unidade e sugere quando genuinamente melhor
 */
const { calcularCustoTotal } = require('./calculo')

function calcularValorPorUnidade (precoUsd, quantidadeUnidades, pesoKg) {
  return {
    preco_por_unidade: parseFloat((precoUsd / quantidadeUnidades).toFixed(2)),
    peso_por_unidade: parseFloat((pesoKg / quantidadeUnidades).toFixed(3)),
    preco_total_usd: precoUsd,
    quantidade: quantidadeUnidades,
    peso_total_kg: pesoKg
  }
}

function extrairQuantidadeDoNome (nome) {
  if (!nome) return 1
  const padroes = [
    { re: /buy\s+(\d+)\s+get\s+(\d+)\s+free/i, fn: m => parseInt(m[1]) + parseInt(m[2]) },
    { re: /(\d+)\s*pack/i, fn: m => parseInt(m[1]) },
    { re: /set\s+of\s+(\d+)/i, fn: m => parseInt(m[1]) },
    { re: /pack\s+of\s+(\d+)/i, fn: m => parseInt(m[1]) },
    { re: /(\d+)\s+unidades?/i, fn: m => parseInt(m[1]) },
    { re: /(\d+)x\s/i, fn: m => parseInt(m[1]) },
    { re: /\bx(\d+)\b/i, fn: m => parseInt(m[1]) }
  ]
  for (const { re, fn } of padroes) {
    const m = nome.match(re)
    if (m) return fn(m)
  }
  return 1
}

function compararAlternativas (intencao, alternativas) {
  const custoIntencao = calcularCustoTotal(
    intencao.preco_usd * intencao.quantidade_desejada,
    intencao.peso_kg_unitario * intencao.quantidade_desejada,
    intencao.imposto_local_pct
  )
  const cpuIntencao = custoIntencao.total_usd / intencao.quantidade_desejada

  const sugestoes = []

  for (const alt of alternativas) {
    const custoAlt = calcularCustoTotal(alt.preco_usd, alt.peso_kg, alt.imposto_local_pct)
    const cpuAlt = custoAlt.total_usd / alt.unidades_no_kit
    const economiaPorUnidade = cpuIntencao - cpuAlt
    const economiaTotalUsd = custoIntencao.total_usd - custoAlt.total_usd
    const economiaPct = (economiaPorUnidade / cpuIntencao) * 100
    const cambio = parseFloat(process.env.CAMBIO_USD_BRL || '5.80')
    const economiaTotalBrl = economiaTotalUsd * cambio

    // SÓ sugere se genuinamente mais barato por unidade E economia > R$ 20
    if (economiaPorUnidade > 0 && economiaTotalBrl >= 20) {
      sugestoes.push({
        produto_alternativo: alt,
        custo_alternativo: custoAlt,
        unidades_extras: alt.unidades_no_kit - intencao.quantidade_desejada,
        economia_por_unidade_usd: parseFloat(economiaPorUnidade.toFixed(2)),
        economia_total_usd: parseFloat(economiaTotalUsd.toFixed(2)),
        economia_total_brl: parseFloat(economiaTotalBrl.toFixed(2)),
        economia_pct: parseFloat(economiaPct.toFixed(1)),
        peso_adicional_kg: parseFloat((alt.peso_kg - (intencao.peso_kg_unitario * intencao.quantidade_desejada)).toFixed(2)),
        mesmo_frete: custoAlt.faixa_kg === custoIntencao.faixa_kg
      })
    }
  }

  return sugestoes.sort((a, b) => b.economia_por_unidade_usd - a.economia_por_unidade_usd)
}

module.exports = { calcularValorPorUnidade, extrairQuantidadeDoNome, compararAlternativas }
