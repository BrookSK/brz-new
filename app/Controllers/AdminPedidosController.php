<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\PedidoEcommerce;

class AdminPedidosController extends Controller {
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pagina = $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            $status = $request->getParam('status', '');
            
            $sql = "SELECT p.*, u.name as cliente_nome, u.email as cliente_email FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE 1=1";
            $params = [];
            
            if (!empty($busca)) {
                $sql .= " AND (p.id LIKE :busca OR u.name LIKE :busca OR u.email LIKE :busca)";
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
            
            $sqlTotal = "SELECT COUNT(*) as total FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE 1=1";
            $paramsTotal = [];
            if (!empty($busca)) {
                $sqlTotal .= " AND (p.id LIKE :busca OR u.name LIKE :busca OR u.email LIKE :busca)";
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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
        .order-card { 
            transition: all 0.3s ease; 
            border-left: 4px solid #dee2e6;
        }
        .order-card:hover { 
            transform: translateX(5px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .order-card .badge {
            font-size: 1.2rem;
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="sidebar-brand-text mx-3">BRZ Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>Usuários</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pagamentos"><i class="fas fa-fw fa-credit-card"></i><span>Pagamentos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/configuracoes"><i class="fas fa-fw fa-cog"></i><span>Configurações</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Sair</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Pedidos (' . $total . ')</h1>
                </div>
                
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="busca" placeholder="Buscar pedido, cliente ou email..." value="' . htmlspecialchars($busca) . '">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">Todos status</option>
                            <option value="pendente" ' . ($status === 'pendente' ? 'selected' : '') . '>Pendente</option>
                            <option value="pago" ' . ($status === 'pago' ? 'selected' : '') . '>Pago</option>
                            <option value="enviado" ' . ($status === 'enviado' ? 'selected' : '') . '>Enviado</option>
                            <option value="entregue" ' . ($status === 'entregue' ? 'selected' : '') . '>Entregue</option>
                            <option value="cancelado" ' . ($status === 'cancelado' ? 'selected' : '') . '>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Filtrar</button>
                    </div>
                </form>
                
                <!-- Abas de Pedidos por Moeda -->
                <div class="mb-3">
                    <ul class="nav nav-pills" id="pedidosTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pedidos-todos-tab" data-bs-toggle="pill" data-bs-target="#pedidos-todos" type="button">
                                <i class="fas fa-list"></i> Todos os Pedidos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pedidos-dolar-tab" data-bs-toggle="pill" data-bs-target="#pedidos-dolar" type="button">
                                <i class="fas fa-dollar-sign"></i> Pagamentos em Dólar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pedidos-real-tab" data-bs-toggle="pill" data-bs-target="#pedidos-real" type="button">
                                <i class="fas fa-currency-brl"></i> Pagamentos em Reais
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="pedidosTabContent">
                        <div class="tab-pane fade show active" id="pedidos-todos" role="tabpanel">
                            <div class="row">';
                
                foreach ($pedidos as $pedido) {
                    $statusClass = 'status-' . $pedido['status'];
                    $statusIcon = $this->getStatusIcon($pedido['status']);
                    $statusColor = $this->getStatusColor($pedido['status']);
                    
                    echo '<div class="col-12 mb-3">
                        <div class="card order-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="badge bg-' . $statusColor . ' fs-6 mb-2">
                                                <i class="' . $statusIcon . '"></i>
                                            </div>
                                            <h6 class="mb-0">#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</h6>
                                            <small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-1">' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</h6>
                                        <p class="text-muted small mb-1">' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                        <p class="text-muted small mb-0">' . htmlspecialchars($pedido['numero_pedido']) . '</p>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="mb-0 text-primary">' . $this->formatarMoeda($pedido['total'], $pedido['moeda']) . '</h5>
                                            <small class="text-muted">Total do Pedido</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="/admin/pedidos/detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                            <select class="form-select form-select-sm" style="width: auto;" onchange="location.href=\'/admin/pedidos/atualizar-status/' . $pedido['id'] . '/\'+this.value">
                                                <option value="">Status</option>
                                                <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>🟡 Pendente</option>
                                                <option value="pagamento" ' . ($pedido['status'] == 'pagamento' ? 'selected' : '') . '>🔵 Pagamento</option>
                                                <option value="aprovado" ' . ($pedido['status'] == 'aprovado' ? 'selected' : '') . '>🟢 Aprovado</option>
                                                <option value="separacao" ' . ($pedido['status'] == 'separacao' ? 'selected' : '') . '>🟠 Separação</option>
                                                <option value="enviado" ' . ($pedido['status'] == 'enviado' ? 'selected' : '') . '>🔵 Enviado</option>
                                                <option value="entregue" ' . ($pedido['status'] == 'entregue' ? 'selected' : '') . '>✅ Entregue</option>
                                                <option value="cancelado" ' . ($pedido['status'] == 'cancelado' ? 'selected' : '') . '>❌ Cancelado</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                if (empty($pedidos)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum pedido encontrado</h5>
                    </div>';
                }
                
                echo '</div>
                            </div>
                            
                            <!-- Aba de Pedidos em Dólar -->
                            <div class="tab-pane fade" id="pedidos-dolar" role="tabpanel">
                                <div class="row">';
                
                // Filtrar pedidos em USD
                $pedidosUSD = array_filter($pedidos, function($pedido) {
                    return $pedido['moeda'] === 'USD';
                });
                
                foreach ($pedidosUSD as $pedido) {
                    $statusClass = 'status-' . $pedido['status'];
                    $statusIcon = $this->getStatusIcon($pedido['status']);
                    $statusColor = $this->getStatusColor($pedido['status']);
                    
                    echo '<div class="col-12 mb-3">
                        <div class="card order-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="badge bg-' . $statusColor . ' fs-6 mb-2">
                                                <i class="' . $statusIcon . '"></i>
                                            </div>
                                            <h6 class="mb-0">#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</h6>
                                            <small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-1">' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</h6>
                                        <p class="text-muted small mb-1">' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                        <p class="text-muted small mb-0">' . htmlspecialchars($pedido['numero_pedido']) . '</p>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="mb-0 text-success">$ ' . number_format($pedido['total'], 2, '.', ',') . '</h5>
                                            <small class="text-muted">Total (USD)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="/admin/pedidos/detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                            <select class="form-select form-select-sm" style="width: auto;" onchange="location.href=\'/admin/pedidos/atualizar-status/' . $pedido['id'] . '/\'+this.value">
                                                <option value="">Status</option>
                                                <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>🟡 Pendente</option>
                                                <option value="pagamento" ' . ($pedido['status'] == 'pagamento' ? 'selected' : '') . '>🔵 Pagamento</option>
                                                <option value="aprovado" ' . ($pedido['status'] == 'aprovado' ? 'selected' : '') . '>🟢 Aprovado</option>
                                                <option value="separacao" ' . ($pedido['status'] == 'separacao' ? 'selected' : '') . '>🟠 Separação</option>
                                                <option value="enviado" ' . ($pedido['status'] == 'enviado' ? 'selected' : '') . '>🔵 Enviado</option>
                                                <option value="entregue" ' . ($pedido['status'] == 'entregue' ? 'selected' : '') . '>✅ Entregue</option>
                                                <option value="cancelado" ' . ($pedido['status'] == 'cancelado' ? 'selected' : '') . '>❌ Cancelado</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                if (empty($pedidosUSD)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-dollar-sign fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum pedido em dólar encontrado</h5>
                    </div>';
                }
                
                echo '</div>
                            </div>
                            
                            <!-- Aba de Pedidos em Real -->
                            <div class="tab-pane fade" id="pedidos-real" role="tabpanel">
                                <div class="row">';
                
                // Filtrar pedidos em BRL
                $pedidosBRL = array_filter($pedidos, function($pedido) {
                    return $pedido['moeda'] === 'BRL';
                });
                
                foreach ($pedidosBRL as $pedido) {
                    $statusClass = 'status-' . $pedido['status'];
                    $statusIcon = $this->getStatusIcon($pedido['status']);
                    $statusColor = $this->getStatusColor($pedido['status']);
                    
                    echo '<div class="col-12 mb-3">
                        <div class="card order-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="badge bg-' . $statusColor . ' fs-6 mb-2">
                                                <i class="' . $statusIcon . '"></i>
                                            </div>
                                            <h6 class="mb-0">#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</h6>
                                            <small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-1">' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</h6>
                                        <p class="text-muted small mb-1">' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                        <p class="text-muted small mb-0">' . htmlspecialchars($pedido['numero_pedido']) . '</p>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="mb-0 text-info">R$ ' . number_format($pedido['total'], 2, ',', '.') . '</h5>
                                            <small class="text-muted">Total (BRL)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="/admin/pedidos/detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                            <select class="form-select form-select-sm" style="width: auto;" onchange="location.href=\'/admin/pedidos/atualizar-status/' . $pedido['id'] . '/\'+this.value">
                                                <option value="">Status</option>
                                                <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>🟡 Pendente</option>
                                                <option value="pagamento" ' . ($pedido['status'] == 'pagamento' ? 'selected' : '') . '>🔵 Pagamento</option>
                                                <option value="aprovado" ' . ($pedido['status'] == 'aprovado' ? 'selected' : '') . '>🟢 Aprovado</option>
                                                <option value="separacao" ' . ($pedido['status'] == 'separacao' ? 'selected' : '') . '>🟠 Separação</option>
                                                <option value="enviado" ' . ($pedido['status'] == 'enviado' ? 'selected' : '') . '>🔵 Enviado</option>
                                                <option value="entregue" ' . ($pedido['status'] == 'entregue' ? 'selected' : '') . '>✅ Entregue</option>
                                                <option value="cancelado" ' . ($pedido['status'] == 'cancelado' ? 'selected' : '') . '>❌ Cancelado</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                if (empty($pedidosBRL)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-currency-brl fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum pedido em real encontrado</h5>
                    </div>';
                }
                
                echo '</div>
                            </div>
                        </div>
                    </div>
                </div>';
                
                if ($totalPaginas > 1) {
                    echo '<nav class="mt-4"><ul class="pagination justify-content-center">';
                    for ($i = 1; $i <= $totalPaginas; $i++) {
                        $url = "/admin/pedidos?pagina={$i}" . (!empty($busca) ? "&busca=" . urlencode($busca) : "") . (!empty($status) ? "&status={$status}" : "");
                        echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">
                            <a class="page-link" href="' . $url . '">' . $i . '</a>
                        </li>';
                    }
                    echo '</ul></nav>';
                }
                
                echo '</main></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }
    
    public function detalhes(Request $request) {
        $id = $request->getParam('id');
        
        try {
            // Usar o PedidoEcommerce que já está corrigido e adaptativo
            $pedidoModel = new PedidoEcommerce();
            $pedido = $pedidoModel->getComDetalhes($id);
            
            if (!$pedido) {
                echo '<div class="alert alert-danger">Pedido não encontrado</div>';
                echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
                exit;
            }
            
            // Obter itens do pedido (já vem com dados do produto adaptados)
            $itens = $pedido['items'] ?? [];
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
            exit;
        }
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . ' - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
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
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="sidebar-brand-text mx-3">BRZ Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>Usuários</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pagamentos"><i class="fas fa-fw fa-credit-card"></i><span>Pagamentos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/configuracoes"><i class="fas fa-fw fa-cog"></i><span>Configurações</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Sair</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Pedido #' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</h1>
                    <a href="/admin/pedidos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Itens do Pedido</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Imagem</th>
                                                <th>Produto</th>
                                                <th>ID Produto</th>
                                                <th>Referência</th>
                                                <th>Quantidade</th>
                                                <th>Preço Unitário</th>
                                                <th>Subtotal</th>
                                                <th>Data de Criação</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        if (empty($itens)) {
                                            echo '<tr><td colspan="8" class="text-center text-warning">Nenhum item encontrado para este pedido</td></tr>';
                                        }
                                        
                                        foreach ($itens as $item) {
                                            echo '<tr>
                                                <td>';
                                            
                                            // Mostrar imagem apenas se existir
                                            if (!empty($item['imagem']) && $item['imagem'] !== 'default.jpg') {
                                                echo '<img src="/assets/images/produtos/' . htmlspecialchars($item['imagem']) . '" alt="' . htmlspecialchars($item['nome_produto']) . '" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                                     onerror="this.style.display=\'none\'">';
                                            }
                                            
                                            echo '</td>
                                                <td>' . htmlspecialchars($item['nome_produto'] ?? 'Produto #' . $item['produto_id']) . '</td>
                                                <td>' . $item['produto_id'] . '</td>
                                                <td>' . htmlspecialchars($item['referencia'] ?? 'N/A') . '</td>
                                                <td>' . $item['quantidade'] . '</td>
                                                <td>' . $this->formatarMoeda($item['preco_unitario'], $pedido['moeda']) . '</td>
                                                <td>' . $this->formatarMoeda($item['subtotal'], $pedido['moeda']) . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($item['created_at'])) . '</td>
                                            </tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Dados Completos do Pedido</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Campo</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><strong>ID</strong></td><td>' . $pedido['id'] . '</td></tr>
                                            <tr><td><strong>Número Pedido</strong></td><td>' . htmlspecialchars($pedido['codigo_pedido'] ?? $pedido['numero_pedido']) . '</td></tr>
                                            <tr><td><strong>Status</strong></td><td><span class="badge status-' . $pedido['status'] . '">' . ucfirst($pedido['status']) . '</span></td></tr>
                                            <tr><td><strong>Nome Cliente</strong></td><td>' . htmlspecialchars($pedido['cliente_nome'] ?? $pedido['nome']) . '</td></tr>
                                            <tr><td><strong>Data Criação</strong></td><td>' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</td></tr>
                                            <tr><td><strong>Última Atualização</strong></td><td>' . date('d/m/Y H:i', strtotime($pedido['updated_at'])) . '</td></tr>
                                            <tr><td><strong>Usuário ID</strong></td><td>' . $pedido['usuario_id'] . '</td></tr>
                                            <tr><td><strong>Cliente ID</strong></td><td>' . $pedido['cliente_id'] . '</td></tr>
                                            <tr><td><strong>Subtotal</strong></td><td>R$ ' . number_format($pedido['subtotal'], 2, ',', '.') . '</td></tr>
                                            <tr><td><strong>Serviços</strong></td><td>R$ ' . number_format($pedido['servicos'], 2, ',', '.') . '</td></tr>
                                            <tr><td><strong>Impostos</strong></td><td>R$ ' . number_format($pedido['impostos'], 2, ',', '.') . '</td></tr>
                                            <tr><td><strong>Frete</strong></td><td>R$ ' . number_format($pedido['frete'], 2, ',', '.') . '</td></tr>
                                            <tr><td><strong>Desconto</strong></td><td>R$ ' . number_format($pedido['desconto'], 2, ',', '.') . '</td></tr>
                                            <tr><td><strong>Total</strong></td><td><strong>R$ ' . number_format($pedido['total'], 2, ',', '.') . '</strong></td></tr>
                                            <tr><td><strong>Moeda</strong></td><td>' . htmlspecialchars($pedido['moeda']) . '</td></tr>
                                            <tr><td><strong>Taxa Conversão</strong></td><td>' . $pedido['taxa_conversao'] . '</td></tr>
                                            <tr><td><strong>End. Entrega ID</strong></td><td>' . ($pedido['endereco_entrega_id'] ?? 'N/A') . '</td></tr>
                                            <tr><td><strong>End. Cobrança ID</strong></td><td>' . ($pedido['endereco_cobranca_id'] ?? 'N/A') . '</td></tr>
                                            <tr><td><strong>Observações</strong></td><td>' . htmlspecialchars($pedido['observacoes'] ?? 'Nenhuma') . '</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Informações do Pedido</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Status:</strong> <span class="badge status-' . $pedido['status'] . '">' . ucfirst($pedido['status']) . '</span></p>
                                <p><strong>Data:</strong> ' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</p>
                                <p><strong>Forma Pagamento:</strong> ' . htmlspecialchars($pedido['moeda'] ?? 'BRL') . '</p>
                                <p><strong>Frete:</strong> R$ ' . number_format($pedido['frete'], 2, ',', '.') . '</p>
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label">Atualizar Status:</label>
                                    <select class="form-select" id="novo_status">
                                        <option value="">Selecione...</option>
                                        <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>Pendente</option>
                                        <option value="pago" ' . ($pedido['status'] == 'pago' ? 'selected' : '') . '>Pago</option>
                                        <option value="enviado" ' . ($pedido['status'] == 'enviado' ? 'selected' : '') . '>Enviado</option>
                                        <option value="entregue" ' . ($pedido['status'] == 'entregue' ? 'selected' : '') . '>Entregue</option>
                                        <option value="cancelado" ' . ($pedido['status'] == 'cancelado' ? 'selected' : '') . '>Cancelado</option>
                                    </select>
                                </div>
                                <button onclick="atualizarStatus()" class="btn btn-primary w-100">Atualizar Status</button>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Dados do Cliente</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Nome:</strong> ' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</p>
                                <p><strong>Email:</strong> ' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                <p><strong>Telefone:</strong> ' . htmlspecialchars($pedido['cliente_telefone'] ?? 'N/A') . '</p>
                                <hr>
                                <p><strong>Endereço:</strong><br>
                                Endereço não disponível no momento</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function atualizarStatus() {
            const status = document.getElementById("novo_status").value;
            if (status) {
                window.location.href = "/admin/pedidos/atualizar-status/' . $id . '/" + status;
            }
        }
    </script>
</body>
</html>';
        exit;
    }
    
    private function formatarMoeda($valor, $moeda) {
        if ($moeda === 'USD') {
            return '$ ' . number_format($valor, 2, '.', ',');
        } else {
            return 'R$ ' . number_format($valor, 2, ',', '.');
        }
    }
    
    private function getStatusIcon($status) {
        $icons = [
            'pendente' => 'fas fa-clock',
            'pagamento' => 'fas fa-credit-card',
            'aprovado' => 'fas fa-check-circle',
            'separacao' => 'fas fa-box',
            'enviado' => 'fas fa-truck',
            'entregue' => 'fas fa-check-double',
            'cancelado' => 'fas fa-times-circle'
        ];
        return $icons[$status] ?? 'fas fa-question-circle';
    }
    
    private function getStatusColor($status) {
        $colors = [
            'pendente' => 'warning',
            'pagamento' => 'info',
            'aprovado' => 'success',
            'separacao' => 'primary',
            'enviado' => 'info',
            'entregue' => 'success',
            'cancelado' => 'danger'
        ];
        return $colors[$status] ?? 'secondary';
    }
    
    public function atualizarStatus(Request $request) {
        $id = $request->getParam('id');
        $novoStatus = $request->getParam('status');
        
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            $stmt = $pdo->prepare("UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$novoStatus, $id]);
            
            header('Location: /admin/pedidos/detalhes/' . $id . '?success=1');
            exit;
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro ao atualizar status: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos/detalhes/' . $id . '" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }
}
