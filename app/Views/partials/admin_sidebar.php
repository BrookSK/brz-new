<?php
// Menu lateral padrão para todas as páginas do admin
function renderAdminSidebar($activePage = '') {
    $perfil = '';
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $perfil = (string) ($_SESSION['usuario_perfil'] ?? '');
    } catch (\Exception $e) {
        $perfil = '';
    }
    $perfil = strtolower(trim($perfil));
    if ($perfil === '') {
        $perfil = 'cliente';
    }

    if ($perfil === 'representante') {
        $menuItems = [
            'rep_produtos' => ['icon' => 'fa-box', 'label' => 'Produtos', 'url' => '/admin/representante/produtos', 'roles' => ['representante']],
        ];

        echo '<button class="btn btn-primary admin-menu-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-expanded="false" aria-label="Abrir menu">
                <i class="fas fa-bars"></i>
              </button>';

        echo '<nav id="adminSidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/representante/produtos">
                    <div class="sidebar-brand-icon"><i class="fas fa-user-tie"></i></div>
                    <div class="sidebar-brand-text mx-3">Representante</div>
                </a>
                <ul class="nav flex-column">';

        foreach ($menuItems as $key => $item) {
            $activeClass = ($activePage === $key) ? 'active' : '';
            echo '<li class="nav-item">
                <a class="nav-link ' . $activeClass . '" href="' . $item['url'] . '">
                    <i class="fas fa-fw ' . $item['icon'] . '"></i>
                    <span>' . $item['label'] . '</span>
                </a>
            </li>';
        }

        echo '</ul>
                <hr class="sidebar-divider">
                <div class="nav-item">
                    <a class="nav-link" href="/">
                        <i class="fas fa-fw fa-home"></i>
                        <span>Voltar ao Site</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="/logout">
                        <i class="fas fa-fw fa-sign-out-alt"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </nav>';

        return;
    }

    $menuItems = [
        'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/admin/dashboard', 'roles' => ['admin','vendedor','suporte','redirecionador']],
        'produtos' => ['icon' => 'fas fa-box', 'label' => 'Produtos', 'url' => '/admin/produtos', 'roles' => ['admin','vendedor','suporte']],
        'variacoes' => ['icon' => 'fas fa-sliders-h', 'label' => 'Variações', 'url' => '/admin/variacoes', 'roles' => ['admin','vendedor','suporte']],
        'lojas' => ['icon' => 'fas fa-store', 'label' => 'Lojas', 'url' => '/admin/lojas', 'roles' => ['admin','vendedor','suporte']],
        'categorias' => ['icon' => 'fas fa-tags', 'label' => 'Categorias', 'url' => '/admin/categorias', 'roles' => ['admin','vendedor','suporte']],
        'pedidos' => ['icon' => 'fas fa-shopping-cart', 'label' => 'Pedidos', 'url' => '/admin/pedidos', 'roles' => ['admin','vendedor','suporte']],
        'pedidos-wp' => ['icon' => 'fab fa-wordpress', 'label' => 'Pedidos (WordPress)', 'url' => '/admin/pedidos-wp', 'roles' => ['admin','vendedor','suporte']],
        'tickets' => ['icon' => 'fas fa-life-ring', 'label' => 'Tickets', 'url' => '/admin/tickets', 'roles' => ['admin','suporte']],
        'pedidos-comissoes' => ['icon' => 'fas fa-percentage', 'label' => 'Minhas Comissões', 'url' => '/admin/pedidos/comissoes', 'roles' => ['admin','vendedor']],
        'estoque' => ['icon' => 'fas fa-warehouse', 'label' => 'Estoque', 'url' => '/admin/estoque', 'roles' => ['admin','vendedor','suporte']],
        'compras' => ['icon' => 'fas fa-shopping-basket', 'label' => 'Compras', 'url' => '/admin/estoque/compras', 'roles' => ['admin','vendedor']],
        'relatorios' => ['icon' => 'fas fa-file-pdf', 'label' => 'Relatórios', 'url' => '/admin/estoque/relatorios', 'roles' => ['admin','vendedor']],
        'remessa-internacional' => ['icon' => 'fas fa-globe-americas', 'label' => 'Remessa Internacional', 'url' => '/admin/remessa-internacional', 'roles' => ['admin','vendedor']],
        'remessa-correios' => ['icon' => 'fas fa-shipping-fast', 'label' => 'Remessa Correios', 'url' => '/admin/remessa-correios', 'roles' => ['admin','vendedor']],
        'remessa-stamps' => ['icon' => 'fas fa-plane', 'label' => 'Remessa Stamps (UPS)', 'url' => '/admin/remessa-stamps', 'roles' => ['admin','vendedor']],
        'usuarios' => ['icon' => 'fas fa-users', 'label' => 'Usuários', 'url' => '/admin/usuarios', 'roles' => ['admin','vendedor','suporte']],
        'pagamentos' => ['icon' => 'fas fa-credit-card', 'label' => 'Pagamentos', 'url' => '/admin/pagamentos', 'roles' => ['admin','vendedor']],
        'configuracoes' => ['icon' => 'fas fa-cog', 'label' => 'Configurações', 'url' => '/admin/configuracoes', 'roles' => ['admin']]
    ];

    $unreadTickets = 0;
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $adminUid = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($adminUid > 0 && in_array($perfil, ['admin', 'suporte'], true)) {
            $pdo = \Config\Database::getConnection();
            $stT = $pdo->query("SHOW TABLES LIKE 'support_ticket_views'");
            $hasViews = (bool) ($stT && $stT->fetchColumn());
            if ($hasViews) {
                $sqlUnread = "
                    SELECT COUNT(*)
                    FROM support_tickets t
                    INNER JOIN (
                        SELECT ticket_id, MAX(created_at) AS last_client_msg_at
                        FROM support_ticket_messages
                        WHERE autor_tipo = 'cliente'
                        GROUP BY ticket_id
                    ) m ON m.ticket_id = t.id
                    LEFT JOIN support_ticket_views v
                        ON v.ticket_id = t.id AND v.viewer_type = 'admin' AND v.viewer_user_id = ?
                    WHERE t.status = 'open'
                      AND (v.last_seen_at IS NULL OR m.last_client_msg_at > v.last_seen_at)
                ";
                $stU = $pdo->prepare($sqlUnread);
                $stU->execute([$adminUid]);
                $unreadTickets = (int) ($stU->fetchColumn() ?: 0);
            }
        }
    } catch (\Exception $e) {
        $unreadTickets = 0;
    }

    // Toggle mobile (collapse) - fica fixo no topo no mobile/tablet
    echo '<button class="btn btn-primary admin-menu-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-expanded="false" aria-label="Abrir menu">
            <i class="fas fa-bars"></i>
          </button>';

    echo '<nav id="adminSidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
        <div class="position-sticky pt-3">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                <div class="sidebar-brand-icon"><i class="fas fa-warehouse"></i></div>
                <div class="sidebar-brand-text mx-3">Braziliana Shop Admin</div>
            </a>
            <ul class="nav flex-column">';
            
            foreach ($menuItems as $key => $item) {
                $roles = isset($item['roles']) && is_array($item['roles']) ? $item['roles'] : [];
                if (!empty($roles) && !in_array($perfil, $roles, true)) {
                    continue;
                }
                $activeClass = ($activePage === $key) ? 'active' : '';
                $label = $item['label'];
                if ($key === 'tickets' && $unreadTickets > 0) {
                    $label .= ' <span class="badge bg-danger ms-2" style="background: rgba(239, 68, 68, 0.18) !important; border-color: rgba(239, 68, 68, 0.35) !important; color: #7f1d1d !important;">' . (int) $unreadTickets . '</span>';
                }
                echo '<li class="nav-item">
                    <a class="nav-link ' . $activeClass . '" href="' . $item['url'] . '">
                        <i class="fas fa-fw ' . $item['icon'] . '"></i>
                        <span>' . $label . '</span>
                    </a>
                </li>';
            }
            
            echo '</ul>
            <hr class="sidebar-divider">
            <div class="nav-item">
                <a class="nav-link" href="/">
                    <i class="fas fa-fw fa-home"></i>
                    <span>Voltar ao Site</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="/logout">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </div>
        </div>
    </nav>';
}

