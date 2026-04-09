<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar -->
        <?php $activePage = 'pedidos'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 user-page-header">
                <div>
                    <h2 class="mb-1"><?= __('user_orders.title', 'Meus Pedidos') ?></h2>
                    <p class="text-muted mb-0"><?= __('user_orders.subtitle', 'Histórico completo dos seus pedidos') ?></p>
                </div>
                <div class="d-flex gap-2 user-page-actions">
                    <div class="input-group" style="width: 250px;">
                        <input type="text" class="form-control" placeholder="<?= htmlspecialchars(__('user_orders.search_placeholder', 'Buscar pedido...'), ENT_QUOTES, 'UTF-8') ?>" id="buscaPedido">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <select class="form-select" id="filtroStatus" style="width: 150px;">
                        <option value=""><?= __('user_orders.filter.all_status', 'Todos Status') ?></option>
                        <?php
                            $normalizeStatusKey = function($st) {
                                $s = is_string($st) ? $st : '';
                                $s = trim($s);
                                if ($s === '') return '';
                                if (function_exists('mb_strtolower')) {
                                    $s = mb_strtolower($s, 'UTF-8');
                                } else {
                                    $s = strtolower($s);
                                }
                                if (function_exists('iconv')) {
                                    $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
                                    if ($t !== false && is_string($t) && $t !== '') {
                                        $s = $t;
                                    }
                                }
                                $s = preg_replace('/[^a-z0-9]+/', '_', $s);
                                $s = preg_replace('/_+/', '_', $s);
                                $s = trim($s, '_');
                                return (string) $s;
                            };

                            $presentStatusMap = [];
                            foreach (($pedidos ?? []) as $p) {
                                $stRaw = (string) ($p['status'] ?? '');
                                if ($stRaw === '') {
                                    $stRaw = (string) ($p['payment_status'] ?? ($p['status_pagamento'] ?? ''));
                                }
                                $stKey = $normalizeStatusKey($stRaw);
                                if ($stKey !== '') {
                                    $presentStatusMap[$stKey] = true;
                                }
                            }

                            $statusOptions = array_keys($presentStatusMap);
                            sort($statusOptions);

                            $statusLabels = [
                                'enviado' => __('order_status.label_generated', 'Etiqueta gerada'),
                            ];

                            foreach ($statusOptions as $key) {
                                $label = $statusLabels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
                                echo '<option value="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '">' . $label . '</option>';
                            }
                        ?>
                    </select>
                </div>
            </div>
            
            <!-- Stats Summary -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-1 fw-bold"><?= count($pedidos) ?></h4>
                                    <p class="text-muted small mb-0"><?= __('user_orders.stats.total_orders', 'Total de Pedidos') ?></p>
                                </div>
                                <div class="ms-3">
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-shopping-bag text-primary fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-1 fw-bold">
                                        <?php 
                                        $ativos = array_filter($pedidos, fn($p) => in_array($p['status'], ['pendente', 'processando', 'enviado']));
                                        echo count($ativos);
                                        ?>
                                    </h4>
                                    <p class="text-muted small mb-0"><?= __('user_orders.stats.active_orders', 'Pedidos Ativos') ?></p>
                                </div>
                                <div class="ms-3">
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-truck text-success fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-1 fw-bold">
                                        <?php 
                                        $entregues = array_filter($pedidos, fn($p) => $p['status'] === 'entregue');
                                        echo count($entregues);
                                        ?>
                                    </h4>
                                    <p class="text-muted small mb-0"><?= __('user_orders.stats.delivered_orders', 'Pedidos Entregues') ?></p>
                                </div>
                                <div class="ms-3">
                                    <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-check-circle text-info fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-1 fw-bold">
                                        <?php 
                                        $totalGastoBRL = 0.0;
                                        $totalGastoUSD = 0.0;
                                        foreach (($pedidos ?? []) as $p) {
                                            $moedaP = strtoupper((string) ($p['moeda'] ?? ($p['currency'] ?? 'BRL')));
                                            $v = $p['valor_total'] ?? $p['total'] ?? $p['valor'] ?? $p['amount'] ?? 0;
                                            if ($moedaP === 'USD') {
                                                $totalGastoUSD += floatval($v);
                                            } else {
                                                $totalGastoBRL += floatval($v);
                                            }
                                        }
                                        if ($totalGastoUSD > 0 && $totalGastoBRL > 0) {
                                            echo 'R$ ' . number_format($totalGastoBRL, 2, ',', '.') . '<br><span class="text-muted small">US$ ' . number_format($totalGastoUSD, 2, ',', '.') . '</span>';
                                        } elseif ($totalGastoUSD > 0) {
                                            echo 'US$ ' . number_format($totalGastoUSD, 2, ',', '.');
                                        } else {
                                            echo 'R$ ' . number_format($totalGastoBRL, 2, ',', '.');
                                        }
                                        ?>
                                    </h4>
                                    <p class="text-muted small mb-0"><?= __('user_orders.stats.total_spent', 'Total Gasto') ?></p>
                                </div>
                                <div class="ms-3">
                                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-dollar-sign text-warning fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pedidos -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                            <?php if (empty($pedidos)): ?>
                                <div class="text-center py-5">
                                    <div class="bg-light rounded-circle p-4 d-inline-block mb-3">
                                        <i class="fas fa-shopping-bag text-muted fs-2"></i>
                                    </div>
                                    <h5 class="mb-2"><?= __('user_orders.empty_title', 'Nenhum pedido encontrado') ?></h5>
                                    <p class="text-muted mb-4"><?= __('user_orders.empty_subtitle', 'Você ainda não fez nenhuma compra.') ?></p>
                                    <a href="/produtos" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart me-2"></i> <?= __('user_orders.view_products', 'Ver Produtos') ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabelaPedidos">
                                        <thead>
                                            <tr>
                                                <th><?= __('user_orders.table.code', 'Código') ?></th>
                                                <th><?= __('user_orders.table.date', 'Data') ?></th>
                                                <th><?= __('user_orders.table.status', 'Status') ?></th>
                                                <th><?= __('user_orders.table.amount', 'Valor') ?></th>
                                                <th><?= __('user_orders.table.items', 'Itens') ?></th>
                                                <th><?= __('user_orders.table.actions', 'Ações') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pedidos as $pedido): ?>
                                            <?php
                                                $codigoPedido = (string) (
                                                    $pedido['codigo_pedido'] ??
                                                    $pedido['numero_pedido'] ??
                                                    $pedido['codigo'] ??
                                                    ('#' . (string) ($pedido['id'] ?? ''))
                                                );
                                                $statusPedido = (string) ($pedido['status'] ?? '');
                                                if ($statusPedido === '') {
                                                    $statusPedido = (string) ($pedido['payment_status'] ?? ($pedido['status_pagamento'] ?? 'pendente'));
                                                }
                                                $statusPedidoKey = $normalizeStatusKey($statusPedido);
                                                $moeda = strtoupper((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'BRL')));
                                                $totalPedido = floatval($pedido['valor_total'] ?? ($pedido['total'] ?? ($pedido['valor'] ?? ($pedido['amount'] ?? 0))));
                                            ?>
                                            <tr data-status="<?= htmlspecialchars($statusPedidoKey) ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                            <i class="fas fa-receipt text-primary fs-6"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($codigoPedido) ?></strong>
                                                            <div class="text-muted small">#<?= str_pad((string) ($pedido['id'] ?? 0), 6, '0', STR_PAD_LEFT) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="small fw-semibold"><?= date('d/m/Y', strtotime($pedido['created_at'])) ?></div>
                                                        <div class="text-muted small"><?= date('H:i', strtotime($pedido['created_at'])) ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'pendente' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.35)', 'color' => 'rgba(124, 45, 18, 1)'],
                                                        'processando' => ['bg' => 'rgba(56, 189, 248, 0.12)', 'border' => 'rgba(56, 189, 248, 0.22)', 'color' => 'rgba(11, 31, 58, 1)'],
                                                        'enviado' => ['bg' => 'rgba(11, 31, 58, 0.08)', 'border' => 'rgba(11, 31, 58, 0.14)', 'color' => 'rgba(11, 31, 58, 1)'],
                                                        'entregue' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)'],
                                                        'cancelado' => ['bg' => 'rgba(239, 68, 68, 0.10)', 'border' => 'rgba(239, 68, 68, 0.18)', 'color' => 'rgba(185, 28, 28, 1)'],
                                                        'pago' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)'],
                                                        'paid' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)'],
                                                        'aprovado' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)'],
                                                        'approved' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)'],
                                                        'selecao' => ['bg' => 'rgba(148, 163, 184, 0.18)', 'border' => 'rgba(148, 163, 184, 0.35)', 'color' => 'rgba(15, 23, 42, 0.82)'],
                                                        'cobranca' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.35)', 'color' => 'rgba(124, 45, 18, 1)'],
                                                        'despacho' => ['bg' => 'rgba(56, 189, 248, 0.12)', 'border' => 'rgba(56, 189, 248, 0.22)', 'color' => 'rgba(11, 31, 58, 1)'],
                                                        'transito' => ['bg' => 'rgba(11, 31, 58, 0.08)', 'border' => 'rgba(11, 31, 58, 0.14)', 'color' => 'rgba(11, 31, 58, 1)'],
                                                        'aduana' => ['bg' => 'rgba(11, 31, 58, 0.08)', 'border' => 'rgba(11, 31, 58, 0.14)', 'color' => 'rgba(11, 31, 58, 1)'],
                                                        'entrega' => ['bg' => 'rgba(11, 31, 58, 0.08)', 'border' => 'rgba(11, 31, 58, 0.14)', 'color' => 'rgba(11, 31, 58, 1)'],
                                                        'concluido' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)'],
                                                        'etiqueta_gerada' => ['bg' => 'rgba(56, 189, 248, 0.12)', 'border' => 'rgba(56, 189, 248, 0.22)', 'color' => 'rgba(11, 31, 58, 1)'],
                                                        'enviado_ao_destinatario' => ['bg' => 'rgba(11, 31, 58, 0.08)', 'border' => 'rgba(11, 31, 58, 0.14)', 'color' => 'rgba(11, 31, 58, 1)'],
                                                        'caixa_fechada' => ['bg' => 'rgba(148, 163, 184, 0.18)', 'border' => 'rgba(148, 163, 184, 0.35)', 'color' => 'rgba(15, 23, 42, 0.82)'],
                                                        'aguardando_liberacao_aduaneira' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.35)', 'color' => 'rgba(124, 45, 18, 1)'],
                                                        'aguardando_lib_alfandegaria' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.35)', 'color' => 'rgba(124, 45, 18, 1)'],
                                                        'carne_pagando' => ['bg' => 'rgba(111, 66, 193, 0.12)', 'border' => 'rgba(111, 66, 193, 0.25)', 'color' => 'rgba(111, 66, 193, 1)'],
                                                        'carne_aguardando' => ['bg' => 'rgba(111, 66, 193, 0.12)', 'border' => 'rgba(111, 66, 193, 0.25)', 'color' => 'rgba(111, 66, 193, 1)'],
                                                    ];
                                                    $statusLabels = [
                                                        'enviado' => __('order_status.label_generated', 'Etiqueta gerada'),
                                                        'carne_pagando' => 'Carnê em Pagamento',
                                                        'carne_aguardando' => 'Carnê Aguardando',
                                                    ];
                                                    $badge = $statusColors[$statusPedidoKey] ?? $statusColors['selecao'];
                                                    $label = $statusLabels[$statusPedidoKey] ?? (trim($statusPedido) !== '' ? ucfirst($statusPedido) : __('order_status.pending', 'Pendente'));
                                                    ?>
                                                    <span class="badge px-3 py-2" style="background: <?= $badge['bg'] ?>; border: 1px solid <?= $badge['border'] ?>; color: <?= $badge['color'] ?>;">
                                                        <?= $label ?>
                                                    </span>
                                                    <?php
                                                    $fpCarne = strtolower(trim((string) ($pedido['forma_pagamento'] ?? '')));
                                                    if ($fpCarne === 'carne_braziliana' || in_array($statusPedidoKey, ['carne_pagando', 'carne_aguardando'], true)):
                                                        $carneIdCliente = 0;
                                                        try { $stCl = \Config\Database::getConnection()->prepare("SELECT id FROM carnes WHERE pedido_id = ? LIMIT 1"); $stCl->execute([(int)$pedido['id']]); $carneIdCliente = (int)($stCl->fetchColumn() ?: 0); } catch (\Exception $e) {}
                                                    ?>
                                                    <div class="mt-1">
                                                        <a href="<?= $carneIdCliente > 0 ? '/meu-carne/' . $carneIdCliente : '/meus-carnes' ?>" class="badge text-white text-decoration-none" style="background:#6f42c1;font-size:.7rem;">
                                                            <i class="fas fa-file-invoice-dollar me-1"></i>Ver Carnê
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-semibold">
                                                    <?php
                                                    $moedaPedido = strtoupper((string) ($moeda ?? 'BRL'));
                                                    $totalDisplay = (float) $totalPedido;
                                                    if ($moedaPedido === 'BRL') {
                                                        // Pedido em BRL: mostrar em reais
                                                        echo 'R$ ' . number_format($totalDisplay, 2, ',', '.');
                                                    } else {
                                                        // Pedido em USD: mostrar em dólar
                                                        echo 'US$ ' . number_format($totalDisplay, 2, ',', '.');
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-box text-muted me-2"></i>
                                                        <span class="small"><?= (int) ($pedido['total_itens'] ?? 0) ?> <?= __('user_orders.items_count', 'itens') ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="/pedido/detalhes/<?= $pedido['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary"
                                                           title="<?= htmlspecialchars(__('user_orders.actions.view_details', 'Ver Detalhes'), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a class="btn btn-sm btn-outline-dark"
                                                           href="/meu-ticket/abrir/pedido/<?= (int) ($pedido['id'] ?? 0) ?>"
                                                           title="<?= htmlspecialchars(__('user_orders.actions.support', 'Suporte'), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fas fa-life-ring me-1"></i><span class="d-none d-md-inline">Abrir Ticket</span>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="rastrearPedido('<?= htmlspecialchars((string)($pedido['codigo_pedido'] ?? $pedido['codigo'] ?? $pedido['codigo_rastreamento'] ?? $pedido['rastreamento'] ?? $pedido['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')"
                                                                title="<?= htmlspecialchars(__('user_orders.actions.track', 'Rastrear'), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fas fa-search-location"></i>
                                                        </button>
                                                        <?php if ($pedido['status'] === 'entregue'): ?>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="recomprarPedido(<?= $pedido['id'] ?>)"
                                                                title="<?= htmlspecialchars(__('user_orders.actions.buy_again', 'Comprar Novamente'), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fas fa-redo"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_paginas > 1): ?>
                                <nav aria-label="<?= htmlspecialchars(__('user_orders.pagination.aria', 'Navegação de páginas'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($pagina > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="/meus-pedidos?pagina=<?= $pagina - 1 ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                            <a class="page-link" href="/meus-pedidos?pagina=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($pagina < $total_paginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="/meus-pedidos?pagina=<?= $pagina + 1 ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.col-lg-9 .user-avatar img {
    border: 3px solid #fff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    height: 80px;
}

