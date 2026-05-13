<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentService;

class AdminPagamentosController extends Controller {

    private function tableExistsPdo(\PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare('SHOW TABLES LIKE ?');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getConfigValueFromKeyValueTable(\PDO $pdo, string $table, string $categoria, string $chave): ?string {
        try {
            $cols = $this->getTableColumnsPdo($pdo, $table);
            if (empty($cols)) {
                return null;
            }
            if (in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                $st = $pdo->prepare('SELECT valor FROM ' . $table . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                $st->execute([(string) $categoria, (string) $chave]);
                $v = $st->fetchColumn();
                return ($v !== false && $v !== null) ? (string) $v : null;
            }
            if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                $fullKey = $categoria . '_' . $chave;
                $st = $pdo->prepare('SELECT valor FROM ' . $table . ' WHERE chave = ? LIMIT 1');
                $st->execute([(string) $fullKey]);
                $v = $st->fetchColumn();
                return ($v !== false && $v !== null) ? (string) $v : null;
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    private function saveConfigValueToKeyValueTable(\PDO $pdo, string $table, string $categoria, string $chave, ?string $valor): void {
        $cols = $this->getTableColumnsPdo($pdo, $table);
        if (empty($cols)) {
            return;
        }
        $valor = $valor === null ? '' : (string) $valor;

        if (in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
            $st = $pdo->prepare('INSERT INTO ' . $table . ' (categoria, chave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
            $st->execute([(string) $categoria, (string) $chave, (string) $valor]);
            return;
        }
        if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
            $fullKey = $categoria . '_' . $chave;
            $st = $pdo->prepare('INSERT INTO ' . $table . ' (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
            $st->execute([(string) $fullKey, (string) $valor]);
            return;
        }
    }

    private function getTableColumnsPdo(\PDO $pdo, string $table): array {
        try {
            $stmt = $pdo->query('DESCRIBE ' . $table);
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickColumn(array $columns, array $candidates): string {
        $lower = [];
        foreach ($columns as $c) {
            $lower[strtolower((string) $c)] = (string) $c;
        }
        foreach ($candidates as $cand) {
            $key = strtolower((string) $cand);
            if (isset($lower[$key])) {
                return $lower[$key];
            }
        }
        return '';
    }

    private function buildCoalesceExpr(string $tableAlias, array $tableColumns, array $candidates, string $fallback = "''"): string {
        $parts = [];
        $lower = [];
        foreach ($tableColumns as $c) {
            $lower[strtolower((string) $c)] = (string) $c;
        }
        foreach ($candidates as $cand) {
            $key = strtolower((string) $cand);
            if (isset($lower[$key])) {
                $parts[] = $tableAlias . '.' . $lower[$key];
            }
        }
        if (empty($parts)) {
            return $fallback;
        }
        if (count($parts) === 1) {
            return 'COALESCE(' . $parts[0] . ', ' . $fallback . ')';
        }
        return 'COALESCE(' . implode(', ', $parts) . ', ' . $fallback . ')';
    }
    
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $loadError = '';
        try {
            $pdo = \Config\Database::getConnection();

            $colsPedidos = $this->getTableColumnsPdo($pdo, 'pedidos');
            $colsUsuarios = $this->getTableColumnsPdo($pdo, 'usuarios');

            $colUsuarioNome = $this->pickColumn($colsUsuarios, ['nome', 'name', 'full_name']);
            $colPedidoCreatedAt = $this->pickColumn($colsPedidos, ['created_at', 'data_criacao', 'created', 'data', 'data_pedido']);

            $exprGateway = $this->buildCoalesceExpr('p', $colsPedidos, ['payment_gateway', 'pagamento_gateway', 'gateway_pagamento'], "''");
            $exprPaymentId = $this->buildCoalesceExpr('p', $colsPedidos, ['payment_id', 'pagamento_transacao', 'pagamento_id', 'transaction_id'], "''");
            $exprPaymentStatus = $this->buildCoalesceExpr('p', $colsPedidos, ['payment_status', 'pagamento_status', 'status_pagamento'], "''");
            $exprPaymentMetodo = $this->buildCoalesceExpr('p', $colsPedidos, ['forma_pagamento', 'pagamento_metodo', 'payment_method', 'metodo_pagamento'], "''");
            $exprValorTotal = $this->buildCoalesceExpr('p', $colsPedidos, ['valor_total', 'total', 'total_valor', 'valor', 'amount', 'amount_total', 'total_amount', 'total_pedido', 'valor_final'], '0');

            $pagina = $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            $status = $request->getParam('status', '');
            $metodo = $request->getParam('metodo', '');
            $gateway = $request->getParam('gateway', '');

            $selectUsuarioNome = "'Visitante'";
            if ($colUsuarioNome !== '') {
                $selectUsuarioNome = 'u.' . $colUsuarioNome;
            }
            
            $sql = "
                SELECT
                    p.*,
                    {$selectUsuarioNome} as cliente_nome,
                    u.email as cliente_email,
                    {$exprGateway} as gateway_pagamento,
                    {$exprPaymentId} as codigo_transacao,
                    {$exprPaymentStatus} as status_pagamento,
                    {$exprPaymentMetodo} as metodo_pagamento,
                    {$exprValorTotal} as valor_total_calc
                FROM pedidos p
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                WHERE 1=1
            ";
            $params = [];
            
            if (!empty($busca)) {
                $sql .= " AND (p.id LIKE :busca OR {$selectUsuarioNome} LIKE :busca OR {$exprPaymentId} LIKE :busca)";
                $params[':busca'] = "%{$busca}%";
            }
            if (!empty($status)) {
                $sql .= " AND {$exprPaymentStatus} = :status";
                $params[':status'] = $status;
            }
            if (!empty($metodo)) {
                $sql .= " AND {$exprPaymentMetodo} = :metodo";
                $params[':metodo'] = $metodo;
            }
            if (!empty($gateway)) {
                $sql .= " AND LOWER({$exprGateway}) = :gateway";
                $params[':gateway'] = strtolower((string) $gateway);
            }
            
            if ($colPedidoCreatedAt !== '') {
                $sql .= ' ORDER BY p.' . $colPedidoCreatedAt . ' DESC';
            } else {
                $sql .= ' ORDER BY p.id DESC';
            }
            $sql .= " LIMIT :limite OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) $stmt->bindValue($key, $value);
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $pagamentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Carregar split (pedido_pagamentos) para exibição do "quanto foi para cada conta"
            $splitPorPedido = [];
            try {
                $ids = [];
                foreach ($pagamentos as $p) {
                    $pid = (int) ($p['id'] ?? 0);
                    if ($pid > 0) $ids[] = $pid;
                }
                $ids = array_values(array_unique($ids));
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stSplit = $pdo->prepare('SELECT pedido_id, componente, gateway, metodo, moeda, valor, status FROM pedido_pagamentos WHERE pedido_id IN (' . $placeholders . ') ORDER BY pedido_id ASC, id ASC');
                    $stSplit->execute($ids);
                    $rowsSplit = $stSplit->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($rowsSplit as $r) {
                        $pid = (int) ($r['pedido_id'] ?? 0);
                        if ($pid <= 0) continue;
                        if (!isset($splitPorPedido[$pid])) $splitPorPedido[$pid] = [];
                        $splitPorPedido[$pid][] = $r;
                    }
                }
            } catch (\Exception $e) {
                $splitPorPedido = [];
            }
            
            $sqlTotal = "SELECT COUNT(*) as total FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE 1=1";
            $paramsTotal = [];
            if (!empty($busca)) {
                $sqlTotal .= " AND (p.id LIKE :busca OR {$selectUsuarioNome} LIKE :busca OR {$exprPaymentId} LIKE :busca)";
                $paramsTotal[':busca'] = "%{$busca}%";
            }
            if (!empty($status)) {
                $sqlTotal .= " AND {$exprPaymentStatus} = :status";
                $paramsTotal[':status'] = $status;
            }
            if (!empty($metodo)) {
                $sqlTotal .= " AND {$exprPaymentMetodo} = :metodo";
                $paramsTotal[':metodo'] = $metodo;
            }
            if (!empty($gateway)) {
                $sqlTotal .= " AND LOWER({$exprGateway}) = :gateway";
                $paramsTotal[':gateway'] = strtolower((string) $gateway);
            }
            
            $stmtTotal = $pdo->prepare($sqlTotal);
            foreach ($paramsTotal as $key => $value) $stmtTotal->bindValue($key, $value);
            $stmtTotal->execute();
            $total = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($total / $limite);
            
            // Estatísticas (não pode derrubar a listagem caso falhe por schema)
            $stats = ['total_transacoes' => 0, 'valor_total' => 0, 'valor_aprovado' => 0, 'valor_pendente' => 0, 'valor_recusado' => 0];
            try {
                $where30 = '';
                if ($colPedidoCreatedAt !== '') {
                    $where30 = ' WHERE p.' . $colPedidoCreatedAt . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                }
                $stmtStats = $pdo->query("
                    SELECT 
                        COUNT(*) as total_transacoes,
                        SUM({$exprValorTotal}) as valor_total,
                        SUM(CASE WHEN LOWER({$exprPaymentStatus}) IN ('approved','aprovado','paid','pago','succeeded','success') THEN {$exprValorTotal} ELSE 0 END) as valor_aprovado,
                        SUM(CASE WHEN LOWER({$exprPaymentStatus}) IN ('pending','pendente') THEN {$exprValorTotal} ELSE 0 END) as valor_pendente,
                        SUM(CASE WHEN LOWER({$exprPaymentStatus}) IN ('rejected','recusado','failed','canceled','cancelled') THEN {$exprValorTotal} ELSE 0 END) as valor_recusado
                    FROM pedidos p
                    {$where30}
                ");
                $rowStats = $stmtStats ? $stmtStats->fetch(\PDO::FETCH_ASSOC) : null;
                if (is_array($rowStats)) {
                    $stats = array_merge($stats, $rowStats);
                }
            } catch (\Exception $e) {
                // fallback silencioso
            }

            // Se o stats não veio, ao menos refletir a existência de dados na tela.
            if ((int) ($stats['total_transacoes'] ?? 0) <= 0) {
                $stats['total_transacoes'] = (int) ($total ?? 0);
            }
            
        } catch (\Exception $e) {
            $pagamentos = [];
            $total = 0;
            $totalPaginas = 0;
            $stats = ['total_transacoes' => 0, 'valor_total' => 0, 'valor_aprovado' => 0, 'valor_pendente' => 0, 'valor_recusado' => 0];
            $loadError = $e->getMessage();
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamentos - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .payment-card { transition: transform 0.2s; }
        .payment-card:hover { transform: translateY(-5px); }
        .status-aprovado { background-color: #28a745; }
        .status-pendente { background-color: #ffc107; }
        .status-recusado { background-color: #dc3545; }
        .status-estornado { background-color: #6f42c1; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('pagamentos');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">Pagamentos (' . $stats['total_transacoes'] . ' transações)</h1>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success me-2" onclick="alert(\'Funcionalidade em desenvolvimento\')">
                            <i class="fas fa-download me-1"></i>Exportar Relatório
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                        <a class="btn btn-outline-primary" href="/admin/pagamentos/configuracoes"><i class="fas fa-cog"></i> Configurações</a>
                    </div>
                </div>';

                if (!empty($loadError)) {
                    echo '<div class="alert alert-danger">Erro ao carregar pagamentos: ' . htmlspecialchars($loadError) . '</div>';
                }
                
                echo '<div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Transações (30 dias)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['total_transacoes'] . '</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-credit-card fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Aprovados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ ' . number_format($stats['valor_aprovado'], 2, ',', '.') . '</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pendentes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ ' . number_format($stats['valor_pendente'], 2, ',', '.') . '</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Recusados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ ' . number_format($stats['valor_recusado'], 2, ',', '.') . '</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-times-circle fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="busca" placeholder="Buscar pedido, cliente ou transação..." value="' . htmlspecialchars($busca) . '">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">Todos status</option>
                            <option value="approved" ' . ($status === 'approved' ? 'selected' : '') . '>Aprovado</option>
                            <option value="pending" ' . ($status === 'pending' ? 'selected' : '') . '>Pendente</option>
                            <option value="rejected" ' . ($status === 'rejected' ? 'selected' : '') . '>Recusado</option>
                            <option value="refunded" ' . ($status === 'refunded' ? 'selected' : '') . '>Estornado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="metodo">
                            <option value="">Todos métodos</option>
                            <option value="CREDIT_CARD" ' . ($metodo === 'CREDIT_CARD' ? 'selected' : '') . '>Cartão</option>
                            <option value="BOLETO" ' . ($metodo === 'BOLETO' ? 'selected' : '') . '>Boleto</option>
                            <option value="PIX" ' . ($metodo === 'PIX' ? 'selected' : '') . '>PIX</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="gateway">
                            <option value="">Todos gateways</option>
                            <option value="stripe" ' . ($gateway === 'stripe' ? 'selected' : '') . '>Stripe</option>
                            <option value="appmax" ' . ($gateway === 'appmax' ? 'selected' : '') . '>AppMax</option>
                            <option value="cambioreal" ' . ($gateway === 'cambioreal' ? 'selected' : '') . '>Câmbio Real</option>
                            <option value="split" ' . ($gateway === 'split' ? 'selected' : '') . '>Split</option>
                            <option value="carteira" ' . ($gateway === 'carteira' ? 'selected' : '') . '>Carteira</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Filtrar</button>
                    </div>
                </form>
                
                <div class="row">';
                
                foreach ($pagamentos as $pagamento) {
                    $pedidoIdRow = (int) ($pagamento['id'] ?? 0);
                    $stRow = strtolower(trim((string) ($pagamento['status_pagamento'] ?? 'pending')));
                    $gwRow = strtolower(trim((string) ($pagamento['gateway_pagamento'] ?? '')));
                    $valorRow = 0.0;
                    if (isset($pagamento['valor_total_calc'])) {
                        $valorRow = (float) $pagamento['valor_total_calc'];
                    } elseif (isset($pagamento['valor_total'])) {
                        $valorRow = (float) $pagamento['valor_total'];
                    }

                    $splitRows = $splitPorPedido[$pedidoIdRow] ?? [];
                    $splitResumoHtml = '';
                    if (is_array($splitRows) && !empty($splitRows)) {
                        $sumByGateway = [];
                        foreach ($splitRows as $sr) {
                            $g = strtolower(trim((string) ($sr['gateway'] ?? '')));
                            if ($g === '') continue;
                            $v = (float) ($sr['valor'] ?? 0);
                            if (!isset($sumByGateway[$g])) $sumByGateway[$g] = 0.0;
                            $sumByGateway[$g] += $v;
                        }
                        $parts = [];
                        foreach ($sumByGateway as $g => $v) {
                            $label = strtoupper($g);
                            if ($g === 'cambioreal') $label = 'Câmbio Real';
                            if ($g === 'appmax') $label = 'AppMax';
                            if ($g === 'stripe') $label = 'Stripe';
                            $parts[] = '<span class="badge bg-light text-dark" style="border:1px solid #e5e7eb;">' . htmlspecialchars($label) . ': <strong>R$ ' . number_format((float) $v, 2, ',', '.') . '</strong></span>';
                        }
                        if (!empty($parts)) {
                            $splitResumoHtml = '<div class="mt-2 d-flex flex-wrap gap-2">' . implode(' ', $parts) . '</div>';
                        }
                    }

                    $statusBadge = 'Pendente';
                    $statusClass = 'status-pendente';
                    if (in_array($stRow, ['approved', 'aprovado', 'paid', 'pago', 'succeeded', 'success'], true)) {
                        $statusBadge = 'Aprovado';
                        $statusClass = 'status-aprovado';
                    } elseif (in_array($stRow, ['rejected', 'recusado', 'failed', 'canceled', 'cancelled'], true)) {
                        $statusBadge = 'Recusado';
                        $statusClass = 'status-recusado';
                    } elseif (in_array($stRow, ['refunded', 'estornado'], true)) {
                        $statusBadge = 'Estornado';
                        $statusClass = 'status-estornado';
                    }

                    echo '<div class="col-md-6 col-lg-4 mb-4">
                        <div class="card payment-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong>Pedido #' . str_pad((string) $pedidoIdRow, 6, '0', STR_PAD_LEFT) . '</strong>
                                <span class="badge ' . $statusClass . '">' . $statusBadge . '</span>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title">' . htmlspecialchars($pagamento['cliente_nome'] ?? 'Visitante') . '</h6>
                                <p class="card-text text-muted small">' . htmlspecialchars($pagamento['cliente_email'] ?? 'N/A') . '</p>
                                <p class="card-text">
                                    <small class="text-muted">Método: ' . htmlspecialchars((string) ($pagamento['metodo_pagamento'] ?? 'N/A')) . '</small><br>
                                    <small class="text-muted">Gateway: ' . htmlspecialchars($gwRow !== '' ? strtoupper($gwRow) : 'N/A') . '</small><br>
                                    <small class="text-muted">Transação: ' . htmlspecialchars((string) ($pagamento['codigo_transacao'] ?? 'N/A')) . '</small><br>
                                    <strong>Valor: R$ ' . number_format($valorRow, 2, ',', '.') . '</strong>
                                </p>
                                ' . $splitResumoHtml . '
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="/admin/pedidos/detalhes/' . $pedidoIdRow . '" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Ver Pedido
                                    </a>
                                    <button class="btn btn-sm btn-outline-info" onclick="refreshPagamento(' . $pedidoIdRow . ')">
                                        <i class="fas fa-sync"></i> Atualizar status
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" onclick="cancelarPagamento(' . $pedidoIdRow . ')">
                                        <i class="fas fa-ban"></i> Cancelar pag.
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="estornarPagamento(' . $pedidoIdRow . ')">
                                        <i class="fas fa-undo"></i> Estornar
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="cancelarPedido(' . $pedidoIdRow . ')">
                                        <i class="fas fa-xmark"></i> Cancelar pedido
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                if (empty($pagamentos)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum pagamento encontrado</h5>
                    </div>';
                }
                
                echo '</div>';
                
                if ($totalPaginas > 1) {
                    echo '<nav class="mt-4"><ul class="pagination justify-content-center">';
                    for ($i = 1; $i <= $totalPaginas; $i++) {
                        $url = "/admin/pagamentos?pagina={$i}" . (!empty($busca) ? "&busca=" . urlencode($busca) : "") . (!empty($status) ? "&status={$status}" : "") . (!empty($metodo) ? "&metodo={$metodo}" : "");
                        echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">
                            <a class="page-link" href="' . $url . '">' . $i . '</a>
                        </li>';
                    }
                    echo '</ul></nav>';
                }
                
                echo '</main></div></div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarPagamento(pedidoId) {
            if (confirm("Tem certeza que deseja confirmar este pagamento?")) {
                fetch("/admin/pagamentos/confirmar/" + pedidoId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Erro ao confirmar pagamento: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao confirmar pagamento");
                });
            }
        }

        function refreshPagamento(pedidoId) {
            fetch("/admin/pagamentos/refresh/" + pedidoId, {
                method: "POST",
                headers: { "Content-Type": "application/json" }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert("Erro ao atualizar status: " + (data.error || data.message || ""));
                }
            })
            .catch(() => alert("Erro ao atualizar status"));
        }

        function cancelarPagamento(pedidoId) {
            if (!confirm("Cancelar pagamento no gateway?")) return;
            fetch("/admin/pagamentos/cancelar/" + pedidoId, {
                method: "POST",
                headers: { "Content-Type": "application/json" }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert("Erro ao cancelar pagamento: " + (data.error || data.message || ""));
                }
            })
            .catch(() => alert("Erro ao cancelar pagamento"));
        }

        function estornarPagamento(pedidoId) {
            const motivo = prompt("Motivo do estorno (opcional):", "");
            if (motivo === null) return;
            if (!confirm("Confirmar estorno?")) return;

            fetch("/admin/pagamentos/estornar/" + pedidoId, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ motivo: motivo || "" })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert("Erro ao estornar: " + (data.error || data.message || ""));
                }
            })
            .catch(() => alert("Erro ao estornar"));
        }

        function cancelarPedido(pedidoId) {
            if (!confirm("Cancelar o pedido no sistema?")) return;
            const estornar = confirm("Deseja estornar/cancelar o pagamento também?");
            fetch("/admin/pagamentos/cancelar-pedido/" + pedidoId, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ estornar: estornar ? 1 : 0 })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert("Erro ao cancelar pedido: " + (data.error || data.message || ""));
                }
            })
            .catch(() => alert("Erro ao cancelar pedido"));
        }
    </script>
</body>
</html>';
        exit;
    }

