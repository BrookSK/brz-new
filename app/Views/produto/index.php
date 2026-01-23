<?php ob_start(); ?>
<div class="container py-4">
    <!-- Container para alertas -->
    <div id="alert-container" class="mb-4"></div>
    
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
                                <img src="https://via.placeholder.com/300x300/6c757d/ffffff?text=<?= urlencode($produto['nome']) ?>" 
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
                                    <span class="amount product-price" data-original-price="<?= $produto['preco'] ?>"><?= number_format($produto['preco'], 2, ',', '.') ?></span>
                                </div>
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
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adicionando...';
    
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
            botao.innerHTML = '<i class="fas fa-cart-plus"></i> Adicionar';
            
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
                mostrarAlerta('danger', 'Erro ao adicionar produto');
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
