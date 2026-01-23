# BRZ Logistics - Sistema de Logística Internacional

Sistema completo de logística e comércio internacional desenvolvido em PHP com arquitetura MVC.

## Fluxo do Processo

1. **Cliente** - Acesso ao sistema e seleção de produtos
2. **Seleção dos Produtos** - Escolha dos produtos desejados no catálogo
3. **Cobrança** - Cálculo automático de produtos + serviços + impostos
4. **API W Express** - Integração com sistema de envio internacional
5. **Despacho para MIA** - Processamento de despacho para Miami
6. **Voo para BR** - Transporte aéreo para o Brasil
7. **Pagamento dos Impostos** - Processamento aduaneiro e tributário
8. **Translado da Carga** - Transporte até armazém
9. **Processamento da Carga** - Tratamento e armazenamento
10. **Envio via Correios** - Entrega final ao cliente

## Estrutura do Projeto

```
brz-new/
├── app/
│   ├── Controllers/     # Controladores MVC
│   ├── Core/           # Classes principais (Router, Request)
│   ├── Models/         # Modelos de dados
│   ├── Services/       # Serviços externos (WExpress)
│   ├── Views/          # Views/templates
│   └── routes.php      # Definição de rotas
├── config/
│   └── Database.php    # Configuração do banco
├── database/
│   └── 001_create_tables.sql  # Migração inicial
├── public/
│   └── index.php       # Front controller
├── composer.json       # Dependências PHP
└── .htaccess          # Configuração Apache
```

## Instalação

1. **Clone o repositório**
   ```bash
   git clone <repository-url>
   cd brz-new
   ```

2. **Instale as dependências**
   ```bash
   composer install
   ```

3. **Configure o banco de dados**
   - Crie o banco de dados `brz_logistics`
   - Execute o arquivo SQL: `database/001_create_tables.sql`
   - Ajuste as credenciais em `config/Database.php`

4. **Configure o servidor web**
   - Apache: Configure o DocumentRoot para a pasta `public/`
   - Nginx: Configure rewrite rules para apontar para `public/index.php`

## Funcionalidades

### 📦 Catálogo de Produtos
- Listagem de produtos com busca e filtros
- Informações detalhadas (preço, peso, estoque)
- Sistema de carrinho de compras

### 💰 Cálculo Automático
- Cálculo de impostos (Importação, ICMS, PIS, COFINS)
- Serviços adicionais (despacho, translado, armazenamento)
- Resumo detalhado dos custos

### 🚀 Integração W Express
- API para cotação de fretes
- Rastreamento de envios
- Criação de remessas internacionais

### 📍 Rastreamento
- Acompanhamento completo do pedido
- Timeline visual do processo
- Histórico de atualizações

### 👥 Gestão de Clientes
- Cadastro automático de clientes
- Histórico de pedidos
- Dados de contato e endereço

## Tecnologias Utilizadas

- **PHP 8.0+** - Linguagem principal
- **MySQL** - Banco de dados
- **Bootstrap 5** - Framework CSS
- **jQuery** - Biblioteca JavaScript
- **Font Awesome** - Ícones

## Configuração de Ambiente

### Variáveis de Ambiente
Crie um arquivo `.env` na raiz do projeto:

```env
WEXPRESS_API_KEY=sua_chave_api
DB_HOST=localhost
DB_NAME=brz_logistics
DB_USER=root
DB_PASSWORD=
```

### Configuração Apache
Certifique-se de que os módulos abaixo estão ativos:
- mod_rewrite
- mod_php

### Configuração PHP
Extensões necessárias:
- pdo_mysql
- curl
- json

## Endpoints da API

### Produtos
- `GET /produtos` - Listar produtos
- `POST /produtos/carrinho` - Adicionar ao carrinho

### Cobrança
- `GET /cobranca` - Página de cálculo
- `POST /cobranca/calcular` - Calcular valores

### Processamento
- `POST /processar` - Processar pedido

### Rastreamento
- `GET /rastreamento` - Buscar pedido
- `GET /rastreamento?id={id}` - Detalhes do pedido

## Estrutura do Banco de Dados

### Tabelas Principais
- `clientes` - Dados dos clientes
- `produtos` - Catálogo de produtos
- `pedidos` - Pedidos realizados
- `pedido_items` - Itens dos pedidos
- `rastreamento` - Histórico de rastreamento
- `servicos` - Serviços disponíveis
- `impostos` - Configurações de impostos

## Fluxo de Trabalho

1. **Cliente acessa o sistema** → Página inicial
2. **Seleciona produtos** → Adiciona ao carrinho
3. **Finaliza compra** → Preenche dados e calcula custos
4. **Processa pedido** → Gera pedido no sistema
5. **Integração W Express** → Cria envio internacional
6. **Acompanhamento** → Rastreamento completo

## Desenvolvimento

### Adicionando Novos Controllers
```php
<?php
namespace App\Controllers;

use App\Core\Request;

class NovoController extends Controller {
    public function index(Request $request) {
        $this->view('novo/index', ['data' => []]);
    }
}
```

### Adicionando Novos Models
```php
<?php
namespace App\Models;

class NovoModel extends Model {
    protected $table = 'nova_tabela';
    
    public function customMethod() {
        // Lógica personalizada
    }
}
```

## Contribuição

1. Fork do projeto
2. Criar branch para feature (`git checkout -b feature/nova-funcionalidade`)
3. Commit das mudanças (`git commit -am 'Adiciona nova funcionalidade'`)
4. Push para o branch (`git push origin feature/nova-funcionalidade`)
5. Abrir Pull Request

## Licença

Este projeto está licenciado sob a MIT License.
