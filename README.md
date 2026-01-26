# Braziliana Shop
 
Sistema MVC em PHP para e-commerce com operação logística internacional e painel administrativo.
 
Este `README.md` é a documentação principal do repositório (como rodar, como configurar e como a estrutura está organizada). Documentação complementar está em `docs/`.
 
## Visão geral
 
O projeto implementa:
- **Loja (público)**: catálogo, carrinho, checkout e páginas institucionais.
- **Autenticação**: login/cadastro de usuário e login admin.
- **Admin**: dashboard, produtos, pedidos, usuários, pagamentos, configurações e módulos operacionais (estoque, compras, relatórios e remessas).
- **Integrações / Webhooks**: endpoints para webhooks (ex.: Asaas/Stripe) e endpoints de API interna.
 
## Stack / requisitos
 
- **PHP**: `^8.0`
- **Extensões**: `pdo`, `mysqli` (ver `composer.json`)
- **Banco**: MySQL/MariaDB
- **Frontend**: Bootstrap + jQuery (views)
- **Web server**: Apache (com `mod_rewrite`) ou Nginx (com rewrite para front controller)
 
## Estrutura de pastas
 
```bash
brz-new/
├── app/                        # Aplicação (MVC)
│   ├── Controllers/            # Controllers
│   ├── Models/                 # Models
│   ├── Views/                  # Views/templates
│   ├── Core/                   # Router/Request e base do framework
│   ├── Services/               # Serviços (ex.: autenticação/pagamento)
│   ├── routes.php              # Rotas principais (público + admin + api)
│   └── routes_admin.php        # Rotas alternativas do admin (legado/auxiliar)
├── config/
│   └── Database.php            # Configuração de conexão com banco
├── public/                     # Única pasta pública (DocumentRoot)
│   ├── index.php               # Front controller (carrega Router + routes.php)
│   ├── .htaccess               # Rewrites do Apache para /public/index.php
│   └── uploads/                # Uploads (imagens etc.)
├── database/
│   ├── *.sql                   # Scripts de banco/schema
│   ├── migrations/             # Scripts auxiliares/migrations (quando existirem)
│   └── scripts/                # Scripts utilitários (ex.: verificações)
├── docs/                       # Documentação
│   ├── arquitetura.md
│   ├── readme/                 # READMEs complementares
│   └── notes/                  # Notas internas (checkpoint/correções/fluxo)
├── legacy/                     # Código/rascunhos antigos preservados (não usados no runtime)
├── scripts/                    # Scripts de desenvolvimento/debug (não usados no runtime)
├── index.php                   # Entry point alternativo (delegando para public/index.php)
└── composer.json               # Dependências + autoload
```
 
## Como o sistema roda (fluxo HTTP)
 
1. O servidor aponta o **DocumentRoot** para `public/`.
2. Rewrites encaminham as URLs para `public/index.php`.
3. `public/index.php` inicializa sessão, carrega `Router`/`Request` e inclui `app/routes.php`.
4. O router resolve a rota e chama o controller/método correspondente.

## Quick start (local)

1. **Configure o banco** (crie o schema executando os `.sql` em `database/`).
2. **Ajuste credenciais** em `config/Database.php`.
3. **Aponte o servidor para `public/`**.

Se você quiser rodar de forma simples em ambiente local, pode usar o servidor embutido do PHP apontando para a pasta `public/` (útil para testes rápidos). Exemplo:

```bash
php -S localhost:8000 -t public
```

Depois acesse:
- `http://localhost:8000/`

## Instalação / configuração
 
### 1) Dependências PHP
- Rode `composer install` para gerar o autoload.
- Observação: existe um autoloader manual em `public/index.php` para facilitar ambientes onde o Composer ainda não foi executado.

### 1.1) Variáveis de ambiente (opcional)

O projeto hoje lê a configuração de banco via `config/Database.php`. Se você quiser padronizar ambientes, pode criar um `.env` na raiz e adaptar o `config/Database.php` futuramente.

### 2) Banco de dados
 
Os scripts SQL ficam em `database/`. Em geral, você vai usar:
- `database/001_create_tables.sql`
- `database/002_complete_ecommerce_schema.sql`
- `database/003_admin_user_and_product_photos.sql`
 
Além disso, utilitários ficam em:
- `database/scripts/038_verificar_placeholder_banco.sql`
 
Configure a conexão em `config/Database.php`.

### 2.1) Observações de permissões (uploads)

- A pasta `public/uploads/` deve existir e ter permissão de escrita no ambiente onde o PHP roda.
- Em produção, evite servir uploads sem validação (tipo, tamanho e sanitização de nome de arquivo).

### 3) Apache / Nginx
 
**Apache**:
- Habilitar `mod_rewrite`.
- Configurar o DocumentRoot para a pasta `public/`.
 
