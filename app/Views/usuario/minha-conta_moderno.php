<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Profile Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <?php
                        $avatarColumnCandidates = ['avatar', 'foto_perfil', 'imagem_perfil', 'foto'];
                        $avatarUrl = null;
                        foreach ($avatarColumnCandidates as $c) {
                            if (!empty($usuario[$c]) && is_string($usuario[$c])) {
                                $avatarUrl = $usuario[$c];
                                break;
                            }
                        }
                        if (empty($avatarUrl)) {
                            $avatarUrl = $_SESSION['usuario_avatar'] ?? null;
                        }
                        if (empty($avatarUrl)) {
                            $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($usuario['nome']) . '&background=0b1f3a&color=fff&size=128';
                        }
                    ?>
                    <div class="user-avatar mx-auto mb-3">
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" 
                             alt="<?= htmlspecialchars($usuario['nome']) ?>" 
                             class="rounded-circle" width="80" height="80" style="object-fit: cover;">
                    </div>
                    <h5 class="card-title mb-1"><?= htmlspecialchars($usuario['nome']) ?></h5>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($usuario['email']) ?></p>
                    <span class="badge px-3 py-2" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                        <?= ucfirst($usuario['perfil']) ?>
                    </span>
                    <?php $suiteValue = $usuario['suite'] ?? ($usuario['switch'] ?? null); ?>
                    <?php if (!empty($suiteValue)): ?>
                        <div class="mt-2 small text-muted"><?= __('user.suite', 'Suite') ?>: <strong><?= (int) $suiteValue ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Menu -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">
                    <h6 class="card-title mb-3"><?= __('user.quick_menu', 'Menu Rápido') ?></h6>
                    <nav class="nav flex-column">
                        <a class="nav-link active mb-2" href="/minha-conta">
                            <i class="fas fa-tachometer-alt me-2"></i> <?= __('user.dashboard', 'Dashboard') ?>
                        </a>
                        <a class="nav-link mb-2" href="/meus-dados">
                            <i class="fas fa-user me-2"></i> <?= __('user.my_data', 'Meus Dados') ?>
                        </a>
                        <a class="nav-link mb-2" href="/meus-pedidos">
                            <i class="fas fa-shopping-bag me-2"></i> <?= __('user.my_orders', 'Meus Pedidos') ?>
                        </a>
                        <a class="nav-link mb-2" href="/carrinho">
                            <i class="fas fa-shopping-cart me-2"></i> <?= __('user.my_cart', 'Meu Carrinho') ?>
                            <?php if (!empty($_SESSION['carrinho'])): ?>
                                <span class="badge rounded-pill ms-auto" style="background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.18); color: rgba(185, 28, 28, 1);">
                                    <?= count($_SESSION['carrinho']) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <hr class="my-3">
                        <a class="nav-link text-danger mb-2" href="/logout">
                            <i class="fas fa-sign-out-alt me-2"></i> <?= __('auth.logout', 'Sair') ?>
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
                    <h2 class="mb-1"><?= __('user.my_account', 'Minha Conta') ?></h2>
                    <p class="text-muted mb-0"><?= __('user.welcome_back', 'Bem-vindo de volta, {name}!', ['name' => '<strong>' . htmlspecialchars($usuario['nome']) . '</strong>']) ?></p>
                </div>
                <div class="text-end">
                    <small class="text-muted"><?= __('user.last_access', 'Último acesso:') ?></small><br>
                    <strong><?= date('d/m/Y H:i', strtotime($usuario['ultimo_acesso'] ?? 'now')) ?></strong>
                </div>
            </div>

            <?php if (!empty($usuario['precisa_recadastro'])): ?>
                <div class="alert alert-warning" role="alert" style="background: #fff3cd; border-color: #ffecb5; color: #664d03;">
                    <?= __('user.recadastro_warning', 'Como este é um site novo, precisamos que você atualize seus dados cadastrais. ') ?>
                    <a href="/meus-dados" class="alert-link"><?= __('user.recadastro_cta', 'Clique aqui para atualizar agora.') ?></a>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-1 fw-bold"><?= $total_pedidos ?></h3>
                                    <p class="text-muted small mb-0"><?= __('user.stats.total_orders', 'Total de Pedidos') ?></p>
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
                                    <p class="text-muted small mb-0"><?= __('user.stats.active_orders', 'Pedidos Ativos') ?></p>
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
                                    <p class="text-muted small mb-0"><?= __('user.stats.total_spent', 'Total Gasto') ?></p>
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
                                    <p class="text-muted small mb-0"><?= __('user.stats.addresses', 'Endereços') ?></p>
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
            
            <!-- Orçamentos da Assessoria -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-clipboard-list me-2"></i> <?= __('user.redirect_quotes', 'Orçamentos do Redirecionamento') ?>
                        </h5>
                        <a href="/assessoria" class="btn btn-sm btn-outline-primary"><?= __('user.new_quote', 'Novo Orçamento') ?></a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Conteúdo do orçamento -->
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row g-4 mt-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-bolt me-2"></i> <?= __('user.quick_actions', 'Ações Rápidas') ?>
                            </h6>
                            <div class="d-grid gap-2">
                                <a href="/produtos" class="btn btn-outline-primary">
                                    <i class="fas fa-shopping-cart me-2"></i> <?= __('user.buy_products', 'Comprar Produtos') ?>
                                </a>
                                <a href="/carrinho" class="btn btn-outline-success">
                                    <i class="fas fa-shopping-basket me-2"></i> <?= __('user.view_cart', 'Ver Carrinho') ?>
                                </a>
                                <a href="/rastreamento" class="btn btn-outline-info">
                                    <i class="fas fa-search-location me-2"></i> <?= __('user.track_order', 'Rastrear Pedido') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-user-cog me-2"></i> <?= __('user.settings', 'Configurações') ?>
                            </h6>
                            <div class="d-grid gap-2">
                                <a href="/meus-dados" class="btn btn-outline-secondary">
                                    <i class="fas fa-user-edit me-2"></i> <?= __('user.edit_profile', 'Editar Perfil') ?>
                                </a>
                                <a href="/meus-pedidos" class="btn btn-outline-secondary">
                                    <i class="fas fa-history me-2"></i> <?= __('user.history', 'Histórico') ?>
                                </a>
                                <a href="/contato" class="btn btn-outline-secondary">
                                    <i class="fas fa-headset me-2"></i> <?= __('user.support', 'Suporte') ?>
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
    transform: none;
}

.nav-link.active {
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
</style>

<script>
function rastrearPedido(codigo) {
    // Implementar função de rastreamento
    window.location.href = '/rastreamento?codigo=' + codigo;
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
