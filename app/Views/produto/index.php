<?php ob_start(); ?>
<div class="row mb-4">
    <div class="col-lg-12">
        <h2><i class="fas fa-box"></i> Produtos Disponíveis</h2>
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
    <div class="col-lg-4 text-end">
        <a href="/cobranca" class="btn btn-success">
            <i class="fas fa-shopping-cart"></i> Finalizar Compra
        </a>
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
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($produto['nome']) ?></h5>
                        <p class="text-muted small"><?= htmlspecialchars($produto['categoria']) ?></p>
                        <p class="card-text"><?= htmlspecialchars(substr($produto['descricao'], 0, 100)) ?>...</p>
                        <ul class="list-unstyled">
                            <li><strong>Preço:</strong> R$ <?= number_format($produto['preco'], 2, ',', '.') ?></li>
                            <li><strong>Peso:</strong> <?= number_format($produto['peso'], 3, ',', '.') ?> kg</li>
                            <li><strong>Estoque:</strong> <?= $produto['estoque'] ?> unidades</li>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <div class="input-group">
                            <input type="number" class="form-control quantidade-input" value="1" min="1" max="<?= $produto['estoque'] ?>" data-produto-id="<?= $produto['id'] ?>">
                            <button class="btn btn-primary btn-adicionar" data-produto-id="<?= $produto['id'] ?>" data-produto-nome="<?= htmlspecialchars($produto['nome']) ?>" data-produto-preco="<?= $produto['preco'] ?>">
                                <i class="fas fa-cart-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="alert-container"></div>

<script>
$(document).ready(function() {
    $('.btn-adicionar').click(function() {
        var btn = $(this);
        var produtoId = btn.data('produto-id');
        var quantidade = btn.closest('.input-group').find('.quantidade-input').val();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adicionando...');
        
        $.post('/produtos/carrinho', {
            id: produtoId,
            quantidade: quantidade
        }, function(response) {
            if (response.success) {
                showAlert('success', response.message);
                updateCartBadge(response.total_itens);
            } else {
                showAlert('danger', response.error);
            }
        }).fail(function() {
            showAlert('danger', 'Erro ao adicionar produto ao carrinho');
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-cart-plus"></i> Adicionar');
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
