<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Braziliana - E-commerce Internacional' ?></title>
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

            --bg-gradient: #f6f8fb;
            --surface-gradient: #ffffff;
            --primary-gradient: #0b1f3a;
            --primary-btn-gradient: #0b1f3a;
            --danger-gradient: #ef4444;
            --info-gradient: #38bdf8;

            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 18px;

            --navbar-height: 76px;

            --shadow-sm: 0 6px 18px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 10px 28px rgba(15, 23, 42, 0.10);
            --shadow-lg: 0 16px 44px rgba(15, 23, 42, 0.12);

            --header-surface: rgba(255, 255, 255, 0.86);
            --header-border: rgba(148, 163, 184, 0.35);
            --footer-bg: #0b1f3a;
            --footer-border: rgba(255, 255, 255, 0.10);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            color: var(--primary-color-2) !important;
        }

        .navbar-brand .site-logo {
            max-height: 38px;
            width: auto;
            object-fit: contain;
            display: inline-block;
        }

        .site-footer .site-logo {
            max-height: 32px;
            width: auto;
            object-fit: contain;
            display: inline-block;
            filter: brightness(1.08);
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
            background: var(--primary-color);
            border: 1px solid rgba(11, 31, 58, 0.22);
            box-shadow: var(--shadow-sm);
        }

        .navbar .btn.btn-outline-danger {
            border-color: rgba(239, 68, 68, 0.55);
            color: rgba(239, 68, 68, 0.95);
        }

        .navbar .btn.btn-outline-danger:hover {
            background: rgba(239, 68, 68, 0.10);
            border-color: rgba(239, 68, 68, 0.55);
            color: #fff;
            box-shadow: var(--shadow-sm);
        }

        .navbar .cart-badge {
            background: var(--danger-color) !important;
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
            transition: background-color 0.2s ease;
        }

        .site-footer .social-link:hover,
        .site-footer .social-link:focus,
        .site-footer .social-link:active {
            text-decoration: none;
        }

        .site-footer .social-link:hover {
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
            overflow-x: hidden;
        }

        body {
            padding-top: var(--navbar-height) !important; /* Altura do navbar */
            margin: 0 !important;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg-gradient);
            color: #0f172a;
            overflow-x: hidden;
        }

        main {
            flex: 1 0 auto;
            width: 100%;
        }

        footer.site-footer {
            flex-shrink: 0;
        }

        img,
        svg,
        video,
        canvas {
            max-width: 100%;
            height: auto;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
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
            transform: none;
            box-shadow: none;
        }

        .btn:not(:disabled):not(.disabled):active {
            transform: none;
            box-shadow: none;
        }

        .btn-primary {
            border: 1px solid rgba(11, 31, 58, 0.22);
            background: var(--primary-color);
            box-shadow: none;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0a1a31;
            border-color: rgba(11, 31, 58, 0.28);
            box-shadow: none;
        }

        .btn-success {
            border: 1px solid rgba(16, 185, 129, 0.28);
            background: rgba(16, 185, 129, 0.12);
            color: rgba(6, 78, 59, 1);
        }

        .btn-info {
            border: 1px solid rgba(56, 189, 248, 0.35);
            background: rgba(56, 189, 248, 0.12);
            color: rgba(11, 31, 58, 1);
        }

        .btn-warning {
            border: 1px solid rgba(245, 158, 11, 0.35);
            background: rgba(245, 158, 11, 0.14);
            color: rgba(124, 45, 18, 1);
        }

        .btn-secondary {
            border: 1px solid rgba(148, 163, 184, 0.55);
            background: rgba(255, 255, 255, 0.8);
            color: #0f172a;
        }

        .btn-dark {
            border: none;
            background: #0b1f3a;
            color: #ffffff;
        }

        .btn-danger {
            border: 1px solid rgba(239, 68, 68, 0.35);
            background: rgba(239, 68, 68, 0.12);
            color: rgba(185, 28, 28, 1);
        }

        .btn-outline-primary {
            border-color: rgba(11, 31, 58, 0.28);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: rgba(11, 31, 58, 0.06);
            border-color: rgba(11, 31, 58, 0.28);
            color: var(--primary-color);
        }

        .btn-outline-danger:hover {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.45);
            color: rgba(185, 28, 28, 1);
        }

        .btn-outline-info:hover {
            background: rgba(56, 189, 248, 0.10);
            border-color: rgba(56, 189, 248, 0.45);
            color: rgba(11, 31, 58, 1);
        }

        .btn-outline-success:hover {
            background: rgba(16, 185, 129, 0.10);
            border-color: rgba(16, 185, 129, 0.45);
            color: rgba(6, 78, 59, 1);
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
            transition: none;
            border: none;
            height: 100%;
        }
        
        .feature-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .product-card {
            transition: none;
            border: none;
            overflow: hidden;
        }
        
        .product-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            transition: none;
        }
        
        .product-card:hover .product-image {
            transform: none;
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
            transition: none;
        }
        
        .stats-card:hover {
            transform: none;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin: 15px 0;
        }
        
        .cta-button {
            background: var(--primary-color);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: filter 0.2s ease, background-color 0.2s ease;
            border: none;
        }
        
        .cta-button:hover {
            filter: brightness(1.03);
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
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .floating-cart button:hover {
            box-shadow: 0 8px 30px rgba(37, 99, 235, 0.6);
        }
        
        .user-menu {
            position: relative;
        }
        
        .navbar .user-menu .user-avatar {
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
            overflow: hidden;
            font-size: 0.85rem;
            line-height: 1;
        }

        .navbar .user-menu .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Mobile + Tablet: navbar usable, avoid overflow, better tap targets */
        @media (max-width: 991.98px) {
            :root {
                --navbar-height: 64px;
            }

            body {
                padding-top: var(--navbar-height) !important;
            }

            .navbar .container {
                padding-left: 12px;
                padding-right: 12px;
            }

            .navbar-brand {
                font-size: 1.2rem;
            }

            .navbar-toggler {
                border-radius: 12px;
                padding: 0.5rem 0.65rem;
            }

            .navbar-collapse {
                margin-top: 10px;
                padding: 14px;
                border-radius: var(--radius-md);
                background: rgba(255, 255, 255, 0.95);
                border: 1px solid rgba(148, 163, 184, 0.35);
                box-shadow: var(--shadow-md);
                max-height: calc(100vh - var(--navbar-height) - 16px);
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }

            .navbar-collapse .navbar-nav {
                width: 100%;
            }

            .navbar-collapse .navbar-nav.mx-auto {
                margin: 0 !important;
            }

            .navbar-collapse .navbar-nav.mx-auto .nav-item {
                width: 100%;
            }

            .navbar-collapse .navbar-nav.mx-auto .nav-link {
                width: 100%;
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 0.85rem 0.95rem;
                border-radius: 14px;
                background: rgba(15, 23, 42, 0.03);
                border: 1px solid rgba(148, 163, 184, 0.26);
            }

            .navbar-collapse .navbar-nav.mx-auto .nav-link i {
                width: 18px;
                text-align: center;
                opacity: 0.9;
            }

            .navbar-collapse .navbar-nav.mx-auto .nav-link:hover {
                background: rgba(29, 78, 216, 0.10);
                border-color: rgba(29, 78, 216, 0.22);
            }

            /* Actions section (currency/login/cart) */
            .navbar-collapse > .navbar-nav.align-items-center {
                margin-top: 14px;
                padding-top: 14px;
                border-top: 1px solid rgba(148, 163, 184, 0.25);
                align-items: stretch !important;
                gap: 10px;
            }

            .navbar-collapse > .navbar-nav.align-items-center .nav-item,
            .navbar-collapse > .navbar-nav.align-items-center .nav-link,
            .navbar-collapse > .navbar-nav.align-items-center .btn {
                width: 100%;
            }

            .navbar-collapse > .navbar-nav.align-items-center .nav-link {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0.85rem 0.95rem;
                border-radius: 14px;
                background: rgba(15, 23, 42, 0.03);
                border: 1px solid rgba(148, 163, 184, 0.26);
            }

            .navbar-collapse > .navbar-nav.align-items-center .dropdown.me-3 {
                margin-right: 0 !important;
            }

            .navbar-collapse > .navbar-nav.align-items-center .dropdown-menu {
                width: 100%;
            }

            .navbar-collapse > .navbar-nav.align-items-center .btn {
                padding: 0.85rem 0.95rem;
                border-radius: 14px;
            }

            .navbar-collapse > .navbar-nav.align-items-center .btn.btn-primary,
            .navbar-collapse > .navbar-nav.align-items-center .btn.btn-outline-danger {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }

            .navbar-collapse > .navbar-nav.align-items-center .nav-link.position-relative {
                justify-content: center;
                gap: 10px;
            }

            .navbar-collapse > .navbar-nav.align-items-center .cart-badge {
                position: relative;
                top: auto;
                right: auto;
                margin-left: 8px;
            }

            .navbar .nav-link {
                padding: 0.75rem 0.9rem;
                border-radius: 12px;
            }

            .navbar .dropdown-menu {
                position: static;
                float: none;
                width: 100%;
                box-shadow: none;
                border-radius: 12px;
            }

            .navbar-collapse .btn {
                width: 100%;
            }

            .floating-cart {
                bottom: 16px;
                right: 16px;
            }

            .floating-cart button {
                width: 54px;
                height: 54px;
            }
        }

        @media (max-width: 575.98px) {
            .section-title {
                font-size: 1.75rem;
            }

            .hero-section {
                padding: 48px 0 30px;
                padding-top: calc(86px + var(--navbar-height)) !important;
            }
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
            <?php
            $siteLogo = '';
            try {
                $pdo = \Config\Database::getConnection();
                $raw = '';
                $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
                foreach ($tablesToTry as $t) {
                    if ($raw !== '') break;
                    try {
                        $stmtT = $pdo->prepare('SHOW TABLES LIKE ?');
                        $stmtT->execute([$t]);
                        if (!$stmtT->fetchColumn()) {
                            continue;
                        }

                        $stmtCols = $pdo->query('DESCRIBE ' . $t);
                        $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
                        if (!is_array($cols)) {
                            $cols = [];
                        }

                        // schema categoria+chave+valor
                        if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                            $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                            if ($valCol !== '') {
                                $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                                $stmt->execute(['layout', 'logo']);
                                $raw = (string) ($stmt->fetchColumn() ?: '');
                                if ($raw !== '') break;
                            }
                        }

                        // schema key/value
                        $keyCol = '';
                        if (in_array('chave', $cols, true)) $keyCol = 'chave';
                        elseif (in_array('key', $cols, true)) $keyCol = 'key';
                        elseif (in_array('nome', $cols, true)) $keyCol = 'nome';
                        elseif (in_array('config_key', $cols, true)) $keyCol = 'config_key';
                        $valCol = '';
                        if (in_array('valor', $cols, true)) $valCol = 'valor';
                        elseif (in_array('value', $cols, true)) $valCol = 'value';
                        elseif (in_array('conteudo', $cols, true)) $valCol = 'conteudo';
                        if ($keyCol !== '' && $valCol !== '') {
                            $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                            $stmt->execute(['layout_logo']);
                            $raw = (string) ($stmt->fetchColumn() ?: '');
                            if ($raw !== '') break;
                        }

                        // schema single_row (coluna direta)
                        if (in_array('layout_logo', $cols, true)) {
                            $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                            $stmt2 = $pdo->query('SELECT layout_logo AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                            $raw = (string) ($stmt2->fetchColumn() ?: '');
                            if ($raw !== '') break;
                        }
                    } catch (\Exception $e) {
                    }
                }

                $siteLogo = is_string($raw) ? trim($raw) : '';
            } catch (\Exception $e) {
                $siteLogo = '';
            }
            ?>
            <a class="navbar-brand fw-bold" href="/">
                <?php if (!empty($siteLogo)): ?>
                    <img src="<?= htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Braziliana" class="site-logo">
                <?php else: ?>
                    <i class="fas fa-globe-americas text-primary"></i>
                <?php endif; ?>
                <?php if (!empty($siteLogo)): ?>
                    <span class="visually-hidden">Braziliana</span>
                <?php else: ?>
                    Braziliana
                <?php endif; ?>
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
                    <li class="nav-item">
                        <a class="nav-link" href="/assessoria"><i class="fas fa-magic"></i> Redirecionamento</a>
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
                    $usuarioAvatar = $isLoggedIn ? ($_SESSION['usuario_avatar'] ?? null) : null;
                    if ($isLoggedIn && (empty($usuarioLogado) || !is_string($usuarioLogado))) {
                        try {
                            $uModel = new \App\Models\Usuario();
                            $u = $uModel->find($_SESSION['usuario_id'] ?? 0);
                            if (is_array($u)) {
                                $nameCandidates = ['nome', 'name', 'full_name', 'fullname', 'usuario_nome'];
                                foreach ($nameCandidates as $c) {
                                    if (!empty($u[$c]) && is_string($u[$c])) {
                                        $usuarioLogado = $u[$c];
                                        $_SESSION['usuario_nome'] = $usuarioLogado;
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                        }
                    }
                    if ($isLoggedIn && (empty($usuarioAvatar) || !is_string($usuarioAvatar))) {
                        try {
                            $uModel = new \App\Models\Usuario();
                            $u = $uModel->find($_SESSION['usuario_id'] ?? 0);
                            if (is_array($u)) {
                                $avatarCandidates = ['avatar', 'foto_perfil', 'imagem_perfil', 'foto', 'avatar_url', 'avatarUrl', 'profile_image', 'profileImage', 'foto_url'];
                                foreach ($avatarCandidates as $c) {
                                    if (!empty($u[$c]) && is_string($u[$c])) {
                                        $usuarioAvatar = $u[$c];
                                        $_SESSION['usuario_avatar'] = $usuarioAvatar;
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                        }
                    }
                    $usuarioPerfil = $isLoggedIn ? ($_SESSION['usuario_perfil'] ?? 'cliente') : 'cliente';
                    $totalItens = isset($_SESSION['carrinho']) ? array_sum(array_column($_SESSION['carrinho'], 'quantidade')) : 0;
                    ?>
                    
                    <?php if ($isLoggedIn): ?>
                        <!-- Menu Usuário Logado -->
                        <li class="nav-item dropdown user-menu">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?php if (!empty($usuarioAvatar) && is_string($usuarioAvatar)): ?>
                                        <img src="<?= htmlspecialchars($usuarioAvatar) ?>" alt="<?= htmlspecialchars($usuarioLogado) ?>">
                                    <?php else: ?>
                                        <?= strtoupper(substr($usuarioLogado, 0, 2)) ?>
                                    <?php endif; ?>
                                </div>
                                <span class="d-none d-md-inline"><?= htmlspecialchars($usuarioLogado) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/minha-conta"><i class="fas fa-tachometer-alt"></i> Minha Conta</a></li>
                                <?php if ($usuarioPerfil === 'representante'): ?>
                                    <li><a class="dropdown-item" href="/meu-painel"><i class="fas fa-chart-line"></i> Meu Painel</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="/meus-pedidos"><i class="fas fa-shopping-bag"></i> Meus Pedidos</a></li>
                                <li><a class="dropdown-item" href="/meus-dados"><i class="fas fa-user"></i> Meus Dados</a></li>
                                <?php if (in_array($usuarioPerfil, ['admin', 'vendedor', 'suporte', 'redirecionador'], true)): ?>
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
                    <?php
                    $footerLogo = '';
                    try {
                        $pdo = $pdo ?? \Config\Database::getConnection();
                        $rawFooter = '';
                        $tablesToTryFooter = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
                        foreach ($tablesToTryFooter as $t) {
                            if ($rawFooter !== '') break;
                            try {
                                $stmtT = $pdo->prepare('SHOW TABLES LIKE ?');
                                $stmtT->execute([$t]);
                                if (!$stmtT->fetchColumn()) {
                                    continue;
                                }

                                $stmtCols = $pdo->query('DESCRIBE ' . $t);
                                $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
                                if (!is_array($cols)) {
                                    $cols = [];
                                }

                                if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                                    $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                                    if ($valCol !== '') {
                                        $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                                        $stmt->execute(['layout', 'logo_footer']);
                                        $rawFooter = (string) ($stmt->fetchColumn() ?: '');
                                        if ($rawFooter !== '') break;
                                    }
                                }

                                $keyCol = '';
                                if (in_array('chave', $cols, true)) $keyCol = 'chave';
                                elseif (in_array('key', $cols, true)) $keyCol = 'key';
                                elseif (in_array('nome', $cols, true)) $keyCol = 'nome';
                                elseif (in_array('config_key', $cols, true)) $keyCol = 'config_key';
                                $valCol = '';
                                if (in_array('valor', $cols, true)) $valCol = 'valor';
                                elseif (in_array('value', $cols, true)) $valCol = 'value';
                                elseif (in_array('conteudo', $cols, true)) $valCol = 'conteudo';
                                if ($keyCol !== '' && $valCol !== '') {
                                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                                    $stmt->execute(['layout_logo_footer']);
                                    $rawFooter = (string) ($stmt->fetchColumn() ?: '');
                                    if ($rawFooter !== '') break;
                                }

                                if (in_array('layout_logo_footer', $cols, true)) {
                                    $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                                    $stmt2 = $pdo->query('SELECT layout_logo_footer AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                                    $rawFooter = (string) ($stmt2->fetchColumn() ?: '');
                                    if ($rawFooter !== '') break;
                                }
                            } catch (\Exception $e) {
                            }
                        }

                        $footerLogo = is_string($rawFooter) ? trim($rawFooter) : '';
                    } catch (\Exception $e) {
                        $footerLogo = '';
                    }
                    $effectiveFooterLogo = $footerLogo !== '' ? $footerLogo : $siteLogo;
                    ?>
                    <h5 class="mb-3">
                        <?php if (!empty($effectiveFooterLogo)): ?>
                            <img src="<?= htmlspecialchars($effectiveFooterLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Braziliana" class="site-logo">
                        <?php else: ?>
                            <i class="fas fa-globe-americas"></i>
                        <?php endif; ?>
                        <?php if (!empty($effectiveFooterLogo)): ?>
                            <span class="visually-hidden">Braziliana</span>
                        <?php else: ?>
                            Braziliana
                        <?php endif; ?>
                    </h5>
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
                    <p class="text-muted small mb-0">&copy; 2026 Braziliana. Todos os direitos reservados.</p>
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

        const __USD_BRL_RATE__ = <?php
        try {
            $svc = new \App\Services\PedidoManualService();
            echo json_encode((float) $svc->getTaxaConversaoUSDBRL());
        } catch (\Exception $e) {
            echo '5.5';
        }
        ?>;
        
        // Variáveis globais
        window.CurrencyConverter = {
            currentCurrency: 'USD',
            exchangeRates: {
                BRL: __USD_BRL_RATE__ || 5.50,
                USD: 1.00
            },
            
            init: function() {
                console.log('=== INICIANDO SISTEMA DE CONVERSÃO INLINE ===');
                
                // Recuperar moeda salva
                this.currentCurrency = localStorage.getItem('selected_currency') || 'BRL';
                console.log('Moeda inicial:', this.currentCurrency);

                // Garantir que o header reflita a moeda inicial antes de atualizar preços
                this.updateCurrencyDisplay();
                
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
                
                const el = e.target && e.target.closest ? e.target.closest('.currency-selector') : null;
                const newCurrency = el ? el.getAttribute('data-currency') : e.target.getAttribute('data-currency');
                console.log('=== CLIQUE NO SELETOR ===');
                console.log('Moeda selecionada:', newCurrency);
                console.log('Moeda atual:', this.currentCurrency);

                if (!newCurrency) {
                    console.log('Moeda não encontrada no clique');
                    return;
                }
                
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
                    const rawAttr = element.getAttribute('data-original-value');
                    const isFrete = (element.classList.contains('frete-value') || element.id === 'frete');

                    // Se já está grátis, não sobrescreve.
                    const currentText = String(element.textContent || '').trim().toLowerCase();
                    if (isFrete && (currentText === 'frete grátis' || currentText === 'frete gratis')) {
                        element.textContent = 'Frete grátis';
                        return;
                    }

                    const originalValue = parseFloat(rawAttr);
                    if (isFrete && (rawAttr === null || rawAttr === '' || isNaN(originalValue))) {
                        return;
                    }

                    if (!isNaN(originalValue)) {
                        if (isFrete && originalValue <= 0) {
                            element.textContent = 'Frete grátis';
                            return;
                        }
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
