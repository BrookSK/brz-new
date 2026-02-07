<?php ob_start(); ?>
<?php use App\Core\Url; ?>
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <?php if (empty($carrinho)): ?>
                <?php include __DIR__ . '/vazio.php'; ?>
            <?php else: ?>
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
                                        <?php if (!empty($isClubeAtivo)): ?>
                                            <span class="badge" style="background:#0b1f3a; margin-left: 6px;"><i class="fas fa-crown me-1"></i>Clube Ativo</span>
                                        <?php endif; ?>
                                    </h6>
                                    <?php if (!empty($item['variacao_descricao'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars((string) $item['variacao_descricao'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($item['peso_unit'])): ?>
                                        <div class="small text-muted">
                                            Peso: <?= number_format((float) ($item['peso_unit'] ?? 0), 3, ',', '.') ?> kg (x<?= (int) ($item['quantidade'] ?? 0) ?>)
                                            = <?= number_format((float) ($item['peso_item'] ?? 0), 3, ',', '.') ?> kg
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['ativo'])): ?>
                                        <div class="small text-success">Ativo</div>
                                    <?php else: ?>
                                        <div class="small text-danger">Desativado</div>
                                        <div class="small text-muted fst-italic">Selecione o item para ativar e prossiga.</div>
                                    <?php endif; ?>
                                    <div class="input-group input-group-sm" style="max-width: 240px;">
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
                <div class="card shadow-sm border-0">
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
                            <span class="cart-currency frete-value" data-original-value="0">Frete grátis</span>
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
                <div class="card shadow-sm border-0 mt-3">
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
                <div class="card shadow-sm border-0">
                    <div class="card-header">
                        <h5 class="mb-0">Resumo do Pedido</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($excede_peso)): ?>
                            <div class="alert alert-warning">
                                Peso máximo é <?= number_format((float) ($peso_max_kg ?? 30), 0, ',', '.') ?>kg. Desative itens para continuar.
                            </div>
                        <?php endif; ?>
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal (<?= $total_itens ?> itens)</span>
                            <span class="cart-currency subtotal-value" data-original-value="<?= $subtotal ?>"><?= number_format($subtotal, 2, ',', '.') ?></span>
                        </div>

                        <?php if (!empty($desconto_clube) || !empty($cashback_clube_estimado) || !empty($peso_clube_total) || !empty($subtotal_clube)): ?>
                            <div class="mt-2 mb-2 p-2" style="background: rgba(11,31,58,0.04); border: 1px solid rgba(11,31,58,0.08); border-radius: 12px;">
                                <div class="fw-semibold mb-1" style="color:#0b1f3a;">Clube Brasiliana</div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Peso Clube</span>
                                    <span><?= number_format((float) ($peso_clube_total ?? 0), 3, ',', '.') ?> kg</span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Subtotal Clube</span>
                                    <span class="cart-currency" data-original-value="<?= (float) ($subtotal_clube ?? 0) ?>"><?= number_format((float) ($subtotal_clube ?? 0), 2, ',', '.') ?></span>
                                </div>
                                <?php if (!empty($desconto_clube)): ?>
                                    <div class="d-flex justify-content-between small">
                                        <span class="text-muted">Desconto Clube</span>
                                        <span class="cart-currency" data-original-value="<?= (float) ($desconto_clube ?? 0) ?>">-<?= number_format((float) ($desconto_clube ?? 0), 2, ',', '.') ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($cashback_clube_estimado)): ?>
                                    <div class="d-flex justify-content-between small">
                                        <span class="text-muted">Cashback estimado</span>
                                        <span class="cart-currency" data-original-value="<?= (float) ($cashback_clube_estimado ?? 0) ?>"><?= number_format((float) ($cashback_clube_estimado ?? 0), 2, ',', '.') ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    
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
                        <span class="cart-currency frete-value" data-original-value="<?= (float) ($frete ?? 0) ?>"><?= (((float) ($frete ?? 0)) <= 0 ? 'Frete grátis' : number_format((float) ($frete ?? 0), 2, ',', '.')) ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <h5>Total</h5>
                        <h5 id="total-valor" class="cart-currency total-value" data-original-value="<?= $total ?>"><?= number_format($total, 2, ',', '.') ?></h5>
                    </div>
                    
                    <div class="d-grid">
                        <a href="/carrinho/checkout" class="btn btn-primary btn-lg <?= !empty($excede_peso) ? 'disabled' : '' ?>" <?= !empty($excede_peso) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
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
                <div class="card shadow-sm border-0 mt-3">
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
        error: function() {
            alert('Erro ao atualizar carrinho');
        }
    });
}

function removerItem(itemKey, produtoId) {
    if (confirm('Deseja remover este item do carrinho?')) {
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
                alert('Erro ao remover item');
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
                alert((response && response.error) ? response.error : 'Erro ao atualizar item');
            }
        },
        error: function() {
            alert('Erro ao atualizar item');
        }
    });
}

function limparCarrinho() {
    if (!confirm('Deseja limpar o carrinho?')) return;

    $.ajax({
        url: '/carrinho/limpar',
        method: 'POST',
        data: {},
        success: function(response) {
            if (response && response.success) {
                location.reload();
            } else {
                alert((response && response.error) ? response.error : 'Erro ao limpar carrinho');
            }
        },
        error: function() {
            alert('Erro ao limpar carrinho');
        }
    });
}
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
