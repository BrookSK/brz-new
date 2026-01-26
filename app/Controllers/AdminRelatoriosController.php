<?php
namespace App\Controllers;

class AdminRelatoriosController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function index($request) {
        try {
            // Buscar estatísticas gerais
            $stmt = $this->connection->prepare("
                SELECT 
                    COUNT(DISTINCT p.id) as total_produtos,
                    COUNT(DISTINCT CASE WHEN e.quantidade > 0 THEN p.id END) as produtos_com_estoque,
                    COALESCE(SUM(e.quantidade), 0) as total_itens_estoque,
                    COUNT(DISTINCT CASE WHEN lc.status = 'pendente' THEN lc.produto_id END) as produtos_comprar,
                    COALESCE(SUM(lc.quantidade_faltante), 0) as total_itens_faltantes,
                    COUNT(DISTINCT CASE WHEN e.is_alimenticio = 1 AND e.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN e.produto_id END) as produtos_vencendo
                FROM produtos p
                LEFT JOIN estoque_interno e ON p.id = e.produto_id
                LEFT JOIN lista_compras lc ON p.id = lc.produto_id AND lc.status = 'pendente'
                WHERE p.active = 1
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Movimentações recentes
            $stmt = $this->connection->prepare("
                SELECT 
                    em.*,
                    p.name as produto_nome,
                    p.sku,
                    u.name as usuario_nome
                FROM estoque_movimentacao em
                JOIN produtos p ON em.produto_id = p.id
                LEFT JOIN usuarios u ON em.usuario_id = u.id
                ORDER BY em.data_movimentacao DESC
                LIMIT 20
            ");
            $stmt->execute();
            $movimentacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Produtos críticos
            $stmt = $this->connection->prepare("
                SELECT 
                    p.id,
                    p.name as produto_nome,
                    p.sku,
                    p.loja,
                    COALESCE(SUM(e.quantidade), 0) as quantidade_estoque,
                    ec.estoque_minimo,
                    ec.estoque_ideal
                FROM produtos p
                LEFT JOIN estoque_interno e ON p.id = e.produto_id
                LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
                WHERE p.active = 1
                GROUP BY p.id, p.name, p.sku, p.loja, ec.estoque_minimo, ec.estoque_ideal
                HAVING quantidade_estoque <= COALESCE(ec.estoque_minimo, 5)
                ORDER BY quantidade_estoque ASC
                LIMIT 10
            ");
            $stmt->execute();
            $produtos_criticos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Compras urgentes
            $stmt = $this->connection->prepare("
                SELECT 
                    lc.*,
                    p.name as produto_nome,
                    p.sku,
                    p.price,
                    p.loja
                FROM lista_compras lc
                JOIN produtos p ON lc.produto_id = p.id
                WHERE lc.status = 'pendente' AND lc.prioridade IN ('urgente', 'alta')
                ORDER BY lc.prioridade DESC, lc.data_solicitacao ASC
                LIMIT 10
            ");
            $stmt->execute();
            $compras_urgentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $estatisticas = [];
            $movimentacoes = [];
            $produtos_criticos = [];
            $compras_urgentes = [];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('relatorios');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-file-pdf me-2"></i>Relatórios e Análises</h1>
                    <div>
                        <button type="button" class="btn btn-danger me-2" onclick="gerarPDFCompleto()">
                            <i class="fas fa-file-pdf me-1"></i>Relatório Completo
                        </button>
                        <button type="button" class="btn btn-warning me-2" onclick="gerarPDFEstoque()">
                            <i class="fas fa-warehouse me-1"></i>Estoque
                        </button>
                        <button type="button" class="btn btn-info me-2" onclick="gerarPDFCompras()">
                            <i class="fas fa-shopping-basket me-1"></i>Compras
                        </button>
                        <button type="button" class="btn btn-success" onclick="gerarPDFMovimentacao()">
                            <i class="fas fa-exchange-alt me-1"></i>Movimentação
                        </button>
                    </div>
                </div>';

                // Cards de Estatísticas
                echo '<!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Produtos</h5>
                                <h3>' . number_format($estatisticas['total_produtos']) . '</h3>
                                <small>Cadastrados no sistema</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Com Estoque</h5>
                                <h3>' . number_format($estatisticas['produtos_com_estoque']) . '</h3>
                                <small>Com itens em estoque</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Precisa Comprar</h5>
                                <h3>' . number_format($estatisticas['produtos_comprar']) . '</h3>
                                <small>Na lista de compras</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Vencendo</h5>
                                <h3>' . number_format($estatisticas['produtos_vencendo']) . '</h3>
                                <small>Próximos 30 dias</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros para Relatórios -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-filter me-2"></i>Filtros para Relatórios</h6>
                    </div>
                    <div class="card-body">
                        <form id="formRelatorio">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Tipo de Relatório</label>
                                    <select class="form-select" id="tipo_relatorio" name="tipo_relatorio">
                                        <option value="completo">Relatório Completo</option>
                                        <option value="estoque">Apenas Estoque</option>
                                        <option value="compras">Apenas Compras</option>
                                        <option value="movimentacao">Apenas Movimentação</option>
                                        <option value="validades">Produtos por Validade</option>
                                        <option value="criticos">Produtos Críticos</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data Inicial</label>
                                    <input type="date" class="form-control" id="data_inicial" name="data_inicial">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data Final</label>
                                    <input type="date" class="form-control" id="data_final" name="data_final">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Loja</label>
                                    <select class="form-select" id="loja_filtro" name="loja_filtro">
                                        <option value="">Todas</option>
                                        <option value="sams">Sams</option>
                                        <option value="costco">Costco</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-primary" onclick="gerarRelatorioPersonalizado()">
                                        <i class="fas fa-file-pdf me-1"></i>Gerar Relatório Personalizado
                                    </button>
                                    <button type="button" class="btn btn-secondary ms-2" onclick="limparFiltros()">
                                        <i class="fas fa-times me-1"></i>Limpar Filtros
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Produtos Críticos -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Produtos Críticos</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Estoque</th>
                                                <th>Mínimo</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($produtos_criticos as $produto) {
                                            $status = $produto['quantidade_estoque'] == 0 ? 'Esgotado' : 'Crítico';
                                            echo '<tr>
                                                <td>
                                                    <strong>' . htmlspecialchars($produto['produto_nome']) . '</strong>
                                                    <br><small class="text-muted">' . htmlspecialchars($produto['sku']) . '</small>
                                                </td>
                                                <td><span class="badge bg-danger">' . $produto['quantidade_estoque'] . '</span></td>
                                                <td>' . $produto['estoque_minimo'] . '</td>
                                                <td><span class="badge bg-danger">' . $status . '</span></td>
                                            </tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h6><i class="fas fa-shopping-basket me-2"></i>Compras Urgentes</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Faltante</th>
                                                <th>Prioridade</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($compras_urgentes as $compra) {
                                            $prioridade_class = $compra['prioridade'] == 'urgente' ? 'danger' : 'warning';
                                            $total = $compra['quantidade_faltante'] * $compra['price'];
                                            echo '<tr>
                                                <td>
                                                    <strong>' . htmlspecialchars($compra['produto_nome']) . '</strong>
                                                    <br><small class="text-muted">' . htmlspecialchars($compra['sku']) . '</small>
                                                </td>
                                                <td><span class="badge bg-danger">' . $compra['quantidade_faltante'] . '</span></td>
                                                <td><span class="badge bg-' . $prioridade_class . '">' . ucfirst($compra['prioridade']) . '</span></td>
                                                <td>R$ ' . number_format($total, 2, ',', '.') . '</td>
                                            </tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Movimentações Recentes -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-history me-2"></i>Movimentações Recentes</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Produto</th>
                                        <th>Tipo</th>
                                        <th>Quantidade</th>
                                        <th>Anterior</th>
                                        <th>Nova</th>
                                        <th>Motivo</th>
                                        <th>Usuário</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($movimentacoes as $mov) {
                                    $tipo_class = $mov['tipo_movimentacao'] == 'entrada' ? 'success' : 
                                                 ($mov['tipo_movimentacao'] == 'saida' ? 'danger' : 
                                                 ($mov['tipo_movimentacao'] == 'ajuste' ? 'warning' : 'secondary'));
                                    echo '<tr>
                                        <td>' . date('d/m/Y H:i', strtotime($mov['data_movimentacao'])) . '</td>
                                        <td>
                                            <strong>' . htmlspecialchars($mov['produto_nome']) . '</strong>
                                            <br><small class="text-muted">' . htmlspecialchars($mov['sku']) . '</small>
                                        </td>
                                        <td><span class="badge bg-' . $tipo_class . '">' . ucfirst($mov['tipo_movimentacao']) . '</span></td>
                                        <td>' . $mov['quantidade'] . '</td>
                                        <td>' . $mov['quantidade_anterior'] . '</td>
                                        <td>' . $mov['quantidade_nova'] . '</td>
                                        <td>' . htmlspecialchars($mov['motivo']) . '</td>
                                        <td>' . htmlspecialchars($mov['usuario_nome'] ?? 'Sistema') . '</td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function gerarPDFCompleto() {
            window.open("/admin/estoque/relatorio-pdf?tipo=completo", "_blank");
        }

        function gerarPDFEstoque() {
            window.open("/admin/estoque/relatorio-pdf?tipo=estoque", "_blank");
        }

        function gerarPDFCompras() {
            window.open("/admin/estoque/compras/pdf", "_blank");
        }

        function gerarPDFMovimentacao() {
            window.open("/admin/estoque/relatorio-pdf?tipo=movimentacao", "_blank");
        }

        function gerarRelatorioPersonalizado() {
            const tipo = document.getElementById("tipo_relatorio").value;
            const dataInicial = document.getElementById("data_inicial").value;
            const dataFinal = document.getElementById("data_final").value;
            const loja = document.getElementById("loja_filtro").value;
            
            let url = "/admin/estoque/relatorio-pdf?tipo=" + tipo;
            if (dataInicial) url += "&data_inicial=" + dataInicial;
            if (dataFinal) url += "&data_final=" + dataFinal;
            if (loja) url += "&loja=" + loja;
            
            window.open(url, "_blank");
        }

        function limparFiltros() {
            document.getElementById("formRelatorio").reset();
        }

        // Configurar datas padrão
        document.addEventListener("DOMContentLoaded", function() {
            const hoje = new Date();
            const trintaDiasAtras = new Date(hoje.getTime() - 30 * 24 * 60 * 60 * 1000);
            
            document.getElementById("data_inicial").value = trintaDiasAtras.toISOString().split("T")[0];
            document.getElementById("data_final").value = hoje.toISOString().split("T")[0];
        });
    </script>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '</body>
</html>';
    }

    public function gerarPDF($request) {
        try {
            $tipo = $request->getParam('tipo', 'completo');
            $data_inicial = $request->getParam('data_inicial');
            $data_final = $request->getParam('data_final');
            $loja = $request->getParam('loja');

            // Buscar dados conforme o tipo
            $dados = $this->buscarDadosRelatorio($tipo, $data_inicial, $data_final, $loja);

            // Gerar PDF
            $this->gerarArquivoPDF($dados, $tipo);

        } catch (\Exception $e) {
            echo "Erro ao gerar PDF: " . $e->getMessage();
        }
    }

    private function buscarDadosRelatorio($tipo, $data_inicial, $data_final, $loja) {
        $where_clauses = [];
        $params = [];

        if ($data_inicial) {
            $where_clauses[] = "DATE(em.data_movimentacao) >= :data_inicial";
            $params[':data_inicial'] = $data_inicial;
        }
        if ($data_final) {
            $where_clauses[] = "DATE(em.data_movimentacao) <= :data_final";
            $params[':data_final'] = $data_final;
        }
        if ($loja) {
            $where_clauses[] = "p.loja = :loja";
            $params[':loja'] = $loja;
        }

        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

        switch ($tipo) {
            case 'estoque':
                $sql = "
                    SELECT 
                        p.id,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        p.price,
                        COALESCE(SUM(e.quantidade), 0) as quantidade_estoque,
                        ec.estoque_minimo,
                        ec.estoque_ideal,
                        CASE 
                            WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_minimo, 5) THEN 'crítico'
                            WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_ideal, 20) THEN 'baixo'
                            ELSE 'normal'
                        END as status_estoque
                    FROM produtos p
                    LEFT JOIN estoque_interno e ON p.id = e.produto_id
                    LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
                    " . ($loja ? "WHERE p.loja = :loja" : "") . "
                    GROUP BY p.id, p.name, p.sku, p.loja, p.price, ec.estoque_minimo, ec.estoque_ideal
                    ORDER BY status_estoque, p.name
                ";
                break;

            case 'compras':
                $sql = "
                    SELECT 
                        lc.*,
                        p.name as produto_nome,
                        p.sku,
                        p.price,
                        p.loja,
                        COALESCE(SUM(e.quantidade), 0) as quantidade_estoque_atual
                    FROM lista_compras lc
                    JOIN produtos p ON lc.produto_id = p.id
                    LEFT JOIN estoque_interno e ON lc.produto_id = e.produto_id
                    " . ($loja ? "WHERE p.loja = :loja" : "") . "
                    GROUP BY lc.id, p.name, p.sku, p.price, p.loja
                    ORDER BY lc.prioridade DESC, lc.data_solicitacao ASC
                ";
                break;

            case 'movimentacao':
                $sql = "
                    SELECT 
                        em.*,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        u.name as usuario_nome
                    FROM estoque_movimentacao em
                    JOIN produtos p ON em.produto_id = p.id
                    LEFT JOIN usuarios u ON em.usuario_id = u.id
                    $where_sql
                    ORDER BY em.data_movimentacao DESC
                ";
                break;

            case 'validades':
                $sql = "
                    SELECT 
                        e.*,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        DATEDIFF(e.data_validade, CURDATE()) as dias_para_vencer
                    FROM estoque_interno e
                    JOIN produtos p ON e.produto_id = p.id
                    WHERE e.is_alimenticio = 1 
                    AND e.data_validade IS NOT NULL
                    " . ($loja ? "AND p.loja = :loja" : "") . "
                    ORDER BY e.data_validade ASC
                ";
                break;

            case 'criticos':
                $sql = "
                    SELECT 
                        p.id,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        p.price,
                        COALESCE(SUM(e.quantidade), 0) as quantidade_estoque,
                        ec.estoque_minimo,
                        ec.estoque_ideal,
                        lc.quantidade_faltante
                    FROM produtos p
                    LEFT JOIN estoque_interno e ON p.id = e.produto_id
                    LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
                    LEFT JOIN lista_compras lc ON p.id = lc.produto_id AND lc.status = 'pendente'
                    WHERE p.active = 1
                    " . ($loja ? "AND p.loja = :loja" : "") . "
                    GROUP BY p.id, p.name, p.sku, p.loja, p.price, ec.estoque_minimo, ec.estoque_ideal, lc.quantidade_faltante
                    HAVING quantidade_estoque <= COALESCE(ec.estoque_minimo, 5)
                    ORDER BY quantidade_estoque ASC
                ";
                break;

            default: // completo
                $sql = "
                    SELECT 
                        'estoque' as tipo,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        COALESCE(SUM(e.quantidade), 0) as quantidade,
                        ec.estoque_minimo,
                        ec.estoque_ideal,
                        CASE 
                            WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_minimo, 5) THEN 'crítico'
                            WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_ideal, 20) THEN 'baixo'
                            ELSE 'normal'
                        END as status
                    FROM produtos p
                    LEFT JOIN estoque_interno e ON p.id = e.produto_id
                    LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
                    " . ($loja ? "WHERE p.loja = :loja" : "") . "
                    GROUP BY p.id, p.name, p.sku, p.loja, ec.estoque_minimo, ec.estoque_ideal
                ";
        }

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function gerarArquivoPDF($dados, $tipo) {
        // Configurações do PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="relatorio_' . $tipo . '_' . date('Y-m-d') . '.pdf"');

        // Iniciar buffer de saída
        ob_start();

        // HTML do PDF
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de ' . ucfirst($tipo) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .critico { background-color: #ffebee; }
        .baixo { background-color: #fff3e0; }
        .normal { background-color: #e8f5e8; }
        .total { font-weight: bold; background-color: #f5f5f5; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de ' . ucfirst($tipo) . '</h1>
        <p>Gerado em: ' . date('d/m/Y H:i') . '</p>
    </div>

    <div class="content">
        <table>
            <thead>
                <tr>';

        // Cabeçalho da tabela conforme o tipo
        switch ($tipo) {
            case 'estoque':
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Estoque</th><th>Mínimo</th><th>Ideal</th><th>Status</th>';
                break;
            case 'compras':
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Necessário</th><th>Em Estoque</th><th>Faltante</th><th>Prioridade</th><th>Status</th>';
                break;
            case 'movimentacao':
                echo '<th>Data</th><th>Produto</th><th>SKU</th><th>Tipo</th><th>Quantidade</th><th>Anterior</th><th>Nova</th><th>Motivo</th>';
                break;
            case 'validades':
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Quantidade</th><th>Data Validade</th><th>Dias para Vencer</th>';
                break;
            case 'criticos':
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Estoque</th><th>Mínimo</th><th>Faltante</th><th>Preço Unit.</th>';
                break;
            default:
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Quantidade</th><th>Status</th>';
        }

        echo '</tr>
            </thead>
            <tbody>';

        // Dados da tabela
        foreach ($dados as $item) {
            $row_class = '';
            if ($tipo == 'estoque' || $tipo == 'completo') {
                $row_class = $item['status'] == 'crítico' ? 'critico' : 
                           ($item['status'] == 'baixo' ? 'baixo' : 'normal');
            }

            echo '<tr class="' . $row_class . '">';

            switch ($tipo) {
                case 'estoque':
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade'] . '</td>';
                    echo '<td>' . $item['estoque_minimo'] . '</td>';
                    echo '<td>' . $item['estoque_ideal'] . '</td>';
                    echo '<td>' . ucfirst($item['status']) . '</td>';
                    break;

                case 'compras':
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade_necessaria'] . '</td>';
                    echo '<td>' . $item['quantidade_em_estoque_atual'] . '</td>';
                    echo '<td>' . $item['quantidade_faltante'] . '</td>';
                    echo '<td>' . ucfirst($item['prioridade']) . '</td>';
                    echo '<td>' . ucfirst($item['status']) . '</td>';
                    break;

                case 'movimentacao':
                    echo '<td>' . date('d/m/Y H:i', strtotime($item['data_movimentacao'])) . '</td>';
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['tipo_movimentacao']) . '</td>';
                    echo '<td>' . $item['quantidade'] . '</td>';
                    echo '<td>' . $item['quantidade_anterior'] . '</td>';
                    echo '<td>' . $item['quantidade_nova'] . '</td>';
                    echo '<td>' . htmlspecialchars($item['motivo']) . '</td>';
                    break;

                case 'validades':
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade'] . '</td>';
                    echo '<td>' . date('d/m/Y', strtotime($item['data_validade'])) . '</td>';
                    echo '<td>' . $item['dias_para_vencer'] . ' dias</td>';
                    break;

                case 'criticos':
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade_estoque'] . '</td>';
                    echo '<td>' . $item['estoque_minimo'] . '</td>';
                    echo '<td>' . $item['quantidade_faltante'] . '</td>';
                    echo '<td>R$ ' . number_format($item['price'], 2, ',', '.') . '</td>';
                    break;

                default:
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade'] . '</td>';
                    echo '<td>' . ucfirst($item['status']) . '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody>
        </table>
    </div>

    <div class="footer">
        <p>Relatório gerado pelo Sistema BRZ Estoque - Página ' . date('d/m/Y H:i') . '</p>
    </div>
</body>
</html>';

        // Converter HTML para PDF usando a biblioteca DOMPDF (se disponível)
        // Por enquanto, vamos apenas exibir o HTML que pode ser salvo como PDF pelo navegador
        $html = ob_get_clean();
        
        // Se tiver a biblioteca mPDF, usaríamos:
        // require_once 'vendor/autoload.php';
        // $mpdf = new \Mpdf\Mpdf();
        // $mpdf->WriteHTML($html);
        // $mpdf->Output();
        
        // Por enquanto, apenas exibir o HTML
        echo $html;
    }
}
