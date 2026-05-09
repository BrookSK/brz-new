# Módulo Lives — Live Shopping (TikTok-style)

## Visão Geral

Módulo de transmissão ao vivo com compra integrada. A Admin transmite pelo celular/navegador, destaca produtos durante a live, e os clientes assistem e compram com 1 clique.

## Fluxo Principal

```
Admin cria live → Adiciona produtos → Inicia transmissão (WebRTC ou OBS)
    ↓
Cliente acessa /lives/{id} → Assiste via HLS → Vê chat, likes, produto em destaque
    ↓
Admin destaca produto → Pílula aparece no cliente em < 2s (via SSE)
    ↓
Cliente clica → Bottom sheet com produto → "Comprar agora" → Pedido criado (1 clique)
```

## Configuração Inicial

### 1. Cloudflare Stream

1. Criar conta no Cloudflare e habilitar Stream
2. Gerar API Token: Dashboard → API Tokens → Create Token → Stream:Edit
3. Copiar Account ID (visível na página principal do dashboard)
4. No admin: **Lives → Config Lives** → preencher Account ID, API Token, Subdomain
5. Clicar "Testar Conexão" para validar

### 2. Modo de Operação

- **Desligado** (padrão): ninguém acessa o módulo
- **Teste**: somente admins podem criar/assistir lives
- **Online**: disponível para todos os clientes

Configurar em: Admin → Lives → Config Lives → Modo de Operação

### 3. Gateway de Pagamento

O módulo usa o gateway já configurado no sistema (Câmbio Real, Asaas ou Stripe) para:
- Tokenização de cartão do cliente (SDK no frontend)
- Cobrança avulsa com token (compra 1-clique)

Confirmar que tokenização e cobrança avulsa estão ativas no gateway.

### 4. Produtos

Marcar produtos como "Disponível para Live" no cadastro/edição de produto (checkbox).

## Variáveis de Ambiente (opcionais)

Se preferir usar env vars ao invés do banco para credenciais CF:

```env
APP_KEY=sua-chave-secreta-para-criptografia
CF_ACCOUNT_ID=seu-account-id
CF_API_TOKEN=seu-api-token
CF_STREAM_SUBDOMAIN=customer-xxx.cloudflarestream.com
```

> Por padrão, as credenciais são armazenadas criptografadas (libsodium) na tabela `configuracoes_sistema`.

## Cron Job

Arquivamento de gravações (a cada 30 min):

```bash
*/30 * * * * php /caminho/do/projeto/cron/live-recording-archive.php >> /var/log/live-archive.log 2>&1
```

## Estrutura de Arquivos

```
app/
├── Controllers/
│   ├── AdminLivesController.php      # CRUD, estúdio, moderação
│   ├── AdminLivesConfigController.php # Configurações CF + cota
│   ├── LivesController.php           # Páginas públicas (player)
│   ├── LiveApiController.php         # API REST (chat, like, buy)
│   └── LiveSseController.php         # Server-Sent Events (realtime)
├── Models/
│   ├── Live.php
│   ├── LiveProduct.php
│   ├── LiveChatMessage.php
│   ├── LiveOrder.php
│   ├── LiveFeaturedEvent.php
│   ├── CustomerPaymentMethod.php
│   └── StreamingUsage.php
├── Services/
│   ├── LiveStreamService.php         # Integração Cloudflare
│   ├── LiveShoppingService.php       # Lógica de negócio
│   ├── LiveChatService.php           # Chat + moderação
│   └── LiveMetricsService.php        # Viewers, likes, heartbeat
├── Views/
│   ├── admin/lives/
│   │   ├── index.php                 # Listagem
│   │   ├── form.php                  # Criar/editar
│   │   ├── studio.php                # Estúdio (mobile-first)
│   │   ├── report.php                # Relatório
│   │   └── config.php                # Configurações
│   └── lives/
│       └── watch.php                 # Player (TikTok-style)
public/assets/
├── css/lives/
│   ├── player.css                    # Player TikTok-style
│   └── studio.css                    # Estúdio admin
└── js/lives/
    ├── studio.js                     # WebRTC/WHIP, controles
    ├── player.js                     # HLS, SSE, UI
    ├── chat.js                       # Chat
    └── shopping.js                   # Compra 1-clique
cron/
└── live-recording-archive.php        # Cron de arquivamento
database/migrations/
└── 160_create_live_shopping_schema.sql
```

## Endpoints

### Admin
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /admin/lives | Listagem |
| GET | /admin/lives/nova | Form nova live |
| POST | /admin/lives | Criar live |
| GET | /admin/lives/{id}/editar | Form editar |
| POST | /admin/lives/{id}/atualizar | Atualizar |
| DELETE | /admin/lives/{id} | Excluir |
| GET | /admin/lives/{id}/studio | Estúdio |
| POST | /admin/lives/{id}/start | Iniciar transmissão |
| POST | /admin/lives/{id}/stop | Encerrar |
| POST | /admin/lives/{id}/feature | Destacar produto |
| GET | /admin/lives/{id}/report | Relatório |
| GET | /admin/configuracoes/lives | Config |
| POST | /admin/configuracoes/lives | Salvar config |

### Cliente
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /lives/{id} | Player da live |
| GET | /api/live/{id}/events | SSE (realtime) |
| POST | /api/live/{id}/chat | Enviar mensagem |
| POST | /api/live/{id}/like | Curtir |
| POST | /api/live/{id}/share | Compartilhar |
| POST | /api/live/{id}/buy | Compra 1-clique |
| POST | /api/live/{id}/heartbeat | Freemium gate |

## Troubleshooting

### "Erro ao criar transmissão no Cloudflare"
- Verificar se Account ID e API Token estão corretos
- Confirmar que o token tem permissão Stream:Edit
- Testar conexão em Config Lives

### Vídeo não carrega no player
- Verificar se a live está com status "live" no banco
- Confirmar que `cf_playback_url` está preenchido
- Testar a URL HLS diretamente no navegador

### SSE não funciona (chat/destaque não atualiza)
- Verificar se o servidor suporta conexões long-running
- Se usar Nginx, adicionar: `proxy_buffering off;`
- Fallback: usar endpoint `/api/live/{id}/status` com polling (2s)

### Compra 1-clique falha
- Verificar se cliente tem cartão tokenizado (`customer_payment_methods`)
- Verificar se produto está em `live_products` da live ativa
- Checar logs do gateway de pagamento

### Cota excedida
- Verificar `streaming_usage` para o mês atual
- Aumentar `minutos_inclusos` em Config Lives
- Ou mudar `modo_excedente` para "charge"
