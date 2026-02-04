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
                    <h2 class="mb-1">Meus Pedidos</h2>
                    <p class="text-muted mb-0">Histórico completo dos seus pedidos</p>
                </div>
                <div class="d-flex gap-2 user-page-actions">
                    <div class="input-group" style="width: 250px;">
                        <input type="text" class="form-control" placeholder="Buscar pedido..." id="buscaPedido">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <select class="form-select" id="filtroStatus" style="width: 150px;">
                        <option value="">Todos Status</option>
                        <option value="pendente">Pendente</option>
                        <option value="processando">Processando</option>
                        <option value="enviado">Enviado</option>
                        <option value="entregue">Entregue</option>
                        <option value="cancelado">Cancelado</option>
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
                                    <p class="text-muted small mb-0">Total de Pedidos</p>
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
                                    <p class="text-muted small mb-0">Pedidos Ativos</p>
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
                                    <p class="text-muted small mb-0">Pedidos Entregues</p>
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
                                    <p class="text-muted small mb-0">Total Gasto</p>
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
                                    <h5 class="mb-2">Nenhum pedido encontrado</h5>
                                    <p class="text-muted mb-4">Você ainda não fez nenhuma compra.</p>
                                    <a href="/produtos" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart me-2"></i> Ver Produtos
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabelaPedidos">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Data</th>
                                                <th>Status</th>
                                                <th>Valor</th>
                                                <th>Itens</th>
                                                <th>Ações</th>
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
                                                $moeda = strtoupper((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'BRL')));
                                                $totalPedido = floatval($pedido['valor_total'] ?? ($pedido['total'] ?? ($pedido['valor'] ?? ($pedido['amount'] ?? 0))));
                                            ?>
                                            <tr data-status="<?= htmlspecialchars($statusPedido) ?>">
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
                                                        'concluido' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)']
                                                    ];
                                                    $statusLabels = [
                                                        'pendente' => 'Pendente',
                                                        'processando' => 'Processando',
                                                        'enviado' => 'Enviado',
                                                        'entregue' => 'Entregue',
                                                        'cancelado' => 'Cancelado',
                                                        'pago' => 'Pago',
                                                        'paid' => 'Pago',
                                                        'aprovado' => 'Pago',
                                                        'approved' => 'Pago',
                                                        'selecao' => 'Seleção',
                                                        'cobranca' => 'Cobrança',
                                                        'despacho' => 'Despacho',
                                                        'transito' => 'Trânsito',
                                                        'aduana' => 'Aduana',
                                                        'entrega' => 'Entrega',
                                                        'concluido' => 'Concluído'
                                                    ];
                                                    $badge = $statusColors[$statusPedido] ?? $statusColors['selecao'];
                                                    $label = $statusLabels[$statusPedido] ?? (trim($statusPedido) !== '' ? ucfirst($statusPedido) : 'Pendente');
                                                    ?>
                                                    <span class="badge px-3 py-2" style="background: <?= $badge['bg'] ?>; border: 1px solid <?= $badge['border'] ?>; color: <?= $badge['color'] ?>;">
                                                        <?= $label ?>
                                                    </span>
                                                </td>
                                                <td class="fw-semibold">
                                                    <?php
                                                    $moedaPedido = strtoupper((string) ($moeda ?? 'BRL'));
                                                    $tx = (float) ($pedido['taxa_conversao'] ?? 0);
                                                    if ($tx <= 1.01) {
                                                        try {
                                                            $svcTx = new \App\Services\PedidoManualService();
                                                            $tx = (float) $svcTx->getTaxaConversaoUSDBRL();
                                                        } catch (\Exception $e) {
                                                            $tx = 5.5;
                                                        }
                                                    }
                                                    $totalUsd = (float) $totalPedido;
                                                    if ($moedaPedido === 'BRL') {
                                                        $totalUsd = $tx > 0 ? ($totalUsd / $tx) : $totalUsd;
                                                    }
                                                    ?>
                                                    US$ <?= number_format($totalUsd, 2, ',', '.') ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-box text-muted me-2"></i>
                                                        <span class="small"><?= $pedido['total_itens'] ?? 0 ?> itens</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="/pedido/detalhes/<?= $pedido['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary"
                                                           title="Ver Detalhes">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="rastrearPedido('<?= htmlspecialchars((string)($pedido['codigo_pedido'] ?? $pedido['codigo'] ?? $pedido['codigo_rastreamento'] ?? $pedido['rastreamento'] ?? $pedido['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')"
                                                                title="Rastrear">
                                                            <i class="fas fa-search-location"></i>
                                                        </button>
                                                        <?php if ($pedido['status'] === 'entregue'): ?>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="recomprarPedido(<?= $pedido['id'] ?>)"
                                                                title="Comprar Novamente">
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
                                <nav aria-label="Navegação de páginas" class="mt-4">
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
.user-avatar img {
    border: 3px solid #fff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    height: 80px;
}

.card-body nav.nav.flex-column .nav-link {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
    color: #6c757d;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.card-body nav.nav.flex-column .nav-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    transform: none;
}

.card-body nav.nav.flex-column .nav-link.active {
    background: rgba(11, 31, 58, 0.08);
    border: 1px solid rgba(11, 31, 58, 0.14);
    color: rgba(11, 31, 58, 1) !important;
}

.card {
    transition: none;
}

.card:hover {
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
    // Filtro por status
    const filtroStatus = document.getElementById('filtroStatus');
    const tabelaPedidos = document.getElementById('tabelaPedidos');
    
    if (filtroStatus && tabelaPedidos) {
        filtroStatus.addEventListener('change', function() {
            const status = this.value;
            const rows = tabelaPedidos.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                if (status === '' || row.dataset.status === status) {
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
