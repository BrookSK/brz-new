<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Profile Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="user-avatar mx-auto mb-3">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($usuario['nome']) ?>&background=6366f1&color=fff&size=128" 
                             alt="<?= htmlspecialchars($usuario['nome']) ?>" 
                             class="rounded-circle" width="80" height="80">
                    </div>
                    <h5 class="card-title mb-1"><?= htmlspecialchars($usuario['nome']) ?></h5>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($usuario['email']) ?></p>
                    <span class="badge bg-primary px-3 py-2"><?= ucfirst($usuario['perfil']) ?></span>
                </div>
            </div>
            
            <!-- Quick Menu -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">
                    <h6 class="card-title mb-3">Menu Rápido</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link active mb-2" href="/minha-conta">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link mb-2" href="/meus-dados">
                            <i class="fas fa-user me-2"></i> Meus Dados
                        </a>
                        <a class="nav-link mb-2" href="/meus-pedidos">
                            <i class="fas fa-shopping-bag me-2"></i> Meus Pedidos
                        </a>
                        <a class="nav-link mb-2" href="/carrinho">
                            <i class="fas fa-shopping-cart me-2"></i> Meu Carrinho
                            <?php if (!empty($_SESSION['carrinho'])): ?>
                                <span class="badge bg-danger rounded-pill ms-auto"><?= count($_SESSION['carrinho']) ?></span>
                            <?php endif; ?>
                        </a>
                        <hr class="my-3">
                        <a class="nav-link text-danger mb-2" href="/logout">
                            <i class="fas fa-sign-out-alt me-2"></i> Sair
                        </a>
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Minha Conta</h2>
                    <p class="text-muted mb-0">Bem-vindo de volta, <strong><?= htmlspecialchars($usuario['nome']) ?></strong>!</p>
                </div>
                <div class="text-end">
                    <small class="text-muted">Último acesso:</small><br>
                    <strong><?= date('d/m/Y H:i', strtotime($usuario['ultimo_acesso'] ?? 'now')) ?></strong>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-1 fw-bold"><?= $total_pedidos ?></h3>
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
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-1 fw-bold">
                                        <?php 
                                        $ativos = array_filter($pedidos, fn($p) => in_array($p['status'], ['pendente', 'processando', 'enviado']));
                                        echo count($ativos);
                                        ?>
                                    </h3>
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
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-1 fw-bold">
                                        <?php 
                                        $totalGasto = array_sum(array_column($pedidos, 'valor_total'));
                                        echo 'R$ ' . number_format($totalGasto, 2, ',', '.');
                                        ?>
                                    </h3>
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
                
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-1 fw-bold"><?= count($enderecos) ?></h3>
                                    <p class="text-muted small mb-0">Endereços</p>
                                </div>
                                <div class="ms-3">
                                    <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-map-marker-alt text-info fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-clock me-2"></i> Pedidos Recentes
                        </h5>
                        <a href="/meus-pedidos" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($pedidos_recentes)): ?>
                        <div class="text-center py-5">
                            <div class="bg-light rounded-circle p-4 d-inline-block mb-3">
                                <i class="fas fa-shopping-bag text-muted fs-2"></i>
                            </div>
                            <h5 class="mb-2">Nenhum pedido ainda</h5>
                            <p class="text-muted mb-4">Comece comprando produtos incríveis!</p>
                            <a href="/produtos" class="btn btn-primary">
                                <i class="fas fa-shopping-cart me-2"></i> Ver Produtos
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                        <th>Valor</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pedidos_recentes as $pedido): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                    <i class="fas fa-receipt text-primary fs-6"></i>
                                                </div>
                                                <strong><?= htmlspecialchars($pedido['codigo_pedido']) ?></strong>
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
                                                'pendente' => 'warning',
                                                'processando' => 'info',
                                                'enviado' => 'primary',
                                                'entregue' => 'success',
                                                'cancelado' => 'danger'
                                            ];
                                            $statusLabels = [
                                                'pendente' => 'Pendente',
                                                'processando' => 'Processando',
                                                'enviado' => 'Enviado',
                                                'entregue' => 'Entregue',
                                                'cancelado' => 'Cancelado'
                                            ];
                                            $color = $statusColors[$pedido['status']] ?? 'secondary';
                                            $label = $statusLabels[$pedido['status']] ?? ucfirst($pedido['status']);
                                            ?>
                                            <span class="badge bg-<?= $color ?> px-3 py-2">
                                                <?= $label ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="/pedido/detalhes/<?= $pedido['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-success" onclick="rastrearPedido('<?= $pedido['codigo_pedido'] ?>')">
                                                    <i class="fas fa-search-location"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row g-4 mt-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-bolt me-2"></i> Ações Rápidas
                            </h6>
                            <div class="d-grid gap-2">
                                <a href="/produtos" class="btn btn-outline-primary">
                                    <i class="fas fa-shopping-cart me-2"></i> Comprar Produtos
                                </a>
                                <a href="/carrinho" class="btn btn-outline-success">
                                    <i class="fas fa-shopping-basket me-2"></i> Ver Carrinho
                                </a>
                                <a href="/rastreamento" class="btn btn-outline-info">
                                    <i class="fas fa-search-location me-2"></i> Rastrear Pedido
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-user-cog me-2"></i> Configurações
                            </h6>
                            <div class="d-grid gap-2">
                                <a href="/meus-dados" class="btn btn-outline-secondary">
                                    <i class="fas fa-user-edit me-2"></i> Editar Perfil
                                </a>
                                <a href="/meus-pedidos" class="btn btn-outline-secondary">
                                    <i class="fas fa-history me-2"></i> Histórico
                                </a>
                                <a href="/contato" class="btn btn-outline-secondary">
                                    <i class="fas fa-headset me-2"></i> Suporte
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.user-avatar img {
    border: 3px solid #fff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.nav-link {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
    color: #6c757d;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.nav-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    transform: translateX(5px);
}

.nav-link.active {
    background-color: #6366f1;
    color: white !important;
}

.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
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
</style>

<script>
function rastrearPedido(codigo) {
    // Implementar função de rastreamento
    window.location.href = '/rastreamento?codigo=' + codigo;
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
