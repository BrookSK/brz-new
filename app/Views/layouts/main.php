<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Braziliana Shop - E-commerce Internacional' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #f59e0b;
            --success-color: #10b981;
            --danger-color: #ef4444;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        /* Header fixo - FORÇAR FIXO ACIMA DE TUDO */
        .navbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 9999 !important; /* Máima prioridade */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background: white !important;
        }
        
        /* Sobrescrever qualquer classe sticky do Bootstrap */
        .navbar.sticky-top {
            position: fixed !important;
            top: 0 !important;
            z-index: 9999 !important;
        }
        
        /* Garantir que body tenha espaçamento correto */
        body {
            padding-top: 76px !important; /* Altura do navbar */
            margin: 0 !important;
        }
        
        /* Garantir que main fique abaixo */
        main {
            margin-top: 0 !important;
            padding-top: 20px !important;
            position: relative !important;
        }
        
        /* Hero section ajustado */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0 40px;
            margin-top: 0 !important;
            padding-top: 100px !important;
        }
        
        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .product-card {
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
        }
        
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin: 15px 0;
        }
        
        .cta-button {
            background: var(--accent-color);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
        }
        
        .cta-button:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
            color: white;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .section-subtitle {
            font-size: 1.1rem;
            color: var(--secondary-color);
            margin-bottom: 3rem;
        }
        
        .floating-cart {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        .floating-cart button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 20px rgba(37, 99, 235, 0.4);
            transition: all 0.3s ease;
        }
        
        .floating-cart button:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 30px rgba(37, 99, 235, 0.6);
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0 40px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .floating-cart {
                bottom: 20px;
                right: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <i class="fas fa-globe-americas text-primary"></i> Braziliana Shop
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="fas fa-home"></i> Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/produtos"><i class="fas fa-box"></i> Produtos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/como-funciona"><i class="fas fa-question-circle"></i> Como Funciona</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/faq"><i class="fas fa-comments"></i> FAQ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/contato"><i class="fas fa-envelope"></i> Contato</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav align-items-center">
                    <!-- Seletor de Moeda -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="currencyDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-coins me-1"></i>
                            <span id="current-currency">BRL</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item currency-selector" href="#" data-currency="BRL">
                                <i class="fas fa-dollar-sign me-2"></i> Real (BRL)
                            </a></li>
                            <li><a class="dropdown-item currency-selector" href="#" data-currency="USD">
                                <i class="fas fa-dollar-sign me-2"></i> Dólar (USD)
                            </a></li>
                        </ul>
                    </li>
                    
                    <?php
                    $isLoggedIn = isset($_SESSION['logado']) && $_SESSION['logado'] === true;
                    $usuarioLogado = $isLoggedIn ? $_SESSION['usuario_nome'] : null;
                    $usuarioPerfil = $isLoggedIn ? ($_SESSION['usuario_perfil'] ?? 'cliente') : 'cliente';
                    $totalItens = isset($_SESSION['carrinho']) ? array_sum(array_column($_SESSION['carrinho'], 'quantidade')) : 0;
                    ?>
                    
                    <?php if ($isLoggedIn): ?>
                        <!-- Menu Usuário Logado -->
                        <li class="nav-item dropdown user-menu">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?= strtoupper(substr($usuarioLogado, 0, 2)) ?>
                                </div>
                                <span class="d-none d-md-inline"><?= htmlspecialchars($usuarioLogado) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/minha-conta"><i class="fas fa-tachometer-alt"></i> Minha Conta</a></li>
                                <li><a class="dropdown-item" href="/meus-pedidos"><i class="fas fa-shopping-bag"></i> Meus Pedidos</a></li>
                                <li><a class="dropdown-item" href="/meus-dados"><i class="fas fa-user"></i> Meus Dados</a></li>
                                <?php if ($usuarioPerfil === 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-primary" href="/admin/dashboard"><i class="fas fa-cog"></i> Painel Admin</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Botões Login/Cadastro -->
                        <li class="nav-item">
                            <a class="nav-link" href="/login"><i class="fas fa-sign-in-alt"></i> Entrar</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm ms-2" href="/register">Cadastrar</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-outline-danger btn-sm ms-2" href="/loginadmin">
                                <i class="fas fa-user-shield"></i> Admin
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Carrinho -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="/carrinho">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($totalItens > 0): ?>
                                <span class="cart-badge"><?= $totalItens ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Floating Cart (Mobile) -->
    <?php if ($totalItens > 0): ?>
    <div class="floating-cart d-lg-none">
        <a href="/carrinho" class="text-decoration-none">
            <button>
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge"><?= $totalItens ?></span>
            </button>
        </a>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <div class="container">
                    <?= $_SESSION['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3"><i class="fas fa-globe-americas"></i> Braziliana Shop</h5>
                    <p class="text-muted">Sua plataforma confiável para importação de produtos dos EUA com logística completa e transparente.</p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 mb-4">
                    <h6 class="mb-3">Links Úteis</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/" class="text-muted text-decoration-none">Início</a></li>
                        <li class="mb-2"><a href="/produtos" class="text-muted text-decoration-none">Produtos</a></li>
                        <li class="mb-2"><a href="/como-funciona" class="text-muted text-decoration-none">Como Funciona</a></li>
                        <li class="mb-2"><a href="/faq" class="text-muted text-decoration-none">FAQ</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 mb-4">
                    <h6 class="mb-3">Atendimento</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/contato" class="text-muted text-decoration-none">Contato</a></li>
                        <li class="mb-2"><a href="/suporte" class="text-muted text-decoration-none">Suporte</a></li>
                        <li class="mb-2"><a href="/rastreamento" class="text-muted text-decoration-none">Rastrear Pedido</a></li>
                        <li class="mb-2"><a href="/politicas" class="text-muted text-decoration-none">Políticas</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 mb-4">
                    <h6 class="mb-3">Newsletter</h6>
                    <p class="text-muted small">Receba ofertas exclusivas e novidades</p>
                    <form class="mt-3">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Seu e-mail">
                            <button class="btn btn-primary" type="submit">Assinar</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <hr class="my-4 border-secondary">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted small mb-0">&copy; 2024 Braziliana Shop. Todos os direitos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="/politica-privacidade" class="text-muted small text-decoration-none me-3">Política de Privacidade</a>
                    <a href="/termos-uso" class="text-muted small text-decoration-none">Termos de Uso</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- SISTEMA DE CONVERSÃO EXCLUSIVO -->
    <script>
        // Incluir o código diretamente para evitar problemas de caminho
        console.log('Carregando sistema de conversão inline...');
        
        // Variáveis globais
        window.CurrencyConverter = {
            currentCurrency: 'BRL',
            exchangeRates: {
                BRL: 5.50,
                USD: 1.00
            },
            
            init: function() {
                console.log('=== INICIANDO SISTEMA DE CONVERSÃO INLINE ===');
                
                // Recuperar moeda salva
                this.currentCurrency = localStorage.getItem('selected_currency') || 'BRL';
                console.log('Moeda inicial:', this.currentCurrency);
                
                // Inicializar seletor
                this.initSelector();
                
                // Forçar atualização inicial
                setTimeout(() => {
                    this.updateAllPrices();
                }, 100);
            },
            
            initSelector: function() {
                console.log('Inicializando seletor de moeda...');
                
                // Remover eventos antigos
                const selectors = document.querySelectorAll('.currency-selector');
                console.log('Seletores encontrados:', selectors.length);
                
                selectors.forEach((selector, index) => {
                    console.log(`Seletor [${index}]:`, selector.getAttribute('data-currency'));
                    
                    // Adicionar novo evento
                    selector.addEventListener('click', (e) => this.handleCurrencyClick(e));
                    
                    // Fallback
                    selector.onclick = (e) => this.handleCurrencyClick(e);
                });
            },
            
            handleCurrencyClick: function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const newCurrency = e.target.getAttribute('data-currency');
                console.log('=== CLIQUE NO SELETOR ===');
                console.log('Moeda selecionada:', newCurrency);
                console.log('Moeda atual:', this.currentCurrency);
                
                if (newCurrency !== this.currentCurrency) {
                    this.currentCurrency = newCurrency;
                    localStorage.setItem('selected_currency', newCurrency);
                    console.log('Moeda atualizada para:', this.currentCurrency);
                    
                    this.updateCurrencyDisplay();
                    this.updateAllPrices();
                    this.showNotification(newCurrency);
                } else {
                    console.log('Moeda já é a mesma');
                }
            },
            
            updateCurrencyDisplay: function() {
                const span = document.getElementById('current-currency');
                if (span) {
                    span.textContent = this.currentCurrency;
                    console.log('Display atualizado para:', this.currentCurrency);
                }
            },
            
            updateAllPrices: function() {
                console.log('=== ATUALIZANDO TODOS OS PREÇOS ===');
                console.log('Moeda:', this.currentCurrency);
                console.log('Taxa:', this.exchangeRates[this.currentCurrency]);
                
                const symbol = this.currentCurrency === 'BRL' ? 'R$' : '$';
                const rate = this.exchangeRates[this.currentCurrency];
                
                // Atualizar elementos com data-original-price
                const elements = document.querySelectorAll('[data-original-price]');
                console.log('Elementos com data-original-price:', elements.length);
                
                elements.forEach((element, index) => {
                    const originalPrice = parseFloat(element.getAttribute('data-original-price'));
                    if (!isNaN(originalPrice)) {
                        const convertedPrice = originalPrice * rate;
                        const newPrice = `${symbol} ${convertedPrice.toFixed(2).replace('.', ',')}`;
                        element.textContent = newPrice;
                        console.log(`[${index}] ${originalPrice} → ${newPrice}`);
                    }
                });
                
                // Atualizar elementos .currency
                const currencyElements = document.querySelectorAll('.currency');
                currencyElements.forEach(element => {
                    element.textContent = this.currentCurrency;
                });
                
                // Atualizar carrinho
                this.updateCart();
                
                console.log('=== ATUALIZAÇÃO CONCLUÍDA ===');
            },
            
            updateCart: function() {
                console.log('Atualizando carrinho...');
                
                const symbol = this.currentCurrency === 'BRL' ? 'R$' : '$';
                const rate = this.exchangeRates[this.currentCurrency];
                
                // Atualizar valores do carrinho
                const cartValues = document.querySelectorAll('.cart-currency');
                cartValues.forEach(element => {
                    const originalValue = parseFloat(element.getAttribute('data-original-value'));
                    if (!isNaN(originalValue)) {
                        const convertedValue = originalValue * rate;
                        element.textContent = `${symbol} ${convertedValue.toFixed(2).replace('.', ',')}`;
                    }
                });
                
                // Atualizar itens do carrinho
                const itemPrices = document.querySelectorAll('.cart-item-price, .cart-item-subtotal, .cart-item-unit');
                itemPrices.forEach(element => {
                    const originalPrice = parseFloat(element.getAttribute('data-original-price'));
                    if (!isNaN(originalPrice)) {
                        const convertedPrice = originalPrice * rate;
                        element.textContent = `${symbol} ${convertedPrice.toFixed(2).replace('.', ',')}`;
                    }
                });
            },
            
            showNotification: function(currency) {
                const name = currency === 'BRL' ? 'Real Brasileiro' : 'Dólar Americano';
                console.log('Moeda alterada para:', name);
                
                // Criar notificação
                const notification = document.createElement('div');
                notification.className = 'alert alert-info alert-dismissible fade show position-fixed';
                notification.style.cssText = 'top: 70px; right: 20px; z-index: 9999; min-width: 250px;';
                notification.innerHTML = `
                    <i class="fas fa-coins me-2"></i>
                    Moeda alterada para ${name}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 3000);
            },
            
            // Forçar atualização manual
            forceUpdate: function() {
                console.log('=== FORÇANDO ATUALIZAÇÃO MANUAL ===');
                this.updateAllPrices();
            }
        };
        
        // Inicializar quando o DOM estiver pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                window.CurrencyConverter.init();
            });
        } else {
            window.CurrencyConverter.init();
        }
        
        // Forçar atualização quando a página carregar
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.CurrencyConverter.forceUpdate();
            }, 500);
        });
        
        // Disponibilizar globalmente
        window.forceCurrencyUpdate = () => {
            window.CurrencyConverter.forceUpdate();
        };
        
        console.log('Sistema de conversão inline carregado com sucesso!');
    </script>
    
    <script>
    // Verificar se jQuery foi carregado
        console.log('jQuery carregado:', typeof $ !== 'undefined');
        console.log('Swal carregado:', typeof Swal !== 'undefined');
        console.log('CurrencyConverter carregado:', typeof window.CurrencyConverter !== 'undefined');
    </script>
    
    <!-- Incluir Mini Carrinho DEPOIS dos scripts principais -->
    <?php include_once __DIR__ . '/mini-carrinho.php'; ?>
    
    <script>
        // Inicializar AOS (Animate On Scroll)
        AOS.init({
            duration: 800,
            once: true
        });
        
        // Animação suave ao rolar
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                
                // Verificar se o href não é apenas "#"
                if (href && href !== '#') {
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });
        
        // Verificar se mini carrinho existe
        if (typeof addToMiniCart === 'function') {
            console.log('Mini carrinho está disponível');
        } else {
            console.log('Mini carrinho não encontrado');
        }
        
        // Sistema antigo de moeda removido - usando sistema exclusivo
        console.log('Sistema de moedas antigo removido. Usando currency-converter.js');
    </script>
</html>
