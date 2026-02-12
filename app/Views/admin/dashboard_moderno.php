<?php ob_start(); ?>
<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0"><?= __('admin.dashboard.title', 'Dashboard Administrativo') ?></h1>
            <p class="text-muted mb-0"><?= __('admin.dashboard.welcome', 'Bem-vindo ao painel de controle') ?></p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary">
                <i class="fas fa-download me-2"></i><?= __('admin.dashboard.export_report', 'Exportar Relatório') ?>
            </button>
            <button type="button" class="btn btn-primary">
                <i class="fas fa-sync me-2"></i><?= __('common.refresh', 'Atualizar') ?>
            </button>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small fw-semibold mb-1"><?= __('admin.dashboard.total_orders', 'Total de Pedidos') ?></div>
                            <div class="h3 mb-0 fw-bold"><?= number_format($stats['total_pedidos'], 0, ',', '.') ?></div>
                            <div class="text-success small mt-1">
                                <i class="fas fa-arrow-up me-1"></i>
                                <span><?= __('admin.dashboard.growth_this_month', '{percent}% este mês', ['percent' => 12]) ?></span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="rounded-circle p-3" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                <i class="fas fa-shopping-cart fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small fw-semibold mb-1"><?= __('admin.dashboard.revenue_usd', 'Faturamento USD') ?></div>
                            <div class="h3 mb-0 fw-bold"><?= __('admin.orders.js.currency_usd', 'US$') ?> <?= number_format($stats['financeiro']['faturamento_usd'], 2, ',', '.') ?></div>
                            <div class="text-success small mt-1">
                                <i class="fas fa-arrow-up me-1"></i>
                                <span><?= __('admin.dashboard.growth_this_month', '{percent}% este mês', ['percent' => 8]) ?></span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="rounded-circle p-3" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.22); color: #065f46;">
                                <i class="fas fa-dollar-sign fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small fw-semibold mb-1"><?= __('admin.dashboard.revenue_brl', 'Faturamento BRL') ?></div>
                            <div class="h3 mb-0 fw-bold"><?= __('admin.orders.js.currency_brl', 'R$') ?> <?= number_format($stats['financeiro']['faturamento_brl'], 2, ',', '.') ?></div>
                            <div class="text-success small mt-1">
                                <i class="fas fa-arrow-up me-1"></i>
                                <span><?= __('admin.dashboard.growth_this_month', '{percent}% este mês', ['percent' => 15]) ?></span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="rounded-circle p-3" style="background: rgba(56, 189, 248, 0.10); border: 1px solid rgba(56, 189, 248, 0.22); color: rgba(11, 31, 58, 1);">
                                <i class="fas fa-dollar-sign fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small fw-semibold mb-1"><?= __('admin.dashboard.taxes_collected', 'Impostos Arrecadados') ?></div>
                            <div class="h3 mb-0 fw-bold"><?= __('admin.orders.js.currency_brl', 'R$') ?> <?= number_format($stats['financeiro']['impostos_arrecadados'], 2, ',', '.') ?></div>
                            <div class="text-warning small mt-1">
                                <i class="fas fa-minus me-1"></i>
                                <span><?= __('admin.dashboard.stable', 'Estável') ?></span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="rounded-circle p-3" style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.22); color: #7c2d12;">
                                <i class="fas fa-receipt fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos e Tabelas -->
    <div class="row g-4 mb-4">
        <!-- Pedidos por Status -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4">
                    <h6 class="mb-0 fw-bold"><?= __('admin.dashboard.orders_by_status', 'Pedidos por Status') ?></h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="pedidosStatusChart"></canvas>
                    </div>
                    <div class="mt-4">
                        <div class="row g-2">
                            <?php foreach ($stats['pedidos_por_status'] as $status): ?>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="me-2">
                                        <div class="rounded-circle" style="width: 12px; height: 12px; background-color: <?= getStatusColor($status['status']) ?>"></div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small fw-semibold"><?= getStatusLabel($status['status']) ?></div>
                                        <div class="text-muted small"><?= __('admin.dashboard.orders_count_inline', '{count} pedidos', ['count' => (int) ($status['quantidade'] ?? 0)]) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pedidos Recentes -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><?= __('admin.dashboard.recent_orders', 'Pedidos Recentes') ?></h6>
                    <a href="/admin/pedidos" class="btn btn-sm btn-outline-primary"><?= __('common.view_all', 'Ver Todos') ?></a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?= __('admin.orders.table.order', 'Pedido') ?></th>
                                    <th><?= __('admin.orders.table.customer', 'Cliente') ?></th>
                                    <th><?= __('admin.orders.table.value', 'Valor') ?></th>
                                    <th><?= __('admin.orders.table.status', 'Status') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($stats['pedidos_recentes'], 0, 5) as $pedido): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/pedido/detalhes/<?= $pedido['id'] ?>" class="text-decoration-none fw-semibold">
                                            #<?= $pedido['codigo_pedido'] ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle p-2 me-2" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                                <i class="fas fa-user fs-6"></i>
                                            </div>
                                            <?= htmlspecialchars($pedido['cliente_nome']) ?>
                                        </div>
                                    </td>
                                    <td class="fw-semibold"><?= __('admin.orders.js.currency_usd', 'US$') ?> <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php
                                            $dashStatus = (string) ($pedido['status'] ?? '');
                                            $dashBadgeStyle = 'background: rgba(148, 163, 184, 0.16); border: 1px solid rgba(148, 163, 184, 0.28); color: #334155;';
                                            if ($dashStatus === 'pago') {
                                                $dashBadgeStyle = 'background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.22); color: #065f46;';
                                            } elseif ($dashStatus === 'rascunho_etiqueta' || $dashStatus === 'aguardando_lib_alfandegaria') {
                                                $dashBadgeStyle = 'background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.22); color: #7c2d12;';
                                            } elseif ($dashStatus === 'entrega_finalizada') {
                                                $dashBadgeStyle = 'background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.22); color: #065f46;';
                                            } else {
                                                $dashBadgeStyle = 'background: rgba(56, 189, 248, 0.10); border: 1px solid rgba(56, 189, 248, 0.22); color: #0b1f3a;';
                                            }
                                        ?>
                                        <span class="badge px-3 py-2" style="<?= $dashBadgeStyle ?>">
                                            <?= getStatusLabel($pedido['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4">
                    <h6 class="mb-0 fw-bold"><?= __('admin.dashboard.quick_actions', 'Ações Rápidas') ?></h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="/admin/pedidos" class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-list fs-4 mb-2"></i>
                                <span><?= __('admin.dashboard.manage_orders', 'Gerenciar Pedidos') ?></span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/consolidar-pedidos" class="btn btn-info w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-compress fs-4 mb-2"></i>
                                <span><?= __('admin.dashboard.consolidate_orders', 'Consolidar Pedidos') ?></span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/configuracoes" class="btn btn-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-cog fs-4 mb-2"></i>
                                <span><?= __('admin.menu.settings', 'Configurações') ?></span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/usuarios" class="btn btn-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-users fs-4 mb-2"></i>
                                <span><?= __('admin.menu.users', 'Usuários') ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions
function getStatusColor($status) {
    $colors = [
        'pago' => '#10b981',
        'aguardando_processamento' => '#3b82f6',
        'consolidado' => '#1d4ed8',
        'rascunho_etiqueta' => '#f59e0b',
        'etiqueta_efetivada' => '#1d4ed8',
        'enviado' => '#3b82f6',
        'aguardando_lib_alfandegaria' => '#f59e0b',
        'finalizacao_embalagem' => '#3b82f6',
        'entrega_finalizada' => '#10b981'
    ];
    return $colors[$status] ?? '#6b7280';
}

function getStatusLabel($status) {
    $labels = [
        'pago' => __('admin.order_status.paid', 'Pago'),
        'aguardando_processamento' => __('admin.order_status.awaiting_processing', 'Aguardando Processamento'),
        'consolidado' => __('admin.order_status.consolidated', 'Consolidado'),
        'rascunho_etiqueta' => __('admin.order_status.label_draft', 'Rascunho Etiqueta'),
        'etiqueta_efetivada' => __('admin.order_status.label_effective', 'Etiqueta Efetivada'),
        'enviado' => __('admin.order_status.shipped', 'Enviado'),
        'aguardando_lib_alfandegaria' => __('admin.order_status.awaiting_customs_release', 'Aguardando Liberação'),
        'finalizacao_embalagem' => __('admin.order_status.packaging_finalization', 'Finalização Embalagem'),
        'entrega_finalizada' => __('admin.order_status.delivery_completed', 'Entrega Finalizada')
    ];
    return $labels[$status] ?? $status;
}
?>

<script>
window.ADMIN_DASHBOARD_I18N = {
    order_status_paid: <?= json_encode(__('admin.order_status.paid', 'Pago'), JSON_UNESCAPED_UNICODE) ?>,
    order_status_processing: <?= json_encode(__('admin.order_status.awaiting_processing', 'Aguardando Processamento'), JSON_UNESCAPED_UNICODE) ?>,
    order_status_selection: <?= json_encode(__('admin.order_status.consolidated', 'Consolidado'), JSON_UNESCAPED_UNICODE) ?>,
    order_status_billing: <?= json_encode(__('admin.order_status.label_draft', 'Rascunho Etiqueta'), JSON_UNESCAPED_UNICODE) ?>,
    order_status_label_effective: <?= json_encode(__('admin.order_status.label_effective', 'Etiqueta Efetivada'), JSON_UNESCAPED_UNICODE) ?>,
    order_status_shipped: <?= json_encode(__('admin.order_status.shipped', 'Enviado'), JSON_UNESCAPED_UNICODE) ?>,
    order_status_customs: <?= json_encode(__('admin.order_status.awaiting_customs_release', 'Aguardando Liberação'), JSON_UNESCAPED_UNICODE) ?>,
    order_status_dispatch: <?= json_encode(__('admin.order_status.packaging_finalization', 'Finalização Embalagem'), JSON_UNESCAPED_UNICODE) ?>,
    order_status_completed: <?= json_encode(__('admin.order_status.delivery_completed', 'Entrega Finalizada'), JSON_UNESCAPED_UNICODE) ?>,
    orders_suffix: <?= json_encode(__('admin.dashboard.orders_suffix', 'pedidos'), JSON_UNESCAPED_UNICODE) ?>,
    tooltip_orders: <?= json_encode(__('admin.dashboard.tooltip_orders', '{label}: {value} pedidos'), JSON_UNESCAPED_UNICODE) ?>
};
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de pizza para pedidos por status
    const ctx = document.getElementById('pedidosStatusChart').getContext('2d');
    const pedidosStatus = JSON.parse('<?= json_encode($stats['pedidos_por_status'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>');
    
    const labels = pedidosStatus.map(function(item) {
        const statusLabels = {
            'pago': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_paid) ? window.ADMIN_DASHBOARD_I18N.order_status_paid : 'Pago',
            'aguardando_processamento': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_processing) ? window.ADMIN_DASHBOARD_I18N.order_status_processing : 'Aguardando Processamento',
            'consolidado': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_selection) ? window.ADMIN_DASHBOARD_I18N.order_status_selection : 'Consolidado',
            'rascunho_etiqueta': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_billing) ? window.ADMIN_DASHBOARD_I18N.order_status_billing : 'Rascunho Etiqueta',
            'etiqueta_efetivada': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_label_effective) ? window.ADMIN_DASHBOARD_I18N.order_status_label_effective : 'Etiqueta Efetivada',
            'enviado': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_shipped) ? window.ADMIN_DASHBOARD_I18N.order_status_shipped : 'Enviado',
            'aguardando_lib_alfandegaria': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_customs) ? window.ADMIN_DASHBOARD_I18N.order_status_customs : 'Aguardando Liberação',
            'finalizacao_embalagem': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_dispatch) ? window.ADMIN_DASHBOARD_I18N.order_status_dispatch : 'Finalização Embalagem',
            'entrega_finalizada': (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.order_status_completed) ? window.ADMIN_DASHBOARD_I18N.order_status_completed : 'Entrega Finalizada'
        };
        return statusLabels[item.status] || item.status;
    });
    
    const data = pedidosStatus.map(function(item) {
        return item.quantidade;
    });
    
    const colors = [
        '#10b981', '#3b82f6', '#1d4ed8', '#f59e0b', '#ef4444',
        '#6b7280', '#4b5563', '#2563eb', '#059669', '#0891b2'
    ];
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, data.length),
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#ddd',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            if (window.ADMIN_DASHBOARD_I18N && window.ADMIN_DASHBOARD_I18N.tooltip_orders) {
                                return window.ADMIN_DASHBOARD_I18N.tooltip_orders
                                    .replace('{label}', context.label)
                                    .replace('{value}', context.parsed);
                            }
                            return context.label + ': ' + context.parsed + ' pedidos';
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
