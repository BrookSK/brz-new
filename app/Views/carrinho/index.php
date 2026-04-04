<?php ob_start(); ?>
<?php use App\Core\Url; ?>
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <?php if (empty($carrinho)): ?>
                <?php include __DIR__ . '/vazio.php'; ?>
            <?php else: ?>
                <?php $hasCartChanges = !empty($cart_changes) && is_array($cart_changes); ?>
                <?php if ($hasCartChanges): ?>
                    <div class="alert alert-warning">
                        <strong>Atenção:</strong> alguns produtos do seu carrinho tiveram alterações. Revise os itens marcados antes de finalizar.
                    </div>
                <?php endif; ?>
                <!-- Itens do Carrinho -->
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <?php foreach ($carrinho as $index => $item): ?>
                        <?php $itemKeyStable = ((string) ($item['produto_id'] ?? '0')) . ':' . ((string) ((int) ($item['produto_variacao_id'] ?? 0))); ?>
                        <?php $isAtivo = !empty($item['ativo']); ?>
                        <div class="cart-item border-bottom pb-3 mb-3 <?= $isAtivo ? '' : 'opacity-50' ?>">
                            <div class="row align-items-center">
                                <div class="col-2 col-md-1 mb-2 mb-md-0 d-flex justify-content-center">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox"
                                               <?= $isAtivo ? 'checked' : '' ?>
                                               onchange='toggleAtivo(<?= htmlspecialchars(json_encode((string) $itemKeyStable), ENT_QUOTES, "UTF-8") ?>, this.checked ? 1 : 0)'>
                                    </div>
                                </div>
                                <div class="col-4 col-md-2 mb-2 mb-md-0">
                                    <?php 
                                    $fotoUrl = null;
                                    $capaArquivo = null;
                                    $isClubeAtivo = false;
                                    try {
                                        $produtoModel = new \App\Models\Produto();
                                        $produtoCarrinho = $produtoModel->find($item['produto_id']);
                                        if (!empty($produtoCarrinho) && is_array($produtoCarrinho) && !empty($produtoCarrinho['foto_principal'])) {
                                            $capaArquivo = (string) $produtoCarrinho['foto_principal'];
                                        }

                                        if (!empty($produtoCarrinho) && is_array($produtoCarrinho) && !empty($produtoCarrinho['clube_ativo'])) {
                                            $isClubeAtivo = ((int) ($produtoCarrinho['clube_ativo'] ?? 0)) === 1;
                                        }
                                    } catch (\Exception $e) {
                                    }

                                    if (!empty($capaArquivo) && is_string($capaArquivo)) {
                                        $capaArquivo = trim($capaArquivo);
                                        if ($capaArquivo !== '') {
                                            if (preg_match('#^https?://#i', $capaArquivo)) {
                                                $fotoUrl = $capaArquivo;
                                            }
                                            if ($capaArquivo[0] !== '/') {
                                                $capaArquivo = '/' . $capaArquivo;
                                            }
                                            if (strpos($capaArquivo, '/uploads/') !== 0) {
                                                $capaArquivo = '/uploads/produtos/' . ltrim($capaArquivo, '/');
                                            }

                                            $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
                                            $rel = '/' . ltrim((string) $capaArquivo, '/');
                                            $capaExists = (
                                                ($docRoot !== '' && file_exists($docRoot . $rel)) ||
                                                ($docRoot !== '' && file_exists($docRoot . '/public' . $rel))
                                            );
                                            if ($capaExists) {
                                                $fotoUrl = Url::absolute($capaArquivo);
                                            }
                                        }
                                    }

                                    if (empty($fotoUrl)) {
                                        try {
                                            if (!empty($produtoCarrinho) && is_array($produtoCarrinho) && !empty($produtoCarrinho['imagens']) && is_array($produtoCarrinho['imagens'])) {
                                                $first = $produtoCarrinho['imagens'][0] ?? null;
                                                if (is_string($first) && preg_match('#^https?://#i', $first)) {
                                                    $fotoUrl = $first;
                                                }
                                            }
                                        } catch (\Exception $e) {
                                        }
                                    }

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

                                    $fotoArquivo = $fotoPrincipal['nome_arquivo'] ?? ($fotosProduto[0]['nome_arquivo'] ?? null);
                                    if (empty($fotoUrl) && !empty($fotoArquivo) && is_string($fotoArquivo)) {
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
                                    <h6 class="mb-1">
                                        <?= htmlspecialchars($item['nome']) ?>
                                        <?php if (!empty($item['is_free_offer'])): ?>
                                            <span class="badge bg-success ms-1"><i class="fas fa-gift me-1"></i>Produto Gratuito</span>
                                        <?php endif; ?>
                                        <?php if (!empty($isClubeAtivo)): ?>
                                            <span class="badge" style="background:#0b1f3a; margin-left: 6px;"><i class="fas fa-crown me-1"></i><?= __('cart.club_active', 'Clube Ativo') ?></span>
                                        <?php endif; ?>
                                    </h6>
                                    <?php if (!empty($item['item_changed'])): ?>
                                        <div class="alert alert-warning py-2 px-2 mb-2">
                                            <div class="small">
                                                <strong>Produto alterado:</strong>
                                                <?php
                                                $changed = $item['changed_fields'] ?? [];
                                                if (!is_array($changed)) $changed = [];
                                                $labels = [];
                                                foreach ($changed as $f) {
                                                    if ($f === 'price') $labels[] = 'preço';
                                                    if ($f === 'weight') $labels[] = 'peso';
                                                }
                                                ?>
                                                <?= !empty($labels) ? htmlspecialchars(implode(', ', $labels), ENT_QUOTES, 'UTF-8') : 'informações' ?>.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['variacao_descricao'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars((string) $item['variacao_descricao'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($item['peso_unit'])): ?>
                                        <div class="small text-muted">
                                            <?= __('cart.weight', 'Peso') ?>: <?= number_format((float) ($item['peso_unit'] ?? 0), 3, ',', '.') ?> kg (x<?= (int) ($item['quantidade'] ?? 0) ?>)
                                            = <?= number_format((float) ($item['peso_item'] ?? 0), 3, ',', '.') ?> kg
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['ativo'])): ?>
                                        <div class="small text-success"><?= __('cart.active', 'Ativo') ?></div>
                                    <?php else: ?>
                                        <div class="small text-danger"><?= __('cart.inactive', 'Desativado') ?></div>
                                        <div class="small text-muted fst-italic"><?= __('cart.select_to_activate', 'Selecione o item para ativar e prossiga.') ?></div>
                                    <?php endif; ?>
                                    <div class="input-group input-group-sm" style="max-width: 240px;">
                                        <?php if (empty($item['is_free_offer'])): ?>
                                        <button class="btn btn-outline-secondary" <?= $isAtivo ? '' : 'disabled' ?> onclick='atualizarQuantidade(<?= htmlspecialchars(json_encode((string) $itemKeyStable), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode((string) $item['produto_id']), ENT_QUOTES, "UTF-8") ?>, <?= max(1, $item['quantidade'] - 1) ?>)'>
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="form-control text-center" 
                                               value="<?= $item['quantidade'] ?>" 
                                               min="1" 
                                               max="999"
                                               <?= $isAtivo ? '' : 'disabled' ?>
                                               id="quantidade-<?= htmlspecialchars((string) $index) ?>"
                                               onchange='atualizarQuantidade(<?= htmlspecialchars(json_encode((string) $itemKeyStable), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode((string) $item['produto_id']), ENT_QUOTES, "UTF-8") ?>, this.value)'>
                                        <button class="btn btn-outline-secondary" <?= $isAtivo ? '' : 'disabled' ?> onclick='atualizarQuantidade(<?= htmlspecialchars(json_encode((string) $itemKeyStable), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode((string) $item['produto_id']), ENT_QUOTES, "UTF-8") ?>, <?= $item['quantidade'] + 1 ?>)'>
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <?php else: ?>
                                        <span class="badge bg-success"><i class="fas fa-gift me-1"></i>Qtd: 1</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= __('cart.id', 'ID') ?>: <?= $item['produto_id'] ?></small>
                                </div>
                                <div class="col-8 col-md-3 text-start text-md-end mt-2 mt-md-0">
                                    <?php if (!empty($item['is_free_offer'])): ?>
                                        <?php $freeOrigPrice = (float) ($item['free_offer_original_price'] ?? 0); ?>
                                        <?php if ($freeOrigPrice > 0): ?>
                                            <div class="text-muted small text-decoration-line-through">$ <?= number_format($freeOrigPrice, 2, ',', '.') ?></div>
                                        <?php endif; ?>
                                        <div class="fw-bold text-success" style="font-size:1.1em;">GRÁTIS</div>
                                    <?php else: ?>
                                    <div class="fw-bold">
                                        <span class="cart-item-subtotal" data-original-price="<?= $item['subtotal'] ?>">
                                            <?= number_format($item['subtotal'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?= __('cart.unit', 'unit') ?>: 
                                        <span class="cart-item-unit" data-original-price="<?= $item['price'] ?? $item['preco_unitario'] ?>">
                                            <?= number_format($item['price'] ?? $item['preco_unitario'], 2, ',', '.') ?>
                                        </span>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-4 col-md-1 text-end mt-2 mt-md-0">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button class="btn btn-sm btn-outline-danger" onclick='removerItem(<?= htmlspecialchars(json_encode((string) $itemKeyStable), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode((string) $item['produto_id']), ENT_QUOTES, "UTF-8") ?>)'>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between">
                        <a href="/produtos" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> <?= __('cart.continue_shopping', 'Continuar Comprando') ?>
                        </a>
                        <button class="btn btn-outline-danger" onclick="limparCarrinho()">
                            <i class="fas fa-trash"></i> <?= __('cart.clear_cart', 'Limpar Carrinho') ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <?php if (empty($carrinho)): ?>
                <!-- Resumo do Pedido (Carrinho vazio) -->
                <div class="card shadow-sm border-0">
                    <div class="card-header">
                        <h5 class="mb-0"><?= __('cart.order_summary', 'Resumo do Pedido') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span><?= __('cart.subtotal_items', 'Subtotal ({count} itens)', ['count' => 0]) ?></span>
                            <span class="cart-currency subtotal-value" data-original-value="0">0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?= __('cart.service_fee_kg', 'Taxa de Serviço ({kg} kg)', ['kg' => 0]) ?></span>
                            <span class="cart-currency taxa-servico-value" data-original-value="0">0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?= __('cart.taxes', 'Impostos') ?></span>
                            <span class="cart-currency impostos-value" data-original-value="0">0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?= __('cart.shipping_kg', 'Frete ({kg} kg)', ['kg' => 0]) ?></span>
                            <span class="cart-currency frete-value" data-original-value="0"><?= __('cart.free_shipping', 'Frete grátis') ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <h5><?= __('cart.total', 'Total') ?></h5>
                            <h5 id="total-valor" class="cart-currency total-value" data-original-value="0">0,00</h5>
                        </div>
                        <div class="d-grid">
                            <a href="/checkout" class="btn btn-primary btn-lg disabled" aria-disabled="true" tabindex="-1">
                                <i class="fas fa-lock"></i> <?= __('cart.checkout', 'Finalizar Compra') ?>
                            </a>
                        </div>
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> <?= __('cart.add_items_to_continue', 'Adicione itens ao carrinho para continuar.') ?>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-3" style="display:none;">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-info-circle"></i> <?= __('cart.important_info', 'Informações Importantes') ?></h6>
                        <ul class="small text-muted mb-0">
                            <!-- <li><?= __('cart.info.delivery_time', 'Prazo de entrega: 15-30 dias') ?></li> -->
                            <li><?= __('cart.info.taxes_included', 'Impostos inclusos no valor final') ?></li>
                            <li><?= __('cart.info.service_fee', 'Taxa de serviço: US$ 39/kg (arredondado para cima)') ?></li>
                            <li><?= __('cart.info.shipping_calc', 'Frete calculado pelo peso total arredondado') ?></li>
                            <li><?= __('cart.info.insurance', 'Seguro contra perda/dano') ?></li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <!-- Resumo do Pedido -->
                <div class="card shadow-sm border-0">
                    <div class="card-header">
                        <h5 class="mb-0"><?= __('cart.order_summary', 'Resumo do Pedido') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($excede_peso)): ?>
                            <div class="alert alert-warning">
                                <?= __('cart.max_weight_disable_items', 'Peso máximo é {kg}kg. Desative itens para continuar.', ['kg' => number_format((float) ($peso_max_kg ?? 30), 0, ',', '.')]) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($entrega_fora_br)): ?>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-globe-americas me-1"></i>
                                Entrega para fora do Brasil não inclui impostos brasileiros. A tributação local é responsabilidade do cliente.
                            </div>
                        <?php endif; ?>
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span><?= __('cart.subtotal_items', 'Subtotal ({count} itens)', ['count' => (int) $total_itens]) ?></span>
                            <span class="cart-currency subtotal-value" data-original-value="<?= $subtotal ?>"><?= number_format($subtotal, 2, ',', '.') ?></span>
                        </div>

                        <?php if (!empty($desconto_clube) || !empty($cashback_clube_estimado) || !empty($peso_clube_total) || !empty($subtotal_clube)): ?>
                            <div class="mt-2 mb-2 p-2" style="background: rgba(11,31,58,0.04); border: 1px solid rgba(11,31,58,0.08); border-radius: 12px;">
                                <div class="fw-semibold mb-1" style="color:#0b1f3a;"><?= __('cart.club', 'Clube Brasiliana') ?></div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted"><?= __('cart.club_weight', 'Peso Clube') ?></span>
                                    <span><?= number_format((float) ($peso_clube_total ?? 0), 3, ',', '.') ?> kg</span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted"><?= __('cart.club_subtotal', 'Subtotal Clube') ?></span>
                                    <span class="cart-currency" data-original-value="<?= (float) ($subtotal_clube ?? 0) ?>"><?= number_format((float) ($subtotal_clube ?? 0), 2, ',', '.') ?></span>
                                </div>
                                <?php if (!empty($desconto_clube)): ?>
                                    <div class="d-flex justify-content-between small">
                                        <span class="text-muted"><?= __('cart.club_discount', 'Desconto Clube') ?></span>
                                        <span class="cart-currency" data-original-value="<?= (float) ($desconto_clube ?? 0) ?>">-<?= number_format((float) ($desconto_clube ?? 0), 2, ',', '.') ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($cashback_clube_estimado)): ?>
                                    <div class="d-flex justify-content-between small">
                                        <span class="text-muted"><?= __('cart.club_cashback_est', 'Cashback estimado') ?></span>
                                        <span class="cart-currency" data-original-value="<?= (float) ($cashback_clube_estimado ?? 0) ?>"><?= number_format((float) ($cashback_clube_estimado ?? 0), 2, ',', '.') ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= __('cart.service_fee_kg', 'Taxa de Serviço ({kg} kg)', ['kg' => number_format(ceil($peso_total), 0, ',', '.')]) ?></span>
                        <span class="cart-currency taxa-servico-value" data-original-value="<?= $taxa_servico ?>"><?= number_format($taxa_servico, 2, ',', '.') ?></span>
                    </div>
                    
                    <?php if (empty($entrega_fora_br)): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= __('cart.taxes_brazil', 'Impostos do Brasil') ?></span>
                        <span class="cart-currency impostos-value" data-original-value="<?= $impostos ?>"><?= number_format($impostos, 2, ',', '.') ?></span>
                    </div>
                    <?php if (!empty($free_offer_info)): ?>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Imposto do brinde (não cobrado)</span>
                            <span class="text-decoration-line-through text-muted">$ <?= number_format($free_offer_info['imposto_teorico'], 2, ',', '.') ?></span>
                        </div>
                        <div class="small text-success mb-2">
                            <i class="fas fa-gift me-1"></i> Você não paga o imposto do brinde — a Braziliana paga por você!
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (($imposto_local ?? 0) > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Imposto local (<?= number_format($imposto_local_percent ?? 0, 0) ?>%)</span>
                        <span class="cart-currency imposto-local-value" data-original-value="<?= $imposto_local ?>"><?= number_format($imposto_local, 2, ',', '.') ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= __('cart.shipping_kg', 'Frete ({kg} kg)', ['kg' => number_format(ceil($peso_total), 0, ',', '.')]) ?></span>
                        <span class="cart-currency frete-value" data-original-value="<?= (float) ($frete ?? 0) ?>"><?= (((float) ($frete ?? 0)) <= 0 ? __('cart.free_shipping', 'Frete grátis') : number_format((float) ($frete ?? 0), 2, ',', '.')) ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <h5><?= __('cart.total', 'Total') ?></h5>
                        <h5 id="total-valor" class="cart-currency total-value" data-original-value="<?= $total ?>"><?= number_format($total, 2, ',', '.') ?></h5>
                    </div>
                    
                    <div class="d-grid">
                        <a id="btnCheckoutFromCart" href="/carrinho/checkout" class="btn btn-primary btn-lg" style="position: relative; z-index: 5;" onclick="try{window.location.href='/carrinho/checkout';}catch(e){} return false;">
                            <i class="fas fa-lock"></i> <?= __('cart.checkout', 'Finalizar Compra') ?>
                        </a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt"></i> <?= __('cart.secure_payment', 'Pagamento 100% Seguro') ?>
                        </small>
                    </div>
                </div>
            </div>
            
                <!-- Informações Importantes -->
                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-info-circle"></i> <?= __('cart.important_info', 'Informações Importantes') ?></h6>
                        <ul class="small text-muted mb-0">
                            <!-- <li><?= __('cart.info.delivery_time', 'Prazo de entrega: 15-30 dias') ?></li> -->
                            <li><?= __('cart.info.taxes_included', 'Impostos inclusos no valor final') ?></li>
                            <li><?= __('cart.info.service_fee', 'Taxa de serviço: US$ 39/kg (arredondado para cima)') ?></li>
                            <li><?= __('cart.info.shipping_calc', 'Frete calculado pelo peso total arredondado') ?></li>
                            <li><?= __('cart.info.insurance', 'Seguro contra perda/dano') ?></li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const I18N = {
    err_update_cart: <?= json_encode(__('cart.err_update_cart', 'Erro ao atualizar carrinho'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_remove_item: <?= json_encode(__('cart.confirm_remove_item', 'Deseja remover este item do carrinho?'), JSON_UNESCAPED_UNICODE) ?>,
    err_remove_item: <?= json_encode(__('cart.err_remove_item', 'Erro ao remover item'), JSON_UNESCAPED_UNICODE) ?>,
    err_update_item: <?= json_encode(__('cart.err_update_item', 'Erro ao atualizar item'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_clear_cart: <?= json_encode(__('cart.confirm_clear_cart', 'Deseja limpar o carrinho?'), JSON_UNESCAPED_UNICODE) ?>,
    err_clear_cart: <?= json_encode(__('cart.err_clear_cart', 'Erro ao limpar carrinho'), JSON_UNESCAPED_UNICODE) ?>
};

function atualizarQuantidade(itemKey, produtoId, quantidade) {
    if (quantidade < 1) return;
    
    $.ajax({
        url: '/carrinho/atualizar',
        method: 'POST',
        data: {
            id: itemKey,
            produto_id: produtoId,
            quantidade: quantidade
        },
        success: function(response) {
            if (response.success) {
                location.reload(); // Recarregar para atualizar valores
            } else {
                alert(response.error);
            }
        },
        error: function(jqXHR) {
            const resp = jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null;
            if (resp && resp.error) {
                alert(resp.error);
                return;
            }
            try {
                const txt = (jqXHR && jqXHR.responseText) ? jqXHR.responseText : '';
                const parsed = txt ? JSON.parse(txt) : null;
                if (parsed && parsed.error) {
                    alert(parsed.error);
                    return;
                }
            } catch (e) {
            }
            alert(I18N.err_update_cart);
        }
    });
}

function removerItem(itemKey, produtoId) {
    if (confirm(I18N.confirm_remove_item)) {
        $.ajax({
            url: '/carrinho/remover',
            method: 'POST',
            data: {
                id: itemKey,
                produto_id: produtoId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.error);
                }
            },
            error: function() {
                alert(I18N.err_remove_item);
            }
        });
    }

}

function toggleAtivo(itemKey, ativo) {
    $.ajax({
        url: '/carrinho/toggle-ativo',
        method: 'POST',
        data: {
            id: itemKey,
            ativo: ativo
        },
        success: function(response) {
            if (response && response.success) {
                location.reload();
            } else {
                alert((response && response.error) ? response.error : I18N.err_update_item);
            }
        },
        error: function() {
            alert(I18N.err_update_item);
        }
    });
}

function limparCarrinho() {
    if (!confirm(I18N.confirm_clear_cart)) return;

    $.ajax({
        url: '/carrinho/limpar',
        method: 'POST',
        data: {},
        success: function(response) {
            if (response && response.success) {
                location.reload();
            } else {
                alert((response && response.error) ? response.error : I18N.err_clear_cart);
            }
        },
        error: function() {
            alert(I18N.err_clear_cart);
        }
    });
}
</script>

<!-- Modal Oferta Gratuita -->
<style>
@keyframes ofertaPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}
@keyframes ofertaShine {
    0% { background-position: -200% center; }
    100% { background-position: 200% center; }
}
#modalOfertaGratuita .modal-content {
    border-radius: 20px;
    overflow: hidden;
    border: 3px solid #28a745;
}
#modalOfertaGratuita .oferta-header {
    background: linear-gradient(135deg, #1a7a3a 0%, #28a745 40%, #34d058 100%);
    padding: 24px 20px 18px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
#modalOfertaGratuita .oferta-header::before {
    content: '\1F340 \2728 \1F381 \2728 \1F340';
    position: absolute;
    top: 6px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 14px;
    letter-spacing: 8px;
    opacity: 0.5;
}
#modalOfertaGratuita .oferta-icon {
    font-size: 48px;
    animation: ofertaPulse 1.5s ease-in-out infinite;
    display: inline-block;
    margin-bottom: 6px;
}
#modalOfertaGratuita .oferta-titulo {
    font-size: 1.4rem;
    font-weight: 800;
    color: #fff;
    margin: 0;
    text-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
