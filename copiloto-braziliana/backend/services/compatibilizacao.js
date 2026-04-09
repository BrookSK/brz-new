/**
 * Serviço de Compatibilização — Recurso 7
 * Normaliza dados do sistema para o formato esperado pelo copiloto
 */
const { MAPA_GRUPO_LOJA, MAPA_IMPOSTO_LOCAL } = require('./calculo')

function compatibilizarProduto (dadosBrutos) {
  return {
    produto_id: dadosBrutos.id || dadosBrutos.produto_id || null,
    produto_nome: dadosBrutos.nome || dadosBrutos.title || dadosBrutos.name || null,
    produto_preco_usd: parseFloat(
      (dadosBrutos.preco || dadosBrutos.price || '0').toString().replace(/[^0-9.]/g, '')
    ) || null,
    produto_peso_kg: parseFloat(
      (dadosBrutos.peso || dadosBrutos.weight || '0').toString().replace(/[^0-9.]/g, '')
    ) || null,
    produto_foto_url: dadosBrutos.foto || dadosBrutos.imagem || dadosBrutos.image || dadosBrutos.foto_url || null,
    produto_disponivel: dadosBrutos.disponivel ?? dadosBrutos.available ?? true,
    produto_grupo: dadosBrutos.grupo || dadosBrutos.group || null,
    produto_loja: dadosBrutos.loja || MAPA_GRUPO_LOJA[dadosBrutos.grupo] || null,
    imposto_local_pct: dadosBrutos.imposto_local ?? MAPA_IMPOSTO_LOCAL[dadosBrutos.grupo] ?? 0,
    produto_categoria: dadosBrutos.categoria || null
  }
}

function compatibilizarContexto (contextoRaw) {
  return {
    pagina: contextoRaw.pagina || 'desconhecida',
    url_atual: contextoRaw.url_atual || '',
    produto_id: contextoRaw.produto_id || null,
    produto_nome: contextoRaw.produto_nome || null,
    produto_preco_usd: contextoRaw.produto_preco_usd ? parseFloat(contextoRaw.produto_preco_usd) : null,
    produto_peso_kg: contextoRaw.produto_peso_kg ? parseFloat(contextoRaw.produto_peso_kg) : null,
    produto_grupo: contextoRaw.produto_grupo || null,
    imposto_local_pct: contextoRaw.imposto_local_pct ? parseFloat(contextoRaw.imposto_local_pct) : 0,
    carrinho_itens: Array.isArray(contextoRaw.carrinho_itens) ? contextoRaw.carrinho_itens : [],
    carrinho_subtotal: contextoRaw.carrinho_subtotal ? parseFloat(contextoRaw.carrinho_subtotal) : 0,
    usuario_logado: !!contextoRaw.usuario_logado,
    usuario_nome: contextoRaw.usuario_nome || null,
    moeda_atual: contextoRaw.moeda_atual || 'BRL'
  }
}

/**
 * Tratar dado ausente — nunca inventar, retornar null
 */
function tratarDadoAusente (campo, valor, contexto) {
  if (valor !== null && valor !== undefined) return valor

  const estrategias = {
    produto_peso_kg: () => null, // Sem peso = não calcular, não inventar
    produto_preco_usd: () => null,
    imposto_local_pct: () => MAPA_IMPOSTO_LOCAL[contexto?.produto_grupo] || 0,
    usuario_nome: () => 'Cliente',
    produto_categoria: () => null
  }

  const estrategia = estrategias[campo]
  return estrategia ? estrategia() : null
}

module.exports = { compatibilizarProduto, compatibilizarContexto, tratarDadoAusente }
