# BRZ Shop - Painel Administrativo

## 📋 Visão Geral

Painel administrativo completo para e-commerce com todas as funcionalidades essenciais para gerenciamento de loja online.

## 🚀 Funcionalidades

### ✅ Dashboard
- Estatísticas em tempo real (produtos, pedidos, usuários, faturamento)
- Pedidos recentes
- Produtos mais vendidos
- Ações rápidas

### ✅ Produtos
- CRUD completo de produtos
- Upload múltiplo de imagens
- Categorias
- Gestão de estoque
- Preços e dimensões
- Status (ativo/inativo)

### ✅ Pedidos
- Listagem completa com filtros
- Detalhes do pedido
- Atualização de status
- Informações do cliente
- Histórico de pedidos

### ✅ Usuários
- Gestão de clientes
- Visualização de perfil
- Histórico de compras
- Estatísticas por usuário
- Ativação/desativação

### ✅ Pagamentos
- Múltiplos gateways (Stripe, Mercado Pago)
- PIX
- Confirmação manual
- Estatísticas de transações
- Configurações de pagamento

### ✅ Configurações
- Configurações da loja
- Email (SMTP)
- Entrega e frete
- SEO e Analytics
- Configurações do sistema

## 📁 Estrutura de Arquivos

```
app/Controllers/
├── AdminDashboardController.php     # Dashboard principal
├── AdminProdutosController.php       # Gestão de produtos
├── AdminPedidosController.php       # Gestão de pedidos
├── AdminUsuariosController.php       # Gestão de usuários
├── AdminPagamentosController.php    # Gestão de pagamentos
└── AdminConfiguracoesController.php # Configurações

app/
├── routes_admin.php                 # Rotas do painel admin
└── routes.php                      # Rotas principais

database/migrations/
└── 001_create_admin_tables.sql     # Migrações do banco
```

## 🛠️ Instalação

### 1. Banco de Dados

Execute o arquivo SQL de migração:

```sql
-- Execute o arquivo:
database/migrations/001_create_admin_tables.sql
```

### 2. Configuração das Rotas

Adicione as rotas do admin no seu arquivo principal de rotas:

```php
// Inclua as rotas do admin
require_once 'app/routes_admin.php';
```

### 3. Configuração do Banco

Verifique as credenciais do banco nos controllers:

```php
$pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
```

## 🔗 URLs de Acesso

### Painel Principal
- **Dashboard**: `/admin/dashboard`
- **Painel Admin**: `/admin`

### Módulos
- **Produtos**: `/admin/produtos`
- **Novo Produto**: `/admin/produtos/novo`
- **Pedidos**: `/admin/pedidos`
- **Usuários**: `/admin/usuarios`
- **Pagamentos**: `/admin/pagamentos`
- **Configurações**: `/admin/configuracoes`

## 🎨 Tecnologias Utilizadas

- **PHP 8+** - Backend
- **MySQL** - Banco de dados
- **Bootstrap 5** - UI Framework
- **Font Awesome 6** - Ícones
- **jQuery** - JavaScript
- **Summernote** - Editor de texto

## 📱 Design Responsivo

- Layout adaptativo para desktop, tablet e mobile
- Sidebar com navegação intuitiva
- Cards modernos com animações
- Cores consistentes com identidade visual

## 🔐 Segurança

- Validação de inputs
- Proteção contra SQL Injection
- Tratamento de erros
- Logs de atividades (implementado)

## 📊 Funcionalidades Técnicas

### Paginação
- Todos os listados possuem paginação
- Filtros por busca e status
- URLs amigáveis

### Upload de Imagens
- Múltiplas imagens por produto
- Imagem principal configurável
- Validação de tipos de arquivo

### Gestão de Status
- Pedidos: pendente, pago, enviado, entregue, cancelado
- Pagamentos: pendente, aprovado, recusado, estornado
- Produtos/Usuários: ativo/inativo

## 🔄 Fluxos de Trabalho

### Cadastro de Produto
1. Acessar `/admin/produtos/novo`
2. Preencher informações básicas
3. Adicionar imagens
4. Configurar preço e estoque
5. Definir status
6. Salvar

### Processamento de Pedido
1. Visualizar pedidos em `/admin/pedidos`
2. Ver detalhes do pedido
3. Atualizar status conforme necessário
4. Confirmar pagamento se manual

### Configuração de Pagamentos
1. Acessar `/admin/pagamentos/configuracoes`
2. Configurar gateways (Stripe, Mercado Pago)
3. Definir chaves PIX
4. Salvar configurações

## 🐛 Troubleshooting

### Erro 500
- Verifique conexão com banco
- Confirme permissões de pasta
- Verifique logs de erro

### Imagens não sobem
- Verifique permissões da pasta `uploads/`
- Confirme limite de upload no PHP
- Verifique extensões permitidas

### Emails não enviam
- Configure SMTP corretamente
- Verifique credenciais
- Confirme porta e criptografia

## 📈 Próximos Passos

1. **Autenticação**: Implementar login/admin
2. **API REST**: Criar endpoints para mobile
3. **Relatórios**: Adicionar relatórios detalhados
4. **Notificações**: Sistema de notificações
5. **Integrações**: WhatsApp, SMS, etc.

## 🤝 Contribuição

1. Fork o projeto
2. Crie branch para feature
3. Commit suas mudanças
4. Abra Pull Request

## 📄 Licença

Este projeto está sob licença MIT.

---

## 🎉 Resultado Final

Você terá um painel administrativo completo, moderno e funcional com:

- ✅ Interface profissional e responsiva
- ✅ Todas as funcionalidades de e-commerce
- ✅ Sistema de pagamentos integrado
- ✅ Gestão completa de produtos e pedidos
- ✅ Relatórios e estatísticas
- ✅ Configurações flexíveis

**Acesse `/admin` para começar a usar!** 🚀
