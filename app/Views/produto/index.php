<?php ob_start(); ?>
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-lg-8">
            <h2><i class="fas fa-box"></i> Produtos Disponíveis</h2>
        </div>
        <div class="col-lg-4 text-end">
            <a href="/carrinho" class="btn btn-success">
                <i class="fas fa-shopping-cart"></i> Ver Carrinho
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-4">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Buscar produtos..." value="<?= htmlspecialchars($search ?? '') ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        <div class="col-lg-4">
            <form method="GET">
                <select name="categoria" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($categoriaSelecionada ?? '') === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <div class="row">
        <?php if (empty($produtos)): ?>
            <div class="col-lg-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Nenhum produto encontrado.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($produtos as $produto): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100 product-card">
                    <a href="/produto/detalhes/<?= $produto['id'] ?>" class="text-decoration-none">
                        <div class="product-image-container">
                            <?php if ($produto['foto_principal']): ?>
                                <img src="/uploads/produtos/<?= $produto['foto_principal'] ?>" 
                                     alt="<?= htmlspecialchars($produto['nome']) ?>"
                                     class="card-img-top product-image">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x300/6c757d/ffffff?text=Sem+Foto" 
                                     alt="<?= htmlspecialchars($produto['nome']) ?>"
                                     class="card-img-top product-image">
                            <?php endif; ?>
                             
                            <!-- Badge de estoque -->
                            <?php if ($produto['estoque'] <= 5): ?>
                                <span class="position-absolute top-0 end-0 m-2 badge bg-warning">
                                    <?= $produto['estoque'] ?> unidades
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title text-dark"><?= htmlspecialchars($produto['nome']) ?></h5>
                            <p class="text-muted small"><?= htmlspecialchars($produto['categoria']) ?></p>
                            <p class="card-text text-truncate"><?= htmlspecialchars(substr($produto['descricao'], 0, 80)) ?>...</p>
                             
                            <div class="price-section mb-3">
                                <div class="current-price">
                                    <span class="currency"><?= $produto['moeda'] ?></span>
                                    <span class="amount"><?= number_format($produto['preco'], 2, ',', '.') ?></span>
                                </div>
                                <?php if ($produto['moeda'] === 'USD'): ?>
                                <div class="price-conversion text-muted">
                                    <small>≈ R$ <?= number_format($produto['preco'] * 5.5, 2, ',', '.') ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <div class="card-footer">
                        <div class="input-group">
                            <input type="number" class="form-control quantidade-input" value="1" min="1" max="<?= $produto['estoque'] ?>" data-produto-id="<?= $produto['id'] ?>">
                            <button class="btn btn-primary btn-adicionar" data-produto-id="<?= $produto['id'] ?>" data-produto-nome="<?= htmlspecialchars($produto['nome']) ?>" data-produto-preco="<?= $produto['preco'] ?>" <?= $produto['estoque'] > 0 ? '' : 'disabled' ?>>
                                <?php if ($produto['estoque'] > 0): ?>
                                    <i class="fas fa-cart-plus"></i> Adicionar
                                <?php else: ?>
                                    <i class="fas fa-times"></i> Indisponível
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="alert-container"></div>

<style>
.product-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.product-image-container {
    height: 200px;
    overflow: hidden;
    position: relative;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-image {
    transform: scale(1.05);
}

.current-price {
    font-size: 1.25rem;
    font-weight: bold;
    color: #007bff;
}

.currency {
    font-size: 0.9rem;
    vertical-align: super;
}

.price-conversion {
    font-size: 0.8rem;
}
</style>

<script>
$(document).ready(function() {
    $('.btn-adicionar').click(function() {
        var btn = $(this);
        var produtoId = btn.data('produto-id');
        var quantidade = btn.closest('.input-group').find('.quantidade-input').val();
        
        if (btn.prop('disabled')) {
            return;
        }
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adicionando...');
        
        $.ajax({
            url: '/api/carrinho/adicionar',
            method: 'POST',
            data: {
                produto_id: produtoId,
                quantidade: quantidade
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    updateCartBadge(response.total_itens);
                } else {
                    showAlert('danger', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.log('Erro:', xhr.responseText);
                showAlert('danger', 'Erro ao adicionar produto ao carrinho');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-cart-plus"></i> Adicionar');
            }
        });
    });
    
    function showAlert(type, message) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                       message +
                       '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                       '</div>';
        $('#alert-container').html(alertHtml);
        
        setTimeout(function() {
            $('#alert-container .alert').alert('close');
        }, 5000);
    }
    
    function updateCartBadge(totalItens) {
        var badge = $('.navbar-nav .badge');
        if (totalItens > 0) {
            badge.text(totalItens).show();
        } else {
            badge.hide();
        }
    }
});
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
