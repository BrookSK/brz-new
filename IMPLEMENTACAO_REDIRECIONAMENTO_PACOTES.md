# Implementacao do Sistema de Redirecionamento de Pacotes

## Contexto

Este documento detalha o que precisa ser implementado no sistema principal (brz-new) para replicar o fluxo de redirecionamento de pacotes que hoje existe apenas no plugin WordPress (woocommerce-package-redirect).

**IMPORTANTE:** O "Redirecionamento de Pacotes" descrito aqui e DIFERENTE do modulo "Redirecionador" que ja existe no sistema. O modulo existente (`AdminRedirecionamentoController`) e para parceiros (redirecionadores) que enviam suas proprias caixas atraves da Braziliana. O novo sistema e para clientes finais que compram produtos online, mandam entregar no armazem da Braziliana (EUA), e a Braziliana envia para o Brasil.

---

## 1. O QUE JA EXISTE NO SISTEMA (nao precisa reimplementar)

| Funcionalidade | Onde esta |
|---|---|
| Carrinho persistente (DB + session) | `CarrinhoController`, tabelas `carrinhos` + `carrinho_items` |
| Checkout com calculo de taxas (taxa_servico/kg, impostos, frete) | `CheckoutController` |
| Pagamento multi-gateway (Stripe, Asaas, Cambio Real, Wallet, Carne) | `PaymentService`, `CheckoutController` |
| Gestao de pedidos (CRUD, status, historico) | `AdminPedidosController`, `AdminPedidosEditController` |
| Geracao de etiquetas (W-Express, Correios, WordPress PACKET) | `AdminRedirecionamentoController` (envios), `snippet-api-etiquetas.php` |
| Tabela de pesos com precos por faixa | `redirecionamento_tabela_pesos` |
| Sistema de usuarios com perfis/roles | `AuthService`, tabela `usuarios` |
| E-mail service | `EmailService` |
| PDF generation | `PdfPedidoService` |
| QuickBooks sync (invoices contabeis) | `QuickBooksService` |

---

## 2. O QUE PRECISA SER IMPLEMENTADO (Gap)

### 2.1. Cadastro de Pacotes Recebidos (Admin)

**Conceito:** Quando um produto chega no armazem (EUA), o admin cadastra esse produto vinculado ao numero de suite do cliente.

**Tabela nova: `pacotes_recebidos`**

