<?php
// Menu lateral padrão para todas as páginas do admin
function renderAdminSidebar($activePage = '') {
    $menuItems = [
        'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/admin/dashboard'],
        'produtos' => ['icon' => 'fas fa-box', 'label' => 'Produtos', 'url' => '/admin/produtos'],
        'lojas' => ['icon' => 'fas fa-store', 'label' => 'Lojas', 'url' => '/admin/lojas'],
        'categorias' => ['icon' => 'fas fa-tags', 'label' => 'Categorias', 'url' => '/admin/categorias'],
        'pedidos' => ['icon' => 'fas fa-shopping-cart', 'label' => 'Pedidos', 'url' => '/admin/pedidos'],
        'estoque' => ['icon' => 'fas fa-warehouse', 'label' => 'Estoque', 'url' => '/admin/estoque'],
        'compras' => ['icon' => 'fas fa-shopping-basket', 'label' => 'Compras', 'url' => '/admin/estoque/compras'],
        'relatorios' => ['icon' => 'fas fa-file-pdf', 'label' => 'Relatórios', 'url' => '/admin/estoque/relatorios'],
        'remessa-internacional' => ['icon' => 'fas fa-globe-americas', 'label' => 'Remessa Internacional', 'url' => '/admin/remessa-internacional'],
        'remessa-correios' => ['icon' => 'fas fa-shipping-fast', 'label' => 'Remessa Correios', 'url' => '/admin/remessa-correios'],
        'usuarios' => ['icon' => 'fas fa-users', 'label' => 'Usuários', 'url' => '/admin/usuarios'],
        'pagamentos' => ['icon' => 'fas fa-credit-card', 'label' => 'Pagamentos', 'url' => '/admin/pagamentos'],
        'configuracoes' => ['icon' => 'fas fa-cog', 'label' => 'Configurações', 'url' => '/admin/configuracoes']
    ];

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
}

// Estilos CSS comuns para o menu lateral
function renderAdminSidebarStyles() {
    echo '<style>
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
            background: linear-gradient(180deg, #0b1f3a 10%, #1d4ed8 100%); 
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
            transition: transform 0.2s; 
        }
        .card-stats:hover { 
            transform: translateY(-5px); 
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
