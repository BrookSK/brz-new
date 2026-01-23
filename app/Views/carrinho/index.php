<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <h2 class="mb-4"><i class="fas fa-shopping-cart"></i> Meu Carrinho</h2>
            
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
                                    <img src="/uploads/produtos/<?= $item['foto_principal'] ?? 'placeholder.jpg' ?>" 
                                         alt="<?= htmlspecialchars($item['nome']) ?>"
                                         class="img-fluid rounded">
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1"><?= htmlspecialchars($item['nome']) ?></h6>
                                    <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                    <div class="mt-2">
                                        <span class="badge bg-primary"><?= $item['moeda'] ?> <?= number_format($item['valor'], 2, ',', '.') ?></span>
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
                                        <?= $item['moeda'] ?> <?= number_format($item['subtotal'], 2, ',', '.') ?>
                                    </div>
                                    <small class="text-muted">unit: <?= $item['moeda'] ?> <?= number_format($item['valor'], 2, ',', '.') ?></small>
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
                    <div class="mb-3">
                        <label for="cep" class="form-label">CEP para Cálculo de Frete</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="cep" placeholder="00000-000">
                            <button class="btn btn-outline-secondary" onclick="calcularFrete()">
                                Calcular
                            </button>
                        </div>
                        <div id="frete-opcoes" class="mt-2"></div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal (<?= $total_itens ?> itens)</span>
                        <span><?= $carrinho[0]['moeda'] ?> <?= number_format($subtotal, 2, ',', '.') ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Taxa de Serviço (<?= number_format(ceil($peso_total), 0, ',', '.') ?> kg)</span>
                        <span><?= $carrinho[0]['moeda'] ?> <?= number_format($taxa_servico, 2, ',', '.') ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Impostos (80%)</span>
                        <span><?= $carrinho[0]['moeda'] ?> <?= number_format($impostos, 2, ',', '.') ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Frete (<?= number_format(ceil($peso_total), 0, ',', '.') ?> kg)</span>
                        <span><?= $carrinho[0]['moeda'] ?> <?= number_format($frete, 2, ',', '.') ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <h5>Total</h5>
                        <h5 id="total-valor"><?= $carrinho[0]['moeda'] ?> <?= number_format($total, 2, ',', '.') ?></h5>
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
let freteSelecionado = 0;

function atualizarQuantidade(produtoId, quantidade) {
    if (quantidade < 1) return;
    
    $.ajax({
        url: '/api/carrinho/atualizar',
        method: 'POST',
        data: {
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

function removerItem(produtoId) {
    if (confirm('Deseja remover este item do carrinho?')) {
        $.ajax({
            url: '/api/carrinho/remover',
            method: 'POST',
            data: {
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

function limparCarrinho() {
    if (confirm('Deseja limpar todo o carrinho?')) {
        $.ajax({
            url: '/api/carrinho/limpar',
            method: 'POST',
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    }
}

function calcularFrete() {
    const cep = $('#cep').val().replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('CEP inválido');
        return;
    }
    
    $.ajax({
        url: '/api/frete/calcular',
        method: 'GET',
        data: {
            cep: cep,
            peso: <?= $peso_total ?>,
            valor: <?= $subtotal ?>
        },
        success: function(response) {
            if (response.success) {
                let html = '<div class="mt-2">';
                html += '<h6>Opções de Frete:</h6>';
                
                response.frete.forEach(function(opcao) {
                    html += `
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="frete" 
                                   id="frete-${opcao.nome}" value="${opcao.valor}" 
                                   onchange="selecionarFrete(${opcao.valor})">
                            <label class="form-check-label" for="frete-${opcao.nome}">
                                <strong>${opcao.nome}</strong> - R$ ${opcao.valor.toFixed(2)} 
                                (${opcao.prazo} dias úteis)
                            </label>
                        </div>
                    `;
                });
                
                html += '</div>';
                $('#frete-opcoes').html(html);
            } else {
                alert(response.error);
            }
        },
        error: function() {
            alert('Erro ao calcular frete');
        }
    });
}

function selecionarFrete(valor) {
    freteSelecionado = valor;
    $('#frete-display').show();
    $('#frete-valor').text('R$ ' + valor.toFixed(2));
    
    const totalAtual = <?= $total ?>;
    const novoTotal = totalAtual + valor;
    $('#total-valor').text('<?= $carrinho[0]["moeda"] ?> ' + novoTotal.toFixed(2).replace('.', ','));
}
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