```sql
CREATE TABLE IF NOT EXISTS pacotes_recebidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_suite INT NOT NULL,
    usuario_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    fornecedor VARCHAR(255) NOT NULL,
    ncm VARCHAR(20) NULL,
    data_recebimento DATE NOT NULL,
    peso_kg DECIMAL(6,3) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    foto_url TEXT NULL,
    status ENUM(
        'pendente',
        'pedido_criado',
        'invoice_liberado',
        'invoice_confirmado',
        'invoice_contestado',
        'enviado',
        'fatura_pendente',
        'fatura_paga',
        'descartado'
    ) NOT NULL DEFAULT 'pendente',
    pedido_id INT NULL,
    produto_carrinho_id INT NULL,
    dias_armazenamento INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suite (numero_suite),
    INDEX idx_usuario (usuario_id),
    INDEX idx_status (status),
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos do formulario admin:**
| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| numero_suite | number | Sim | Identifica o cliente. Busca via AJAX pelo campo `suite` na tabela `usuarios` |
| nome | text | Sim | Nome do produto |
| descricao | textarea | Nao | Observacoes |
| fornecedor | text | Sim | Loja/origem |
| ncm | select | Sim | Codigo fiscal (lista pre-definida) |
| data_recebimento | date | Sim | Default: hoje |
| peso_kg | number(step=0.01) | Sim | Peso em kg |
| quantidade | number | Sim | Qtd de itens |
| foto | file/image | Sim | Foto do produto |

**Ao salvar:**
1. Valida se existe usuario com aquele numero de suite
2. Cria um registro em `pacotes_recebidos` com status `pendente`
3. Envia e-mail ao cliente informando que o produto foi cadastrado
4. Produto fica disponivel automaticamente no carrinho do cliente (ver 2.2)

**Bloqueio:** Apos status sair de `pendente`, campos ficam readonly.

---

### 2.2. Auto-Adicao ao Carrinho do Cliente

**Conceito:** Quando o cliente acessa o site (qualquer pagina), o sistema verifica se existem pacotes pendentes vinculados a sua suite e os adiciona automaticamente ao carrinho.

**Logica (no `CarrinhoController` ou middleware):**

```php
// Pseudo-codigo
function autoAdicionarPacotesPendentes(int $usuarioId): void {
    $suite = getUserSuite($usuarioId);
    if (!$suite) return;
    
    $pacotes = getPacotesPendentesPorSuite($suite);
    foreach ($pacotes as $pacote) {
        // Verifica se ja esta no carrinho
        if (!itemJaNoCarrinho($usuarioId, 'pacote_' . $pacote['id'])) {
            adicionarAoCarrinho($usuarioId, [
                'tipo' => 'pacote_redirecionamento',
                'pacote_id' => $pacote['id'],
                'nome' => $pacote['nome'],
                'peso_kg' => $pacote['peso_kg'],
                'quantidade' => $pacote['quantidade'],
                'preco' => 0, // Preco real e 0, taxas sao calculadas separado
                'foto_url' => $pacote['foto_url'],
            ]);
        }
    }
}
```

**Cache:** Usar transient/cache de 1-2 min para nao consultar DB a cada request.

---

### 2.3. Declaracao de Valor no Carrinho

**Conceito:** Para cada item do tipo `pacote_redirecionamento` no carrinho, o cliente DEVE informar o valor em dolares (declaration_value) antes de prosseguir ao checkout.

**Implementacao:**
- Campo `declaration_value` editavel no carrinho, junto a cada item de pacote
- Validacao pre-checkout: se algum item de pacote nao tem declaration_value, redireciona ao carrinho com erro
- O valor declarado e usado no calculo da Taxa de Seguro e na Invoice/etiqueta

**Coluna nova em `carrinho_items`:**
```sql
ALTER TABLE carrinho_items ADD COLUMN declaration_value DECIMAL(10,2) NULL;
ALTER TABLE carrinho_items ADD COLUMN tipo_item VARCHAR(30) DEFAULT 'produto';
ALTER TABLE carrinho_items ADD COLUMN pacote_id INT NULL;
```

---

### 2.4. Ativar/Desativar Itens no Carrinho

**Conceito:** Cliente pode desmarcar pacotes que nao quer enviar agora. Itens desmarcados ficam em "carrinho paralelo" (visiveis mas em cinza).

**Implementacao:**
- Checkbox por item no carrinho
- Itens desmarcados: salvos em `user_meta` ou tabela auxiliar (`carrinho_itens_inativos`)
- Itens inativos NAO entram no calculo de taxas/checkout
- Ao remarcar, volta ao carrinho ativo

**Nota:** O sistema ja tem algo similar via session (`$_SESSION['carrinho_itens_ativos']`). Pode reaproveitar/expandir.

---

### 2.5. Sistema de Invoice (Conferencia pelo Cliente)

**Conceito:** Apos o pagamento do pedido, antes de enviar, o admin "libera o invoice" para o cliente conferir os dados que irao na etiqueta. O cliente pode confirmar ou contestar.

**Tabela nova: `pedido_invoices`**

```sql
CREATE TABLE IF NOT EXISTS pedido_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    status ENUM('liberado','confirmado','contestado') NOT NULL DEFAULT 'liberado',
    contestacao_motivo TEXT NULL,
    confirmado_em TIMESTAMP NULL,
    contestado_em TIMESTAMP NULL,
    liberado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Tabela nova: `pedido_invoice_items`**

