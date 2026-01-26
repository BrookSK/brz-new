<?php
namespace App\Controllers;

class AdminRemessaInternacionalController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function index($request) {
        try {
            // Buscar janelas de remessa (intervalos de 13 dias)
            $janelas = $this->getJanelasRemessa();
            
            // Buscar pedidos pendentes
            $pedidosPendentes = $this->getPedidosPendentes();
            
            // Buscar pedidos em atraso (15+ dias)
            $pedidosAtraso = $this->getPedidosAtraso();
            
            // Buscar remessas geradas
            $remessasGeradas = $this->getRemessasGeradas();

        } catch (\Exception $e) {
            $janelas = [];
            $pedidosPendentes = [];
            $pedidosAtraso = [];
            $remessasGeradas = [];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remessa Internacional - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .status-pendente { background-color: #ffc107; }
        .status-atraso { background-color: #dc3545; }
        .status-gerada { background-color: #17a2b8; }
        .status-enviado { background-color: #28a745; }
        .janela-card { 
            transition: transform 0.2s; 
            border-left: 4px solid #007bff;
        }
        .janela-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .pedido-card { 
            transition: all 0.3s; 
            border-left: 4px solid #dee2e6;
        }
        .pedido-card:hover { 
            transform: translateX(5px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('remessa-internacional');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-globe-americas me-2"></i>Remessa Internacional</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="criarNovaJanela()">
                            <i class="fas fa-plus me-1"></i>Nova Janela
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Pendentes</h5>
                                <h3>' . count($pedidosPendentes) . '</h3>
                                <small>Aguardando envio</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Atraso</h5>
                                <h3>' . count($pedidosAtraso) . '</h3>
                                <small>15+ dias</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Geradas</h5>
                                <h3>' . count($remessasGeradas) . '</h3>
                                <small>Remessas prontas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Janelas</h5>
                                <h3>' . count($janelas) . '</h3>
                                <small>Períodos ativos</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Janelas de Remessa -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Janelas de Remessa (13 dias)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">';
                                
                                foreach ($janelas as $janela) {
                                    $statusClass = $janela['status'] == 'aberta' ? 'success' : 'secondary';
                                    echo '<div class="col-md-4 mb-3">
                                        <div class="card janela-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Janela #' . $janela['id'] . '</h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($janela['data_fim'])) . '
                                                    </small><br>
                                                    <span class="badge bg-' . $statusClass . '">' . ucfirst($janela['status']) . '</span>
                                                </p>
                                                <button class="btn btn-sm btn-outline-primary" onclick="verJanela(' . $janela['id'] . ')">
                                                    <i class="fas fa-eye"></i> Ver Pedidos
                                                </button>
                                            </div>
                                        </div>
                                    </div>';
                                }
                                
                                if (empty($janelas)) {
                                    echo '<div class="col-12 text-center py-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Nenhuma janela de remessa encontrada</p>
                                    </div>';
                                }
                                
                                echo '</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pedidos Pendentes -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pedidos Pendentes de Envio</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Data</th>
                                                <th>Dias</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($pedidosPendentes as $pedido) {
                                            $dias = $this->calcularDias($pedido['created_at']);
                                            $statusBadge = $dias >= 15 ? 'atraso' : 'pendente';
                                            echo '<tr>
                                                <td><strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>' . htmlspecialchars($pedido['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y', strtotime($pedido['created_at'])) . '</td>
                                                <td><span class="badge bg-' . ($dias >= 15 ? 'danger' : 'warning') . '">' . $dias . ' dias</span></td>
                                                <td>R$ ' . number_format($pedido['total'], 2, ',', '.') . '</td>
                                                <td><span class="badge bg-' . $statusBadge . '">' . ucfirst($statusBadge) . '</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="gerarRemessa(' . $pedido['id'] . ')">
                                                        <i class="fas fa-globe"></i> Gerar Remessa
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(' . $pedido['id'] . ')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($pedidosPendentes)) {
                                            echo '<tr><td colspan="7" class="text-center text-muted">Nenhum pedido pendente encontrado</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pedidos em Atraso -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Pedidos em Atraso (15+ dias)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Data</th>
                                                <th>Dias</th>
                                                <th>Total</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($pedidosAtraso as $pedido) {
                                            $dias = $this->calcularDias($pedido['created_at']);
                                            echo '<tr class="table-danger">
                                                <td><strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>' . htmlspecialchars($pedido['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y', strtotime($pedido['created_at'])) . '</td>
                                                <td><span class="badge bg-danger">' . $dias . ' dias</span></td>
                                                <td>R$ ' . number_format($pedido['total'], 2, ',', '.') . '</td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="gerarRemessaUrgente(' . $pedido['id'] . ')">
                                                        <i class="fas fa-exclamation"></i> Gerar Urgente
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(' . $pedido['id'] . ')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($pedidosAtraso)) {
                                            echo '<tr><td colspan="6" class="text-center text-muted">Nenhum pedido em atraso encontrado</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Remessas Geradas -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Remessas Geradas</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Remessa</th>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Data Geração</th>
                                                <th>Status</th>
                                                <th>Webhook</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($remessasGeradas as $remessa) {
                                            $webhookStatus = $remessa['webhook_enviado'] ? 'success' : 'warning';
                                            echo '<tr>
                                                <td><strong>#' . str_pad($remessa['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>#' . str_pad($remessa['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($remessa['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($remessa['created_at'])) . '</td>
                                                <td><span class="badge bg-info">Remessa Gerada</span></td>
                                                <td><span class="badge bg-' . $webhookStatus . '">' . ($remessa['webhook_enviado'] ? 'Enviado' : 'Pendente') . '</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="reenviarWebhook(' . $remessa['id'] . ')">
                                                        <i class="fas fa-redo"></i> Reenviar
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesRemessa(' . $remessa['id'] . ')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($remessasGeradas)) {
                                            echo '<tr><td colspan="7" class="text-center text-muted">Nenhuma remessa gerada encontrada</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
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
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function gerarRemessa(pedidoId) {
            if (confirm("Deseja gerar a remessa internacional para este pedido?")) {
                fetch("/admin/remessa-internacional/gerar/" + pedidoId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Remessa gerada com sucesso!");
                        location.reload();
                    } else {
                        alert("Erro ao gerar remessa: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao gerar remessa");
                });
            }
        }

        function gerarRemessaUrgente(pedidoId) {
            if (confirm("ESTE PEDIDO ESTÁ EM ATRASO! Deseja gerar a remessa internacional urgentemente?")) {
                gerarRemessa(pedidoId);
            }
        }

        function verDetalhes(pedidoId) {
            window.open("/admin/pedidos/detalhes/" + pedidoId, "_blank");
        }

        function reenviarWebhook(remessaId) {
            if (confirm("Deseja reenviar o webhook para esta remessa?")) {
                fetch("/admin/remessa-internacional/reenviar-webhook/" + remessaId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Webhook reenviado com sucesso!");
                        location.reload();
                    } else {
                        alert("Erro ao reenviar webhook: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao reenviar webhook");
                });
            }
        }

        function criarNovaJanela() {
            alert("Funcionalidade em desenvolvimento");
        }

        function verJanela(janelaId) {
            alert("Funcionalidade em desenvolvimento");
        }

        function verDetalhesRemessa(remessaId) {
            alert("Funcionalidade em desenvolvimento");
        }
    </script>
</body>
</html>';
        exit;
    }

    private function getJanelasRemessa() {
        $stmt = $this->connection->prepare("
            SELECT * FROM remessa_janelas 
            WHERE status = 'aberta' 
            ORDER BY data_inicio DESC 
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPedidosPendentes() {
        $stmt = $this->connection->prepare("
            SELECT p.*, u.nome as cliente_nome 
            FROM pedidos p 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.status = 'pendente_envio' 
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)
            ORDER BY p.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPedidosAtraso() {
        $stmt = $this->connection->prepare("
            SELECT p.*, u.nome as cliente_nome 
            FROM pedidos p 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.status = 'pendente_envio' 
            AND p.created_at < DATE_SUB(NOW(), INTERVAL 15 DAY)
            ORDER BY p.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getRemessasGeradas() {
        $stmt = $this->connection->prepare("
            SELECT r.*, p.usuario_id, u.nome as cliente_nome 
            FROM remessas_internacionais r 
            LEFT JOIN pedidos p ON r.pedido_id = p.id 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE r.status = 'remessa_gerada' 
            ORDER BY r.created_at DESC 
            LIMIT 50
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function calcularDias($data) {
        $dataCriacao = new \DateTime($data);
        $dataAtual = new \DateTime();
        return $dataAtual->diff($dataCriacao)->days;
    }

    public function gerarRemessa($request) {
        $pedidoId = $request->getParam('id');
        
        try {
            $this->connection->beginTransaction();
            
            // Buscar dados completos do pedido
            $pedido = $this->getPedidoCompleto($pedidoId);
            
            if (!$pedido) {
                echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
                exit;
            }
            
            // Criar remessa internacional
            $stmt = $this->connection->prepare("
                INSERT INTO remessas_internacionais 
                (pedido_id, dados_pedido, dados_cliente, status, webhook_enviado, created_at) 
                VALUES (?, ?, ?, 'remessa_gerada', 0, NOW())
            ");
            $stmt->execute([
                $pedidoId,
                json_encode($pedido),
                json_encode($pedido['cliente']),
                'remessa_gerada'
            ]);
            
            $remessaId = $this->connection->lastInsertId();
            
            // Atualizar status do pedido
            $stmt = $this->connection->prepare("
                UPDATE pedidos SET status = 'remessa_gerada', updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$pedidoId]);
            
            // Enviar webhook
            $webhookEnviado = $this->enviarWebhook($remessaId, $pedido);
            
            if ($webhookEnviado) {
                $stmt = $this->connection->prepare("
                    UPDATE remessas_internacionais SET webhook_enviado = 1 WHERE id = ?
                ");
                $stmt->execute([$remessaId]);
            }
            
            $this->connection->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Remessa gerada com sucesso!',
                'remessa_id' => $remessaId,
                'webhook_enviado' => $webhookEnviado
            ]);
            
        } catch (\Exception $e) {
            $this->connection->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    private function getPedidoCompleto($pedidoId) {
        // Buscar pedido principal
        $stmt = $this->connection->prepare("
            SELECT p.*, u.nome as cliente_nome, u.email as cliente_email, u.telefone as cliente_telefone 
            FROM pedidos p 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$pedido) return null;
        
        // Buscar itens do pedido
        $stmt = $this->connection->prepare("
            SELECT pi.*, pr.nome as produto_nome, pr.sku 
            FROM pedido_itens pi 
            LEFT JOIN produtos pr ON pi.produto_id = pr.id 
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedidoId]);
        $pedido['itens'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar endereço
        $stmt = $this->connection->prepare("
            SELECT * FROM enderecos 
            WHERE usuario_id = ? AND tipo = 'entrega' 
            LIMIT 1
        ");
        $stmt->execute([$pedido['usuario_id']]);
        $pedido['endereco'] = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $pedido;
    }

    private function enviarWebhook($remessaId, $pedido) {
        $webhookUrl = "https://api.logistica-internacional.com/webhook/remessa"; // URL do webhook
        
        $payload = [
            'remessa_id' => $remessaId,
            'pedido_id' => $pedido['id'],
            'codigo_pedido' => $pedido['codigo_pedido'],
            'cliente' => [
                'nome' => $pedido['cliente_nome'],
                'email' => $pedido['cliente_email'],
                'telefone' => $pedido['cliente_telefone']
            ],
            'endereco' => $pedido['endereco'],
            'itens' => $pedido['itens'],
            'total' => $pedido['total'],
            'data_pedido' => $pedido['created_at']
        ];
        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer TOKEN_API' // Token da API
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }

    public function reenviarWebhook($request) {
        $remessaId = $request->getParam('id');
        
        try {
            $stmt = $this->connection->prepare("
                SELECT * FROM remessas_internacionais WHERE id = ?
            ");
            $stmt->execute([$remessaId]);
            $remessa = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$remessa) {
                echo json_encode(['success' => false, 'message' => 'Remessa não encontrada']);
                exit;
            }
            
            $pedido = json_decode($remessa['dados_pedido'], true);
            $webhookEnviado = $this->enviarWebhook($remessaId, $pedido);
            
            if ($webhookEnviado) {
                $stmt = $this->connection->prepare("
                    UPDATE remessas_internacionais SET webhook_enviado = 1 WHERE id = ?
                ");
                $stmt->execute([$remessaId]);
                
                echo json_encode(['success' => true, 'message' => 'Webhook reenviado com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Falha ao enviar webhook']);
            }
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }
}