.col-lg-9 .card-body nav.nav.flex-column .nav-link {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
    color: #6c757d;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.col-lg-9 .card-body nav.nav.flex-column .nav-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    transform: none;
}

.col-lg-9 .card-body nav.nav.flex-column .nav-link.active {
    background: rgba(11, 31, 58, 0.08);
    border: 1px solid rgba(11, 31, 58, 0.14);
    color: rgba(11, 31, 58, 1) !important;
}

.col-lg-9 .card {
    transition: none;
}

.col-lg-9 .card:hover {
    transform: none;
    box-shadow: none;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
    text-transform: uppercase;
    background-color: #f8f9fa;
}

.badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.btn-group .btn {
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
}

.btn-group .btn:last-child {
    border-top-right-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}

.pagination .page-link {
    border-radius: 0.375rem;
    margin: 0 2px;
}

.pagination .page-item.active .page-link {
    background: rgba(11, 31, 58, 0.08);
    border-color: rgba(11, 31, 58, 0.14);
    color: rgba(11, 31, 58, 1);
}

@media (max-width: 991.98px) {
    .user-page-actions {
        flex-wrap: wrap;
        width: 100%;
    }

    .user-page-actions .input-group {
        width: 100% !important;
    }

    #filtroStatus {
        width: 100% !important;
    }
}