```sql
CREATE TABLE IF NOT EXISTS pedido_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    pedido_item_id INT NULL,
    pacote_id INT NULL,
    nome_produto VARCHAR(255) NOT NULL,
    ncm VARCHAR(20) NULL,
    declaration_value DECIMAL(10,2) NOT NULL,
    peso_kg DECIMAL(6,3) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    tem_bateria ENUM('S','N') DEFAULT 'N',
    tem_perfume ENUM('S','N') DEFAULT 'N',
    foto_url TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fluxo:**

1. **Admin libera invoice:** Acao no pedido que cria registro em `pedido_invoices` com status `liberado`
   - Status do pedido muda para `invoice_liberado`
   - Items do pedido sao copiados para `pedido_invoice_items`
   
2. **Cliente confere:** Pagina `/minha-conta/invoice?pedido_id=X`
   - Ve: dados pessoais (somente leitura), endereco de entrega (editavel), itens (editavel)
   - Pode editar: nome do produto (sera usado na etiqueta), valor declarado (limitado: <= valor original), bateria S/N, perfume S/N, endereco de entrega
   - **Botao "Finalizar":** Confirma os dados, status muda para `invoice_confirmado`, pedido muda para `invoice_confirmado`
   - **Botao "Contestar":** Abre textarea para motivo, status muda para `invoice_contestado`

3. **Admin ve contestacao:** Motivo aparece no painel do pedido. Pode ajustar e re-liberar.

**IMPORTANTE sobre a etiqueta:** Os dados que o cliente preenche na invoice (especialmente o `nome_produto` de cada item) sao os que vao na declaracao aduaneira da etiqueta. Ou seja, o campo `_product_name` do invoice e o que aparece no PDF da etiqueta como descricao do produto (correios internacional).

---

### 2.6. Novos Status de Pedido

Adicionar ao sistema os seguintes status (na tabela `pedidos` ou no enum/logica de status):

| Status | Slug | Descricao |
|--------|------|-----------|
| Invoice Liberado | `invoice_liberado` | Admin liberou para cliente conferir |
| Invoice Confirmado | `invoice_confirmado` | Cliente confirmou os dados |
| Invoice Contestado | `invoice_contestado` | Cliente contestou, precisa ajuste |
| Fatura Pendente | `fatura_pendente` | Cobranca adicional pendente |
| Fatura Paga | `fatura_paga` | Cobranca adicional paga |

Os status existentes (`pendente`, `pago`, `enviado`, `cancelado`, etc.) continuam validos.

---

### 2.7. Sistema de Fatura Adicional

**Conceito:** Cobrar valor extra de um pedido ja existente (ex: taxa adicional, produto faltante, etc.)

**Tabela nova: `pedido_faturas_adicionais`**

```sql
CREATE TABLE IF NOT EXISTS pedido_faturas_adicionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao TEXT NULL,
    status ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
    pago_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fluxo:**
1. Admin cria fatura adicional vinculada a um pedido
2. Produto/item aparece automaticamente no carrinho do cliente (como tipo `fatura_adicional`)
3. Cliente paga
4. Status do pedido muda de `fatura_pendente` para `fatura_paga`
5. Item e removido do carrinho

---

### 2.8. Cron Job - Verificacao de Armazenamento

**Conceito:** Verificacao diaria dos pacotes pendentes. Aplica multa por dia de atraso e descarta apos prazo.

**Logica:**
```
1. Buscar todos pacotes com status = 'pendente'
2. Calcular dias desde data_recebimento
3. Salvar dias_armazenamento no registro
4. Se dias >= 15 e (dias - 15) % 5 == 0: enviar e-mail de lembrete
5. Se dias >= dia_descarte (config): mudar status para 'descartado', enviar e-mail
```

**Configuracoes (tabela `configuracoes_sistema`):**
- `pacote_dias_multa_inicio`: 15 (dia que comeca a multa)
- `pacote_multa_valor_dia_usd`: 2.00 (valor por dia)
- `pacote_dias_descarte`: 42 (dia do descarte)
- `pacote_lembrete_intervalo_dias`: 5 (a cada quantos dias enviar e-mail)

**Taxa de armazenamento no checkout:** Quando o pacote tem `dias_armazenamento > pacote_dias_multa_inicio`, calcular e adicionar fee:
```
multa = (dias_armazenamento - dias_multa_inicio) * valor_dia
```

---

### 2.9. Telas Admin Necessarias

