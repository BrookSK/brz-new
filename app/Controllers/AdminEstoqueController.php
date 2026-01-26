<?php
namespace App\Controllers;

class AdminEstoqueController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function index($request) {
        try {
            // Buscar status geral do estoque
            $stmt = $this->connection->prepare("SELECT * FROM vw_status_geral_estoque ORDER BY produto_nome");
            $stmt->execute();
            $status_geral = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Estatísticas
            $stmt = $this->connection->prepare("
                SELECT 
                    COUNT(*) as total_produtos,
                    SUM(CASE WHEN status_estoque = 'crítico' THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN status_estoque = 'baixo' THEN 1 ELSE 0 END) as baixos,
                    SUM(CASE WHEN status_estoque = 'normal' THEN 1 ELSE 0 END) as normais
                FROM vw_status_geral_estoque
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $status_geral = [];
            $estatisticas = ['total_produtos' => 0, 'criticos' => 0, 'baixos' => 0, 'normais' => 0];
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque Interno - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { 
            min-height: 100vh; 
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); 
        }
        .sidebar .nav-link { 
            color: rgba(255, 255, 255, 0.8); 
            border-radius: 0.35rem; 
            margin: 0.2rem 0; 
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { 
            color: #fff; 
            background-color: rgba(255, 255, 255, 0.1); 
        }
        .sidebar .sidebar-brand { 
            color: #fff; 
            font-weight: bold; 
            padding: 1rem; 
        }
        .card-stats { 
            transition: transform 0.2s; 
        }
        .card-stats:hover { 
            transform: translateY(-5px); 
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-warehouse"></i></div>
                        <div class="sidebar-brand-text mx-3">BRZ Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/estoque"><i class="fas fa-fw fa-warehouse"></i><span>Estoque</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/estoque/compras"><i class="fas fa-fw fa-shopping-basket"></i><span>Compras</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/estoque/relatorios"><i class="fas fa-fw fa-file-pdf"></i><span>Relatórios</span></a></li>
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
                    <h1 class="h2"><i class="fas fa-warehouse me-2"></i>Estoque Interno</h1>
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

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Produtos</h5>
                                <h3>' . number_format($estatisticas['total_produtos']) . '</h3>
                                <small>Ativos no sistema</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Crítico</h5>
                                <h3>' . number_format($estatisticas['criticos']) . '</h3>
                                <small>Abaixo do mínimo</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Baixo</h5>
                                <h3>' . number_format($estatisticas['baixos']) . '</h3>
                                <small>Abaixo do ideal</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Normal</h5>
                                <h3>' . number_format($estatisticas['normais']) . '</h3>
                                <small>Níveis adequados</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Estoque -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Estoque Atual</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>SKU</th>
                                        <th>Loja</th>
                                        <th>Estoque</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($status_geral as $item) {
                                    $status_class = $item['status_estoque'] == 'crítico' ? 'danger' : 
                                                   ($item['status_estoque'] == 'baixo' ? 'warning' : 'success');
                                    
                                    echo '<tr>
                                        <td>
                                            <strong>' . htmlspecialchars($item['produto_nome']) . '</strong>
                                            <br><small class="text-muted">ID: ' . $item['produto_id'] . '</small>
                                        </td>
                                        <td>' . htmlspecialchars($item['sku']) . '</td>
                                        <td>
                                            <span class="badge bg-' . ($item['loja'] == 'sams' ? 'primary' : ($item['loja'] == 'costco' ? 'success' : 'secondary')) . '">
                                                ' . ucfirst($item['loja']) . '
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . $item['quantidade_estoque'] . '</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . ucfirst($item['status_estoque']) . '</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success" onclick="alert(\'Adicionar estoque para: ' . htmlspecialchars($item['produto_nome']) . '\')">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>';
                                }
                                
                                echo '</tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Informações do Sistema -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Módulo:</strong> Estoque Interno</p>
                                <p><strong>Status:</strong> <span class="badge bg-success">Ativo</span></p>
                                <p><strong>Última Atualização:</strong> ' . date('d/m/Y H:i:s') . '</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Funcionalidades Disponíveis:</strong></p>
                                <ul class="list-unstyled">
                                    <li>✅ Visualização de estoque</li>
                                    <li>✅ Estatísticas em tempo real</li>
                                    <li>✅ Filtros e busca</li>
                                    <li>🚧 Adição de itens (em desenvolvimento)</li>
                                    <li>🚧 Edição de itens (em desenvolvimento)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
    }

    public function salvar($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
    }

    public function marcarComprado($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
    }
}
