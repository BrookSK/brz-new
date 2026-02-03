<?php ob_start(); ?>
<div class="container py-4">

    <!-- Search and Filters -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <form method="GET" class="d-flex">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" 
                           placeholder="Buscar produtos..." value="<?= htmlspecialchars($search ?? '') ?>">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Buscar
                    </button>
                </div>
            </form>
        </div>
        <div class="col-lg-3">
            <form method="GET">
                <select name="categoria" class="form-select form-select-lg" onchange="this.form.submit()">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" 
                                <?= ($categoriaSelecionada ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="col-lg-3 text-end">
            <a href="/carrinho" class="btn btn-success btn-lg">
                <i class="fas fa-shopping-cart me-2"></i>
                Ver Carrinho
                <span class="badge bg-white text-success ms-2 cart-badge">0</span>
            </a>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="row">
        <?php if (empty($produtos)): ?>
            <div class="col-lg-12">
                <div class="text-center py-5">
                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                    <h3 class="text-muted">Nenhum produto encontrado</h3>
                    <p class="text-muted">Tente ajustar sua busca ou filtros</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($produtos as $produto): ?>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card h-100 product-card-modern border-0 shadow-sm">
                    <div class="position-relative overflow-hidden product-image-frame">
                        <?php if ($produto['foto_principal']): ?>
                            <img src="<?= $produto['foto_principal'] ?>" 
                                 alt="<?= htmlspecialchars($produto['name']) ?>"
                                 class="card-img-top product-image-modern">
                            <!-- Badge de Estoque Baixo -->
                            <?php if ($produto['stock'] <= 5 && $produto['stock'] > 0): ?>
                                <span class="position-absolute top-0 end-0 m-2 badge bg-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?= $produto['stock'] ?> unidades
                                </span>
                            <?php endif; ?>
                            <!-- Badge de Destaque -->
                            <?php if ($produto['featured']): ?>
                                <span class="position-absolute top-0 start-0 m-2 badge bg-danger">
                                    <i class="fas fa-star me-1"></i>Destaque
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="card-img-top product-image-modern bg-light d-flex align-items-center justify-content-center">
                                <i class="fas fa-image text-muted fa-3x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-tag me-1"></i>
                                <?= htmlspecialchars($produto['categoria'] ?? 'Sem categoria') ?>
                            </small>
                        </div>
                        
                        <h5 class="card-title fw-bold text-truncate">
                            <?= htmlspecialchars($produto['name']) ?>
                        </h5>
                        
                        <p class="card-text text-muted small mb-3">
                            <?= htmlspecialchars(substr($produto['short_description'] ?? '', 0, 80)) ?>...
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="price-section">
                                <span class="h4 text-primary fw-bold mb-0 product-price" 
                                      data-original-price="<?= $produto['price'] ?>">
                                    $<?= number_format($produto['price'], 2, '.', ',') ?>
                                </span>
                                <?php if ($produto['sale_price'] > 0): ?>
                                    <small class="text-decoration-line-through text-muted product-sale-price"
                                          data-original-sale-price="<?= $produto['sale_price'] ?>">
                                        $<?= number_format($produto['sale_price'], 2, '.', ',') ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="stock-info">
                                <?php if ($produto['stock'] > 0): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Disponível
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i>Esgotado
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent border-top-0">
                        <div class="d-grid gap-2">
                            <a href="/produto/detalhes/<?= $produto['id'] ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-2"></i>Ver Detalhes
                            </a>
                            <button class="btn btn-primary btn-sm btn-adicionar-modern" 
                                    data-produto-id="<?= $produto['id'] ?>"
                                    data-produto-nome="<?= htmlspecialchars($produto['name']) ?>"
                                    data-produto-preco="<?= $produto['price'] ?>"
                                    data-is-variavel="<?= !empty($produto['is_variavel']) ? '1' : '0' ?>"
                                    <?= $produto['stock'] > 0 ? '' : 'disabled' ?>>
                                <i class="fas fa-cart-plus me-2"></i>
                                <?= $produto['stock'] > 0 ? 'Adicionar ao Carrinho' : 'Indisponível' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.product-card-modern {
    transition: none;
    border-radius: 15px;
}

.product-image-frame {
    aspect-ratio: 1 / 1;
}

.product-card-modern:hover {
    transform: none;
    box-shadow: var(--shadow-md);
}

.product-image-modern {
    transition: none;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-card-modern:hover .product-image-modern {
    transform: none;
}

.bg-gradient-primary {
    background: var(--primary-color);
}

.btn-adicionar-modern {
    transition: none;
}

.btn-adicionar-modern:hover:not(:disabled) {
    transform: none;
    box-shadow: none;
}

.cart-badge {
    display: none;
    min-width: 20px;
    height: 20px;
    border-radius: 50%;
    font-size: 0.75rem;
    line-height: 1;
}

.alert-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
}

.alert {
    animation: none;
}
</style>

<script>
// Função para converter preços com base na moeda
function updateProductPrices(currency) {
    const currencySymbol = currency === 'BRL' ? 'R$' : '$';
    const rate = window.exchangeRates ? window.exchangeRates[currency] : 1;
    
    // Atualizar todos os preços de produtos
    const productPrices = document.querySelectorAll('.product-price');
    productPrices.forEach((element, index) => {
        const originalValue = parseFloat(element.getAttribute('data-original-price'));
        
        if (!isNaN(originalValue)) {
            let convertedPrice;
            
            if (currency === 'BRL') {
                // Converter USD para BRL: multiplicar pela taxa
                convertedPrice = originalValue * rate;
            } else {
                // Manter em USD: sem conversão
                convertedPrice = originalValue;
            }
            
            const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
            element.textContent = formattedPrice;
        }
    });
    
    // Atualizar preços promocionais
    const salePrices = document.querySelectorAll('.product-sale-price');
    salePrices.forEach((element, index) => {
        const originalValue = parseFloat(element.getAttribute('data-original-sale-price'));
        
        if (!isNaN(originalValue)) {
            let convertedPrice;
            
            if (currency === 'BRL') {
                convertedPrice = originalValue * rate;
            } else {
                convertedPrice = originalValue;
            }
            
            const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
            element.textContent = formattedPrice;
        }
    });
}

// Função para adicionar ao carrinho
function adicionarAoCarrinhoModerno(botao) {
    const produtoId = botao.getAttribute('data-produto-id');
    const quantidade = 1; // Simplificado - sempre 1 por agora

    const isVariavel = String(botao.getAttribute('data-is-variavel') || '0') === '1';
    if (isVariavel) {
        const url = `/produto/detalhes/${encodeURIComponent(produtoId)}?selecionar_variacao=1`;
        window.location.href = url;
        return;
    }
    
    if (botao.disabled) {
        return;
    }
    
    // Desabilitar botão
    botao.disabled = true;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adicionando...';
    
    // Fazer requisição AJAX
    fetch('/carrinho/adicionar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `id=${encodeURIComponent(produtoId)}&quantidade=${encodeURIComponent(quantidade)}`
    })
    .then(response => response.json())
    .then(data => {
        // Reabilitar botão
        botao.disabled = false;
        botao.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Adicionar ao Carrinho';
        
        if (data.success) {
            mostrarAlerta('success', data.message);
            atualizarBadge(data.total_itens);
        } else {
            mostrarAlerta('danger', data.error);
        }
    })
    .catch(error => {
        botao.disabled = false;
        botao.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Adicionar ao Carrinho';
        mostrarAlerta('danger', 'Erro ao adicionar produto');
    });
}