| Tela | Rota | Descricao |
|------|------|-----------|
| Lista pacotes recebidos | `/admin/pacotes-recebidos` | Tabela com filtros por suite, status, data |
| Novo pacote | `/admin/pacotes-recebidos/novo` | Formulario de cadastro |
| Editar pacote | `/admin/pacotes-recebidos/{id}` | Edicao (bloqueada apos pagamento) |
| Config taxas pacotes | `/admin/pacotes-recebidos/configuracoes` | Taxas por faixa de peso, multa, etc. |
| Faturas adicionais | `/admin/faturas-adicionais` | Lista de faturas extras |
| Invoice do pedido | (dentro de `/admin/pedidos/{id}`) | Secao com botao "Liberar Invoice" e detalhes |

### 2.10. Telas Cliente Necessarias

| Tela | Rota | Descricao |
|------|------|-----------|
| Conferir Invoice | `/minha-conta/invoice?pedido_id=X` | Pagina de conferencia dos dados para etiqueta |

---

## 3. RELACAO COM A ETIQUETA (Invoice -> Etiqueta)

O ponto crucial: **os dados confirmados pelo cliente no invoice sao os que vao na etiqueta de envio**.

Quando o admin gera a etiqueta (via W-Express, Correios ou WordPress PACKET), os dados devem vir de `pedido_invoice_items`:
- `nome_produto` -> campo `description` na declaracao aduaneira
- `ncm` -> campo `hsCode`
- `declaration_value` -> campo `value` (preco unitario USD)
- `peso_kg` -> campo `weight`
- `quantidade` -> campo `quantity`
- `tem_bateria` / `tem_perfume` -> informacoes adicionais

O endereco de entrega tambem pode ter sido alterado pelo cliente durante a conferencia do invoice.

---

## 4. LISTA NCM (Codigos Fiscais)

Lista pre-definida usada no select do cadastro e no invoice:

```php
$ncm_options = [
    '61091000' => 'Camiseta',
    '64041900' => 'Calcados',
    '42022200' => 'Bolsa',
    '42023100' => 'Carteira',
    '71171900' => 'Bijuteria',
    '33049900' => 'Cosmeticos',
    '85176200' => 'Eletronicos',
    '95030099' => 'Brinquedos',
    '84713019' => 'Notebook/Tablet',
    '85171200' => 'Celular',
    '62046200' => 'Calca Jeans',
    '61101200' => 'Casaco/Moletom',
    '39269090' => 'Acessorios Plastico',
    '96032100' => 'Escova de Dentes',
    '33051000' => 'Shampoo',
    '30049099' => 'Suplementos/Vitaminas',
    '84718000' => 'Acessorios de Informatica',
];
```

---

## 5. RESUMO DO FLUXO COMPLETO

```
[ADMIN] Cadastra pacote recebido (suite + dados do produto)
    |
    v
Cria registro em pacotes_recebidos (status: pendente)
Envia e-mail ao cliente
    |
    v
[CLIENTE] Acessa o site -> produto aparece automaticamente no carrinho
    |
    v
[CLIENTE] Preenche declaracao de valor ($) para cada pacote
[CLIENTE] Ativa/desativa pacotes que quer enviar agora
    |
    v
[CLIENTE] Vai ao checkout -> taxas calculadas (servico/kg + seguro + armazenamento se aplicavel)
    |
    v
[CLIENTE] Paga -> status do pacote: 'pedido_criado', pedido criado normalmente
    |
    v
[ADMIN] No pedido, clica "Liberar Invoice"
    -> status pedido: 'invoice_liberado'
    -> status pacote: 'invoice_liberado'
    |
    v
[CLIENTE] Acessa /minha-conta/invoice?pedido_id=X
    -> Confere/edita: nomes dos produtos, valores, bateria/perfume, endereco
    -> Clica "Finalizar" OU "Contestar"
    |
    v
SE FINALIZAR:
    -> status pedido: 'invoice_confirmado'
    -> status pacote: 'invoice_confirmado'
    -> Admin pode gerar etiqueta com os dados confirmados
    |
SE CONTESTAR:
    -> status pedido: 'invoice_contestado'
    -> motivo salvo
    -> Admin ajusta e re-libera
    |
    v
[ADMIN] Gera etiqueta (dados vem do invoice confirmado)
[ADMIN] Envia codigo de rastreio
    -> status: 'enviado'
```

---


---

