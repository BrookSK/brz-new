# Co-Piloto Braziliana — Pendências do Sistema

## PENDÊNCIA 1 — Categorias de produto
- **Situação:** todos os produtos estão como "Sem categoria" no sistema
- **O que criar:** campo `categoria` real nos produtos (beleza, casa, alimentos, roupas, eletrônicos, limpeza, etc.)
- **Impacto se não criado:** copiloto não consegue sugerir por categoria, apenas por grupo/loja
- **Prioridade:** MÉDIA

## PENDÊNCIA 2 — Categoria "duvidas_gerais" no sistema de tickets
- **Situação:** sistema de tickets não tem esta categoria
- **O que criar:** adicionar "duvidas_gerais" nas categorias aceitas pela rota existente de criação de tickets
- **Impacto se não criado:** tickets sem pedido são recusados pela API
- **Prioridade:** ALTA

## PENDÊNCIA 3 — Data attributes no HTML dos produtos e grupos
- **Situação:** HTML atual não tem data attributes estruturados
- **O que criar:** adicionar `data-copiloto-*` nos templates de produto e grupo
- **Impacto se não criado:** leitura de DOM cai para seletores frágeis com fallbacks
- **Prioridade:** ALTA

## PENDÊNCIA 4 — Endpoint de status de pedido acessível sem navegação
- **Situação:** status só disponível via página /rastreamento (HTML)
- **O que criar:** endpoint JSON `GET /api/pedidos/:id/status`
- **Impacto se não criado:** copiloto usa scraping de /rastreamento como fallback (funciona mas é mais frágil)
- **Prioridade:** MÉDIA

## JÁ EXISTENTE
- Sistema de tickets (rota de criação)
- Carrinho com mini-carrinho no DOM
- Sessão via cookie
- Troca de idioma/moeda via /lang/pt e /lang/en
- Dados de produto no DOM (nome, preço, peso)
- Grupos de compra com imposto local informado no DOM
- Formulário de contato em /contato
- Página de rastreamento com fluxo de etapas