#modalOfertaGratuita .oferta-subtitulo {
    color: rgba(255,255,255,0.85);
    font-size: 0.9rem;
    margin-top: 4px;
}
#modalOfertaGratuita .oferta-preco-tag {
    display: inline-block;
    background: linear-gradient(90deg, #ffd700, #ffec80, #ffd700);
    background-size: 200% auto;
    animation: ofertaShine 2s linear infinite;
    color: #1a5c2a;
    font-weight: 900;
    font-size: 1.3rem;
    padding: 6px 22px;
    border-radius: 30px;
    box-shadow: 0 4px 15px rgba(255,215,0,0.4);
}
#modalOfertaGratuita .oferta-aviso-unico {
    background: #fff8e1;
    border: 1px dashed #f9a825;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 0.8rem;
    color: #7b6b1a;
}
#modalOfertaGratuita .btn-aceitar-oferta {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    font-weight: 700;
    font-size: 1.1rem;
    padding: 12px 24px;
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(40,167,69,0.35);
    transition: transform 0.15s, box-shadow 0.15s;
}
#modalOfertaGratuita .btn-aceitar-oferta:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(40,167,69,0.45);
}
</style>

<div class="modal fade" id="modalOfertaGratuita" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="oferta-header">
                <div class="oferta-icon">&#x1F340;</div>
                <h5 class="oferta-titulo">Dia de sorte! Um presente especial</h5>
                <div class="oferta-subtitulo">Selecionamos um brinde exclusivo para o seu pedido</div>
            </div>
            <div class="modal-body text-center py-4 px-4">
                <div id="ofertaGratuitaConteudo">
                    <img id="ofertaFoto" src="" alt="Produto gratuito" class="img-fluid rounded mb-3" style="max-height:180px; object-fit:contain; display:none; border: 2px solid #e9ecef; border-radius: 12px !important;">

                    <h5 id="ofertaNome" class="mb-2 fw-bold"></h5>

                    <div class="mb-3">
                        <div class="text-muted small mb-1">Valor original: <span class="text-decoration-line-through" id="ofertaPrecoOriginal"></span></div>
                        <div class="oferta-preco-tag">GRÁTIS</div>
                    </div>

                    <div class="text-muted small mb-3" style="line-height: 1.5;">
                        Você paga apenas a taxa de serviço pelo peso.<br>
                        <span class="text-success fw-semibold">Sem imposto adicional — a Braziliana paga por você! &#x1F389;</span>
                    </div>

                    <div class="oferta-aviso-unico mb-3">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        <strong>Atenção:</strong> esta oferta é única e exclusiva. Após sua escolha (aceitar ou recusar), ela não aparecerá novamente.
                    </div>

                    <div class="d-grid gap-2">
                        <button id="btnAceitarOferta" class="btn btn-aceitar-oferta btn-lg text-white" onclick="aceitarOfertaGratuita()">
                            &#x1F340; Quero meu brinde gratuito!
                        </button>
                        <button id="btnRecusarOferta" class="btn btn-outline-secondary btn-sm" onclick="recusarOfertaGratuita()" style="border-radius: 10px;">
                            Não, obrigado — continuar sem o brinde
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // Verificar oferta gratuita via AJAX
    var url = '/oferta-gratuita/verificar';
    // Propagar modo teste se presente na URL do carrinho
    if (window.location.search.indexOf('test_oferta=1') !== -1) {
        url += '?test_oferta=1';
    }
    fetch(url, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.show) return;
            const p = data.produto;
            document.getElementById('ofertaNome').textContent = p.nome || '';
            document.getElementById('ofertaPrecoOriginal').textContent = '$ ' + parseFloat(p.preco_original || 0).toFixed(2);
            document.getElementById('ofertaTaxaServico').textContent = '$ ' + parseFloat(p.taxa_servico || 0).toFixed(2);
            const fotoEl = document.getElementById('ofertaFoto');
            if (p.foto) {
                fotoEl.src = p.foto;
                fotoEl.style.display = 'block';
            }
            // Mostrar modal após 1.5s
            setTimeout(function() {
                try {
                    var modal = new bootstrap.Modal(document.getElementById('modalOfertaGratuita'));
                    modal.show();
                } catch(e) {}
            }, 1500);
        })
        .catch(function() {});
})();

function aceitarOfertaGratuita() {
    var btn = document.getElementById('btnAceitarOferta');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adicionando ao carrinho...';

    fetch('/oferta-gratuita/aceitar', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                btn.innerHTML = '\u2705 Brinde adicionado!';
                setTimeout(function() { location.reload(); }, 800);
            } else {
                alert(data.error || 'Erro ao aceitar oferta');
                btn.disabled = false;
                btn.innerHTML = '\uD83C\uDF40 Quero meu brinde gratuito!';
            }
        })
        .catch(function() {
            alert('Erro de conexão');
            btn.disabled = false;
            btn.innerHTML = '\uD83C\uDF40 Quero meu brinde gratuito!';
        });
}

function recusarOfertaGratuita() {
    fetch('/oferta-gratuita/recusar', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function() {
            try { bootstrap.Modal.getInstance(document.getElementById('modalOfertaGratuita')).hide(); } catch(e) {}
        })
        .catch(function() {
            try { bootstrap.Modal.getInstance(document.getElementById('modalOfertaGratuita')).hide(); } catch(e) {}
        });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