@media (max-width: 767.98px) {
    .user-page-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.75rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const normalizeStatusKey = function(st) {
        try {
            let s = String(st || '').trim().toLowerCase();
            if (!s) return '';
            if (s.normalize) {
                s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            }
            s = s.replace(/[^a-z0-9]+/g, '_');
            s = s.replace(/_+/g, '_');
            s = s.replace(/^_+|_+$/g, '');
            return s;
        } catch (e) {
            return String(st || '').trim().toLowerCase();
        }
    };

    // Filtro por status
    const filtroStatus = document.getElementById('filtroStatus');
    const tabelaPedidos = document.getElementById('tabelaPedidos');
    
    if (filtroStatus && tabelaPedidos) {
        filtroStatus.addEventListener('change', function() {
            const status = normalizeStatusKey(this.value);
            const rows = tabelaPedidos.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const rowStatus = normalizeStatusKey(row.dataset.status);
                if (status === '' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Busca de pedido
    const buscaPedido = document.getElementById('buscaPedido');
    if (buscaPedido && tabelaPedidos) {
        buscaPedido.addEventListener('input', function() {
            const termo = this.value.toLowerCase();
            const rows = tabelaPedidos.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const texto = row.textContent.toLowerCase();
                if (texto.includes(termo)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    try {
        const params = new URLSearchParams(window.location.search || '');
        const tab = (params.get('tab') || '').toLowerCase();
        if (tab === 'comissoes') {
            const tabBtn = document.getElementById('tab-comissoes');
            if (tabBtn) {
                tabBtn.click();
            }
        }
    } catch (e) {
    }
});

function rastrearPedido(codigo) {
    window.location.href = '/rastreamento?codigo=' + codigo;
}

function recomprarPedido(pedidoId) {
    if (confirm('Deseja adicionar os itens deste pedido ao seu carrinho?')) {
        window.location.href = '/pedido/recomprar/' + pedidoId;
    }
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
