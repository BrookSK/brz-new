<?php ob_start(); ?>
<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0">Dashboard Administrativo</h1>
            <p class="text-muted mb-0">Bem-vindo ao painel de controle</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary">
                <i class="fas fa-download me-2"></i>Exportar Relatório
            </button>
            <button type="button" class="btn btn-primary">
                <i class="fas fa-sync me-2"></i>Atualizar
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
                            <div class="text-muted small fw-semibold mb-1">Total de Pedidos</div>
                            <div class="h3 mb-0 fw-bold"><?= number_format($stats['total_pedidos'], 0, ',', '.') ?></div>
                            <div class="text-success small mt-1">
                                <i class="fas fa-arrow-up me-1"></i>
                                <span>12% este mês</span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-shopping-cart text-primary fs-4"></i>
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
                            <div class="text-muted small fw-semibold mb-1">Faturamento USD</div>
                            <div class="h3 mb-0 fw-bold">$ <?= number_format($stats['financeiro']['faturamento_usd'], 2, ',', '.') ?></div>
                            <div class="text-success small mt-1">
                                <i class="fas fa-arrow-up me-1"></i>
                                <span>8% este mês</span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-dollar-sign text-success fs-4"></i>
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
                            <div class="text-muted small fw-semibold mb-1">Faturamento BRL</div>
                            <div class="h3 mb-0 fw-bold">R$ <?= number_format($stats['financeiro']['faturamento_brl'], 2, ',', '.') ?></div>
                            <div class="text-success small mt-1">
                                <i class="fas fa-arrow-up me-1"></i>
                                <span>15% este mês</span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-dollar-sign text-info fs-4"></i>
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
                            <div class="text-muted small fw-semibold mb-1">Impostos Arrecadados</div>
                            <div class="h3 mb-0 fw-bold">R$ <?= number_format($stats['financeiro']['impostos_arrecadados'], 2, ',', '.') ?></div>
                            <div class="text-warning small mt-1">
                                <i class="fas fa-minus me-1"></i>
                                <span>Estável</span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-receipt text-warning fs-4"></i>
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
                    <h6 class="mb-0 fw-bold">Pedidos por Status</h6>
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
                                        <div class="rounded-circle" style="width: 12px; height: 12px; background-color: <?= $this->getStatusColor($status['status']) ?>"></div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small fw-semibold"><?= $this->getStatusLabel($status['status']) ?></div>
                                        <div class="text-muted small"><?= $status['quantidade'] ?> pedidos</div>
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
                    <h6 class="mb-0 fw-bold">Pedidos Recentes</h6>
                    <a href="/admin/pedidos" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                    <th>Status</th>
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
                                            <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                                                <i class="fas fa-user text-primary fs-6"></i>
                                            </div>
                                            <?= htmlspecialchars($pedido['cliente_nome']) ?>
                                        </div>
                                    </td>
                                    <td class="fw-semibold">$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $this->getStatusColor($pedido['status']) ?> px-3 py-2">
                                            <?= $this->getStatusLabel($pedido['status']) ?>
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
                    <h6 class="mb-0 fw-bold">Ações Rápidas</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="/admin/pedidos" class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-list fs-4 mb-2"></i>
                                <span>Gerenciar Pedidos</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/consolidar-pedidos" class="btn btn-info w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-compress fs-4 mb-2"></i>
                                <span>Consolidar Pedidos</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/configuracoes" class="btn btn-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-cog fs-4 mb-2"></i>
                                <span>Configurações</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/admin/usuarios" class="btn btn-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-users fs-4 mb-2"></i>
                                <span>Usuários</span>
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
        'consolidado' => '#6366f1',
        'rascunho_etiqueta' => '#f59e0b',
        'etiqueta_efetivada' => '#6366f1',
        'enviado' => '#3b82f6',
        'aguardando_lib_alfandegaria' => '#f59e0b',
        'finalizacao_embalagem' => '#3b82f6',
        'entrega_finalizada' => '#10b981'
    ];
    return $colors[$status] ?? '#6b7280';
}

function getStatusLabel($status) {
    $labels = [
        'pago' => 'Pago',
        'aguardando_processamento' => 'Aguardando Processamento',
        'consolidado' => 'Consolidado',
        'rascunho_etiqueta' => 'Rascunho Etiqueta',
        'etiqueta_efetivada' => 'Etiqueta Efetivada',
        'enviado' => 'Enviado',
        'aguardando_lib_alfandegaria' => 'Aguardando Liberação',
        'finalizacao_embalagem' => 'Finalização Embalagem',
        'entrega_finalizada' => 'Entrega Finalizada'
    ];
    return $labels[$status] ?? $status;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de pizza para pedidos por status
    const ctx = document.getElementById('pedidosStatusChart').getContext('2d');
    const pedidosStatus = <?= json_encode($stats['pedidos_por_status']) ?>;
    
    const labels = pedidosStatus.map(function(item) {
        const statusLabels = {
            'pago' => 'Pago',
            'aguardando_processamento' => 'Aguardando Processamento',
            'consolidado' => 'Consolidado',
            'rascunho_etiqueta' => 'Rascunho Etiqueta',
            'etiqueta_efetivada' => 'Etiqueta Efetivada',
            'enviado' : 'Enviado',
            'aguardando_lib_alfandegaria' => 'Aguardando Liberação',
            'finalizacao_embalagem' => 'Finalização Embalagem',
            'entrega_finalizada' => 'Entrega Finalizada'
        };
        return statusLabels[item.status] || item.status;
    });
    
    const data = pedidosStatus.map(function(item) {
        return item.quantidade;
    });
    
    const colors = [
        '#10b981', '#3b82f6', '#6366f1', '#f59e0b', '#ef4444',
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
