<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Dashboard Administrativo</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-outline-secondary">Exportar</button>
            </div>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total de Pedidos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_pedidos'], 0, ',', '.') ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Faturamento USD</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$ <?= number_format($stats['financeiro']['faturamento_usd'], 2, ',', '.') ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Faturamento BRL</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?= number_format($stats['financeiro']['faturamento_brl'] ?? 0, 2, ',', '.') ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Impostos Arrecadados</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?= number_format($stats['financeiro']['impostos_arrecadados'] ?? 0, 2, ',', '.') ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-receipt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos e Tabelas -->
    <div class="row">
        <!-- Pedidos por Status -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Pedidos por Status</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="pedidosStatusChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <?php foreach ($stats['pedidos_por_status'] as $status): ?>
                        <span class="mr-2">
                            <i class="fas fa-circle" style="color: <?= getStatusColor($status['status']) ?>"></i>
                            <?= getStatusLabel($status['status']) ?>: <?= $status['quantidade'] ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pedidos Recentes -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Pedidos Recentes</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow">
                            <a class="dropdown-item" href="/admin/pedidos">Ver Todos</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless">
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
                                        <a href="/admin/pedido/detalhes/<?= $pedido['id'] ?>" class="text-decoration-none">
                                            <?= $pedido['codigo_pedido'] ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
                                    <td>$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
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
                                        <span class="badge" style="<?= $dashBadgeStyle ?>">
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
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Ações Rápidas</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="/admin/pedidos" class="btn btn-primary btn-block">
                                <i class="fas fa-list"></i> Gerenciar Pedidos
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="/admin/consolidar-pedidos" class="btn btn-info btn-block">
                                <i class="fas fa-compress"></i> Consolidar Pedidos
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="/admin/configuracoes" class="btn btn-warning btn-block">
                                <i class="fas fa-cog"></i> Configurações
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="/admin/usuarios" class="btn btn-secondary btn-block">
                                <i class="fas fa-users"></i> Usuários
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
        'pago' => 'success',
        'aguardando_processamento' => 'info',
        'consolidado' => 'primary',
        'rascunho_etiqueta' => 'warning',
        'etiqueta_efetivada' => 'primary',
        'enviado' => 'info',
        'aguardando_lib_alfandegaria' => 'warning',
        'finalizacao_embalagem' => 'info',
        'entrega_finalizada' => 'success'
    ];
    return $colors[$status] ?? 'secondary';
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
$(document).ready(function() {
    // Gráfico de pizza para pedidos por status
    var ctx = document.getElementById('pedidosStatusChart').getContext('2d');
    var pedidosStatus = <?= json_encode($stats['pedidos_por_status']) ?>;
    
    var labels = pedidosStatus.map(function(item) {
        var statusLabels = {
            'pago': 'Pago',
            'aguardando_processamento': 'Aguardando Processamento',
            'consolidado': 'Consolidado',
            'rascunho_etiqueta': 'Rascunho Etiqueta',
            'etiqueta_efetivada': 'Etiqueta Efetivada',
            'enviado' : 'Enviado',
            'aguardando_lib_alfandegaria': 'Aguardando Liberação',
            'finalizacao_embalagem': 'Finalização Embalagem',
            'entrega_finalizada': 'Entrega Finalizada'
        };
        return statusLabels[item.status] || item.status;
    });
    
    var data = pedidosStatus.map(function(item) {
        return item.quantidade;
    });
    
    var colors = [
        '#1d4ed8', '#10b981', '#38bdf8', '#f59e0b', '#ef4444',
        '#94a3b8', '#0b1f3a', '#2563eb', '#059669', '#0891b2'
    ];
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, data.length),
                hoverBackgroundColor: colors.slice(0, data.length),
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
            },
            legend: {
                display: false
            },
            cutoutPercentage: 70,
        },
    });
});
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
