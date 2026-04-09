require('dotenv').config()
const express = require('express')
const cors = require('cors')
const helmet = require('helmet')
const rateLimit = require('express-rate-limit')

const mensagemRouter = require('./routes/mensagem')
const gatilhoRouter = require('./routes/gatilho')
const ticketRouter = require('./routes/ticket')
const statusPedidoRouter = require('./routes/status-pedido')
const cancelamentoRouter = require('./routes/cancelamento')

const app = express()
const PORT = process.env.PORT || 3000

// Segurança
app.use(helmet())
app.use(cors({
  origin: process.env.CORS_ORIGIN || 'https://brazilianashop.com.br',
  credentials: true
}))
app.use(express.json({ limit: '1mb' }))

// Rate limiting global
const limiter = rateLimit({
  windowMs: 60 * 1000,
  max: parseInt(process.env.MAX_MSGS_POR_MINUTO || '20'),
  message: { erro: 'Muitas requisições. Tente novamente em 1 minuto.' },
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => req.body?.sessao_cookie || req.ip
})
app.use('/api/', limiter)

// Rate limiting específico para ações (mais restritivo)
const actionLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 10,
  message: { erro: 'Limite de ações atingido.' },
  keyGenerator: (req) => req.body?.sessao_cookie || req.ip
})

// Rotas
app.use('/api/copiloto', mensagemRouter)
app.use('/api/copiloto/gatilho', gatilhoRouter)
app.use('/api/copiloto/ticket', actionLimiter, ticketRouter)
app.use('/api/copiloto/status-pedido', statusPedidoRouter)
app.use('/api/copiloto/cancelamento', actionLimiter, cancelamentoRouter)

// Health check
app.get('/health', (req, res) => {
  res.json({ status: 'ok', modelo: 'claude-sonnet-4-5', timestamp: new Date().toISOString() })
})

// Servir widget estático
app.use('/copiloto.js', express.static(__dirname + '/../widget/copiloto.js'))

app.listen(PORT, () => {
  console.log(`[Co-Piloto Braziliana] Backend rodando na porta ${PORT}`)
  console.log(`[Co-Piloto Braziliana] Modelo: claude-sonnet-4-5 (hardcoded)`)

  // Iniciar cron jobs embutidos se CRON_EMBEDDED=1
  if (process.env.CRON_EMBEDDED === '1') {
    try {
      require('./workers/cron')
      console.log('[Co-Piloto Braziliana] Cron jobs embutidos ativados')
    } catch (err) {
      console.warn('[Co-Piloto Braziliana] Cron jobs não iniciados:', err.message)
    }
  }
})
