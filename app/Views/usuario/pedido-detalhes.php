<?php
ob_start();
use App\Core\Url;

function getStatusColor($status) {
    $colors = [
        'pendente' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.35)', 'color' => 'rgba(124, 45, 18, 1)'],
        'processando' => ['bg' => 'rgba(56, 189, 248, 0.12)', 'border' => 'rgba(56, 189, 248, 0.22)', 'color' => 'rgba(11, 31, 58, 1)'],
        'enviado' => ['bg' => 'rgba(11, 31, 58, 0.08)', 'border' => 'rgba(11, 31, 58, 0.14)', 'color' => 'rgba(11, 31, 58, 1)'],
        'entregue' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)'],
        'cancelado' => ['bg' => 'rgba(239, 68, 68, 0.10)', 'border' => 'rgba(239, 68, 68, 0.18)', 'color' => 'rgba(185, 28, 28, 1)'],
        'pago' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)']
    ];

    $statusKey = is_string($status) ? strtolower($status) : '';
    return $colors[$statusKey] ?? ['bg' => 'rgba(148, 163, 184, 0.18)', 'border' => 'rgba(148, 163, 184, 0.35)', 'color' => 'rgba(15, 23, 42, 0.82)'];
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

    $statusKey = is_string($status) ? strtolower($status) : '';
    return $texts[$statusKey] ?? (is_string($status) ? ucfirst($status) : '');
}

