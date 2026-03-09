<?php ob_start(); ?>
<div class="container py-4">
    <!-- Container para alertas -->
    <div id="alert-container" class="mb-4"></div>
    
    <div class="row mb-4">
        <div class="col-lg-8">
            <h2><i class="fas fa-box"></i> <?= __('products.title', 'Produtos Disponíveis') ?></h2>
        </div>
        <div class="col-lg-4 text-end">
            <a href="/carrinho" class="btn btn-outline-primary">
                <i class="fas fa-shopping-cart"></i> <?= __('products.view_cart', 'Ver Carrinho') ?>
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-4">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="<?= htmlspecialchars(__('products.search_placeholder', 'Buscar produtos...'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($search ?? '') ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        <div class="col-lg-4">
            <form method="GET">
                <select name="categoria" class="form-select" onchange="this.form.submit()">
                    <option value=""><?= __('products.all_categories', 'Todas as Categorias') ?></option>
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
                    <i class="fas fa-info-circle"></i> <?= __('products.none_found', 'Nenhum produto encontrado.') ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($produtos as $produto): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100 product-card">
                    <a href="/produto/detalhes/<?= $produto['id'] ?>" class="text-decoration-none">
                        <div class="product-image-container">
                            <?php 
                            // Usar apenas imagens reais, sem placeholder
                            $fotoUrl = !empty($produto['foto_principal']) ? $produto['foto_principal'] : null;
                            ?>
                            <?php if ($fotoUrl): ?>
                                <img src="<?= $fotoUrl ?>" 
                                     alt="<?= htmlspecialchars($produto['nome']) ?>"
                                     class="card-img-top product-image"
                                     style="cursor: pointer;">
                            <?php else: ?>
                                <div class="card-img-top product-image d-flex align-items-center justify-content-center bg-light">
                                    <i class="fas fa-image text-muted fa-2x"></i>
                                </div>
                            <?php endif; ?>
                             
                            <!-- Badge de estoque -->
                            <?php if ($produto['estoque'] <= 5): ?>
                                <span class="position-absolute top-0 end-0 m-2 badge" style="background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.35); color: rgba(124, 45, 18, 1);">
                                    <?= $produto['estoque'] ?> unidades
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title text-dark"><?= htmlspecialchars($produto['nome']) ?></h5>
                            <p class="text-muted small"><?= htmlspecialchars($produto['categoria_nome']) ?></p>
                            <p class="card-text text-truncate"><?= htmlspecialchars(substr($produto['descricao_curta'], 0, 80)) ?>...</p>
                             
                            <div class="price-section mb-3">
                                <div class="current-price">
                                    <span class="currency"><?= $produto['moeda'] ?></span>
                                    <span class="amount product-price" data-original-price="<?= $produto['valor'] ?>"><?= number_format($produto['valor'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                    <div class="card-footer">
                        <div class="input-group">
                            <input type="number" class="form-control quantidade-input" value="1" min="1" max="<?= $produto['estoque'] ?>" data-produto-id="<?= $produto['id'] ?>">
                            <button class="btn btn-primary btn-adicionar" data-produto-id="<?= $produto['id'] ?>" data-produto-nome="<?= htmlspecialchars($produto['nome']) ?>" data-produto-preco="<?= $produto['valor'] ?>" <?= $produto['estoque'] > 0 ? '' : 'disabled' ?>>
                                <?php if ($produto['estoque'] > 0): ?>
                                    <i class="fas fa-cart-plus"></i> <?= __('products.add', 'Adicionar') ?>
                                <?php else: ?>
                                    <i class="fas fa-times"></i> <?= __('products.unavailable', 'Indisponível') ?>
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

<style>
.product-image-container {
    height: 200px;
    overflow: hidden;
    position: relative;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.current-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary-color);
}

.currency {
    font-size: 0.9rem;
    vertical-align: super;
}

.price-conversion {
    font-size: 0.8rem;
}

@media (max-width: 575.98px) {
    .product-card .card-footer .input-group {
        flex-wrap: wrap;
        gap: 10px;
    }

    .product-card .card-footer .quantidade-input {
        flex: 1 1 100px;
        min-width: 96px;
        border-radius: 12px !important;
    }

    .product-card .card-footer .btn-adicionar {
        flex: 1 1 100%;
        width: 100%;
        border-radius: 12px !important;
        padding-top: 10px;
        padding-bottom: 10px;
    }
}
</style>

<script>
const I18N = {
    add: <?= json_encode(__('products.add', 'Adicionar'), JSON_UNESCAPED_UNICODE) ?>,
    adding: <?= json_encode(__('products.adding', 'Adicionando...'), JSON_UNESCAPED_UNICODE) ?>,
    error_add: <?= json_encode(__('products.error_add', 'Erro ao adicionar produto'), JSON_UNESCAPED_UNICODE) ?>
};

// TESTE BÁSICO - VERIFICAR SE JAVASCRIPT ESTÁ FUNCIONANDO
console.log('🚀 SCRIPT CARREGADO - TESTE BÁSICO');
console.log('📄 Documento:', document);
console.log('🌐 Window:', window);
console.log('⏰ Hora atual:', new Date().toISOString());

// TESTE: Verificar se os botões existem ANTES do document ready
var botoesTeste = document.querySelectorAll('.btn-adicionar');
console.log('🔍 Botões encontrados (antes do ready):', botoesTeste.length);

// FORÇAR EXECUÇÃO IMEDIATA
if (botoesTeste.length > 0) {
    console.log('🎯 ADICIONANDO EVENTOS IMEDIATAMENTE');
    
    botoesTeste.forEach(function(botao, index) {
        console.log('🔧 Adicionando evento ao botão', index + 1, 'IMEDIATO');
        
        // Adicionar evento de clique
        botao.onclick = function(e) {
            e.preventDefault();
            console.log('🎯 CLIQUE DETECTADO NO BOTÃO', index + 1, '(IMEDIATO)');
            console.log('📦 Botão:', this);
            console.log('🆔 Produto ID:', this.getAttribute('data-produto-id'));
            console.log('📊 Quantidade:', this.closest('.input-group').querySelector('.quantidade-input').value);
            
            // Chamar função de adicionar
            adicionarAoCarrinho(this);
        };
        
        console.log('✅ Evento adicionado ao botão', index + 1);
    });
} else {
    console.log('❌ NENHUM BOTÃO ENCONTRADO IMEDIATAMENTE');
}

// Função para adicionar ao carrinho
function adicionarAoCarrinho(botao) {
    console.log('🚀 FUNÇÃO adicionarAoCarrinho CHAMADA');
    
    var produtoId = botao.getAttribute('data-produto-id');
    var quantidade = botao.closest('.input-group').querySelector('.quantidade-input').value;
    
    console.log('📦 Produto ID:', produtoId);
    console.log('📊 Quantidade:', quantidade);
    console.log('🔒 Botão desabilitado:', botao.disabled);
    
    if (botao.disabled) {
        console.log('❌ Botão está desabilitado');
        return;
    }
    
    // Desabilitar botão
    botao.disabled = true;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + I18N.adding;
    
    console.log('🌐 Iniciando requisição AJAX...');
    
    // Fazer requisição AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/carrinho/adicionar', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onreadystatechange = function() {
        console.log('📡 Estado XHR:', xhr.readyState);
        
        if (xhr.readyState === 4) {
            console.log('📡 Status XHR:', xhr.status);
            console.log('📡 Resposta XHR:', xhr.responseText);
            
            // Reabilitar botão
            botao.disabled = false;
            botao.innerHTML = '<i class="fas fa-cart-plus"></i> ' + I18N.add;
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    console.log('✅ Resposta JSON:', response);
                    
                    if (response.success) {
                        console.log('✅ PRODUTO ADICIONADO!');
                        console.log('🛒 Total itens:', response.total_itens);
                        
                        // Mostrar alerta
                        mostrarAlerta('success', response.message);
                        
                        // Atualizar badge
                        atualizarBadge(response.total_itens);
                        
                        // Adicionar ao mini carrinho lateral
                        if (typeof addToMiniCart === 'function') {
                            var produtoData = {
                                id: produtoId,
                                nome: botao.getAttribute('data-produto-nome'),
                                preco: botao.getAttribute('data-produto-preco'),
                                quantidade: quantidade,
                                imagem: botao.closest('.product-card').querySelector('.product-image').getAttribute('src')
                            };
                            console.log('📦 Adicionando ao mini carrinho:', produtoData);
                            addToMiniCart(produtoData);
                        } else {
                            console.log('❌ Mini carrinho não disponível');
                        }
                        
                    } else {
                        console.log('❌ Erro na resposta:', response.error);
                        mostrarAlerta('danger', response.error);
                    }
                } catch (e) {
                    console.log('❌ Erro ao parsear JSON:', e);
                    mostrarAlerta('danger', 'Erro na resposta do servidor');
                }
            } else {
                console.log('❌ Erro HTTP:', xhr.status);
                mostrarAlerta('danger', I18N.error_add);
            }
        }
    };
    
    var dados = 'id=' + encodeURIComponent(produtoId) + '&quantidade=' + encodeURIComponent(quantidade);
    console.log('📤 Dados enviados:', dados);
    
    xhr.send(dados);
}

