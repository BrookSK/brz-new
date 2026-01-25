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
                        <a class="nav-link mb-2" href="/minha-conta">
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
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="/meus-pedidos">Meus Pedidos</a></li>
                            <li class="breadcrumb-item active">Detalhes do Pedido</li>
                        </ol>
                    </nav>
                    <h2 class="mb-1">Detalhes do Pedido #<?= htmlspecialchars($pedido['codigo_pedido'] ?? $pedido['id']) ?></h2>
                    <p class="text-muted mb-0">Data: <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></p>
                </div>
                <div class="text-end">
                    <span class="badge bg-<?= getStatusColor($pedido['status']) ?> fs-6">
                        <?= getStatusText($pedido['status']) ?>
                    </span>
                </div>
            </div>
            
            <!-- Order Status Timeline -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-truck me-2"></i> Status do Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php if (!empty($historico)): ?>
                            <?php foreach ($historico as $index => $item): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker">
                                        <i class="fas fa-check-circle text-success"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['novo_status'] ?? 'Status atualizado') ?></h6>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($item['observacao'] ?? 'Sem observação') ?></p>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Nenhum histórico de status disponível.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-box me-2"></i> Itens do Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pedido['items'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-center">Qtd</th>
                                        <th class="text-end">Preço Unit.</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pedido['items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="https://novobr.brazilianashop.com.br/uploads/produtos/<?= $item['imagem'] ?? 'default.jpg' ?>" 
                                                         alt="<?= htmlspecialchars($item['nome_produto'] ?? 'Produto') ?>" 
                                                         class="rounded me-3" width="50" height="50">
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($item['nome_produto'] ?? 'Produto sem nome') ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($item['referencia'] ?? '') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center"><?= $item['quantidade'] ?? 1 ?></td>
                                            <td class="text-end">R$ <?= number_format($item['preco_unitario'] ?? 0, 2, ',', '.') ?></td>
                                            <td class="text-end fw-bold">R$ <?= number_format($item['subtotal'] ?? 0, 2, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Nenhum item encontrado neste pedido.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-calculator me-2"></i> Resumo do Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Endereço de Entrega</h6>
                            <address class="mb-0">
                                <?= htmlspecialchars($pedido['endereco_entrega'] ?? 'Não informado') ?>, <?= htmlspecialchars($pedido['numero_entrega'] ?? '') ?><br>
                                <?= htmlspecialchars($pedido['complemento_entrega'] ?? '') ?><br>
                                <?= htmlspecialchars($pedido['bairro_entrega'] ?? '') ?><br>
                                <?= htmlspecialchars($pedido['cidade_entrega'] ?? '') ?> - <?= htmlspecialchars($pedido['estado_entrega'] ?? '') ?><br>
                                CEP: <?= htmlspecialchars($pedido['cep_entrega'] ?? '') ?>
                            </address>
                        </div>
                        <div class="col-md-6">
                            <h6>Valores</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span>R$ <?= number_format($pedido['subtotal_produtos'] ?? 0, 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Frete:</span>
                                <span>R$ <?= number_format($pedido['valor_frete'] ?? 0, 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Taxa de Serviço:</span>
                                <span>R$ <?= number_format($pedido['taxa_servico'] ?? 0, 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Impostos:</span>
                                <span>R$ <?= number_format($pedido['valor_impostos'] ?? 0, 2, ',', '.') ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Total:</span>
                                <span class="text-primary">R$ <?= number_format($pedido['valor_total'] ?? 0, 2, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="d-flex gap-2">
                <a href="/meus-pedidos" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Voltar para Pedidos
                </a>
                <?php if ($pedido['status'] === 'pago'): ?>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Imprimir
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 30px;
    height: 30px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e9ecef;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #6366f1;
}

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

@media print {
    .d-flex.gap-2 {
        display: none !important;
    }
    
    .timeline::before {
        background: #000 !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
}
</style>

<?php
// Helper functions
function getStatusColor($status) {
    $colors = [
        'pendente' => 'warning',
        'processando' => 'info',
        'enviado' => 'primary',
        'entregue' => 'success',
        'cancelado' => 'danger',
        'pago' => 'success'
    ];
    return $colors[$status] ?? 'secondary';
}

function getStatusText($status) {
    $texts = [
        'pendente' => 'Pendente',
        'processando' => 'Processando',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
        'pago' => 'Pago'
    ];
    return $texts[$status] ?? ucfirst($status);
}
?>

<?php $content = ob_get_clean(); echo $content; ?>
