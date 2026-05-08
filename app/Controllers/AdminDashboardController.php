<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminDashboardController extends Controller {

    private function tableExists(\PDO $pdo, $table) {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function columnExists(\PDO $pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function safeScalar(\PDO $pdo, $sql) {
        try {
            $stmt = $pdo->query($sql);
            return $stmt ? ($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $u = [];
        $isRedirecionador = false;
        $adminUid = 0;
        try {
            $pdo = \Config\Database::getConnection();

            $u = $auth->getUsuarioLogado();
            $perfilAtual = strtolower(trim((string) ($u['perfil'] ?? '')));
            $roleAtual = strtolower(trim((string) ($u['role'] ?? '')));
            $isRedirecionador = ($perfilAtual === 'redirecionador' || $roleAtual === 'redirecionador');
            $adminUid = (int) ($u['id'] ?? 0);
            
            // Estatísticas
            $stats = [];

            $pedidoTotalCol = $this->columnExists($pdo, 'pedidos', 'valor_total')
                ? 'valor_total'
                : ($this->columnExists($pdo, 'pedidos', 'total')
                    ? 'total'
                    : ($this->columnExists($pdo, 'pedidos', 'valor') ? 'valor' : null));

            $itensTable = $this->tableExists($pdo, 'pedido_itens')
                ? 'pedido_itens'
                : ($this->tableExists($pdo, 'itens_pedido')
                    ? 'itens_pedido'
                    : ($this->tableExists($pdo, 'pedido_items') ? 'pedido_items' : null));

            $produtoNomeCol = $this->columnExists($pdo, 'produtos', 'nome') ? 'nome' : ($this->columnExists($pdo, 'produtos', 'name') ? 'name' : null);

            $stats['faturamento_display'] = '';

            if (!$isRedirecionador) {
                $stats['produtos_total'] = $this->safeScalar($pdo, "SELECT COUNT(*) as total FROM produtos");

                $produtosAtivoCol = $this->columnExists($pdo, 'produtos', 'ativo') ? 'ativo' : ($this->columnExists($pdo, 'produtos', 'active') ? 'active' : null);
                if ($produtosAtivoCol) {
                    $stats['produtos_ativos'] = $this->safeScalar($pdo, "SELECT COUNT(*) as total FROM produtos WHERE {$produtosAtivoCol} = 1");
                } else {
                    $stats['produtos_ativos'] = 0;
                }

                $stats['pedidos_total'] = $this->safeScalar($pdo, "SELECT COUNT(*) as total FROM pedidos");
                $stats['usuarios_total'] = $this->safeScalar($pdo, "SELECT COUNT(*) as total FROM usuarios");

                $fatBrlAdmin = 0.0;
                $fatUsdAdmin = 0.0;
                if ($pedidoTotalCol) {
                    $colMoedaAdmin = $this->columnExists($pdo, 'pedidos', 'moeda') ? 'moeda' : ($this->columnExists($pdo, 'pedidos', 'currency') ? 'currency' : null);
                    if ($colMoedaAdmin) {
                        try {
                            $stAdmin = $pdo->query("SELECT UPPER(COALESCE({$colMoedaAdmin},'BRL')) as moeda, COALESCE(SUM({$pedidoTotalCol}),0) as total FROM pedidos WHERE status = 'pago' GROUP BY UPPER(COALESCE({$colMoedaAdmin},'BRL'))");
                            $rowsAdmin = $stAdmin ? ($stAdmin->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                            foreach ($rowsAdmin as $r) {
                                $m = strtoupper(trim((string)($r['moeda'] ?? '')));
                                $v = (float)($r['total'] ?? 0);
                                if ($m === 'USD') { $fatUsdAdmin += $v; } else { $fatBrlAdmin += $v; }
                            }
                        } catch (\Exception $e) {
                            $fatBrlAdmin = (float)$this->safeScalar($pdo, "SELECT COALESCE(SUM({$pedidoTotalCol}),0) as total FROM pedidos WHERE status = 'pago'");
                        }
                    } else {
                        $fatBrlAdmin = (float)$this->safeScalar($pdo, "SELECT COALESCE(SUM({$pedidoTotalCol}),0) as total FROM pedidos WHERE status = 'pago'");
                    }
                }
                $stats['faturamento_total'] = $fatBrlAdmin + $fatUsdAdmin;
                $partsAdmin = [];
                if ($fatBrlAdmin > 0) $partsAdmin[] = 'R$ ' . number_format($fatBrlAdmin, 2, ',', '.');
                if ($fatUsdAdmin > 0) $partsAdmin[] = 'US$ ' . number_format($fatUsdAdmin, 2, ',', '.');
                $stats['faturamento_display'] = !empty($partsAdmin) ? implode(' / ', $partsAdmin) : 'R$ 0,00';
            } else {
                $colAdminCriador = null;
                foreach (['admin_criador_id', 'admin_creator_id', 'created_by_admin_id', 'criador_admin_id', 'admin_id'] as $c) {
                    if ($this->columnExists($pdo, 'pedidos', $c)) {
                        $colAdminCriador = $c;
                        break;
                    }
                }

                $scopeWhere = '1=0';
                $scopeParams = [];
                if ($adminUid > 0 && $colAdminCriador) {
                    $scopeWhere = 'p.' . $colAdminCriador . ' = :admin_uid';
                    $scopeParams[':admin_uid'] = $adminUid;
                }

                $stats['pedidos_total'] = 0;
                $stats['usuarios_total'] = 0;
                $stats['produtos_total'] = 0;
                $stats['produtos_ativos'] = 0;
                $stats['faturamento_total'] = 0;

                try {
                    $st = $pdo->prepare('SELECT COUNT(*) as total FROM pedidos p WHERE ' . $scopeWhere);
                    $st->execute($scopeParams);
                    $stats['pedidos_total'] = (int) (($st->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
                } catch (\Exception $e) {
                    $stats['pedidos_total'] = 0;
                }

                try {
                    $st = $pdo->prepare('SELECT COUNT(DISTINCT p.usuario_id) as total FROM pedidos p WHERE ' . $scopeWhere);
                    $st->execute($scopeParams);
                    $stats['usuarios_total'] = (int) (($st->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
                } catch (\Exception $e) {
                    $stats['usuarios_total'] = 0;
                }

                if ($itensTable) {
                    try {
                        $st = $pdo->prepare('SELECT COUNT(DISTINCT ip.produto_id) as total FROM ' . $itensTable . ' ip JOIN pedidos p ON ip.pedido_id = p.id WHERE ' . $scopeWhere);
                        $st->execute($scopeParams);
                        $stats['produtos_total'] = (int) (($st->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
                        $stats['produtos_ativos'] = (int) $stats['produtos_total'];
                    } catch (\Exception $e) {
                        $stats['produtos_total'] = 0;
                        $stats['produtos_ativos'] = 0;
                    }
                }

                $fatBrl = 0.0;
                $fatUsd = 0.0;

                if ($pedidoTotalCol) {
                    $paidParts = [];
                    if ($this->columnExists($pdo, 'pedidos', 'status')) {
                        $paidParts[] = "LOWER(COALESCE(p.status,'')) IN ('pago','paid','approved','aprovado')";
                    }
                    if ($this->columnExists($pdo, 'pedidos', 'payment_status')) {
                        $paidParts[] = "LOWER(COALESCE(p.payment_status,'')) IN ('approved','paid','pago','aprovado','confirmed','received','succeeded','success')";
                    }
                    $paidWhere = !empty($paidParts) ? (' AND (' . implode(' OR ', $paidParts) . ')') : '';

                    $colMoeda = $this->columnExists($pdo, 'pedidos', 'moeda') ? 'moeda' : ($this->columnExists($pdo, 'pedidos', 'currency') ? 'currency' : null);
                    if ($colMoeda) {
                        try {
                            $st = $pdo->prepare('SELECT UPPER(COALESCE(p.' . $colMoeda . ',\'BRL\')) as moeda, COALESCE(SUM(p.' . $pedidoTotalCol . '),0) as total FROM pedidos p WHERE ' . $scopeWhere . $paidWhere . ' GROUP BY UPPER(COALESCE(p.' . $colMoeda . ',\'BRL\'))');
                            $st->execute($scopeParams);
                            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                            foreach ($rows as $r) {
                                $m = strtoupper(trim((string) ($r['moeda'] ?? '')));
                                $v = (float) ($r['total'] ?? 0);
                                if ($m === 'USD') {
                                    $fatUsd += $v;
                                } else {
                                    $fatBrl += $v;
                                }
                            }
                        } catch (\Exception $e) {
                            $fatBrl = 0.0;
                            $fatUsd = 0.0;
                        }
                    } else {
                        try {
                            $st = $pdo->prepare('SELECT COALESCE(SUM(p.' . $pedidoTotalCol . '),0) as total FROM pedidos p WHERE ' . $scopeWhere . $paidWhere);
                            $st->execute($scopeParams);
                            $fatBrl = (float) (($st->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
                        } catch (\Exception $e) {
                            $fatBrl = 0.0;
                        }
                    }
                }

                $stats['faturamento_total'] = $fatBrl + $fatUsd;
                $parts = [];
                if ($fatBrl > 0) $parts[] = 'R$ ' . number_format($fatBrl, 2, ',', '.');
                if ($fatUsd > 0) $parts[] = 'US$ ' . number_format($fatUsd, 2, ',', '.');
                $stats['faturamento_display'] = !empty($parts) ? implode(' / ', $parts) : 'R$ 0,00';
            }
            
            // Pedidos recentes
            $usuarioNomeCol = $this->columnExists($pdo, 'usuarios', 'nome') ? 'nome' : ($this->columnExists($pdo, 'usuarios', 'name') ? 'name' : null);
            $pedidosSql = "SELECT p.*";
            if ($usuarioNomeCol) {
                $pedidosSql .= ", u.{$usuarioNomeCol} as cliente_nome";
            } else {
                $pedidosSql .= ", '' as cliente_nome";
            }
            if (!$isRedirecionador) {
                $pedidosSql .= " FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id ORDER BY p.created_at DESC LIMIT 5";
                $stmt = $pdo->query($pedidosSql);
                $pedidos_recentes = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            } else {
                $colAdminCriador = null;
                foreach (['admin_criador_id', 'admin_creator_id', 'created_by_admin_id', 'criador_admin_id', 'admin_id'] as $c) {
                    if ($this->columnExists($pdo, 'pedidos', $c)) {
                        $colAdminCriador = $c;
                        break;
                    }
                }
                $scopeWhere = '1=0';
                $scopeParams = [];
                if ($adminUid > 0 && $colAdminCriador) {
                    $scopeWhere = 'p.' . $colAdminCriador . ' = :admin_uid';
                    $scopeParams[':admin_uid'] = $adminUid;
                }

                $pedidosSql .= ' FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE ' . $scopeWhere . ' ORDER BY p.created_at DESC LIMIT 5';
                try {
                    $stmt = $pdo->prepare($pedidosSql);
                    $stmt->execute($scopeParams);
                    $pedidos_recentes = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) {
                    $pedidos_recentes = [];
                }
            }

            if ($itensTable && $produtoNomeCol) {
                if (!$isRedirecionador) {
                    $stmt = $pdo->query(" 
                        SELECT pr.{$produtoNomeCol} as nome, COUNT(ip.produto_id) as vendas, COALESCE(SUM(ip.quantidade),0) as quantidade
                        FROM {$itensTable} ip
                        JOIN produtos pr ON ip.produto_id = pr.id
                        JOIN pedidos p ON ip.pedido_id = p.id
                        WHERE p.status = 'pago'
                        GROUP BY pr.id, pr.{$produtoNomeCol}
                        ORDER BY vendas DESC
                        LIMIT 5
                    ");
                    $produtos_mais_vendidos = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
                } else {
                    $colAdminCriador = null;
                    foreach (['admin_criador_id', 'admin_creator_id', 'created_by_admin_id', 'criador_admin_id', 'admin_id'] as $c) {
                        if ($this->columnExists($pdo, 'pedidos', $c)) {
                            $colAdminCriador = $c;
                            break;
                        }
                    }
                    $scopeWhere = '1=0';
                    $scopeParams = [];
                    if ($adminUid > 0 && $colAdminCriador) {
                        $scopeWhere = 'p.' . $colAdminCriador . ' = :admin_uid';
                        $scopeParams[':admin_uid'] = $adminUid;
                    }

                    $paidParts = [];
                    if ($this->columnExists($pdo, 'pedidos', 'status')) {
                        $paidParts[] = "LOWER(COALESCE(p.status,'')) IN ('pago','paid','approved','aprovado')";
                    }
                    if ($this->columnExists($pdo, 'pedidos', 'payment_status')) {
                        $paidParts[] = "LOWER(COALESCE(p.payment_status,'')) IN ('approved','paid','pago','aprovado','confirmed','received','succeeded','success')";
                    }
                    $paidWhere = !empty($paidParts) ? (' AND (' . implode(' OR ', $paidParts) . ')') : '';

                    try {
                        $sql = "
                            SELECT pr.{$produtoNomeCol} as nome, COUNT(DISTINCT ip.pedido_id) as vendas, COALESCE(SUM(ip.quantidade),0) as quantidade
                            FROM {$itensTable} ip
                            JOIN produtos pr ON ip.produto_id = pr.id
                            JOIN pedidos p ON ip.pedido_id = p.id
                            WHERE {$scopeWhere}{$paidWhere}
                            GROUP BY pr.id, pr.{$produtoNomeCol}
                            ORDER BY quantidade DESC
                            LIMIT 5
                        ";
                        $st = $pdo->prepare($sql);
                        $st->execute($scopeParams);
                        $produtos_mais_vendidos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\Exception $e) {
                        $produtos_mais_vendidos = [];
                    }
                }
            } else {
                $produtos_mais_vendidos = [];
            }

            // Alertas: validade próxima (até 30 dias)
            $validade_alertas = [];
            $validade_alertas_pedidos = [];
            $pendencias_pagamento = [];
            $pendencias_pagamento_total = 0;
            $pendencias_pagamento_valor = 0.0;
            if (!$isRedirecionador && $this->tableExists($pdo, 'estoque_interno') && $produtoNomeCol) {
                try {
                    $stmt = $pdo->prepare("\
                        SELECT\
                            e.produto_id,\
                            pr.{$produtoNomeCol} AS produto_nome,\
                            MIN(e.data_validade) AS validade_mais_proxima,\
                            SUM(e.quantidade) AS quantidade_total\
                        FROM estoque_interno e\
                        JOIN produtos pr ON pr.id = e.produto_id\
                        WHERE e.quantidade > 0\
                          AND e.is_alimenticio = 1\
                          AND e.data_validade IS NOT NULL\
                          AND e.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)\
                        GROUP BY e.produto_id, pr.{$produtoNomeCol}\
                        ORDER BY validade_mais_proxima ASC\
                    ");
                    $stmt->execute();
                    $validade_alertas = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                    if (!empty($validade_alertas) && $itensTable && $this->tableExists($pdo, 'pedidos') && $this->tableExists($pdo, 'usuarios')) {
                        $ids = array_map(static fn($r) => (int) ($r['produto_id'] ?? 0), $validade_alertas);
                        $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
                        if (!empty($ids)) {
                            $placeholders = implode(',', array_fill(0, count($ids), '?'));
                            $userNomeCol = $usuarioNomeCol ?: ($this->columnExists($pdo, 'usuarios', 'nome') ? 'nome' : ($this->columnExists($pdo, 'usuarios', 'name') ? 'name' : null));
                            $userNomeExpr = $userNomeCol ? ('u.' . $userNomeCol) : "''";

                            $sqlPedidos = "\
                                SELECT\
                                    ip.produto_id,\
                                    p.id AS pedido_id,\
                                    p.created_at AS pedido_data,\
                                    p.status AS pedido_status,\
                                    u.id AS usuario_id,\
                                    {$userNomeExpr} AS usuario_nome\
                                FROM {$itensTable} ip\
                                JOIN pedidos p ON p.id = ip.pedido_id\
                                LEFT JOIN usuarios u ON u.id = p.usuario_id\
                                WHERE ip.produto_id IN ({$placeholders})\
                                ORDER BY p.created_at DESC\
                            ";
                            $stmtPedidos = $pdo->prepare($sqlPedidos);
                            $stmtPedidos->execute($ids);
                            $rowsPedidos = $stmtPedidos->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                            foreach ($rowsPedidos as $rp) {
                                $pid = (int) ($rp['produto_id'] ?? 0);
                                if ($pid <= 0) {
                                    continue;
                                }
                                if (!isset($validade_alertas_pedidos[$pid])) {
                                    $validade_alertas_pedidos[$pid] = [];
                                }
                                $validade_alertas_pedidos[$pid][] = $rp;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $validade_alertas = [];
                    $validade_alertas_pedidos = [];
                }
            }

            // Pendências de pagamento (diferença)
            try {
                if ($isRedirecionador) {
                    throw new \RuntimeException('skip');
                }
                if ($this->tableExists($pdo, 'pedidos')
                    && $this->columnExists($pdo, 'pedidos', 'payment_diferenca_id')
                    && $this->columnExists($pdo, 'pedidos', 'payment_diferenca_valor')
                    && $this->columnExists($pdo, 'pedidos', 'payment_diferenca_paid_at')) {
                    $sqlDif = "SELECT id, codigo_pedido, usuario_id, payment_diferenca_valor, payment_diferenca_status, payment_diferenca_invoice_url, payment_diferenca_bank_slip_url, payment_diferenca_created_at
                              FROM pedidos
                              WHERE payment_diferenca_id IS NOT NULL
                                AND payment_diferenca_id <> ''
                                AND COALESCE(payment_diferenca_valor,0) > 0
                                AND (payment_diferenca_paid_at IS NULL OR payment_diferenca_paid_at = '')
                              ORDER BY COALESCE(payment_diferenca_created_at, created_at) DESC
                              LIMIT 20";
                    $stmtDif = $pdo->query($sqlDif);
                    $pendencias_pagamento = $stmtDif ? ($stmtDif->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                    $pendencias_pagamento_total = is_array($pendencias_pagamento) ? count($pendencias_pagamento) : 0;
                    if ($pendencias_pagamento_total > 0) {
                        foreach ($pendencias_pagamento as $pp) {
                            $pendencias_pagamento_valor += (float) ($pp['payment_diferenca_valor'] ?? 0);
                        }
                    }
                }
            } catch (\Exception $e) {
                $pendencias_pagamento = [];
                $pendencias_pagamento_total = 0;
                $pendencias_pagamento_valor = 0.0;
            }
            
        } catch (\Exception $e) {
            $stats = ['produtos_total' => 0, 'produtos_ativos' => 0, 'pedidos_total' => 0, 'usuarios_total' => 0, 'faturamento_total' => 0];
            $pedidos_recentes = [];
            $produtos_mais_vendidos = [];
            $validade_alertas = [];
            $validade_alertas_pedidos = [];
            $pendencias_pagamento = [];
            $pendencias_pagamento_total = 0;
            $pendencias_pagamento_valor = 0.0;

            $isRedirecionador = false;
            $adminUid = 0;
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        if (!empty($isRedirecionador)) {
            echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

            renderAdminSidebarStyles();

            echo '<style>
        .stat-card { transition: none; }
        .quick-action-card { transition: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';

            renderAdminSidebar('dashboard');

            echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Produtos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . (int) ($stats['produtos_total'] ?? 0) . '</div>
                                        <div class="text-xs text-muted">Nos seus pedidos</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-box fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Pedidos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . (int) ($stats['pedidos_total'] ?? 0) . '</div>
                                        <div class="text-xs text-muted">Total</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Clientes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . (int) ($stats['usuarios_total'] ?? 0) . '</div>
                                        <div class="text-xs text-muted">Com pedidos</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Faturamento</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . htmlspecialchars((string) ($stats['faturamento_display'] ?? 'R$ 0,00')) . '</div>
                                        <div class="text-xs text-muted">Pedidos pagos</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="h5 mb-3">Ações Rápidas</h3>
                        <div class="row">
                            <div class="col-lg-4 col-md-6 mb-3">
                                <a href="/admin/correios-mundial" class="text-decoration-none">
                                    <div class="card quick-action-card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-globe fa-3x mb-3"></i>
                                            <h5 class="card-title">Correios Mundial</h5>
                                            <p class="card-text small">Gerar etiquetas PACKET</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <a href="/meus-pedidos" class="text-decoration-none">
                                    <div class="card quick-action-card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-shopping-bag fa-3x mb-3"></i>
                                            <h5 class="card-title">Meus Pedidos</h5>
                                            <p class="card-text small">Acompanhar pedidos</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <a href="/meus-dados" class="text-decoration-none">
                                    <div class="card quick-action-card bg-info text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user fa-3x mb-3"></i>
                                            <h5 class="card-title">Meus Dados</h5>
                                            <p class="card-text small">Atualizar cadastro</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Pedidos Recentes</h6>
                                <a href="/admin/correios-mundial" class="btn btn-sm btn-outline-primary">Correios Mundial</a>
                            </div>
                            <div class="card-body">';

            if (!empty($pedidos_recentes)) {
                foreach ($pedidos_recentes as $pedido) {
                    $valorTotalPedido = 0;
                    if (isset($pedido['valor_total'])) {
                        $valorTotalPedido = floatval($pedido['valor_total']);
                    } elseif (isset($pedido['total'])) {
                        $valorTotalPedido = floatval($pedido['total']);
                    } elseif (isset($pedido['valor'])) {
                        $valorTotalPedido = floatval($pedido['valor']);
                    }

                    $pid = (int) ($pedido['id'] ?? 0);
                    $hrefPedido = $pid > 0 ? ('/admin/correios-mundial/pedido/' . $pid) : '#';
                    $moedaPed = strtoupper(trim((string)($pedido['moeda'] ?? $pedido['currency'] ?? 'BRL')));
                    $simboloPed = ($moedaPed === 'USD') ? 'US$ ' : 'R$ ';

                    echo '<div class="d-flex justify-content-between align-items-center mb-2">'
                        . '<div>'
                        . '<strong><a class="text-decoration-none" href="' . htmlspecialchars($hrefPedido) . '">#' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</a></strong> - ' . htmlspecialchars((string) ($pedido['cliente_nome'] ?? 'Cliente'))
                        . (!empty($pedido['created_at']) ? ('<br><small class="text-muted">' . date('d/m/Y H:i', strtotime((string) $pedido['created_at'])) . '</small>') : '')
                        . '</div>'
                        . '<div class="text-end">'
                        . '<span class="badge bg-' . ((string) ($pedido['status'] ?? '') === 'pago' ? 'success' : 'warning') . '">' . htmlspecialchars(ucfirst((string) ($pedido['status'] ?? '-'))) . '</span>'
                        . '<br><strong>' . $simboloPed . number_format($valorTotalPedido, 2, ',', '.') . '</strong>'
                        . '</div>'
                        . '</div>';
                }
            } else {
                echo '<p class="text-muted text-center">Nenhum pedido encontrado</p>';
            }

            echo '</div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Produtos Mais Vendidos</h6>
                            </div>
                            <div class="card-body">';

            if (!empty($produtos_mais_vendidos)) {
                foreach ($produtos_mais_vendidos as $produto) {
                    echo '<div class="d-flex justify-content-between align-items-center mb-2">'
                        . '<div>'
                        . '<strong>' . htmlspecialchars((string) ($produto['nome'] ?? 'Produto')) . '</strong>'
                        . '<br><small class="text-muted">' . (int) ($produto['vendas'] ?? 0) . ' pedido(s)</small>'
                        . '</div>'
                        . '<div class="text-end">'
                        . '<span class="badge bg-info">' . (int) ($produto['quantidade'] ?? 0) . ' unidades</span>'
                        . '</div>'
                        . '</div>';
                }
            } else {
                echo '<p class="text-muted text-center">Nenhuma venda encontrada</p>';
            }

            echo '</div>
                        </div>
                    </div>
                </div>
        </main>
    </div>
    </div>';

            renderAdminScripts();

            echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
            exit;
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .stat-card { transition: none; }
        .quick-action-card { transition: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('dashboard');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync"></i> Atualizar
                        </button>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Produtos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['produtos_total'] . '</div>
                                        <div class="text-xs text-muted">' . $stats['produtos_ativos'] . ' ativos</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-box fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Pedidos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['pedidos_total'] . '</div>
                                        <div class="text-xs text-muted">Total</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Usuários</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . $stats['usuarios_total'] . '</div>
                                        <div class="text-xs text-muted">Cadastrados</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Faturamento</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">' . htmlspecialchars((string)($stats['faturamento_display'] ?: ('R$ ' . number_format((float)($stats['faturamento_total'] ?? 0), 2, ',', '.')))) . '</div>
                                        <div class="text-xs text-muted">Total</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        ' . ($pendencias_pagamento_total > 0 ? ('
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-circle-dollar-to-slot me-2"></i>Pendências de pagamento (diferença)</h6>
                                <a href="/admin/pedidos" class="btn btn-sm btn-outline-danger">Ver pedidos</a>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <span class="badge bg-danger">' . (int) $pendencias_pagamento_total . ' pendência(s)</span>
                                    <span class="badge bg-warning text-dark">R$ ' . number_format((float) $pendencias_pagamento_valor, 2, ',', '.') . '</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead><tr><th>Pedido</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
                                        <tbody>
                        '): '') . '
                        ';

        if (!empty($pendencias_pagamento)) {
            foreach ($pendencias_pagamento as $pp) {
                $pid = (int) ($pp['id'] ?? 0);
                $codigo = (string) ($pp['codigo_pedido'] ?? $pid);
                $valor = (float) ($pp['payment_diferenca_valor'] ?? 0);
                $st = (string) ($pp['payment_diferenca_status'] ?? '');
                $link = (string) ($pp['payment_diferenca_bank_slip_url'] ?? '');
                if ($link === '') {
                    $link = (string) ($pp['payment_diferenca_invoice_url'] ?? '');
                }
                echo '<tr>'
                    . '<td><a class="text-decoration-none" href="/admin/pedidos/detalhes/' . $pid . '">#' . htmlspecialchars($codigo) . '</a></td>'
                    . '<td><strong>R$ ' . number_format($valor, 2, ',', '.') . '</strong></td>'
                    . '<td>' . ($st !== '' ? '<span class="badge bg-warning text-dark">' . htmlspecialchars($st) . '</span>' : '-') . '</td>'
                    . '<td class="text-end">'
                        . '<a class="btn btn-sm btn-outline-primary" href="/admin/pedidos/detalhes/' . $pid . '">Detalhes</a> '
                        . ($link !== '' ? '<a class="btn btn-sm btn-outline-dark" href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener">Abrir cobrança</a>' : '')
                    . '</td>'
                    . '</tr>';
            }
        }

        echo ($pendencias_pagamento_total > 0)
            ? '</tbody></table></div></div></div>'
            : '';

        echo '
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-triangle-exclamation me-2"></i>Validade (próximos 30 dias)</h6>
                                <a href="/admin/estoque" class="btn btn-sm btn-outline-primary">Ver Estoque</a>
                            </div>
                            <div class="card-body">';

                            if (empty($validade_alertas)) {
                                echo '<p class="text-muted mb-0">Nenhum produto com validade a vencer nos próximos 30 dias.</p>';
                            } else {
                                $totalProdutos = count($validade_alertas);
                                $totalUnidades = 0;
                                foreach ($validade_alertas as $va) {
                                    $totalUnidades += (int) ($va['quantidade_total'] ?? 0);
                                }

                                echo '<div class="mb-3">'
                                    . '<span class="badge bg-warning">' . $totalProdutos . ' produto(s)</span> '
                                    . '<span class="badge bg-info">' . $totalUnidades . ' unidade(s)</span>'
                                    . '</div>';

                                echo '<div class="table-responsive">'
                                    . '<table class="table table-hover">'
                                    . '<thead><tr><th>Produto</th><th>Validade</th><th>Qtd</th><th>Pedidos que contém</th></tr></thead><tbody>';

                                foreach ($validade_alertas as $va) {
                                    $produtoId = (int) ($va['produto_id'] ?? 0);
                                    $produtoNome = (string) ($va['produto_nome'] ?? '');
                                    $validade = (string) ($va['validade_mais_proxima'] ?? '');
                                    $qtd = (int) ($va['quantidade_total'] ?? 0);
                                    $dias = null;
                                    if ($validade !== '') {
                                        $dias = (int) floor((strtotime($validade) - strtotime(date('Y-m-d'))) / 86400);
                                    }
                                    $badgeStyle = 'bg-info';
                                    if ($dias !== null && $dias <= 7) {
                                        $badgeStyle = 'bg-danger';
                                    } elseif ($dias !== null && $dias <= 15) {
                                        $badgeStyle = 'bg-warning';
                                    }

                                    $rowsP = $validade_alertas_pedidos[$produtoId] ?? [];
                                    $countPedidos = is_array($rowsP) ? count($rowsP) : 0;

                                    echo '<tr>'
                                        . '<td><strong>' . htmlspecialchars($produtoNome) . '</strong><br><small class="text-muted">ID: ' . $produtoId . '</small></td>'
                                        . '<td>'
                                        . '<span class="badge ' . $badgeStyle . '">' . ($validade !== '' ? date('d/m/Y', strtotime($validade)) : '-') . '</span>'
                                        . ($dias !== null ? '<br><small class="text-muted">em ' . $dias . ' dia(s)</small>' : '')
                                        . '</td>'
                                        . '<td><span class="badge bg-info">' . $qtd . '</span></td>'
                                        . '<td>';

                                    if ($countPedidos === 0) {
                                        echo '<span class="text-muted">Nenhum</span>';
                                    } else {
                                        echo '<div class="mb-1"><span class="badge bg-warning">' . $countPedidos . '</span></div>';
                                        echo '<div class="small">';
                                        foreach (array_slice($rowsP, 0, 6) as $rp) {
                                            $pedidoId = (int) ($rp['pedido_id'] ?? 0);
                                            $pedidoData = (string) ($rp['pedido_data'] ?? '');
                                            $usuarioId = (int) ($rp['usuario_id'] ?? 0);
                                            $usuarioNome = (string) ($rp['usuario_nome'] ?? '');
                                            $pedidoHref = '/admin/pedidos/detalhes/' . $pedidoId;
                                            $usuarioHref = '/admin/usuarios/detalhes/' . $usuarioId;
                                            echo '<div class="d-flex justify-content-between gap-2">'
                                                . '<div>'
                                                . '<a href="' . htmlspecialchars($pedidoHref) . '" class="text-decoration-none">Pedido #' . $pedidoId . '</a>'
                                                . ($pedidoData !== '' ? ' <span class="text-muted">(' . date('d/m/Y', strtotime($pedidoData)) . ')</span>' : '')
                                                . '</div>'
                                                . '<div>'
                                                . ($usuarioId > 0 ? '<a href="' . htmlspecialchars($usuarioHref) . '" class="text-decoration-none">' . htmlspecialchars($usuarioNome !== '' ? $usuarioNome : ('Cliente #' . $usuarioId)) . '</a>' : '<span class="text-muted">Cliente</span>')
                                                . '</div>'
                                                . '</div>';
                                        }
                                        if ($countPedidos > 6) {
                                            echo '<div class="text-muted">+ ' . ($countPedidos - 6) . ' pedido(s)</div>';
                                        }
                                        echo '</div>';
                                    }

                                    echo '</td></tr>';
                                }

                                echo '</tbody></table></div>';
                            }

                            echo '</div>
                        </div>
                    </div>
                </div>';

                            echo '<div class="row mb-4">
                    <div class="col-12">
                        <h3 class="h5 mb-3">Ações Rápidas</h3>
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/produtos/novo" class="text-decoration-none">
                                    <div class="card quick-action-card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-plus fa-3x mb-3"></i>
                                            <h5 class="card-title">Novo Produto</h5>
                                            <p class="card-text small">Adicionar produto</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/pedidos" class="text-decoration-none">
                                    <div class="card quick-action-card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                            <h5 class="card-title">Pedidos</h5>
                                            <p class="card-text small">Gerenciar pedidos</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/usuarios" class="text-decoration-none">
                                    <div class="card quick-action-card bg-info text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-users fa-3x mb-3"></i>
                                            <h5 class="card-title">Usuários</h5>
                                            <p class="card-text small">Gerenciar clientes</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="/admin/configuracoes" class="text-decoration-none">
                                    <div class="card quick-action-card bg-warning text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-cog fa-3x mb-3"></i>
                                            <h5 class="card-title">Configurações</h5>
                                            <p class="card-text small">Configurar loja</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Pedidos Recentes</h6>
                                <a href="/admin/pedidos" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                            </div>
                            <div class="card-body">';
                            
                            if (!empty($pedidos_recentes)) {
                                foreach ($pedidos_recentes as $pedido) {
                                    $valorTotalPedido = 0;
                                    if (isset($pedido['valor_total'])) {
                                        $valorTotalPedido = floatval($pedido['valor_total']);
                                    } elseif (isset($pedido['total'])) {
                                        $valorTotalPedido = floatval($pedido['total']);
                                    } elseif (isset($pedido['valor'])) {
                                        $valorTotalPedido = floatval($pedido['valor']);
                                    }

                                    echo '<div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong> - ' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '
                                            <br><small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-' . ($pedido['status'] == 'pago' ? 'success' : 'warning') . '">' . ucfirst($pedido['status']) . '</span>
                                            <br><strong>' . (strtoupper(trim((string)($pedido['moeda'] ?? $pedido['currency'] ?? 'BRL'))) === 'USD' ? 'US$ ' : 'R$ ') . number_format($valorTotalPedido, 2, ',', '.') . '</strong>
                                        </div>
                                    </div>';
                                }
                            } else {
                                echo '<p class="text-muted text-center">Nenhum pedido encontrado</p>';
                            }
                            
                            echo '</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Produtos Mais Vendidos</h6>
                                <a href="/admin/produtos" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                            </div>
                            <div class="card-body">';
                            
                            if (!empty($produtos_mais_vendidos)) {
                                foreach ($produtos_mais_vendidos as $produto) {
                                    echo '<div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong>' . htmlspecialchars($produto['nome']) . '</strong>
                                            <br><small class="text-muted">' . $produto['vendas'] . ' vendas</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info">' . $produto['quantidade'] . ' unidades</span>
                                        </div>
                                    </div>';
                                }
                            } else {
                                echo '<p class="text-muted text-center">Nenhuma venda encontrada</p>';
                            }
                            
                            echo '</div>
                        </div>
                    </div>
                </div>';

        // Calendário de Marketing
        echo '<div class="row mb-4"><div class="col-12">';
        include __DIR__ . '/../Views/admin/partials/marketing_calendar_widget.php';
        echo '</div></div>';

        echo '</div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }
}
