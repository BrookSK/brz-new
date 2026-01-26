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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #0b1f3a;
            --primary-color-2: #1d4ed8;
            --secondary-color: #94a3b8;
            --accent-color: #38bdf8;
            --success-color: #10b981;
            --danger-color: #ef4444;

            --bg-gradient: linear-gradient(180deg, #f8fafc 0%, #eef2f7 45%, #ffffff 100%);
            --surface-gradient: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);
            --primary-gradient: linear-gradient(135deg, #0b1f3a 0%, #1d4ed8 55%, #e2e8f0 120%);
            --primary-btn-gradient: linear-gradient(135deg, #0b1f3a 0%, #1d4ed8 100%);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            --info-gradient: linear-gradient(135deg, #38bdf8 0%, #ffffff 100%);

            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 18px;

            --navbar-height: 76px;

            --shadow-sm: 0 6px 18px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 10px 28px rgba(15, 23, 42, 0.10);
            --shadow-lg: 0 16px 44px rgba(15, 23, 42, 0.12);

            --header-surface: rgba(255, 255, 255, 0.86);
            --header-border: rgba(148, 163, 184, 0.35);
            --footer-bg: linear-gradient(135deg, #0b1f3a 0%, #0f2b57 45%, #1d4ed8 110%);
            --footer-border: rgba(255, 255, 255, 0.10);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .navbar-brand i {
            color: var(--primary-color-2) !important;
        }

        .navbar .nav-link {
            color: rgba(15, 23, 42, 0.78) !important;
            font-weight: 600;
            padding: 0.55rem 0.75rem;
            border-radius: 999px;
            transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        .navbar .nav-link:hover {
            color: rgba(15, 23, 42, 0.92) !important;
            background: rgba(29, 78, 216, 0.10);
        }

        .navbar .dropdown-menu {
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow: var(--shadow-md);
        }

        .navbar .dropdown-item {
            font-weight: 600;
        }

        .navbar .btn.btn-primary {
            background: var(--primary-btn-gradient);
            border: none;
            box-shadow: var(--shadow-sm);
        }

        .navbar .btn.btn-outline-danger {
            border-color: rgba(239, 68, 68, 0.55);
            color: rgba(239, 68, 68, 0.95);
        }

        .navbar .btn.btn-outline-danger:hover {
            background: var(--danger-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: var(--shadow-sm);
        }

        .navbar .cart-badge {
            background: var(--danger-gradient) !important;
        }

        .site-footer {
            background: var(--footer-bg);
            border-top: 1px solid var(--footer-border);
        }

        .site-footer .text-muted {
            color: rgba(255, 255, 255, 0.72) !important;
        }

        .site-footer a.text-muted {
            color: rgba(255, 255, 255, 0.75) !important;
        }

        .site-footer a.text-muted:hover {
            color: rgba(255, 255, 255, 0.95) !important;
        }

        .site-footer .footer-link {
            color: rgba(255, 255, 255, 0.78);
            text-decoration: none;
        }

        .site-footer .footer-link:hover {
            color: rgba(255, 255, 255, 0.98);
            text-decoration: underline;
        }

        .site-footer .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            width: 44px;
            height: 44px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: rgba(255, 255, 255, 0.92);
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .site-footer .social-link:hover,
        .site-footer .social-link:focus,
        .site-footer .social-link:active {
            text-decoration: none;
        }

        .site-footer .social-link:hover {
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.16);
        }

        .site-footer .input-group .form-control {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.10);
            color: rgba(255, 255, 255, 0.92);
        }

        .site-footer .input-group .form-control::placeholder {
            color: rgba(255, 255, 255, 0.65);
        }

        .site-footer .input-group .btn {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: rgba(255, 255, 255, 0.95);
        }

        .site-footer .input-group .btn:hover {
            background: rgba(255, 255, 255, 0.26);
        }
        
        /* Header fixo - FORÇAR FIXO ACIMA DE TUDO */
        .navbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 9999 !important; /* Máima prioridade */
            box-shadow: var(--shadow-sm) !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background: var(--header-surface) !important;
            border-bottom: 1px solid var(--header-border) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        /* Sobrescrever qualquer classe sticky do Bootstrap */
        .navbar.sticky-top {
            position: fixed !important;
            top: 0 !important;
            z-index: 9999 !important;
        }
        
        /* Garantir que body tenha espaçamento correto */
        html {
            height: 100%;
        }

        body {
            padding-top: var(--navbar-height) !important; /* Altura do navbar */
            margin: 0 !important;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg-gradient);
            color: #0f172a;
        }

        section.bg-light {
            background: var(--surface-gradient) !important;
        }

        .card,
        .dropdown-menu,
        .modal-content,
        .form-control,
        .form-select,
        .btn {
            border-radius: var(--radius-md);
        }

        .card {
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow: var(--shadow-sm);
        }

        .shadow {
            box-shadow: var(--shadow-md) !important;
        }

        .shadow-sm {
            box-shadow: var(--shadow-sm) !important;
        }

        .shadow-lg {
            box-shadow: var(--shadow-lg) !important;
        }

        .feature-card:hover,
        .product-card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            background: rgba(255, 255, 255, 0.65);
            border-bottom: 1px solid rgba(148, 163, 184, 0.35);
        }

        .btn {
            font-weight: 600;
        }

        .btn:not(:disabled):not(.disabled):hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn:not(:disabled):not(.disabled):active {
            transform: translateY(0);
            box-shadow: none;
        }

        .btn-primary {
            border: none;
            background: var(--primary-btn-gradient);
            box-shadow: var(--shadow-sm);
            color: #ffffff;
        }

        .btn-primary:hover {
            filter: brightness(1.03);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            border: none;
            background: linear-gradient(135deg, #10b981 0%, #ecfdf5 100%);
            color: #064e3b;
        }

        .btn-info {
            border: none;
            background: var(--info-gradient);
            color: #0b1f3a;
        }

        .btn-warning {
            border: none;
            background: linear-gradient(135deg, #f59e0b 0%, #fff7ed 100%);
            color: #7c2d12;
        }

        .btn-secondary {
            border: 1px solid rgba(148, 163, 184, 0.55);
            background: rgba(255, 255, 255, 0.8);
            color: #0f172a;
        }

        .btn-dark {
            border: none;
            background: linear-gradient(135deg, #0b1f3a 0%, #0f172a 100%);
        }

        .btn-danger {
            border: none;
            background: var(--danger-gradient);
            color: #ffffff;
        }

        .btn-outline-primary {
            border-color: rgba(29, 78, 216, 0.45);
            color: #1d4ed8;
        }

        .btn-outline-primary:hover {
            background: var(--info-gradient);
            border-color: rgba(29, 78, 216, 0.35);
            color: #0b1f3a;
        }

        .btn-outline-danger:hover {
            background: var(--danger-gradient);
            border-color: rgba(239, 68, 68, 0.35);
            color: #ffffff;
        }

        .btn-outline-info:hover {
            background: var(--info-gradient);
            border-color: rgba(56, 189, 248, 0.35);
            color: #0b1f3a;
        }

        .btn-outline-success:hover {
            background: linear-gradient(135deg, #10b981 0%, #ecfdf5 100%);
            border-color: rgba(16, 185, 129, 0.35);
            color: #064e3b;
        }

        .btn-outline-secondary:hover {
            background: rgba(241, 245, 249, 0.9);
            border-color: rgba(148, 163, 184, 0.55);
            color: #0f172a;
        }

        .btn-outline-dark:hover {
            background: rgba(15, 23, 42, 0.08);
            border-color: rgba(15, 23, 42, 0.25);
            color: #0f172a;
        }
        
        /* Garantir que main fique abaixo */
        main {
            margin-top: 0 !important;
            padding-top: 20px !important;
            position: relative !important;
            flex: 1 0 auto;
        }
        
        /* Hero section ajustado */
        .hero-section {
            background: var(--primary-gradient);
            color: white;
            padding: 60px 0 40px;
            margin-top: calc(var(--navbar-height) * -1) !important;
            padding-top: calc(100px + var(--navbar-height)) !important;
        }
        
        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .product-card {
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
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
            background: var(--primary-btn-gradient);
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
            filter: brightness(1.03);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <i class="fas fa-globe-americas text-primary"></i> Braziliana Shop
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto gap-lg-1">
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
                
                <ul class="navbar-nav align-items-center gap-1">
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
    <footer class="site-footer text-light py-5 mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3"><i class="fas fa-globe-americas"></i> Braziliana Shop</h5>
                    <p class="text-muted">Sua plataforma confiável para importação de produtos dos EUA com logística completa e transparente.</p>
                    <div class="mt-3">
                        <a href="#" class="social-link me-2"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="social-link me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 mb-4">
                    <h6 class="mb-3">Links Úteis</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/" class="footer-link">Início</a></li>
                        <li class="mb-2"><a href="/produtos" class="footer-link">Produtos</a></li>
                        <li class="mb-2"><a href="/como-funciona" class="footer-link">Como Funciona</a></li>
                        <li class="mb-2"><a href="/faq" class="footer-link">FAQ</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 mb-4">
                    <h6 class="mb-3">Atendimento</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/contato" class="footer-link">Contato</a></li>
                        <li class="mb-2"><a href="/suporte" class="footer-link">Suporte</a></li>
                        <li class="mb-2"><a href="/rastreamento" class="footer-link">Rastrear Pedido</a></li>
                        <li class="mb-2"><a href="/politicas" class="footer-link">Políticas</a></li>
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
            
            <hr class="my-4" style="border-color: rgba(255,255,255,0.18);">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted small mb-0">&copy; 2026 Braziliana Shop. Todos os direitos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="/politica-privacidade" class="footer-link small me-3">Política de Privacidade</a>
                    <a href="/termos-uso" class="footer-link small">Termos de Uso</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
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