## 6. PROMPT PARA IMPLEMENTACAO (copiar e colar no outro chat)

---

### INICIO DO PROMPT ###

Preciso implementar o sistema de **Redirecionamento de Pacotes** no nosso sistema PHP (brz-new). Esse sistema permite que o admin cadastre produtos que chegam no armazem (EUA) vinculados a suite de um cliente, o produto vai automaticamente pro carrinho do cliente, o cliente paga, e depois passa por um fluxo de conferencia de invoice antes do envio.

**IMPORTANTE:** Isso e DIFERENTE do modulo "Redirecionador" existente (`AdminRedirecionamentoController`). O redirecionador existente e para parceiros que enviam suas proprias caixas. O novo sistema e para clientes finais que compram online, mandam entregar no armazem, e a Braziliana faz o envio para o Brasil.

#### O que ja existe e deve ser REAPROVEITADO:
- `CarrinhoController` (carrinho persistente em DB)
- `CheckoutController` (checkout com taxas, pagamento)
- `AdminPedidosController` / `AdminPedidosEditController` (gestao de pedidos)
- Tabelas: `carrinhos`, `carrinho_items`, `pedidos`, `pedido_items`, `usuarios`
- `EmailService` para envio de e-mails
- Calculo de taxas: `taxa_servico_usd_por_kg` (configuracoes_sistema), impostos, frete
- Campo `suite` na tabela `usuarios` (identifica o cliente)
- Geracao de etiquetas (W-Express, Correios, WordPress PACKET) - ja integrado

#### O que precisa ser CRIADO:

**1. Tabela `pacotes_recebidos`** - Cadastro de produtos que chegam no armazem
- Campos: id, numero_suite, usuario_id, nome, descricao, fornecedor, ncm, data_recebimento, peso_kg, quantidade, foto_url, status (enum: pendente, pedido_criado, invoice_liberado, invoice_confirmado, invoice_contestado, enviado, fatura_pendente, fatura_paga, descartado), pedido_id, dias_armazenamento, created_at, updated_at

**2. Tela admin "Pacotes Recebidos"** (`AdminPacotesRecebidosController`)
- Listagem com filtros (suite, status, data)
- Formulario de cadastro: numero_suite (busca usuario via AJAX), nome, descricao, fornecedor, ncm (select pre-definido), data_recebimento, peso_kg, quantidade, foto (upload)
- Ao salvar: valida usuario pela suite, cria registro, envia e-mail ao cliente
- Edicao bloqueada apos status sair de 'pendente'

**3. Auto-adicao ao carrinho** - No `CarrinhoController` (ou hook no index):
- Ao carregar carrinho, buscar pacotes pendentes da suite do usuario logado
- Se pacote nao esta no carrinho, adicionar automaticamente (preco=0, tipo='pacote_redirecionamento')
- Novas colunas em `carrinho_items`: `declaration_value DECIMAL(10,2) NULL`, `tipo_item VARCHAR(30) DEFAULT 'produto'`, `pacote_id INT NULL`

**4. Campo "Declaracao de Valor" no carrinho:**
- Para itens tipo 'pacote_redirecionamento', exibir campo numerico "Valor declarado (USD)"
- Obrigatorio antes do checkout. Se vazio, bloquear checkout e redirecionar ao carrinho com mensagem
- Valor usado no calculo de Taxa de Seguro e vai na etiqueta

**5. Tabela `pedido_invoices`** - Controle do fluxo de invoice
- Campos: id, pedido_id, status (liberado/confirmado/contestado), contestacao_motivo, confirmado_em, contestado_em, liberado_em, created_at

**6. Tabela `pedido_invoice_items`** - Itens do invoice (editaveis pelo cliente)
- Campos: id, invoice_id, pedido_item_id, pacote_id, nome_produto, ncm, declaration_value, peso_kg, quantidade, tem_bateria (S/N), tem_perfume (S/N), foto_url

**7. Acao "Liberar Invoice" no admin do pedido:**
- Botao na tela de edicao do pedido
- Cria registro em pedido_invoices (status=liberado)
- Copia items do pedido para pedido_invoice_items
- Muda status do pedido para 'invoice_liberado'
- Muda status dos pacotes vinculados para 'invoice_liberado'