// Estilos CSS comuns para o menu lateral
function renderAdminSidebarStyles() {
    echo '<style>
        :root {
            --primary-color: #0b1f3a;
            --bg-surface: #f6f8fb;
            --radius-md: 14px;
            --shadow-sm: 0 6px 18px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 10px 28px rgba(15, 23, 42, 0.10);
        }

        body {
            background: var(--bg-surface);
            color: #0f172a;
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
            border: 0;
            box-shadow: var(--shadow-sm);
        }

        .shadow {
            box-shadow: var(--shadow-md) !important;
        }

        .btn-primary {
            border: none;
            background: var(--primary-color);
            color: #ffffff;
        }

        .btn-success {
            border: 1px solid rgba(16, 185, 129, 0.22);
            background: rgba(16, 185, 129, 0.10);
            color: #065f46;
        }

        .btn-info {
            border: 1px solid rgba(56, 189, 248, 0.22);
            background: rgba(56, 189, 248, 0.10);
            color: #0b1f3a;
        }

        .btn-warning {
            border: 1px solid rgba(245, 158, 11, 0.22);
            background: rgba(245, 158, 11, 0.12);
            color: #7c2d12;
        }

        .btn-secondary,
        .btn-outline-secondary {
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: rgba(148, 163, 184, 0.10);
            color: #334155;
        }

        .btn-outline-primary {
            border: 1px solid rgba(11, 31, 58, 0.35);
            background: transparent;
            color: var(--primary-color);
        }

        .btn-outline-danger {
            border: 1px solid rgba(239, 68, 68, 0.35);
            background: transparent;
            color: #7f1d1d;
        }

        .btn-outline-warning {
            border: 1px solid rgba(245, 158, 11, 0.35);
            background: transparent;
            color: #7c2d12;
        }

        .btn-outline-info {
            border: 1px solid rgba(56, 189, 248, 0.35);
            background: transparent;
            color: #0b1f3a;
        }

        .btn-outline-success {
            border: 1px solid rgba(16, 185, 129, 0.35);
            background: transparent;
            color: #065f46;
        }

        .bg-primary,
        .bg-success,
        .bg-info,
        .bg-warning,
        .bg-danger {
            background: transparent !important;
            color: inherit !important;
        }

        .card.bg-primary,
        .card.bg-success,
        .card.bg-info,
        .card.bg-warning,
        .card.bg-danger {
            background: #ffffff !important;
        }

        .border-left-primary,
        .border-left-success,
        .border-left-info,
        .border-left-warning {
            border-left: 0 !important;
        }

        .badge,
        .badge.bg-primary,
        .badge.bg-success,
        .badge.bg-info,
        .badge.bg-warning,
        .badge.bg-danger {
            background: rgba(148, 163, 184, 0.16) !important;
            border: 1px solid rgba(148, 163, 184, 0.28) !important;
            color: #334155 !important;
            font-weight: 600;
        }

        .badge.bg-success {
            background: rgba(16, 185, 129, 0.12) !important;
            border-color: rgba(16, 185, 129, 0.22) !important;
            color: #065f46 !important;
        }

        .badge.bg-warning {
            background: rgba(245, 158, 11, 0.12) !important;
            border-color: rgba(245, 158, 11, 0.22) !important;
            color: #7c2d12 !important;
        }

        .badge.bg-danger {
            background: rgba(239, 68, 68, 0.12) !important;
            border-color: rgba(239, 68, 68, 0.22) !important;
            color: #7f1d1d !important;
        }

        .badge.bg-info,
        .badge.bg-primary {
            background: rgba(11, 31, 58, 0.08) !important;
            border-color: rgba(11, 31, 58, 0.14) !important;
            color: rgba(11, 31, 58, 1) !important;
        }

        .text-gray-800,
        .text-gray-300 {
            color: inherit !important;
        }

        // .container-fluid {
        //     padding-top: 18px;
        //     padding-bottom: 18px;
        // }

        .container-fluid {
            padding-top: 0px;
            padding-bottom: 0px;
        }

        .card-header {
            background: #ffffff;
            border-bottom: 1px solid rgba(148, 163, 184, 0.22);
        }

        .table thead,
        .table thead.table-dark {
            background: transparent;
        }

        .table thead th {
            background: rgba(148, 163, 184, 0.10);
            border-bottom: 1px solid rgba(148, 163, 184, 0.22);
            color: #0f172a;
        }

        .payment-card,
        .payment-card:hover,
        .quick-action-card,
        .quick-action-card:hover,
        .stat-card,
        .stat-card:hover,
        .card-stats,
        .card-stats:hover,
        .admin-menu-item,
        .admin-menu-item:hover {
            transform: none !important;
            transition: none !important;
            box-shadow: inherit;
        }

        .admin-menu-toggle {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 10050;
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            padding: 0 !important;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
            display: none;
            align-items: center;
            justify-content: center;
        }

        html, body {
            overflow-x: hidden;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        .sidebar { 
            min-height: 100vh; 
            background: #0b1f3a;
        }
        .sidebar .nav-link { 
            color: rgba(255, 255, 255, 0.8); 
            border-radius: 0.35rem; 
            margin: 0.2rem 0; 
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { 
            color: #fff; 
            background-color: rgba(255, 255, 255, 0.1); 
        }
        .sidebar .sidebar-brand { 
            color: #fff; 
            font-weight: bold; 
            padding: 1rem; 
        }
        .card-stats { 
            transition: none;
        }

        @media (max-width: 767.98px) {
            .admin-menu-toggle {
                display: inline-flex !important;
            }

            /* Sidebar como overlay no mobile */
            #adminSidebar.sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: min(86vw, 320px);
                z-index: 10040;
                overflow-y: auto;
                box-shadow: 0 18px 48px rgba(15, 23, 42, 0.22);
                border-top-right-radius: 18px;
                border-bottom-right-radius: 18px;
            }

            /* Quando aberto, precisa ficar acima */
            #adminSidebar.sidebar.show {
                display: block;
            }

            /* Dar espaço no conteúdo pra não ficar atrás do botão */
            main.col-md-9.ms-sm-auto.col-lg-10 {
                padding-top: 58px;
            }

            /* Tabelas do admin: garantir usabilidade */
            table {
                width: 100%;
            }

            .table-responsive {
                margin-left: -12px;
                margin-right: -12px;
                padding-left: 12px;
                padding-right: 12px;
            }

            /* Ações (btn-group) no mobile: empilhar */
            .btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .btn-group > .btn {
                border-radius: 12px !important;
            }

            /* Header padrão do admin (título + botões) */
            main .d-flex.justify-content-between.flex-wrap.flex-md-nowrap.align-items-center {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }

            main .d-flex.justify-content-between.flex-wrap.flex-md-nowrap.align-items-center > div {
                width: 100%;
            }

            main .d-flex.justify-content-between.flex-wrap.flex-md-nowrap.align-items-center .btn,
            main .d-flex.justify-content-between.flex-wrap.flex-md-nowrap.align-items-center .btn-group {
                width: 100%;
            }
        }

        @media (max-width: 991.98px) {
            /* Tablet: botões e ações mais confortáveis */
            .btn-group {
                flex-wrap: wrap;
                gap: 8px;
            }

            main .d-flex.justify-content-between.flex-wrap.flex-md-nowrap.align-items-center {
                gap: 12px;
            }
        }
    </style>';
}

// Scripts JavaScript comuns
function renderAdminScripts() {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
}
?>