// Função para mostrar alerta
function mostrarAlerta(tipo, mensagem) {
    console.log('📢 Mostrando alerta:', tipo, mensagem);
    
    var alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
        console.log('❌ Container de alertas não encontrado');
        return;
    }
    
    var alertHtml = '<div class="alert alert-' + tipo + ' alert-dismissible fade show" role="alert">' +
                   mensagem +
                   '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                   '</div>';
    
    alertContainer.innerHTML = alertHtml;
    
    setTimeout(function() {
        var alert = alertContainer.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Função para atualizar badge
function atualizarBadge(totalItens) {
    if (window.updateCartBadge && typeof window.updateCartBadge === 'function') {
        window.updateCartBadge(totalItens);
        return;
    }

    console.log('🏷️ Atualizando badge:', totalItens);
    
    var badges = document.querySelectorAll('.cart-badge');
    console.log('🏷️ Badges encontrados:', badges.length);
    
    badges.forEach(function(badge) {
        if (totalItens > 0) {
            badge.textContent = totalItens;
            badge.style.display = 'inline-block';
            console.log('✅ Badge atualizado:', totalItens);
        } else {
            badge.style.display = 'none';
            console.log('🙈 Badge ocultado');
        }
    });
}

// Função para atualizar preços com base na moeda - PÁGINA PÚBLICA DE PRODUTOS
function updateProductPrices(currency) {
    console.log('🔍 [PRODUTOS PÚBLICOS] updateProductPrices() chamada com currency:', currency);
    
    const currencySymbol = currency === 'BRL' ? 'R$' : '$';
    const rate = window.exchangeRates ? window.exchangeRates[currency] : 1;
    
    console.log('🔍 [PRODUTOS PÚBLICOS] currencySymbol:', currencySymbol);
    console.log('🔍 [PRODUTOS PÚBLICOS] rate:', rate);
    console.log('🔍 [PRODUTOS PÚBLICOS] window.exchangeRates:', window.exchangeRates);
    
    // DEBUG: Mostrar que o valor original está em USD
    console.log('🔍 [PRODUTOS PÚBLICOS] VALOR ORIGINAL EM USD');
    console.log('🔍 [PRODUTOS PÚBLICOS] - Se currency = BRL: USD × 5.5 = BRL');
    console.log('🔍 [PRODUTOS PÚBLICOS] - Se currency = USD: USD × 1 = USD (sem conversão)');
    
    if (!rate) {
        console.error('❌ [PRODUTOS PÚBLICOS] Taxa de conversão não encontrada para:', currency);
        console.error('❌ [PRODUTOS PÚBLICOS] Taxas disponíveis:', window.exchangeRates);
        return;
    }
    
    // Atualizar todos os preços de produtos - usando data-original-price
    const productPrices = document.querySelectorAll('.product-price');
    console.log('🔍 [PRODUTOS PÚBLICOS] Preços de produtos encontrados:', productPrices.length);
    
    productPrices.forEach((element, index) => {
        if (element) {
            const originalValue = parseFloat(element.getAttribute('data-original-price'));
            console.log(`🔍 [PRODUTOS PÚBLICOS] Produto ${index} - Valor original (USD):`, originalValue);
            
            // LÓGICA CORRETA: valor original em USD
            if (!isNaN(originalValue)) {
                let convertedPrice;
                
                if (currency === 'BRL') {
                    // Converter USD para BRL: multiplicar pela taxa
                    convertedPrice = originalValue * rate;
                    console.log(`🔍 [PRODUTOS PÚBLICOS] Convertendo USD para BRL: ${originalValue} × ${rate} = ${convertedPrice}`);
                } else {
                    // Manter em USD: sem conversão
                    convertedPrice = originalValue;
                    console.log(`🔍 [PRODUTOS PÚBLICOS] Mantendo USD: ${originalValue} (sem conversão)`);
                }
                
                const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
                element.textContent = formattedPrice;
                console.log(`🔍 [PRODUTOS PÚBLICOS] Produto ${index} - Valor convertido:`, formattedPrice);
            } else {
                console.error(`❌ [PRODUTOS PÚBLICOS] Produto ${index} - Valor original inválido:`, element.getAttribute('data-original-price'));
            }
        }
    });
    
    console.log('🔍 [PRODUTOS PÚBLICOS] updateProductPrices() concluída');
    console.log('🔍 [PRODUTOS PÚBLICOS] LÓGICA: valor_original_USD × rate (para BRL) ou valor_original_USD (para USD)');
}

// Inicializar com a moeda atual
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 [PRODUTOS PÚBLICOS] DOMContentLoaded iniciado');
    console.log('🔍 [PRODUTOS PÚBLICOS] Verificando estrutura...');
    
    // Verificar elementos com preço
    const productPrices = document.querySelectorAll('.product-price');
    console.log('🔍 [PRODUTOS PÚBLICOS] Elementos .product-price encontrados:', productPrices.length);
    
    // Verificar todos os spans com preço
    const allSpans = document.querySelectorAll('span');
    console.log('🔍 [PRODUTOS PÚBLICOS] Total de spans na página:', allSpans.length);
    
    let spansComPreco = 0;
    allSpans.forEach((span, index) => {
        const text = span.textContent.trim();
        if (text.includes('R$') || text.includes('$')) {
            spansComPreco++;
            console.log(`🔍 [PRODUTOS PÚBLICOS] Span ${index} com preço:`, text);
            console.log(`🔍 [PRODUTOS PÚBLICOS] Classe:`, span.className);
            console.log(`🔍 [PRODUTOS PÚBLICOS] data-original-price:`, span.getAttribute('data-original-price'));
        }
    });
    
    console.log('🔍 [PRODUTOS PÚBLICOS] Total de spans com preço:', spansComPreco);
    
    const headerCurrency = document.getElementById('current-currency');
    if (headerCurrency) {
        const currentCurrency = headerCurrency.textContent;
        console.log('🔍 [PRODUTOS PÚBLICOS] Moeda inicial:', currentCurrency);
        
        // Definir taxas de conversão se não existirem
        if (typeof window.exchangeRates === 'undefined') {
            window.exchangeRates = {
                'BRL': 5.50,
                'USD': 1.00
            };
            console.log('🔍 [PRODUTOS PÚBLICOS] Taxas de conversão definidas:', window.exchangeRates);
        }
        
        // Teste manual da função
        console.log('🔍 [PRODUTOS PÚBLICOS] Chamando updateProductPrices manualmente...');
        updateProductPrices(currentCurrency);
        
        // Teste após 1 segundo para garantir que o DOM está pronto
        setTimeout(function() {
            console.log('🔍 [PRODUTOS PÚBLICOS] Chamando updateProductPrices após 1 segundo...');
            updateProductPrices(currentCurrency);
        }, 1000);
    } else {
        console.error('❌ [PRODUTOS PÚBLICOS] Elemento current-currency não encontrado');
    }
    
    // Verificar mudanças na moeda do header
    setInterval(function() {
        const headerCurrency = document.getElementById('current-currency');
        if (headerCurrency) {
            const newCurrency = headerCurrency.textContent;
            
            // Verificar se a moeda mudou
            if (typeof window.lastCurrency === 'undefined' || window.lastCurrency !== newCurrency) {
                window.lastCurrency = newCurrency;
                console.log('🔍 [PRODUTOS PÚBLICOS] Moeda mudou para:', newCurrency);
                updateProductPrices(newCurrency);
            }
        }
    }, 200);
});

// Função global para atualizar preços de produtos
window.updateProductPrices = updateProductPrices;

// Tentar document ready também
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 DOMContentLoaded disparado');
    
    var botoes = document.querySelectorAll('.btn-adicionar');
    console.log('🔍 Botões encontrados (DOMContentLoaded):', botoes.length);
    
    if (botoes.length === 0) {
        console.log('❌ NENHUM BOTÃO ENCONTRADO - VERIFICANDO HTML');
        console.log('HTML do body:', document.body.innerHTML.substring(0, 500));
    }
});

console.log('🏁 SCRIPT CARREGADO COMPLETAMENTE');
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
