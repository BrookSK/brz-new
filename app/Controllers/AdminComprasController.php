<?php
namespace App\Controllers;

class AdminComprasController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function pedidosItem($request) {
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);
        $semLoja = (string) $request->getParam('sem_loja', '0') === '1';

        header('Content-Type: application/json; charset=utf-8');

        if ($produtoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            return;
        }

        try {
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

            if (!$temPedidoEmLista) {
                echo json_encode(['success' => true, 'pedidos' => []]);
                return;
            }

            // Detectar tabela de itens do pedido
            $itensTable = null;
            try {
                $stmtT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $stmtT->execute(['pedido_itens']);
                if ((int) $stmtT->fetchColumn() > 0) {
                    $itensTable = 'pedido_itens';
                } else {
                    $stmtT->execute(['pedido_items']);
                    if ((int) $stmtT->fetchColumn() > 0) {
                        $itensTable = 'pedido_items';
                    }
                }
            } catch (\Exception $e) {
                $itensTable = null;
            }

            $whereLoja = '';
            $params = [':produto_id' => $produtoId];
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $whereLoja = ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                } elseif ($lojaId > 0) {
                    $whereLoja = ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                }
            }

            $stmt = $this->connection->prepare(
                "SELECT DISTINCT lc.pedido_id
                 FROM lista_compras lc
                 WHERE lc.produto_id = :produto_id
                   AND lc.pedido_id IS NOT NULL
                   AND lc.pedido_id <> 0" . $whereLoja .
                " ORDER BY lc.pedido_id DESC"
            );
            $stmt->execute($params);
            $pedidoIds = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

            if (empty($pedidoIds)) {
                echo json_encode(['success' => true, 'pedidos' => []]);
                return;
            }

            $in = implode(',', array_fill(0, count($pedidoIds), '?'));
            $stmtPedidos = $this->connection->prepare(
                "SELECT p.*, u.name as cliente_nome, u.email as cliente_email
                 FROM pedidos p
                 LEFT JOIN usuarios u ON u.id = p.usuario_id
                 WHERE p.id IN ($in)"
            );
            $stmtPedidos->execute(array_map('intval', $pedidoIds));
            $pedidosRows = $stmtPedidos->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $pedidos = [];
            foreach ($pedidosRows as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $pedidos[$pid] = [
                    'id' => $pid,
                    'codigo_pedido' => (string) ($p['codigo_pedido'] ?? ''),
                    'status' => (string) ($p['status'] ?? ''),
                    'valor_total' => isset($p['valor_total']) ? (float) $p['valor_total'] : null,
                    'moeda' => (string) ($p['moeda'] ?? ''),
                    'created_at' => (string) ($p['created_at'] ?? ''),
                    'pago_em' => isset($p['pago_em']) ? (string) $p['pago_em'] : '',
                    'cliente_nome' => (string) ($p['cliente_nome'] ?? ''),
                    'cliente_email' => (string) ($p['cliente_email'] ?? ''),
                    'itens' => [],
                ];
            }

            if ($itensTable) {
                $stmtItens = $this->connection->prepare(
                    "SELECT i.*
                     FROM $itensTable i
                     WHERE i.pedido_id IN ($in) AND i.produto_id = ?"
                );
                $vals = array_map('intval', $pedidoIds);
                $vals[] = $produtoId;
                $stmtItens->execute($vals);
                $itens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($itens as $it) {
                    $pid = (int) ($it['pedido_id'] ?? 0);
                    if (!isset($pedidos[$pid])) continue;
                    $pedidos[$pid]['itens'][] = [
                        'produto_id' => (int) ($it['produto_id'] ?? 0),
                        'quantidade' => (int) ($it['quantidade'] ?? 0),
                        'preco_unitario' => isset($it['preco_unitario']) ? (float) $it['preco_unitario'] : null,
                        'subtotal' => isset($it['subtotal']) ? (float) $it['subtotal'] : null,
                        'nome_produto' => (string) ($it['nome_produto'] ?? ''),
                        'nome_produto_sku' => (string) ($it['nome_produto_sku'] ?? ''),
                    ];
                }
            }

            echo json_encode(['success' => true, 'pedidos' => array_values($pedidos)]);
            return;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar pedidos.']);
            return;
        }
    }

    public function reabrirCompras($request) {
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);
        $semLoja = (string) $request->getParam('sem_loja', '0') === '1';
        $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

        try {
            $sql = "UPDATE lista_compras lc SET lc.status = 'pendente' WHERE lc.status IN ('comprado','cancelado')";
            $params = [];
            if ($produtoId > 0) {
                $sql .= ' AND lc.produto_id = :produto_id';
                $params[':produto_id'] = $produtoId;
            }
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $sql .= ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                } elseif ($lojaId > 0) {
                    $sql .= ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                }
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $_SESSION['message'] = 'Itens reabertos.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/estoque/compras?status=pendente' . ($semLoja ? '&sem_loja=1' : ($lojaId > 0 ? ('&loja_id=' . $lojaId) : '')));
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao reabrir itens.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras?status=concluidas');
            exit;
        }
    }

    public function removerItem($request) {
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);

        if ($produtoId <= 0) {
            $_SESSION['message'] = 'Parâmetros inválidos.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }

        try {
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

            $whereLoja = '';
            $params = [':produto_id' => $produtoId];
            if ($temLojaIdEmLista) {
                if ($lojaId > 0) {
                    $whereLoja = ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                } else {
                    $whereLoja = ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                }
            }

            $stmt = $this->connection->prepare("UPDATE lista_compras lc SET lc.status = 'cancelado' WHERE lc.status = 'pendente' AND lc.produto_id = :produto_id" . $whereLoja);
            $stmt->execute($params);

            $_SESSION['message'] = 'Item removido da lista.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/estoque/compras');
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao remover item.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool {
        try {
            $stmt = $this->connection->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
            $stmt->execute([$column]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function fetchLojas(): array {
        try {
            if (!$this->tableExists('lojas')) {
                return [];
            }
            $stmt = $this->connection->query('SELECT id, nome, slug, ativo FROM lojas WHERE ativo = 1 ORDER BY nome ASC');
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            return $rows ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function resolveProdutoImagem(array $produto): ?string {
        $candidates = ['foto_principal', 'imagem', 'image', 'thumb', 'thumbnail', 'imagem_raw', 'images'];
        foreach ($candidates as $c) {
            if (!isset($produto[$c]) || $produto[$c] === null || $produto[$c] === '') {
                continue;
            }
            $raw = $produto[$c];
            if (is_string($raw)) {
                $s = trim($raw);
                if ($s === '') continue;
                // JSON de imagens
                if ($s[0] === '{' || $s[0] === '[') {
                    $decoded = json_decode($s, true);
                    if (is_array($decoded)) {
                        $paths = [];
                        if (isset($decoded['principal'])) $paths[] = $decoded['principal'];
                        if (isset($decoded['capa'])) $paths[] = $decoded['capa'];
                        if (isset($decoded['url'])) $paths[] = $decoded['url'];
                        if (isset($decoded[0])) $paths[] = $decoded[0];
                        foreach ($paths as $p) {
                            if (is_string($p) && trim($p) !== '') return $p;
                            if (is_array($p) && !empty($p['url']) && is_string($p['url'])) return $p['url'];
                            if (is_array($p) && !empty($p['path']) && is_string($p['path'])) return $p['path'];
                        }
                    }
                }
                return $s;
            }
        }
        return null;
    }

    public function index($request) {
        try {
            $lojas = $this->fetchLojas();
            $lojaIdFilter = (int) $request->getParam('loja_id', 0);
            $semLoja = (string) $request->getParam('sem_loja', '0') === '1';
            $statusView = (string) $request->getParam('status', 'pendente');
            $statusView = in_array($statusView, ['pendente', 'concluidas'], true) ? $statusView : 'pendente';

            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');
            $temLojaIdEmProdutos = $this->columnExists('produtos', 'loja_id');
            $temCost = $this->columnExists('produtos', 'cost_price');
            $temFoto = $this->columnExists('produtos', 'foto_principal');
            $temImages = $this->columnExists('produtos', 'images');
            $temLojaText = $this->columnExists('produtos', 'loja');

            $selectCols = [
                'p.id as produto_id',
                'p.sku as sku',
            ];
            if ($this->columnExists('produtos', 'name')) {
                $selectCols[] = 'p.name as produto_nome';
            } elseif ($this->columnExists('produtos', 'nome')) {
                $selectCols[] = 'p.nome as produto_nome';
            } else {
                $selectCols[] = "'' as produto_nome";
            }
            if ($temCost) $selectCols[] = 'p.cost_price as cost_price';
            if ($this->columnExists('produtos', 'price')) $selectCols[] = 'p.price as price';
            if ($temLojaIdEmProdutos) $selectCols[] = 'p.loja_id as produto_loja_id';
            if ($temLojaText) $selectCols[] = 'p.loja as produto_loja';
            if ($temFoto) $selectCols[] = 'p.foto_principal as foto_principal';
            if ($temImages) $selectCols[] = 'p.images as images';

            // Consolidar por produto + loja (para não repetir linhas)
            $rankExpr = "CASE lc.prioridade WHEN 'urgente' THEN 4 WHEN 'alta' THEN 3 WHEN 'media' THEN 2 WHEN 'baixa' THEN 1 ELSE 0 END";
            $sql = 'SELECT ' . implode(', ', $selectCols)
                . ', agg.quantidade_faltante as quantidade_faltante'
                . ', agg.quantidade_necessaria as quantidade_necessaria'
                . ', agg.prioridade as prioridade'
                . ', agg.data_solicitacao as data_solicitacao'
                . ', agg.loja_id as loja_id'
                . ', agg.status as status'
                . ' FROM ('
                . '   SELECT produto_id, '
                . ($temLojaIdEmLista ? 'COALESCE(loja_id,0) as loja_id' : '0 as loja_id')
                . '     , status as status'
                . '     , SUM(COALESCE(quantidade_faltante,0)) as quantidade_faltante'
                . '     , SUM(COALESCE(quantidade_necessaria,0)) as quantidade_necessaria'
                . '     , MIN(COALESCE(data_solicitacao, CURDATE())) as data_solicitacao'
                . '     , CASE MAX(' . $rankExpr . ") WHEN 4 THEN 'urgente' WHEN 3 THEN 'alta' WHEN 2 THEN 'media' WHEN 1 THEN 'baixa' ELSE 'media' END as prioridade"
                . '   FROM lista_compras lc WHERE '
                . ($statusView === 'concluidas' ? "lc.status IN ('comprado','cancelado')" : "lc.status = 'pendente'")
                . '   GROUP BY produto_id, '
                . ($temLojaIdEmLista ? 'COALESCE(loja_id,0), status' : '0, status')
                . ' ) agg'
                . ' JOIN produtos p ON agg.produto_id = p.id';

            $params = [];
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $sql .= ' WHERE agg.loja_id = 0';
                } elseif ($lojaIdFilter > 0) {
                    $sql .= ' WHERE agg.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaIdFilter;
                }
            }
            $sql .= ' ORDER BY agg.prioridade DESC, agg.data_solicitacao ASC';

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $compras = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Estatísticas: itens/valor pendente
            $totalItensPendentes = 0;
            $valorTotalPendente = 0.0;
            foreach ($compras as $c) {
                $qf = (int) ($c['quantidade_faltante'] ?? $c['quantidade_necessaria'] ?? 0);
                $totalItensPendentes += $qf;
                $cost = isset($c['cost_price']) ? (float) $c['cost_price'] : 0.0;
                $valorTotalPendente += ($qf * $cost);
            }

            // Contadores gerais
            $stmt = $this->connection->prepare("SELECT COUNT(*) as total_itens, SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes, SUM(CASE WHEN status = 'comprado' THEN 1 ELSE 0 END) as comprados, SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados FROM lista_compras");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $compras = [];
            $estatisticas = ['total_itens' => 0, 'pendentes' => 0, 'comprados' => 0, 'cancelados' => 0];
            $lojas = [];
            $lojaIdFilter = 0;
            $semLoja = false;
            $statusView = 'pendente';
            $totalItensPendentes = 0;
            $valorTotalPendente = 0.0;
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Compras - Braziliana Shop Admin</title>
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
                        <button type="button" class="btn btn-success me-2" disabled>
                            <i class="fas fa-plus me-1"></i>Novo Item
                        </button>
                        <button type="button" class="btn btn-primary me-2" onclick="window.open(\'/admin/estoque/compras/pdf\', \'_blank\')">
                            <i class="fas fa-file-pdf me-1"></i>Gerar PDF
                        </button>
                        ';

        if ($statusView === 'pendente') {
            echo '<form method="POST" action="/admin/estoque/compras/concluir" class="d-inline">
                            <input type="hidden" name="loja_id" value="' . (int) $lojaIdFilter . '">
                            <input type="hidden" name="sem_loja" value="' . ($semLoja ? '1' : '0') . '">
                            <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#modalConcluirCompras">
                                <i class="fas fa-check-double me-1"></i>Concluir compras
                            </button>
                        </form>';
        } else {
            echo '<form method="POST" action="/admin/estoque/compras/reabrir" class="d-inline">
                            <input type="hidden" name="loja_id" value="' . (int) $lojaIdFilter . '">
                            <input type="hidden" name="sem_loja" value="' . ($semLoja ? '1' : '0') . '">
                            <button type="button" class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#modalReabrirCompras">
                                <i class="fas fa-rotate-left me-1"></i>Reabrir itens
                            </button>
                        </form>';
        }

        echo '
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>';

                echo '<div class="d-flex flex-wrap gap-2 mb-2">'
                    . '<a class="btn btn-sm ' . ($statusView === 'pendente' ? 'btn-primary' : 'btn-outline-primary') . '" href="/admin/estoque/compras?status=pendente">Pendentes</a>'
                    . '<a class="btn btn-sm ' . ($statusView === 'concluidas' ? 'btn-secondary' : 'btn-outline-secondary') . '" href="/admin/estoque/compras?status=concluidas">Concluídas</a>'
                    . '</div>';

                echo '<div class="card mb-4">
                    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <a class="btn btn-sm ' . (!$semLoja && $lojaIdFilter === 0 ? 'btn-primary' : 'btn-outline-primary') . '" href="/admin/estoque/compras?status=' . $statusView . '">Todas</a>';

                foreach ($lojas as $l) {
                    $lid = (int) ($l['id'] ?? 0);
                    $lname = (string) ($l['nome'] ?? '');
                    $active = (!$semLoja && $lojaIdFilter === $lid);
                    echo '<a class="btn btn-sm ' . ($active ? 'btn-primary' : 'btn-outline-primary') . '" href="/admin/estoque/compras?status=' . $statusView . '&loja_id=' . $lid . '">' . htmlspecialchars($lname) . '</a>';
                }

                echo '<a class="btn btn-sm ' . ($semLoja ? 'btn-danger' : 'btn-outline-danger') . '" href="/admin/estoque/compras?status=' . $statusView . '&sem_loja=1">Sem loja</a>'
                    . '</div>'
                    . '<div class="text-end">'
                    . '<div><strong>Total ' . ($statusView === 'pendente' ? 'pendente' : 'concluído') . ' (itens):</strong> ' . number_format($totalItensPendentes) . '</div>'
                    . '<div><strong>Valor total ' . ($statusView === 'pendente' ? 'pendente' : 'concluído') . ':</strong> $ ' . number_format($valorTotalPendente, 2, '.', ',') . '</div>'
                    . '</div>'
                    . '</div>'
                    . '</div>';

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
                        <div class="d-flex justify-content-end gap-2 mb-3">';

                if ($semLoja) {
                    echo '<button type="button" class="btn btn-sm btn-outline-danger" disabled>PDF (Sem loja)</button>';
                } elseif ($lojaIdFilter > 0) {
                    echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="window.open(\'/admin/estoque/compras/pdf?loja_id=' . (int) $lojaIdFilter . '\', \'_blank\')"><i class="fas fa-file-pdf me-1"></i>PDF desta loja</button>';
                } else {
                    echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="window.open(\'/admin/estoque/compras/pdf\', \'_blank\')"><i class="fas fa-file-pdf me-1"></i>PDF (geral)</button>';
                }

                echo '        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>SKU</th>
                                        <th>Loja</th>
                                        <th>Quantidade</th>
                                        <th>Custo</th>
                                        <th>Total</th>
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

                                    $qf = (int) ($item['quantidade_faltante'] ?? $item['quantidade_necessaria'] ?? 0);
                                    $cost = isset($item['cost_price']) ? (float) $item['cost_price'] : 0.0;
                                    $rowTotal = $qf * $cost;

                                    $imgUrl = $this->resolveProdutoImagem($item);
                                    $imgTag = $imgUrl
                                        ? '<img src="' . htmlspecialchars($imgUrl) . '" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:10px; border: 1px solid rgba(148, 163, 184, 0.22); background: rgba(148, 163, 184, 0.06);">'
                                        : '<div style="width:36px;height:36px;border-radius:10px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>';

                                    $lojaNome = '-';
                                    $lojaIdRow = (int) ($item['loja_id'] ?? ($item['produto_loja_id'] ?? 0));
                                    if ($lojaIdRow > 0 && $this->tableExists('lojas')) {
                                        try {
                                            $stmtLn = $this->connection->prepare('SELECT nome FROM lojas WHERE id = :id LIMIT 1');
                                            $stmtLn->execute([':id' => $lojaIdRow]);
                                            $ln = $stmtLn->fetchColumn();
                                            if ($ln !== false && (string) $ln !== '') $lojaNome = (string) $ln;
                                        } catch (\Exception $e) {
                                        }
                                    }

                                    $missingLoja = ($lojaIdRow <= 0);
                                     
                                    $btnEditItem = '<button type="button" class="btn btn-outline-primary"'
                                        . ' data-bs-toggle="modal" data-bs-target="#modalEditarItem"'
                                        . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                        . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                        . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                        . ' data-quantidade="' . (int) $qf . '"'
                                        . ' data-prioridade="' . htmlspecialchars((string) ($item['prioridade'] ?? 'media')) . '"'
                                        . '><i class="fas fa-pen"></i></button>';
                                    $btnRemoverItem = '<button type="button" class="btn btn-outline-danger"'
                                        . ' data-bs-toggle="modal" data-bs-target="#modalRemoverItem"'
                                        . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                        . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                        . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                        . '><i class="fas fa-trash"></i></button>';
                                    $btnVerPedidos = '<button type="button" class="btn btn-outline-dark"'
                                        . ' data-bs-toggle="modal" data-bs-target="#modalPedidosItem"'
                                        . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                        . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                        . ' data-sem-loja="' . ($missingLoja ? '1' : '0') . '"'
                                        . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                        . '><i class="fas fa-eye"></i></button>';
                                    $btnReabrirItem = '<button type="button" class="btn btn-outline-secondary"'
                                        . ' data-bs-toggle="modal" data-bs-target="#modalReabrirItem"'
                                        . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                        . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                        . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                        . '><i class="fas fa-rotate-left"></i></button>';
                                    $btnLoja = $missingLoja
                                        ? '<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalLoja" data-produto-id="' . (int) $item['produto_id'] . '" data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"><i class="fas fa-store"></i></button>'
                                        : '';

                                    echo '<tr>'
                                        . '<td>'
                                        . '<div class="d-flex gap-2 align-items-center">' . $imgTag . '<div>'
                                        . '<strong>' . htmlspecialchars($item['produto_nome']) . '</strong>'
                                        . '<br><small class="text-muted">ID: ' . (int) $item['produto_id'] . '</small>'
                                        . '</div></div>'
                                        . '</td>'
                                        . '<td>' . htmlspecialchars((string) ($item['sku'] ?? '')) . '</td>'
                                        . '<td>' . (!$missingLoja ? htmlspecialchars($lojaNome) : '<span class="badge bg-danger">Sem loja</span>') . '</td>'
                                        . '<td><span class="badge bg-primary">' . $qf . '</span></td>'
                                        . '<td>$ ' . number_format($cost, 2, '.', ',') . '</td>'
                                        . '<td>$ ' . number_format($rowTotal, 2, '.', ',') . '</td>'
                                        . '<td><span class="badge bg-' . $status_class . '">' . ucfirst((string) $item['status']) . '</span></td>'
                                        . '<td><span class="badge bg-' . $prioridade_class . '">' . ucfirst((string) $item['prioridade']) . '</span></td>'
                                        . '<td>' . (!empty($item['data_solicitacao']) ? date('d/m/Y', strtotime((string) $item['data_solicitacao'])) : '-') . '</td>'
                                        . '<td>'
                                        . '<div class="btn-group btn-group-sm">'
                                        . $btnVerPedidos
                                        . ($statusView === 'pendente' ? $btnEditItem : '')
                                        . ($statusView === 'pendente' ? $btnLoja : '')
                                        . ($statusView === 'pendente'
                                            ? ('<button type="button" class="btn btn-outline-success"'
                                                . ' data-bs-toggle="modal" data-bs-target="#modalConcluirItem"'
                                                . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                                . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                                . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                                . '><i class="fas fa-check"></i></button>')
                                            : $btnReabrirItem)
                                        . ($statusView === 'pendente' ? $btnRemoverItem : '')
                                        . '</div>'
                                        . '</td>'
                                        . '</tr>';
                                }
                                
                                echo '</tbody>
                            </table>
                        </div>
                    </div>
                </div>';

                // Modal: Definir loja
                echo '<div class="modal fade" id="modalLoja" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/definir-loja">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Definir loja do produto</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="modal_produto_id" value="">
                                        <div class="mb-2 text-muted" id="modal_produto_nome"></div>
                                        <label class="form-label">Loja *</label>
                                        <select class="form-select" name="loja_id" required>
                                            <option value="">Selecione...</option>';
                foreach ($lojas as $l) {
                    echo '<option value="' . (int) ($l['id'] ?? 0) . '">' . htmlspecialchars((string) ($l['nome'] ?? '')) . '</option>';
                }
                echo '            </select>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Salvar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                // Modal: Pedidos do item
                echo '<div class="modal fade" id="modalPedidosItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Pedidos relacionados</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-2 text-muted" id="pedidos_produto_nome"></div>
                                    <div id="pedidos_loading" class="text-muted">Carregando...</div>
                                    <div id="pedidos_empty" class="alert alert-warning d-none">Nenhum pedido encontrado para este item.</div>
                                    <div class="accordion" id="accordionPedidos"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    function escapeHtml(str){
                        if (str === null || str === undefined) return "";
                        return String(str)
                            .replace(/&/g, "&amp;")
                            .replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;")
                            .replace(/\"/g, "&quot;")
                            .replace(/\'/g, "&#039;");
                    }

                    function formatMoney(v){
                        if (v === null || v === undefined || v === "") return "-";
                        var n = Number(v);
                        if (isNaN(n)) return String(v);
                        return "$ " + n.toFixed(2);
                    }

                    function renderPedidosAccordion(pedidos){
                        var acc = document.getElementById("accordionPedidos");
                        if (!acc) return;
                        acc.innerHTML = "";

                        pedidos.forEach(function(p, idx){
                            var pid = p.id || 0;
                            var headId = "pedidoHead_" + pid;
                            var bodyId = "pedidoBody_" + pid;
                            var total = (p.valor_total !== null && p.valor_total !== undefined) ? formatMoney(p.valor_total) : "-";
                            var cliente = (p.cliente_nome || "") + (p.cliente_email ? (" - " + p.cliente_email) : "");
                            var criado = p.created_at ? escapeHtml(p.created_at) : "";
                            var pagoEm = p.pago_em ? escapeHtml(p.pago_em) : "";
                            var status = p.status ? escapeHtml(p.status) : "";
                            var codigo = p.codigo_pedido ? escapeHtml(p.codigo_pedido) : "";

                            var itensHtml = "";
                            if (Array.isArray(p.itens) && p.itens.length > 0) {
                                itensHtml += "<div class=\"table-responsive\"><table class=\"table table-sm\">";
                                itensHtml += "<thead><tr><th>Produto</th><th style=\"width:90px;\">Qtd</th><th style=\"width:120px;\">Preço</th><th style=\"width:120px;\">Subtotal</th></tr></thead><tbody>";
                                p.itens.forEach(function(it){
                                    itensHtml += "<tr>";
                                    itensHtml += "<td>" + escapeHtml(it.nome_produto || it.nome_produto_sku || ("Produto ID: " + (it.produto_id||""))) + "</td>";
                                    itensHtml += "<td>" + escapeHtml(it.quantidade || 0) + "</td>";
                                    itensHtml += "<td>" + formatMoney(it.preco_unitario) + "</td>";
                                    itensHtml += "<td>" + formatMoney(it.subtotal) + "</td>";
                                    itensHtml += "</tr>";
                                });
                                itensHtml += "</tbody></table></div>";
                            } else {
                                itensHtml = "<div class=\"text-muted\">Itens do pedido não disponíveis.</div>";
                            }

                            var html = "";
                            html += "<div class=\"accordion-item\">";
                            html += "<h2 class=\"accordion-header\" id=\"" + headId + "\">";
                            html += "<button class=\"accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#" + bodyId + "\">";
                            html += "Pedido #" + pid + (codigo ? (" (" + codigo + ")") : "") + " - " + status + " - " + total;
                            html += "</button></h2>";
                            html += "<div id=\"" + bodyId + "\" class=\"accordion-collapse collapse\" data-bs-parent=\"#accordionPedidos\">";
                            html += "<div class=\"accordion-body\">";
                            html += "<div class=\"mb-2\">";
                            html += "<div><strong>Cliente:</strong> " + escapeHtml(cliente) + "</div>";
                            html += "<div><strong>Criado em:</strong> " + criado + "</div>";
                            if (pagoEm) html += "<div><strong>Pago em:</strong> " + pagoEm + "</div>";
                            html += "<div class=\"mt-2\"><a class=\"btn btn-sm btn-outline-primary\" href=\"/admin/pedidos/detalhes/" + pid + "\" target=\"_blank\">Abrir pedido</a></div>";
                            html += "</div>";
                            html += itensHtml;
                            html += "</div></div></div>";

                            acc.insertAdjacentHTML("beforeend", html);
                        });
                    }

                    var modalPedidosItem = document.getElementById("modalPedidosItem");
                    if (modalPedidosItem) {
                        modalPedidosItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            var produtoId = button.getAttribute("data-produto-id") || "";
                            var lojaId = button.getAttribute("data-loja-id") || "0";
                            var semLoja = button.getAttribute("data-sem-loja") || "0";
                            var produtoNome = button.getAttribute("data-produto-nome") || "";

                            var label = document.getElementById("pedidos_produto_nome");
                            if (label) label.textContent = produtoNome;

                            var loading = document.getElementById("pedidos_loading");
                            var empty = document.getElementById("pedidos_empty");
                            var acc = document.getElementById("accordionPedidos");
                            if (loading) loading.classList.remove("d-none");
                            if (empty) empty.classList.add("d-none");
                            if (acc) acc.innerHTML = "";

                            var url = "/admin/estoque/compras/pedidos?produto_id=" + encodeURIComponent(produtoId)
                                + "&loja_id=" + encodeURIComponent(lojaId)
                                + "&sem_loja=" + encodeURIComponent(semLoja);

                            fetch(url, { headers: { "Accept": "application/json" } })
                                .then(function(r){ return r.json(); })
                                .then(function(data){
                                    if (loading) loading.classList.add("d-none");
                                    if (!data || !data.success) {
                                        if (empty) {
                                            empty.classList.remove("d-none");
                                            empty.textContent = (data && data.message) ? data.message : "Erro ao buscar pedidos.";
                                        }
                                        return;
                                    }
                                    var pedidos = data.pedidos || [];
                                    if (!pedidos.length) {
                                        if (empty) empty.classList.remove("d-none");
                                        return;
                                    }
                                    renderPedidosAccordion(pedidos);
                                })
                                .catch(function(){
                                    if (loading) loading.classList.add("d-none");
                                    if (empty) {
                                        empty.classList.remove("d-none");
                                        empty.textContent = "Erro ao buscar pedidos.";
                                    }
                                });
                        });
                    }
                </script>';

                // Modal: Reabrir compras (tudo do filtro)
                echo '<div class="modal fade" id="modalReabrirCompras" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/reabrir">
                                    <input type="hidden" name="loja_id" value="' . (int) $lojaIdFilter . '">
                                    <input type="hidden" name="sem_loja" value="' . ($semLoja ? '1' : '0') . '">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reabrir itens</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-secondary mb-0">
                                            Deseja voltar para <strong>pendente</strong> todos os itens concluídos deste filtro?
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Reabrir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalLoja = document.getElementById("modalLoja");
                    if (modalLoja) {
                        modalLoja.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            var produtoId = button.getAttribute("data-produto-id");
                            var produtoNome = button.getAttribute("data-produto-nome");
                            var input = document.getElementById("modal_produto_id");
                            var label = document.getElementById("modal_produto_nome");
                            if (input) input.value = produtoId;
                            if (label) label.textContent = produtoNome;
                        });
                    }
                </script>';

                // Modal: Editar item
                echo '<div class="modal fade" id="modalEditarItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/editar-item">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar item da lista</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="edit_produto_id" value="">
                                        <input type="hidden" name="loja_id" id="edit_loja_id" value="0">
                                        <div class="mb-2 text-muted" id="edit_produto_nome"></div>
                                        <label class="form-label">Quantidade *</label>
                                        <input type="number" class="form-control" name="quantidade" id="edit_quantidade" min="0" required>
                                        <label class="form-label mt-3">Prioridade *</label>
                                        <select class="form-select" name="prioridade" id="edit_prioridade" required>
                                            <option value="baixa">Baixa</option>
                                            <option value="media">Média</option>
                                            <option value="alta">Alta</option>
                                            <option value="urgente">Urgente</option>
                                        </select>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Salvar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalEditarItem = document.getElementById("modalEditarItem");
                    if (modalEditarItem) {
                        modalEditarItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            document.getElementById("edit_produto_id").value = button.getAttribute("data-produto-id") || "";
                            document.getElementById("edit_loja_id").value = button.getAttribute("data-loja-id") || "0";
                            document.getElementById("edit_produto_nome").textContent = button.getAttribute("data-produto-nome") || "";
                            document.getElementById("edit_quantidade").value = button.getAttribute("data-quantidade") || "0";
                            document.getElementById("edit_prioridade").value = button.getAttribute("data-prioridade") || "media";
                        });
                    }
                </script>';

                // Modal: Remover item (cancelar pendência na lista)
                echo '<div class="modal fade" id="modalRemoverItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/remover-item">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Remover item da lista</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="remover_produto_id" value="">
                                        <input type="hidden" name="loja_id" id="remover_loja_id" value="0">
                                        <div class="alert alert-warning mb-0">
                                            Tem certeza que deseja remover da lista o item <strong id="remover_produto_nome"></strong>?
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-danger">Remover</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalRemoverItem = document.getElementById("modalRemoverItem");
                    if (modalRemoverItem) {
                        modalRemoverItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            document.getElementById("remover_produto_id").value = button.getAttribute("data-produto-id") || "";
                            document.getElementById("remover_loja_id").value = button.getAttribute("data-loja-id") || "0";
                            document.getElementById("remover_produto_nome").textContent = button.getAttribute("data-produto-nome") || "";
                        });
                    }
                </script>';

                // Modal: Reabrir item
                echo '<div class="modal fade" id="modalReabrirItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/reabrir">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reabrir item</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="reabrir_produto_id" value="">
                                        <input type="hidden" name="loja_id" id="reabrir_loja_id" value="0">
                                        <div class="alert alert-secondary mb-0">
                                            Voltar para <strong>pendente</strong>: <strong id="reabrir_produto_nome"></strong>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Reabrir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalReabrirItem = document.getElementById("modalReabrirItem");
                    if (modalReabrirItem) {
                        modalReabrirItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            document.getElementById("reabrir_produto_id").value = button.getAttribute("data-produto-id") || "";
                            document.getElementById("reabrir_loja_id").value = button.getAttribute("data-loja-id") || "0";
                            document.getElementById("reabrir_produto_nome").textContent = button.getAttribute("data-produto-nome") || "";
                        });
                    }
                </script>';

                // Modal: Concluir item
                echo '<div class="modal fade" id="modalConcluirItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/concluir">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Concluir item</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="concluir_produto_id" value="">
                                        <input type="hidden" name="loja_id" id="concluir_loja_id" value="0">
                                        <div class="alert alert-success mb-0">
                                            Marcar como comprado: <strong id="concluir_produto_nome"></strong>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-success">Concluir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalConcluirItem = document.getElementById("modalConcluirItem");
                    if (modalConcluirItem) {
                        modalConcluirItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            document.getElementById("concluir_produto_id").value = button.getAttribute("data-produto-id") || "";
                            document.getElementById("concluir_loja_id").value = button.getAttribute("data-loja-id") || "0";
                            document.getElementById("concluir_produto_nome").textContent = button.getAttribute("data-produto-nome") || "";
                        });
                    }
                </script>';

                // Modal: Concluir compras (tudo do filtro)
                echo '<div class="modal fade" id="modalConcluirCompras" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/concluir">
                                    <input type="hidden" name="loja_id" value="' . (int) $lojaIdFilter . '">
                                    <input type="hidden" name="sem_loja" value="' . ($semLoja ? '1' : '0') . '">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Concluir compras</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-success mb-0">
                                            Deseja marcar como <strong>comprado</strong> todos os itens pendentes deste filtro?
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-success">Concluir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '</main>
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

    public function editarItem($request) {
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);
        $quantidade = (int) $request->getParam('quantidade', 0);
        $prioridade = (string) $request->getParam('prioridade', 'media');
        $prioridade = in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'], true) ? $prioridade : 'media';

        if ($produtoId <= 0) {
            $_SESSION['message'] = 'Parâmetros inválidos.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }

        try {
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

            $this->connection->beginTransaction();
            $whereLoja = '';
            $params = [':produto_id' => $produtoId];
            if ($temLojaIdEmLista) {
                if ($lojaId > 0) {
                    $whereLoja = ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                } else {
                    $whereLoja = ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                }
            }

            $stmt = $this->connection->prepare("UPDATE lista_compras lc SET lc.status = 'cancelado' WHERE lc.status = 'pendente' AND lc.produto_id = :produto_id" . $whereLoja);
            $stmt->execute($params);

            $cols = ['produto_id', 'quantidade_necessaria', 'quantidade_faltante', 'prioridade', 'status', 'data_solicitacao'];
            $vals = [':produto_id', ':q', ':q', ':prioridade', "'pendente'", 'CURDATE()'];
            $insertParams = [':produto_id' => $produtoId, ':q' => $quantidade, ':prioridade' => $prioridade];
            if ($temLojaIdEmLista) {
                $cols[] = 'loja_id';
                if ($lojaId > 0) {
                    $vals[] = ':ins_loja_id';
                    $insertParams[':ins_loja_id'] = $lojaId;
                } else {
                    $vals[] = 'NULL';
                }
            }

            $stmt = $this->connection->prepare('INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
            $stmt->execute($insertParams);

            $this->connection->commit();
            $_SESSION['message'] = 'Item atualizado.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/estoque/compras');
            exit;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            $_SESSION['message'] = 'Erro ao atualizar item.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }
    }

    public function mudarStatus($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
    }

    public function concluirCompras($request) {
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);
        $semLoja = (string) $request->getParam('sem_loja', '0') === '1';
        $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

        try {
            $sql = "UPDATE lista_compras lc SET lc.status = 'comprado' WHERE lc.status = 'pendente'";
            $params = [];
            if ($produtoId > 0) {
                $sql .= ' AND lc.produto_id = :produto_id';
                $params[':produto_id'] = $produtoId;
            }
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $sql .= ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                } elseif ($lojaId > 0) {
                    $sql .= ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                }
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $_SESSION['message'] = 'Compras concluídas.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/estoque/compras' . ($semLoja ? '?sem_loja=1' : ($lojaId > 0 ? ('?loja_id=' . $lojaId) : '')));
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao concluir compras.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }
    }

    public function gerarPDF($request) {
        $lojaId = (int) $request->getParam('loja_id', 0);
        $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');
        $temCost = $this->columnExists('produtos', 'cost_price');
        $temFoto = $this->columnExists('produtos', 'foto_principal');
        $temImages = $this->columnExists('produtos', 'images');

        $lojaNome = 'Compras';
        if ($lojaId > 0 && $this->tableExists('lojas')) {
            try {
                $stmt = $this->connection->prepare('SELECT nome FROM lojas WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $lojaId]);
                $n = $stmt->fetchColumn();
                if ($n !== false && (string) $n !== '') $lojaNome = (string) $n;
            } catch (\Exception $e) {
            }
        }

        $selectCols = [
            'p.id as produto_id',
            'p.sku as sku',
        ];
        if ($this->columnExists('produtos', 'name')) {
            $selectCols[] = 'p.name as produto_nome';
        } elseif ($this->columnExists('produtos', 'nome')) {
            $selectCols[] = 'p.nome as produto_nome';
        } else {
            $selectCols[] = "'' as produto_nome";
        }
        if ($temCost) $selectCols[] = 'p.cost_price as cost_price';
        if ($temFoto) $selectCols[] = 'p.foto_principal as foto_principal';
        if ($temImages) $selectCols[] = 'p.images as images';

        // PDF consolidado por produto + loja
        $rankExpr = "CASE lc.prioridade WHEN 'urgente' THEN 4 WHEN 'alta' THEN 3 WHEN 'media' THEN 2 WHEN 'baixa' THEN 1 ELSE 0 END";
        $sql = 'SELECT ' . implode(', ', $selectCols)
            . ', agg.quantidade_faltante as quantidade_faltante'
            . ', agg.quantidade_necessaria as quantidade_necessaria'
            . ', agg.data_solicitacao as data_solicitacao'
            . ', agg.loja_id as loja_id'
            . ', agg.prioridade as prioridade'
            . ", 'pendente' as status"
            . ' FROM ('
            . '   SELECT produto_id, '
            . ($temLojaIdEmLista ? 'COALESCE(loja_id,0) as loja_id' : '0 as loja_id')
            . '     , SUM(COALESCE(quantidade_faltante,0)) as quantidade_faltante'
            . '     , SUM(COALESCE(quantidade_necessaria,0)) as quantidade_necessaria'
            . '     , MIN(COALESCE(data_solicitacao, CURDATE())) as data_solicitacao'
            . '     , CASE MAX(' . $rankExpr . ") WHEN 4 THEN 'urgente' WHEN 3 THEN 'alta' WHEN 2 THEN 'media' WHEN 1 THEN 'baixa' ELSE 'media' END as prioridade"
            . "   FROM lista_compras lc WHERE lc.status = 'pendente'"
            . '   GROUP BY produto_id, '
            . ($temLojaIdEmLista ? 'COALESCE(loja_id,0)' : '0')
            . ' ) agg'
            . ' JOIN produtos p ON agg.produto_id = p.id';

        $params = [];
        if ($lojaId > 0 && $temLojaIdEmLista) {
            $sql .= ' WHERE agg.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }
        $sql .= ' ORDER BY agg.prioridade DESC, agg.data_solicitacao ASC';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $totalItens = 0;
        $totalValor = 0.0;
        foreach ($rows as $r) {
            $qf = (int) ($r['quantidade_faltante'] ?? $r['quantidade_necessaria'] ?? 0);
            $totalItens += $qf;
            $cost = isset($r['cost_price']) ? (float) $r['cost_price'] : 0.0;
            $totalValor += ($qf * $cost);
        }

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Lista de Compras - ' . htmlspecialchars($lojaNome) . '</title>'
            . '<style>
                body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:18px;}
                h1{font-size:18px;margin:0 0 8px 0;}
                .meta{font-size:12px;color:#444;margin-bottom:14px;}
                table{width:100%;border-collapse:collapse;}
                th,td{border:1px solid #ddd;padding:8px;vertical-align:middle;}
                th{background:#f6f6f6;text-align:left;font-size:12px;}
                td{font-size:12px;}
                .img{width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #ddd;background:#fafafa;}
                .check{width:18px;height:18px;border:1px solid #111;display:inline-block;}
                .totais{margin-top:12px;font-size:12px;}
                @media print{a{color:inherit;text-decoration:none;} .no-print{display:none;}}
            </style></head><body>';

        echo '<h1>Lista de Compras - ' . htmlspecialchars($lojaNome) . '</h1>'
            . '<div class="meta">Gerado em: ' . date('d/m/Y H:i') . '</div>';

        echo '<table><thead><tr>'
            . '<th style="width:34px;">OK</th>'
            . '<th style="width:60px;">Foto</th>'
            . '<th>Produto</th>'
            . '<th style="width:80px;">SKU</th>'
            . '<th style="width:70px;">Qtd</th>'
            . '<th style="width:80px;">Custo</th>'
            . '<th style="width:90px;">Total</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $qf = (int) ($r['quantidade_faltante'] ?? $r['quantidade_necessaria'] ?? 0);
            $cost = isset($r['cost_price']) ? (float) $r['cost_price'] : 0.0;
            $rowTotal = $qf * $cost;
            $img = $this->resolveProdutoImagem($r);
            $imgTag = $img ? '<img class="img" src="' . htmlspecialchars($img) . '" alt="">' : '<div class="img"></div>';
            echo '<tr>'
                . '<td style="text-align:center;"><span class="check"></span></td>'
                . '<td style="text-align:center;">' . $imgTag . '</td>'
                . '<td><strong>' . htmlspecialchars((string) ($r['produto_nome'] ?? '')) . '</strong></td>'
                . '<td>' . htmlspecialchars((string) ($r['sku'] ?? '')) . '</td>'
                . '<td style="text-align:center;font-size:14px;"><strong>' . $qf . '</strong></td>'
                . '<td>$ ' . number_format($cost, 2, '.', ',') . '</td>'
                . '<td>$ ' . number_format($rowTotal, 2, '.', ',') . '</td>'
                . '</tr>';
        }

        echo '</tbody></table>';
        echo '<div class="totais"><strong>Total de itens:</strong> ' . number_format($totalItens) . '<br>'
            . '<strong>Valor total pendente:</strong> $ ' . number_format($totalValor, 2, '.', ',') . '</div>';

        echo '<div class="no-print" style="margin-top:14px;">
                <button onclick="window.print()">Imprimir / Salvar como PDF</button>
              </div>';
        echo '</body></html>';
        exit;
    }

    public function definirLojaProduto($request) {
        try {
            $produtoId = (int) $request->getParam('produto_id');
            $lojaId = (int) $request->getParam('loja_id');
            if ($produtoId <= 0 || $lojaId <= 0) {
                $_SESSION['message'] = 'Parâmetros inválidos.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/estoque/compras?sem_loja=1');
                exit;
            }

            $cols = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE produtos');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $slug = null;
            if ($this->tableExists('lojas')) {
                $stmt = $this->connection->prepare('SELECT slug FROM lojas WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $lojaId]);
                $tmp = $stmt->fetchColumn();
                if ($tmp !== false && (string) $tmp !== '') $slug = (string) $tmp;
            }

            $sqlParts = [];
            $params = [':id' => $produtoId];
            if (in_array('loja_id', $cols, true)) {
                $sqlParts[] = 'loja_id = :loja_id';
                $params[':loja_id'] = $lojaId;
            }
            if (in_array('loja', $cols, true) && $slug !== null) {
                $sqlParts[] = 'loja = :loja';
                $params[':loja'] = $slug;
            }
            if (!empty($sqlParts)) {
                $stmtUpd = $this->connection->prepare('UPDATE produtos SET ' . implode(', ', $sqlParts) . ' WHERE id = :id LIMIT 1');
                $stmtUpd->execute($params);
            }

            // Atualizar lista_compras pendentes deste produto sem loja
            $hasLojaIdLista = $this->columnExists('lista_compras', 'loja_id');
            if ($hasLojaIdLista) {
                $stmtLC = $this->connection->prepare("UPDATE lista_compras SET loja_id = :loja_id WHERE produto_id = :produto_id AND status = 'pendente' AND (loja_id IS NULL OR loja_id = 0)");
                $stmtLC->execute([':loja_id' => $lojaId, ':produto_id' => $produtoId]);
            }

            $_SESSION['message'] = 'Loja configurada e aplicada às compras pendentes.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/estoque/compras');
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao definir loja.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras?sem_loja=1');
            exit;
        }
    }

    public function verificarEstoque($request) {
        $produto_id = $request->getParam('produto_id');
        echo json_encode(['success' => true, 'message' => 'Verificação de estoque para produto ID: ' . $produto_id]);
    }
}
