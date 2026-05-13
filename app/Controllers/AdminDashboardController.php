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
                $stats['faturamento_brl'] = $fatBrlAdmin;
                $stats['faturamento_usd'] = $fatUsdAdmin;
                $partsAdmin = [];
                if ($fatBrlAdmin > 0) $partsAdmin[] = 'R$ ' . number_format($fatBrlAdmin, 2, ',', '.');
                if ($fatUsdAdmin > 0) $partsAdmin[] = 'US$ ' . number_format($fatUsdAdmin, 2, ',', '.');
                $stats['faturamento_display'] = !empty($partsAdmin) ? implode('<br>', $partsAdmin) : 'R$ 0,00';
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
                $stats['faturamento_brl'] = $fatBrl;
                $stats['faturamento_usd'] = $fatUsd;
                $parts = [];
                if ($fatBrl > 0) $parts[] = 'R$ ' . number_format($fatBrl, 2, ',', '.');
                if ($fatUsd > 0) $parts[] = 'US$ ' . number_format($fatUsd, 2, ',', '.');
                $stats['faturamento_display'] = !empty($parts) ? implode('<br>', $parts) : 'R$ 0,00';
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
                    <h1 class="page-title">Dashboard</h1>
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
                                <a href="/admin/redirecionamento/envios/novo" class="text-decoration-none">
                                    <div class="card quick-action-card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-plus fa-3x mb-3"></i>
                                            <h5 class="card-title">Novo Envio</h5>
                                            <p class="card-text small">Criar envio e gerar etiqueta</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <a href="/admin/redirecionamento/envios" class="text-decoration-none">
                                    <div class="card quick-action-card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-truck-fast fa-3x mb-3"></i>
                                            <h5 class="card-title">Meus Envios</h5>
                                            <p class="card-text small">Acompanhar envios</p>
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
                                <a href="/admin/redirecionamento/envios" class="btn btn-sm btn-outline-primary">Ver todos</a>
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
                    $hrefPedido = $pid > 0 ? ('/admin/pedidos/detalhes/' . $pid) : '#';
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/dashboard-redesign.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('dashboard');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="dashboard-page">';

        // === HEADER ===
        echo '<header class="page-header">
                <div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Resumo operacional, financeiro e comercial da empresa</p>
                </div>
                <button class="btn-dash-primary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </header>';

        // === CALENDÁRIO DE MARKETING ===
        echo '<section class="section-card">
                <header class="section-card-header">
                    <h2 class="section-title">Calendário de Marketing</h2>
                </header>
                <div class="section-body">';
        include __DIR__ . '/../Views/admin/partials/marketing_calendar_widget.php';
        echo '</div></section>';

        // === KPI GRID ===
        echo '<section class="kpi-grid">
                <article class="kpi-card">
                    <div>
                        <div class="kpi-label">Produtos</div>
                        <div class="kpi-value">' . (int)$stats['produtos_total'] . '</div>
                        <div class="kpi-subtext">' . (int)$stats['produtos_ativos'] . ' ativos</div>
                    </div>
                    <div class="kpi-icon"><i class="bi bi-box-seam-fill"></i></div>
                </article>
                <article class="kpi-card">
                    <div>
                        <div class="kpi-label">Pedidos</div>
                        <div class="kpi-value">' . (int)$stats['pedidos_total'] . '</div>
                        <div class="kpi-subtext">Total</div>
                    </div>
                    <div class="kpi-icon"><i class="bi bi-cart-fill"></i></div>
                </article>
                <article class="kpi-card">
                    <div>
                        <div class="kpi-label">Usuários</div>
                        <div class="kpi-value">' . number_format((int)$stats['usuarios_total']) . '</div>
                        <div class="kpi-subtext">Cadastrados</div>
                    </div>
                    <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
                </article>
                <article class="kpi-card is-featured">
                    <div>
                        <div class="kpi-label">Faturamento BRL</div>
                        <div class="kpi-value">R$ ' . number_format((float)($stats['faturamento_brl'] ?? 0), 2, ',', '.') . '</div>
                        <div class="kpi-subtext">Pedidos pagos</div>
                    </div>
                    <div class="kpi-icon"><i class="bi bi-currency-dollar"></i></div>
                </article>
                <article class="kpi-card is-featured">
                    <div>
                        <div class="kpi-label">Faturamento USD</div>
                        <div class="kpi-value">US$ ' . number_format((float)($stats['faturamento_usd'] ?? 0), 2, ',', '.') . '</div>
                        <div class="kpi-subtext">Pedidos pagos</div>
                    </div>
                    <div class="kpi-icon"><i class="bi bi-currency-dollar"></i></div>
                </article>
            </section>';

        // === RESUMO GERENCIAL ===
        echo '<section class="executive-grid">
                <article class="section-card">
                    <header class="section-card-header">
                        <h2 class="section-title">Resumo Gerencial</h2>
                    </header>
                    <div class="section-body">
                        <div class="summary-list">
                            <div class="summary-item">
                                <div class="summary-label">Faturamento total</div>
                                <div class="summary-value">' . ($stats['faturamento_display'] ?: 'R$ 0,00') . '</div>
                                <div class="summary-note">Pedidos pagos e processados</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Pedidos totais</div>
                                <div class="summary-value">' . (int)$stats['pedidos_total'] . '</div>
                                <div class="summary-note">Todos os status</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Produtos ativos</div>
                                <div class="summary-value">' . (int)$stats['produtos_ativos'] . '</div>
                                <div class="summary-note">Disponíveis para venda</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Usuários cadastrados</div>
                                <div class="summary-value">' . number_format((int)$stats['usuarios_total']) . '</div>
                                <div class="summary-note">Total na plataforma</div>
                            </div>
                        </div>
                    </div>
                </article>
                <article class="section-card">
                    <header class="section-card-header">
                        <h2 class="section-title">Pontos de Atenção</h2>
                    </header>
                    <div class="section-body">
                        <div class="insight-list">
                            <div class="insight-item">
                                <div class="insight-icon"><i class="bi bi-cash-stack"></i></div>
                                <div>
                                    <div class="insight-title">Validar pedidos não pagos</div>
                                    <div class="insight-text">Pedidos pendentes não devem entrar no total financeiro realizado.</div>
                                </div>
                            </div>
                            <div class="insight-item">
                                <div class="insight-icon"><i class="bi bi-box-seam"></i></div>
                                <div>
                                    <div class="insight-title">Acompanhar estoque e validade</div>
                                    <div class="insight-text">Produtos próximos do vencimento precisam continuar destacados no painel.</div>
                                </div>
                            </div>
                            <div class="insight-item">
                                <div class="insight-icon"><i class="bi bi-graph-up-arrow"></i></div>
                                <div>
                                    <div class="insight-title">Monitorar produtos campeões</div>
                                    <div class="insight-text">Use os mais vendidos para direcionar lives, campanhas e reposição.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>';

        // === PENDÊNCIAS DE PAGAMENTO ===
        if ($pendencias_pagamento_total > 0) {
            echo '<section class="section-card" style="border-color:var(--red-bg);">
                <header class="section-card-header">
                    <h2 class="section-title">Pendências de pagamento</h2>
                    <a href="/admin/pedidos" class="btn-dash-secondary" style="height:30px;padding:0 10px;font-size:12px;">Ver pedidos</a>
                </header>
                <div class="section-body">
                    <div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Pedido</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead><tbody>';
            foreach ($pendencias_pagamento as $pp) {
                $pid = (int)($pp['id'] ?? 0);
                $codigo = (string)($pp['codigo_pedido'] ?? $pid);
                $valor = (float)($pp['payment_diferenca_valor'] ?? 0);
                $st = (string)($pp['payment_diferenca_status'] ?? '');
                $link = (string)($pp['payment_diferenca_bank_slip_url'] ?? '');
                if ($link === '') $link = (string)($pp['payment_diferenca_invoice_url'] ?? '');
                echo '<tr><td><a href="/admin/pedidos/detalhes/' . $pid . '">#' . htmlspecialchars($codigo) . '</a></td>'
                    . '<td><strong>R$ ' . number_format($valor, 2, ',', '.') . '</strong></td>'
                    . '<td>' . ($st !== '' ? '<span class="badge-yellow" style="padding:2px 8px;border-radius:4px;">' . htmlspecialchars($st) . '</span>' : '-') . '</td>'
                    . '<td><a href="/admin/pedidos/detalhes/' . $pid . '">Detalhes</a>'
                    . ($link !== '' ? ' <a href="' . htmlspecialchars($link) . '" target="_blank">Cobrança</a>' : '')
                    . '</td></tr>';
            }
            echo '</tbody></table></div></div></section>';
        }

        // === VALIDADE ===
        echo '<section class="validity-card">
                <header class="validity-header">
                    <div class="validity-title"><i class="bi bi-exclamation-triangle-fill"></i> Validade (próximos 30 dias)</div>
                    <a href="/admin/estoque" class="btn-dash-secondary" style="height:30px;padding:0 10px;font-size:12px;">Ver Estoque</a>
                </header>
                <div class="validity-body">';

        if (empty($validade_alertas)) {
            echo 'Nenhum produto com validade a vencer nos próximos 30 dias.';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Produto</th><th>Validade</th><th>Qtd</th></tr></thead><tbody>';
            foreach ($validade_alertas as $va) {
                $produtoNomeV = (string)($va['produto_nome'] ?? '');
                $validadeV = (string)($va['validade_mais_proxima'] ?? '');
                $qtdV = (int)($va['quantidade_total'] ?? 0);
                $diasV = $validadeV !== '' ? (int)floor((strtotime($validadeV) - strtotime(date('Y-m-d'))) / 86400) : null;
                $badgeClass = 'badge-navy';
                if ($diasV !== null && $diasV <= 7) $badgeClass = 'badge-yellow';
                elseif ($diasV !== null && $diasV <= 15) $badgeClass = 'badge-yellow';
                echo '<tr><td>' . htmlspecialchars($produtoNomeV) . '</td>'
                    . '<td><span class="' . $badgeClass . '" style="padding:2px 8px;border-radius:4px;">' . ($validadeV !== '' ? date('d/m/Y', strtotime($validadeV)) : '-') . '</span>'
                    . ($diasV !== null ? ' <small>(' . $diasV . ' dias)</small>' : '') . '</td>'
                    . '<td>' . $qtdV . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '</div></section>';

        // === AÇÕES RÁPIDAS ===
        echo '<h2 class="quick-title">Ações Rápidas</h2>
            <section class="quick-grid">
                <a href="/admin/produtos/novo" class="quick-card">
                    <div>
                        <div class="quick-icon"><i class="bi bi-plus-lg"></i></div>
                        <div class="quick-label">Novo Produto</div>
                        <div class="quick-subtext">Adicionar produto</div>
                    </div>
                </a>
                <a href="/admin/pedidos" class="quick-card">
                    <div>
                        <div class="quick-icon"><i class="bi bi-cart-fill"></i></div>
                        <div class="quick-label">Pedidos</div>
                        <div class="quick-subtext">Gerenciar pedidos</div>
                    </div>
                </a>
                <a href="/admin/usuarios" class="quick-card">
                    <div>
                        <div class="quick-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="quick-label">Usuários</div>
                        <div class="quick-subtext">Gerenciar clientes</div>
                    </div>
                </a>
                <a href="/admin/configuracoes" class="quick-card">
                    <div>
                        <div class="quick-icon"><i class="bi bi-gear-fill"></i></div>
                        <div class="quick-label">Configurações</div>
                        <div class="quick-subtext">Configurar loja</div>
                    </div>
                </a>
            </section>';

        // === PEDIDOS RECENTES + PRODUTOS MAIS VENDIDOS ===
        echo '<section class="bottom-grid">
                <article class="list-card">
                    <header class="list-card-header">
                        <div class="list-card-title">Pedidos Recentes</div>
                        <a href="/admin/pedidos" class="btn-dash-secondary" style="height:30px;padding:0 10px;font-size:12px;">Ver Todos</a>
                    </header>
                    <div class="list-card-body">';

        if (!empty($pedidos_recentes)) {
            foreach ($pedidos_recentes as $pedido) {
                $valorTotalPedido = 0;
                if (isset($pedido['valor_total'])) $valorTotalPedido = floatval($pedido['valor_total']);
                elseif (isset($pedido['total'])) $valorTotalPedido = floatval($pedido['total']);
                elseif (isset($pedido['valor'])) $valorTotalPedido = floatval($pedido['valor']);
                $moedaPedido = strtoupper(trim((string)($pedido['moeda'] ?? $pedido['currency'] ?? 'BRL')));
                $statusPedido = strtolower(trim((string)($pedido['status'] ?? '')));
                $statusBadge = 'badge-yellow';
                if (in_array($statusPedido, ['pago','paid','approved','entregue'])) $statusBadge = 'badge-green';
                $statusLabels = [
                    'pago' => 'Pago', 'paid' => 'Pago', 'approved' => 'Aprovado',
                    'pendente' => 'Pendente', 'pending' => 'Pendente',
                    'pagamento' => 'Pagamento', 'processando' => 'Processando',
                    'enviado' => 'Enviado', 'entregue' => 'Entregue',
                    'cancelado' => 'Cancelado', 'cancelled' => 'Cancelado',
                    'carne_pagando' => 'Carnê Pagando', 'carne_braziliana' => 'Carnê',
                    'produto_consolidado' => 'Consolidado', 'consolidado' => 'Consolidado',
                    'rascunho_etiqueta' => 'Etiqueta Rascunho', 'etiqueta_efetivada' => 'Etiqueta Efetivada',
                    'aguardando_lib_alfandegaria' => 'Alfândega', 'finalizacao_embalagem' => 'Embalagem',
                    'entrega_finalizada' => 'Entrega Finalizada',
                ];
                $statusLabel = $statusLabels[$statusPedido] ?? ucfirst(str_replace('_', ' ', $statusPedido));

                echo '<div class="recent-order">
                    <div>
                        <div class="item-title">#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . ' - ' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</div>
                        <div class="item-subtext">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</div>
                    </div>
                    <div class="order-side">
                        <span class="' . $statusBadge . '" style="padding:3px 9px;border-radius:6px;font-size:11px;font-weight:650;">' . htmlspecialchars($statusLabel) . '</span>
                        <span class="item-value">' . ($moedaPedido === 'USD' ? 'US$ ' : 'R$ ') . number_format($valorTotalPedido, 2, ',', '.') . '</span>
                    </div>
                </div>';
            }
        } else {
            echo '<p style="color:var(--text-muted);text-align:center;padding:20px 0;">Nenhum pedido encontrado</p>';
        }

        echo '</div></article>
                <article class="list-card">
                    <header class="list-card-header">
                        <div class="list-card-title">Produtos Mais Vendidos</div>
                        <a href="/admin/produtos" class="btn-dash-secondary" style="height:30px;padding:0 10px;font-size:12px;">Ver Todos</a>
                    </header>
                    <div class="list-card-body">';

        if (!empty($produtos_mais_vendidos)) {
            foreach ($produtos_mais_vendidos as $produto) {
                echo '<div class="top-product">
                    <div>
                        <div class="item-title">' . htmlspecialchars($produto['nome']) . '</div>
                        <div class="item-subtext">' . (int)$produto['vendas'] . ' vendas</div>
                    </div>
                    <span class="badge-navy" style="padding:3px 9px;border-radius:6px;font-size:11px;font-weight:650;">' . (int)$produto['quantidade'] . ' unidades</span>
                </div>';
            }
        } else {
            echo '<p style="color:var(--text-muted);text-align:center;padding:20px 0;">Nenhuma venda encontrada</p>';
        }

        echo '</div></article>
            </section>';

        echo '</div></main></div></div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '</body></html>';
        exit;
    }
}
