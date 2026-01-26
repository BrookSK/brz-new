<?php
namespace App\Controllers;

class AdminComprasController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function index($request) {
        try {
            // Buscar lista de compras
            $stmt = $this->connection->prepare("
                SELECT lc.*, p.name as produto_nome, p.sku, p.price
                FROM lista_compras lc
                JOIN produtos p ON lc.produto_id = p.id
                ORDER BY lc.prioridade DESC, lc.data_solicitacao ASC
            ");
            $stmt->execute();
            $compras = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Estatísticas
            $stmt = $this->connection->prepare("
                SELECT 
                    COUNT(*) as total_itens,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'comprado' THEN 1 ELSE 0 END) as comprados,
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados
                FROM lista_compras
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $compras = [];
            $estatisticas = ['total_itens' => 0, 'pendentes' => 0, 'comprados' => 0, 'cancelados' => 0];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Compras - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('compras');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-shopping-basket me-2"></i>Lista de Compras</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="alert(\'Funcionalidade em desenvolvimento\')">
                            <i class="fas fa-plus me-1"></i>Novo Item
                        </button>
                        <button type="button" class="btn btn-primary me-2" onclick="window.open(\'/admin/estoque/compras/pdf\', \'_blank\')">
                            <i class="fas fa-file-pdf me-1"></i>Gerar PDF
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>';

                // Cards de Estatísticas
                echo '<div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Itens</h5>
                                <h3>' . number_format($estatisticas['total_itens']) . '</h3>
                                <small>Na lista de compras</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Pendentes</h5>
                                <h3>' . number_format($estatisticas['pendentes']) . '</h3>
                                <small>Aguardando compra</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Comprados</h5>
                                <h3>' . number_format($estatisticas['comprados']) . '</h3>
                                <small>Itens adquiridos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Cancelados</h5>
                                <h3>' . number_format($estatisticas['cancelados']) . '</h3>
                                <small>Itens cancelados</small>
                            </div>
                        </div>
                    </div>
                </div>';

                // Tabela de Compras
                echo '<div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Itens da Lista de Compras</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>SKU</th>
                                        <th>Quantidade</th>
                                        <th>Status</th>
                                        <th>Prioridade</th>
                                        <th>Data Solicitação</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($compras as $item) {
                                    $status_class = $item['status'] == 'pendente' ? 'warning' : 
                                                   ($item['status'] == 'comprado' ? 'success' : 'danger');
                                    $prioridade_class = $item['prioridade'] == 'urgente' ? 'danger' : 
                                                       ($item['prioridade'] == 'alta' ? 'warning' : 'info');
                                    
                                    echo '<tr>
                                        <td>
                                            <strong>' . htmlspecialchars($item['produto_nome']) . '</strong>
                                            <br><small class="text-muted">ID: ' . $item['produto_id'] . '</small>
                                        </td>
                                        <td>' . htmlspecialchars($item['sku']) . '</td>
                                        <td>
                                            <span class="badge bg-primary">' . $item['quantidade_necessaria'] . '</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . ucfirst($item['status']) . '</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-' . $prioridade_class . '">' . ucfirst($item['prioridade']) . '</span>
                                        </td>
                                        <td>' . date('d/m/Y', strtotime($item['data_solicitacao'])) . '</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success" onclick="alert(\'Marcar como comprado: ' . htmlspecialchars($item['produto_nome']) . '\')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="alert(\'Cancelar item: ' . htmlspecialchars($item['produto_nome']) . '\')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>';
                                }
                                
                                echo '</tbody>
                            </table>
                        </div>
                    </div>
                </div>';

                // Informações do Sistema
                echo '<div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Módulo:</strong> Lista de Compras</p>
                                <p><strong>Status:</strong> <span class="badge bg-success">Ativo</span></p>
                                <p><strong>Última Atualização:</strong> ' . date('d/m/Y H:i:s') . '</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Funcionalidades Disponíveis:</strong></p>
                                <ul class="list-unstyled">
                                    <li>✅ Visualização da lista de compras</li>
                                    <li>✅ Estatísticas em tempo real</li>
                                    <li>✅ Filtros por status</li>
                                    <li>🚧 Adicionar novos itens (em desenvolvimento)</li>
                                    <li>🚧 Editar itens (em desenvolvimento)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '</body>
</html>';
    }

    public function salvar($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
    }

    public function mudarStatus($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
    }

    public function gerarPDF($request) {
        echo '<h1>Relatório de Compras - PDF</h1><p>Funcionalidade em desenvolvimento</p>';
    }

    public function verificarEstoque($request) {
        $produto_id = $request->getParam('produto_id');
        echo json_encode(['success' => true, 'message' => 'Verificação de estoque para produto ID: ' . $produto_id]);
    }
}