**Nginx**:
- Configurar rewrite para direcionar todas as rotas para `public/index.php`.

## Rotas e endpoints (principais)
 
As rotas reais ficam em `app/routes.php`.
 
### Público
- `GET /` → `HomeController::index`
- `GET /produtos` → `ProdutoController::index`
- `GET /produto/detalhes/{id}` → `ProdutoController::detalhes`
- `GET /carrinho` → `CarrinhoController::index`
- `POST /carrinho/adicionar` → `CarrinhoController::adicionar`
- `POST /carrinho/remover` → `CarrinhoController::remover`
- `POST /carrinho/atualizar` → `CarrinhoController::atualizar`
- `POST /carrinho/limpar` → `CarrinhoController::limpar`
- `GET /checkout` → `CheckoutController::index`
- `POST /checkout/processar` → `CheckoutController::processar`
- `GET /checkout/conclusao/{id}` → `CheckoutController::conclusao`
- `GET /rastreamento` → `RastreamentoController::index`
- `GET /faq` → `FaqController::index`
- `GET /como-funciona` → `ComoFuncionaController::index`
- `GET /contato` → `ContatoController::index`
 
### Autenticação
- `GET/POST /login` → `AuthController::login`
- `GET/POST /loginadmin` → `AuthController::loginAdmin`
- `GET /logout` → `AuthController::logout`
- `GET/POST /register` → `AuthController::register`
- `GET/POST /perfil` → `AuthController::perfil`
 
### Área do usuário
- `GET /minha-conta` → `UsuarioController::minhaConta`
- `GET/POST /meus-dados` → `UsuarioController::meusDados`
- `GET /meus-pedidos` → `UsuarioController::meusPedidos`
- `GET /pedido/detalhes/{id}` → `UsuarioController::pedidoDetalhes`
 
### Admin (painel)
- `GET /admin` → página de menu do admin
- `GET /admin/dashboard` → `AdminDashboardController::index`
- `GET /admin/produtos` → `AdminProdutosController::index`
- `GET /admin/pedidos` → `AdminPedidosController::index`
- `GET /admin/usuarios` → `AdminUsuariosController::index`
- `GET /admin/pagamentos` → `AdminPagamentosController::index`
- `GET /admin/configuracoes` → `AdminConfiguracoesController::index`
 
### Admin (estoque / compras / relatórios)
- `GET /admin/estoque` → `AdminEstoqueController::index`
- `GET /admin/estoque/compras` → `AdminComprasController::index`
- `GET /admin/estoque/relatorios` → `AdminRelatoriosController::index`
 
### Webhooks
- `POST /webhook/asaas` → `WebhookController::asaas`
- `POST /webhook/stripe` → `WebhookController::stripe`
 
### API interna
- `GET /api/produtos/buscar` → `ApiController::buscarProdutos`
- `GET /api/produtos/destaque` → `ApiController::produtosDestaque`
- `POST /api/carrinho/adicionar` → `ApiController::adicionarAoCarrinho`
- `POST /api/carrinho/remover` → `ApiController::removerDoCarrinho`
- `POST /api/carrinho/atualizar` → `ApiController::atualizarCarrinho`
- `POST /api/carrinho/limpar` → `ApiController::limparCarrinho`
- `GET /api/cep/{cep}` → `ApiController::consultarCEP`
- `GET /api/frete/calcular` → `ApiController::calcularFrete`
 
## Documentação
 
- `docs/arquitetura.md`
- `docs/readme/README_ECOMMERCE.md`
- `docs/readme/README_ADMIN.md`
- `docs/notes/` (notas internas de correções/fluxo/checkpoints)
 
## Testes

O `composer.json` lista `phpunit/phpunit` em `require-dev`. Se você estiver usando Composer:
 
```bash
./vendor/bin/phpunit
```

## Troubleshooting

- **Erro 404 em rotas**
  - Confirme que o DocumentRoot está apontando para `public/`.
  - Confirme que o rewrite está ativo (Apache: `mod_rewrite`).
- **Erro 500**
  - Verifique logs do servidor web/PHP.
  - Confirme credenciais e acesso ao banco em `config/Database.php`.
- **Imagens/uploads não funcionam**
  - Confirme permissão de escrita em `public/uploads/`.
 
## Nota sobre `legacy/`

A pasta `legacy/` contém artefatos antigos/rascunhos preservados para referência e **não** deve ser usada no runtime.
 
## Desenvolvimento (padrões rápidos)
 
### Controllers
- Devem ficar em `app/Controllers/`.
 
### Models
- Devem ficar em `app/Models/`.
 
### Views
- Devem ficar em `app/Views/`.
 
## Licença
 
Este projeto está licenciado sob uma **licença proprietária de uso restrito**.

Veja `LICENSE.md`.
