<?php
namespace App\Controllers;

class AdminRemessaCorreiosController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function index($request) {
        try {
            // Buscar remessas prontas para etiqueta (status = remessa_gerada)
            $remessasProntas = $this->getRemessasProntas();
            
            // Buscar etiquetas geradas
            $etiquetasGeradas = $this->getEtiquetasGeradas();
            
            // Buscar etiquetas impressas
            $etiquetasImpressas = $this->getEtiquetasImpressas();

        } catch (\Exception $e) {
            $remessasProntas = [];
            $etiquetasGeradas = [];
            $etiquetasImpressas = [];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remessa Correios - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .bg-purple { background-color: #6f42c1 !important; }
        .badge.bg-purple { background-color: #6f42c1 !important; color: #fff !important; }
        .status-pronta { background-color: #17a2b8; }
        .status-etiqueta { background-color: #6f42c1; }
        .status-impressa { background-color: #28a745; }
        .status-postada { background-color: #007bff; }
        .remessa-card { 
            transition: transform 0.2s; 
            border-left: 4px solid #17a2b8;
        }
        .remessa-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .etiqueta-card { 
            transition: all 0.3s; 
            border-left: 4px solid #6f42c1;
        }
        .etiqueta-card:hover { 
            transform: translateX(5px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .codigo-etiqueta {
            font-family: "Courier New", monospace;
            font-size: 14px;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('remessa-correios');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-shipping-fast me-2"></i>Remessa Correios</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="gerarLoteEtiquetas()">
                            <i class="fas fa-tags me-1"></i>Gerar Lote
                        </button>
                        <button type="button" class="btn btn-warning me-2" onclick="imprimirTodasEtiquetas()">
                            <i class="fas fa-print me-1"></i>Imprimir Todas
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Prontas</h5>
                                <h3>' . count($remessasProntas) . '</h3>
                                <small>Aguardando etiqueta</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-purple text-white">
                            <div class="card-body">
                                <h5 class="card-title">Etiquetas</h5>
                                <h3>' . count($etiquetasGeradas) . '</h3>
                                <small>Geradas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Impressas</h5>
                                <h3>' . count($etiquetasImpressas) . '</h3>
                                <small>Prontas para postagem</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Postadas</h5>
                                <h3>' . $this->getTotalPostadas() . '</h3>
                                <small>Enviadas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Remessas Prontas para Etiqueta -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Remessas Prontas para Etiqueta</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll" onchange="toggleAll()"></th>
                                                <th>Remessa</th>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Data Remessa</th>
                                                <th>Peso</th>
                                                <th>Valor</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($remessasProntas as $remessa) {
                                            echo '<tr class="remessa-card">
                                                <td><input type="checkbox" class="remessa-checkbox" value="' . $remessa['id'] . '"></td>
                                                <td><strong>#' . str_pad($remessa['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>#' . str_pad($remessa['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($remessa['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($remessa['created_at'])) . '</td>
                                                <td>' . number_format($remessa['peso_total'] ?? 1.0, 3, ',', '.') . ' kg</td>
                                                <td>R$ ' . number_format($remessa['valor_total'] ?? 0, 2, ',', '.') . '</td>
                                                <td>
                                                    <button class="btn btn-sm btn-purple" onclick="gerarEtiqueta(' . $remessa['id'] . ')">
                                                        <i class="fas fa-tags"></i> Gerar Etiqueta
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesRemessa(' . $remessa['id'] . ')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($remessasProntas)) {
                                            echo '<tr><td colspan="8" class="text-center text-muted">Nenhuma remessa pronta para etiqueta encontrada</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Etiquetas Geradas -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-purple text-white">
                                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Etiquetas Geradas</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Etiqueta</th>
                                                <th>Remessa</th>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Código</th>
                                                <th>Data Geração</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($etiquetasGeradas as $etiqueta) {
                                            echo '<tr class="etiqueta-card">
                                                <td><strong>#' . str_pad($etiqueta['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>#' . str_pad($etiqueta['remessa_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>#' . str_pad($etiqueta['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($etiqueta['cliente_nome'] ?? 'N/A') . '</td>
                                                <td><div class="codigo-etiqueta">' . htmlspecialchars($etiqueta['codigo_etiqueta']) . '</div></td>
                                                <td>' . date('d/m/Y H:i', strtotime($etiqueta['created_at'])) . '</td>
                                                <td><span class="badge bg-purple">Etiqueta Gerada</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="imprimirEtiqueta(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-print"></i> Imprimir
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" onclick="rastrearEtiqueta(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($etiquetasGeradas)) {
                                            echo '<tr><td colspan="8" class="text-center text-muted">Nenhuma etiqueta gerada encontrada</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Etiquetas Impressas -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Etiquetas Impressas</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Etiqueta</th>
                                                <th>Remessa</th>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Código</th>
                                                <th>Data Impressão</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($etiquetasImpressas as $etiqueta) {
                                            echo '<tr class="table-success">
                                                <td><strong>#' . str_pad($etiqueta['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>#' . str_pad($etiqueta['remessa_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>#' . str_pad($etiqueta['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($etiqueta['cliente_nome'] ?? 'N/A') . '</td>
                                                <td><div class="codigo-etiqueta">' . htmlspecialchars($etiqueta['codigo_etiqueta']) . '</div></td>
                                                <td>' . date('d/m/Y H:i', strtotime($etiqueta['data_impressao'])) . '</td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="confirmarPostagem(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-check"></i> Confirmar Postagem
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" onclick="rastrearEtiqueta(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($etiquetasImpressas)) {
                                            echo '<tr><td colspan="7" class="text-center text-muted">Nenhuma etiqueta impressa encontrada</td></tr>';
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
        function toggleAll() {
            const checkboxes = document.querySelectorAll(\'.remessa-checkbox\');
            const selectAll = document.getElementById(\'selectAll\');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function gerarEtiqueta(remessaId) {
            if (confirm("Deseja gerar a etiqueta dos Correios para esta remessa?")) {
                fetch("/admin/remessa-correios/gerar-etiqueta/" + remessaId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Etiqueta gerada com sucesso! Código: " + data.codigo_etiqueta);
                        location.reload();
                    } else {
                        alert("Erro ao gerar etiqueta: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao gerar etiqueta");
                });
            }
        }

        function gerarLoteEtiquetas() {
            const checkboxes = document.querySelectorAll(\'.remessa-checkbox:checked\');
            
            if (checkboxes.length === 0) {
                alert("Selecione pelo menos uma remessa para gerar etiquetas em lote");
                return;
            }
            
            if (confirm("Deseja gerar etiquetas para " + checkboxes.length + " remessas selecionadas?")) {
                const remessas = Array.from(checkboxes).map(cb => cb.value);
                
                fetch("/admin/remessa-correios/gerar-lote-etiquetas", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ remessas: remessas })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert("Erro ao gerar lote: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao gerar lote de etiquetas");
                });
            }
        }

        function imprimirEtiqueta(etiquetaId) {
            window.open("/admin/remessa-correios/imprimir-etiqueta/" + etiquetaId, "_blank");
        }

        function imprimirTodasEtiquetas() {
            window.open("/admin/remessa-correios/imprimir-todas-etiquetas", "_blank");
        }

        function rastrearEtiqueta(etiquetaId) {
            window.open("/admin/remessa-correios/rastrear/" + etiquetaId, "_blank");
        }

        function confirmarPostagem(etiquetaId) {
            if (confirm("Confirmar postagem desta etiqueta?")) {
                fetch("/admin/remessa-correios/confirmar-postagem/" + etiquetaId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Postagem confirmada com sucesso!");
                        location.reload();
                    } else {
                        alert("Erro ao confirmar postagem: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao confirmar postagem");
                });
            }
        }

        function verDetalhesRemessa(remessaId) {
            window.open("/admin/remessa-internacional/detalhes/" + remessaId, "_blank");
        }
    </script>
</body>
</html>';
        exit;
    }

    private function getRemessasProntas() {
        $stmt = $this->connection->prepare("
            SELECT r.*, p.usuario_id, u.nome as cliente_nome, p.total as valor_total
            FROM remessas_internacionais r 
            LEFT JOIN pedidos p ON r.pedido_id = p.id 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE r.status = 'remessa_gerada' 
            AND r.etiqueta_gerada = 0
            ORDER BY r.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getEtiquetasGeradas() {
        $stmt = $this->connection->prepare("
            SELECT e.*, r.remessa_id, r.pedido_id, u.nome as cliente_nome
            FROM etiquetas_correios e 
            LEFT JOIN remessas_internacionais r ON e.remessa_id = r.id 
            LEFT JOIN usuarios u ON r.usuario_id = u.id 
            WHERE e.status = 'gerada' 
            AND e.data_impressao IS NULL
            ORDER BY e.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getEtiquetasImpressas() {
        $stmt = $this->connection->prepare("
            SELECT e.*, r.remessa_id, r.pedido_id, u.nome as cliente_nome
            FROM etiquetas_correios e 
            LEFT JOIN remessas_internacionais r ON e.remessa_id = r.id 
            LEFT JOIN usuarios u ON r.usuario_id = u.id 
            WHERE e.status = 'impressa' 
            AND e.data_postagem IS NULL
            ORDER BY e.data_impressao DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getTotalPostadas() {
        $stmt = $this->connection->prepare("
            SELECT COUNT(*) as total FROM etiquetas_correios 
            WHERE status = 'postada'
        ");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public function gerarEtiqueta($request) {
        $remessaId = $request->getParam('id');
        
        try {
            $this->connection->beginTransaction();
            
            // Buscar dados da remessa
            $stmt = $this->connection->prepare("
                SELECT r.*, p.codigo_pedido, p.usuario_id, u.nome as cliente_nome, u.email as cliente_email
                FROM remessas_internacionais r 
                LEFT JOIN pedidos p ON r.pedido_id = p.id 
                LEFT JOIN usuarios u ON p.usuario_id = u.id 
                WHERE r.id = ?
            ");
            $stmt->execute([$remessaId]);
            $remessa = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$remessa) {
                echo json_encode(['success' => false, 'message' => 'Remessa não encontrada']);
                exit;
            }
            
            // Gerar código da etiqueta (simulação)
            $codigoEtiqueta = $this->gerarCodigoEtiqueta();
            
            // Criar etiqueta
            $stmt = $this->connection->prepare("
                INSERT INTO etiquetas_correios 
                (remessa_id, pedido_id, codigo_etiqueta, dados_remetente, dados_destinatario, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'gerada', NOW())
            ");
            $stmt->execute([
                $remessaId,
                $remessa['pedido_id'],
                $codigoEtiqueta,
                json_encode($this->getDadosRemetente()),
                json_encode($this->getDadosDestinatario($remessa))
            ]);
            
            $etiquetaId = $this->connection->lastInsertId();
            
            // Atualizar remessa
            $stmt = $this->connection->prepare("
                UPDATE remessas_internacionais SET etiqueta_gerada = 1 WHERE id = ?
            ");
            $stmt->execute([$remessaId]);
            
            $this->connection->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Etiqueta gerada com sucesso!',
                'etiqueta_id' => $etiquetaId,
                'codigo_etiqueta' => $codigoEtiqueta
            ]);
            
        } catch (\Exception $e) {
            $this->connection->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    private function gerarCodigoEtiqueta() {
        // Simular geração de código de etiqueta dos Correios
        return 'BR' . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 10));
    }

    private function getDadosRemetente() {
        return [
            'nome' => 'Braziliana Shop',
            'cnpj' => '00.000.000/0001-00',
            'endereco' => 'Rua das Empresas, 123',
            'cidade' => 'São Paulo',
            'estado' => 'SP',
            'cep' => '01234-567',
            'telefone' => '(11) 1234-5678'
        ];
    }

    private function getDadosDestinatario($remessa) {
        $dadosPedido = json_decode($remessa['dados_pedido'], true);
        
        return [
            'nome' => $remessa['cliente_nome'],
            'email' => $remessa['cliente_email'],
            'endereco' => $dadosPedido['endereco']['endereco'] ?? 'Endereço não disponível',
            'cidade' => $dadosPedido['endereco']['cidade'] ?? 'Cidade',
            'estado' => $dadosPedido['endereco']['estado'] ?? 'SP',
            'cep' => $dadosPedido['endereco']['cep'] ?? '00000-000',
            'telefone' => $dadosPedido['endereco']['telefone'] ?? ''
        ];
    }

    public function confirmarPostagem($request) {
        $etiquetaId = $request->getParam('id');
        
        try {
            $stmt = $this->connection->prepare("
                UPDATE etiquetas_correios 
                SET status = 'postada', data_postagem = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$etiquetaId]);
            
            echo json_encode(['success' => true, 'message' => 'Postagem confirmada com sucesso!']);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    public function imprimirEtiqueta($request) {
        $etiquetaId = $request->getParam('id');
        
        try {
            $stmt = $this->connection->prepare("
                SELECT * FROM etiquetas_correios WHERE id = ?
            ");
            $stmt->execute([$etiquetaId]);
            $etiqueta = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$etiqueta) {
                echo '<div class="alert alert-danger">Etiqueta não encontrada</div>';
                exit;
            }
            
            // Marcar como impressa
            $stmt = $this->connection->prepare("
                UPDATE etiquetas_correios 
                SET status = 'impressa', data_impressao = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$etiquetaId]);
            
            // Gerar HTML da etiqueta para impressão
            $dadosRemetente = json_decode($etiqueta['dados_remetente'], true);
            $dadosDestinatario = json_decode($etiqueta['dados_destinatario'], true);
            
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Etiqueta Correios #' . $etiqueta['codigo_etiqueta'] . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .etiqueta { 
            border: 2px solid #000; 
            padding: 20px; 
            width: 400px; 
            margin: 0 auto;
        }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .codigo { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 20px; }
        .section { margin-bottom: 15px; }
        .label { font-weight: bold; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <div class="etiqueta">
        <div class="header">
            <h3>CORREIOS - ETIQUETA DE POSTAGEM</h3>
        </div>
        <div class="codigo">
            CÓDIGO: ' . $etiqueta['codigo_etiqueta'] . '
        </div>
        <div class="section">
            <div class="label">REMETENTE:</div>
            <div>' . htmlspecialchars($dadosRemetente['nome']) . '</div>
            <div>' . htmlspecialchars($dadosRemetente['endereco']) . '</div>
            <div>' . htmlspecialchars($dadosRemetente['cidade'] . '/' . $dadosRemetente['estado']) . ' - CEP: ' . htmlspecialchars($dadosRemetente['cep']) . '</div>
        </div>
        <div class="section">
            <div class="label">DESTINATÁRIO:</div>
            <div>' . htmlspecialchars($dadosDestinatario['nome']) . '</div>
            <div>' . htmlspecialchars($dadosDestinatario['endereco']) . '</div>
            <div>' . htmlspecialchars($dadosDestinatario['cidade'] . '/' . $dadosDestinatario['estado']) . ' - CEP: ' . htmlspecialchars($dadosDestinatario['cep']) . '</div>
        </div>
        <div class="section">
            <div class="label">DATA POSTAGEM:</div>
            <div>' . date('d/m/Y H:i') . '</div>
        </div>
    </div>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>';
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
        }
        exit;
    }
}
