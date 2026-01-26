<?php
namespace App\Controllers;

use App\Core\Request;

class AdminDashboardController extends Controller {
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Estatísticas
            $stats = [];
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos");
            $stats['produtos_total'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1");
            $stats['produtos_ativos'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos");
            $stats['pedidos_total'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
            $stats['usuarios_total'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $pdo->query("SELECT SUM(valor_total) as total FROM pedidos WHERE status = 'pago'");
            $stats['faturamento_total'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Pedidos recentes
            $stmt = $pdo->query("
                SELECT p.*, u.nome as cliente_nome 
                FROM pedidos p 
                LEFT JOIN usuarios u ON p.usuario_id = u.id 
                ORDER BY p.created_at DESC 
                LIMIT 5
            ");
            $pedidos_recentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Produtos mais vendidos
            $stmt = $pdo->query("
                SELECT pr.nome, COUNT(ip.produto_id) as vendas, SUM(ip.quantidade) as quantidade
                FROM itens_pedido ip
                JOIN produtos pr ON ip.produto_id = pr.id
                JOIN pedidos p ON ip.pedido_id = p.id
                WHERE p.status = 'pago'
                GROUP BY pr.id, pr.nome
                ORDER BY vendas DESC
                LIMIT 5
            ");
            $produtos_mais_vendidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            $stats = ['produtos_total' => 0, 'produtos_ativos' => 0, 'pedidos_total' => 0, 'usuarios_total' => 0, 'faturamento_total' => 0];
            $pedidos_recentes = [];
            $produtos_mais_vendidos = [];
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .quick-action-card { transition: all 0.3s; cursor: pointer; }
        .quick-action-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('dashboard');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync"></i> Atualizar
                        </button>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Produtos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['produtos_total'] . '</div>
                                        <div class="text-xs text-muted">' . $stats['produtos_ativos'] . ' ativos</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-box fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Pedidos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['pedidos_total'] . '</div>
                                        <div class="text-xs text-muted">Total</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Usuários</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['usuarios_total'] . '</div>
                                        <div class="text-xs text-muted">Cadastrados</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Faturamento</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ ' . number_format($stats['faturamento_total'], 2, ',', '.') . '</div>
                                        <div class="text-xs text-muted">Total</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="h5 mb-3">Ações Rápidas</h3>
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/produtos/novo" class="text-decoration-none">
                                    <div class="card quick-action-card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-plus fa-3x mb-3"></i>
                                            <h5 class="card-title">Novo Produto</h5>
                                            <p class="card-text small">Adicionar produto</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/pedidos" class="text-decoration-none">
                                    <div class="card quick-action-card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                            <h5 class="card-title">Pedidos</h5>
                                            <p class="card-text small">Gerenciar pedidos</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/usuarios" class="text-decoration-none">
                                    <div class="card quick-action-card bg-info text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-users fa-3x mb-3"></i>
                                            <h5 class="card-title">Usuários</h5>
                                            <p class="card-text small">Gerenciar clientes</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/configuracoes" class="text-decoration-none">
                                    <div class="card quick-action-card bg-warning text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-cog fa-3x mb-3"></i>
                                            <h5 class="card-title">Configurações</h5>
                                            <p class="card-text small">Configurar loja</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Pedidos Recentes</h6>
                                <a href="/admin/pedidos" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                            </div>
                            <div class="card-body">';
                            
                            if (!empty($pedidos_recentes)) {
                                foreach ($pedidos_recentes as $pedido) {
                                    echo '<div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong> - ' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '
                                            <br><small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-' . ($pedido['status'] == 'pago' ? 'success' : 'warning') . '">' . ucfirst($pedido['status']) . '</span>
                                            <br><strong>R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</strong>
                                        </div>
                                    </div>';
                                }
                            } else {
                                echo '<p class="text-muted text-center">Nenhum pedido encontrado</p>';
                            }
                            
                            echo '</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Produtos Mais Vendidos</h6>
                                <a href="/admin/produtos" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                            </div>
                            <div class="card-body">';
                            
                            if (!empty($produtos_mais_vendidos)) {
                                foreach ($produtos_mais_vendidos as $produto) {
                                    echo '<div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong>' . htmlspecialchars($produto['nome']) . '</strong>
                                            <br><small class="text-muted">' . $produto['vendas'] . ' vendas</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info">' . $produto['quantidade'] . ' unidades</span>
                                        </div>
                                    </div>';
                                }
                            } else {
                                echo '<p class="text-muted text-center">Nenhuma venda encontrada</p>';
                            }
                            
                            echo '</div>
                        </div>
                    </div>
                </div>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }
}
