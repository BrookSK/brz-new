<?php ob_start(); ?>
<?php use App\Core\Url; ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <?php if (empty($carrinho)): ?>
                <?php include __DIR__ . '/vazio.php'; ?>
            <?php else: ?>
                <!-- Itens do Carrinho -->
                <div class="card">
                    <div class="card-body">
                        <?php foreach ($carrinho as $index => $item): ?>
                        <div class="cart-item border-bottom pb-3 mb-3">
                            <div class="row align-items-center">
                                <div class="col-4 col-md-2 mb-2 mb-md-0">
                                    <?php 
                                    $fotoPrincipal = null;
                                    $fotosProduto = [];
                                    try {
                                        $fotoModel = new \App\Models\ProdutoFoto();
                                        $fotoPrincipal = $fotoModel->getFotoPrincipal($item['produto_id']);
                                        if (!$fotoPrincipal) {
                                            $fotosProduto = $fotoModel->getFotosProduto($item['produto_id']);
                                        }
                                    } catch (\Exception $e) {
                                        // Ignorar erro
                                    }
                                    
                                    $fotoUrl = null;
                                    $fotoArquivo = $fotoPrincipal['nome_arquivo'] ?? ($fotosProduto[0]['nome_arquivo'] ?? null);
                                    if (!empty($fotoArquivo) && is_string($fotoArquivo)) {
                                        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
                                        $rel = '/' . ltrim((string) $fotoArquivo, '/');
                                        $fotoExists = (
                                            ($docRoot !== '' && file_exists($docRoot . $rel)) ||
                                            ($docRoot !== '' && file_exists($docRoot . '/public' . $rel))
                                        );
                                        if ($fotoExists) {
                                            $fotoUrl = Url::absolute($fotoArquivo);
                                        }
                                    }
                                    if (empty($fotoUrl)) {
                                        $fotoUrl = Url::absolute('/uploads/produtos/placeholder.jpg');
                                    }
                                    ?>
                                    <img src="<?= $fotoUrl ?>?v=<?= time() ?>" 
                                         alt="<?= htmlspecialchars($item['nome']) ?>"
                                         class="img-fluid rounded">
                                </div>
                                <div class="col-8 col-md-5">
                                    <h6 class="mb-1"><?= htmlspecialchars($item['nome']) ?></h6>
                                    <div class="input-group input-group-sm" style="max-width: 240px;">
                                        <button class="btn btn-outline-secondary" onclick="atualizarQuantidade(<?= $item['produto_id'] ?>, <?= max(1, $item['quantidade'] - 1) ?>)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="form-control text-center" 
                                               value="<?= $item['quantidade'] ?>" 
                                               min="1" 
                                               max="999"
                                               id="quantidade-<?= $item['produto_id'] ?>"
                                               onchange="atualizarQuantidade(<?= $item['produto_id'] ?>, this.value)">
                                        <button class="btn btn-outline-secondary" onclick="atualizarQuantidade(<?= $item['produto_id'] ?>, <?= $item['quantidade'] + 1 ?>)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">ID: <?= $item['produto_id'] ?></small>
                                </div>
                                <div class="col-8 col-md-3 text-start text-md-end mt-2 mt-md-0">
                                    <div class="fw-bold">
                                        <span class="cart-item-subtotal" data-original-price="<?= $item['subtotal'] ?>">
                                            <?= number_format($item['subtotal'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">unit: 
                                        <span class="cart-item-unit" data-original-price="<?= $item['price'] ?? $item['preco_unitario'] ?>">
                                            <?= number_format($item['price'] ?? $item['preco_unitario'], 2, ',', '.') ?>
                                        </span>
                                    </small>
                                </div>
                                <div class="col-4 col-md-1 text-end mt-2 mt-md-0">
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
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between">
                        <a href="/produtos" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Continuar Comprando
                        </a>
                        <button class="btn btn-outline-danger" onclick="limparCarrinho()">
                            <i class="fas fa-trash"></i> Limpar Carrinho
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <?php if (empty($carrinho)): ?>
                <!-- Resumo do Pedido (Carrinho vazio) -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Resumo do Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal (0 itens)</span>
                            <span class="cart-currency subtotal-value" data-original-value="0">0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Taxa de Serviço (0 kg)</span>
                            <span class="cart-currency taxa-servico-value" data-original-value="0">0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Impostos</span>
                            <span class="cart-currency impostos-value" data-original-value="0">0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Frete (0 kg)</span>
                            <span class="cart-currency frete-value" data-original-value="0">0,00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <h5>Total</h5>
                            <h5 id="total-valor" class="cart-currency total-value" data-original-value="0">0,00</h5>
                        </div>
                        <div class="d-grid">
                            <a href="/checkout" class="btn btn-primary btn-lg disabled" aria-disabled="true" tabindex="-1">
                                <i class="fas fa-lock"></i> Finalizar Compra
                            </a>
                        </div>
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Adicione itens ao carrinho para continuar.
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
            <?php else: ?>
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
                        <span>Impostos</span>
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
            <?php endif; ?>
        </div>
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
