# Co-Piloto Braziliana — Guia de Integração

## Injeção no Site (automática)

O widget é injetado automaticamente em todas as páginas quando ativado no admin.
A injeção é feita no layout principal (`app/Views/layouts/main.php`).

Quando o copiloto está **ativo** no admin (`Configurações > Co-Piloto IA`), o script é carregado:

```html
<script src="https://copiloto.braziliana.com.br/copiloto.js"
    data-backend="https://copiloto.braziliana.com.br"
    async></script>
```

## Backend Node.js

O backend roda separadamente como um serviço Node.js/Express.

### Setup

```bash
cd copiloto-braziliana/backend
cp .env.example .env
# Editar .env com as credenciais reais
npm install
npm start
```

### Rotas

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | /api/copiloto/mensagem | Rota principal — classifica + responde |
| GET | /api/copiloto/gatilho | Gatilhos proativos |
| POST | /api/copiloto/ticket | Proxy para tickets |
| POST | /api/copiloto/status-pedido | Consulta status |
| GET | /api/copiloto/cancelamento/verificar | Verifica elegibilidade |
| POST | /api/copiloto/cancelamento/solicitar | Solicita cancelamento |
| GET | /health | Health check |

## Admin

Acessar: `/admin/copiloto`

- Configurações gerais (API key, ativar/desativar)
- Aprendizado da IA (pendências automáticas)
- Conteúdo de referência (upload de PDFs, docs)
- Cancelamentos (aprovação/recusa)
