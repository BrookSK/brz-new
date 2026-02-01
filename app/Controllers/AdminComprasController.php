<?php
namespace App\Controllers;

class AdminComprasController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
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

            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');
            $temLojaIdEmProdutos = $this->columnExists('produtos', 'loja_id');
            $temCost = $this->columnExists('produtos', 'cost_price');
            $temFoto = $this->columnExists('produtos', 'foto_principal');
            $temImages = $this->columnExists('produtos', 'images');
            $temLojaText = $this->columnExists('produtos', 'loja');

            $selectCols = [
                'lc.*',
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

            $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM lista_compras lc JOIN produtos p ON lc.produto_id = p.id';
            if ($this->tableExists('lojas') && $temLojaIdEmLista) {
                $sql .= ' LEFT JOIN lojas l ON l.id = lc.loja_id';
            }
            $sql .= " WHERE lc.status = 'pendente'";
            $params = [];
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $sql .= ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                } elseif ($lojaIdFilter > 0) {
                    $sql .= ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaIdFilter;
                }
            }
            $sql .= ' ORDER BY lc.prioridade DESC, lc.data_solicitacao ASC';

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

                echo '<div class="card mb-4">
                    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <a class="btn btn-sm ' . (!$semLoja && $lojaIdFilter === 0 ? 'btn-primary' : 'btn-outline-primary') . '" href="/admin/estoque/compras">Todas</a>';

                foreach ($lojas as $l) {
                    $lid = (int) ($l['id'] ?? 0);
                    $lname = (string) ($l['nome'] ?? '');
                    $active = (!$semLoja && $lojaIdFilter === $lid);
                    echo '<a class="btn btn-sm ' . ($active ? 'btn-primary' : 'btn-outline-primary') . '" href="/admin/estoque/compras?loja_id=' . $lid . '">' . htmlspecialchars($lname) . '</a>';
                }

                echo '<a class="btn btn-sm ' . ($semLoja ? 'btn-danger' : 'btn-outline-danger') . '" href="/admin/estoque/compras?sem_loja=1">Sem loja</a>'
                    . '</div>'
                    . '<div class="text-end">'
                    . '<div><strong>Total pendente (itens):</strong> ' . number_format($totalItensPendentes) . '</div>'
                    . '<div><strong>Valor total pendente:</strong> $ ' . number_format($valorTotalPendente, 2, '.', ',') . '</div>'
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
                                        . $btnLoja
                                        . '<button type="button" class="btn btn-outline-success" onclick="alert(\'Marcar como comprado: ' . htmlspecialchars($item['produto_nome']) . '\')"><i class="fas fa-check"></i></button>'
                                        . '<button type="button" class="btn btn-outline-danger" onclick="alert(\'Cancelar item: ' . htmlspecialchars($item['produto_nome']) . '\')"><i class="fas fa-times"></i></button>'
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

    public function mudarStatus($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
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
            'lc.*',
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

        $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM lista_compras lc JOIN produtos p ON lc.produto_id = p.id WHERE lc.status = \'pendente\'';
        $params = [];
        if ($lojaId > 0 && $temLojaIdEmLista) {
            $sql .= ' AND lc.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }
        $sql .= ' ORDER BY lc.prioridade DESC, lc.data_solicitacao ASC';
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
