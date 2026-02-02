<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

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
                        <a href="/admin/pagamentos/comissoes-gerais" class="btn btn-outline-primary me-2">
                            <i class="fas fa-percentage me-1"></i>Comissões gerais
                        </a>
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

    private function getAdminUserId(): int {
        try {
            $auth = new AuthService();
            $u = $auth->getUsuarioLogado();
            if (is_array($u) && !empty($u['id'])) {
                return (int) $u['id'];
            }
        } catch (\Exception $e) {
        }
        return 0;
    }

    private function getConfigComissao(string $chave, $default = '') {
        $db = \Config\Database::getConnection();

        try {
            $stmtCols = $db->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (is_array($cols) && !empty($cols)) {
                $direct = [
                    'comissao_janela_primeiro_inicio',
                    'comissao_janela_primeiro_fim',
                    'comissao_janela_duracao_dias',
                ];
                $colName = null;
                $directKey = 'comissao_' . $chave;
                if (in_array($directKey, $direct, true) && in_array($directKey, $cols, true)) {
                    $colName = $directKey;
                }
                if (!empty($colName)) {
                    $stmt = $db->query('SELECT ' . $colName . ' AS valor FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        try {
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
            $stmt->execute(['comissao', $chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        try {
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['comissao_' . $chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    private function parseDate(string $value): ?\DateTime {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return new \DateTime($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getWindowsForUi(): array {
        $inicioCfg = (string) $this->getConfigComissao('janela_primeiro_inicio', '');
        $fimCfg = (string) $this->getConfigComissao('janela_primeiro_fim', '');
        $durCfg = (int) $this->getConfigComissao('janela_duracao_dias', '14');
        if ($durCfg <= 0) {
            $durCfg = 14;
        }

        $inicio = $this->parseDate($inicioCfg);
        $fim = $this->parseDate($fimCfg);

        $windows = [];
        if ($inicio && $fim && $fim >= $inicio) {
            $windows[] = ['inicio' => $inicio, 'fim' => $fim];

            $cursor = clone $fim;
            $cursor->modify('+1 day');
            for ($i = 0; $i < 12; $i++) {
                $wInicio = clone $cursor;
                $wFim = clone $cursor;
                $wFim->modify('+' . ($durCfg - 1) . ' day');
                $windows[] = ['inicio' => $wInicio, 'fim' => $wFim];
                $cursor = clone $wFim;
                $cursor->modify('+1 day');
            }
        }

        if (empty($windows)) {
            $wFim = new \DateTime('today');
            $wFim->setTime(23, 59, 59);
            $wInicio = new \DateTime('today');
            $wInicio->modify('-13 day');
            $wInicio->setTime(0, 0, 0);
            $windows[] = ['inicio' => $wInicio, 'fim' => $wFim];
        }

        usort($windows, function ($a, $b) {
            return $b['inicio'] <=> $a['inicio'];
        });

        $windows = array_slice($windows, 0, 12);
        return $windows;
    }

    private function loadPedidoColumns(\PDO $db): array {
        try {
            $stmt = $db->query('DESCRIBE pedidos');
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            return is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function loadTableColumns(\PDO $db, string $table): array {
        try {
            $stmt = $db->query('DESCRIBE ' . $table);
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            return is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function detectPedidoItensTable(\PDO $db): string {
        try {
            $st = $db->prepare('SHOW TABLES LIKE ?');
            $st->execute(['pedido_itens']);
            if ($st->fetchColumn()) {
                return 'pedido_itens';
            }
        } catch (\Exception $e) {
        }
        try {
            $st = $db->prepare('SHOW TABLES LIKE ?');
            $st->execute(['pedido_items']);
            if ($st->fetchColumn()) {
                return 'pedido_items';
            }
        } catch (\Exception $e) {
        }
        return 'pedido_itens';
    }

    private function buildPaidAtExpression(array $colsPedidos, array $colsPagamentos, bool $temPagamentosJoin): string {
        if (in_array('pago_em', $colsPedidos, true)) {
            return 'p.pago_em';
        }
        if ($temPagamentosJoin) {
            foreach (['data_pagamento', 'paid_at', 'data_confirmacao', 'updated_at', 'created_at'] as $c) {
                if (in_array($c, $colsPagamentos, true)) {
                    return 'pg.' . $c;
                }
            }
        }
        if (in_array('updated_at', $colsPedidos, true)) {
            return 'p.updated_at';
        }
        if (in_array('created_at', $colsPedidos, true)) {
            return 'p.created_at';
        }
        return 'p.id';
    }

    private function buildPaidStatusWhere(array $colsPedidos, array $colsPagamentos, bool $temPagamentosJoin): array {
        $paid = ['pago', 'paid', 'approved', 'aprovado', 'concluido', 'concluído'];

        $statusColPedido = null;
        foreach (['payment_status', 'status_pagamento', 'status'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $statusColPedido = 'p.' . $c;
                break;
            }
        }

        $statusColPg = null;
        if ($temPagamentosJoin) {
            foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                if (in_array($c, $colsPagamentos, true)) {
                    $statusColPg = 'pg.' . $c;
                    break;
                }
            }
        }

        $whereParts = [];
        if (!empty($statusColPedido)) {
            $whereParts[] = "LOWER(COALESCE({$statusColPedido}, '')) IN ('" . implode("','", $paid) . "')";
        }
        if (!empty($statusColPg)) {
            $whereParts[] = "LOWER(COALESCE({$statusColPg}, '')) IN ('" . implode("','", $paid) . "')";
        }

        if (empty($whereParts)) {
            return ['sql' => '1=1', 'usesPaidAt' => false];
        }

        return ['sql' => '(' . implode(' OR ', $whereParts) . ')', 'usesPaidAt' => true];
    }

    private function getPedidosComissaoForWindowAndVendedor(\PDO $db, int $vendedorId, \DateTime $inicio, \DateTime $fim): array {
        $vendedorId = (int) $vendedorId;
        if ($vendedorId <= 0) {
            return [];
        }

        $colsPedidos = $this->loadPedidoColumns($db);
        if (empty($colsPedidos) || !in_array('origem_pedido', $colsPedidos, true) || !in_array('admin_criador_id', $colsPedidos, true)) {
            return [];
        }

        $totalCol = null;
        foreach (['valor_total', 'total', 'amount', 'valor'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $totalCol = $c;
                break;
            }
        }
        if (!$totalCol) {
            return [];
        }

        $temPagamentosJoin = false;
        $colsPagamentos = [];
        try {
            $colsPagamentos = $this->loadTableColumns($db, 'pagamentos');
            if (!empty($colsPagamentos) && in_array('pedido_id', $colsPagamentos, true)) {
                $temPagamentosJoin = true;
            }
        } catch (\Exception $e) {
            $temPagamentosJoin = false;
        }

        $paidAtExpr = $this->buildPaidAtExpression($colsPedidos, $colsPagamentos, $temPagamentosJoin);
        $statusWhere = $this->buildPaidStatusWhere($colsPedidos, $colsPagamentos, $temPagamentosJoin);
        $joinPagamentos = $temPagamentosJoin ? 'LEFT JOIN pagamentos pg ON pg.pedido_id = p.id' : '';

        $itensTable = $this->detectPedidoItensTable($db);
        $colsItens = $this->loadTableColumns($db, $itensTable);
        $qtdCol = null;
        foreach (['quantidade', 'qty', 'qtd'] as $c) {
            if (in_array($c, $colsItens, true)) {
                $qtdCol = $c;
                break;
            }
        }
        $produtoIdCol = in_array('produto_id', $colsItens, true) ? 'produto_id' : null;
        $pedidoIdCol = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : null;
        if (!$pedidoIdCol || !$produtoIdCol || !$qtdCol) {
            return [];
        }

        $colsProdutos = $this->loadTableColumns($db, 'produtos');
        $custoCol = null;
        foreach (['cost_price', 'custo', 'preco_custo', 'cost', 'valor_custo'] as $c) {
            if (in_array($c, $colsProdutos, true)) {
                $custoCol = $c;
                break;
            }
        }
        if (!$custoCol) {
            return [];
        }

        $sqlPedidos = "SELECT p.id AS pedido_id, p.{$totalCol} AS faturado, {$paidAtExpr} AS pago_em FROM pedidos p {$joinPagamentos} WHERE p.origem_pedido = 'manual' AND p.admin_criador_id = :vid AND {$statusWhere['sql']} AND {$paidAtExpr} BETWEEN :inicio AND :fim ORDER BY {$paidAtExpr} ASC";
        $stmtP = $db->prepare($sqlPedidos);
        $stmtP->execute([
            ':vid' => $vendedorId,
            ':inicio' => $inicio->format('Y-m-d H:i:s'),
            ':fim' => $fim->format('Y-m-d H:i:s'),
        ]);
        $pedidos = $stmtP->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if (empty($pedidos)) {
            return [];
        }

        $pedidoIds = array_values(array_filter(array_map(fn($p) => (int) ($p['pedido_id'] ?? 0), $pedidos), fn($v) => $v > 0));
        if (empty($pedidoIds)) {
            return [];
        }

        $in = implode(',', array_fill(0, count($pedidoIds), '?'));
        $sqlCusto = "SELECT i.{$pedidoIdCol} AS pedido_id, SUM(i.{$qtdCol} * COALESCE(pr.{$custoCol},0)) AS custo_total FROM {$itensTable} i INNER JOIN produtos pr ON pr.id = i.{$produtoIdCol} WHERE i.{$pedidoIdCol} IN ({$in}) GROUP BY i.{$pedidoIdCol}";
        $stmtC = $db->prepare($sqlCusto);
        $stmtC->execute($pedidoIds);
        $rowsC = $stmtC->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $custoPorPedido = [];
        foreach ($rowsC as $r) {
            $pid = (int) ($r['pedido_id'] ?? 0);
            if ($pid <= 0) continue;
            $custoPorPedido[$pid] = (float) ($r['custo_total'] ?? 0);
        }

        $totalFaturado = 0.0;
        foreach ($pedidos as $p) {
            $totalFaturado += (float) ($p['faturado'] ?? 0);
        }

        $pedidoModel = null;
        $faixas = [];
        $percent = 0.0;
        try {
            $pedidoModel = new \App\Models\PedidoEcommerce();
            $faixas = $pedidoModel->getFaixasComissaoManual();
            $percent = (float) $pedidoModel->calcularPercentualComissaoManual($totalFaturado, $faixas);
        } catch (\Exception $e) {
            $pedidoModel = null;
            $faixas = [];
            $percent = 0.0;
        }

        $out = [];
        foreach ($pedidos as $p) {
            $pid = (int) ($p['pedido_id'] ?? 0);
            if ($pid <= 0) continue;
            $fat = (float) ($p['faturado'] ?? 0);
            $custo = (float) ($custoPorPedido[$pid] ?? 0);
            $liq = $fat - $custo;
            $com = $liq * ($percent / 100.0);
            if ($com < 0) $com = 0.0;
            $out[] = [
                'pedido_id' => $pid,
                'faturado' => $fat,
                'custo' => $custo,
                'liquido' => $liq,
                'comissao_usd' => $com,
            ];
        }

        return $out;
    }

    private function getCommissionKpisForWindow(\PDO $db, \DateTime $inicio, \DateTime $fim): array {
        $colsPedidos = $this->loadPedidoColumns($db);
        if (empty($colsPedidos) || !in_array('origem_pedido', $colsPedidos, true) || !in_array('admin_criador_id', $colsPedidos, true)) {
            return [];
        }

        $totalCol = null;
        foreach (['valor_total', 'total', 'amount', 'valor'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $totalCol = $c;
                break;
            }
        }
        if (!$totalCol) {
            return [];
        }

        $temPagamentosJoin = false;
        $colsPagamentos = [];
        try {
            $colsPagamentos = $this->loadTableColumns($db, 'pagamentos');
            if (!empty($colsPagamentos) && in_array('pedido_id', $colsPagamentos, true)) {
                $temPagamentosJoin = true;
            }
        } catch (\Exception $e) {
            $temPagamentosJoin = false;
        }

        $paidAtExpr = $this->buildPaidAtExpression($colsPedidos, $colsPagamentos, $temPagamentosJoin);
        $statusWhere = $this->buildPaidStatusWhere($colsPedidos, $colsPagamentos, $temPagamentosJoin);

        $joinPagamentos = $temPagamentosJoin ? 'LEFT JOIN pagamentos pg ON pg.pedido_id = p.id' : '';

        $sqlBase = "FROM pedidos p {$joinPagamentos} WHERE p.origem_pedido = 'manual' AND p.admin_criador_id IS NOT NULL AND {$statusWhere['sql']} AND {$paidAtExpr} BETWEEN :inicio AND :fim";

        $sqlAgg = "SELECT p.admin_criador_id AS vendedor_id, COUNT(*) AS pedidos_qtd, SUM(p.{$totalCol}) AS faturado_total, AVG(p.{$totalCol}) AS ticket_medio {$sqlBase} GROUP BY p.admin_criador_id";

        $stmt = $db->prepare($sqlAgg);
        $stmt->execute([
            ':inicio' => $inicio->format('Y-m-d H:i:s'),
            ':fim' => $fim->format('Y-m-d H:i:s'),
        ]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            return [];
        }

        $vendedorIds = array_values(array_filter(array_map(fn($r) => (int) ($r['vendedor_id'] ?? 0), $rows), fn($v) => $v > 0));
        if (empty($vendedorIds)) {
            return [];
        }

        $in = implode(',', array_fill(0, count($vendedorIds), '?'));
        $mapVendedor = [];
        try {
            $stU = $db->prepare("SELECT id, COALESCE(nome, name) AS nome, email FROM usuarios WHERE id IN ({$in})");
            $stU->execute($vendedorIds);
            $us = $stU->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($us as $u) {
                $mapVendedor[(int) $u['id']] = $u;
            }
        } catch (\Exception $e) {
            $mapVendedor = [];
        }

        $itensTable = $this->detectPedidoItensTable($db);
        $colsItens = $this->loadTableColumns($db, $itensTable);
        $qtdCol = null;
        foreach (['quantidade', 'qty', 'qtd'] as $c) {
            if (in_array($c, $colsItens, true)) {
                $qtdCol = $c;
                break;
            }
        }
        $produtoIdCol = in_array('produto_id', $colsItens, true) ? 'produto_id' : null;
        $pedidoIdCol = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : null;

        $colsProdutos = $this->loadTableColumns($db, 'produtos');
        $pesoCol = null;
        foreach (['peso', 'weight'] as $c) {
            if (in_array($c, $colsProdutos, true)) {
                $pesoCol = $c;
                break;
            }
        }
        $custoCol = null;
        foreach (['cost_price', 'custo', 'preco_custo', 'cost', 'valor_custo'] as $c) {
            if (in_array($c, $colsProdutos, true)) {
                $custoCol = $c;
                break;
            }
        }
        $nomeProdCol = null;
        foreach (['nome', 'name'] as $c) {
            if (in_array($c, $colsProdutos, true)) {
                $nomeProdCol = $c;
                break;
            }
        }

        $kgPorVendedor = [];
        $topProdutoPorVendedor = [];
        $custoPorVendedor = [];

        if ($pedidoIdCol && $produtoIdCol && $qtdCol && ($pesoCol || $custoCol || $nomeProdCol)) {
            $sqlItensAgg = "SELECT p.admin_criador_id AS vendedor_id, i.{$produtoIdCol} AS produto_id, SUM(i.{$qtdCol}) AS qtd_sum";
            if ($pesoCol) {
                $sqlItensAgg .= ", SUM(i.{$qtdCol} * COALESCE(pr.{$pesoCol},0)) AS kg_sum";
            }
            if ($custoCol) {
                $sqlItensAgg .= ", SUM(i.{$qtdCol} * COALESCE(pr.{$custoCol},0)) AS custo_sum";
            }
            if ($nomeProdCol) {
                $sqlItensAgg .= ", MAX(pr.{$nomeProdCol}) AS produto_nome";
            }
            $sqlItensAgg .= " {$sqlBase} AND i.{$pedidoIdCol} = p.id INNER JOIN {$itensTable} i ON i.{$pedidoIdCol} = p.id INNER JOIN produtos pr ON pr.id = i.{$produtoIdCol} GROUP BY p.admin_criador_id, i.{$produtoIdCol}";

            try {
                $stItens = $db->prepare($sqlItensAgg);
                $stItens->execute([
                    ':inicio' => $inicio->format('Y-m-d H:i:s'),
                    ':fim' => $fim->format('Y-m-d H:i:s'),
                ]);
                $itRows = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($itRows as $ir) {
                    $vid = (int) ($ir['vendedor_id'] ?? 0);
                    if ($vid <= 0) {
                        continue;
                    }
                    if ($pesoCol) {
                        $kgPorVendedor[$vid] = ($kgPorVendedor[$vid] ?? 0.0) + (float) ($ir['kg_sum'] ?? 0);
                    }
                    if ($custoCol) {
                        $custoPorVendedor[$vid] = ($custoPorVendedor[$vid] ?? 0.0) + (float) ($ir['custo_sum'] ?? 0);
                    }
                    $qsum = (float) ($ir['qtd_sum'] ?? 0);
                    $cur = $topProdutoPorVendedor[$vid] ?? null;
                    if (!$cur || $qsum > (float) ($cur['qtd_sum'] ?? 0)) {
                        $topProdutoPorVendedor[$vid] = [
                            'produto_id' => (int) ($ir['produto_id'] ?? 0),
                            'produto_nome' => (string) ($ir['produto_nome'] ?? ''),
                            'qtd_sum' => $qsum,
                        ];
                    }
                }
            } catch (\Exception $e) {
                $kgPorVendedor = [];
                $topProdutoPorVendedor = [];
                $custoPorVendedor = [];
            }
        }

        $faixas = [];
        $pedidoModel = null;
        try {
            $pedidoModel = new \App\Models\PedidoEcommerce();
            $faixas = $pedidoModel->getFaixasComissaoManual();
        } catch (\Exception $e) {
            $pedidoModel = null;
            $faixas = [];
        }

        $out = [];
        foreach ($rows as $r) {
            $vid = (int) ($r['vendedor_id'] ?? 0);
            if ($vid <= 0) {
                continue;
            }
            $faturado = (float) ($r['faturado_total'] ?? 0);
            $custo = (float) ($custoPorVendedor[$vid] ?? 0);
            $liquido = $faturado - $custo;
            $percent = 0.0;
            if ($pedidoModel) {
                try {
                    $percent = (float) $pedidoModel->calcularPercentualComissaoManual($faturado, $faixas);
                } catch (\Exception $e) {
                    $percent = 0.0;
                }
            }
            $comissao = $liquido * ($percent / 100.0);

            $out[$vid] = [
                'vendedor_id' => $vid,
                'vendedor_nome' => (string) (($mapVendedor[$vid]['nome'] ?? '') ?: ('#' . $vid)),
                'pedidos_qtd' => (int) ($r['pedidos_qtd'] ?? 0),
                'ticket_medio' => (float) ($r['ticket_medio'] ?? 0),
                'faturado_total' => $faturado,
                'custo_total' => $custo,
                'liquido_total' => $liquido,
                'percentual' => $percent,
                'comissao_usd' => $comissao,
                'kg_total' => (float) ($kgPorVendedor[$vid] ?? 0),
                'top_produto_nome' => (string) (($topProdutoPorVendedor[$vid]['produto_nome'] ?? '') ?: ''),
            ];
        }

        return $out;
    }

    public function comissoesGerais(Request $request) {
        $db = \Config\Database::getConnection();
        $windows = $this->getWindowsForUi();

        $janelaParam = (string) $request->getParam('janela', '');
        $selected = $windows[0] ?? null;
        foreach ($windows as $w) {
            $key = $w['inicio']->format('Y-m-d') . '|' . $w['fim']->format('Y-m-d');
            if ($janelaParam !== '' && $janelaParam === $key) {
                $selected = $w;
                break;
            }
        }
        if (!$selected) {
            $wFim = new \DateTime('today');
            $wFim->setTime(23, 59, 59);
            $wInicio = new \DateTime('today');
            $wInicio->modify('-13 day');
            $wInicio->setTime(0, 0, 0);
            $selected = ['inicio' => $wInicio, 'fim' => $wFim];
        }

        $inicio = clone $selected['inicio'];
        $inicio->setTime(0, 0, 0);
        $fim = clone $selected['fim'];
        $fim->setTime(23, 59, 59);

        $kpis = [];
        try {
            $kpis = $this->getCommissionKpisForWindow($db, $inicio, $fim);
        } catch (\Exception $e) {
            $kpis = [];
        }

        $vendedorIds = array_keys($kpis);
        $ajustesPorVendedor = [];
        $pagosPorVendedor = [];
        $pagamentosPorVendedor = [];

        $janelaId = 0;
        try {
            $stJ = $db->prepare('SELECT id FROM comissao_janelas WHERE data_inicio = :di AND data_fim = :df LIMIT 1');
            $stJ->execute([':di' => $inicio->format('Y-m-d H:i:s'), ':df' => $fim->format('Y-m-d H:i:s')]);
            $janelaId = (int) ($stJ->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            $janelaId = 0;
        }

        if ($janelaId <= 0) {
            try {
                $stIns = $db->prepare('INSERT INTO comissao_janelas (data_inicio, data_fim, status, created_at) VALUES (:di, :df, :st, NOW())');
                $stIns->execute([':di' => $inicio->format('Y-m-d H:i:s'), ':df' => $fim->format('Y-m-d H:i:s'), ':st' => 'aberta']);
                $janelaId = (int) $db->lastInsertId();
            } catch (\Exception $e) {
                $janelaId = 0;
            }
        }

        if ($janelaId > 0 && !empty($vendedorIds)) {
            $in = implode(',', array_fill(0, count($vendedorIds), '?'));
            try {
                $stA = $db->prepare("SELECT vendedor_id, tipo, SUM(valor_usd) AS total FROM comissao_ajustes WHERE janela_id = ? AND vendedor_id IN ({$in}) GROUP BY vendedor_id, tipo");
                $stA->execute(array_merge([$janelaId], $vendedorIds));
                $ars = $stA->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($ars as $a) {
                    $vid = (int) ($a['vendedor_id'] ?? 0);
                    $tipo = (string) ($a['tipo'] ?? '');
                    $val = (float) ($a['total'] ?? 0);
                    if ($vid <= 0) continue;
                    if (!isset($ajustesPorVendedor[$vid])) $ajustesPorVendedor[$vid] = 0.0;
                    if (strtolower($tipo) === 'debito') {
                        $ajustesPorVendedor[$vid] -= $val;
                    } else {
                        $ajustesPorVendedor[$vid] += $val;
                    }
                }
            } catch (\Exception $e) {
                $ajustesPorVendedor = [];
            }

            try {
                $stP = $db->prepare("SELECT vendedor_id, SUM(valor_pago_usd) AS total_pago FROM comissao_pagamentos WHERE janela_id = ? AND deleted_at IS NULL AND status IN ('pendente','aprovado') AND vendedor_id IN ({$in}) GROUP BY vendedor_id");
                $stP->execute(array_merge([$janelaId], $vendedorIds));
                $prs = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($prs as $p) {
                    $vid = (int) ($p['vendedor_id'] ?? 0);
                    if ($vid <= 0) continue;
                    $pagosPorVendedor[$vid] = (float) ($p['total_pago'] ?? 0);
                }
            } catch (\Exception $e) {
                $pagosPorVendedor = [];
            }

            try {
                $stPH = $db->prepare("SELECT * FROM comissao_pagamentos WHERE janela_id = ? AND deleted_at IS NULL AND vendedor_id IN ({$in}) ORDER BY id DESC");
                $stPH->execute(array_merge([$janelaId], $vendedorIds));
                $ph = $stPH->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($ph as $p) {
                    $vid = (int) ($p['vendedor_id'] ?? 0);
                    if ($vid <= 0) continue;
                    if (!isset($pagamentosPorVendedor[$vid])) $pagamentosPorVendedor[$vid] = [];
                    $pagamentosPorVendedor[$vid][] = $p;
                }
            } catch (\Exception $e) {
                $pagamentosPorVendedor = [];
            }
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comissões gerais - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('pagamentos');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-0">Comissões gerais</h1>
                    <div class="text-muted small">Janela por data de pagamento</div>
                </div>
                <div>
                    <a class="btn btn-secondary" href="/admin/pagamentos"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
                </div>
            </div>';

        echo '<form method="GET" class="row g-3 align-items-end mb-4">
            <div class="col-md-6">
                <label class="form-label">Janela</label>
                <select class="form-select" name="janela">';

        foreach ($windows as $w) {
            $key = $w['inicio']->format('Y-m-d') . '|' . $w['fim']->format('Y-m-d');
            $label = $w['inicio']->format('d/m/Y') . ' - ' . $w['fim']->format('d/m/Y');
            $sel = ($selected && $key === ($selected['inicio']->format('Y-m-d') . '|' . $selected['fim']->format('Y-m-d'))) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }

        echo '</select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Aplicar</button>
            </div>
        </form>';

        if (empty($kpis)) {
            echo '<div class="alert alert-warning">Nenhum dado encontrado para esta janela.</div>';
        } else {
            echo '<div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Pedidos</th>
                            <th>Ticket médio</th>
                            <th>Top produto</th>
                            <th>Total kg</th>
                            <th>Comissão (USD)</th>
                            <th>Ajustes (USD)</th>
                            <th>Total (USD)</th>
                            <th>Pago (USD)</th>
                            <th>Saldo (USD)</th>
                            <th style="width: 280px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($kpis as $vid => $r) {
                $aj = (float) ($ajustesPorVendedor[$vid] ?? 0);
                $calc = (float) ($r['comissao_usd'] ?? 0);
                $total = $calc + $aj;
                $pago = (float) ($pagosPorVendedor[$vid] ?? 0);
                $saldo = $total - $pago;

                echo '<tr>
                    <td><strong>' . htmlspecialchars((string) ($r['vendedor_nome'] ?? ('#' . $vid)), ENT_QUOTES, 'UTF-8') . '</strong><div class="text-muted small">#' . (int) $vid . '</div></td>
                    <td>' . (int) ($r['pedidos_qtd'] ?? 0) . '</td>
                    <td>$ ' . number_format((float) ($r['ticket_medio'] ?? 0), 2, '.', ',') . '</td>
                    <td>' . htmlspecialchars((string) ($r['top_produto_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . number_format((float) ($r['kg_total'] ?? 0), 2, '.', ',') . '</td>
                    <td><strong>$ ' . number_format($calc, 2, '.', ',') . '</strong></td>
                    <td>$ ' . number_format($aj, 2, '.', ',') . '</td>
                    <td><strong>$ ' . number_format($total, 2, '.', ',') . '</strong></td>
                    <td>$ ' . number_format($pago, 2, '.', ',') . '</td>
                    <td><strong>$ ' . number_format($saldo, 2, '.', ',') . '</strong></td>
                    <td>
                        <form method="POST" action="/admin/pagamentos/comissoes-gerais/ajuste" class="d-flex flex-wrap gap-2 mb-2">
                            <input type="hidden" name="janela_id" value="' . (int) $janelaId . '">
                            <input type="hidden" name="vendedor_id" value="' . (int) $vid . '">
                            <select class="form-select form-select-sm" name="tipo" style="width: 110px">
                                <option value="credito">Crédito</option>
                                <option value="debito">Débito</option>
                            </select>
                            <input class="form-control form-control-sm" name="valor_usd" placeholder="Valor USD" style="width: 110px">
                            <input class="form-control form-control-sm" name="motivo" placeholder="Motivo" style="width: 160px">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Ajustar</button>
                        </form>

                        <form method="POST" action="/admin/pagamentos/comissoes-gerais/pagamento" class="d-flex flex-wrap gap-2">
                            <input type="hidden" name="janela_id" value="' . (int) $janelaId . '">
                            <input type="hidden" name="vendedor_id" value="' . (int) $vid . '">
                            <input class="form-control form-control-sm" name="valor_pago_usd" placeholder="Pagar USD" style="width: 110px">
                            <input class="form-control form-control-sm" name="metodo" placeholder="Método" style="width: 110px">
                            <button class="btn btn-sm btn-success" type="submit">Registrar</button>
                        </form>
                    </td>
                </tr>';

                $hist = $pagamentosPorVendedor[$vid] ?? [];
                if (!empty($hist)) {
                    echo '<tr class="table-light"><td colspan="11">';
                    foreach ($hist as $p) {
                        $pid = (int) ($p['id'] ?? 0);
                        $st = (string) ($p['status'] ?? '');
                        $valPago = (float) ($p['valor_pago_usd'] ?? 0);
                        echo '<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                            <div>
                                <strong>Pagamento #' . $pid . '</strong>
                                <span class="badge bg-' . ($st === 'aprovado' ? 'success' : ($st === 'cancelado' ? 'secondary' : 'warning')) . ' ms-2">' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . '</span>
                                <div class="text-muted small">$ ' . number_format($valPago, 2, '.', ',') . ' | ' . htmlspecialchars((string) ($p['metodo'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="POST" action="/admin/pagamentos/comissoes-gerais/aprovar/' . $pid . '">
                                    <button class="btn btn-sm btn-outline-success" type="submit">Aprovar</button>
                                </form>
                                <form method="POST" action="/admin/pagamentos/comissoes-gerais/deletar/' . $pid . '">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Deletar</button>
                                </form>
                            </div>
                        </div>';
                    }
                    echo '</td></tr>';
                }
            }

            echo '</tbody></table></div>';
        }

        echo '</main>
        </div>
    </div>';

        renderAdminScripts();

        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function criarAjusteComissaoGeral(Request $request) {
        $db = \Config\Database::getConnection();
        $janelaId = (int) $request->getParam('janela_id', 0);
        $vendedorId = (int) $request->getParam('vendedor_id', 0);
        $tipo = strtolower(trim((string) $request->getParam('tipo', 'credito')));
        $valorUsd = (float) $request->getParam('valor_usd', 0);
        $motivo = (string) $request->getParam('motivo', '');

        if ($janelaId <= 0 || $vendedorId <= 0 || $valorUsd <= 0) {
            header('Location: /admin/pagamentos/comissoes-gerais');
            exit;
        }

        if ($tipo !== 'debito') {
            $tipo = 'credito';
        }

        try {
            $stmt = $db->prepare('INSERT INTO comissao_ajustes (janela_id, vendedor_id, tipo, valor_usd, motivo, criado_por, created_at) VALUES (:j, :v, :t, :val, :m, :cp, NOW())');
            $stmt->execute([
                ':j' => $janelaId,
                ':v' => $vendedorId,
                ':t' => $tipo,
                ':val' => $valorUsd,
                ':m' => $motivo,
                ':cp' => $this->getAdminUserId() ?: null,
            ]);
        } catch (\Exception $e) {
        }

        header('Location: /admin/pagamentos/comissoes-gerais');
        exit;
    }

    public function criarPagamentoComissaoGeral(Request $request) {
        $db = \Config\Database::getConnection();
        $janelaId = (int) $request->getParam('janela_id', 0);
        $vendedorId = (int) $request->getParam('vendedor_id', 0);
        $valorPago = (float) $request->getParam('valor_pago_usd', 0);
        $metodo = (string) $request->getParam('metodo', '');

        if ($janelaId <= 0 || $vendedorId <= 0 || $valorPago <= 0) {
            header('Location: /admin/pagamentos/comissoes-gerais');
            exit;
        }

        $janela = null;
        try {
            $st = $db->prepare('SELECT * FROM comissao_janelas WHERE id = :id LIMIT 1');
            $st->execute([':id' => $janelaId]);
            $janela = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $janela = null;
        }

        $valorCalculado = 0.0;
        $valorAjustes = 0.0;
        $valorJaPago = 0.0;
        $saldoDisponivel = 0.0;

        if ($janela) {
            try {
                $inicio = new \DateTime((string) ($janela['data_inicio'] ?? ''));
                $fim = new \DateTime((string) ($janela['data_fim'] ?? ''));
                $inicio->setTime(0, 0, 0);
                $fim->setTime(23, 59, 59);
                $kpis = $this->getCommissionKpisForWindow($db, $inicio, $fim);
                if (isset($kpis[$vendedorId])) {
                    $valorCalculado = (float) ($kpis[$vendedorId]['comissao_usd'] ?? 0);
                }
            } catch (\Exception $e) {
                $valorCalculado = 0.0;
            }

            try {
                $stA = $db->prepare('SELECT tipo, SUM(valor_usd) AS total FROM comissao_ajustes WHERE janela_id = :j AND vendedor_id = :v GROUP BY tipo');
                $stA->execute([':j' => $janelaId, ':v' => $vendedorId]);
                $ars = $stA->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($ars as $a) {
                    $t = strtolower((string) ($a['tipo'] ?? ''));
                    $val = (float) ($a['total'] ?? 0);
                    if ($t === 'debito') {
                        $valorAjustes -= $val;
                    } else {
                        $valorAjustes += $val;
                    }
                }
            } catch (\Exception $e) {
                $valorAjustes = 0.0;
            }

            try {
                $stP = $db->prepare("SELECT COALESCE(SUM(valor_pago_usd),0) AS total_pago FROM comissao_pagamentos WHERE janela_id = :j AND vendedor_id = :v AND deleted_at IS NULL AND status IN ('pendente','aprovado')");
                $stP->execute([':j' => $janelaId, ':v' => $vendedorId]);
                $valorJaPago = (float) ($stP->fetchColumn() ?: 0);
            } catch (\Exception $e) {
                $valorJaPago = 0.0;
            }
        }

        $valorTotal = $valorCalculado + $valorAjustes;

        $saldoDisponivel = $valorTotal - $valorJaPago;
        if ($saldoDisponivel < 0) {
            $saldoDisponivel = 0.0;
        }

        if ($valorPago > $saldoDisponivel) {
            $valorPago = $saldoDisponivel;
        }

        if ($valorPago <= 0) {
            header('Location: /admin/pagamentos/comissoes-gerais');
            exit;
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare('INSERT INTO comissao_pagamentos (janela_id, vendedor_id, valor_calculado_usd, valor_ajustes_usd, valor_total_usd, valor_pago_usd, metodo, status, created_at) VALUES (:j, :v, :vc, :va, :vt, :vp, :m, :st, NOW())');
            $stmt->execute([
                ':j' => $janelaId,
                ':v' => $vendedorId,
                ':vc' => $valorCalculado,
                ':va' => $valorAjustes,
                ':vt' => $valorTotal,
                ':vp' => $valorPago,
                ':m' => $metodo,
                ':st' => 'pendente',
            ]);

            $pagamentoId = (int) $db->lastInsertId();

            if ($pagamentoId > 0 && $janela) {
                $inicio = new \DateTime((string) ($janela['data_inicio'] ?? ''));
                $fim = new \DateTime((string) ($janela['data_fim'] ?? ''));
                $inicio->setTime(0, 0, 0);
                $fim->setTime(23, 59, 59);

                $pedidosCom = $this->getPedidosComissaoForWindowAndVendedor($db, $vendedorId, $inicio, $fim);

                $pedidoIds = array_values(array_filter(array_map(fn($p) => (int) ($p['pedido_id'] ?? 0), $pedidosCom), fn($v) => $v > 0));
                $jaPagoPorPedido = [];
                if (!empty($pedidoIds)) {
                    $in = implode(',', array_fill(0, count($pedidoIds), '?'));
                    $sqlJa = "SELECT cpp.pedido_id, COALESCE(SUM(cpp.valor_comissao_usd),0) AS total_pago\n                            FROM comissao_pagamento_pedidos cpp\n                            INNER JOIN comissao_pagamentos cp ON cp.id = cpp.pagamento_id\n                            WHERE cp.janela_id = ? AND cp.vendedor_id = ? AND cp.deleted_at IS NULL AND cp.status IN ('pendente','aprovado') AND cpp.pedido_id IN ({$in})\n                            GROUP BY cpp.pedido_id";
                    $stJa = $db->prepare($sqlJa);
                    $stJa->execute(array_merge([$janelaId, $vendedorId], $pedidoIds));
                    $rowsJa = $stJa->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($rowsJa as $r) {
                        $pid = (int) ($r['pedido_id'] ?? 0);
                        if ($pid <= 0) continue;
                        $jaPagoPorPedido[$pid] = (float) ($r['total_pago'] ?? 0);
                    }
                }

                $restante = $valorPago;
                foreach ($pedidosCom as $p) {
                    if ($restante <= 0) {
                        break;
                    }
                    $pid = (int) ($p['pedido_id'] ?? 0);
                    if ($pid <= 0) continue;
                    $comissaoPedido = (float) ($p['comissao_usd'] ?? 0);
                    if ($comissaoPedido <= 0) continue;

                    $ja = (float) ($jaPagoPorPedido[$pid] ?? 0);
                    $disponivelPedido = $comissaoPedido - $ja;
                    if ($disponivelPedido <= 0) {
                        continue;
                    }

                    $alocar = ($restante < $disponivelPedido) ? $restante : $disponivelPedido;
                    if ($alocar <= 0) continue;

                    $stIns = $db->prepare('INSERT INTO comissao_pagamento_pedidos (pagamento_id, pedido_id, valor_comissao_usd, created_at) VALUES (:pg, :pid, :val, NOW())');
                    $stIns->execute([':pg' => $pagamentoId, ':pid' => $pid, ':val' => $alocar]);
                    $restante -= $alocar;
                }
            }

            $db->commit();
        } catch (\Exception $e) {
            try {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            } catch (\Exception $e2) {
            }
        }

        header('Location: /admin/pagamentos/comissoes-gerais');
        exit;
    }

    public function aprovarPagamentoComissaoGeral(Request $request) {
        $db = \Config\Database::getConnection();
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            header('Location: /admin/pagamentos/comissoes-gerais');
            exit;
        }

        try {
            $stmt = $db->prepare("UPDATE comissao_pagamentos SET status = 'aprovado', aprovado_por = :ap, aprovado_em = NOW() WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([
                ':ap' => $this->getAdminUserId() ?: null,
                ':id' => $id,
            ]);
        } catch (\Exception $e) {
        }

        header('Location: /admin/pagamentos/comissoes-gerais');
        exit;
    }

    public function deletarPagamentoComissaoGeral(Request $request) {
        $db = \Config\Database::getConnection();
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            header('Location: /admin/pagamentos/comissoes-gerais');
            exit;
        }

        try {
            $stmt = $db->prepare("UPDATE comissao_pagamentos SET status = 'cancelado', deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);
        } catch (\Exception $e) {
        }

        header('Location: /admin/pagamentos/comissoes-gerais');
        exit;
    }
}
