# 🏗️ Arquitetura do Sistema E-commerce

## 📁 Estrutura de Diretórios

```
brz-new/
├── app/
│   ├── Core/                           # Camada Core (Domain)
│   │   ├── Domain/                     # Entidades de Domínio
│   │   │   ├── User.php
│   │   │   ├── Product.php
│   │   │   ├── Order.php
│   │   │   ├── Cart.php
│   │   │   └── Payment.php
│   │   ├── Services/                   # Serviços de Domínio
│   │   │   ├── AuthService.php
│   │   │   ├── ProductService.php
│   │   │   ├── OrderService.php
│   │   │   ├── PaymentService.php
│   │   │   └── CartService.php
│   │   ├── Repositories/               # Interfaces de Repositórios
│   │   │   ├── UserRepositoryInterface.php
│   │   │   ├── ProductRepositoryInterface.php
│   │   │   ├── OrderRepositoryInterface.php
│   │   │   └── PaymentRepositoryInterface.php
│   │   └── ValueObjects/               # Value Objects
│   │       ├── Email.php
│   │       ├── Money.php
│   │       ├── Address.php
│   │       └── Phone.php
│   │
│   ├── Infrastructure/                  # Camada de Infraestrutura
│   │   ├── Database/                   # Implementações de BD
│   │   │   ├── Connection.php
│   │   │   ├── Migrations/
│   │   │   └── Seeders/
│   │   ├── Repositories/               # Implementações concretas
│   │   │   ├── MySQLUserRepository.php
│   │   │   ├── MySQLProductRepository.php
│   │   │   ├── MySQLOrderRepository.php
│   │   │   └── MySQLPaymentRepository.php
│   │   ├── Payment/                    # Gateways de Pagamento
│   │   │   ├── PaymentGatewayInterface.php
│   │   │   ├── StripeGateway.php
│   │   │   ├── MercadoPagoGateway.php
│   │   │   └── PixGateway.php
│   │   ├── Cache/                      # Sistema de Cache
│   │   │   ├── CacheInterface.php
│   │   │   └── RedisCache.php
│   │   └── Email/                      # Sistema de Email
│   │       ├── EmailServiceInterface.php
│   │       └── SMTPEmailService.php
│   │
│   ├── Application/                    # Camada de Aplicação
│   │   ├── UseCases/                  # Casos de Uso
│   │   │   ├── Auth/
│   │   │   │   ├── RegisterUser.php
│   │   │   │   ├── LoginUser.php
│   │   │   │   ├── LogoutUser.php
│   │   │   │   └── RecoverPassword.php
│   │   │   ├── Product/
│   │   │   │   ├── CreateProduct.php
│   │   │   │   ├── UpdateProduct.php
│   │   │   │   ├── DeleteProduct.php
│   │   │   │   └── ListProducts.php
│   │   │   ├── Order/
│   │   │   │   ├── CreateOrder.php
│   │   │   │   ├── UpdateOrderStatus.php
│   │   │   │   └── CancelOrder.php
│   │   │   └── Cart/
│   │   │       ├── AddToCart.php
│   │   │       ├── RemoveFromCart.php
│   │   │       └── UpdateCart.php
│   │   ├── DTOs/                      # Data Transfer Objects
│   │   │   ├── UserDTO.php
│   │   │   ├── ProductDTO.php
│   │   │   ├── OrderDTO.php
│   │   │   └── CartDTO.php
│   │   └── Validators/                # Validadores
│   │       ├── UserValidator.php
│   │       ├── ProductValidator.php
│   │       └── OrderValidator.php
│   │
│   ├── Presentation/                   # Camada de Apresentação
│   │   ├── Controllers/               # Controllers (HTTP)
│   │   │   ├── AuthController.php
│   │   │   ├── ProductController.php
│   │   │   ├── OrderController.php
│   │   │   ├── CartController.php
│   │   │   └── Admin/
│   │   │       ├── AdminDashboardController.php
│   │   │       ├── AdminProductController.php
│   │   │       ├── AdminOrderController.php
│   │   │       └── AdminUserController.php
│   │   ├── Middleware/               # Middleware
│   │   │   ├── AuthMiddleware.php
│   │   │   ├── AdminMiddleware.php
│   │   │   └── CSRFMiddleware.php
│   │   └── Views/                    # Views (Templates)
│   │       ├── auth/
│   │       ├── products/
│   │       ├── orders/
│   │       ├── cart/
│   │       └── admin/
│   │
│   └── Shared/                        # Compartilhado
│       ├── Config/                   # Configurações
│       │   ├── Database.php
│       │   ├── Payment.php
│       │   └── Email.php
│       ├── Exceptions/               # Exceções personalizadas
│       │   ├── ValidationException.php
│       │   ├── NotFoundException.php
│       │   └── PaymentException.php
│       ├── Utils/                    # Utilitários
│       │   ├── Logger.php
│       │   ├── Validator.php
│       │   └── Sanitizer.php
│       └── Constants/                # Constantes
│           ├── UserRoles.php
│           ├── OrderStatus.php
│           └── PaymentStatus.php
│
├── public/                           # Arquivos públicos
│   ├── assets/                      # CSS, JS, Imagens
│   ├── uploads/                     # Uploads de usuários
│   └── index.php                    # Entry point
│
├── tests/                           # Testes
│   ├── Unit/                        # Testes unitários
│   ├── Integration/                 # Testes de integração
│   └── Feature/                     # Testes de funcionalidades
│
├── docs/                            # Documentação
├── config/                          # Configurações ambiente
└── database/                        # Migrações e seeds
```

