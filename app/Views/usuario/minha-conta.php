<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4 account-sidebar">
            <div class="card shadow-sm">
                <div class="card-body text-center">
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
                    ?>
                    <div class="user-avatar mx-auto mb-3">
                        <?php if (!empty($avatarUrl) && is_string($avatarUrl)): ?>
                            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($usuario['nome']) ?>">
                        <?php else: ?>
                            <?= strtoupper(substr($usuario['nome'], 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <h5 class="card-title"><?= htmlspecialchars($usuario['nome']) ?></h5>
                    <p class="text-muted"><?= htmlspecialchars($usuario['email']) ?></p>
                    <span class="badge bg-primary"><?= ucfirst($usuario['perfil']) ?></span>
                </div>
            </div>
            
            <div class="card shadow-sm mt-3">
                <div class="card-body">
                    <h6 class="card-title">Menu Rápido</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="/minha-conta">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="/meus-dados">
                            <i class="fas fa-user me-2"></i> Meus Dados
                        </a>
                        <a class="nav-link" href="/meus-pedidos">
                            <i class="fas fa-shopping-bag me-2"></i> Meus Pedidos
                        </a>
                        <a class="nav-link" href="/carrinho">
                            <i class="fas fa-shopping-cart me-2"></i> Meu Carrinho
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="/logout">
                            <i class="fas fa-sign-out-alt me-2"></i> Sair
                        </a>
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4 user-page-header">
                <h2><i class="fas fa-tachometer-alt"></i> Minha Conta</h2>
                <span class="text-muted">
                    Bem-vindo, <strong><?= htmlspecialchars($usuario['nome']) ?></strong>!
                </span>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $total_pedidos ?></h4>
                                    <small>Total de Pedidos</small>
                                </div>
                                <i class="fas fa-shopping-bag fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0">
                                        <?php 
                                        echo (int) ($pedidos_ativos ?? 0);
                                        ?>
                                    </h4>
                                    <small>Pedidos Ativos</small>
                                </div>
                                <i class="fas fa-truck fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0">
                                        <?php 
                                        $tgBRL = floatval($total_gasto_brl ?? 0);
                                        $tgUSD = floatval($total_gasto_usd ?? 0);
                                        if ($tgUSD > 0 && $tgBRL > 0) {
                                            echo 'R$ ' . number_format($tgBRL, 2, ',', '.') . '<br><span class="text-white-50" style="font-size: 0.85rem;">US$ ' . number_format($tgUSD, 2, ',', '.') . '</span>';
                                        } elseif ($tgUSD > 0) {
                                            echo 'US$ ' . number_format($tgUSD, 2, ',', '.');
                                        } else {
                                            echo 'R$ ' . number_format($tgBRL, 2, ',', '.');
                                        }
                                        ?>
                                    </h4>
                                    <small>Total Gasto</small>
                                </div>
                                <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= count($enderecos) ?></h4>
                                    <small>Endereços</small>
                                </div>
                                <i class="fas fa-map-marker-alt fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Pedidos Recentes</h5>
                    <a href="/meus-pedidos" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                </div>
                <div class="card-body">
                    <?php if (empty($pedidos_recentes)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                            <h6>Nenhum pedido ainda</h6>
                            <p class="text-muted">Comece comprando produtos incríveis!</p>
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
                                            <strong><?= htmlspecialchars($pedido['codigo_pedido']) ?></strong>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($pedido['created_at'])) ?></td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'pendente' => 'warning',
                                                'processando' => 'info',
                                                'enviado' => 'primary',
                                                'entregue' => 'success',
                                                'cancelado' => 'danger'
                                            ];
                                            $color = $statusColors[$pedido['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>">
                                                <?= ucfirst($pedido['status']) ?>
                                            </span>
                                        </td>
                                        <td>R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                                        <td>
                                            <a href="/pedido/detalhes/<?= $pedido['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-robot"></i> Orçamentos da Assessoria</h5>
                    <a href="/assessoria" class="btn btn-sm btn-outline-primary">Novo Orçamento</a>
                </div>
                <div class="card-body">
                    <?php $orcamentosAssessoria = $orcamentos_assessoria ?? []; ?>
                    <?php if (empty($orcamentosAssessoria)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                            <h6>Nenhum orçamento ainda</h6>
                            <p class="text-muted">Gere um orçamento pela Assessoria para aparecer aqui.</p>
                            <a href="/assessoria" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Criar Orçamento
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                        <th>Tempo</th>
                                        <th>Pedido</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orcamentosAssessoria as $o): ?>
                                        <?php
                                            $status = (string) ($o['status'] ?? 'rascunho');
                                            $isPago = ($status === 'pago');
                                            $pedidoId = (int) ($o['pedido_id'] ?? 0);
                                            $createdAt = !empty($o['created_at']) ? strtotime((string) $o['created_at']) : null;
                                            $expiresAt = ($createdAt !== null) ? ($createdAt + (15 * 60)) : null;
                                            $remaining = ($expiresAt !== null) ? max(0, $expiresAt - time()) : 0;
                                            $isExpired = (!$isPago) && ($expiresAt !== null) && ($remaining <= 0);
                                        ?>
                                        <tr>
                                            <td>#<?= (int) ($o['id'] ?? 0) ?></td>
                                            <td><?= !empty($o['created_at']) ? date('d/m/Y H:i', strtotime($o['created_at'])) : '-' ?></td>
                                            <td>
                                                <?php if ($isPago): ?>
                                                    <span class="badge bg-success">Pago</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Rascunho</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isPago): ?>
                                                    -
                                                <?php else: ?>
                                                    <span class="assessoria-timer" data-remaining="<?= (int) $remaining ?>" style="color: #dc3545; font-weight: 700;">
                                                        <?= $isExpired ? '00:00' : gmdate('i:s', $remaining) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($pedidoId > 0): ?>
                                                    <a href="/pedido/detalhes/<?= $pedidoId ?>" class="text-decoration-none">#<?= $pedidoId ?></a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($isPago): ?>
                                                    <a href="/assessoria/orcamento?orcamento_id=<?= (int) ($o['id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">
                                                        Abrir
                                                    </a>
                                                <?php else: ?>
                                                    <?php if ($isExpired): ?>
                                                        <a href="/assessoria/orcamento?orcamento_id=<?= (int) ($o['id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">
                                                            Ver orçamento
                                                        </a>
                                                        <a href="/assessoria/reprocessar?orcamento_id=<?= (int) ($o['id'] ?? 0) ?>" class="btn btn-sm btn-primary">
                                                            Reprocessar
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="/assessoria/orcamento?orcamento_id=<?= (int) ($o['id'] ?? 0) ?>" class="btn btn-sm btn-success">
                                                            Finalizar orçamento
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            (function() {
                function pad2(n) {
                    return (n < 10 ? '0' : '') + n;
                }

                function tick() {
                    document.querySelectorAll('.assessoria-timer').forEach(function(el) {
                        var r = parseInt(el.getAttribute('data-remaining') || '0', 10);
                        if (isNaN(r) || r <= 0) {
                            el.textContent = '00:00';
                            el.setAttribute('data-remaining', '0');
                            return;
                        }
                        r = r - 1;
                        el.setAttribute('data-remaining', String(r));
                        var m = Math.floor(r / 60);
                        var s = r % 60;
                        el.textContent = pad2(m) + ':' + pad2(s);
                    });
                }

                setInterval(tick, 1000);
            })();
            </script>
        </div>
            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-bolt"></i> Ações Rápidas</h6>
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
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-user-cog"></i> Configurações</h6>
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
.account-sidebar .user-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    overflow: hidden;
}

.account-sidebar .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.nav-link {
    border-radius: 8px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.nav-link.active {
    background: var(--primary-gradient);
    color: white !important;
}

.card {
    border: none;
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
    text-transform: uppercase;
}

@media (max-width: 767.98px) {
    .user-page-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.35rem;
    }

    .user-page-header h2 {
        font-size: 1.5rem;
        margin-bottom: 0;
    }
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
