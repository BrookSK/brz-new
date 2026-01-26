<?php
namespace App\Controllers;

class AdminComprasController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function index($request) {
        try {
            // Buscar lista de compras completa
            $stmt = $this->connection->prepare("
                SELECT 
                    lc.*,
                    p.name as produto_nome,
                    p.sku,
                    p.price,
                    p.loja,
                    COALESCE(SUM(e.quantidade), 0) as quantidade_estoque_atual,
                    ec.estoque_minimo,
                    ec.estoque_ideal
                FROM lista_compras lc
                JOIN produtos p ON lc.produto_id = p.id
                LEFT JOIN estoque_interno e ON lc.produto_id = e.produto_id
                LEFT JOIN estoque_configuracoes ec ON lc.produto_id = ec.produto_id
                ORDER BY 
                    CASE lc.prioridade 
                        WHEN 'urgente' THEN 1 
                        WHEN 'alta' THEN 2 
                        WHEN 'media' THEN 3 
                        WHEN 'baixa' THEN 4 
                    END,
                    lc.data_solicitacao ASC
            ");
            $stmt->execute();
            $lista_compras = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Estatísticas das compras
            $stmt = $this->connection->prepare("
                SELECT 
                    COUNT(*) as total_itens,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'comprando' THEN 1 ELSE 0 END) as comprando,
                    SUM(CASE WHEN status = 'comprado' THEN 1 ELSE 0 END) as comprados,
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                    SUM(quantidade_faltante) as total_faltante,
                    SUM(quantidade_faltante * p.price) as valor_estimado
                FROM lista_compras lc
                JOIN produtos p ON lc.produto_id = p.id
                WHERE lc.status IN ('pendente', 'comprando')
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Agrupar por prioridade
            $stmt = $this->connection->prepare("
                SELECT 
                    prioridade,
                    COUNT(*) as quantidade,
                    SUM(quantidade_faltante) as total_faltante
                FROM lista_compras 
                WHERE status = 'pendente'
                GROUP BY prioridade
                ORDER BY 
                    CASE prioridade 
                        WHEN 'urgente' THEN 1 
                        WHEN 'alta' THEN 2 
                        WHEN 'media' THEN 3 
                        WHEN 'baixa' THEN 4 
                    END
            ");
            $stmt->execute();
            $por_prioridade = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Agrupar por loja
            $stmt = $this->connection->prepare("
                SELECT 
                    p.loja,
                    COUNT(*) as quantidade,
                    SUM(quantidade_faltante) as total_faltante,
                    SUM(quantidade_faltante * p.price) as valor_estimado
                FROM lista_compras lc
                JOIN produtos p ON lc.produto_id = p.id
                WHERE lc.status = 'pendente'
                GROUP BY p.loja
                ORDER BY total_faltante DESC
            ");
            $stmt->execute();
            $por_loja = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $lista_compras = [];
            $estatisticas = [];
            $por_prioridade = [];
            $por_loja = [];
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Compras - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
        .prioridade-urgente { background-color: #dc3545; color: white; }
        .prioridade-alta { background-color: #fd7e14; color: white; }
        .prioridade-media { background-color: #0dcaf0; color: white; }
        .prioridade-baixa { background-color: #6c757d; color: white; }
        .status-pendente { background-color: #ffc107; color: black; }
        .status-comprando { background-color: #0dcaf0; color: white; }
        .status-comprado { background-color: #198754; color: white; }
        .status-cancelado { background-color: #6c757d; color: white; }
        .card-stats { transition: transform 0.2s; }
        .card-stats:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-warehouse"></i></div>
                        <div class="sidebar-brand-text mx-3">BRZ Estoque</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/estoque"><i class="fas fa-fw fa-warehouse"></i><span>Estoque</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/estoque/compras"><i class="fas fa-fw fa-shopping-basket"></i><span>Compras</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/estoque/movimentacao"><i class="fas fa-fw fa-exchange-alt"></i><span>Movimentação</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/estoque/relatorios"><i class="fas fa-fw fa-file-pdf"></i><span>Relatórios</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>Usuários</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Sair</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-shopping-basket me-2"></i>Lista de Compras</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="abrirModalAdicionar()">
                            <i class="fas fa-plus me-1"></i>Adicionar Item
                        </button>
                        <button type="button" class="btn btn-primary me-2" onclick="gerarPDFCompras()">
                            <i class="fas fa-file-pdf me-1"></i>Gerar PDF
                        </button>
                        <button type="button" class="btn btn-info" onclick="atualizarDados()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
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
                        <div class="card card-stats bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Comprando</h5>
                                <h3>' . number_format($estatisticas['comprando']) . '</h3>
                                <small>Em processo</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Comprados</h5>
                                <h3>' . number_format($estatisticas['comprados']) . '</h3>
                                <small>Concluídos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Valor Estimado</h5>
                                <h3>R$ ' . number_format($estatisticas['valor_estimado'], 2, ',', '.') . '</h3>
                                <small>Total pendente</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Buscar Produto</label>
                                <input type="text" class="form-control" id="busca_produto" placeholder="Nome ou SKU...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="filtro_status">
                                    <option value="">Todos</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="comprando">Comprando</option>
                                    <option value="comprado">Comprado</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Prioridade</label>
                                <select class="form-select" id="filtro_prioridade">
                                    <option value="">Todas</option>
                                    <option value="urgente">Urgente</option>
                                    <option value="alta">Alta</option>
                                    <option value="media">Média</option>
                                    <option value="baixa">Baixa</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Loja</label>
                                <select class="form-select" id="filtro_loja">
                                    <option value="">Todas</option>
                                    <option value="sams">Sams</option>
                                    <option value="costco">Costco</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="button" class="btn btn-primary" onclick="aplicarFiltros()">
                                        <i class="fas fa-filter me-1"></i>Filtrar
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltros()">
                                        <i class="fas fa-times me-1"></i>Limpar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos por Prioridade e Loja -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Itens por Prioridade</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">';
                                
                                foreach ($por_prioridade as $item) {
                                    $prioridade_class = $item['prioridade'] == 'urgente' ? 'danger' : 
                                                       ($item['prioridade'] == 'alta' ? 'warning' : 
                                                       ($item['prioridade'] == 'media' ? 'info' : 'secondary'));
                                    echo '<div class="col-6 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-' . $prioridade_class . '">' . ucfirst($item['prioridade']) . '</span>
                                            <span>' . $item['quantidade'] . ' itens</span>
                                        </div>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar bg-' . $prioridade_class . '" style="width: ' . min(100, ($item['quantidade'] / max(1, $estatisticas['pendentes'])) * 100) . '%"></div>
                                        </div>
                                    </div>';
                                }
                                
                                echo '</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Itens por Loja</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">';
                                
                                foreach ($por_loja as $item) {
                                    $loja_class = $item['loja'] == 'sams' ? 'primary' : 
                                                 ($item['loja'] == 'costco' ? 'success' : 'secondary');
                                    echo '<div class="col-6 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-' . $loja_class . '">' . ucfirst($item['loja']) . '</span>
                                            <span>' . $item['quantidade'] . ' itens</span>
                                        </div>
                                        <small class="text-muted">R$ ' . number_format($item['valor_estimado'], 2, ',', '.') . '</small>
                                    </div>';
                                }
                                
                                echo '</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Lista de Compras -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Itens da Lista de Compras</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tabela_compras">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>SKU</th>
                                        <th>Loja</th>
                                        <th>Necessário</th>
                                        <th>Em Estoque</th>
                                        <th>Faltante</th>
                                        <th>Prioridade</th>
                                        <th>Status</th>
                                        <th>Data Solicitação</th>
                                        <th>Preço Unit.</th>
                                        <th>Total</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($lista_compras as $item) {
                                    $prioridade_class = $item['prioridade'] == 'urgente' ? 'danger' : 
                                                       ($item['prioridade'] == 'alta' ? 'warning' : 
                                                       ($item['prioridade'] == 'media' ? 'info' : 'secondary');
                                    $status_class = $item['status'] == 'pendente' ? 'warning' : 
                                                  ($item['status'] == 'comprando' ? 'info' : 
                                                  ($item['status'] == 'comprado' ? 'success' : 'secondary');
                                    $total_item = $item['quantidade_faltante'] * $item['price'];
                                    
                                    echo '<tr data-item-id="' . $item['id'] . '">
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
                                        <td>' . $item['quantidade_necessaria'] . '</td>
                                        <td>
                                            <span class="badge bg-' . ($item['quantidade_estoque_atual'] > 0 ? 'success' : 'danger') . '">
                                                ' . $item['quantidade_estoque_atual'] . '
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger">' . $item['quantidade_faltante'] . '</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-' . $prioridade_class . '">' . ucfirst($item['prioridade']) . '</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . ucfirst($item['status']) . '</span>
                                        </td>
                                        <td>' . date('d/m/Y', strtotime($item['data_solicitacao'])) . '</td>
                                        <td>R$ ' . number_format($item['price'], 2, ',', '.') . '</td>
                                        <td>
                                            <strong>R$ ' . number_format($total_item, 2, ',', '.') . '</strong>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success" onclick="mudarStatus(' . $item['id'] . ', \'comprado\')" title="Marcar como Comprado">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" onclick="mudarStatus(' . $item['id'] . ', \'comprando\')" title="Marcar como Comprando">
                                                    <i class="fas fa-shopping-cart"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" onclick="editarItem(' . $item['id'] . ')" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="mudarStatus(' . $item['id'] . ', \'cancelado\')" title="Cancelar">
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
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Adicionar/Editar Item -->
    <div class="modal fade" id="modalItemCompra">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Adicionar Item à Lista de Compras</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formItemCompra">
                        <input type="hidden" id="item_id" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Produto *</label>
                                    <select class="form-select" id="produto_id" name="produto_id" required>
                                        <option value="">Selecione...</option>';
                                        
                                        // Buscar produtos ativos
                                        $stmt = $this->connection->prepare("SELECT id, name, sku, loja, price FROM produtos WHERE active = 1 ORDER BY name");
                                        $stmt->execute();
                                        $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                                        
                                        foreach ($produtos as $produto) {
                                            echo '<option value="' . $produto['id'] . '" data-price="' . $produto['price'] . '">' . htmlspecialchars($produto['name']) . ' (' . htmlspecialchars($produto['sku']) . ') - ' . ucfirst($produto['loja']) . '</option>';
                                        }
                                        
                                        echo '</select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quantidade Necessária *</label>
                                    <input type="number" class="form-control" id="quantidade_necessaria" name="quantidade_necessaria" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prioridade *</label>
                                    <select class="form-select" id="prioridade" name="prioridade" required>
                                        <option value="baixa">Baixa</option>
                                        <option value="media" selected>Média</option>
                                        <option value="alta">Alta</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Prevista de Compra</label>
                                    <input type="date" class="form-control" id="data_prevista_compra" name="data_prevista_compra">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Observações</label>
                                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarItemCompra()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalAdicionar() {
            document.getElementById("modalTitle").textContent = "Adicionar Item à Lista de Compras";
            document.getElementById("formItemCompra").reset();
            document.getElementById("data_prevista_compra").value = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split("T")[0];
            new bootstrap.Modal(document.getElementById("modalItemCompra")).show();
        }

        function editarItem(itemId) {
            // Implementar edição de item
            alert("Funcionalidade de edição em desenvolvimento");
        }

        function salvarItemCompra() {
            const form = document.getElementById("formItemCompra");
            const formData = new FormData(form);
            
            fetch("/admin/estoque/compras/salvar", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Item adicionado à lista de compras com sucesso!");
                    bootstrap.Modal.getInstance(document.getElementById("modalItemCompra")).hide();
                    location.reload();
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(error => {
                console.error("Erro:", error);
                alert("Erro ao salvar item");
            });
        }

        function mudarStatus(itemId, novoStatus) {
            const confirmMessage = {
                'comprado': 'Tem certeza que deseja marcar este item como comprado?',
                'comprando': 'Tem certeza que deseja marcar este item como em compra?',
                'cancelado': 'Tem certeza que deseja cancelar este item?'
            };
            
            if (confirm(confirmMessage[novoStatus])) {
                fetch("/admin/estoque/compras/mudar-status", {
                    method: "POST",
                    headers: {"Content-Type": "application/json"},
                    body: JSON.stringify({item_id: itemId, status: novoStatus})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Status atualizado com sucesso!");
                        location.reload();
                    } else {
                        alert("Erro: " + data.message);
                    }
                });
            }
        }

        function aplicarFiltros() {
            const busca = document.getElementById("busca_produto").value.toLowerCase();
            const status = document.getElementById("filtro_status").value;
            const prioridade = document.getElementById("filtro_prioridade").value;
            const loja = document.getElementById("filtro_loja").value;
            
            const rows = document.querySelectorAll("#tabela_compras tbody tr");
            
            rows.forEach(row => {
                const produto = row.cells[0].textContent.toLowerCase();
                const statusRow = row.cells[7].textContent.toLowerCase();
                const prioridadeRow = row.cells[6].textContent.toLowerCase();
                const lojaRow = row.cells[2].textContent.toLowerCase();
                
                let show = true;
                
                if (busca && !produto.includes(busca)) show = false;
                if (status && !statusRow.includes(status)) show = false;
                if (prioridade && !prioridadeRow.includes(prioridade)) show = false;
                if (loja && !lojaRow.includes(loja)) show = false;
                
                row.style.display = show ? "" : "none";
            });
        }

        function limparFiltros() {
            document.getElementById("busca_produto").value = "";
            document.getElementById("filtro_status").value = "";
            document.getElementById("filtro_prioridade").value = "";
            document.getElementById("filtro_loja").value = "";
            aplicarFiltros();
        }

        function atualizarDados() {
            location.reload();
        }

        function gerarPDFCompras() {
            window.open("/admin/estoque/compras/pdf", "_blank");
        }

        // Atualizar quantidade em estoque ao selecionar produto
        document.getElementById("produto_id").addEventListener("change", function() {
            const selectedOption = this.options[this.selectedIndex];
            const produtoId = this.value;
            
            if (produtoId) {
                fetch("/admin/estoque/verificar-estoque/" + produtoId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const quantidadeEstoque = data.quantidade_estoque || 0;
                        document.getElementById("quantidade_necessaria").min = quantidadeEstoque + 1;
                        document.getElementById("quantidade_necessaria").placeholder = `Mínimo: ${quantidadeEstoque + 1}`;
                    }
                });
            }
        });
    </script>
</body>
</html>';
    }

    public function salvar($request) {
        try {
            $this->connection->beginTransaction();
            
            $produto_id = $request->getParam('produto_id');
            $quantidade_necessaria = $request->getParam('quantidade_necessaria');
            $prioridade = $request->getParam('prioridade') ?: 'media';
            $data_prevista_compra = $request->getParam('data_prevista_compra');
            $observacoes = $request->getParam('observacoes');
            
            // Buscar quantidade atual em estoque
            $stmt = $this->connection->prepare("
                SELECT COALESCE(SUM(quantidade), 0) as quantidade_estoque 
                FROM estoque_interno 
                WHERE produto_id = :produto_id
            ");
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->execute();
            $quantidade_estoque = $stmt->fetch(\PDO::FETCH_ASSOC)['quantidade_estoque'];
            
            // Calcular quantidade faltante
            $quantidade_faltante = max(0, $quantidade_necessaria - $quantidade_estoque);
            
            // Inserir na lista de compras
            $stmt = $this->connection->prepare("
                INSERT INTO lista_compras (
                    produto_id, quantidade_necessaria, quantidade_em_estoque, 
                    quantidade_faltante, prioridade, data_solicitacao, 
                    data_prevista_compra, observacoes, status
                ) VALUES (
                    :produto_id, :quantidade_necessaria, :quantidade_estoque,
                    :quantidade_faltante, :prioridade, CURDATE(),
                    :data_prevista_compra, :observacoes, 'pendente'
                )
            ");
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->bindParam(':quantidade_necessaria', $quantidade_necessaria);
            $stmt->bindParam(':quantidade_estoque', $quantidade_estoque);
            $stmt->bindParam(':quantidade_faltante', $quantidade_faltante);
            $stmt->bindParam(':prioridade', $prioridade);
            $stmt->bindParam(':data_prevista_compra', $data_prevista_compra);
            $stmt->bindParam(':observacoes', $observacoes);
            $stmt->execute();
            
            $this->connection->commit();
            
            echo json_encode(['success' => true, 'message' => 'Item adicionado à lista de compras com sucesso']);
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function mudarStatus($request) {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            $item_id = $dados['item_id'];
            $novo_status = $dados['status'];
            
            $stmt = $this->connection->prepare("
                UPDATE lista_compras 
                SET status = :status,
                    data_prevista_compra = CASE WHEN :status = 'comprado' THEN CURDATE() ELSE data_prevista_compra END
                WHERE id = :item_id
            ");
            $stmt->bindParam(':status', $novo_status);
            $stmt->bindParam(':item_id', $item_id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function verificarEstoque($request, $produto_id) {
        try {
            $stmt = $this->connection->prepare("
                SELECT COALESCE(SUM(quantidade), 0) as quantidade_estoque 
                FROM estoque_interno 
                WHERE produto_id = :produto_id
            ");
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->execute();
            $quantidade_estoque = $stmt->fetch(\PDO::FETCH_ASSOC)['quantidade_estoque'];
            
            echo json_encode(['success' => true, 'quantidade_estoque' => $quantidade_estoque]);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