## 🔄 Fluxo de Dados

### 1. Request HTTP
```
Client → Router → Controller → UseCase → Service → Repository → Database
```

### 2. Response HTTP
```
Database → Repository → Service → UseCase → Controller → View → Client
```

## 🎯 Princípios SOLID Aplicados

### **S** - Single Responsibility Principle
- Cada classe tem uma única responsabilidade
- Controllers apenas lidam com HTTP
- Services contêm lógica de negócio
- Repositories apenas acessam dados

### **O** - Open/Closed Principle
- Sistema aberto para extensão
- Fechado para modificação
- Interfaces para novos gateways de pagamento

### **L** - Liskov Substitution Principle
- Implementações podem substituir interfaces
- PaymentGatewayInterface → StripeGateway

### **I** - Interface Segregation Principle
- Interfaces específicas e coesas
- UserRepositoryInterface vs ProductRepositoryInterface

### **D** - Dependency Inversion Principle
- Dependências injetadas via constructor
- High-level modules não dependem de low-level

## 🔐 Segurança Implementada

1. **Injeção de SQL**: Prepared statements
2. **XSS**: Sanitização de inputs
3. **CSRF**: Tokens em formulários
4. **Autenticação**: JWT ou Sessions seguras
5. **Autorização**: RBAC (Role-Based Access Control)
6. **Validação**: Input validation em todas as camadas

## 📊 Performance

1. **Cache Redis**: Para dados frequentes
2. **Lazy Loading**: Carregar apenas necessário
3. **Query Optimization**: Índices e joins otimizados
4. **Connection Pool**: Reutilizar conexões BD
5. **CDN**: Para assets estáticos

## 🧪 Testes

1. **Unit Tests**: Para Services e UseCases
2. **Integration Tests**: Para Repositories
3. **Feature Tests**: Para fluxos completos
4. **E2E Tests**: Para interface do usuário

---

Esta arquitetura garante:
✅ **Escalabilidade**: Fácil adicionar novas features
✅ **Manutenibilidade**: Código organizado e documentado
✅ **Testabilidade**: Cada componente pode ser testado isoladamente
✅ **Segurança**: Múltiplas camadas de proteção
✅ **Performance**: Otimizado para alta carga