**8. Pagina do cliente "Conferir Invoice"** (`/minha-conta/invoice?pedido_id=X`):
- So acessivel quando status = invoice_liberado
- Exibe: dados pessoais (readonly), endereco de entrega (EDITAVEL), tabela de itens
- Cada item editavel: nome_produto (o que vai na etiqueta), declaration_value (max = valor original), bateria S/N, perfume S/N
- Exibe imagens anexadas
- Dois botoes:
  - "Finalizar" -> status = invoice_confirmado, pedido = invoice_confirmado
  - "Contestar" -> abre textarea pro motivo, status = invoice_contestado

**9. Novos status de pedido:** invoice_liberado, invoice_confirmado, invoice_contestado, fatura_pendente, fatura_paga

**10. Integracao com etiqueta:** Quando gerar etiqueta, os dados devem vir de `pedido_invoice_items` (nome_produto, ncm, declaration_value, peso, quantidade). O campo `nome_produto` que o cliente confirmou/editou no invoice e o que aparece como descricao na declaracao aduaneira da etiqueta.

**11. Tabela `pedido_faturas_adicionais`** - Cobrar valor extra de pedido existente
- Campos: id, pedido_id, motivo, valor, descricao, status (pendente/pago/cancelado), pago_em
- Ao criar: item aparece no carrinho do cliente como tipo 'fatura_adicional', pedido muda para 'fatura_pendente'
- Ao pagar: status = pago, pedido volta ao status anterior (ou 'fatura_paga')

**12. Cron job diario** para verificar pacotes pendentes:
- Calcular dias de armazenamento desde data_recebimento
- A cada 5 dias apos o 15o dia: enviar e-mail de lembrete (taxa de armazenamento)
- Apos dia configuravel (default 42): descartar (status='descartado'), enviar e-mail
- Taxa de armazenamento calculada no checkout: `(dias - 15) * valor_dia_config`
- Configs em configuracoes_sistema: `pacote_dias_multa_inicio`, `pacote_multa_valor_dia_usd`, `pacote_dias_descarte`

**13. SPLIT DE PAGAMENTO (CRITICO):**
O sistema usa DUAS contas Cambio Real separadas:
- **Conta 1 (gateway `cambioreal`):** Recebe SOMENTE o valor dos produtos (subtotal)
- **Conta 2 (gateway `cambioreal_taxas`):** Recebe taxa de servico + impostos + TODAS as demais taxas

As novas taxas (Taxa de Seguro, Taxa de Armazenamento) devem ir para a **Conta 2 (`cambioreal_taxas`)**, junto com taxa de servico e impostos. O metodo `gerarCobrancaCambioRealTaxasSplit()` ja existe no `CheckoutController` - basta somar as novas taxas no `$valorCR2`.

Resumo do split:
```
Cobranca 1 (cambioreal):       subtotal produtos (preco dos itens)
Cobranca 2 (cambioreal_taxas): taxa_servico + impostos + taxa_seguro + taxa_armazenamento
```

Para Fatura Adicional (cobranca posterior): tambem vai pela conta `cambioreal_taxas` usando o mesmo `gerarCobrancaCambioRealTaxasSplit()`.

Credenciais ja configuradas em `configuracoes_sistema`: `cambioreal_taxas_app_id`, `cambioreal_taxas_app_public`, `cambioreal_taxas_app_secret`.

**14. Lista NCM fixa** (para select):
```php
$ncm_options = [
    '61091000' => 'Camiseta',
    '64041900' => 'Calcados',
    '42022200' => 'Bolsa',
    '42023100' => 'Carteira',
    '71171900' => 'Bijuteria',
    '33049900' => 'Cosmeticos',
    '85176200' => 'Eletronicos',
    '95030099' => 'Brinquedos',
    '84713019' => 'Notebook/Tablet',
    '85171200' => 'Celular',
    '62046200' => 'Calca Jeans',
    '61101200' => 'Casaco/Moletom',
    '39269090' => 'Acessorios Plastico',
    '96032100' => 'Escova de Dentes',
    '33051000' => 'Shampoo',
    '30049099' => 'Suplementos/Vitaminas',
    '84718000' => 'Acessorios de Informatica',
];
```

