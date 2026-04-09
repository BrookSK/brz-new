/**
 * Serviço de integração com Claude (Anthropic)
 * Modelo obrigatório: claude-sonnet-4-5 — NUNCA substituir
 */
const Anthropic = require('@anthropic-ai/sdk')

const MODELO = 'claude-sonnet-4-5-20241022' // Hardcoded — regra absoluta
const TIMEOUT = parseInt(process.env.TIMEOUT_CLAUDE_MS || '15000')

let client = null

function getClient () {
  if (!client) {
    client = new Anthropic({ apiKey: process.env.ANTHROPIC_API_KEY })
  }
  return client
}

/**
 * Classificar intenção do usuário (chamada rápida)
 */
async function classificarIntencao (mensagem, contexto) {
  const c = getClient()
  const resp = await c.messages.create({
    model: MODELO,
    max_tokens: 120,
    system: `Classifique a intenção do usuário em uma das categorias:
adicionar_carrinho, trocar_moeda_brl, trocar_moeda_usd, consultar_status_pedido,
abrir_whatsapp_vendas, ir_para_checkout, ir_para_contato, ir_para_clube,
ir_para_meus_dados, buscar_produto, ir_para_grupo, criar_ticket_suporte,
criar_ticket_duvida, verificar_cancelamento, solicitar_cancelamento, nenhuma.
Contexto da página: ${contexto.pagina || 'desconhecida'}
Responda APENAS com JSON: {"intencao":"nome_da_intencao","confianca":0.0-1.0}`,
    messages: [{ role: 'user', content: mensagem }]
  })
  try {
    const texto = resp.content[0].text.trim()
    const match = texto.match(/\{[\s\S]*\}/)
    return match ? JSON.parse(match[0]) : { intencao: 'nenhuma', confianca: 0 }
  } catch {
    return { intencao: 'nenhuma', confianca: 0 }
  }
}

/**
 * Gerar resposta completa da Bri (chamada principal)
 */
async function gerarResposta (systemPrompt, mensagens, contexto) {
  const c = getClient()
  const resp = await c.messages.create({
    model: MODELO,
    max_tokens: 1200,
    system: systemPrompt,
    messages: mensagens.map(m => ({
      role: m.role === 'assistant' ? 'assistant' : 'user',
      content: m.content
    }))
  })

  const textoResposta = resp.content[0].text.trim()
  const tokensUsados = (resp.usage?.input_tokens || 0) + (resp.usage?.output_tokens || 0)

  // Tentar parsear JSON da resposta
  let parsed = null
  try {
    const match = textoResposta.match(/\{[\s\S]*\}/)
    if (match) {
      parsed = JSON.parse(match[0])
    }
  } catch {
    // Se não for JSON, retornar como texto puro
  }

  return {
    texto: parsed?.texto || textoResposta,
    acao: parsed?.acao || 'nenhuma',
    parametros: parsed?.parametros || {},
    skus_sugeridos: parsed?.skus_sugeridos || [],
    requer_confirmacao: parsed?.requer_confirmacao || false,
    mensagem_confirmacao: parsed?.mensagem_confirmacao || null,
    max_tentativas_problema: parsed?.max_tentativas_problema ?? null,
    oferecer_ticket: parsed?.oferecer_ticket || false,
    sugestao_valor: parsed?.sugestao_valor || null,
    aprendizado: parsed?.aprendizado || null,
    tokens_usados: tokensUsados
  }
}

module.exports = { classificarIntencao, gerarResposta, MODELO }
