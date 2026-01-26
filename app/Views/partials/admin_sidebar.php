<?php
// Menu lateral padrão para todas as páginas do admin
function renderAdminSidebar($activePage = '') {
    $menuItems = [
        'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/admin/dashboard'],
        'produtos' => ['icon' => 'fas fa-box', 'label' => 'Produtos', 'url' => '/admin/produtos'],
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

    echo '<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
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
    </style>';
}

// Scripts JavaScript comuns
function renderAdminScripts() {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>';
}
?>