$badgePedido = getStatusColor($pedido['status'] ?? '');
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/meus-pedidos" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
                <h1 class="h4 mb-0">Pedido #<?= htmlspecialchars($pedido['codigo_pedido'] ?? $pedido['id']) ?></h1>
            </div>
            <div class="text-muted small">Data: <?= !empty($pedido['created_at']) ? date('d/m/Y \à\s H:i', strtotime($pedido['created_at'])) : '-' ?></div>
        </div>
        <div>
            <span class="badge" style="background: <?= $badgePedido['bg'] ?>; border: 1px solid <?= $badgePedido['border'] ?>; color: <?= $badgePedido['color'] ?>;">
                <?= getStatusText($pedido['status'] ?? '') ?>
            </span>
        </div>
    </div>

        <!-- Main Content -->
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <?php
                        $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode((string) ($usuario['nome'] ?? '')) . '&background=6366f1&color=fff&size=128';
                    ?>
                    <div class="user-avatar mx-auto mb-3">
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" class="rounded-circle" width="80" height="80" style="object-fit: cover;">
                    </div>
                    <h5 class="card-title mb-1"><?= htmlspecialchars($usuario['nome'] ?? '') ?></h5>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($usuario['email'] ?? '') ?></p>
                    <span class="badge px-3 py-2" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                        <?= ucfirst((string) ($usuario['perfil'] ?? '')) ?>
                    </span>
                </div>
            </div>

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
                        <a class="nav-link active mb-2" href="/meus-pedidos">
                            <i class="fas fa-shopping-bag me-2"></i> Meus Pedidos
                        </a>
                        <a class="nav-link mb-2" href="/carrinho">
                            <i class="fas fa-shopping-cart me-2"></i> Meu Carrinho
                            <?php if (!empty($_SESSION['carrinho'])): ?>
                                <span class="badge rounded-pill ms-auto" style="background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.18); color: rgba(185, 28, 28, 1);">
                                    <?= count($_SESSION['carrinho']) ?>
                                </span>
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
                    <!-- Status Timeline -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
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
                                                <h6><?= htmlspecialchars($item['novo_status'] ?? 'Status atualizado') ?></h6>
                                                <p class="mb-0"><?= htmlspecialchars($item['observacao'] ?? 'Sem observação') ?></p>
                                                <small><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?> - Por: <?= htmlspecialchars($item['usuario_alterou'] ?? 'Sistema') ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhum histórico de status disponível para este pedido.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-box me-2"></i> Itens do Pedido
                                <span class="badge ms-2" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                    <?= count($pedido['items']) ?> itens
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pedido['items'])): ?>
                                <?php foreach ($pedido['items'] as $item): ?>
                                    <div class="product-item">
                                        <img src="<?= Url::absolute('/uploads/produtos/' . ($item['imagem'] ?? 'default.jpg')) ?>" 
                                             alt="<?= htmlspecialchars($item['nome_produto'] ?? 'Produto') ?>" 
                                             class="product-image">
                                        <div class="product-info flex-grow-1">
                                            <h6><?= htmlspecialchars($item['nome_produto'] ?? 'Produto sem nome') ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($item['referencia'] ?? '') ?></small>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="quantity-badge"><?= $item['quantidade'] ?? 1 ?></span>
                                            <span class="text-muted">x</span>
                                            <span class="fw-bold">R$ <?= number_format($item['preco_unitario'] ?? 0, 2, ',', '.') ?></span>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">Subtotal: R$ <?= number_format($item['subtotal'] ?? 0, 2, ',', '.') ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Nenhum item encontrado neste pedido.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i> Endereço de Entrega
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <address class="mb-0">
                                        <?= htmlspecialchars($pedido['endereco_entrega'] ?? 'Não informado') ?>, <?= htmlspecialchars($pedido['numero_entrega'] ?? '') ?><br>
                                        <?= htmlspecialchars($pedido['complemento_entrega'] ?? '') ?><br>
                                        <?= htmlspecialchars($pedido['bairro_entrega'] ?? '') ?><br>
                                        <?= htmlspecialchars($pedido['cidade_entrega'] ?? '') ?> - <?= htmlspecialchars($pedido['estado_entrega'] ?? '') ?><br>
                                        CEP: <?= htmlspecialchars($pedido['cep_entrega'] ?? '') ?>
                                    </address>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calculator me-2"></i> Resumo do Pedido
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="price-row">
                                        <span>Subtotal:</span>
                                        <span>R$ <?= number_format($pedido['subtotal_produtos'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <div class="price-row">
                                        <span>Frete:</span>
                                        <span>R$ <?= number_format($pedido['valor_frete'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <div class="price-row">
                                        <span>Taxa de Serviço:</span>
                                        <span>R$ <?= number_format($pedido['taxa_servico'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <div class="price-row">
                                        <span>Impostos:</span>
                                        <span>R$ <?= number_format($pedido['valor_impostos'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <hr>
                                    <div class="price-row">
                                        <span>Total:</span>
                                        <span class="text-primary">R$ <?= number_format($pedido['valor_total'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($paymentDetails) || !empty($pedido['pagamento_gateway']) || !empty($pedido['payment_gateway'])): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card me-2"></i> Pagamento
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $statusPagamento = $pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? ($paymentDetails['status'] ?? null));
                            if (is_string($statusPagamento)) {
                                $statusPagamento = strtoupper($statusPagamento);
                            }

                            $badgeClass = 'bg-warning text-dark';
                            $statusLabel = 'Aguardando';
                            if (!empty($statusPagamento)) {
                                if (in_array($statusPagamento, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                                    $badgeClass = 'bg-success';
                                    $statusLabel = 'Pago';
                                } elseif (in_array($statusPagamento, ['REJECTED', 'CANCELED', 'CANCELLED', 'DELETED'], true)) {
                                    $badgeClass = 'bg-danger';
                                    $statusLabel = 'Cancelado';
                                } elseif (in_array($statusPagamento, ['REFUNDED'], true)) {
                                    $badgeClass = 'bg-secondary';
                                    $statusLabel = 'Estornado';
                                }
                            }

                            $billingType = strtoupper((string) ($paymentDetails['billingType'] ?? ''));
                            $invoiceUrl = $paymentDetails['invoiceUrl'] ?? null;
                            $bankSlipUrl = $paymentDetails['bankSlipUrl'] ?? null;
                            $digitableLine = $paymentDetails['digitableLine'] ?? null;
                            ?>

                            <p class="mb-2 text-muted">
                                <small>Status do pagamento: <span class="badge" style="background: rgba(148, 163, 184, 0.18); border: 1px solid rgba(148, 163, 184, 0.35); color: rgba(15, 23, 42, 0.82);"><?= htmlspecialchars($statusLabel) ?></span></small>
                            </p>

                            <?php if ($billingType === 'PIX' && !empty($pixQrCode)): ?>
                                <?php $pixImage = $pixQrCode['encodedImage'] ?? null; ?>
                                <?php $pixPayload = $pixQrCode['payload'] ?? null; ?>
                                <?php if (!empty($pixImage)): ?>
                                    <div class="text-center my-3">
                                        <img src="data:image/png;base64,<?= $pixImage ?>" alt="QR Code PIX" style="max-width: 240px; width: 100%; height: auto;" />
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($pixPayload)): ?>
                                    <div class="mb-2">
                                        <strong>Copia e cola:</strong>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($pixPayload) ?>" readonly onclick="this.select();" />
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($billingType === 'BOLETO'): ?>
                                <?php if (!empty($bankSlipUrl) || !empty($invoiceUrl)): ?>
                                    <p class="mb-2">
                                        <a class="btn btn-outline-primary" href="<?= htmlspecialchars($bankSlipUrl ?: $invoiceUrl) ?>" target="_blank" rel="noopener">Abrir boleto</a>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($digitableLine)): ?>
                                    <div class="mb-2">
                                        <strong>Linha digitável:</strong>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($digitableLine) ?>" readonly onclick="this.select();" />
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php
                            $statusInterno = (string) ($pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? ''));
                            $podeReemitir = in_array(strtoupper($statusInterno), ['PENDING', 'PENDENTE', 'OVERDUE'], true);
                            ?>

                            <?php if ($podeReemitir && in_array($billingType, ['PIX', 'BOLETO'], true) && !empty($pedido['id'])): ?>
                                <form method="POST" action="/pedido/reemitir-pagamento/<?= (int) $pedido['id'] ?>" class="mt-3">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        Gerar nova cobrança
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="d-flex gap-2 flex-wrap mt-4">
                <a href="/meus-pedidos" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Voltar
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgba(148, 163, 184, 0.35);
}

.timeline-item {
    position: relative;
    margin-bottom: 1.25rem;
}

.timeline-marker {
    position: absolute;
    left: -2rem;
    top: 0;
    width: 2rem;
    height: 2rem;
    background: #fff;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(148, 163, 184, 0.35);
}

.timeline-content {
    background: rgba(255, 255, 255, 0.9);
    padding: 0.9rem 1rem;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    margin-left: 1rem;
}

.product-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.22);
}

.product-item:last-child {
    border-bottom: none;
}

.product-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 12px;
    margin-right: 1rem;
}

.quantity-badge {
    background: rgba(11, 31, 58, 0.08);
    border: 1px solid rgba(11, 31, 58, 0.14);
    color: rgba(11, 31, 58, 1);
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

@media print {
    .btn,
    .nav,
    .navbar,
    footer {
        display: none !important;
    }
}
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
