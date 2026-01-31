<?php
namespace App\Controllers;

use App\Core\Request;

class AdminDashboardController extends Controller {

    private function tableExists(\PDO $pdo, $table) {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function columnExists(\PDO $pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function safeScalar(\PDO $pdo, $sql) {
        try {
            $stmt = $pdo->query($sql);
            return $stmt ? ($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Estatísticas
            $stats = [];

            $stats['produtos_total'] = $this->safeScalar($pdo, "SELECT COUNT(*) as total FROM produtos");

            $produtosAtivoCol = $this->columnExists($pdo, 'produtos', 'ativo') ? 'ativo' : ($this->columnExists($pdo, 'produtos', 'active') ? 'active' : null);
            if ($produtosAtivoCol) {
                $stats['produtos_ativos'] = $this->safeScalar($pdo, "SELECT COUNT(*) as total FROM produtos WHERE {$produtosAtivoCol} = 1");
            } else {
                $stats['produtos_ativos'] = 0;
            }

            $stats['pedidos_total'] = $this->safeScalar($pdo, "SELECT COUNT(*) as total FROM pedidos");
            $stats['usuarios_total'] = $this->safeScalar($pdo, "SELECT COUNT(*) as total FROM usuarios");

            $pedidoTotalCol = $this->columnExists($pdo, 'pedidos', 'valor_total') ? 'valor_total' : ($this->columnExists($pdo, 'pedidos', 'total') ? 'total' : null);
            if ($pedidoTotalCol) {
                $stats['faturamento_total'] = $this->safeScalar($pdo, "SELECT COALESCE(SUM({$pedidoTotalCol}),0) as total FROM pedidos WHERE status = 'pago'");
            } else {
                $stats['faturamento_total'] = 0;
            }
            
            // Pedidos recentes
            $usuarioNomeCol = $this->columnExists($pdo, 'usuarios', 'nome') ? 'nome' : ($this->columnExists($pdo, 'usuarios', 'name') ? 'name' : null);
            $pedidosSql = "SELECT p.*";
            if ($usuarioNomeCol) {
                $pedidosSql .= ", u.{$usuarioNomeCol} as cliente_nome";
            } else {
                $pedidosSql .= ", '' as cliente_nome";
            }
            $pedidosSql .= " FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id ORDER BY p.created_at DESC LIMIT 5";
            $stmt = $pdo->query($pedidosSql);
            $pedidos_recentes = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            
            // Produtos mais vendidos
            $itensTable = $this->tableExists($pdo, 'pedido_itens') ? 'pedido_itens' : ($this->tableExists($pdo, 'itens_pedido') ? 'itens_pedido' : null);
            $produtoNomeCol = $this->columnExists($pdo, 'produtos', 'nome') ? 'nome' : ($this->columnExists($pdo, 'produtos', 'name') ? 'name' : null);
            if ($itensTable && $produtoNomeCol) {
                $stmt = $pdo->query("
                    SELECT pr.{$produtoNomeCol} as nome, COUNT(ip.produto_id) as vendas, COALESCE(SUM(ip.quantidade),0) as quantidade
                    FROM {$itensTable} ip
                    JOIN produtos pr ON ip.produto_id = pr.id
                    JOIN pedidos p ON ip.pedido_id = p.id
                    WHERE p.status = 'pago'
                    GROUP BY pr.id, pr.{$produtoNomeCol}
                    ORDER BY vendas DESC
                    LIMIT 5
                ");
                $produtos_mais_vendidos = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            } else {
                $produtos_mais_vendidos = [];
            }
            
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
        .stat-card { transition: none; }
        .quick-action-card { transition: none; cursor: pointer; }
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
                                    $valorTotalPedido = 0;
                                    if (isset($pedido['valor_total'])) {
                                        $valorTotalPedido = floatval($pedido['valor_total']);
                                    } elseif (isset($pedido['total'])) {
                                        $valorTotalPedido = floatval($pedido['total']);
                                    } elseif (isset($pedido['valor'])) {
                                        $valorTotalPedido = floatval($pedido['valor']);
                                    }

                                    echo '<div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong> - ' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '
                                            <br><small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-' . ($pedido['status'] == 'pago' ? 'success' : 'warning') . '">' . ucfirst($pedido['status']) . '</span>
                                            <br><strong>R$ ' . number_format($valorTotalPedido, 2, ',', '.') . '</strong>
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
