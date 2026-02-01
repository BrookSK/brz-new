<?php
namespace App\Controllers;

class AdminEstoqueController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function setFlash(string $message, string $type = 'success'): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }

    public function index($request) {
        try {
            $produtos = [];
            try {
                $stmtProdutos = $this->connection->prepare("SELECT id, name, sku FROM produtos WHERE active = 1 ORDER BY name");
                $stmtProdutos->execute();
                $produtos = $stmtProdutos->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $produtos = [];
            }

            // Buscar status geral do estoque (apenas itens com quantidade no galpão)
            $stmt = $this->connection->prepare("
                SELECT
                    v.*, 
                    loc.localizacao,
                    loc.data_compra_mais_recente,
                    loc.validade_mais_proxima
                FROM vw_status_geral_estoque v
                JOIN (
                    SELECT
                        e.produto_id,
                        GROUP_CONCAT(DISTINCT CONCAT(COALESCE(e.galpao, ''), ' - Prateleira ', COALESCE(e.prateleira, '')) SEPARATOR ', ') AS localizacao,
                        MAX(e.data_compra) AS data_compra_mais_recente,
                        MIN(CASE WHEN e.is_alimenticio = 1 AND e.data_validade IS NOT NULL THEN e.data_validade ELSE NULL END) AS validade_mais_proxima
                    FROM estoque_interno e
                    WHERE e.quantidade > 0
                    GROUP BY e.produto_id
                ) loc ON loc.produto_id = v.produto_id
                ORDER BY v.produto_nome
            ");
            $stmt->execute();
            $status_geral = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Estatísticas
            $stmt = $this->connection->prepare("SELECT
                    COUNT(*) as total_produtos,
                    SUM(CASE WHEN status_estoque = 'crítico' THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN status_estoque = 'baixo' THEN 1 ELSE 0 END) as baixos,
                    SUM(CASE WHEN status_estoque = 'normal' THEN 1 ELSE 0 END) as normais
                FROM (
                    SELECT v.*
                    FROM vw_status_geral_estoque v
                    JOIN (SELECT DISTINCT produto_id FROM estoque_interno WHERE quantidade > 0) e ON e.produto_id = v.produto_id
                ) t
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $produtos = [];
            $status_geral = [];
            $estatisticas = ['total_produtos' => 0, 'criticos' => 0, 'baixos' => 0, 'normais' => 0];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque Interno - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('estoque');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-warehouse me-2"></i>Estoque Interno</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalNovoItem">
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
                </div>';

                // Tabela de Estoque
                echo '<div class="card">
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
                                        <th>Disponível</th>
                                        <th>Data compra</th>
                                        <th>Validade</th>
                                        <th>Localização</th>
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
                                        <td>' . (!empty($item['data_compra_mais_recente']) ? date('d/m/Y', strtotime($item['data_compra_mais_recente'])) : '-') . '</td>
                                        <td>' . (!empty($item['validade_mais_proxima']) ? date('d/m/Y', strtotime($item['validade_mais_proxima'])) : '-') . '</td>
                                        <td>' . (!empty($item['localizacao']) ? htmlspecialchars($item['localizacao']) : '-') . '</td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . ucfirst($item['status_estoque']) . '</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNovoItem" onclick="preencherProdutoEstoque(' . (int)$item['produto_id'] . ')">
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
                </div>';

                // Informações do Sistema
                echo '<div class="card mt-4">
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

    <div class="modal fade" id="modalNovoItem" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="/admin/estoque/salvar">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Entrada de Estoque (Galpão)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Produto</label>
                                <select class="form-select" name="produto_id" id="estoque_produto_id" required>
                                    <option value="">Selecione...</option>';

        foreach (($produtos ?? []) as $p) {
            echo '<option value="' . (int) $p['id'] . '">' . htmlspecialchars($p['name'] . ' (' . $p['sku'] . ')') . '</option>';
        }

        echo '                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Quantidade disponível</label>
                                <input type="number" class="form-control" name="quantidade" min="1" step="1" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Data da compra</label>
                                <input type="date" class="form-control" name="data_compra">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Alimentício</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" value="1" id="estoque_is_alimenticio" name="is_alimenticio" onchange="toggleValidade()">
                                    <label class="form-check-label" for="estoque_is_alimenticio">Controlar validade</label>
                                </div>
                            </div>

                            <div class="col-md-4" id="grupo_validade" style="display:none;">
                                <label class="form-label">Data de validade</label>
                                <input type="date" class="form-control" name="data_validade">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Galpão</label>
                                <input type="text" class="form-control" name="galpao" placeholder="Ex: Galpão A">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Prateleira</label>
                                <input type="text" class="form-control" name="prateleira" placeholder="Ex: 3">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Observação</label>
                                <input type="text" class="form-control" name="observacao" placeholder="Opcional">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar entrada</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function preencherProdutoEstoque(produtoId) {
            var el = document.getElementById('estoque_produto_id');
            if (!el) return;
            el.value = String(produtoId);
        }
        function toggleValidade() {
            var chk = document.getElementById('estoque_is_alimenticio');
            var grp = document.getElementById('grupo_validade');
            if (!chk || !grp) return;
            grp.style.display = chk.checked ? '' : 'none';
        }
    </script>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '</body>
</html>';
    }

    public function salvar($request) {
        try {
            $produtoId = (int) $request->getParam('produto_id');
            $quantidade = (int) $request->getParam('quantidade');
            $dataCompra = trim((string) $request->getParam('data_compra', ''));
            $isAlimenticio = $request->getParam('is_alimenticio', '0') ? 1 : 0;
            $dataValidade = trim((string) $request->getParam('data_validade', ''));
            $galpao = trim((string) $request->getParam('galpao', ''));
            $prateleira = trim((string) $request->getParam('prateleira', ''));
            $observacao = trim((string) $request->getParam('observacao', ''));

            if ($produtoId <= 0) {
                $this->setFlash('Selecione um produto válido.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }
            if ($quantidade <= 0) {
                $this->setFlash('Informe uma quantidade válida.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }
            if ($isAlimenticio === 0) {
                $dataValidade = '';
            }

            // Validar produto existente
            $stmtProduto = $this->connection->prepare('SELECT id FROM produtos WHERE id = :id LIMIT 1');
            $stmtProduto->execute([':id' => $produtoId]);
            if (!$stmtProduto->fetchColumn()) {
                $this->setFlash('Produto não encontrado.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }

            $this->connection->beginTransaction();

            $stmtEstoque = $this->connection->prepare('
                INSERT INTO estoque_interno (
                    produto_id,
                    quantidade,
                    data_compra,
                    data_validade,
                    is_alimenticio,
                    galpao,
                    prateleira,
                    observacao
                ) VALUES (
                    :produto_id,
                    :quantidade,
                    :data_compra,
                    :data_validade,
                    :is_alimenticio,
                    :galpao,
                    :prateleira,
                    :observacao
                )
            ');

            $stmtEstoque->execute([
                ':produto_id' => $produtoId,
                ':quantidade' => $quantidade,
                ':data_compra' => ($dataCompra !== '' ? $dataCompra : null),
                ':data_validade' => ($dataValidade !== '' ? $dataValidade : null),
                ':is_alimenticio' => $isAlimenticio,
                ':galpao' => ($galpao !== '' ? $galpao : null),
                ':prateleira' => ($prateleira !== '' ? $prateleira : null),
                ':observacao' => ($observacao !== '' ? $observacao : null),
            ]);

            // Registrar movimentação (entrada)
            $stmtMov = $this->connection->prepare('
                INSERT INTO estoque_movimentacao (
                    produto_id,
                    tipo_movimentacao,
                    quantidade,
                    quantidade_anterior,
                    quantidade_nova,
                    motivo,
                    usuario_id
                ) VALUES (
                    :produto_id,
                    :tipo_movimentacao,
                    :quantidade,
                    :quantidade_anterior,
                    :quantidade_nova,
                    :motivo,
                    :usuario_id
                )
            ');

            $stmtAtual = $this->connection->prepare('SELECT COALESCE(SUM(quantidade),0) as total FROM estoque_interno WHERE produto_id = :produto_id');
            $stmtAtual->execute([':produto_id' => $produtoId]);
            $atual = (int) ($stmtAtual->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
            $anterior = $atual - $quantidade;

            $usuarioId = null;
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            if (!empty($_SESSION['user_id'])) {
                $usuarioId = (int) $_SESSION['user_id'];
            } elseif (!empty($_SESSION['usuario_id'])) {
                $usuarioId = (int) $_SESSION['usuario_id'];
            }

            $motivo = 'Entrada de estoque (galpão)';
            if ($galpao !== '' || $prateleira !== '') {
                $motivo .= ' - ' . trim($galpao . ' - Prateleira ' . $prateleira);
            }

            $stmtMov->execute([
                ':produto_id' => $produtoId,
                ':tipo_movimentacao' => 'entrada',
                ':quantidade' => $quantidade,
                ':quantidade_anterior' => $anterior,
                ':quantidade_nova' => $atual,
                ':motivo' => $motivo,
                ':usuario_id' => $usuarioId,
            ]);

            $this->connection->commit();

            $this->setFlash('Entrada de estoque registrada com sucesso.', 'success');
            header('Location: /admin/estoque');
            exit;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            $this->setFlash('Erro ao registrar entrada de estoque.', 'danger');
            header('Location: /admin/estoque');
            exit;
        }
    }

    public function marcarComprado($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
    }
}
