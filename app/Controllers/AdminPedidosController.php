<?php
namespace App\Controllers;

use App\Core\Request;

class AdminPedidosController extends Controller {
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pagina = $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            $status = $request->getParam('status', '');
            
            $sql = "SELECT p.*, u.nome as cliente_nome, u.email as cliente_email FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE 1=1";
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
        .order-card { transition: transform 0.2s; }
        .order-card:hover { transform: translateY(-5px); }
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
                
                <div class="row">';
                
                foreach ($pedidos as $pedido) {
                    $statusClass = 'status-' . $pedido['status'];
                    echo '<div class="col-md-6 col-lg-4 mb-4">
                        <div class="card order-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong>
                                <span class="badge ' . $statusClass . '">' . ucfirst($pedido['status']) . '</span>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title">' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</h6>
                                <p class="card-text text-muted small">' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                <p class="card-text">
                                    <small class="text-muted">Data: ' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small><br>
                                    <strong>Total: R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</strong>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <a href="/admin/pedidos/detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <select class="form-select form-select-sm" onchange="location.href=\'/admin/pedidos/atualizar-status/' . $pedido['id'] . '/\'+this.value">
                                        <option value="">Alterar</option>
                                        <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>Pendente</option>
                                        <option value="pago" ' . ($pedido['status'] == 'pago' ? 'selected' : '') . '>Pago</option>
                                        <option value="enviado" ' . ($pedido['status'] == 'enviado' ? 'selected' : '') . '>Enviado</option>
                                        <option value="entregue" ' . ($pedido['status'] == 'entregue' ? 'selected' : '') . '>Entregue</option>
                                        <option value="cancelado" ' . ($pedido['status'] == 'cancelado' ? 'selected' : '') . '>Cancelado</option>
                                    </select>
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
                
                echo '</div>';
                
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
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Buscar pedido
            $stmt = $pdo->prepare("
                SELECT p.*, u.nome as cliente_nome, u.email as cliente_email, u.telefone as cliente_telefone,
                       u.cpf as cliente_cpf, u.cep as cliente_cep, u.endereco as cliente_endereco,
                       u.numero as cliente_numero, u.bairro as cliente_bairro, u.cidade as cliente_cidade,
                       u.estado as cliente_estado
                FROM pedidos p 
                LEFT JOIN usuarios u ON p.usuario_id = u.id 
                WHERE p.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$pedido) {
                echo '<div class="alert alert-danger">Pedido não encontrado</div>';
                echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
                exit;
            }
            
            // Buscar itens do pedido
            $stmt = $pdo->prepare("
                SELECT ip.*, p.nome as produto_nome, p.sku as produto_sku
                FROM itens_pedido ip
                JOIN produtos p ON ip.produto_id = p.id
                WHERE ip.pedido_id = :pedido_id
            ");
            $stmt->bindParam(':pedido_id', $id);
            $stmt->execute();
            $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
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
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Itens do Pedido</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>SKU</th>
                                                <th>Qtd</th>
                                                <th>Valor Unit.</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($itens as $item) {
                                            echo '<tr>
                                                <td>' . htmlspecialchars($item['produto_nome']) . '</td>
                                                <td>' . htmlspecialchars($item['produto_sku']) . '</td>
                                                <td>' . $item['quantidade'] . '</td>
                                                <td>R$ ' . number_format($item['valor_unitario'], 2, ',', '.') . '</td>
                                                <td>R$ ' . number_format($item['valor_total'], 2, ',', '.') . '</td>
                                            </tr>';
                                        }
                                        
                                        echo '</tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4">Total do Pedido:</th>
                                                <th>R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Informações do Pedido</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Status:</strong> <span class="badge status-' . $pedido['status'] . '">' . ucfirst($pedido['status']) . '</span></p>
                                <p><strong>Data:</strong> ' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</p>
                                <p><strong>Forma Pagamento:</strong> ' . htmlspecialchars($pedido['forma_pagamento'] ?? 'N/A') . '</p>
                                <p><strong>Frete:</strong> R$ ' . number_format($pedido['valor_frete'], 2, ',', '.') . '</p>
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
                                <p><strong>CPF:</strong> ' . htmlspecialchars($pedido['cliente_cpf'] ?? 'N/A') . '</p>
                                <hr>
                                <p><strong>Endereço:</strong><br>
                                ' . htmlspecialchars($pedido['cliente_endereco'] ?? '') . ', ' . htmlspecialchars($pedido['cliente_numero'] ?? '') . '<br>
                                ' . htmlspecialchars($pedido['cliente_bairro'] ?? '') . '<br>
                                ' . htmlspecialchars($pedido['cliente_cidade'] ?? '') . ' - ' . htmlspecialchars($pedido['cliente_estado'] ?? '') . '<br>
                                CEP: ' . htmlspecialchars($pedido['cliente_cep'] ?? '') . '</p>
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