// Função para mostrar alerta
function mostrarAlerta(tipo, mensagem) {
    const alertContainer = document.createElement('div');
    alertContainer.className = 'alert-container';
    
    const alertHtml = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${mensagem}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    alertContainer.innerHTML = alertHtml;
    document.body.appendChild(alertContainer);
    
    setTimeout(() => {
        alertContainer.remove();
    }, 5000);
}

// Função para atualizar badge
function atualizarBadge(totalItens) {
    const badges = document.querySelectorAll('.cart-badge');
    badges.forEach(badge => {
        if (totalItens > 0) {
            badge.textContent = totalItens;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    });
}

// Inicializar taxas de conversão se não existirem
if (typeof window.exchangeRates === 'undefined') {
    window.exchangeRates = {
        'BRL': 5.50,
        'USD': 1.00
    };
}

// Adicionar eventos aos botões
document.addEventListener('DOMContentLoaded', function() {
    const botoes = document.querySelectorAll('.btn-adicionar-modern');
    botoes.forEach(botao => {
        botao.addEventListener('click', function(e) {
            e.preventDefault();
            adicionarAoCarrinhoModerno(this);
        });
    });
    
    // Verificar moeda atual e atualizar preços
    const headerCurrency = document.getElementById('current-currency');
    if (headerCurrency) {
        const currentCurrency = headerCurrency.textContent;
        updateProductPrices(currentCurrency);
        
        // Monitorar mudanças na moeda
        setInterval(function() {
            const newCurrency = headerCurrency.textContent;
            if (typeof window.lastCurrency === 'undefined' || window.lastCurrency !== newCurrency) {
                window.lastCurrency = newCurrency;
                updateProductPrices(newCurrency);
            }
        }, 200);
    }
});

// Função global para atualizar preços
window.updateProductPrices = updateProductPrices;
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