    public function refreshPagamento(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $pedidoId = (int) $request->getParam('id');
        try {
            $svc = new PaymentService();

            $db = \Config\Database::getConnection();
            $colsP = $this->getTableColumnsPdo($db, 'pedidos');

            $exprGateway = $this->buildCoalesceExpr('p', $colsP, ['payment_gateway', 'pagamento_gateway', 'gateway_pagamento'], "''");
            $exprPaymentId = $this->buildCoalesceExpr('p', $colsP, ['payment_id', 'pagamento_transacao', 'pagamento_id', 'transaction_id'], "''");
            $exprPaymentStatus = $this->buildCoalesceExpr('p', $colsP, ['payment_status', 'pagamento_status', 'status_pagamento'], "''");

            $st = $db->prepare('SELECT ' . $exprGateway . ' AS gateway_pagamento, ' . $exprPaymentId . ' AS payment_id_calc, ' . $exprPaymentStatus . ' AS status_pagamento FROM pedidos p WHERE p.id = ? LIMIT 1');
            $st->execute([$pedidoId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            $gateway = strtolower(trim((string) ($row['gateway_pagamento'] ?? '')));
            $paymentId = trim((string) ($row['payment_id_calc'] ?? ''));
            $paymentStatus = strtolower(trim((string) ($row['status_pagamento'] ?? '')));

            if ($gateway === 'stripe') {
                $resp = $svc->atualizarStatusPagamentoStripePorPedido($pedidoId);
                $this->json($resp);
                return;
            }

            if ($gateway === 'appmax') {
                $resp = $svc->atualizarStatusPagamentoAppmaxPorPedido($pedidoId);
                $this->json($resp);
                return;
            }

            if (in_array($gateway, ['carteira', 'wallet'], true)) {
                $this->json([
                    'success' => true,
                    'gateway' => $gateway,
                    'payment_id' => $paymentId,
                    'payment_status' => $paymentStatus !== '' ? $paymentStatus : 'approved',
                    'message' => 'Status da carteira é local (sem refresh externo)'
                ]);
                return;
            }

            $this->json(['success' => false, 'error' => 'Refresh automático ainda não implementado para este gateway']);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function cancelarPagamento(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $pedidoId = (int) $request->getParam('id');
        try {
            $svc = new PaymentService();
            $db = \Config\Database::getConnection();
            $st = $db->prepare('SELECT payment_gateway FROM pedidos WHERE id = ? LIMIT 1');
            $st->execute([$pedidoId]);
            $gateway = strtolower(trim((string) ($st->fetchColumn() ?: '')));

            if ($gateway === 'stripe') {
                $resp = $svc->cancelarPagamentoStripePorPedido($pedidoId);
                $this->json($resp);
                return;
            }

            if ($gateway === 'appmax') {
                $resp = $svc->cancelarPagamentoAppmaxPorPedido($pedidoId);
                $this->json($resp);
                return;
            }

            if ($gateway === 'carteira') {
                $resp = $svc->cancelarPagamentoCarteiraPorPedido($pedidoId);
                $this->json($resp);
                return;
            }

            $this->json(['success' => false, 'error' => 'Cancelamento ainda não implementado para este gateway']);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function estornarPagamento(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $pedidoId = (int) $request->getParam('id');
        $payload = [];
        try {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            $payload = is_array($decoded) ? $decoded : [];
        } catch (\Exception $e) {
            $payload = [];
        }
        $motivo = (string) ($payload['motivo'] ?? '');
        $valor = null;
        if (isset($payload['valor'])) {
            $v = str_replace(',', '.', trim((string) $payload['valor']));
            if (is_numeric($v)) {
                $valor = (float) $v;
            }
        }

        try {
            $svc = new PaymentService();
            $db = \Config\Database::getConnection();
            $st = $db->prepare('SELECT payment_gateway FROM pedidos WHERE id = ? LIMIT 1');
            $st->execute([$pedidoId]);
            $gateway = strtolower(trim((string) ($st->fetchColumn() ?: '')));

            if ($gateway === 'stripe') {
                $resp = $svc->estornarPagamentoStripePorPedido($pedidoId, $motivo);
                $this->json($resp);
                return;
            }

            if ($gateway === 'appmax') {
                $resp = $svc->estornarPagamentoAppmaxPorPedido($pedidoId, $valor);
                $this->json($resp);
                return;
            }

            if ($gateway === 'carteira') {
                $resp = $svc->estornarPagamentoCarteiraPorPedido($pedidoId, $valor, $motivo);
                $this->json($resp);
                return;
            }

            $this->json(['success' => false, 'error' => 'Estorno ainda não implementado para este gateway']);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function cancelarPedido(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $pedidoId = (int) $request->getParam('id');
        try {
            $db = \Config\Database::getConnection();
            $svc = new PaymentService();

            $payload = [];
            try {
                $raw = file_get_contents('php://input');
                $decoded = json_decode((string) $raw, true);
                $payload = is_array($decoded) ? $decoded : [];
            } catch (\Exception $e) {
                $payload = [];
            }
            $estornar = !empty($payload['estornar']);

            $stPedido = $db->prepare('SELECT id, payment_gateway, payment_id, payment_status, pagamento_gateway, pagamento_transacao, pagamento_status FROM pedidos WHERE id = ? LIMIT 1');
            $stPedido->execute([$pedidoId]);
            $pedido = $stPedido->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                $this->json(['success' => false, 'error' => 'Pedido não encontrado']);
                return;
            }

            $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
            $paymentStatus = strtolower(trim((string) ($pedido['payment_status'] ?? ($pedido['pagamento_status'] ?? ''))));
            $isPaid = in_array($paymentStatus, ['approved', 'aprovado', 'paid', 'pago', 'succeeded', 'success'], true);
            $hasPayment = trim((string) ($pedido['payment_id'] ?? ($pedido['pagamento_transacao'] ?? ''))) !== '';

            $gatewayResult = null;
            if ($estornar && $hasPayment && $gateway !== '') {
                if ($isPaid) {
                    if ($gateway === 'stripe') {
                        $gatewayResult = $svc->estornarPagamentoStripePorPedido($pedidoId, 'Cancelamento do pedido no sistema');
                    } elseif ($gateway === 'appmax') {
                        $gatewayResult = $svc->estornarPagamentoAppmaxPorPedido($pedidoId, null);
                    } elseif ($gateway === 'carteira') {
                        $gatewayResult = $svc->estornarPagamentoCarteiraPorPedido($pedidoId, null, 'Cancelamento do pedido no sistema');
                    }
                } else {
                    if ($gateway === 'stripe') {
                        $gatewayResult = $svc->cancelarPagamentoStripePorPedido($pedidoId);
                    } elseif ($gateway === 'appmax') {
                        $gatewayResult = $svc->cancelarPagamentoAppmaxPorPedido($pedidoId);
                    } elseif ($gateway === 'carteira') {
                        $gatewayResult = $svc->cancelarPagamentoCarteiraPorPedido($pedidoId);
                    }
                }
            }

            $colsP = [];
            try {
                $stmtColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stmtColsP ? ($stmtColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsP = [];
            }

            $set = [];
            $params = [':id' => $pedidoId];
            if (in_array('status', $colsP, true)) {
                $set[] = 'status = :status';
                $params[':status'] = 'cancelado';
            }
            if (in_array('updated_at', $colsP, true)) {
                $set[] = 'updated_at = NOW()';
            }

            if (!empty($set)) {
                $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $st = $db->prepare($sql);
                $st->execute($params);
            }

            $this->json([
                'success' => true,
                'gateway' => $gateway,
                'payment_status' => $paymentStatus,
                'gateway_action' => $estornar ? ($isPaid ? 'refund' : 'cancel') : 'none',
                'gateway_result' => $gatewayResult,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function comissoesGerais(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $janelaId = (int) $request->getParam('janela_id', 0);

        $pdo = null;
        $janelas = [];
        $rows = [];

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $hasSchema = true;
            foreach (['comissao_janelas', 'comissao_pagamentos', 'comissao_ajustes'] as $t) {
                $st = $pdo->prepare('SHOW TABLES LIKE ?');
                $st->execute([$t]);
                if (!$st->fetchColumn()) {
                    $hasSchema = false;
                    break;
                }
            }

            if ($hasSchema) {
                $st = $pdo->query('SELECT id, data_inicio, data_fim, status FROM comissao_janelas ORDER BY data_inicio DESC');
                $janelas = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                if ($janelaId <= 0 && !empty($janelas)) {
                    $janelaId = (int) ($janelas[0]['id'] ?? 0);
                }
            }

            if ($hasSchema && $janelaId > 0) {
                $colsPag = [];
                try {
                    $stCols = $pdo->query('DESCRIBE comissao_pagamentos');
                    $colsPag = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsPag = [];
                }
                $hasMoedaPag = is_array($colsPag) && in_array('moeda', $colsPag, true);

                $colsAj = [];
                try {
                    $stCols = $pdo->query('DESCRIBE comissao_ajustes');
                    $colsAj = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsAj = [];
                }
                $hasMoedaAj = is_array($colsAj) && in_array('moeda', $colsAj, true);

                $hasCpp = false;
                $colsCpp = [];
                try {
                    $st = $pdo->prepare('SHOW TABLES LIKE ?');
                    $st->execute(['comissao_pagamento_pedidos']);
                    $hasCpp = (bool) $st->fetchColumn();
                } catch (\Exception $e) {
                    $hasCpp = false;
                }
                if ($hasCpp) {
                    try {
                        $stCols = $pdo->query('DESCRIBE comissao_pagamento_pedidos');
                        $colsCpp = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                    } catch (\Exception $e) {
                        $colsCpp = [];
                    }
                }
                $hasMoedaCpp = is_array($colsCpp) && in_array('moeda', $colsCpp, true);

                $sql = "
                    SELECT
                        cp.vendedor_id,
                        u.nome AS vendedor_nome,
                        u.email AS vendedor_email,
                        " . ($hasMoedaPag ? 'cp.moeda' : "'USD'") . " AS moeda,
                        COALESCE(SUM(cpp.valor_comissao_usd), 0) AS comissao_calculada_usd,
                        COALESCE((
                            SELECT SUM(CASE WHEN ca.tipo = 'credito' THEN ca.valor_usd ELSE -ca.valor_usd END)
                            FROM comissao_ajustes ca
                            WHERE ca.janela_id = cp.janela_id
                              AND ca.vendedor_id = cp.vendedor_id
                              " . ($hasMoedaAj ? 'AND ca.moeda = ' . ($hasMoedaPag ? 'cp.moeda' : "'USD'") : '') . "
                        ), 0) AS ajustes_usd,
                        COALESCE(cp.valor_pago_usd, 0) AS valor_pago_usd,
                        cp.status AS status_pagamento,
                        cp.id AS pagamento_id
                    FROM comissao_pagamentos cp
                    LEFT JOIN usuarios u ON u.id = cp.vendedor_id
                    " . ($hasCpp ? ('LEFT JOIN comissao_pagamento_pedidos cpp ON cpp.pagamento_id = cp.id' . ($hasMoedaCpp && $hasMoedaPag ? ' AND cpp.moeda = cp.moeda' : '') ) : 'LEFT JOIN comissao_pagamento_pedidos cpp ON 1=0') . "
                    WHERE cp.janela_id = :janela_id
                      AND cp.deleted_at IS NULL
                    GROUP BY cp.id, cp.vendedor_id, moeda
                    ORDER BY u.nome ASC
                ";

                $st = $pdo->prepare($sql);
                $st->execute([':janela_id' => $janelaId]);
                $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $pdo = null;
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $fmt = function (float $v, string $moeda): string {
            $moeda = strtoupper(trim($moeda));
            if ($moeda === 'USD') {
                return '$ ' . number_format($v, 2, '.', ',');
            }
            return 'R$ ' . number_format($v, 2, ',', '.');
        };

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comissões gerais - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('pagamentos');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">Comissões gerais</h1>
                    <div>
                        <a href="/admin/pagamentos" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                    </div>
                </div>';

        if (empty($janelas)) {
            echo '<div class="alert alert-warning">Schema de Comissões gerais não encontrado. Rode as migrations 052, 053 e 057.</div>';
            echo '</main></div></div>';
            renderAdminScripts();
            echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
            exit;
        }

        echo '<form method="GET" class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Janela</label>
                    <select class="form-select" name="janela_id" onchange="this.form.submit()">';
        foreach ($janelas as $j) {
            $jid = (int) ($j['id'] ?? 0);
            $lab = ' #' . $jid . ' - ' . date('d/m/Y', strtotime((string) ($j['data_inicio'] ?? 'now'))) . ' até ' . date('d/m/Y', strtotime((string) ($j['data_fim'] ?? 'now')));
            $sel = ($jid === $janelaId) ? 'selected' : '';
            echo '<option value="' . $jid . '" ' . $sel . '>' . htmlspecialchars($lab) . '</option>';
        }
        echo '     </select>
                </div>
            </form>';

        echo '<div class="card mb-4">
                <div class="card-header"><strong>Resumo por vendedor</strong></div>
                <div class="card-body">';

        if (empty($rows)) {
            echo '<div class="text-muted">Sem pagamentos nesta janela.</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Moeda</th>
                            <th class="text-end">Comissão calculada</th>
                            <th class="text-end">Ajustes</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Pago</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($rows as $r) {
                $moeda = strtoupper(trim((string) ($r['moeda'] ?? 'USD')));
                $calc = (float) ($r['comissao_calculada_usd'] ?? 0);
                $aj = (float) ($r['ajustes_usd'] ?? 0);
                $total = $calc + $aj;
                $pago = (float) ($r['valor_pago_usd'] ?? 0);
                $st = (string) ($r['status_pagamento'] ?? 'pendente');
                $pagamentoId = (int) ($r['pagamento_id'] ?? 0);
                $vendedorNome = (string) ($r['vendedor_nome'] ?? '');
                $vendedorEmail = (string) ($r['vendedor_email'] ?? '');
                $vLabel = trim($vendedorNome . ($vendedorEmail !== '' ? (' <' . $vendedorEmail . '>') : ''));

                echo '<tr>'
                    . '<td>' . htmlspecialchars($vLabel !== '' ? $vLabel : ('Vendedor #' . (int) ($r['vendedor_id'] ?? 0))) . '</td>'
                    . '<td>' . htmlspecialchars($moeda) . '</td>'
                    . '<td class="text-end">' . $fmt($calc, $moeda) . '</td>'
                    . '<td class="text-end">' . $fmt($aj, $moeda) . '</td>'
                    . '<td class="text-end fw-semibold">' . $fmt($total, $moeda) . '</td>'
                    . '<td class="text-end">' . $fmt($pago, $moeda) . '</td>'
                    . '<td>' . htmlspecialchars($st) . '</td>'
                    . '<td class="text-nowrap">'
                    . '<button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="abrirAjuste(' . (int) ($r['vendedor_id'] ?? 0) . ', \' ' . htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') . '\')">Ajuste</button>'
                    . '<button class="btn btn-sm btn-outline-success me-1" type="button" onclick="abrirPagamento(' . (int) ($r['vendedor_id'] ?? 0) . ', \' ' . htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') . '\', ' . number_format($total, 2, '.', '') . ')">Pagamento</button>'
                    . (($st !== 'aprovado' && $pagamentoId > 0)
                        ? ('<form method="POST" action="/admin/pagamentos/comissoes-gerais/aprovar/' . $pagamentoId . '" style="display:inline-block">'
                            . '<button class="btn btn-sm btn-success me-1" type="submit">Aprovar</button>'
                            . '</form>')
                        : '')
                    . (($pagamentoId > 0)
                        ? ('<form method="POST" action="/admin/pagamentos/comissoes-gerais/deletar/' . $pagamentoId . '" style="display:inline-block" onsubmit="return confirm(\'Remover este pagamento?\')">'
                            . '<button class="btn btn-sm btn-outline-danger" type="submit">Deletar</button>'
                            . '</form>')
                        : '')
                    . '</td>'
                    . '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</div></div>';

        echo '<div class="modal fade" id="modalAjuste" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="/admin/pagamentos/comissoes-gerais/ajuste">
                            <div class="modal-header">
                                <h5 class="modal-title">Criar ajuste</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="janela_id" value="' . (int) $janelaId . '">
                                <input type="hidden" name="vendedor_id" id="aj_vendedor_id" value="">
                                <input type="hidden" name="moeda" id="aj_moeda" value="USD">
                                <div class="mb-3">
                                    <label class="form-label">Tipo</label>
                                    <select class="form-select" name="tipo" required>
                                        <option value="credito">Crédito</option>
                                        <option value="debito">Débito</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Valor</label>
                                    <input class="form-control" name="valor" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Motivo</label>
                                    <input class="form-control" name="motivo">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar ajuste</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';

        echo '<div class="modal fade" id="modalPagamento" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="/admin/pagamentos/comissoes-gerais/pagamento">
                            <div class="modal-header">
                                <h5 class="modal-title">Registrar pagamento</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="janela_id" value="' . (int) $janelaId . '">
                                <input type="hidden" name="vendedor_id" id="pg_vendedor_id" value="">
                                <input type="hidden" name="moeda" id="pg_moeda" value="USD">
                                <div class="mb-3">
                                    <label class="form-label">Valor pago</label>
                                    <input class="form-control" name="valor_pago" id="pg_valor_pago" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Método</label>
                                    <input class="form-control" name="metodo">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Observação</label>
                                    <textarea class="form-control" name="observacao" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success">Salvar pagamento</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';

        echo '</main></div></div>';
        renderAdminScripts();
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '<script>
            function abrirAjuste(vendedorId, moeda){
                document.getElementById("aj_vendedor_id").value = String(vendedorId || "");
                document.getElementById("aj_moeda").value = String(moeda || "USD").trim();
                var m = new bootstrap.Modal(document.getElementById("modalAjuste"));
                m.show();
            }
            function abrirPagamento(vendedorId, moeda, sugestao){
                document.getElementById("pg_vendedor_id").value = String(vendedorId || "");
                document.getElementById("pg_moeda").value = String(moeda || "USD").trim();
                document.getElementById("pg_valor_pago").value = (sugestao !== undefined && sugestao !== null) ? String(sugestao) : "";
                var m = new bootstrap.Modal(document.getElementById("modalPagamento"));
                m.show();
            }
        </script></body></html>';
        exit;
    }

    public function criarAjusteComissaoGeral(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
        $admin = $auth->getUsuarioLogado();

        $janelaId = (int) $request->getParam('janela_id', 0);
        $vendedorId = (int) $request->getParam('vendedor_id', 0);
        $tipo = (string) $request->getParam('tipo', 'credito');
        $moeda = strtoupper(trim((string) $request->getParam('moeda', 'USD')));
        $motivo = (string) $request->getParam('motivo', '');
        $valor = (float) str_replace(',', '.', (string) $request->getParam('valor', '0'));

        if ($janelaId <= 0 || $vendedorId <= 0 || $valor <= 0) {
            $this->redirect('/admin/pagamentos/comissoes-gerais?janela_id=' . (int) $janelaId);
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $cols = [];
            try {
                $stCols = $pdo->query('DESCRIBE comissao_ajustes');
                $cols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $insertCols = ['janela_id', 'vendedor_id', 'tipo', 'valor_usd', 'motivo', 'criado_por'];
            $insertVals = [':janela_id', ':vendedor_id', ':tipo', ':valor', ':motivo', ':criado_por'];
            $params = [
                ':janela_id' => $janelaId,
                ':vendedor_id' => $vendedorId,
                ':tipo' => $tipo,
                ':valor' => $valor,
                ':motivo' => $motivo,
                ':criado_por' => (int) ($admin['id'] ?? 0),
            ];

            if (is_array($cols) && in_array('moeda', $cols, true)) {
                $insertCols[] = 'moeda';
                $insertVals[] = ':moeda';
                $params[':moeda'] = ($moeda !== '' ? $moeda : 'USD');
            }

            $sql = 'INSERT INTO comissao_ajustes (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')';
            $st = $pdo->prepare($sql);
            $st->execute($params);
        } catch (\Exception $e) {
        }

        $this->redirect('/admin/pagamentos/comissoes-gerais?janela_id=' . (int) $janelaId);
    }

    public function criarPagamentoComissaoGeral(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $janelaId = (int) $request->getParam('janela_id', 0);
        $vendedorId = (int) $request->getParam('vendedor_id', 0);
        $moeda = strtoupper(trim((string) $request->getParam('moeda', 'USD')));
        $metodo = (string) $request->getParam('metodo', '');
        $observacao = (string) $request->getParam('observacao', '');
        $valorPago = (float) str_replace(',', '.', (string) $request->getParam('valor_pago', '0'));

        if ($janelaId <= 0 || $vendedorId <= 0 || $valorPago <= 0) {
            $this->redirect('/admin/pagamentos/comissoes-gerais?janela_id=' . (int) $janelaId);
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $colsPag = [];
            try {
                $stCols = $pdo->query('DESCRIBE comissao_pagamentos');
                $colsPag = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsPag = [];
            }
            $hasMoedaPag = is_array($colsPag) && in_array('moeda', $colsPag, true);

            // Por enquanto, mantém compatibilidade: valor_calculado_usd fica 0 quando não houver alocação em comissao_pagamento_pedidos
            $calc = 0.0;
            $ajustes = 0.0;

            try {
                $st = $pdo->prepare('SHOW TABLES LIKE ?');
                $st->execute(['comissao_ajustes']);
                if ($st->fetchColumn()) {
                    $colsAj = [];
                    try {
                        $stCols = $pdo->query('DESCRIBE comissao_ajustes');
                        $colsAj = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                    } catch (\Exception $e) {
                        $colsAj = [];
                    }
                    $hasMoedaAj = is_array($colsAj) && in_array('moeda', $colsAj, true);
                    $sqlAj = 'SELECT SUM(CASE WHEN tipo = \'credito\' THEN valor_usd ELSE -valor_usd END) AS v FROM comissao_ajustes WHERE janela_id = :janela AND vendedor_id = :vend' . ($hasMoedaAj ? ' AND moeda = :moeda' : '');
                    $stAj = $pdo->prepare($sqlAj);
                    $paramsAj = [':janela' => $janelaId, ':vend' => $vendedorId];
                    if ($hasMoedaAj) {
                        $paramsAj[':moeda'] = ($moeda !== '' ? $moeda : 'USD');
                    }
                    $stAj->execute($paramsAj);
                    $ajustes = (float) ($stAj->fetchColumn() ?: 0);
                }
            } catch (\Exception $e) {
            }

            $total = $calc + $ajustes;

            $insertCols = ['janela_id', 'vendedor_id', 'valor_calculado_usd', 'valor_ajustes_usd', 'valor_total_usd', 'valor_pago_usd', 'metodo', 'observacao', 'status'];
            $insertVals = [':janela', ':vend', ':calc', ':aj', ':total', ':pago', ':metodo', ':obs', "'pendente'"];
            $params = [
                ':janela' => $janelaId,
                ':vend' => $vendedorId,
                ':calc' => $calc,
                ':aj' => $ajustes,
                ':total' => $total,
                ':pago' => $valorPago,
                ':metodo' => $metodo,
                ':obs' => $observacao,
            ];

            if ($hasMoedaPag) {
                $insertCols[] = 'moeda';
                $insertVals[] = ':moeda';
                $params[':moeda'] = ($moeda !== '' ? $moeda : 'USD');
            }

            $sql = 'INSERT INTO comissao_pagamentos (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')';
            $st = $pdo->prepare($sql);
            $st->execute($params);
        } catch (\Exception $e) {
        }

        $this->redirect('/admin/pagamentos/comissoes-gerais?janela_id=' . (int) $janelaId);
    }

    public function aprovarPagamentoComissaoGeral(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
        $admin = $auth->getUsuarioLogado();

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            $this->redirect('/admin/pagamentos/comissoes-gerais');
        }

        $janelaId = 0;
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $st = $pdo->prepare('SELECT janela_id FROM comissao_pagamentos WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $janelaId = (int) ($st->fetchColumn() ?: 0);

            $cols = [];
            try {
                $stCols = $pdo->query('DESCRIBE comissao_pagamentos');
                $cols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $set = ['status = \'aprovado\'', 'aprovado_em = NOW()'];
            $params = [':id' => $id];
            if (is_array($cols) && in_array('aprovado_por', $cols, true)) {
                $set[] = 'aprovado_por = :aprovado_por';
                $params[':aprovado_por'] = (int) ($admin['id'] ?? 0);
            }
            $sql = 'UPDATE comissao_pagamentos SET ' . implode(', ', $set) . ' WHERE id = :id';
            $st = $pdo->prepare($sql);
            $st->execute($params);
        } catch (\Exception $e) {
        }

        $this->redirect('/admin/pagamentos/comissoes-gerais' . ($janelaId > 0 ? ('?janela_id=' . (int) $janelaId) : ''));
    }

    public function deletarPagamentoComissaoGeral(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            $this->redirect('/admin/pagamentos/comissoes-gerais');
        }

        $janelaId = 0;
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $st = $pdo->prepare('SELECT janela_id FROM comissao_pagamentos WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $janelaId = (int) ($st->fetchColumn() ?: 0);

            $st = $pdo->prepare('UPDATE comissao_pagamentos SET deleted_at = NOW() WHERE id = :id');
            $st->execute([':id' => $id]);
        } catch (\Exception $e) {
        }

        $this->redirect('/admin/pagamentos/comissoes-gerais' . ($janelaId > 0 ? ('?janela_id=' . (int) $janelaId) : ''));
    }
    
    public function confirmarPagamento(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
        $pedidoId = $request->getParam('id');
        
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            // Atualizar status do pagamento
            $stmt = $pdo->prepare("
                UPDATE pagamentos 
                SET status = 'aprovado', data_pagamento = NOW() 
                WHERE pedido_id = :pedido_id
            ");
            $stmt->bindParam(':pedido_id', $pedidoId);
            $stmt->execute();
            
            // Atualizar status do pedido
            $stmt = $pdo->prepare("
                UPDATE pedidos 
                SET status = 'pago', updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $pedidoId);
            $stmt->execute();
            
            $pdo->commit();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    public function configuracoes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
        try {
            $pdo = \Config\Database::getConnection();

            $config = [];
            $keys = [
                'stripe_public_key',
                'stripe_secret_key',
                'stripe_webhook_secret',
                'stripe_enabled',
                'cambioreal_enabled',
                'cambioreal_app_id',
                'cambioreal_app_public',
                'cambioreal_app_secret',
                'cambioreal_base_url',
                'cambioreal_taxas_app_id',
                'cambioreal_taxas_app_public',
                'cambioreal_taxas_app_secret',
                'mercadopago_access_token',
                'mercadopago_public_key',
                'mercadopago_client_id',
                'mercadopago_client_secret',
                'mercadopago_enabled',
                'pix_key',
                'pix_key_type',
                'pix_enabled',
                'default_currency',
                'default_payment_method',
                'carne_ativo',
            ];

            if ($this->tableExistsPdo($pdo, 'configuracoes_sistema')) {
                foreach ($keys as $k) {
                    $v = $this->getConfigValueFromKeyValueTable($pdo, 'configuracoes_sistema', 'pagamentos', (string) $k);
                    if ($v !== null) {
                        $config[$k] = $v;
                    }
                }
            }

            // Carnê: buscar diretamente da configuracoes_sistema (chave simples, sem categoria)
            if (empty($config['carne_ativo'])) {
                try {
                    $stCarne = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_ativo' LIMIT 1");
                    $stCarne->execute();
                    $vCarne = $stCarne->fetchColumn();
                    if ($vCarne !== false && $vCarne !== null) {
                        $config['carne_ativo'] = (string) $vCarne;
                    }
                } catch (\Exception $e) {}
            }

            if ($this->tableExistsPdo($pdo, 'configuracoes')) {
                foreach ($keys as $k) {
                    if (array_key_exists($k, $config)) {
                        continue;
                    }
                    $v = $this->getConfigValueFromKeyValueTable($pdo, 'configuracoes', 'pagamentos', (string) $k);
                    if ($v === null) {
                        try {
                            $st = $pdo->prepare("SELECT valor FROM configuracoes WHERE categoria = 'pagamento' AND chave = ? LIMIT 1");
                            $st->execute([(string) $k]);
                            $vv = $st->fetchColumn();
                            if ($vv !== false && $vv !== null) {
                                $v = (string) $vv;
                            }
                        } catch (\Exception $e) {
                        }
                    }
                    if ($v !== null) {
                        $config[$k] = $v;
                    }
                }
            }
            
        } catch (\Exception $e) {
            $config = [];
        }
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações de Pagamento - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="sidebar-brand-text mx-3">Braziliana Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>Usuários</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/pagamentos"><i class="fas fa-fw fa-credit-card"></i><span>Pagamentos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/configuracoes"><i class="fas fa-fw fa-cog"></i><span>Configurações</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Sair</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">Configurações de Pagamento</h1>
                    <a href="/admin/pagamentos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
                
                <form method="POST" action="/admin/pagamentos/salvar-configuracoes">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Stripe</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Chave Pública</label>
                                        <input type="text" class="form-control" name="stripe_public_key" value="' . htmlspecialchars($config['stripe_public_key'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Chave Secreta</label>
                                        <input type="password" class="form-control" name="stripe_secret_key" value="' . htmlspecialchars($config['stripe_secret_key'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Webhook Secret</label>
                                        <input type="password" class="form-control" name="stripe_webhook_secret" value="' . htmlspecialchars($config['stripe_webhook_secret'] ?? '') . '">
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="stripe_enabled" ' . ($config['stripe_enabled'] ?? false ? 'checked' : '') . '>
                                        <label class="form-check-label">Habilitar Stripe</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Câmbio Real</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Base URL</label>
                                        <input type="text" class="form-control" name="cambioreal_base_url" value="' . htmlspecialchars($config['cambioreal_base_url'] ?? '') . '" placeholder="https://sandbox.cambioreal.com">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">APP ID</label>
                                        <input type="text" class="form-control" name="cambioreal_app_id" value="' . htmlspecialchars($config['cambioreal_app_id'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">APP Secret</label>
                                        <input type="password" class="form-control" name="cambioreal_app_secret" value="' . htmlspecialchars($config['cambioreal_app_secret'] ?? '') . '">
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="cambioreal_enabled" ' . (!empty($config['cambioreal_enabled']) && (string) $config['cambioreal_enabled'] !== '0' ? 'checked' : '') . '>
                                        <label class="form-check-label">Habilitar Câmbio Real</label>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4 border-info">
                                <div class="card-header bg-info bg-opacity-10">
                                    <h5 class="mb-0">Câmbio Real Taxas <small class="text-muted fs-6">(taxa de serviço e impostos)</small></h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">APP ID</label>
                                        <input type="text" class="form-control" name="cambioreal_taxas_app_id" value="' . htmlspecialchars($config['cambioreal_taxas_app_id'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">APP Public</label>
                                        <input type="text" class="form-control" name="cambioreal_taxas_app_public" value="' . htmlspecialchars($config['cambioreal_taxas_app_public'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">APP Secret</label>
                                        <input type="password" class="form-control" name="cambioreal_taxas_app_secret" value="' . htmlspecialchars($config['cambioreal_taxas_app_secret'] ?? '') . '">
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label text-muted small">Webhook URL</label>
                                        <input type="text" class="form-control form-control-sm" value="/webhook/cambioreal-taxas" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Mercado Pago</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Access Token</label>
                                        <input type="password" class="form-control" name="mercadopago_access_token" value="' . htmlspecialchars($config['mercadopago_access_token'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Public Key</label>
                                        <input type="text" class="form-control" name="mercadopago_public_key" value="' . htmlspecialchars($config['mercadopago_public_key'] ?? '') . '">
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="mercadopago_enabled" ' . ($config['mercadopago_enabled'] ?? false ? 'checked' : '') . '>
                                        <label class="form-check-label">Habilitar Mercado Pago</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">PIX</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Chave PIX</label>
                                        <input type="text" class="form-control" name="pix_key" value="' . htmlspecialchars($config['pix_key'] ?? '') . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tipo da Chave</label>
                                        <select class="form-select" name="pix_key_type">
                                            <option value="cpf" ' . (($config['pix_key_type'] ?? '') === 'cpf' ? 'selected' : '') . '>CPF</option>
                                            <option value="cnpj" ' . (($config['pix_key_type'] ?? '') === 'cnpj' ? 'selected' : '') . '>CNPJ</option>
                                            <option value="email" ' . (($config['pix_key_type'] ?? '') === 'email' ? 'selected' : '') . '>Email</option>
                                            <option value="telefone" ' . (($config['pix_key_type'] ?? '') === 'telefone' ? 'selected' : '') . '>Telefone</option>
                                            <option value="aleatoria" ' . (($config['pix_key_type'] ?? '') === 'aleatoria' ? 'selected' : '') . '>Aleatória</option>
                                        </select>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pix_enabled" ' . ($config['pix_enabled'] ?? false ? 'checked' : '') . '>
                                        <label class="form-check-label">Habilitar PIX</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Configurações Gerais</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Moeda Padrão</label>
                                        <select class="form-select" name="default_currency">
                                            <option value="BRL" ' . (($config['default_currency'] ?? 'BRL') === 'BRL' ? 'selected' : '') . '>Real (BRL)</option>
                                            <option value="USD" ' . (($config['default_currency'] ?? '') === 'USD' ? 'selected' : '') . '>Dólar (USD)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Método Padrão</label>
                                        <select class="form-select" name="default_payment_method">
                                            <option value="cartao" ' . (($config['default_payment_method'] ?? '') === 'cartao' ? 'selected' : '') . '>Cartão de Crédito</option>
                                            <option value="boleto" ' . (($config['default_payment_method'] ?? '') === 'boleto' ? 'selected' : '') . '>Boleto</option>
                                            <option value="pix" ' . (($config['default_payment_method'] ?? '') === 'pix' ? 'selected' : '') . '>PIX</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Carnê Braziliana</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Controla se o método de pagamento Carnê Braziliana aparece no checkout para novas compras. Desativar não afeta carnês já existentes.</p>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="carne_ativo" value="0">
                                        <input class="form-check-input" type="checkbox" name="carne_ativo" value="1" id="carne_ativo" ' . ((!empty($config['carne_ativo']) && (string) $config['carne_ativo'] !== '0') ? 'checked' : '') . '>
                                        <label class="form-check-label" for="carne_ativo">Exibir Carnê Braziliana no Checkout</label>
                                    </div>
                                    <div class="mt-3">
                                        <a href="/admin/carnes/configuracoes" class="btn btn-sm btn-outline-primary"><i class="fas fa-cog"></i> Configurações avançadas do Carnê</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Configurações
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function salvarConfiguracoes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $dados = $request->getParams();
        $keys = [
            'stripe_public_key',
            'stripe_secret_key',
            'stripe_webhook_secret',
            'stripe_enabled',
            'cambioreal_enabled',
            'cambioreal_app_id',
            'cambioreal_app_public',
            'cambioreal_app_secret',
            'cambioreal_base_url',
            'cambioreal_taxas_app_id',
            'cambioreal_taxas_app_public',
            'cambioreal_taxas_app_secret',
            'mercadopago_access_token',
            'mercadopago_public_key',
            'mercadopago_client_id',
            'mercadopago_client_secret',
            'mercadopago_enabled',
            'pix_key',
            'pix_key_type',
            'pix_enabled',
            'default_currency',
            'default_payment_method',
            'carne_ativo',
        ];

        try {
            $pdo = \Config\Database::getConnection();
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            $table = null;
            if ($this->tableExistsPdo($pdo, 'configuracoes_sistema')) {
                $table = 'configuracoes_sistema';
            } elseif ($this->tableExistsPdo($pdo, 'configuracoes')) {
                $table = 'configuracoes';
            }
            if ($table === null) {
                throw new \Exception('Tabela de configurações não encontrada');
            }

            foreach ($keys as $k) {
                $val = $dados[$k] ?? '';
                if (in_array($k, ['stripe_enabled', 'mercadopago_enabled', 'pix_enabled', 'cambioreal_enabled', 'carne_ativo'], true)) {
                    $val = !empty($dados[$k]) ? '1' : '0';
                }
                $this->saveConfigValueToKeyValueTable($pdo, $table, 'pagamentos', (string) $k, is_string($val) ? $val : (string) $val);
            }

            // Carnê: salvar também diretamente na configuracoes_sistema (chave simples)
            // para que o CarneService::isCarneDisponivel() funcione corretamente
            try {
                $carneVal = !empty($dados['carne_ativo']) ? '1' : '0';
                $stCarne = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = 'carne_ativo'");
                $stCarne->execute([$carneVal]);
                if ($stCarne->rowCount() === 0) {
                    $stCarne = $pdo->prepare("INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES ('carne_ativo', ?)");
                    $stCarne->execute([$carneVal]);
                }
            } catch (\Exception $e) {}

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            header('Location: /admin/pagamentos/configuracoes');
            exit;
        } catch (\Exception $e) {
            try {
                if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Exception $e2) {
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}
