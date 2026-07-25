<?php
// Menu lateral padrão para todas as páginas do admin
function renderAdminSidebar($activePage = '') {
    $perfil = '';
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $perfil = (string) ($_SESSION['usuario_perfil'] ?? '');
        if ($perfil === '') {
            $perfil = (string) ($_SESSION['usuario_role'] ?? '');
        }

        if ($perfil === '') {
            $adminUid = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($adminUid > 0) {
                try {
                    $pdo = \Config\Database::getConnection();
                    $cols = [];
                    try {
                        $stCols = $pdo->query('DESCRIBE usuarios');
                        $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Throwable $e) {
                        $cols = [];
                    }

                    $perfilCol = '';
                    if (in_array('perfil', $cols, true)) $perfilCol = 'perfil';
                    elseif (in_array('role', $cols, true)) $perfilCol = 'role';

                    $roleCol = '';
                    if (in_array('role', $cols, true)) $roleCol = 'role';
                    elseif (in_array('perfil', $cols, true)) $roleCol = 'perfil';

                    if ($perfilCol !== '' || $roleCol !== '') {
                        $fields = [];
                        if ($perfilCol !== '') $fields[] = $perfilCol . ' AS perfil';
                        if ($roleCol !== '' && $roleCol !== $perfilCol) $fields[] = $roleCol . ' AS role';
                        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM usuarios WHERE id = ? LIMIT 1';
                        $stU = $pdo->prepare($sql);
                        $stU->execute([$adminUid]);
                        $row = $stU->fetch(\PDO::FETCH_ASSOC) ?: [];
                        $perfilDb = (string) ($row['perfil'] ?? '');
                        $roleDb = (string) ($row['role'] ?? '');

                        if ($perfilDb !== '') {
                            $_SESSION['usuario_perfil'] = $perfilDb;
                        }
                        if ($roleDb !== '') {
                            $_SESSION['usuario_role'] = $roleDb;
                        }

                        if ($perfilDb !== '') {
                            $perfil = $perfilDb;
                        } elseif ($roleDb !== '') {
                            $perfil = $roleDb;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }
    } catch (\Exception $e) {
        $perfil = '';
    }

    $perfil = strtolower(trim($perfil));
    if ($perfil === 'administrator' || $perfil === 'administrador') {
        $perfil = 'admin';
    } elseif ($perfil === 'seller') {
        $perfil = 'vendedor';
    } elseif ($perfil === 'support') {
        $perfil = 'suporte';
    }
    if ($perfil === '') {
        $perfil = 'cliente';
    }

    if ($perfil === 'representante') {
        $menuItems = [
            'rep_produtos' => ['icon' => 'fa-box', 'label' => __('admin.menu.products', 'Produtos'), 'url' => '/admin/representante/produtos', 'roles' => ['representante']],
        ];

        echo '<button class="btn btn-primary admin-menu-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-expanded="false" aria-label="' . htmlspecialchars(__('admin.open_menu', 'Abrir menu'), ENT_QUOTES, 'UTF-8') . '">
                <i class="fas fa-bars"></i>
              </button>';

        echo '<nav id="adminSidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/representante/produtos">
                    <div class="sidebar-brand-icon"><i class="fas fa-user-tie"></i></div>
                    <div class="sidebar-brand-text mx-3">' . htmlspecialchars(__('admin.role.representative', 'Representante'), ENT_QUOTES, 'UTF-8') . '</div>
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
                        <span>' . htmlspecialchars(__('admin.back_to_site', 'Voltar ao Site'), ENT_QUOTES, 'UTF-8') . '</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="/logout">
                        <i class="fas fa-fw fa-sign-out-alt"></i>
                        <span>' . htmlspecialchars(__('common.logout', 'Sair'), ENT_QUOTES, 'UTF-8') . '</span>
                    </a>
                </div>
            </div>
        </nav>';

        return;
    }

    // Painel dedicado do redirecionador (portal operacional)
    if ($perfil === 'redirecionador') {
        $menuItems = [
            'redirecionamento-dashboard' => [
                'icon' => 'fas fa-tachometer-alt',
                'label' => __('admin.menu.redirecionamento.dashboard', 'Dashboard de Redirecionamento'),
                'url' => '/admin/redirecionamento/dashboard',
            ],
            'redirecionamento-envios' => [
                'icon' => 'fas fa-truck-fast',
                'label' => __('admin.menu.redirecionamento.envios', 'Envios'),
                'url' => '/admin/redirecionamento/envios',
            ],
            'redirecionamento-divergencias' => [
                'icon' => 'fas fa-scale-balanced',
                'label' => __('admin.menu.redirecionamento.divergencias', 'Divergências e Ajustes'),
                'url' => '/admin/redirecionamento/divergencias',
            ],
            'redirecionamento-clientes' => [
                'icon' => 'fas fa-users',
                'label' => __('admin.menu.redirecionamento.clientes', 'Clientes'),
                'url' => '/admin/redirecionamento/clientes',
            ],
            'redirecionamento-tabela-pesos' => [
                'icon' => 'fas fa-table',
                'label' => __('admin.menu.redirecionamento.tabela_pesos', 'Tabela de Pesos e Preços'),
                'url' => '/admin/redirecionamento/tabela-pesos',
            ],
            'redirecionamento-pagamentos' => [
                'icon' => 'fas fa-credit-card',
                'label' => __('admin.menu.redirecionamento.pagamentos', 'Pagamentos'),
                'url' => '/admin/redirecionamento/pagamentos',
            ],
            'redirecionamento-comprovantes' => [
                'icon' => 'fas fa-file-upload',
                'label' => __('admin.menu.redirecionamento.comprovantes', 'Comprovantes'),
                'url' => '/admin/redirecionamento/comprovantes',
            ],
            'redirecionamento-coletas' => [
                'icon' => 'fas fa-calendar-check',
                'label' => __('admin.menu.redirecionamento.coletas', 'Coletas'),
                'url' => '/admin/redirecionamento/coletas',
            ],
            'redirecionamento-ajuda' => [
                'icon' => 'fas fa-question-circle',
                'label' => __('admin.menu.redirecionamento.ajuda', 'Como Funciona'),
                'url' => '/admin/redirecionamento/ajuda',
            ],
        ];

        echo '<button class="btn btn-primary admin-menu-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-expanded="false" aria-label="' . htmlspecialchars(__('admin.open_menu', 'Abrir menu'), ENT_QUOTES, 'UTF-8') . '">
                <i class="fas fa-bars"></i>
              </button>';

        echo '<nav id="adminSidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/redirecionamento/dashboard">
                    <div class="sidebar-brand-icon"><i class="fas fa-truck-fast"></i></div>
                    <div class="sidebar-brand-text mx-3">' . htmlspecialchars(__('admin.role.redirecionador', 'Redirecionamento'), ENT_QUOTES, 'UTF-8') . '</div>
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
                        <span>' . htmlspecialchars(__('admin.back_to_site', 'Voltar ao Site'), ENT_QUOTES, 'UTF-8') . '</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="/logout">
                        <i class="fas fa-fw fa-sign-out-alt"></i>
                        <span>' . htmlspecialchars(__('common.logout', 'Sair'), ENT_QUOTES, 'UTF-8') . '</span>
                    </a>
                </div>
            </div>
        </nav>';

        return;
    }

    $menuItems = [
        'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'label' => __('admin.menu.dashboard', 'Dashboard'), 'url' => '/admin/dashboard', 'roles' => ['admin','vendedor','suporte','redirecionador']],
        'produtos' => ['icon' => 'fas fa-box', 'label' => __('admin.menu.products', 'Produtos'), 'url' => '/admin/produtos', 'roles' => ['admin','vendedor','suporte']],
        'grupos-compras' => ['icon' => 'fas fa-store', 'label' => 'Grupos de Compras', 'url' => '/admin/grupos-compras', 'roles' => ['admin','vendedor','suporte']],
        'lojas' => ['icon' => 'fas fa-store', 'label' => __('admin.menu.stores', 'Lojas'), 'url' => '/admin/lojas', 'roles' => ['admin','vendedor','suporte']],
        'categorias' => ['icon' => 'fas fa-tags', 'label' => __('admin.menu.categories', 'Categorias'), 'url' => '/admin/categorias', 'roles' => ['admin','vendedor','suporte']],
        'pedidos' => ['icon' => 'fas fa-shopping-cart', 'label' => __('admin.menu.orders', 'Pedidos'), 'url' => '/admin/pedidos', 'roles' => ['admin','vendedor','suporte']],
        'pacotes-recebidos' => ['icon' => 'fas fa-box-open', 'label' => 'Pacotes Recebidos', 'url' => '/admin/pacotes-recebidos', 'roles' => ['admin','vendedor','conferente']],
        'faturas-adicionais' => ['icon' => 'fas fa-file-invoice-dollar', 'label' => 'Faturas Adicionais', 'url' => '/admin/faturas-adicionais', 'roles' => ['admin','vendedor']],
        'pedidos-conferencia' => ['icon' => 'fas fa-clipboard-check', 'label' => 'Em Conferência', 'url' => '/admin/pedidos/conferencia', 'roles' => ['admin','vendedor']],
        'split-order' => ['icon' => 'fas fa-cut', 'label' => 'Split Order', 'url' => '/admin/pedidos/split', 'roles' => ['admin','vendedor']],
        'wp-estatisticas' => ['icon' => 'fas fa-chart-pie', 'label' => __('admin.menu.wp_orders_stats', 'Estatísticas (WP)'), 'url' => '/admin/pedidos-wp/estatisticas', 'roles' => ['admin']],
        'tickets' => ['icon' => 'fas fa-life-ring', 'label' => __('admin.menu.tickets', 'Tickets'), 'url' => '/admin/tickets', 'roles' => ['admin','suporte']],
        'pedidos-comissoes' => ['icon' => 'fas fa-percentage', 'label' => __('admin.menu.my_commissions', 'Minhas Comissões'), 'url' => '/admin/pedidos/comissoes', 'roles' => ['admin','vendedor']],
        'estoque' => ['icon' => 'fas fa-warehouse', 'label' => __('admin.menu.inventory', 'Estoque'), 'url' => '/admin/estoque', 'roles' => ['admin','vendedor','suporte']],
        'compras' => ['icon' => 'fas fa-shopping-basket', 'label' => __('admin.menu.purchases', 'Compras'), 'url' => '/admin/estoque/compras', 'roles' => ['admin','vendedor']],
        // 'relatorios' => ['icon' => 'fas fa-file-pdf', 'label' => __('admin.menu.reports', 'Relatórios'), 'url' => '/admin/estoque/relatorios', 'roles' => ['admin','vendedor']],
        'remessa-internacional' => ['icon' => 'fas fa-globe-americas', 'label' => 'Wexpress', 'url' => '/admin/remessa-internacional', 'roles' => ['admin','vendedor']],
        // 'remessa-wp' => ['icon' => 'fab fa-wordpress', 'label' => 'Remessa WP', 'url' => '/admin/remessa-wp', 'roles' => ['admin','vendedor','conferente']],
        'remessa-conferencia' => ['icon' => 'fas fa-clipboard-check', 'label' => 'Conferência de Remessa', 'url' => '/admin/remessa-conferencia', 'roles' => ['admin','vendedor','suporte','conferente']],
        'remessa-correios' => ['icon' => 'fas fa-shipping-fast', 'label' => 'Correio Brasil', 'url' => '/admin/remessa-correios', 'roles' => ['admin','vendedor']],
        'correios-mundial' => ['icon' => 'fas fa-globe', 'label' => 'Correio Internacional', 'url' => '/admin/etiquetas-wp', 'roles' => ['admin','vendedor','suporte','redirecionador']],
        'etiquetas-wp' => ['icon' => 'fas fa-tags', 'label' => 'Etiquetas', 'url' => '/admin/etiquetas-wp', 'roles' => ['admin','vendedor','suporte']],
        'pacotes-wordpress' => ['icon' => 'fab fa-wordpress', 'label' => 'Pacotes WordPress', 'url' => '/admin/pacotes-wordpress', 'roles' => ['admin','vendedor','suporte']],
        'remessa-shipstation' => ['icon' => 'fas fa-plane', 'label' => 'UPS', 'url' => '/admin/remessa-shipstation', 'roles' => ['admin','vendedor']],
        'usuarios' => ['icon' => 'fas fa-users', 'label' => __('admin.menu.users', 'Usuários'), 'url' => '/admin/usuarios', 'roles' => ['admin','vendedor','suporte']],
        // 'pagamentos' => ['icon' => 'fas fa-credit-card', 'label' => __('admin.menu.payments', 'Pagamentos'), 'url' => '/admin/pagamentos', 'roles' => ['admin','vendedor']],
        'carnes' => ['icon' => 'fas fa-file-invoice-dollar', 'label' => 'Carnê Braziliana', 'url' => '/admin/carnes', 'roles' => ['admin','vendedor']],
        'carnes-compras' => ['icon' => 'fas fa-shopping-basket', 'label' => 'Compras do Carnê', 'url' => '/admin/carnes/compras', 'roles' => ['admin','vendedor']],
        'relatorio-pedidos' => ['icon' => 'fas fa-file-alt', 'label' => 'Relatório Pedidos', 'url' => '/admin/relatorio-pedidos', 'roles' => ['admin','vendedor']],
        'relatorio-geral' => ['icon' => 'fas fa-chart-bar', 'label' => 'Resumo Financeiro', 'url' => '/admin/relatorio-geral', 'roles' => ['admin']],
        'payment-links' => ['icon' => 'fas fa-link', 'label' => 'Payment Links', 'url' => '/admin/payment-links', 'roles' => ['admin','vendedor']],
        'clube-recargas' => ['icon' => 'fas fa-wallet', 'label' => 'Recargas Clube', 'url' => '/admin/clube/recargas', 'roles' => ['admin','vendedor','suporte','redirecionador']],
        'backup' => ['icon' => 'fas fa-database', 'label' => 'Backup', 'url' => '/admin/backup', 'roles' => ['admin']],
        'oferta-gratuita' => ['icon' => 'fas fa-gift', 'label' => 'Oferta Gratuita', 'url' => '/admin/oferta-gratuita', 'roles' => ['admin']],
        'copiloto' => ['icon' => 'fas fa-robot', 'label' => 'Co-Piloto IA', 'url' => '/admin/copiloto', 'roles' => ['admin']],
        'live-shop' => ['icon' => 'fas fa-video', 'label' => 'Campanhas', 'url' => '/admin/live-shop', 'roles' => ['admin', 'vendedor']],
        'live-shop-create' => ['icon' => 'fas fa-plus-circle', 'label' => 'Nova Campanha', 'url' => '/admin/live-shop/create', 'roles' => ['admin', 'vendedor']],
        'live-shop-orders' => ['icon' => 'fas fa-shopping-bag', 'label' => 'Pedidos da Live', 'url' => '/admin/live-shop/orders', 'roles' => ['admin', 'vendedor']],
        'live-shop-reports' => ['icon' => 'fas fa-chart-line', 'label' => 'Relatórios', 'url' => '/admin/live-shop/reports', 'roles' => ['admin', 'vendedor']],
        'quickbooks' => ['icon' => 'fas fa-calculator', 'label' => 'QuickBooks', 'url' => '/admin/quickbooks', 'roles' => ['admin']],
        'marketing-calendario' => ['icon' => 'fas fa-calendar-alt', 'label' => 'Calendário', 'url' => '/admin/marketing-calendar', 'roles' => ['admin']],
        'email-marketing' => ['icon' => 'fas fa-envelope-open-text', 'label' => 'Email Marketing', 'url' => '/admin/email-marketing', 'roles' => ['admin']],
        'cart-recovery' => ['icon' => 'fas fa-cart-arrow-down', 'label' => 'Recuperação Carrinho', 'url' => '/admin/cart-recovery', 'roles' => ['admin','vendedor']],
        'mapa-calor-site' => ['icon' => 'fas fa-fire', 'label' => 'Mapa de Calor', 'url' => '/admin/mapa-calor-site', 'roles' => ['admin']],
        'configuracoes' => ['icon' => 'fas fa-cog', 'label' => __('admin.menu.settings', 'Configurações'), 'url' => '/admin/configuracoes', 'roles' => ['admin']],
        'carteira-config' => ['icon' => 'fas fa-wallet', 'label' => 'Config. Carteira', 'url' => '/admin/carteira-config', 'roles' => ['admin']],
        'email-logs' => ['icon' => 'fas fa-envelope', 'label' => 'Log de Emails', 'url' => '/admin/emails', 'roles' => ['admin']],
        'descontos' => ['icon' => 'fas fa-tag', 'label' => 'Autorizações Desconto', 'url' => '/admin/configuracoes/desconto/painel', 'roles' => ['admin']],
        'documentacao-webhook' => ['icon' => 'fas fa-book', 'label' => 'Documentação API', 'url' => '/admin/documentacao/webhook-ticket', 'roles' => ['admin']],
        'promocoes-auditoria' => ['icon' => 'fas fa-percent', 'label' => 'Auditoria Promoções', 'url' => '/admin/promocoes-auditoria', 'roles' => ['admin']],
        'descricao-produtos' => ['icon' => 'fas fa-pen-fancy', 'label' => 'Descrição de Produtos', 'url' => '/admin/descricao-produtos', 'roles' => ['admin','vendedor']],
        'promocoes-agendadas' => ['icon' => 'fas fa-calendar-alt', 'label' => 'Promoções Agendadas', 'url' => '/admin/promocoes-agendadas', 'roles' => ['admin', 'vendedor']],
        'faq' => ['icon' => 'fas fa-question-circle', 'label' => 'FAQ / Termos', 'url' => '/admin/faq', 'roles' => ['admin']],
        'comissoes-global' => ['icon' => 'fas fa-users-cog', 'label' => 'Comissões Global', 'url' => '/admin/comissoes-global', 'roles' => ['admin']],
        'despesas' => ['icon' => 'fas fa-wallet', 'label' => 'Despesas', 'url' => '/admin/despesas', 'roles' => ['admin']],
        'demandas-painel' => ['icon' => 'fas fa-columns', 'label' => 'Painel de Demandas', 'url' => '/admin/demandas/painel', 'roles' => ['admin', 'suporte']],
        'demandas-minhas' => ['icon' => 'fas fa-user-clock', 'label' => 'Minhas Solicitações', 'url' => '/admin/demandas/minhas', 'roles' => ['admin', 'suporte', 'vendedor']],
        'demandas-nova' => ['icon' => 'fas fa-file-alt', 'label' => 'Nova Solicitação', 'url' => '/admin/demandas/nova', 'roles' => ['admin', 'suporte', 'vendedor']],
        'demandas-concluidos' => ['icon' => 'fas fa-check-circle', 'label' => 'Concluídos', 'url' => '/admin/demandas/concluidos', 'roles' => ['admin', 'suporte']],
        'demandas-arquivados' => ['icon' => 'fas fa-archive', 'label' => 'Arquivados', 'url' => '/admin/demandas/arquivados', 'roles' => ['admin', 'suporte']],

        // Módulo Redirecionamento (visível no admin/suporte)
        'redirecionamento-envios' => ['icon' => 'fas fa-truck-fast', 'label' => '(RED) Envios', 'url' => '/admin/redirecionamento/envios', 'roles' => ['admin', 'suporte']],
        'redirecionamento-divergencias' => ['icon' => 'fas fa-scale-balanced', 'label' => '(RED) Divergências e Ajustes', 'url' => '/admin/redirecionamento/divergencias', 'roles' => ['admin', 'suporte']],
        'redirecionamento-clientes' => ['icon' => 'fas fa-users', 'label' => '(RED) Clientes', 'url' => '/admin/redirecionamento/clientes', 'roles' => ['admin', 'suporte']],
        'redirecionamento-tabela-pesos' => ['icon' => 'fas fa-table', 'label' => '(RED) Tabela de Pesos e Preços', 'url' => '/admin/redirecionamento/tabela-pesos', 'roles' => ['admin', 'suporte']],
        'redirecionamento-pagamentos' => ['icon' => 'fas fa-credit-card', 'label' => '(RED) Pagamentos', 'url' => '/admin/redirecionamento/pagamentos', 'roles' => ['admin', 'suporte']],
        'redirecionamento-comprovantes' => ['icon' => 'fas fa-file-upload', 'label' => '(RED) Comprovantes', 'url' => '/admin/redirecionamento/comprovantes', 'roles' => ['admin', 'suporte']],
        'redirecionamento-coletas' => ['icon' => 'fas fa-calendar-check', 'label' => '(RED) Coletas', 'url' => '/admin/redirecionamento/coletas', 'roles' => ['admin', 'suporte']]
    ];

    $unreadTickets = 0;
    $pendentesConferencia = 0;
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

    try {
        $pdo = \Config\Database::getConnection();
        $stT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stT->execute(['pedidos']);
        $hasPedidos = ((int) ($stT->fetchColumn() ?: 0) > 0);
        if ($hasPedidos) {
            $stC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'status_conferencia'");
            $stC->execute();
            $hasCol = ((int) ($stC->fetchColumn() ?: 0) > 0);
            if ($hasCol) {
                $stP = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status_conferencia = 'pendente'");
                $pendentesConferencia = (int) ($stP ? ($stP->fetchColumn() ?: 0) : 0);
            }
        }
    } catch (\Exception $e) {
        $pendentesConferencia = 0;
    }

    // Toggle mobile - custom JS (não usa collapse do Bootstrap para evitar conflito com grupos internos)
    echo '<button class="btn btn-primary admin-menu-toggle d-md-none" type="button" onclick="document.getElementById(\'adminSidebar\').classList.toggle(\'sidebar-open\')" aria-label="' . htmlspecialchars(__('admin.open_menu', 'Abrir menu'), ENT_QUOTES, 'UTF-8') . '">
            <i class="fas fa-bars"></i>
          </button>';

    $adminLogo = '';
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

                if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                    $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                    if ($valCol !== '') {
                        $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                        $stmt->execute(['layout', 'logo_admin']);
                        $raw = (string) ($stmt->fetchColumn() ?: '');
                        if ($raw !== '') break;
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
                    $stmt->execute(['layout_logo_admin']);
                    $raw = (string) ($stmt->fetchColumn() ?: '');
                    if ($raw !== '') break;
                }

                if (in_array('layout_logo_admin', $cols, true)) {
                    $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                    $stmt2 = $pdo->query('SELECT layout_logo_admin AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                    $raw = (string) ($stmt2->fetchColumn() ?: '');
                    if ($raw !== '') break;
                }
            } catch (\Exception $e) {
            }
        }

        $adminLogo = is_string($raw) ? trim($raw) : '';
    } catch (\Exception $e) {
        $adminLogo = '';
    }

    // Normalizar valores inválidos e garantir fallback quando o arquivo não existir
    $adminLogo = is_string($adminLogo) ? trim($adminLogo) : '';
    $adminLogoLower = strtolower($adminLogo);
    if ($adminLogo === '' || $adminLogoLower === '0' || $adminLogoLower === 'null' || $adminLogoLower === 'none' || $adminLogoLower === 'false' || $adminLogoLower === 'undefined') {
        $adminLogo = '';
    } else {
        // Se for um caminho local (ex.: /uploads/...), validar existência do arquivo para evitar mostrar logo removida
        $isHttp = (stripos($adminLogo, 'http://') === 0 || stripos($adminLogo, 'https://') === 0);
        $isData = (stripos($adminLogo, 'data:') === 0);
        if (!$isHttp && !$isData) {
            $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
            if ($docRoot !== '') {
                $logoPath = $adminLogo;
                if (($logoPath[0] ?? '') !== '/') {
                    $logoPath = '/' . ltrim($logoPath, '/');
                }
                $candidatePath = rtrim($docRoot, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $logoPath);
                if (!@file_exists($candidatePath)) {
                    $adminLogo = '';
                }
            }
        }
    }

    echo '<nav id="adminSidebar" class="col-md-3 col-lg-2 d-md-block sidebar">
        <div class="position-sticky pt-3">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                ' . (!empty($adminLogo)
                    ? '<img src="' . htmlspecialchars($adminLogo, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars(__('admin.brand.alt', 'Admin'), ENT_QUOTES, 'UTF-8') . '" class="admin-sidebar-logo">'
                    : '<div class="sidebar-brand-icon"><i class="fas fa-warehouse"></i></div><div class="sidebar-brand-text mx-3">' . htmlspecialchars(__('admin.brand.name', 'Braziliana Admin'), ENT_QUOTES, 'UTF-8') . '</div>'
                ) . '
            </a>
            <ul class="nav flex-column">';

            // Definir grupos de menu
            $menuGroups = [
                '_solo_dashboard' => ['items' => ['dashboard']],
                'Pedidos' => ['icon' => 'fas fa-shopping-cart', 'items' => ['pedidos', 'pacotes-recebidos', 'faturas-adicionais', 'pedidos-conferencia', 'split-order', 'tickets', 'pedidos-comissoes', 'carnes', 'relatorio-pedidos']],
                'Catálogo' => ['icon' => 'fas fa-box', 'items' => ['produtos', 'grupos-compras', 'lojas', 'categorias', 'descricao-produtos', 'promocoes-agendadas', 'promocoes-auditoria', 'oferta-gratuita']],
                'Estoque & Compras' => ['icon' => 'fas fa-warehouse', 'items' => ['estoque', 'compras', 'carnes-compras', 'relatorios']],
                'Envios & Etiquetas' => ['icon' => 'fas fa-shipping-fast', 'items' => ['remessa-internacional', 'remessa-wp', 'remessa-conferencia', 'remessa-correios', 'correios-mundial', 'etiquetas-wp', 'pacotes-wordpress', 'remessa-shipstation']],
                'Financeiro' => ['icon' => 'fas fa-credit-card', 'items' => ['pagamentos', 'relatorio-geral', 'despesas', 'comissoes-global', 'clube-recargas', 'quickbooks']],
                'Demandas' => ['icon' => 'fas fa-tasks', 'items' => ['demandas-minhas', 'demandas-nova', 'demandas-painel', 'demandas-concluidos', 'demandas-arquivados']],
                'Live Shop' => ['icon' => 'fas fa-video', 'items' => ['live-shop', 'live-shop-create', 'live-shop-orders', 'live-shop-reports']],
                'Marketing' => ['icon' => 'fas fa-bullhorn', 'items' => ['marketing-calendario', 'email-marketing', 'cart-recovery', 'mapa-calor-site']],
                'Redirecionamento' => ['icon' => 'fas fa-truck-fast', 'items' => ['redirecionamento-envios', 'redirecionamento-divergencias', 'redirecionamento-clientes', 'redirecionamento-tabela-pesos', 'redirecionamento-pagamentos', 'redirecionamento-comprovantes', 'redirecionamento-coletas']],
                'Configurações' => ['icon' => 'fas fa-cog', 'items' => ['configuracoes', 'carteira-config', 'email-logs', 'usuarios', 'descontos', 'faq', 'copiloto', 'backup', 'documentacao-webhook']],
            ];

            foreach ($menuGroups as $groupName => $group) {
                $groupItems = [];
                foreach ($group['items'] as $key) {
                    if (!isset($menuItems[$key])) continue;
                    $item = $menuItems[$key];
                    $roles = isset($item['roles']) && is_array($item['roles']) ? $item['roles'] : [];
                    if (!empty($roles) && !in_array($perfil, $roles, true)) continue;
                    $groupItems[$key] = $item;
                }
                if (empty($groupItems)) continue;

                // Solo items (sem grupo)
                if (strpos($groupName, '_solo_') === 0) {
                    foreach ($groupItems as $key => $item) {
                        $activeClass = ($activePage === $key) ? 'active' : '';
                        $label = $item['label'];
                        echo '<li class="nav-item">
                            <a class="nav-link ' . $activeClass . '" href="' . $item['url'] . '">
                                <i class="fas fa-fw ' . $item['icon'] . '"></i>
                                <span>' . $label . '</span>
                            </a>
                        </li>';
                    }
                    continue;
                }

                // Verificar se algum item do grupo está ativo
                $groupActive = false;
                foreach ($groupItems as $key => $item) {
                    if ($activePage === $key) { $groupActive = true; break; }
                }
                $groupId = 'grp_' . md5($groupName);
                $collapseClass = $groupActive ? 'show' : '';

                echo '<li class="nav-item">
                    <a class="nav-link sidebar-group-toggle' . ($groupActive ? ' active' : '') . '" href="javascript:void(0)" onclick="toggleSidebarGroup(this)" aria-expanded="' . ($groupActive ? 'true' : 'false') . '" style="display:flex;justify-content:space-between;align-items:center;">
                        <span><i class="fas fa-fw ' . ($group['icon'] ?? 'fas fa-folder') . '"></i> ' . htmlspecialchars($groupName) . '</span>
                        <i class="fas fa-chevron-down sidebar-chevron" style="font-size:10px;transition:transform .2s;' . ($groupActive ? 'transform:rotate(180deg);' : '') . '"></i>
                    </a>
                    <div class="sidebar-group-content" style="' . ($groupActive ? '' : 'display:none;') . '">
                        <ul class="nav flex-column" style="padding-left:12px;">';

                foreach ($groupItems as $key => $item) {
                    $activeClass = ($activePage === $key) ? 'active' : '';
                    $label = $item['label'];
                    // Remover prefixo (RED) dos labels dentro do grupo
                    $label = preg_replace('/^\(RED\)\s*/i', '', $label);
                    if ($key === 'tickets' && $unreadTickets > 0) {
                        $label .= ' <span class="badge ms-2" style="background: #ffffff !important; color: #18253D !important; border: 2px solid #18253D !important; font-weight: 700; font-size: 11px; min-width: 22px; padding: 2px 6px;">' . (int) $unreadTickets . '</span>';
                    }
                    if ($key === 'pedidos-conferencia' && $pendentesConferencia > 0) {
                        $label .= ' <span class="badge ms-2" style="background: #ffffff !important; color: #18253D !important; border: 2px solid #18253D !important; font-weight: 700; font-size: 11px; min-width: 22px; padding: 2px 6px;">' . (int) $pendentesConferencia . '</span>';
                    }
                    echo '<li class="nav-item">
                        <a class="nav-link ' . $activeClass . '" href="' . $item['url'] . '" style="padding:4px 12px;font-size:13px;">
                            <i class="fas fa-fw ' . $item['icon'] . '" style="font-size:11px;"></i>
                            <span>' . $label . '</span>
                        </a>
                    </li>';
                }

                echo '</ul></div></li>';
            }
            
            echo '</ul>
            <hr class="sidebar-divider">
            <div class="nav-item">
                <a class="nav-link" href="/">
                    <i class="fas fa-fw fa-home"></i>
                    <span>' . htmlspecialchars(__('admin.back_to_site', 'Voltar ao Site'), ENT_QUOTES, 'UTF-8') . '</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link" href="/logout">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>' . htmlspecialchars(__('common.logout', 'Sair'), ENT_QUOTES, 'UTF-8') . '</span>
                </a>
            </div>
        </div>
    </nav>
    <script>
    function toggleSidebarGroup(el){
        var content=el.nextElementSibling;
        var chevron=el.querySelector(".sidebar-chevron");
        if(!content)return;
        var isVisible=(content.style.display!=="none");
        if(isVisible){
            content.style.display="none";
            if(chevron)chevron.style.transform="rotate(0deg)";
            el.setAttribute("aria-expanded","false");
        } else {
            content.style.display="block";
            if(chevron)chevron.style.transform="rotate(180deg)";
            el.setAttribute("aria-expanded","true");
        }
    }
    </script>';
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

        /* Hide icons in page titles globally */
        main h1 i.fas, main h1 i.far, main h1 i.fab, main h1 i.bi,
        main h2 i.fas, main h2 i.far, main h2 i.fab, main h2 i.bi,
        main h3 i.fas, main h3 i.far, main h3 i.fab, main h3 i.bi,
        main .h2 i.fas, main .h2 i.far, main .h2 i.fab, main .h2 i.bi,
        main .h3 i.fas, main .h3 i.far, main .h3 i.fab, main .h3 i.bi,
        main .page-title i, main .page-title-icon,
        main .page-title-wrap .page-title-icon,
        main .border-bottom h1 i, main .border-bottom h2 i,
        main .border-bottom h3 i,
        .col-md-9 h1 i.fas, .col-md-9 h1 i.far, .col-md-9 h1 i.fab, .col-md-9 h1 i.bi,
        .col-md-9 h2 i.fas, .col-md-9 h2 i.far, .col-md-9 h2 i.fab, .col-md-9 h2 i.bi,
        .col-md-9 h3 i.fas, .col-md-9 h3 i.far, .col-md-9 h3 i.fab, .col-md-9 h3 i.bi,
        .col-lg-10 h1 i.fas, .col-lg-10 h1 i.far, .col-lg-10 h1 i.fab, .col-lg-10 h1 i.bi,
        .col-lg-10 h2 i.fas, .col-lg-10 h2 i.far, .col-lg-10 h2 i.fab, .col-lg-10 h2 i.bi,
        .col-lg-10 h3 i.fas, .col-lg-10 h3 i.far, .col-lg-10 h3 i.fab, .col-lg-10 h3 i.bi,
        .container-fluid > .d-flex h1 i, .container-fluid > .d-flex h2 i, .container-fluid > .d-flex h3 i,
        .page-header .page-title-icon {
            display: none !important;
        }

        /* Standardize page title sizes globally - match Dashboard pattern */
        main h1.h2, main h1.h3, main .border-bottom h1,
        .col-md-9 h1.h2, .col-md-9 h1.h3,
        .col-lg-10 h1.h2, .col-lg-10 h1.h3,
        .col-lg-10 .border-bottom h1,
        main .page-title, .col-lg-10 .page-title,
        .container-fluid .page-title,
        main h4.fw-bold, .col-lg-10 h4.fw-bold,
        .container-fluid h4.fw-bold {
            font-size: 20px !important;
            font-weight: 700 !important;
            color: #18253D !important;
            margin: 0 !important;
            line-height: 1.3 !important;
        }

        main h2.mb-1, main h2.mb-0,
        .col-lg-10 h2.mb-1, .col-lg-10 h2.mb-0,
        .container-fluid h2.mb-1, .container-fluid h1.h3,
        .container-fluid h2.mb-0 {
            font-size: 20px !important;
            font-weight: 700 !important;
            color: #18253D !important;
            margin: 0 !important;
            line-height: 1.3 !important;
        }

        /* Standardize page subtitles */
        main .border-bottom + p.text-muted,
        main .page-subtitle, .col-lg-10 .page-subtitle,
        .container-fluid .page-subtitle,
        main .page-count {
            font-size: 13px !important;
            color: #64748B !important;
            margin: 2px 0 0 0 !important;
        }

        /* Nav tabs/pills global override - navy instead of blue */
        .nav-tabs .nav-link.active,
        .nav-tabs .nav-link.active:focus,
        .nav-tabs .nav-link.active:hover {
            color: #18253D !important;
            border-color: #18253D !important;
            border-bottom-color: #fff !important;
            font-weight: 600;
        }
        .nav-tabs .nav-link {
            color: #64748B;
            font-weight: 500;
            font-size: 13px;
        }
        .nav-tabs .nav-link:hover {
            color: #18253D;
            border-color: transparent;
        }
        .nav-pills .nav-link.active {
            background: #18253D !important;
            color: #fff !important;
            font-weight: 600;
        }
        .nav-pills .nav-link {
            color: #334155;
            font-weight: 500;
            font-size: 13px;
        }
        .nav-pills .nav-link:hover {
            color: #18253D;
            background: #F8FAFC;
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
            background: #ffffff;
            border-right: 1px solid #EBF0F6;
            padding: 16px;
        }
        .sidebar .position-sticky {
            padding-top: 0 !important;
        }
        .sidebar .nav-link { 
            color: #334155; 
            border-radius: 9px; 
            margin: 2px 0;
            padding: 9px 12px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: .18s ease;
        }
        .sidebar .nav-link i {
            color: #94A3B8;
            width: 18px;
            text-align: center;
            font-size: 14px;
        }
        .sidebar .nav-link:hover { 
            color: #18253D; 
            background: #F8FAFC; 
        }
        .sidebar .nav-link:hover i {
            color: #18253D;
        }
        .sidebar .nav-link.active { 
            color: #fff; 
            background: #18253D;
            box-shadow: 0 4px 14px rgba(24, 37, 61, .16);
        }
        .sidebar .nav-link.active i {
            color: #fff;
        }
        .sidebar .sidebar-group-toggle {
            font-weight: 600;
            color: #1F2937;
        }
        .sidebar .sidebar-group-toggle:hover {
            background: #F8FAFC;
            color: #18253D;
        }
        .sidebar .sidebar-group-toggle.active {
            background: rgba(24, 37, 61, 0.06);
            color: #18253D;
            box-shadow: none;
        }
        .sidebar .sidebar-group-toggle.active i {
            color: #18253D;
        }
        .sidebar .sidebar-brand { 
            color: #18253D; 
            font-weight: bold; 
            padding: 10px 10px 16px;
            margin-bottom: 8px;
            border-bottom: 1px solid #EBF0F6;
        }
        .sidebar .sidebar-brand .sidebar-brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #18253D;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .sidebar .sidebar-brand .sidebar-brand-text {
            font-size: 15px;
            font-weight: 700;
            color: #18253D;
        }

        .sidebar .sidebar-brand .admin-sidebar-logo {
            max-height: 44px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            display: inline-block;
        }
        .sidebar .sidebar-divider {
            border-color: #EBF0F6;
            margin: 12px 0;
        }
        .sidebar .collapse .nav-link {
            padding: 6px 12px;
            font-size: 12.5px;
            margin: 1px 0;
        }
        .sidebar .collapse .nav-link i {
            font-size: 11px;
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
                background: #ffffff;
                border-right: none;
                transform: translateX(-100%);
                transition: transform .25s ease;
                display: block !important;
            }

            #adminSidebar.sidebar.sidebar-open {
                transform: translateX(0);
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

        /* Mobile responsivo global - tabelas e layout */
        @media (max-width: 767.98px) {
            main { padding-left: 8px !important; padding-right: 8px !important; }
            main h1, main h2, main h3 { font-size: 1.1rem !important; word-break: break-word; }
            main .card-header h5, main .card-header h6 { font-size: 0.85rem; }
            main .card-body { padding: 0.75rem; }
            main table { font-size: 11px; width: 100% !important; }
            main table th, main table td { padding: 4px 6px; font-size: 11px; white-space: normal !important; word-break: break-word; }
            main .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            main .btn:not(.btn-sm):not(.btn-lg) { font-size: 0.8rem; padding: 0.3rem 0.6rem; }
            main .row > [class*="col-md-4"],
            main .row > [class*="col-md-3"],
            main .row > [class*="col-lg-3"],
            main .row > [class*="col-lg-4"] { flex: 0 0 50%; max-width: 50%; }
            main form .row > [class*="col-md"] { flex: 0 0 100%; max-width: 100%; margin-bottom: 0.5rem; }
            main .alert { font-size: 0.8rem; padding: 0.5rem 0.75rem; }
            /* Cards de pedido na listagem */
            main .card { font-size: 12px; margin-bottom: 0.75rem !important; }
            main .card .d-flex { flex-wrap: wrap; gap: 4px; }
            main .card img, main .card .rounded-circle { width: 32px !important; height: 32px !important; }
            main .card .badge { font-size: 9px !important; padding: 2px 5px; }
            main .card select.form-select { font-size: 11px; padding: 2px 24px 2px 6px; height: auto; }
            /* Código do pedido longo */
            main .card .text-muted { word-break: break-all; }
            /* Prevent horizontal scroll on nav tabs/pills */
            main .nav-tabs, main .nav-pills {
                flex-wrap: wrap !important;
                overflow-x: visible !important;
                white-space: normal !important;
            }
            main .nav-tabs .nav-link, main .nav-pills .nav-link {
                font-size: 11px !important;
                padding: 6px 10px !important;
                white-space: nowrap;
            }
            /* Global no horizontal scroll */
            body, html { overflow-x: hidden !important; }
            main, .container-fluid, .col-md-9, .col-lg-10 { overflow-x: hidden !important; max-width: 100vw; }
        }
    </style>';
}

