<?php ob_start(); ?>
<div class="container py-4">

    <!-- Search and Filters -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <form method="GET" class="d-flex">
                <?php if (!empty($categoriaSelecionada)): ?>
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars((string) $categoriaSelecionada, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" 
                           placeholder="<?= htmlspecialchars(__('products.search_placeholder', 'Buscar produtos...'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($search ?? '') ?>">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?= __('products.search_button', 'Buscar') ?>
                    </button>
                </div>
            </form>
        </div>
        <div class="col-lg-3">
            <form method="GET">
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <select name="categoria" class="form-select form-select-lg" onchange="this.form.submit()">
                    <option value=""><?= __('products.all_categories', 'Todas as Categorias') ?></option>
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
                <?= __('products.view_cart', 'Ver Carrinho') ?>
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
                    <h3 class="text-muted"><?= __('products.none_found', 'Nenhum produto encontrado') ?></h3>
                    <p class="text-muted"><?= __('products.try_adjust_search', 'Tente ajustar sua busca ou filtros') ?></p>
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
                                    <?= (int) $produto['stock'] ?> <?= __('home.units_short', 'unidades') ?>
                                </span>
                            <?php endif; ?>
                            <!-- Badge de Destaque -->
                            <?php if ($produto['featured']): ?>
                                <span class="position-absolute top-0 start-0 m-2 badge bg-danger">
                                    <i class="fas fa-star me-1"></i><?= __('products.featured', 'Destaque') ?>
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
                                <?= htmlspecialchars($produto['categoria'] ?? __('products.no_category', 'Sem categoria')) ?>
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
                                <?php
                                    $precoBase = (float) ($produto['price'] ?? 0);
                                    $precoPromo = (float) ($produto['sale_price'] ?? 0);
                                    $temPromo = ($precoPromo > 0 && $precoPromo < $precoBase);
                                    $precoExibir = $temPromo ? $precoPromo : $precoBase;
                                    $isClubeBlocked = !empty($produto['clube_only']) && empty($clube_acesso);
                                ?>
                                <?php if ($isClubeBlocked): ?>
                                    <span class="badge" style="background:#0b1f3a;"><i class="fas fa-crown me-1"></i>Exclusivo Clube</span>
                                <?php else: ?>
                                <span class="h4 text-primary fw-bold mb-0 product-price" 
                                      data-original-price="<?= $precoExibir ?>">
                                    <?= number_format($precoExibir, 2, ',', '.') ?>
                                </span>
                                <?php if ($temPromo): ?>
                                    <small class="text-decoration-line-through text-muted product-original-price"
                                          data-original-original-price="<?= $precoBase ?>">
                                        <?= number_format($precoBase, 2, ',', '.') ?>
                                    </small>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="stock-info">
                                <?php if ($produto['stock'] > 0): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i><?= __('products.available', 'Disponível') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i><?= __('products.out_of_stock', 'Esgotado') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent border-top-0">
                        <div class="d-grid gap-2">
                            <?php if (!empty($isClubeBlocked)): ?>
                                <a href="/grupo-compras" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-crown me-2"></i>Saiba mais sobre o Clube
                                </a>
                            <?php else: ?>
                            <a href="/produto/detalhes/<?= $produto['id'] ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-2"></i><?= __('products.view_details', 'Ver Detalhes') ?>
                            </a>
                            <button class="btn btn-primary btn-sm btn-adicionar-modern" 
                                    data-produto-id="<?= $produto['id'] ?>"
                                    data-produto-nome="<?= htmlspecialchars($produto['name']) ?>"
                                    data-produto-preco="<?= $precoExibir ?>"
                                    data-is-variavel="<?= !empty($produto['is_variavel']) ? '1' : '0' ?>"
                                    <?= $produto['stock'] > 0 ? '' : 'disabled' ?>>
                                <i class="fas fa-cart-plus me-2"></i>
                                <?= $produto['stock'] > 0 ? __('products.add_to_cart', 'Adicionar ao Carrinho') : __('products.unavailable', 'Indisponível') ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php
        $page = (int) ($page ?? 1);
        if ($page <= 0) $page = 1;
        $totalPages = (int) ($totalPages ?? 1);
        if ($totalPages <= 0) $totalPages = 1;
        $searchVal = (string) ($search ?? '');
        $categoriaVal = (string) ($categoriaSelecionada ?? '');

        $buildProductsUrl = static function (int $p) use ($searchVal, $categoriaVal): string {
            $qs = [];
            if ($searchVal !== '') $qs['search'] = $searchVal;
            if ($categoriaVal !== '') $qs['categoria'] = $categoriaVal;
            if ($p > 1) $qs['page'] = $p;
            return '/produtos' . (!empty($qs) ? ('?' . http_build_query($qs)) : '');
        };
    ?>

    <?php if (!empty($produtos) && $totalPages > 1): ?>
        <nav aria-label="Paginação" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= htmlspecialchars($buildProductsUrl(max(1, $page - 1)), ENT_QUOTES, 'UTF-8') ?>" tabindex="-1">Anterior</a>
                </li>

                <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($buildProductsUrl(1), ENT_QUOTES, 'UTF-8') . '">1</a></li>';
                        if ($start > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    for ($p = $start; $p <= $end; $p++) {
                        $active = ($p === $page);
                        echo '<li class="page-item' . ($active ? ' active' : '') . '"><a class="page-link" href="' . htmlspecialchars($buildProductsUrl($p), ENT_QUOTES, 'UTF-8') . '">' . (int) $p . '</a></li>';
                    }
                    if ($end < $totalPages) {
                        if ($end < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($buildProductsUrl($totalPages), ENT_QUOTES, 'UTF-8') . '">' . (int) $totalPages . '</a></li>';
                    }
                ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= htmlspecialchars($buildProductsUrl(min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8') ?>">Próxima</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
window.PRODUCTS_MODERNO_I18N = {
    add_to_cart: <?= json_encode(__('products.add_to_cart', 'Adicionar ao Carrinho'), JSON_UNESCAPED_UNICODE) ?>,
    adding: <?= json_encode(__('products.adding', 'Adicionando...'), JSON_UNESCAPED_UNICODE) ?>,
    error_add: <?= json_encode(__('products.error_add', 'Erro ao adicionar produto'), JSON_UNESCAPED_UNICODE) ?>
};
</script>

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
    
    // Atualizar preços originais (riscados) quando houver promoção
    const originalPrices = document.querySelectorAll('.product-original-price');
    originalPrices.forEach((element, index) => {
        const originalValue = parseFloat(element.getAttribute('data-original-original-price'));
        
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
    botao.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + ((window.PRODUCTS_MODERNO_I18N && window.PRODUCTS_MODERNO_I18N.adding) ? window.PRODUCTS_MODERNO_I18N.adding : 'Adicionando...');
    
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
        botao.innerHTML = '<i class="fas fa-cart-plus me-2"></i>' + ((window.PRODUCTS_MODERNO_I18N && window.PRODUCTS_MODERNO_I18N.add_to_cart) ? window.PRODUCTS_MODERNO_I18N.add_to_cart : 'Adicionar ao Carrinho');
        
        if (data.success) {
            mostrarAlerta('success', data.message);
            atualizarBadge(data.total_itens);
        } else {
            mostrarAlerta('danger', data.error);
        }
    })
    .catch(error => {
        botao.disabled = false;
        botao.innerHTML = '<i class="fas fa-cart-plus me-2"></i>' + ((window.PRODUCTS_MODERNO_I18N && window.PRODUCTS_MODERNO_I18N.add_to_cart) ? window.PRODUCTS_MODERNO_I18N.add_to_cart : 'Adicionar ao Carrinho');
        mostrarAlerta('danger', (window.PRODUCTS_MODERNO_I18N && window.PRODUCTS_MODERNO_I18N.error_add) ? window.PRODUCTS_MODERNO_I18N.error_add : 'Erro ao adicionar produto');
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
    if (window.updateCartBadge && typeof window.updateCartBadge === 'function') {
        window.updateCartBadge(totalItens);
        return;
    }

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
    const resolveCurrency = function() {
        if (window.CurrencyConverter && window.CurrencyConverter.currentCurrency) {
            return window.CurrencyConverter.currentCurrency;
        }
        return localStorage.getItem('selected_currency') || 'BRL';
    };

    updateProductPrices(resolveCurrency());
    
    // Monitorar mudanças na moeda
    setInterval(function() {
        const newCurrency = resolveCurrency();
        if (typeof window.lastCurrency === 'undefined' || window.lastCurrency !== newCurrency) {
            window.lastCurrency = newCurrency;
            updateProductPrices(newCurrency);
        }
    }, 200);
});

// Função global para atualizar preços
window.updateProductPrices = updateProductPrices;
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
