<?php
namespace App\Controllers;

use App\Core\Request;

class AdminPagamentosController extends Controller {
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pagina = $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            $status = $request->getParam('status', '');
            $metodo = $request->getParam('metodo', '');
            
            $sql = "
                SELECT p.*, u.nome as cliente_nome, u.email as cliente_email,
                       pg.metodo, pg.status as status_pagamento, pg.gateway,
                       pg.codigo_transacao, pg.data_pagamento
                FROM pedidos p 
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN pagamentos pg ON p.id = pg.pedido_id
                WHERE 1=1
            ";
            $params = [];
            
            if (!empty($busca)) {
                $sql .= " AND (p.id LIKE :busca OR u.nome LIKE :busca OR pg.codigo_transacao LIKE :busca)";
                $params[':busca'] = "%{$busca}%";
            }
            if (!empty($status)) {
                $sql .= " AND pg.status = :status";
                $params[':status'] = $status;
            }
            if (!empty($metodo)) {
                $sql .= " AND pg.metodo = :metodo";
                $params[':metodo'] = $metodo;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT :limite OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) $stmt->bindValue($key, $value);
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $pagamentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $sqlTotal = "SELECT COUNT(*) as total FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id LEFT JOIN pagamentos pg ON p.id = pg.pedido_id WHERE 1=1";
            $paramsTotal = [];
            if (!empty($busca)) {
                $sqlTotal .= " AND (p.id LIKE :busca OR u.nome LIKE :busca OR pg.codigo_transacao LIKE :busca)";
                $paramsTotal[':busca'] = "%{$busca}%";
            }
            if (!empty($status)) {
                $sqlTotal .= " AND pg.status = :status";
                $paramsTotal[':status'] = $status;
            }
            if (!empty($metodo)) {
                $sqlTotal .= " AND pg.metodo = :metodo";
                $paramsTotal[':metodo'] = $metodo;
            }
            
            $stmtTotal = $pdo->prepare($sqlTotal);
            foreach ($paramsTotal as $key => $value) $stmtTotal->bindValue($key, $value);
            $stmtTotal->execute();
            $total = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($total / $limite);
            