// Scripts JavaScript comuns
function renderAdminScripts() {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
    // Notificações push de demandas + Sino de Notificações
    echo '<div id="admin-notif-container" style="position:fixed;top:80px;right:20px;z-index:99998;max-width:400px;"></div>';

    // Bell Widget
    echo '<div id="admin-bell-widget" style="position:fixed;top:16px;right:20px;z-index:99999;">
        <button id="bellBtn" onclick="toggleBellDropdown()" style="position:relative;background:#1e293b;border:none;border-radius:50%;width:44px;height:44px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.15);transition:transform .2s;">
            <i class="fas fa-bell" style="color:#fff;font-size:18px;"></i>
            <span id="bellBadge" style="display:none;position:absolute;top:-2px;right:-2px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;min-width:18px;height:18px;border-radius:9px;line-height:18px;text-align:center;padding:0 4px;">0</span>
        </button>
        <div id="bellDropdown" style="display:none;position:absolute;top:52px;right:0;width:360px;max-height:440px;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);overflow:hidden;">
            <div style="padding:14px 16px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-weight:700;font-size:15px;color:#1e293b;"><i class="fas fa-bell me-2"></i>Notificações</span>
                <button onclick="marcarTodasLidas()" style="background:none;border:none;color:#3b82f6;font-size:12px;font-weight:600;cursor:pointer;">Marcar todas como lidas</button>
            </div>
            <div id="bellList" style="max-height:340px;overflow-y:auto;padding:8px;">
                <div class="text-center text-muted py-4" style="font-size:13px;"><i class="fas fa-check-circle d-block mb-2" style="font-size:24px;opacity:.4;"></i>Nenhuma notificação</div>
            </div>
            <div style="padding:10px 16px;border-top:1px solid #e2e8f0;text-align:center;">
                <a href="/admin/demandas/minhas" style="color:#3b82f6;font-size:12px;font-weight:600;text-decoration:none;">Ver todas as demandas</a>
            </div>
        </div>
    </div>';

    echo '<style>
@keyframes notifSlideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes notifPulse{0%,100%{box-shadow:0 4px 20px rgba(0,0,0,0.15)}50%{box-shadow:0 4px 30px rgba(59,130,246,0.4)}}
@keyframes bellShake{0%,100%{transform:rotate(0)}25%{transform:rotate(15deg)}50%{transform:rotate(-10deg)}75%{transform:rotate(5deg)}}
.admin-notif{animation:notifSlideIn .4s ease,notifPulse 2s ease-in-out 3;border-left:5px solid #3b82f6;border-radius:12px;background:#fff;padding:16px 20px;margin-bottom:12px;box-shadow:0 8px 30px rgba(0,0,0,0.15);position:relative;font-size:14px;}
.admin-notif .notif-title{font-weight:700;font-size:16px;margin-bottom:4px;color:#1e293b;}
.admin-notif .notif-msg{color:#64748b;font-size:13px;margin-bottom:8px;}
.admin-notif .notif-link{color:#3b82f6;font-weight:600;text-decoration:none;font-size:13px;}
.admin-notif .notif-link:hover{text-decoration:underline;}
.admin-notif .notif-close{position:absolute;top:8px;right:12px;background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;line-height:1;}
.admin-notif .notif-close:hover{color:#ef4444;}
.bell-item{padding:10px 12px;border-radius:8px;margin-bottom:4px;cursor:pointer;transition:background .2s;display:flex;gap:10px;align-items:flex-start;text-decoration:none;color:inherit;}
.bell-item:hover{background:#f1f5f9;}
.bell-item.unread{background:#eff6ff;}
.bell-item .bell-icon{width:32px;height:32px;border-radius:50%;background:#3b82f6;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.bell-item .bell-icon i{color:#fff;font-size:12px;}
.bell-item .bell-content{flex:1;min-width:0;}
.bell-item .bell-title{font-weight:600;font-size:13px;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bell-item .bell-msg{font-size:11px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bell-item .bell-time{font-size:10px;color:#94a3b8;margin-top:2px;}
#bellBtn.bell-shake i{animation:bellShake .5s ease;}
</style>';
    echo '<script>
(function(){
    var bellOpen=false;
    var lastNotifCount=0;
    var allNotifs=[];

    function playNotifSound(){try{var a=new AudioContext();var o=a.createOscillator();var g=a.createGain();o.connect(g);g.connect(a.destination);o.frequency.value=880;o.type="sine";g.gain.value=0.3;o.start();g.gain.exponentialRampToValueAtTime(0.001,a.currentTime+0.3);o.stop(a.currentTime+0.3);}catch(e){}}

    function timeAgo(dateStr){
        var now=new Date();var d=new Date(dateStr.replace(" ","T"));var diff=Math.floor((now-d)/1000);
        if(diff<60)return"agora";if(diff<3600)return Math.floor(diff/60)+"min";if(diff<86400)return Math.floor(diff/3600)+"h";return Math.floor(diff/86400)+"d";
    }

    function updateBell(notifs){
        allNotifs=notifs;
        var badge=document.getElementById("bellBadge");
        var list=document.getElementById("bellList");
        var count=notifs.length;

        if(count>0){
            badge.textContent=count>99?"99+":count;
            badge.style.display="inline-block";
        } else {
            badge.style.display="none";
        }

        // Shake bell on new notifications
        if(count>lastNotifCount&&lastNotifCount>=0){
            var btn=document.getElementById("bellBtn");
            btn.classList.add("bell-shake");
            setTimeout(function(){btn.classList.remove("bell-shake");},600);
        }
        lastNotifCount=count;

        // Render dropdown list
        if(count===0){
            list.innerHTML="<div class=\"text-center text-muted py-4\" style=\"font-size:13px;\"><i class=\"fas fa-check-circle d-block mb-2\" style=\"font-size:24px;opacity:.4;\"></i>Nenhuma notificação pendente</div>";
            return;
        }
        var html="";
        notifs.slice(0,15).forEach(function(n){
            html+="<a href=\""+(n.link||"#")+"\" class=\"bell-item unread\" onclick=\"markAndGo(event,"+n.id+")\">";
            html+="<div class=\"bell-icon\"><i class=\"fas fa-comment-dots\"></i></div>";
            html+="<div class=\"bell-content\">";
            html+="<div class=\"bell-title\">"+n.titulo+"</div>";
            html+="<div class=\"bell-msg\">"+(n.mensagem||"")+"</div>";
            html+="<div class=\"bell-time\">"+timeAgo(n.created_at)+"</div>";
            html+="</div></a>";
        });
        list.innerHTML=html;
    }

    function checkNotifs(){
        fetch("/admin/demandas/api/notificacoes").then(function(r){return r.json()}).then(function(d){
            var notifs=d.notificacoes||[];
            updateBell(notifs);

            // Toast for brand-new ones
            var container=document.getElementById("admin-notif-container");
            var newCount=0;
            notifs.forEach(function(n){
                if(document.getElementById("notif-"+n.id))return;
                newCount++;
                var el=document.createElement("div");
                el.id="notif-"+n.id;
                el.className="admin-notif";
                el.innerHTML="<button class=\"notif-close\" onclick=\"dismissNotif("+n.id+",this)\">&times;</button>"
                    +"<div class=\"notif-title\">"+n.titulo+"</div>"
                    +"<div class=\"notif-msg\">"+(n.mensagem||"")+"</div>"
                    +(n.link?"<a href=\""+n.link+"\" class=\"notif-link\">Ver demanda &rarr;</a>":"");
                container.appendChild(el);
                // Auto-dismiss toast after 10s
                setTimeout(function(){if(el.parentNode){el.style.transition="all .3s";el.style.opacity="0";el.style.transform="translateX(100%)";setTimeout(function(){el.remove()},300);}},10000);
            });
            if(newCount>0)playNotifSound();
        }).catch(function(){});
    }

    window.toggleBellDropdown=function(){
        var dd=document.getElementById("bellDropdown");
        bellOpen=!bellOpen;
        dd.style.display=bellOpen?"block":"none";
    };

    window.markAndGo=function(e,id){
        fetch("/admin/demandas/api/notificacao/"+id+"/lida",{method:"POST"});
    };

    window.marcarTodasLidas=function(){
        if(!allNotifs.length)return;
        allNotifs.forEach(function(n){
            fetch("/admin/demandas/api/notificacao/"+n.id+"/lida",{method:"POST"});
        });
        updateBell([]);
        document.getElementById("bellDropdown").style.display="none";
        bellOpen=false;
    };

    window.dismissNotif=function(id,btn){
        fetch("/admin/demandas/api/notificacao/"+id+"/lida",{method:"POST"});
        var el=btn.closest(".admin-notif");
        el.style.transition="all .3s ease";el.style.transform="translateX(100%)";el.style.opacity="0";
        setTimeout(function(){el.remove()},300);
    };

    // Close dropdown on outside click
    document.addEventListener("click",function(e){
        var widget=document.getElementById("admin-bell-widget");
        if(bellOpen&&widget&&!widget.contains(e.target)){
            document.getElementById("bellDropdown").style.display="none";
            bellOpen=false;
        }
    });

    setInterval(checkNotifs,15000);
    setTimeout(checkNotifs,1500);
})();
</script>';
}

// Widget Co-Piloto Admin — injeta automaticamente em todas as páginas admin
function renderAdminCopilotoWidget() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    $widgetFile = __DIR__ . '/admin_copiloto_widget.php';
    if (file_exists($widgetFile)) {
        include $widgetFile;
    }
}

// Auto-injetar widget via register_shutdown_function (funciona em páginas inline e layout)
// NÃO injetar em rotas de API (JSON)
if (!defined('_ADMIN_COP_WIDGET_REGISTERED')) {
    define('_ADMIN_COP_WIDGET_REGISTERED', true);
    register_shutdown_function(function() {
        // Não injetar em respostas de API/JSON
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/') === 0) return;
        $contentType = '';
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') === 0) {
                $contentType = strtolower(trim(substr($h, 13)));
                break;
            }
        }
        if (strpos($contentType, 'application/json') !== false) return;
        if (strpos($contentType, 'application/pdf') !== false) return;
        renderAdminCopilotoWidget();
    });
}
?>
