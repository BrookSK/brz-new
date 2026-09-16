<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminController extends Controller {
    
    public function dashboard(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);
        // Conexão com o banco
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        } catch (\Exception $e) {
            // Se não conseguir conectar ao banco, mostra dashboard básico
            include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

            echo '<!DOCTYPE html>
<html lang="' . \App\Core\I18n::getLocaleHtml() . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(__('admin.core.admin_panel_title', 'Braziliana Admin - Painel Administrativo'), ENT_QUOTES, 'UTF-8') . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        
            // Renderizar estilos do menu
            renderAdminSidebarStyles();
        
            echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
            // Renderizar menu lateral usando o partial
            renderAdminSidebar('dashboard');
        
            echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">' . __('admin.core.dashboard_admin', 'Dashboard Administrativo') . '</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar-alt"></i> ' . __('admin.core.today', 'Hoje') . '
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download"></i> ' . __('admin.core.export', 'Exportar') . '
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">' . __('admin.core.sales_today', 'Vendas Hoje') . '</h5>
                                <h3>R$ 2.543</h3>
                                <small><i class="fas fa-arrow-up"></i> ' . __('admin.core.pct_vs_yesterday', '12% vs ontem') . '</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">' . __('admin.core.orders', 'Pedidos') . '</h5>
                                <h3>47</h3>
                                <small><i class="fas fa-arrow-up"></i> ' . __('admin.core.pct_vs_week', '8% vs semana') . '</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">' . __('admin.core.products', 'Produtos') . '</h5>
                                <h3>1.234</h3>
                                <small>' . __('admin.core.total_active', 'Total ativos') . '</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">' . __('admin.core.users', 'Usuários') . '</h5>
                                <h3>892</h3>
                                <small><i class="fas fa-arrow-up"></i> ' . __('admin.core.new_count', '23 novos') . '</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos e Tabelas -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line me-2"></i>' . __('admin.core.sales_last_7_days', 'Vendas - Últimos 7 dias') . '</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-shopping-cart me-2"></i>' . __('admin.core.recent_orders', 'Pedidos Recentes') . '</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">' . __('admin.core.order_number', 'Pedido #{n}', ['n' => '1234']) . '</h6>
                                            <small class="text-muted">João Silva</small>
                                        </div>
                                        <span class="badge bg-success">R$ 234</span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">' . __('admin.core.order_number', 'Pedido #{n}', ['n' => '1235']) . '</h6>
                                            <small class="text-muted">Maria Santos</small>
                                        </div>
                                        <span class="badge bg-warning">R$ 189</span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">' . __('admin.core.order_number', 'Pedido #{n}', ['n' => '1236']) . '</h6>
                                            <small class="text-muted">Pedro Costa</small>
                                        </div>
                                        <span class="badge bg-primary">R$ 456</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de vendas
        const ctx = document.getElementById(\'salesChart\').getContext(\'2d\');
        const salesChart = new Chart(ctx, {
            type: \'line\',
            data: {
                labels: [\'' . htmlspecialchars(__('admin.core.day_mon', 'Seg'), ENT_QUOTES, 'UTF-8') . '\', \'' . htmlspecialchars(__('admin.core.day_tue', 'Ter'), ENT_QUOTES, 'UTF-8') . '\', \'' . htmlspecialchars(__('admin.core.day_wed', 'Qua'), ENT_QUOTES, 'UTF-8') . '\', \'' . htmlspecialchars(__('admin.core.day_thu', 'Qui'), ENT_QUOTES, 'UTF-8') . '\', \'' . htmlspecialchars(__('admin.core.day_fri', 'Sex'), ENT_QUOTES, 'UTF-8') . '\', \'' . htmlspecialchars(__('admin.core.day_sat', 'Sáb'), ENT_QUOTES, 'UTF-8') . '\', \'' . htmlspecialchars(__('admin.core.day_sun', 'Dom'), ENT_QUOTES, 'UTF-8') . '\'],
                datasets: [{
                    label: \'' . htmlspecialchars(__('admin.core.sales', 'Vendas'), ENT_QUOTES, 'UTF-8') . '\',
                    data: [1200, 1900, 1500, 2500, 2200, 3000, 2800],
                    borderColor: \'rgb(75, 192, 192)\',
                    backgroundColor: \'rgba(75, 192, 192, 0.2)\',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>';
            exit;
        }
        
        // Se conectou ao banco, continua com estatísticas...
        $stats = [];
        $stats['produtos_total'] = 0;
        $stats['produtos_ativos'] = 0;
        $stats['pedidos_total'] = 0;
        $stats['pedidos_hoje'] = 0;
        $stats['usuarios_total'] = 0;
        $stats['usuarios_novos'] = 0;
        $stats['faturamento_total'] = 0;
        $stats['faturamento_mes'] = 0;
        
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="' . \App\Core\I18n::getLocaleHtml() . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(__('admin.core.dashboard_title', 'Dashboard - Braziliana Admin'), ENT_QUOTES, 'UTF-8') . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('dashboard');
        
        echo '<!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">' . __('admin.core.dashboard', 'Dashboard') . '</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync"></i> ' . __('admin.core.refresh', 'Atualizar') . '
                        </button>
                    </div>
                </div>';
                
                // Cards de Estatísticas
                echo '<div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">' . __('admin.core.products', 'Produtos') . '</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['produtos_total'] . '</div>
                                        <div class="text-xs text-muted">' . __('admin.core.n_active', '{n} ativos', ['n' => $stats['produtos_ativos']]) . '</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">' . __('admin.core.orders', 'Pedidos') . '</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['pedidos_total'] . '</div>
                                        <div class="text-xs text-muted">' . __('admin.core.n_today', '{n} hoje', ['n' => $stats['pedidos_hoje']]) . '</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">' . __('admin.core.users', 'Usuários') . '</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['usuarios_total'] . '</div>
                                        <div class="text-xs text-muted">' . __('admin.core.n_new_7d', '{n} novos (7d)', ['n' => $stats['usuarios_novos']]) . '</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">' . __('admin.core.revenue', 'Faturamento') . '</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ ' . number_format($stats['faturamento_total'], 2, ',', '.') . '</div>
                                        <div class="text-xs text-muted">R$ ' . number_format($stats['faturamento_mes'], 2, ',', '.') . ' ' . __('admin.core.this_month', 'este mês') . '</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ações Rápidas -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="h5 mb-3">' . __('admin.core.quick_actions', 'Ações Rápidas') . '</h3>
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/novo-produto" class="text-decoration-none">
                                    <div class="card quick-action-card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-plus fa-3x mb-3"></i>
                                            <h5 class="card-title">' . __('admin.core.new_product', 'Novo Produto') . '</h5>
                                            <p class="card-text small">' . __('admin.core.new_product_desc', 'Adicionar novo produto ao catálogo') . '</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/pedidos" class="text-decoration-none">
                                    <div class="card quick-action-card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                            <h5 class="card-title">' . __('admin.core.view_orders', 'Ver Pedidos') . '</h5>
                                            <p class="card-text small">' . __('admin.core.view_orders_desc', 'Gerenciar pedidos recentes') . '</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/usuarios" class="text-decoration-none">
                                    <div class="card quick-action-card bg-info text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-users fa-3x mb-3"></i>
                                            <h5 class="card-title">' . __('admin.core.users', 'Usuários') . '</h5>
                                            <p class="card-text small">' . __('admin.core.manage_clients', 'Gerenciar clientes') . '</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/configuracoes" class="text-decoration-none">
                                    <div class="card quick-action-card bg-warning text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-cog fa-3x mb-3"></i>
                                            <h5 class="card-title">' . __('admin.core.settings', 'Configurações') . '</h5>
                                            <p class="card-text small">' . __('admin.core.configure_store', 'Configurar loja') . '</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pedidos Recentes e Produtos Mais Vendidos -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">' . __('admin.core.recent_orders', 'Pedidos Recentes') . '</h6>
                                <a href="/admin/pedidos" class="btn btn-sm btn-outline-primary">' . __('admin.core.view_all', 'Ver Todos') . '</a>
                            </div>
                            <div class="card-body">';
                            
                            if (!empty($pedidos_recentes)) {
                                foreach ($pedidos_recentes as $pedido) {
                                    echo '<div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong>#' . $pedido['id'] . '</strong> - ' . htmlspecialchars($pedido['cliente_nome'] ?? __('admin.core.visitor', 'Visitante')) . '
                                            <br><small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-' . ($pedido['status'] == 'pago' ? 'success' : 'warning') . '">' . ucfirst($pedido['status']) . '</span>
                                            <br><strong>R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</strong>
                                        </div>
                                    </div>';
                                }
                            } else {
                                echo '<p class="text-muted text-center">' . __('admin.core.no_orders_found', 'Nenhum pedido encontrado') . '</p>';
                            }
                            
                            echo '</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">' . __('admin.core.best_sellers', 'Produtos Mais Vendidos') . '</h6>
                                <a href="/admin/produtos" class="btn btn-sm btn-outline-primary">' . __('admin.core.view_all', 'Ver Todos') . '</a>
                            </div>
                            <div class="card-body">';
                            
                            if (!empty($produtos_mais_vendidos)) {
                                foreach ($produtos_mais_vendidos as $produto) {
                                    echo '<div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong>' . htmlspecialchars($produto['nome']) . '</strong>
                                            <br><small class="text-muted">' . __('admin.core.n_sales', '{n} vendas', ['n' => $produto['vendas']]) . '</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info">' . __('admin.core.n_units', '{n} unidades', ['n' => $produto['quantidade']]) . '</span>
                                        </div>
                                    </div>';
                                }
                            } else {
                                echo '<p class="text-muted text-center">' . __('admin.core.no_sales_found', 'Nenhuma venda encontrada') . '</p>';
                            }
                            
                            echo '</div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        
        exit;
    }
    
    public function pedidos(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pagina = $request->getParam('pagina', 1);
            $limite = 10;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            $status = $request->getParam('status', '');
            
            $sql = "SELECT p.*, u.nome as cliente_nome, u.email as cliente_email 
                    FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE 1=1";
            $params = [];
            
            if (!empty($busca)) {
                $sql .= " AND (p.id LIKE :busca OR u.nome LIKE :busca OR u.email LIKE :busca)";
                $params[':busca'] = "%{$busca}%";
            }
            if (!empty($status)) {
                $sql .= " AND p.status = :status";
                $params[':status'] = $status;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT :limite OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) $stmt->bindValue($key, $value);
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Total para paginação
            $sqlTotal = "SELECT COUNT(*) as total FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE 1=1";
            $paramsTotal = [];
            if (!empty($busca)) {
                $sqlTotal .= " AND (p.id LIKE :busca OR u.nome LIKE :busca OR u.email LIKE :busca)";
                $paramsTotal[':busca'] = "%{$busca}%";
            }
            if (!empty($status)) {
                $sqlTotal .= " AND p.status = :status";
                $paramsTotal[':status'] = $status;
            }
            
            $stmtTotal = $pdo->prepare($sqlTotal);
            foreach ($paramsTotal as $key => $value) $stmtTotal->bindValue($key, $value);
            $stmtTotal->execute();
            $total = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($total / $limite);
            
        } catch (\Exception $e) {
            $pedidos = [];
            $total = 0;
            $totalPaginas = 0;
        }
        
        echo '<!DOCTYPE html>
<html lang="' . \App\Core\I18n::getLocaleHtml() . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(__('admin.core.orders_title', 'Pedidos - Braziliana Admin'), ENT_QUOTES, 'UTF-8') . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
        .order-card { transition: transform 0.2s; }
        .status-pendente { background-color: #ffc107; }
        .status-pago { background-color: #28a745; }
        .status-cancelado { background-color: #dc3545; }
        .status-enviado { background-color: #17a2b8; }
        .status-entregue { background-color: #6f42c1; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="sidebar-brand-text mx-3">Braziliana Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>' . __('admin.core.nav_dashboard', 'Dashboard') . '</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>' . __('admin.core.nav_products', 'Produtos') . '</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>' . __('admin.core.nav_orders', 'Pedidos') . '</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>' . __('admin.core.nav_users', 'Usuários') . '</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pagamentos"><i class="fas fa-fw fa-credit-card"></i><span>' . __('admin.core.nav_payments', 'Pagamentos') . '</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/configuracoes"><i class="fas fa-fw fa-cog"></i><span>' . __('admin.core.nav_settings', 'Configurações') . '</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>' . __('admin.core.nav_logout', 'Sair') . '</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">' . __('admin.core.orders_found', 'Pedidos ({n} encontrados)', ['n' => $total]) . '</h1>
                </div>
                
                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="busca" placeholder="' . htmlspecialchars(__('admin.core.search_orders_placeholder', 'Buscar por ID, cliente ou email'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($busca) . '">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">' . __('admin.core.all_statuses', 'Todos os status') . '</option>
                                    <option value="pendente" ' . ($status === 'pendente' ? 'selected' : '') . '>' . __('admin.core.status_pending', 'Pendente') . '</option>
                                    <option value="pago" ' . ($status === 'pago' ? 'selected' : '') . '>' . __('admin.core.status_paid', 'Pago') . '</option>
                                    <option value="enviado" ' . ($status === 'enviado' ? 'selected' : '') . '>' . __('admin.core.status_shipped', 'Enviado') . '</option>
                                    <option value="entregue" ' . ($status === 'entregue' ? 'selected' : '') . '>' . __('admin.core.status_delivered', 'Entregue') . '</option>
                                    <option value="cancelado" ' . ($status === 'cancelado' ? 'selected' : '') . '>' . __('admin.core.status_canceled', 'Cancelado') . '</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> ' . __('admin.core.filter', 'Filtrar') . '</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Lista de Pedidos -->
                <div class="row">';
                
                if (!empty($pedidos)) {
                    foreach ($pedidos as $pedido) {
                        $statusClass = 'status-' . $pedido['status'];
                        echo '<div class="col-md-6 col-lg-4 mb-4">
                            <div class="card order-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong>
                                    <span class="badge ' . $statusClass . '">' . ucfirst($pedido['status']) . '</span>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title">' . htmlspecialchars($pedido['cliente_nome'] ?? __('admin.core.visitor', 'Visitante')) . '</h6>
                                    <p class="card-text text-muted small">' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                    <p class="card-text">
                                        <small class="text-muted">' . __('admin.core.date_label', 'Data:') . ' ' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small><br>
                                        <strong>' . __('admin.core.total_label', 'Total:') . ' R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</strong>
                                    </p>
                                    <div class="d-flex justify-content-between">
                                        <a href="/admin/pedido-detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> ' . __('admin.core.view', 'Ver') . '
                                        </a>
                                        <select class="form-select form-select-sm" onchange="location.href=\'/admin/atualizar-status-pedido/' . $pedido['id'] . '/\'+this.value">
                                            <option value="">' . __('admin.core.change', 'Alterar') . '</option>
                                            <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>' . __('admin.core.status_pending', 'Pendente') . '</option>
                                            <option value="pago" ' . ($pedido['status'] == 'pago' ? 'selected' : '') . '>' . __('admin.core.status_paid', 'Pago') . '</option>
                                            <option value="enviado" ' . ($pedido['status'] == 'enviado' ? 'selected' : '') . '>' . __('admin.core.status_shipped', 'Enviado') . '</option>
                                            <option value="entregue" ' . ($pedido['status'] == 'entregue' ? 'selected' : '') . '>' . __('admin.core.status_delivered', 'Entregue') . '</option>
                                            <option value="cancelado" ' . ($pedido['status'] == 'cancelado' ? 'selected' : '') . '>' . __('admin.core.status_canceled', 'Cancelado') . '</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>';
                    }
                } else {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">' . __('admin.core.no_orders_found', 'Nenhum pedido encontrado') . '</h5>
                    </div>';
                }
                
                echo '</div>
                
                <!-- Paginação -->';
                if ($totalPaginas > 1) {
                    echo '<nav class="mt-4"><ul class="pagination justify-content-center">';
                    for ($i = 1; $i <= $totalPaginas; $i++) {
                        $url = "/admin/pedidos?pagina={$i}";
                        if (!empty($busca)) $url .= "&busca=" . urlencode($busca);
                        if (!empty($status)) $url .= "&status={$status}";
                        echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">
                            <a class="page-link" href="' . $url . '">' . $i . '</a>
                        </li>';
                    }
                    echo '</ul></nav>';
                }
                echo '</main>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>';
        
        exit;
    }
    
    public function novoProduto(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        // Conexão com o banco
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        
        // Obter categorias
        $stmtCats = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
        $categorias = $stmtCats->fetchAll(\PDO::FETCH_ASSOC);
        
        // Layout Bootstrap
        echo '<!DOCTYPE html>
<html lang="' . \App\Core\I18n::getLocaleHtml() . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(__('admin.core.new_product_title', 'Novo Produto - Braziliana Admin'), ENT_QUOTES, 'UTF-8') . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 0.35rem;
            margin: 0.2rem 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .sidebar-brand {
            color: #fff;
            font-weight: bold;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="sidebar-brand-text mx-3">Braziliana Admin</div>
                    </a>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/dashboard">
                                <i class="fas fa-fw fa-tachometer-alt"></i>
                                <span>' . __('admin.core.nav_dashboard', 'Dashboard') . '</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="/admin/produtos">
                                <i class="fas fa-fw fa-box"></i>
                                <span>' . __('admin.core.nav_products', 'Produtos') . '</span>
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="sidebar-divider">
                    
                    <div class="nav-item">
                        <a class="nav-link" href="/logout">
                            <i class="fas fa-fw fa-sign-out-alt"></i>
                            <span>' . __('admin.core.nav_logout', 'Sair') . '</span>
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">' . __('admin.core.new_product', 'Novo Produto') . '</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="/admin/produtos" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> ' . __('admin.core.back', 'Voltar') . '
                        </a>
                    </div>
                </div>
                
                <form method="POST" action="/admin/salvar-produto" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Coluna Esquerda -->
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">' . __('admin.core.basic_info', 'Informações Básicas') . '</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="nome" class="form-label">' . __('admin.core.product_name', 'Nome do Produto *') . '</label>
                                        <input type="text" class="form-control" name="nome" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sku" class="form-label">' . __('admin.core.sku', 'SKU *') . '</label>
                                        <input type="text" class="form-control" name="sku" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descricao_curta" class="form-label">' . __('admin.core.short_desc', 'Descrição Curta') . '</label>
                                        <textarea class="form-control" name="descricao_curta" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descricao_completa" class="form-label">' . __('admin.core.full_desc', 'Descrição Completa') . '</label>
                                        <textarea class="form-control" name="descricao_completa" rows="5"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="categoria_id" class="form-label">' . __('admin.core.category', 'Categoria') . '</label>
                                        <select class="form-select" name="categoria_id" required>
                                            <option value="">' . __('admin.core.select_category', 'Selecione uma categoria') . '</option>';
                                            foreach ($categorias as $cat) {
                                                echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['nome']) . '</option>';
                                            }
        echo '</select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">' . __('admin.core.product_images', 'Imagens do Produto') . '</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="imagens[]" class="form-label">' . __('admin.core.product_images', 'Imagens do Produto') . '</label>
                                        <input type="file" class="form-control" name="imagens[]" multiple accept="image/*">
                                        <div class="form-text">' . __('admin.core.product_images_help', 'Selecione várias imagens para a galeria do produto') . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Coluna Direita -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">' . __('admin.core.price_stock', 'Preço e Estoque') . '</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="valor" class="form-label">' . __('admin.core.price', 'Preço *') . '</label>
                                        <div class="input-group">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" class="form-control" name="valor" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="moeda" class="form-label">' . __('admin.core.currency', 'Moeda') . '</label>
                                        <select class="form-select" name="moeda">
                                            <option value="BRL" selected>BRL</option>
                                            <option value="USD">USD</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="peso" class="form-label">' . __('admin.core.weight_kg', 'Peso (kg)') . '</label>
                                        <input type="text" class="form-control" name="peso" placeholder="0.0">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="estoque" class="form-label">' . __('admin.core.stock', 'Estoque') . '</label>
                                        <input type="number" class="form-control" name="estoque" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">' . __('admin.core.status', 'Status') . '</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">' . __('admin.core.status', 'Status') . '</label>
                                        <select class="form-select" name="status">
                                            <option value="1" selected>' . __('admin.core.active', 'Ativo') . '</option>
                                            <option value="0">' . __('admin.core.inactive', 'Inativo') . '</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ativo" class="form-label">' . __('admin.core.visible_on_site', 'Visível no site') . '</label>
                                        <select class="form-select" name="ativo">
                                            <option value="1" selected>' . __('admin.core.yes', 'Sim') . '</option>
                                            <option value="0">' . __('admin.core.no', 'Não') . '</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> ' . __('admin.core.save_product', 'Salvar Produto') . '
                                </button>
                                <a href="/admin/produtos" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> ' . __('admin.core.cancel', 'Cancelar') . '
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        
        exit;
    }
    
    public function salvarProduto(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        
        try {
            $pdo->beginTransaction();
            
            // Inserir produto com todos os campos
            $sql = "
                INSERT INTO produtos (
                    nome, sku, descricao_curta, descricao_completa, categoria_id, 
                    valor, moeda, peso, estoque, status, ativo, 
                    created_at, updated_at
                ) VALUES (
                    :nome, :sku, :descricao_curta, :descricao_completa, :categoria_id,
                    :valor, :moeda, :peso, :estoque, :status, :ativo,
                    NOW(), NOW()
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nome', $request->getParam('nome'));
            $stmt->bindParam(':sku', $request->getParam('sku'));
            $stmt->bindParam(':descricao_curta', $request->getParam('descricao_curta'));
            $stmt->bindParam(':descricao_completa', $request->getParam('descricao_completa'));
            $stmt->bindParam(':categoria_id', $request->getParam('categoria_id'));
            $stmt->bindParam(':valor', str_replace(',', '.', $request->getParam('valor')));
            $stmt->bindParam(':moeda', $request->getParam('moeda'));
            $stmt->bindParam(':peso', $request->getParam('peso'));
            $stmt->bindParam(':estoque', $request->getParam('estoque'));
            $stmt->bindParam(':status', $request->getParam('status'));
            $stmt->bindParam(':ativo', $request->getParam('ativo'));
            
            $stmt->execute();
            $produtoId = $pdo->lastInsertId();
            
            // Processar upload de imagens (se houver)
            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $imagens = $_FILES['imagens'];
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/produtos/';
                
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($imagens['name'] as $key => $name) {
                    if ($imagens['error'][$key] === UPLOAD_ERR_OK) {
                        $fileName = time() . '_' . $key . '_' . $name;
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($imagens['tmp_name'][$key], $filePath)) {
                            // Inserir no banco
                            $sqlFoto = "
                                INSERT INTO produto_fotos (
                                    produto_id, nome_arquivo, legenda, principal, ordem, created_at
                                ) VALUES (
                                    :produto_id, :nome_arquivo, :legenda, :principal, :ordem, NOW()
                                )
                            ";
                            
                            $stmtFoto = $pdo->prepare($sqlFoto);
                            $stmtFoto->bindParam(':produto_id', $produtoId);
                            $stmtFoto->bindParam(':nome_arquivo', $fileName);
                            $stmtFoto->bindValue(':legenda', '');
                            $stmtFoto->bindValue(':principal', $key === 0 ? 1 : 0); // Primeira imagem como principal
                            $stmtFoto->bindValue(':ordem', $key);
                            $stmtFoto->execute();
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            // Redirecionar para edição
            header('Location: /admin/editar-produto/' . $produtoId);
            exit;
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            
            // Exibir erro com layout Bootstrap
            echo '<!DOCTYPE html>
<html lang="' . \App\Core\I18n::getLocaleHtml() . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(__('admin.core.error_title', 'Erro - Braziliana Admin'), ENT_QUOTES, 'UTF-8') . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h4>' . __('admin.core.save_product_error', 'Erro ao salvar produto') . '</h4>
            <p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>
            <a href="/admin/novo-produto" class="btn btn-secondary">' . __('admin.core.back', 'Voltar') . '</a>
        </div>
    </div>
</body>
</html>';
            exit;
        }
    }
    
    public function editarProduto(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $produtoId = $request->getParam('id');
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
        $stmt->execute([$produtoId]);
        $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$produto) {
            echo htmlspecialchars(__('admin.core.product_not_found', 'Produto não encontrado!'), ENT_QUOTES, 'UTF-8');
            exit;
        }
        
        echo '<h1>' . __('admin.core.edit_product', 'Editar Produto') . '</h1>';
        echo '<form method="POST" action="/admin/atualizar-produto/' . $produtoId . '">';
        echo '<input type="hidden" name="id" value="' . $produtoId . '">';
        echo '<input type="text" name="nome" value="' . htmlspecialchars($produto['nome']) . '" required><br><br>';
        echo '<input type="text" name="sku" value="' . htmlspecialchars($produto['sku']) . '" required><br><br>';
        echo '<button type="submit">' . __('admin.core.update', 'Atualizar') . '</button>';
        echo '<a href="/admin/produtos">' . __('admin.core.cancel', 'Cancelar') . '</a>';
        echo '</form>';
        exit;
    }
    
    public function atualizarProduto(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $produtoId = $request->getParam('id');
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        
        try {
            $stmt = $pdo->prepare("UPDATE produtos SET nome = ?, sku = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$request->getParam('nome'), $request->getParam('sku'), $produtoId]);
            
            header('Location: /admin/editar-produto/' . $produtoId);
            exit;
        } catch (\Exception $e) {
            echo htmlspecialchars(__('admin.core.error_prefix', 'Erro: {msg}', ['msg' => $e->getMessage()]), ENT_QUOTES, 'UTF-8');
            exit;
        }
    }
    
    public function excluirProduto(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        error_log('🔍 [ADMIN-EXCLUIR] Método excluirProduto chamado com ID: ' . $request->getParam('id'));
        
        $produtoId = $request->getParam('id');
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        
        try {
            error_log('🔍 [ADMIN-EXCLUIR] Iniciando transação');
            $pdo->beginTransaction();
            
            // Excluir fotos
            error_log('🔍 [ADMIN-EXCLUIR] Excluindo fotos do produto ' . $produtoId);
            $stmt = $pdo->prepare("DELETE FROM produto_fotos WHERE produto_id = ?");
            $stmt->execute([$produtoId]);
            
            // Excluir produto
            error_log('🔍 [ADMIN-EXCLUIR] Excluindo produto ' . $produtoId);
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$produtoId]);
            
            $pdo->commit();
            
            error_log('🔍 [ADMIN-EXCLUIR] Produto excluído com sucesso, redirecionando...');
            
            // Redirecionar para lista
            header('Location: /admin/produtos');
            exit;
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log('❌ [ADMIN-EXCLUIR] Erro: ' . $e->getMessage());
            echo '<div class="alert alert-danger">' . __('admin.core.delete_product_error', 'Erro ao excluir produto: {msg}', ['msg' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')]) . '</div>';
            echo '<a href="/admin/produtos" class="btn btn-secondary">' . __('admin.core.back', 'Voltar') . '</a>';
            exit;
        }
    }
}
