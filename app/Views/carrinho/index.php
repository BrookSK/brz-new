<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <?php if (empty($carrinho)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                    <h4>Seu carrinho está vazio</h4>
                    <p class="text-muted">Adicione produtos para continuar comprando</p>
                    <a href="/produtos" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Continuar Comprando
                    </a>
                </div>
            <?php else: ?>
                <!-- Itens do Carrinho -->
                <div class="card">
                    <div class="card-body">
                        <?php foreach ($carrinho as $index => $item): ?>
                        <div class="cart-item border-bottom pb-3 mb-3">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <img src="/uploads/produtos/<?= $item['foto_principal'] ?? 'placeholder.svg' ?>" 
                                         alt="<?= htmlspecialchars($item['nome']) ?>"
                                         class="img-fluid rounded">
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1"><?= htmlspecialchars($item['nome']) ?></h6>
                                    <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                    <div class="mt-2">
                                        <span class="badge bg-primary cart-item-price" data-original-price="<?= $item['valor'] ?>"><?= number_format($item['valor'], 2, ',', '.') ?></span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="input-group input-group-sm">
                                        <button class="btn btn-outline-secondary" onclick="atualizarQuantidade(<?= $item['produto_id'] ?>, <?= max(1, $item['quantidade'] - 1) ?>)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="form-control text-center" 
                                               value="<?= $item['quantidade'] ?>" 
                                               min="1" 
                                               max="<?= $item['estoque'] ?>"
                                               id="quantidade-<?= $item['produto_id'] ?>"
                                               onchange="atualizarQuantidade(<?= $item['produto_id'] ?>, this.value)">
                                        <button class="btn btn-outline-secondary" onclick="atualizarQuantidade(<?= $item['produto_id'] ?>, <?= min($item['estoque'], $item['quantidade'] + 1) ?>)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Estoque: <?= $item['estoque'] ?></small>
                                </div>
                                <div class="col-md-2 text-end">
                                    <div class="fw-bold">
                                        <span class="cart-item-subtotal" data-original-price="<?= $item['subtotal'] ?>"><?= number_format($item['subtotal'], 2, ',', '.') ?></span>
                                    </div>
                                    <small class="text-muted">unit: <span class="cart-item-unit" data-original-price="<?= $item['valor'] ?>"><?= number_format($item['valor'], 2, ',', '.') ?></span></small>
                                </div>
                                <div class="col-md-1 text-end">
                                    <button class="btn btn-sm btn-outline-danger" onclick="removerItem(<?= $item['produto_id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="/produtos" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Continuar Comprando
                    </a>
                    <button class="btn btn-outline-danger float-end" onclick="limparCarrinho()">
                        <i class="fas fa-trash"></i> Limpar Carrinho
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($carrinho)): ?>
        <div class="col-lg-4">
            <!-- Resumo do Pedido -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Resumo do Pedido</h5>
                </div>
                <div class="card-body">
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal (<?= $total_itens ?> itens)</span>
                        <span class="cart-currency subtotal-value" data-original-value="<?= $subtotal ?>"><?= number_format($subtotal, 2, ',', '.') ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Taxa de Serviço (<?= number_format(ceil($peso_total), 0, ',', '.') ?> kg)</span>
                        <span class="cart-currency taxa-servico-value" data-original-value="<?= $taxa_servico ?>"><?= number_format($taxa_servico, 2, ',', '.') ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Impostos (80%)</span>
                        <span class="cart-currency impostos-value" data-original-value="<?= $impostos ?>"><?= number_format($impostos, 2, ',', '.') ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Frete (<?= number_format(ceil($peso_total), 0, ',', '.') ?> kg)</span>
                        <span class="cart-currency frete-value" data-original-value="<?= $frete ?>"><?= number_format($frete, 2, ',', '.') ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <h5>Total</h5>
                        <h5 id="total-valor" class="cart-currency total-value" data-original-value="<?= $total ?>"><?= number_format($total, 2, ',', '.') ?></h5>
                    </div>
                    
                    <div class="d-grid">
                        <a href="/checkout" class="btn btn-primary btn-lg">
                            <i class="fas fa-lock"></i> Finalizar Compra
                        </a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt"></i> Pagamento 100% Seguro
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Informações Importantes -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-info-circle"></i> Informações Importantes</h6>
                    <ul class="small text-muted mb-0">
                        <li>Prazo de entrega: 15-30 dias</li>
                        <li>Impostos inclusos no valor final</li>
                        <li>Taxa de serviço: US$ 39/kg (arredondado para cima)</li>
                        <li>Frete calculado pelo peso total arredondado</li>
                        <li>Seguro contra perda/dano</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function atualizarQuantidade(produtoId, quantidade) {
    if (quantidade < 1) return;
    
    $.ajax({
        url: '/carrinho/atualizar',
        method: 'POST',
        data: {
            id: produtoId,
            quantidade: quantidade
        },
        success: function(response) {
            if (response.success) {
                location.reload(); // Recarregar para atualizar valores
            } else {
                alert(response.error);
            }
        },
        error: function() {
            alert('Erro ao atualizar carrinho');
        }
    });
}

function removerItem(produtoId) {
    if (confirm('Deseja remover este item do carrinho?')) {
        $.ajax({
            url: '/carrinho/remover',
            method: 'POST',
            data: {
                id: produtoId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.error);
                }
            },
            error: function() {
                alert('Erro ao remover item');
            }
        });
    }
}
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
