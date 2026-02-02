<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\PedidoEcommerce;
use App\Services\PdfPedidoService;
use App\Services\PaymentService;
use App\Services\AuthService;

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
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .order-card { 
            transition: none;
            border-left: 4px solid #dee2e6;
        }
        .order-card .badge {
            font-size: 1.2rem;
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('pedidos');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Pedidos (' . $total . ')</h1>
                    <div>
                        <a href="/admin/pedidos/novo-manual" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-1"></i>Novo Pedido Manual
                        </a>
                        <a href="/admin/pedidos/comissoes" class="btn btn-outline-primary me-2">
                            <i class="fas fa-percentage me-1"></i>Minhas Comissões
                        </a>
                        <button type="button" class="btn btn-success me-2" onclick="alert(\'Funcionalidade em desenvolvimento\')">
                            <i class="fas fa-download me-1"></i>Exportar
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
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
                            <option value="processando" ' . ($status === 'processando' ? 'selected' : '') . '>Processando</option>
                            <option value="produto_consolidado" ' . ($status === 'produto_consolidado' ? 'selected' : '') . '>Produto Consolidado</option>
                            <option value="em_transporte" ' . ($status === 'em_transporte' ? 'selected' : '') . '>Em Transporte</option>
                            <option value="aguardando_liberacao_aduaneira" ' . ($status === 'aguardando_liberacao_aduaneira' ? 'selected' : '') . '>Aguardando Liberação Aduaneira</option>
                            <option value="enviado_ao_destinatario" ' . ($status === 'enviado_ao_destinatario' ? 'selected' : '') . '>Enviado ao Destinatário</option>
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
                                            <a href="/admin/pedidos/excluir/' . $pedido['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\"Tem certeza que deseja excluir este pedido? Essa ação não pode ser desfeita.\");">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <select class="form-select form-select-sm" style="width: auto;" onchange="location.href=\'/admin/pedidos/atualizar-status/' . $pedido['id'] . '/\'+this.value">
                                                <option value="">Status</option>
                                                <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>Pendente</option>
                                                <option value="pago" ' . ($pedido['status'] == 'pago' ? 'selected' : '') . '>Pago</option>
                                                <option value="processando" ' . ($pedido['status'] == 'processando' ? 'selected' : '') . '>Processando</option>
                                                <option value="produto_consolidado" ' . ($pedido['status'] == 'produto_consolidado' ? 'selected' : '') . '>Produto Consolidado</option>
                                                <option value="em_transporte" ' . ($pedido['status'] == 'em_transporte' ? 'selected' : '') . '>Em Transporte</option>
                                                <option value="aguardando_liberacao_aduaneira" ' . ($pedido['status'] == 'aguardando_liberacao_aduaneira' ? 'selected' : '') . '>Aguardando Liberação Aduaneira</option>
                                                <option value="enviado_ao_destinatario" ' . ($pedido['status'] == 'enviado_ao_destinatario' ? 'selected' : '') . '>Enviado ao Destinatário</option>
                                                <option value="enviado" ' . ($pedido['status'] == 'enviado' ? 'selected' : '') . '>Enviado</option>
                                                <option value="entregue" ' . ($pedido['status'] == 'entregue' ? 'selected' : '') . '>Entregue</option>
                                                <option value="cancelado" ' . ($pedido['status'] == 'cancelado' ? 'selected' : '') . '>Cancelado</option>
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
                                            <a href="/admin/pedidos/excluir/' . $pedido['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\"Tem certeza que deseja excluir este pedido? Essa ação não pode ser desfeita.\");">
                                                <i class="fas fa-trash"></i>
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
                                            <a href="/admin/pedidos/excluir/' . $pedido['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\"Tem certeza que deseja excluir este pedido? Essa ação não pode ser desfeita.\");">
                                                <i class="fas fa-trash"></i>
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
                
                echo '</main></div></div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function pdf(Request $request) {
        $id = $request->getParam('id');
        try {
            $pedidoModel = new PedidoEcommerce();
            $pedido = $pedidoModel->getComDetalhes($id);
            if (!$pedido) {
                echo 'Pedido não encontrado';
                return;
            }

            $itens = $pedido['items'] ?? [];

            $paymentDetails = null;
            try {
                $paymentService = new PaymentService();
                $paymentId = (string) ($pedido['pagamento_transacao'] ?? ($pedido['payment_id'] ?? ''));
                $gateway = (string) ($pedido['pagamento_gateway'] ?? ($pedido['payment_gateway'] ?? ''));
                if ($paymentId !== '' && strtolower($gateway) === 'asaas') {
                    $paymentDetails = $paymentService->obterPagamentoAsaas($paymentId);
                }
            } catch (\Exception $e) {
                $paymentDetails = null;
            }

            $svc = new PdfPedidoService();
            $html = $svc->renderPedidoHtml($pedido, is_array($itens) ? $itens : [], is_array($paymentDetails) ? $paymentDetails : null);
            $svc->outputPdfOrHtml($html, 'pedido_' . (string) ($pedido['codigo_pedido'] ?? $pedido['id'] ?? $id));
        } catch (\Exception $e) {
            echo 'Erro ao gerar PDF: ' . $e->getMessage();
        }
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

            $quantidadeTotalItens = 0;
            if (is_array($itens)) {
                foreach ($itens as $it) {
                    $quantidadeTotalItens += (int) ($it['quantidade'] ?? 0);
                }
            }
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
            exit;
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . ' - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .status-pendente { background-color: #ffc107; }
        .status-pago { background-color: #28a745; }
        .status-processando { background-color: #0d6efd; }
        .status-produto_consolidado { background-color: #212529; }
        .status-em_transporte { background-color: #17a2b8; }
        .status-aguardando_liberacao_aduaneira { background-color: #6c757d; }
        .status-enviado_ao_destinatario { background-color: #17a2b8; }
        .status-cancelado { background-color: #dc3545; }
        .status-enviado { background-color: #17a2b8; }
        .status-entregue { background-color: #6f42c1; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('pedidos');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-shopping-cart me-2"></i>Detalhes do Pedido #' . $pedido['codigo_pedido'] . '</h2>
                <div>
                    ' . (((string) ($pedido['origem_pedido'] ?? '') === 'manual')
                        ? ('<a href="/admin/pedidos/novo-manual?pedido_id=' . (int) $id . '" class="btn btn-outline-primary me-2">'
                            . '<i class="fas fa-pen-to-square me-1"></i>Editar Pedido Manual</a>')
                        : '') . '
                    <a href="/admin/pedidos/detalhes/' . $id . '/pdf" class="btn btn-outline-dark me-2" target="_blank" rel="noopener">
                        <i class="fas fa-file-pdf me-1"></i>Exportar PDF
                    </a>
                    <a href="/admin/pedidos/editar/' . $id . '" class="btn btn-warning me-2">
                        <i class="fas fa-edit me-1"></i>Editar Pedido
                    </a>
                    <a href="/admin/pedidos/excluir/' . $id . '" class="btn btn-danger me-2" onclick="return confirm(\"Tem certeza que deseja excluir este pedido? Essa ação não pode ser desfeita.\");">
                        <i class="fas fa-trash me-1"></i>Excluir Pedido
                    </a>
                    <a href="/admin/pedidos" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>';

            // Destaque: pendência de pagamento (diferença)
            $colsPedido = [];
            try {
                $pdoCols = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
                $stmtColsP = $pdoCols->query('DESCRIBE pedidos');
                $colsPedido = $stmtColsP ? $stmtColsP->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsPedido = [];
            }
            $temDif = is_array($colsPedido) && in_array('payment_diferenca_id', $colsPedido, true);
            $difId = $temDif ? (string) ($pedido['payment_diferenca_id'] ?? '') : '';
            $difStatus = $temDif ? (string) ($pedido['payment_diferenca_status'] ?? '') : '';
            $difValor = $temDif ? (float) ($pedido['payment_diferenca_valor'] ?? 0) : 0.0;
            $difInvoiceUrl = $temDif ? (string) ($pedido['payment_diferenca_invoice_url'] ?? '') : '';
            $difBoletoUrl = $temDif ? (string) ($pedido['payment_diferenca_bank_slip_url'] ?? '') : '';
            $difPaidAt = $temDif ? (string) ($pedido['payment_diferenca_paid_at'] ?? '') : '';

            $temDebito = ($difId !== '' && $difValor > 0 && $difPaidAt === '');
            if ($temDebito) {
                $link = $difBoletoUrl !== '' ? $difBoletoUrl : $difInvoiceUrl;
                echo '<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div style="font-weight:800;">Pendência de pagamento (diferença)</div>
                            <div class="small">Valor: <strong>R$ ' . number_format($difValor, 2, ',', '.') . '</strong>'
                                . ($difStatus !== '' ? (' | Status: <strong>' . htmlspecialchars($difStatus) . '</strong>') : '')
                                . '</div>
                        </div>
                        <div class="d-flex gap-2">
                            ' . ($link !== '' ? '<a class="btn btn-sm btn-outline-dark" href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener">Abrir link de pagamento</a>' : '') . '
                        </div>
                    </div>';
            } elseif ($temDif && $difId !== '' && $difPaidAt !== '') {
                echo '<div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div style="font-weight:800;">Diferença quitada</div>
                            <div class="small">Pago em: <strong>' . htmlspecialchars(date('d/m/Y H:i', strtotime($difPaidAt))) . '</strong></div>
                        </div>
                    </div>';
            }
            
            // Conteúdo principal
            echo '<div class="row">
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
                                                $img = (string) $item['imagem'];
                                                // Se já for URL externa, usar diretamente
                                                if (preg_match('#^https?://#i', $img) || strpos($img, '//') === 0) {
                                                    if (strpos($img, '//') === 0) {
                                                        $img = 'https:' . $img;
                                                    }
                                                    echo '<img src="' . htmlspecialchars($img) . '" alt="' . htmlspecialchars($item['nome_produto']) . '" 
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">';
                                                } else {
                                                    // Remover caminho duplicado se existir
                                                    $imagemPath = $img;
                                                    if (strpos($imagemPath, 'uploads/produtos/') !== false) {
                                                        $imagemPath = str_replace('uploads/produtos/', '', $imagemPath);
                                                    }
                                                    echo '<img src="/uploads/produtos/' . htmlspecialchars($imagemPath) . '" alt="' . htmlspecialchars($item['nome_produto']) . '" 
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">';
                                                }
                                            }
                                            
                                            $nomeProduto = (string) ($item['nome_produto'] ?? 'Produto #' . $item['produto_id']);
                                            $sku = (string) ($item['nome_produto_sku'] ?? $item['referencia'] ?? '');
                                            $urlOriginal = (string) ($item['url_original'] ?? '');
                                            $variacaoLabel = (string) ($item['variacao_label'] ?? '');
                                            $variacaoAttrs = $item['variacao_atributos'] ?? null;

                                            $nomeHtml = '';
                                            if ($urlOriginal !== '') {
                                                $nomeHtml = '<a href="' . htmlspecialchars($urlOriginal) . '" target="_blank" class="text-decoration-none">' . htmlspecialchars($nomeProduto) . '</a>';
                                            } else {
                                                $nomeHtml = htmlspecialchars($nomeProduto);
                                            }

                                            $extraHtml = '';
                                            if ($sku !== '') {
                                                $extraHtml .= '<div class="small text-muted">SKU/Ref: ' . htmlspecialchars($sku) . '</div>';
                                            }
                                            if ($urlOriginal !== '') {
                                                $extraHtml .= '<div class="small text-muted">link de acesso original</div>';
                                            }
                                            if ($variacaoLabel !== '') {
                                                $extraHtml .= '<div style="font-weight: 700; font-size: 1.05rem;">Variação: ' . htmlspecialchars($variacaoLabel) . '</div>';
                                            }
                                            if (is_array($variacaoAttrs) && !empty($variacaoAttrs)) {
                                                $pairs = [];
                                                foreach ($variacaoAttrs as $k => $v) {
                                                    if ($k === '' || $v === null) continue;
                                                    $pairs[] = (string) $k . ': ' . (string) $v;
                                                }
                                                if (!empty($pairs)) {
                                                    $extraHtml .= '<div style="font-weight: 700; font-size: 1.05rem;">' . htmlspecialchars(implode(' | ', $pairs)) . '</div>';
                                                }
                                            }

                                            echo '</td>
                                                <td>' . $nomeHtml . $extraHtml . '</td>
                                                <td>' . $item['produto_id'] . '</td>
                                                <td>' . htmlspecialchars($item['nome_produto_sku'] ?? $item['referencia'] ?? 'N/A') . '</td>
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
                    </div>';
                    
                    echo '<div class="col-md-6">
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
                                            <tr><td><strong>Status</strong></td><td><span class="badge status-' . $pedido['status'] . '">' . htmlspecialchars($this->getStatusLabel((string) ($pedido['status'] ?? ''))) . '</span></td></tr>
                                            <tr><td><strong>Nome Cliente</strong></td><td>' . htmlspecialchars($pedido['cliente_nome'] ?? $pedido['nome']) . '</td></tr>
                                            <tr><td><strong>Suite Cliente</strong></td><td>' . (!empty($pedido['cliente_suite']) ? (int) $pedido['cliente_suite'] : 'N/A') . '</td></tr>
                                            <tr><td><strong>Data Criação</strong></td><td>' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</td></tr>
                                            <tr><td><strong>Última Atualização</strong></td><td>' . date('d/m/Y H:i', strtotime($pedido['updated_at'])) . '</td></tr>
                                            <tr><td><strong>Usuário ID</strong></td><td>' . $pedido['usuario_id'] . '</td></tr>
                                            <tr><td><strong>Cliente ID</strong></td><td>' . $pedido['cliente_id'] . '</td></tr>
                                            ' . (!empty($pedido['origem_pedido']) ? ('<tr><td><strong>Origem</strong></td><td>' . htmlspecialchars($pedido['origem_pedido']) . (!empty($pedido['admin_criador_nome']) || !empty($pedido['admin_criador_email']) ? ('<div class="small text-muted">Admin: ' . htmlspecialchars((string) ($pedido['admin_criador_nome'] ?? '')) . (!empty($pedido['admin_criador_email']) ? (' &lt;' . htmlspecialchars((string) $pedido['admin_criador_email']) . '&gt;') : '') . '</div>') : '') . '</td></tr>') : '') . '
                                            <tr><td><strong>Quantidade de itens</strong></td><td>' . (int) $quantidadeTotalItens . '</td></tr>
                                            <tr><td><strong>Subtotal</strong></td><td>R$ ' . number_format($pedido['subtotal'], 2, ',', '.') . '</td></tr>
                                            <tr><td><strong>Serviços</strong></td><td>R$ ' . number_format($pedido['servicos'], 2, ',', '.') . '</td></tr>
                                            <tr><td><strong>Impostos</strong></td><td>R$ ' . number_format($pedido['impostos'], 2, ',', '.') . '</td></tr>
                                            <tr><td><strong>Frete</strong></td><td>' . (((float) ($pedido['frete'] ?? 0)) <= 0 ? 'Frete grátis' : ('R$ ' . number_format($pedido['frete'], 2, ',', '.'))) . '</td></tr>
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
                                <p><strong>Status:</strong> <span class="badge status-' . $pedido['status'] . '">' . htmlspecialchars($this->getStatusLabel((string) ($pedido['status'] ?? ''))) . '</span></p>
                                <p><strong>Data:</strong> ' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</p>
                                <p><strong>Forma Pagamento:</strong> ' . htmlspecialchars($pedido['forma_pagamento'] ?? 'N/A') . '</p>
                                <p><strong>Frete:</strong> ' . (((float) ($pedido['frete'] ?? 0)) <= 0 ? 'Frete grátis' : ('R$ ' . number_format($pedido['frete'], 2, ',', '.'))) . '</p>
                                <hr>
                                <div class="mb-3">
                                    <h6 class="mb-2">Pagamento</h6>
                                    <p class="mb-1"><strong>Método:</strong> ' . htmlspecialchars($pedido['pagamento_metodo'] ?? $pedido['forma_pagamento'] ?? 'N/A') . '</p>
                                    <p class="mb-1"><strong>Status:</strong> ' . htmlspecialchars($pedido['pagamento_status'] ?? 'Pendente') . '</p>
                                    <p class="mb-1"><strong>Gateway:</strong> ' . htmlspecialchars($pedido['pagamento_gateway'] ?? 'N/A') . '</p>
                                    <p class="mb-1"><strong>Transação:</strong> ' . htmlspecialchars($pedido['pagamento_transacao'] ?? 'N/A') . '</p>
                                    <p class="mb-0"><strong>Data:</strong> ' . (!empty($pedido['pagamento_data']) ? date('d/m/Y H:i', strtotime($pedido['pagamento_data'])) : 'N/A') . '</p>';

                                    $pgGateway = (string) ($pedido['pagamento_gateway'] ?? '');
                                    $pgMetodo = strtoupper((string) ($pedido['pagamento_metodo'] ?? $pedido['forma_pagamento'] ?? ''));
                                    $pgStatus = strtoupper((string) ($pedido['pagamento_status'] ?? ''));
                                    $podeReemitir = ($pgGateway === 'asaas') && in_array($pgMetodo, ['PIX', 'BOLETO', 'PXD', 'PIX '], true) && !in_array($pgStatus, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true);

                                    if ($podeReemitir) {
                                        echo '<form method="POST" action="/admin/pedidos/reemitir-pagamento/' . (int) $pedido['id'] . '" class="mt-2">'
                                            . '<button type="submit" class="btn btn-outline-secondary btn-sm">Gerar nova cobrança</button>'
                                            . '</form>';
                                    }

                                echo '</div>
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label">Atualizar Status:</label>
                                    <select class="form-select" id="novo_status">
                                        <option value="">Selecione...</option>
                                        <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>Pendente</option>
                                        <option value="pago" ' . ($pedido['status'] == 'pago' ? 'selected' : '') . '>Pago</option>
                                        <option value="processando" ' . ($pedido['status'] == 'processando' ? 'selected' : '') . '>Processando</option>
                                        <option value="produto_consolidado" ' . ($pedido['status'] == 'produto_consolidado' ? 'selected' : '') . '>Produto Consolidado</option>
                                        <option value="em_transporte" ' . ($pedido['status'] == 'em_transporte' ? 'selected' : '') . '>Em Transporte</option>
                                        <option value="aguardando_liberacao_aduaneira" ' . ($pedido['status'] == 'aguardando_liberacao_aduaneira' ? 'selected' : '') . '>Aguardando Liberação Aduaneira</option>
                                        <option value="enviado_ao_destinatario" ' . ($pedido['status'] == 'enviado_ao_destinatario' ? 'selected' : '') . '>Enviado ao Destinatário</option>
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
                                <p><strong>Suite:</strong> ' . (!empty($pedido['cliente_suite']) ? (int) $pedido['cliente_suite'] : 'N/A') . '</p>
                                <hr>
                                <p><strong>Endereço:</strong><br>' .
                                    htmlspecialchars(
                                        trim(
                                            ($pedido['endereco_entrega'] ?? '') .
                                            (!empty($pedido['numero_entrega']) ? ', ' . $pedido['numero_entrega'] : '') .
                                            (!empty($pedido['complemento_entrega']) ? ' - ' . $pedido['complemento_entrega'] : '') .
                                            (!empty($pedido['bairro_entrega']) ? ' - ' . $pedido['bairro_entrega'] : '') .
                                            (!empty($pedido['cidade_entrega']) ? ' - ' . $pedido['cidade_entrega'] : '') .
                                            (!empty($pedido['estado_entrega']) ? '/' . $pedido['estado_entrega'] : '') .
                                            (!empty($pedido['cep_entrega']) ? ' - CEP: ' . $pedido['cep_entrega'] : '')
                                        )
                                    ) .
                                '</p>
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
    </script>';

    // Renderizar scripts
    renderAdminScripts();
        
    echo '</body>
</html>';
    exit;

    }

    public function reemitirPagamento(Request $request) {
        $id = (int) $request->getParam('id');
        if (empty($id)) {
            header('Location: /admin/pedidos');
            exit;
        }

        try {
            $paymentService = new PaymentService();
            $paymentService->reemitirCobrancaAsaasPorPedido($id);
            header('Location: /admin/pedidos/detalhes/' . $id . '?reemitido=1');
            exit;
        } catch (\Exception $e) {
            header('Location: /admin/pedidos/detalhes/' . $id . '?reemitido=0');
            exit;
        }
    }

    private function formatarMoeda($valor, $moeda) {
        if ($moeda === 'USD') {
            return '$ ' . number_format($valor, 2, '.', ',');
        } else {
            return 'R$ ' . number_format($valor, 2, ',', '.');
        }
    }

    private function getStatusLabel(string $status): string {
        $map = [
            'pendente' => 'Pendente',
            'pago' => 'Pago',
            'processando' => 'Processando',
            'produto_consolidado' => 'Produto Consolidado',
            'em_transporte' => 'Em Transporte',
            'aguardando_liberacao_aduaneira' => 'Aguardando Liberação Aduaneira',
            'enviado_ao_destinatario' => 'Enviado ao Destinatário',
            'enviado' => 'Enviado',
            'entregue' => 'Entregue',
            'cancelado' => 'Cancelado',

            // legado
            'pagamento' => 'Pagamento',
            'aprovado' => 'Aprovado',
            'separacao' => 'Separação',
        ];
        $status = trim($status);
        return $map[$status] ?? ($status !== '' ? ucfirst($status) : '');
    }
    
    private function getStatusIcon($status) {
        $icons = [
            'pendente' => 'fas fa-clock',
            'pago' => 'fas fa-check-circle',
            'processando' => 'fas fa-cogs',
            'produto_consolidado' => 'fas fa-boxes-stacked',
            'em_transporte' => 'fas fa-truck-moving',
            'aguardando_liberacao_aduaneira' => 'fas fa-passport',
            'enviado_ao_destinatario' => 'fas fa-route',
            'enviado' => 'fas fa-truck',
            'entregue' => 'fas fa-check-double',
            'cancelado' => 'fas fa-times-circle'
        ];
        // legado
        if (!isset($icons[$status])) {
            $icons['pagamento'] = 'fas fa-credit-card';
            $icons['aprovado'] = 'fas fa-check-circle';
            $icons['separacao'] = 'fas fa-box';
        }
        return $icons[$status] ?? 'fas fa-question-circle';
    }
    
    private function getStatusColor($status) {
        $colors = [
            'pendente' => 'warning',
            'pago' => 'success',
            'processando' => 'primary',
            'produto_consolidado' => 'dark',
            'em_transporte' => 'info',
            'aguardando_liberacao_aduaneira' => 'secondary',
            'enviado_ao_destinatario' => 'info',
            'enviado' => 'info',
            'entregue' => 'success',
            'cancelado' => 'danger'
        ];
        // legado
        if (!isset($colors[$status])) {
            $colors['pagamento'] = 'info';
            $colors['aprovado'] = 'success';
            $colors['separacao'] = 'primary';
        }
        return $colors[$status] ?? 'secondary';
    }

    public function comissoes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
        $admin = $auth->getUsuarioLogado();

        $pedidoModel = new PedidoEcommerce();
        $resumo = [
            'pedidos' => [],
            'total_faturado' => 0.0,
            'total_custo_produtos' => 0.0,
            'total_liquido' => 0.0,
            'percentual_comissao' => 0.0,
            'valor_comissao' => 0.0,
            'faixas' => [],
        ];
        try {
            $resumo = $pedidoModel->getResumoComissoesPedidosManuaisPorAdminCriador((int) ($admin['id'] ?? 0));
        } catch (\Exception $e) {
            $resumo = $resumo;
        }

        $cPedidos = is_array($resumo) && isset($resumo['pedidos']) && is_array($resumo['pedidos']) ? $resumo['pedidos'] : [];
        $totalFaturado = (float) ($resumo['total_faturado'] ?? 0);
        $totalCusto = (float) ($resumo['total_custo_produtos'] ?? 0);
        $totalLiquido = (float) ($resumo['total_liquido'] ?? 0);
        $percent = (float) ($resumo['percentual_comissao'] ?? 0);
        $valorComissao = (float) ($resumo['valor_comissao'] ?? 0);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Comissões - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('pedidos-comissoes');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Minhas Comissões</h1>
                    <div>
                        <a href="/admin/pedidos" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left"></i> Voltar</a>
                        <a href="/admin/pedidos/novo-manual" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Pedido Manual</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Total Faturado (Manuais)</div>
                            <div class="fs-5 fw-bold">R$ ' . number_format($totalFaturado, 2, ',', '.') . '</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Custo dos Produtos</div>
                            <div class="fs-5 fw-bold">R$ ' . number_format($totalCusto, 2, ',', '.') . '</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Total Líquido</div>
                            <div class="fs-5 fw-bold">R$ ' . number_format($totalLiquido, 2, ',', '.') . '</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Comissão</div>
                            <div class="fs-5 fw-bold">' . number_format($percent, 2, ',', '.') . '% (R$ ' . number_format($valorComissao, 2, ',', '.') . ')</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Pedidos Manuais Pagos</strong></div>
                    <div class="card-body">';

        if (empty($cPedidos)) {
            echo '<div class="text-muted">Sem pedidos manuais pagos para este admin.</div>';
        } else {
            echo '<div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Data</th>
                                <th class="text-end">Faturado</th>
                                <th class="text-end">Custo</th>
                                <th class="text-end">Líquido</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>';

            foreach ($cPedidos as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $codigo = (string) ($p['codigo'] ?? $pid);
                $fat = (float) ($p['faturado'] ?? 0);
                $cus = (float) ($p['custo'] ?? 0);
                $liq = (float) ($p['liquido'] ?? ($fat - $cus));
                $dt = (string) ($p['created_at'] ?? '');
                $dtFmt = $dt !== '' ? date('d/m/Y H:i', strtotime($dt)) : '-';

                echo '<tr>
                        <td><strong>' . htmlspecialchars($codigo) . '</strong><div class="text-muted small">#' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</div></td>
                        <td>' . htmlspecialchars($dtFmt) . '</td>
                        <td class="text-end fw-semibold">R$ ' . number_format($fat, 2, ',', '.') . '</td>
                        <td class="text-end">R$ ' . number_format($cus, 2, ',', '.') . '</td>
                        <td class="text-end">R$ ' . number_format($liq, 2, ',', '.') . '</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="/admin/pedidos/detalhes/' . $pid . '"><i class="fas fa-eye"></i></a></td>
                      </tr>';
            }

            echo '        </tbody>
                    </table>
                </div>';
        }

        echo '        </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }
    
    public function atualizarStatus(Request $request) {
        $id = $request->getParam('id');
        $novoStatus = $request->getParam('status');
        
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $cols = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE pedidos');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $statusCol = 'status';
            if (is_array($cols) && !in_array('status', $cols, true)) {
                foreach (['status_pedido', 'pedido_status'] as $cand) {
                    if (in_array($cand, $cols, true)) {
                        $statusCol = $cand;
                        break;
                    }
                }
            }

            // Se a coluna de status for ENUM, validar se o valor existe; caso contr2rio o MySQL pode gravar '' (string vazia)
            $enumAllowed = null;
            try {
                $stmtType = $pdo->prepare("SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = ? LIMIT 1");
                $stmtType->execute([$statusCol]);
                $colInfo = $stmtType->fetch(\PDO::FETCH_ASSOC);
                if (is_array($colInfo) && isset($colInfo['DATA_TYPE']) && strtolower((string) $colInfo['DATA_TYPE']) === 'enum') {
                    $colType = (string) ($colInfo['COLUMN_TYPE'] ?? '');
                    // COLUMN_TYPE vem como enum('a','b',...)
                    if (preg_match("/^enum\((.*)\)$/i", $colType, $m)) {
                        $raw = $m[1];
                        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $raw, $mm);
                        $vals = [];
                        if (!empty($mm[1])) {
                            foreach ($mm[1] as $v) {
                                $vals[] = stripcslashes($v);
                            }
                        }
                        $enumAllowed = $vals;
                    }
                }
            } catch (\Exception $e) {
                $enumAllowed = null;
            }

            if (is_array($enumAllowed) && !empty($enumAllowed) && !in_array((string) $novoStatus, $enumAllowed, true)) {
                echo '<div class="alert alert-danger">Status inválido para a coluna <strong>' . htmlspecialchars($statusCol) . '</strong>: <strong>' . htmlspecialchars((string) $novoStatus) . '</strong>. Esta coluna é ENUM e o MySQL pode converter valores inválidos para <strong>string vazia</strong>, parecendo que "processou" mas não persiste.</div>';
                echo '<div class="alert alert-secondary"><strong>Valores permitidos</strong><br>' . htmlspecialchars(implode(', ', $enumAllowed)) . '</div>';
                echo '<div class="alert alert-warning">Para permitir novos status (ex: produto_consolidado), crie uma migration SQL para atualizar o ENUM (ou trocar para VARCHAR) no banco.</div>';
                echo '<a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            $set = [$statusCol . ' = ?'];
            $params = [$novoStatus];

            // Se marcou como pago/aprovado, manter colunas relacionadas consistentes.
            // Isso impacta diretamente a tela de comissões (que pode filtrar por payment_status).
            $paidValues = ['pago','paid','approved','aprovado','concluido','concluído','confirmed','received','succeeded','success'];
            $isPaid = in_array(strtolower(trim((string) $novoStatus)), $paidValues, true);
            if ($isPaid && is_array($cols)) {
                // 1) pago_em
                if (in_array('pago_em', $cols, true)) {
                    $set[] = 'pago_em = COALESCE(pago_em, NOW())';
                }

                // 2) payment_status / status_pagamento
                if (in_array('payment_status', $cols, true) && $statusCol !== 'payment_status') {
                    $set[] = 'payment_status = ?';
                    $params[] = 'approved';
                }
                if (in_array('status_pagamento', $cols, true) && $statusCol !== 'status_pagamento') {
                    $set[] = 'status_pagamento = ?';
                    $params[] = 'aprovado';
                }

                // 3) status (caso a coluna atualizada tenha sido payment_status/status_pagamento)
                if (in_array('status', $cols, true) && $statusCol !== 'status') {
                    $set[] = 'status = ?';
                    $params[] = 'pago';
                }
            }

            if (is_array($cols) && in_array('updated_at', $cols, true)) {
                $set[] = 'updated_at = NOW()';
            }

            $params[] = $id;
            $stmt = $pdo->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = ?');
            $stmt->execute($params);

            if ($stmt->rowCount() <= 0) {
                echo '<div class="alert alert-warning">Nenhuma linha foi atualizada. Verifique se o pedido existe e se a coluna de status está correta (coluna usada: <strong>' . htmlspecialchars($statusCol) . '</strong>).</div>';
                echo '<a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            $statusColsToCheck = [];
            foreach (['status', 'status_pedido', 'pedido_status'] as $cand) {
                if (is_array($cols) && in_array($cand, $cols, true)) {
                    $statusColsToCheck[] = $cand;
                }
            }
            if (empty($statusColsToCheck)) {
                $statusColsToCheck[] = $statusCol;
            }

            $selectCols = array_values(array_unique($statusColsToCheck));
            $stmtCheck = $pdo->prepare('SELECT ' . implode(', ', $selectCols) . ' FROM pedidos WHERE id = ? LIMIT 1');
            $stmtCheck->execute([$id]);
            $row = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            $persistiu = false;
            if (is_array($row)) {
                foreach ($selectCols as $c) {
                    if (isset($row[$c]) && (string) $row[$c] === (string) $novoStatus) {
                        $persistiu = true;
                        break;
                    }
                }
            }

            if (!$persistiu) {
                echo '<div class="alert alert-danger">O status foi enviado como <strong>' . htmlspecialchars((string) $novoStatus) . '</strong>, mas n\u00e3o permaneceu gravado no banco ap\u00f3s o UPDATE.</div>';
                echo '<div class="alert alert-secondary"><strong>Diagn\u00f3stico</strong><br>Coluna atualizada: <strong>' . htmlspecialchars($statusCol) . '</strong><br>';
                if (is_array($row)) {
                    foreach ($selectCols as $c) {
                        echo htmlspecialchars($c) . ': <strong>' . htmlspecialchars((string) ($row[$c] ?? 'NULL')) . '</strong><br>';
                    }
                } else {
                    echo 'N\u00e3o foi poss\u00edvel reler o registro ap\u00f3s o UPDATE.';
                }
                echo '</div>';
                echo '<a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            if ((string) $novoStatus === 'produto_consolidado') {
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['estoque_reservas']);
                    $temReservas = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temReservas = false;
                }

                // Determinar tabela de itens do pedido (pedido_itens vs pedido_items)
                $itensTable = null;
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['pedido_itens']);
                    $temPedidoItens = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temPedidoItens = false;
                }
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['pedido_items']);
                    $temPedidoItems = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temPedidoItems = false;
                }
                if ($temPedidoItens && !$temPedidoItems) {
                    $itensTable = 'pedido_itens';
                } elseif ($temPedidoItems && !$temPedidoItens) {
                    $itensTable = 'pedido_items';
                } elseif ($temPedidoItens && $temPedidoItems) {
                    // escolher a tabela com mais itens para este pedido
                    $c1 = 0;
                    $c2 = 0;
                    try {
                        $st = $pdo->prepare('SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = ?');
                        $st->execute([(int) $id]);
                        $c1 = (int) ($st->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        $c1 = 0;
                    }
                    try {
                        $st = $pdo->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = ?');
                        $st->execute([(int) $id]);
                        $c2 = (int) ($st->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        $c2 = 0;
                    }
                    $itensTable = ($c2 > $c1) ? 'pedido_items' : 'pedido_itens';
                }

                // Recalcular faltantes: pedido - reservado (e manter pendancias)
                $itens = [];
                if (!empty($itensTable)) {
                    try {
                        $st = $pdo->prepare('SELECT produto_id, quantidade FROM ' . $itensTable . ' WHERE pedido_id = ?');
                        $st->execute([(int) $id]);
                        $itens = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\Exception $e) {
                        $itens = [];
                    }
                }

                $temLista = false;
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['lista_compras']);
                    $temLista = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temLista = false;
                }

                $colsLista = [];
                if ($temLista) {
                    try {
                        $st = $pdo->query('DESCRIBE lista_compras');
                        $colsLista = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
                    } catch (\Exception $e) {
                        $colsLista = [];
                    }
                }
                $temPedidoIdLista = $temLista && is_array($colsLista) && in_array('pedido_id', $colsLista, true);
                $temProdutoIdLista = $temLista && is_array($colsLista) && in_array('produto_id', $colsLista, true);

                // limpar pendancias antigas deste pedido para regravar somente o que faltar
                if ($temPedidoIdLista) {
                    try {
                        $stmtDel = $pdo->prepare('DELETE FROM lista_compras WHERE pedido_id = ?');
                        $stmtDel->execute([(int) $id]);
                    } catch (\Exception $e) {
                    }
                }

                // preparar leitura de reservas do pedido (quantidade efetivamente reservada)
                $temPedidoIdReserva = false;
                $temProdutoIdReserva = false;
                $temQtdReserva = false;
                $temStatusReserva = false;
                if (!empty($temReservas)) {
                    try {
                        $st = $pdo->query('DESCRIBE estoque_reservas');
                        $colsRes = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
                        $temPedidoIdReserva = is_array($colsRes) && in_array('pedido_id', $colsRes, true);
                        $temProdutoIdReserva = is_array($colsRes) && in_array('produto_id', $colsRes, true);
                        $temQtdReserva = is_array($colsRes) && in_array('quantidade_reservada', $colsRes, true);
                        $temStatusReserva = is_array($colsRes) && in_array('status', $colsRes, true);
                    } catch (\Exception $e) {
                        $temPedidoIdReserva = false;
                        $temProdutoIdReserva = false;
                        $temQtdReserva = false;
                        $temStatusReserva = false;
                    }
                }

                // Verificar suporte ao estoque_interno (para dar baixa do que foi realmente reservado)
                $temEstoqueInterno = false;
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['estoque_interno']);
                    $temEstoqueInterno = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temEstoqueInterno = false;
                }

                // Para cada item, pendenciar somente o faltante (qtd_pedido - qtd_reservada) e dar baixa no estoque pelo reservado
                if ($temPedidoIdLista && $temProdutoIdLista && is_array($itens)) {
                    foreach ($itens as $it) {
                        $produtoId = (int) ($it['produto_id'] ?? 0);
                        $qtdPedido = (int) ($it['quantidade'] ?? 0);
                        if ($produtoId <= 0 || $qtdPedido <= 0) continue;

                        $qtdReservada = 0;
                        if ($temPedidoIdReserva && $temProdutoIdReserva && $temQtdReserva) {
                            try {
                                $sql = 'SELECT COALESCE(SUM(quantidade_reservada),0) FROM estoque_reservas WHERE pedido_id = ? AND produto_id = ?';
                                $params = [(int) $id, $produtoId];
                                if ($temStatusReserva) {
                                    $sql .= " AND status = 'ativa'";
                                }
                                $st = $pdo->prepare($sql);
                                $st->execute($params);
                                $qtdReservada = (int) ($st->fetchColumn() ?: 0);
                            } catch (\Exception $e) {
                                $qtdReservada = 0;
                            }
                        }

                        $faltante = $qtdPedido - $qtdReservada;

                        // Baixa fsica do estoque: consome apenas o que estava reservado (o que de fato existia)
                        if ($temEstoqueInterno && $qtdReservada > 0) {
                            $restante = $qtdReservada;
                            try {
                                $stmtLocs = $pdo->prepare(
                                    'SELECT id, quantidade FROM estoque_interno WHERE produto_id = ? AND quantidade > 0 ORDER BY CASE WHEN data_compra IS NULL THEN 1 ELSE 0 END ASC, data_compra ASC, id ASC'
                                );
                                $stmtLocs->execute([$produtoId]);
                                $locs = $stmtLocs->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                                foreach ($locs as $loc) {
                                    if ($restante <= 0) break;
                                    $locId = (int) ($loc['id'] ?? 0);
                                    $qAtual = (int) ($loc['quantidade'] ?? 0);
                                    if ($locId <= 0 || $qAtual <= 0) continue;
                                    $consumir = ($qAtual <= $restante) ? $qAtual : $restante;
                                    $novoQ = $qAtual - $consumir;
                                    $stmtUpd = $pdo->prepare('UPDATE estoque_interno SET quantidade = ? WHERE id = ? LIMIT 1');
                                    $stmtUpd->execute([$novoQ, $locId]);
                                    $restante -= $consumir;
                                }
                            } catch (\Exception $e) {
                            }
                        }

                        if ($faltante <= 0) {
                            continue;
                        }

                        // inserir pendancia na lista_compras com o que faltar
                        try {
                            $cols = ['produto_id', 'pedido_id'];
                            $vals = [':produto_id', ':pedido_id'];
                            $params = [':produto_id' => $produtoId, ':pedido_id' => (int) $id];

                            if (in_array('quantidade_faltante', $colsLista, true)) {
                                $cols[] = 'quantidade_faltante';
                                $vals[] = ':q';
                                $params[':q'] = $faltante;
                            } elseif (in_array('quantidade_necessaria', $colsLista, true)) {
                                $cols[] = 'quantidade_necessaria';
                                $vals[] = ':q';
                                $params[':q'] = $faltante;
                            }

                            if (in_array('status', $colsLista, true)) {
                                $cols[] = 'status';
                                $vals[] = "'pendente'";
                            }

                            $sql = 'INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
                            $st = $pdo->prepare($sql);
                            $st->execute($params);
                        } catch (\Exception $e) {
                        }
                    }
                }

                if (!empty($temReservas)) {
                    try {
                        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'estoque_reservas' AND column_name = 'pedido_id'");
                        $stmtC->execute();
                        $temPedidoId = ((int) $stmtC->fetchColumn() > 0);
                    } catch (\Exception $e) {
                        $temPedidoId = false;
                    }

                    if (!empty($temPedidoId)) {
                        try {
                            $stmtDel = $pdo->prepare('DELETE FROM estoque_reservas WHERE pedido_id = ?');
                            $stmtDel->execute([(int) $id]);
                        } catch (\Exception $e) {
                        }
                    }
                }
            }

            if ((string) $novoStatus === 'cancelado') {
                // Cancelamento: liberar reservas e remover pendancias do pedido
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['estoque_reservas']);
                    $temReservas = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temReservas = false;
                }
                if (!empty($temReservas)) {
                    try {
                        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'estoque_reservas' AND column_name = 'pedido_id'");
                        $stmtC->execute();
                        $temPedidoId = ((int) $stmtC->fetchColumn() > 0);
                    } catch (\Exception $e) {
                        $temPedidoId = false;
                    }
                    if (!empty($temPedidoId)) {
                        try {
                            $stmtDel = $pdo->prepare('DELETE FROM estoque_reservas WHERE pedido_id = ?');
                            $stmtDel->execute([(int) $id]);
                        } catch (\Exception $e) {
                        }
                    }
                }

                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['lista_compras']);
                    $temLista = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temLista = false;
                }
                if (!empty($temLista)) {
                    try {
                        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'lista_compras' AND column_name = 'pedido_id'");
                        $stmtC->execute();
                        $temPedidoIdLista = ((int) $stmtC->fetchColumn() > 0);
                    } catch (\Exception $e) {
                        $temPedidoIdLista = false;
                    }
                    if (!empty($temPedidoIdLista)) {
                        try {
                            $stmtDel = $pdo->prepare('DELETE FROM lista_compras WHERE pedido_id = ?');
                            $stmtDel->execute([(int) $id]);
                        } catch (\Exception $e) {
                        }
                    }
                }
            }
            
            header('Location: /admin/pedidos/detalhes/' . $id . '?success=1');
            exit;
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro ao atualizar status: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos/detalhes/' . $id . '" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }

    public function excluir(Request $request, $id = null) {
        $id = $id ?? $request->getParam('id');

        if (empty($id)) {
            echo '<div class="alert alert-danger">Pedido inválido</div>';
            echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
            exit;
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ?");
            $stmt->execute([$id]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }

            $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE pedido_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();
            header('Location: /admin/pedidos?success=excluido');
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo '<div class="alert alert-danger">Erro ao excluir pedido: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos/detalhes/' . htmlspecialchars((string)$id) . '" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }
}
