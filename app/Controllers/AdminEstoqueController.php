<?php
namespace App\Controllers;

class AdminEstoqueController {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function index($request) {
        try {
            // Buscar status geral do estoque
            $stmt = $this->connection->prepare("
                SELECT * FROM vw_status_geral_estoque 
                ORDER BY 
                    CASE 
                        WHEN status_estoque = 'crítico' THEN 1
                        WHEN status_estoque = 'baixo' THEN 2
                        ELSE 3
                    END,
                    produto_nome
            ");
            $stmt->execute();
            $status_geral = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Estatísticas gerais
            $stmt = $this->connection->prepare("
                SELECT 
                    COUNT(*) as total_produtos,
                    SUM(CASE WHEN status_estoque = 'crítico' THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN status_estoque = 'baixo' THEN 1 ELSE 0 END) as baixos,
                    SUM(CASE WHEN status_estoque = 'normal' THEN 1 ELSE 0 END) as normais,
                    SUM(quantidade_estoque) as total_estoque,
                    SUM(quantidade_faltante) as total_faltante,
                    COUNT(CASE WHEN is_alimenticio = 1 AND proxima_validade <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as vencendo_7dias
                FROM vw_status_geral_estoque
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Lista de compras pendentes
            $stmt = $this->connection->prepare("
                SELECT lc.*, p.name as produto_nome, p.sku, p.price
                FROM lista_compras lc
                JOIN produtos p ON lc.produto_id = p.id
                WHERE lc.status = 'pendente'
                ORDER BY lc.prioridade DESC, lc.data_solicitacao ASC
                LIMIT 20
            ");
            $stmt->execute();
            $compras_pendentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Produtos vencendo em breve
            $stmt = $this->connection->prepare("
                SELECT e.*, p.name as produto_nome, p.sku
                FROM estoque_interno e
                JOIN produtos p ON e.produto_id = p.id
                WHERE e.is_alimenticio = 1 
                AND e.data_validade IS NOT NULL
                AND e.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND e.data_validade >= CURDATE()
                ORDER BY e.data_validade ASC
                LIMIT 10
            ");
            $stmt->execute();
            $vencendo_breve = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $status_geral = [];
            $estatisticas = [];
            $compras_pendentes = [];
            $vencendo_breve = [];
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
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
        .status-critico { background-color: #dc3545; color: white; }
        .status-baixo { background-color: #ffc107; color: black; }
        .status-normal { background-color: #198754; color: white; }
        .card-stats { transition: transform 0.2s; }
        .card-stats:hover { transform: translateY(-5px); }
        .validade-proxima { background-color: #fff3cd; border-left: 4px solid #ffc107; }
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
                        <li class="nav-item"><a class="nav-link active" href="/admin/estoque"><i class="fas fa-fw fa-warehouse"></i><span>Estoque</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/estoque/compras"><i class="fas fa-fw fa-shopping-basket"></i><span>Compras</span></a></li>
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
                    <h1 class="h2"><i class="fas fa-warehouse me-2"></i>Estoque Interno</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="abrirModalAdicionar()">
                            <i class="fas fa-plus me-1"></i>Adicionar Item
                        </button>
                        <button type="button" class="btn btn-primary me-2" onclick="gerarRelatorioPDF()">
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
                                <h5 class="card-title">Vencendo em 7 dias</h5>
                                <h3>' . number_format($estatisticas['vencendo_7dias']) . '</h3>
                                <small>Produtos alimentícios</small>
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
                                    <option value="crítico">Crítico</option>
                                    <option value="baixo">Baixo</option>
                                    <option value="normal">Normal</option>
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
                            <div class="col-md-2">
                                <label class="form-label">Alimentício</label>
                                <select class="form-select" id="filtro_alimenticio">
                                    <option value="">Todos</option>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
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

                <!-- Tabela de Estoque -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Estoque Atual</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tabela_estoque">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>SKU</th>
                                        <th>Loja</th>
                                        <th>Estoque</th>
                                        <th>Mínimo</th>
                                        <th>Ideal</th>
                                        <th>Status</th>
                                        <th>Faltante</th>
                                        <th>Validade</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($status_geral as $item) {
                                    $status_class = $item['status_estoque'] == 'crítico' ? 'danger' : 
                                                   ($item['status_estoque'] == 'baixo' ? 'warning' : 'success');
                                    $validade_display = $item['proxima_validade'] ? 
                                        date('d/m/Y', strtotime($item['proxima_validade'])) : 
                                        ($item['is_alimenticio'] ? 'N/A' : 'Não aplicável');
                                    
                                    echo '<tr data-produto-id="' . $item['produto_id'] . '">
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
                                        <td>' . $item['estoque_minimo'] . '</td>
                                        <td>' . $item['estoque_ideal'] . '</td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . ucfirst($item['status_estoque']) . '</span>
                                        </td>
                                        <td>
                                            ' . ($item['quantidade_faltante'] > 0 ? '<span class="badge bg-danger">' . $item['quantidade_faltante'] . '</span>' : '-') . '
                                        </td>
                                        <td>
                                            ' . $validade_display . '
                                            ' . ($item['is_alimenticio'] && $item['proxima_validade'] && $item['proxima_validade'] <= date('Y-m-d', strtotime('+7 days')) ? 
                                                '<br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Vencendo em breve</small>' : '') . '
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="editarItem(' . $item['produto_id'] . ')" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-success" onclick="adicionarEstoque(' . $item['produto_id'] . ')" title="Adicionar Estoque">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" onclick="verHistorico(' . $item['produto_id'] . ')" title="Histórico">
                                                    <i class="fas fa-history"></i>
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

                <!-- Alertas de Validade -->
                ' . (!empty($vencendo_breve) ? '
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Produtos Vencendo em Breve</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">';
                        
                        foreach ($vencendo_breve as $item) {
                            $dias_para_vencer = (new \DateTime($item['data_validade']))->diff(new \DateTime())->days;
                            echo '<div class="col-md-4 mb-2">
                                <div class="validade-proxima p-2 rounded">
                                    <strong>' . htmlspecialchars($item['produto_nome']) . '</strong>
                                    <br><small>SKU: ' . htmlspecialchars($item['sku']) . '</small>
                                    <br><small class="text-danger">Vence em ' . $dias_para_vencer . ' dias (' . date('d/m/Y', strtotime($item['data_validade'])) . ')</small>
                                </div>
                            </div>';
                        }
                        
                        echo '</div>
                    </div>
                </div>' : '') . '

                <!-- Lista de Compras Pendentes -->
                ' . (!empty($compras_pendentes) ? '
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-shopping-basket me-2"></i>Compras Pendentes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Necessário</th>
                                        <th>Em Estoque</th>
                                        <th>Faltante</th>
                                        <th>Prioridade</th>
                                        <th>Data Solicitação</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($compras_pendentes as $item) {
                                    $prioridade_class = $item['prioridade'] == 'urgente' ? 'danger' : 
                                                       ($item['prioridade'] == 'alta' ? 'warning' : 
                                                       ($item['prioridade'] == 'media' ? 'info' : 'secondary'));
                                    
                                    echo '<tr>
                                        <td>' . htmlspecialchars($item['produto_nome']) . '</td>
                                        <td>' . $item['quantidade_necessaria'] . '</td>
                                        <td>' . $item['quantidade_em_estoque'] . '</td>
                                        <td><span class="badge bg-danger">' . $item['quantidade_faltante'] . '</span></td>
                                        <td><span class="badge bg-' . $prioridade_class . '">' . ucfirst($item['prioridade']) . '</span></td>
                                        <td>' . date('d/m/Y', strtotime($item['data_solicitacao'])) . '</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-success" onclick="marcarComprado(' . $item['id'] . ')">
                                                <i class="fas fa-check"></i> Comprado
                                            </button>
                                        </td>
                                    </tr>';
                                }
                                
                                echo '</tbody>
                            </table>
                        </div>
                    </div>
                </div>' : '') . '
            </main>
        </div>
    </div>

    <!-- Modal Adicionar/Editar Item -->
    <div class="modal fade" id="modalItemEstoque">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Adicionar Item ao Estoque</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formItemEstoque">
                        <input type="hidden" id="item_id" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Produto *</label>
                                    <select class="form-select" id="produto_id" name="produto_id" required>
                                        <option value="">Selecione...</option>';
                                        
                                        // Buscar produtos ativos
                                        $stmt = $this->connection->prepare("SELECT id, name, sku, loja FROM produtos WHERE active = 1 ORDER BY name");
                                        $stmt->execute();
                                        $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                                        
                                        foreach ($produtos as $produto) {
                                            echo '<option value="' . $produto['id'] . '">' . htmlspecialchars($produto['name']) . ' (' . htmlspecialchars($produto['sku']) . ') - ' . ucfirst($produto['loja']) . '</option>';
                                        }
                                        
                                        echo '</select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quantidade *</label>
                                    <input type="number" class="form-control" id="quantidade" name="quantidade" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data da Compra *</label>
                                    <input type="date" class="form-control" id="data_compra" name="data_compra" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Categoria</label>
                                    <input type="text" class="form-control" id="categoria" name="categoria" placeholder="Ex: Alimentos, Eletrônicos">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">É Alimentício?</label>
                                    <select class="form-select" id="is_alimenticio" name="is_alimenticio">
                                        <option value="0">Não</option>
                                        <option value="1">Sim</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data de Validade</label>
                                    <input type="date" class="form-control" id="data_validade" name="data_validade">
                                    <small class="text-muted">Apenas para produtos alimentícios</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Local de Armazenamento</label>
                                    <input type="text" class="form-control" id="local_armazenamento" name="local_armazenamento" value="Principal">
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
                    <button type="button" class="btn btn-primary" onclick="salvarItemEstoque()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Funções JavaScript
        function abrirModalAdicionar() {
            document.getElementById("modalTitle").textContent = "Adicionar Item ao Estoque";
            document.getElementById("formItemEstoque").reset();
            document.getElementById("data_compra").value = new Date().toISOString().split("T")[0];
            new bootstrap.Modal(document.getElementById("modalItemEstoque")).show();
        }

        function editarItem(produtoId) {
            // Implementar edição de item
            alert("Funcionalidade de edição em desenvolvimento");
        }

        function adicionarEstoque(produtoId) {
            document.getElementById("produto_id").value = produtoId;
            document.getElementById("modalTitle").textContent = "Adicionar Estoque";
            document.getElementById("formItemEstoque").reset();
            document.getElementById("data_compra").value = new Date().toISOString().split("T")[0];
            new bootstrap.Modal(document.getElementById("modalItemEstoque")).show();
        }

        function salvarItemEstoque() {
            const form = document.getElementById("formItemEstoque");
            const formData = new FormData(form);
            
            fetch("/admin/estoque/salvar", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Item salvo com sucesso!");
                    bootstrap.Modal.getInstance(document.getElementById("modalItemEstoque")).hide();
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

        function aplicarFiltros() {
            const busca = document.getElementById("busca_produto").value.toLowerCase();
            const status = document.getElementById("filtro_status").value;
            const loja = document.getElementById("filtro_loja").value;
            const alimenticio = document.getElementById("filtro_alimenticio").value;
            
            const rows = document.querySelectorAll("#tabela_estoque tbody tr");
            
            rows.forEach(row => {
                const produto = row.cells[0].textContent.toLowerCase();
                const statusRow = row.cells[6].textContent.toLowerCase();
                const lojaRow = row.cells[2].textContent.toLowerCase();
                const validadeCell = row.cells[8].textContent;
                const isAlimenticio = validadeCell !== "Não aplicável";
                
                let show = true;
                
                if (busca && !produto.includes(busca)) show = false;
                if (status && !statusRow.includes(status)) show = false;
                if (loja && !lojaRow.includes(loja)) show = false;
                if (alimenticio !== "" && alimenticio !== (isAlimenticio ? "1" : "0")) show = false;
                
                row.style.display = show ? "" : "none";
            });
        }

        function limparFiltros() {
            document.getElementById("busca_produto").value = "";
            document.getElementById("filtro_status").value = "";
            document.getElementById("filtro_loja").value = "";
            document.getElementById("filtro_alimenticio").value = "";
            aplicarFiltros();
        }

        function atualizarDados() {
            location.reload();
        }

        function gerarRelatorioPDF() {
            window.open("/admin/estoque/relatorio-pdf", "_blank");
        }

        function verHistorico(produtoId) {
            window.open("/admin/estoque/historico/" + produtoId, "_blank");
        }

        function marcarComprado(itemId) {
            if (confirm("Tem certeza que deseja marcar este item como comprado?")) {
                fetch("/admin/estoque/marcar-comprado", {
                    method: "POST",
                    headers: {"Content-Type": "application/json"},
                    body: JSON.stringify({item_id: itemId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Item marcado como comprado!");
                        location.reload();
                    } else {
                        alert("Erro: " + data.message);
                    }
                });
            }
        }

        // Configurar validação de data de validade
        document.getElementById("is_alimenticio").addEventListener("change", function() {
            const validadeField = document.getElementById("data_validade");
            if (this.value === "1") {
                validadeField.required = true;
                validadeField.disabled = false;
            } else {
                validadeField.required = false;
                validadeField.disabled = true;
                validadeField.value = "";
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
            $quantidade = $request->getParam('quantidade');
            $data_compra = $request->getParam('data_compra');
            $categoria = $request->getParam('categoria') ?: 'Geral';
            $is_alimenticio = $request->getParam('is_alimenticio') ?: 0;
            $data_validade = $request->getParam('data_validade') ?: null;
            $local_armazenamento = $request->getParam('local_armazenamento') ?: 'Principal';
            $observacoes = $request->getParam('observacoes');
            
            // Buscar quantidade anterior
            $stmt = $this->connection->prepare("
                SELECT COALESCE(SUM(quantidade), 0) as quantidade_anterior 
                FROM estoque_interno 
                WHERE produto_id = :produto_id
            ");
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->execute();
            $quantidade_anterior = $stmt->fetch(\PDO::FETCH_ASSOC)['quantidade_anterior'];
            
            // Inserir novo item no estoque
            $stmt = $this->connection->prepare("
                INSERT INTO estoque_interno (
                    produto_id, quantidade, data_compra, categoria, 
                    is_alimenticio, data_validade, local_armazenamento, observacoes
                ) VALUES (
                    :produto_id, :quantidade, :data_compra, :categoria,
                    :is_alimenticio, :data_validade, :local_armazenamento, :observacoes
                )
            ");
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':data_compra', $data_compra);
            $stmt->bindParam(':categoria', $categoria);
            $stmt->bindParam(':is_alimenticio', $is_alimenticio);
            $stmt->bindParam(':data_validade', $data_validade);
            $stmt->bindParam(':local_armazenamento', $local_armazenamento);
            $stmt->bindParam(':observacoes', $observacoes);
            $stmt->execute();
            
            // Calcular nova quantidade
            $quantidade_nova = $quantidade_anterior + $quantidade;
            
            // Registrar movimentação
            $stmt = $this->connection->prepare("
                INSERT INTO estoque_movimentacao (
                    produto_id, tipo_movimentacao, quantidade, 
                    quantidade_anterior, quantidade_nova, motivo
                ) VALUES (
                    :produto_id, 'entrada', :quantidade,
                    :quantidade_anterior, :quantidade_nova, 'Entrada manual de estoque'
                )
            ");
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':quantidade_anterior', $quantidade_anterior);
            $stmt->bindParam(':quantidade_nova', $quantidade_nova);
            $stmt->execute();
            
            // Atualizar lista de compras (se houver itens pendentes)
            $stmt = $this->connection->prepare("
                UPDATE lista_compras 
                SET quantidade_em_estoque = quantidade_em_estoque + :quantidade,
                    quantidade_faltante = GREATEST(0, quantidade_faltante - :quantidade),
                    status = CASE WHEN quantidade_faltante - :quantidade <= 0 THEN 'comprado' ELSE 'pendente' END
                WHERE produto_id = :produto_id AND status = 'pendente'
            ");
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->execute();
            
            $this->connection->commit();
            
            echo json_encode(['success' => true, 'message' => 'Item adicionado ao estoque com sucesso']);
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function marcarComprado($request) {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            $item_id = $dados['item_id'];
            
            $stmt = $this->connection->prepare("
                UPDATE lista_compras 
                SET status = 'comprado', data_prevista_compra = CURDATE()
                WHERE id = :item_id
            ");
            $stmt->bindParam(':item_id', $item_id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Item marcado como comprado']);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