            // Estatísticas
            $stmtStats = $pdo->query("
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(p.valor_total) as valor_total,
                    SUM(CASE WHEN pg.status = 'aprovado' THEN p.valor_total ELSE 0 END) as valor_aprovado,
                    SUM(CASE WHEN pg.status = 'pendente' THEN p.valor_total ELSE 0 END) as valor_pendente,
                    SUM(CASE WHEN pg.status = 'recusado' THEN p.valor_total ELSE 0 END) as valor_recusado
                FROM pedidos p
                LEFT JOIN pagamentos pg ON p.id = pg.pedido_id
                WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stats = $stmtStats->fetch(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            $pagamentos = [];
            $total = 0;
            $totalPaginas = 0;
            $stats = ['total_transacoes' => 0, 'valor_total' => 0, 'valor_aprovado' => 0, 'valor_pendente' => 0, 'valor_recusado' => 0];
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamentos - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .payment-card { transition: transform 0.2s; }
        .payment-card:hover { transform: translateY(-5px); }
        .status-aprovado { background-color: #28a745; }
        .status-pendente { background-color: #ffc107; }
        .status-recusado { background-color: #dc3545; }
        .status-estornado { background-color: #6f42c1; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('pagamentos');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Pagamentos (' . $stats['total_transacoes'] . ' transações)</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="alert(\'Funcionalidade em desenvolvimento\')">
                            <i class="fas fa-download me-1"></i>Exportar Relatório
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>';
                
                echo '<div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Transações (30 dias)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['total_transacoes'] . '</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-credit-card fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Aprovados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ ' . number_format($stats['valor_aprovado'], 2, ',', '.') . '</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pendentes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ ' . number_format($stats['valor_pendente'], 2, ',', '.') . '</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Recusados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ ' . number_format($stats['valor_recusado'], 2, ',', '.') . '</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-times-circle fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="busca" placeholder="Buscar pedido, cliente ou transação..." value="' . htmlspecialchars($busca) . '">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">Todos status</option>
                            <option value="aprovado" ' . ($status === 'aprovado' ? 'selected' : '') . '>Aprovado</option>
                            <option value="pendente" ' . ($status === 'pendente' ? 'selected' : '') . '>Pendente</option>
                            <option value="recusado" ' . ($status === 'recusado' ? 'selected' : '') . '>Recusado</option>
                            <option value="estornado" ' . ($status === 'estornado' ? 'selected' : '') . '>Estornado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="metodo">
                            <option value="">Todos métodos</option>
                            <option value="cartao" ' . ($metodo === 'cartao' ? 'selected' : '') . '>Cartão</option>
                            <option value="boleto" ' . ($metodo === 'boleto' ? 'selected' : '') . '>Boleto</option>
                            <option value="pix" ' . ($metodo === 'pix' ? 'selected' : '') . '>PIX</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Filtrar</button>
                    </div>
                </form>
                
                <div class="row">';
                
                foreach ($pagamentos as $pagamento) {
                    $statusClass = 'status-' . ($pagamento['status_pagamento'] ?? 'pendente');
                    echo '<div class="col-md-6 col-lg-4 mb-4">
                        <div class="card payment-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong>#' . str_pad($pagamento['id'], 6, '0', STR_PAD_LEFT) . '</strong>
                                <span class="badge ' . $statusClass . '">' . ucfirst($pagamento['status_pagamento'] ?? 'Pendente') . '</span>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title">' . htmlspecialchars($pagamento['cliente_nome'] ?? 'Visitante') . '</h6>
                                <p class="card-text text-muted small">' . htmlspecialchars($pagamento['cliente_email'] ?? 'N/A') . '</p>
                                <p class="card-text">
                                    <small class="text-muted">Método: ' . ($pagamento['metodo'] ?? 'N/A') . '</small><br>
                                    <small class="text-muted">Gateway: ' . ($pagamento['gateway'] ?? 'N/A') . '</small><br>
                                    <small class="text-muted">Transação: ' . htmlspecialchars($pagamento['codigo_transacao'] ?? 'N/A') . '</small><br>
                                    <strong>Valor: R$ ' . number_format($pagamento['valor_total'], 2, ',', '.') . '</strong>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <a href="/admin/pedidos/detalhes/' . $pagamento['id'] . '" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Ver Pedido
                                    </a>';
                                    if ($pagamento['status_pagamento'] === 'pendente') {
                                        echo '<button class="btn btn-sm btn-outline-success" onclick="confirmarPagamento(' . $pagamento['id'] . ')">
                                            <i class="fas fa-check"></i> Confirmar
                                        </button>';
                                    }
                                    echo '</div>
                            </div>
                        </div>
                    </div>';
                }
                
                if (empty($pagamentos)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum pagamento encontrado</h5>
                    </div>';
                }
                
                echo '</div>';
                
                if ($totalPaginas > 1) {
                    echo '<nav class="mt-4"><ul class="pagination justify-content-center">';
                    for ($i = 1; $i <= $totalPaginas; $i++) {
                        $url = "/admin/pagamentos?pagina={$i}" . (!empty($busca) ? "&busca=" . urlencode($busca) : "") . (!empty($status) ? "&status={$status}" : "") . (!empty($metodo) ? "&metodo={$metodo}" : "");
                        echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">
                            <a class="page-link" href="' . $url . '">' . $i . '</a>
                        </li>';
                    }
                    echo '</ul></nav>';
                }
                
                echo '</main></div></div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarPagamento(pedidoId) {
            if (confirm("Tem certeza que deseja confirmar este pagamento?")) {
                fetch("/admin/pagamentos/confirmar/" + pedidoId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Erro ao confirmar pagamento: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao confirmar pagamento");
                });
            }
        }
    </script>
</body>
</html>';
        exit;
    }
    
    public function confirmarPagamento(Request $request) {
        $pedidoId = $request->getParam('id');
        
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            // Atualizar status do pagamento
            $stmt = $pdo->prepare("
                UPDATE pagamentos 
                SET status = 'aprovado', data_pagamento = NOW() 
                WHERE pedido_id = :pedido_id
            ");
            $stmt->bindParam(':pedido_id', $pedidoId);
            $stmt->execute();
            
            // Atualizar status do pedido
            $stmt = $pdo->prepare("
                UPDATE pedidos 
                SET status = 'pago', updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $pedidoId);
            $stmt->execute();
            
            $pdo->commit();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    public function configuracoes(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Buscar configurações de pagamento
            $stmt = $pdo->query("SELECT * FROM configuracoes WHERE categoria = 'pagamento'");
            $configuracoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Organizar configurações por chave
            $config = [];
            foreach ($configuracoes as $c) {
                $config[$c['chave']] = $c['valor'];
            }
            
        } catch (\Exception $e) {
            $config = [];
        }
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações de Pagamento - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="sidebar-brand-text mx-3">Braziliana Shop Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>Usuários</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/pagamentos"><i class="fas fa-fw fa-credit-card"></i><span>Pagamentos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/configuracoes"><i class="fas fa-fw fa-cog"></i><span>Configurações</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Sair</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Configurações de Pagamento</h1>
                    <a href="/admin/pagamentos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
                
                <form method="POST" action="/admin/pagamentos/salvar-configuracoes">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Stripe</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Chave Pública</label>
                                        <input type="text" class="form-control" name="stripe_public_key" value="' . htmlspecialchars($config['stripe_public_key'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Chave Secreta</label>
                                        <input type="password" class="form-control" name="stripe_secret_key" value="' . htmlspecialchars($config['stripe_secret_key'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Webhook Secret</label>
                                        <input type="password" class="form-control" name="stripe_webhook_secret" value="' . htmlspecialchars($config['stripe_webhook_secret'] ?? '') . '">
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="stripe_enabled" ' . ($config['stripe_enabled'] ?? false ? 'checked' : '') . '>
                                        <label class="form-check-label">Habilitar Stripe</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Mercado Pago</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Access Token</label>
                                        <input type="password" class="form-control" name="mercadopago_access_token" value="' . htmlspecialchars($config['mercadopago_access_token'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Public Key</label>
                                        <input type="text" class="form-control" name="mercadopago_public_key" value="' . htmlspecialchars($config['mercadopago_public_key'] ?? '') . '">
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="mercadopago_enabled" ' . ($config['mercadopago_enabled'] ?? false ? 'checked' : '') . '>
                                        <label class="form-check-label">Habilitar Mercado Pago</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">PIX</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Chave PIX</label>
                                        <input type="text" class="form-control" name="pix_key" value="' . htmlspecialchars($config['pix_key'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tipo da Chave</label>
                                        <select class="form-select" name="pix_key_type">
                                            <option value="cpf" ' . (($config['pix_key_type'] ?? '') === 'cpf' ? 'selected' : '') . '>CPF</option>
                                            <option value="cnpj" ' . (($config['pix_key_type'] ?? '') === 'cnpj' ? 'selected' : '') . '>CNPJ</option>
                                            <option value="email" ' . (($config['pix_key_type'] ?? '') === 'email' ? 'selected' : '') . '>Email</option>
                                            <option value="telefone" ' . (($config['pix_key_type'] ?? '') === 'telefone' ? 'selected' : '') . '>Telefone</option>
                                            <option value="aleatoria" ' . (($config['pix_key_type'] ?? '') === 'aleatoria' ? 'selected' : '') . '>Aleatória</option>
                                        </select>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pix_enabled" ' . ($config['pix_enabled'] ?? false ? 'checked' : '') . '>
                                        <label class="form-check-label">Habilitar PIX</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Configurações Gerais</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Moeda Padrão</label>
                                        <select class="form-select" name="default_currency">
                                            <option value="BRL" ' . (($config['default_currency'] ?? 'BRL') === 'BRL' ? 'selected' : '') . '>Real (BRL)</option>
                                            <option value="USD" ' . (($config['default_currency'] ?? '') === 'USD' ? 'selected' : '') . '>Dólar (USD)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Método Padrão</label>
                                        <select class="form-select" name="default_payment_method">
                                            <option value="cartao" ' . (($config['default_payment_method'] ?? '') === 'cartao' ? 'selected' : '') . '>Cartão de Crédito</option>
                                            <option value="boleto" ' . (($config['default_payment_method'] ?? '') === 'boleto' ? 'selected' : '') . '>Boleto</option>
                                            <option value="pix" ' . (($config['default_payment_method'] ?? '') === 'pix' ? 'selected' : '') . '>PIX</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Configurações
                        </button>
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
}