**Padrao de codigo:**
- Seguir o mesmo padrao dos controllers existentes (namespace `App\Controllers`, extends `Controller`)
- Views em `app/Views/admin/pacotes-recebidos/` e `app/Views/cliente/invoice.php`
- Migrations em `database/migrations/`
- Usar Bootstrap 5 para UI (mesmo das telas existentes)
- Usar o `AuthService` para controle de acesso
- Ajax com fetch API (padrao do projeto)

**Fluxo resumido:**
```
Admin cadastra pacote -> Email ao cliente -> Produto no carrinho automaticamente
-> Cliente declara valor -> Checkout -> Pagamento -> Pedido criado
-> Admin libera invoice -> Cliente confere (edita nomes/valores/endereco)
-> Cliente finaliza OU contesta -> Admin gera etiqueta com dados do invoice
-> Envio -> Tracking code -> Concluido
```

### FIM DO PROMPT ###

---

## 7. NOTAS TECNICAS ADICIONAIS

### E-mail de novo pacote (template):
- Assunto: "Seu produto foi cadastrado!"
- Conteudo: nome do produto, fornecedor, peso, quantidade, data recebimento
- Alerta: "Voce tem ate 30 dias para concluir suas compras e solicitar o envio"
- Aviso de multa apos 30 dias e descarte apos 42 dias
- Link para o carrinho

### Calculo de taxas no checkout (para pacotes):
- **Taxa de Servico:** peso_total * taxa_servico_usd_por_kg (ja existe)
- **Taxa de Seguro:** baseada no declaration_value (NOVA - configurar faixas)
- **Taxa de Armazenamento:** dias_atraso > 15 ? (dias_atraso - 15) * valor_dia : 0
- **Impostos:** mesma regra existente (Receita Federal sobre subtotal)

### SPLIT DE PAGAMENTO - Cambio Real (duas contas):

O sistema usa DUAS contas Cambio Real separadas:
- **Conta 1 (CR Produtos):** Recebe SOMENTE o valor dos produtos (subtotal)
- **Conta 2 (CR Taxas):** Recebe taxa de servico + impostos + TODAS as demais taxas

As novas taxas (Seguro, Armazenamento, Fatura Adicional) devem ir para a **Conta 2 (CR Taxas)**, junto com taxa de servico e impostos.

**Como funciona hoje no `CheckoutController`:**
```
Cobranca 1: cambioreal (produtos)     -> valor dos itens (subtotal produtos)
Cobranca 2: cambioreal_taxas (taxas)  -> taxa_servico + impostos
```

**Como deve ficar com as novas taxas:**
```
Cobranca 1: cambioreal (produtos)     -> valor dos itens (subtotal produtos)
Cobranca 2: cambioreal_taxas (taxas)  -> taxa_servico + impostos + taxa_seguro + taxa_armazenamento
```

O metodo `gerarCobrancaCambioRealTaxasSplit()` ja existe no `CheckoutController`. Basta somar as novas taxas no `$valorCR2` que e passado para ele. As credenciais da conta taxas ja estao configuradas (`cambioreal_taxas_app_id`, `cambioreal_taxas_app_public`, `cambioreal_taxas_app_secret`).

**Para Fatura Adicional:** Como e uma cobranca separada (posterior ao pedido original), tambem vai pela conta CR Taxas. Usar o mesmo `gerarCobrancaCambioRealTaxasSplit()` passando o valor da fatura.

### Validacao de suite:
- Campo `suite` na tabela `usuarios`
- AJAX endpoint: `/api/buscar-usuario-suite?suite=XXXX` retorna `{nome, email, id}` ou erro

### Sobre o campo nome_produto no invoice:
- Este e o campo mais importante da invoice
- O que o cliente escreve aqui vai DIRETO na etiqueta como descricao do produto
- Normalmente o admin pre-preenche com o nome cadastrado no pacote
- O cliente pode ajustar (ex: mudar de "iPhone 15" para "Celular Apple" por questoes aduaneiras)
- O sistema deve usar EXATAMENTE o que o cliente colocou ao gerar a etiqueta
