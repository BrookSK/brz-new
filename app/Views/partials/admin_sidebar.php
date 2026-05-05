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
        'variacoes' => ['icon' => 'fas fa-sliders-h', 'label' => __('admin.menu.variations', 'Variações'), 'url' => '/admin/variacoes', 'roles' => ['admin','vendedor','suporte']],
        'lojas' => ['icon' => 'fas fa-store', 'label' => __('admin.menu.stores', 'Lojas'), 'url' => '/admin/lojas', 'roles' => ['admin','vendedor','suporte']],
        'categorias' => ['icon' => 'fas fa-tags', 'label' => __('admin.menu.categories', 'Categorias'), 'url' => '/admin/categorias', 'roles' => ['admin','vendedor','suporte']],
        'pedidos' => ['icon' => 'fas fa-shopping-cart', 'label' => __('admin.menu.orders', 'Pedidos'), 'url' => '/admin/pedidos', 'roles' => ['admin','vendedor','suporte']],
        'pedidos-conferencia' => ['icon' => 'fas fa-clipboard-check', 'label' => __('admin.menu.orders_audit', 'Pedidos para conferência'), 'url' => '/admin/pedidos/conferencia', 'roles' => ['admin','vendedor']],
        'pedidos-wp' => ['icon' => 'fab fa-wordpress', 'label' => __('admin.menu.orders_wp', 'Pedidos (WordPress)'), 'url' => '/admin/pedidos-wp', 'roles' => ['admin','vendedor','suporte']],
        'wp-estatisticas' => ['icon' => 'fas fa-chart-pie', 'label' => __('admin.menu.wp_orders_stats', 'Estatísticas (WP)'), 'url' => '/admin/pedidos-wp/estatisticas', 'roles' => ['admin']],
        'tickets' => ['icon' => 'fas fa-life-ring', 'label' => __('admin.menu.tickets', 'Tickets'), 'url' => '/admin/tickets', 'roles' => ['admin','suporte']],
        'pedidos-comissoes' => ['icon' => 'fas fa-percentage', 'label' => __('admin.menu.my_commissions', 'Minhas Comissões'), 'url' => '/admin/pedidos/comissoes', 'roles' => ['admin','vendedor']],
        'estoque' => ['icon' => 'fas fa-warehouse', 'label' => __('admin.menu.inventory', 'Estoque'), 'url' => '/admin/estoque', 'roles' => ['admin','vendedor','suporte']],
        'compras' => ['icon' => 'fas fa-shopping-basket', 'label' => __('admin.menu.purchases', 'Compras'), 'url' => '/admin/estoque/compras', 'roles' => ['admin','vendedor']],
        'relatorios' => ['icon' => 'fas fa-file-pdf', 'label' => __('admin.menu.reports', 'Relatórios'), 'url' => '/admin/estoque/relatorios', 'roles' => ['admin','vendedor']],
        'remessa-internacional' => ['icon' => 'fas fa-globe-americas', 'label' => __('admin.menu.international_shipment', 'Remessa Internacional'), 'url' => '/admin/remessa-internacional', 'roles' => ['admin','vendedor']],
        'remessa-wp' => ['icon' => 'fab fa-wordpress', 'label' => 'Remessa WP', 'url' => '/admin/remessa-wp', 'roles' => ['admin','vendedor','conferente']],
        'remessa-conferencia' => ['icon' => 'fas fa-clipboard-check', 'label' => 'Conferência de Remessa', 'url' => '/admin/remessa-conferencia', 'roles' => ['admin','vendedor','suporte','conferente']],
        'remessa-correios' => ['icon' => 'fas fa-shipping-fast', 'label' => __('admin.menu.post_office_shipment', 'Remessa Correios'), 'url' => '/admin/remessa-correios', 'roles' => ['admin','vendedor']],
        'correios-mundial' => ['icon' => 'fas fa-globe', 'label' => 'Correios Mundial', 'url' => '/admin/correios-mundial', 'roles' => ['admin','vendedor','suporte','redirecionador']],
        'remessa-shipstation' => ['icon' => 'fas fa-plane', 'label' => __('admin.menu.shipstation_shipment', 'Remessa ShipStation (UPS)'), 'url' => '/admin/remessa-shipstation', 'roles' => ['admin','vendedor']],
        'usuarios' => ['icon' => 'fas fa-users', 'label' => __('admin.menu.users', 'Usuários'), 'url' => '/admin/usuarios', 'roles' => ['admin','vendedor','suporte']],
        'pagamentos' => ['icon' => 'fas fa-credit-card', 'label' => __('admin.menu.payments', 'Pagamentos'), 'url' => '/admin/pagamentos', 'roles' => ['admin','vendedor']],
        'carnes' => ['icon' => 'fas fa-file-invoice-dollar', 'label' => 'Carnê Braziliana', 'url' => '/admin/carnes', 'roles' => ['admin','vendedor']],
        'relatorio-pedidos' => ['icon' => 'fas fa-file-alt', 'label' => 'Relatório Pedidos', 'url' => '/admin/relatorio-pedidos', 'roles' => ['admin','vendedor']],
        'relatorio-geral' => ['icon' => 'fas fa-chart-bar', 'label' => 'Resumo Financeiro', 'url' => '/admin/relatorio-geral', 'roles' => ['admin']],
        'payment-links' => ['icon' => 'fas fa-link', 'label' => 'Payment Links', 'url' => '/admin/payment-links', 'roles' => ['admin','vendedor']],
        'clube-recargas' => ['icon' => 'fas fa-wallet', 'label' => 'Recargas Clube', 'url' => '/admin/clube/recargas', 'roles' => ['admin','vendedor','suporte','redirecionador']],
        'backup' => ['icon' => 'fas fa-database', 'label' => 'Backup', 'url' => '/admin/backup', 'roles' => ['admin']],
        'oferta-gratuita' => ['icon' => 'fas fa-gift', 'label' => 'Oferta Gratuita', 'url' => '/admin/oferta-gratuita', 'roles' => ['admin']],
        'copiloto' => ['icon' => 'fas fa-robot', 'label' => 'Co-Piloto IA', 'url' => '/admin/copiloto', 'roles' => ['admin']],
        'quickbooks' => ['icon' => 'fas fa-calculator', 'label' => 'QuickBooks', 'url' => '/admin/quickbooks', 'roles' => ['admin']],
        'configuracoes' => ['icon' => 'fas fa-cog', 'label' => __('admin.menu.settings', 'Configurações'), 'url' => '/admin/configuracoes', 'roles' => ['admin']],
        'descontos' => ['icon' => 'fas fa-tag', 'label' => 'Autorizações Desconto', 'url' => '/admin/configuracoes/desconto/painel', 'roles' => ['admin']],
        'promocoes-auditoria' => ['icon' => 'fas fa-percent', 'label' => 'Auditoria Promoções', 'url' => '/admin/promocoes-auditoria', 'roles' => ['admin']],
        'promocoes-agendadas' => ['icon' => 'fas fa-calendar-alt', 'label' => 'Promoções Agendadas', 'url' => '/admin/promocoes-agendadas', 'roles' => ['admin', 'vendedor']],
        'faq' => ['icon' => 'fas fa-question-circle', 'label' => 'FAQ / Termos', 'url' => '/admin/faq', 'roles' => ['admin']],
        'comissoes-global' => ['icon' => 'fas fa-users-cog', 'label' => 'Comissões Global', 'url' => '/admin/comissoes-global', 'roles' => ['admin']],
        'resumo-financeiro' => ['icon' => 'fas fa-chart-bar', 'label' => 'Resumo Financeiro', 'url' => '/admin/resumo-financeiro', 'roles' => ['admin']],

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

    // Toggle mobile (collapse) - fica fixo no topo no mobile/tablet
    echo '<button class="btn btn-primary admin-menu-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-expanded="false" aria-label="' . htmlspecialchars(__('admin.open_menu', 'Abrir menu'), ENT_QUOTES, 'UTF-8') . '">
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

    echo '<nav id="adminSidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
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
                'Pedidos' => ['icon' => 'fas fa-shopping-cart', 'items' => ['pedidos', 'pedidos-conferencia', 'pedidos-wp', 'wp-estatisticas', 'tickets', 'pedidos-comissoes', 'carnes', 'payment-links']],
                'Catálogo' => ['icon' => 'fas fa-box', 'items' => ['produtos', 'grupos-compras', 'variacoes', 'lojas', 'categorias', 'promocoes-agendadas', 'promocoes-auditoria', 'oferta-gratuita']],
                'Estoque & Compras' => ['icon' => 'fas fa-warehouse', 'items' => ['estoque', 'compras', 'relatorios']],
                'Envios & Etiquetas' => ['icon' => 'fas fa-shipping-fast', 'items' => ['remessa-internacional', 'remessa-wp', 'remessa-conferencia', 'remessa-correios', 'correios-mundial', 'remessa-shipstation']],
                'Financeiro' => ['icon' => 'fas fa-credit-card', 'items' => ['pagamentos', 'relatorio-pedidos', 'relatorio-geral', 'resumo-financeiro', 'comissoes-global', 'clube-recargas', 'quickbooks']],
                'Redirecionamento' => ['icon' => 'fas fa-truck-fast', 'items' => ['redirecionamento-envios', 'redirecionamento-divergencias', 'redirecionamento-clientes', 'redirecionamento-tabela-pesos', 'redirecionamento-pagamentos', 'redirecionamento-comprovantes', 'redirecionamento-coletas']],
                'Configurações' => ['icon' => 'fas fa-cog', 'items' => ['configuracoes', 'usuarios', 'descontos', 'faq', 'copiloto', 'backup']],
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
                    <a class="nav-link sidebar-group-toggle' . ($groupActive ? ' active' : '') . '" href="#' . $groupId . '" data-bs-toggle="collapse" role="button" aria-expanded="' . ($groupActive ? 'true' : 'false') . '" style="display:flex;justify-content:space-between;align-items:center;">
                        <span><i class="fas fa-fw ' . ($group['icon'] ?? 'fas fa-folder') . '"></i> ' . htmlspecialchars($groupName) . '</span>
                        <i class="fas fa-chevron-down" style="font-size:10px;transition:transform .2s;' . ($groupActive ? 'transform:rotate(180deg);' : '') . '"></i>
                    </a>
                    <div class="collapse ' . $collapseClass . '" id="' . $groupId . '">
                        <ul class="nav flex-column" style="padding-left:12px;">';

                foreach ($groupItems as $key => $item) {
                    $activeClass = ($activePage === $key) ? 'active' : '';
                    $label = $item['label'];
                    // Remover prefixo (RED) dos labels dentro do grupo
                    $label = preg_replace('/^\(RED\)\s*/i', '', $label);
                    if ($key === 'tickets' && $unreadTickets > 0) {
                        $label .= ' <span class="badge ms-2" style="background: #ffffff !important; color: #0b1f3a !important; border: 1px solid rgba(11, 31, 58, 0.25) !important; font-weight: 600;">' . (int) $unreadTickets . '</span>';
                    }
                    if ($key === 'pedidos-conferencia' && $pendentesConferencia > 0) {
                        $label .= ' <span class="badge ms-2" style="background: #ffffff !important; color: #0b1f3a !important; border: 1px solid rgba(11, 31, 58, 0.25) !important; font-weight: 600;">' . (int) $pendentesConferencia . '</span>';
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

        .sidebar .sidebar-brand .admin-sidebar-logo {
            max-height: 44px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            display: inline-block;
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
