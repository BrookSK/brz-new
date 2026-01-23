<!-- Mini Carrinho Lateral -->
<div id="mini-cart" class="mini-cart">
    <div class="mini-cart-header">
        <h5><i class="fas fa-shopping-cart"></i> Carrinho</h5>
        <button class="close-cart" onclick="toggleMiniCart()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="mini-cart-body">
        <div id="mini-cart-items" class="mini-cart-items">
            <!-- Itens do carrinho serão inseridos aqui via JavaScript -->
            <div class="text-center py-4">
                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                <p class="text-muted">Seu carrinho está vazio</p>
            </div>
        </div>
    </div>
    
    <div class="mini-cart-footer">
        <div class="mini-cart-summary">
            <div class="d-flex justify-content-between mb-2">
                <span>Subtotal:</span>
                <span id="mini-cart-subtotal">R$ 0,00</span>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <span>Total:</span>
                <span id="mini-cart-total" class="fw-bold">R$ 0,00</span>
            </div>
        </div>
        <div class="mini-cart-actions">
            <a href="/carrinho" class="btn btn-outline-primary btn-sm w-100 mb-2">
                <i class="fas fa-shopping-cart me-2"></i> Ver Carrinho
            </a>
            <a href="/checkout" class="btn btn-primary btn-sm w-100">
                <i class="fas fa-credit-card me-2"></i> Finalizar Compra
            </a>
        </div>
    </div>
</div>

<!-- Overlay para mini carrinho -->
<div id="mini-cart-overlay" class="mini-cart-overlay" onclick="toggleMiniCart()"></div>

<style>
.mini-cart {
    position: fixed;
    top: 0;
    right: -400px;
    width: 400px;
    height: 100vh;
    background: white;
    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
    z-index: 1050;
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
}

.mini-cart.active {
    right: 0;
}

.mini-cart-header {
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: between;
    align-items: center;
    background: #f8f9fa;
}

.mini-cart-header h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.close-cart {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    transition: background-color 0.2s;
}

.close-cart:hover {
    background-color: #e9ecef;
    color: #495057;
}

.mini-cart-body {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}

.mini-cart-items {
    max-height: 400px;
    overflow-y: auto;
}

.mini-cart-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
    animation: slideIn 0.3s ease;
}

.mini-cart-item:last-child {
    border-bottom: none;
}

.mini-cart-item img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 0.5rem;
    margin-right: 0.75rem;
}

.mini-cart-item-info {
    flex: 1;
    min-width: 0;
}

.mini-cart-item-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.mini-cart-item-price {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.mini-cart-item-quantity {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mini-cart-item-quantity button {
    width: 24px;
    height: 24px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.mini-cart-item-quantity button:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
}

.mini-cart-item-quantity input {
    width: 40px;
    text-align: center;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    padding: 0.25rem;
}

.mini-cart-item-remove {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 0.25rem;
    padding: 0.25rem 0.5rem;
    cursor: pointer;
    font-size: 0.8rem;
    transition: background-color 0.2s;
}

.mini-cart-item-remove:hover {
    background: #c82333;
}

.mini-cart-footer {
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
}

.mini-cart-summary {
    margin-bottom: 1rem;
}

.mini-cart-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.mini-cart-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1040;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.mini-cart-overlay.active {
    opacity: 1;
    visibility: visible;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Mobile Responsivo */
@media (max-width: 768px) {
    .mini-cart {
        width: 100%;
        right: -100%;
    }
    
    .mini-cart.active {
        right: 0;
    }
}

/* Animação de shake quando adiciona item */
.mini-cart-item.new-item {
    animation: slideIn 0.3s ease, shake 0.5s ease 0.3s;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
    20%, 40%, 60%, 80% { transform: translateX(2px); }
}
</style>

<script>
// Função para abrir/fechar mini carrinho
function toggleMiniCart() {
    console.log('=== DEPURAÇÃO TOGGLE MINI CARRINHO ===');
    console.log('Chamado toggleMiniCart()');
    
    const miniCart = document.getElementById('mini-cart');
    const overlay = document.getElementById('mini-cart-overlay');
    
    console.log('Mini cart element:', miniCart);
    console.log('Overlay element:', overlay);
    
    if (!miniCart) {
        console.error('ERRO: Elemento mini-cart não encontrado!');
        return;
    }
    
    if (!overlay) {
        console.error('ERRO: Elemento mini-cart-overlay não encontrado!');
        return;
    }
    
    miniCart.classList.toggle('active');
    overlay.classList.toggle('active');
    
    console.log('Mini cart classes:', miniCart.className);
    console.log('Overlay classes:', overlay.className);
    
    // Auto-fechar após 5 segundos se estiver aberto
    if (miniCart.classList.contains('active')) {
        console.log('Mini carrinho aberto, agendando auto-close em 5 segundos');
        setTimeout(() => {
            if (miniCart.classList.contains('active')) {
                console.log('Fechando mini carrinho automaticamente');
                toggleMiniCart();
            }
        }, 5000);
    }
}

// Função para adicionar item ao mini carrinho
function addToMiniCart(product) {
    console.log('=== DEPURAÇÃO ADD TO MINI CART ===');
    console.log('Produto recebido:', product);
    
    const itemsContainer = document.getElementById('mini-cart-items');
    
    console.log('Container de itens:', itemsContainer);
    
    if (!itemsContainer) {
        console.error('ERRO: Elemento mini-cart-items não encontrado!');
        return;
    }
    
    // Verificar se carrinho está vazio
    if (itemsContainer.querySelector('.text-center')) {
        console.log('Removendo mensagem de carrinho vazio');
        itemsContainer.innerHTML = '';
    }
    
    // Criar elemento do item
    const itemElement = document.createElement('div');
    itemElement.className = 'mini-cart-item new-item';
    itemElement.innerHTML = `
        <img src="${product.imagem || '/uploads/produtos/placeholder.jpg'}" alt="${product.nome}">
        <div class="mini-cart-item-info">
            <div class="mini-cart-item-title">${product.nome}</div>
            <div class="mini-cart-item-price">R$ ${parseFloat(product.preco).toFixed(2)}</div>
            <div class="mini-cart-item-quantity">
                <button onclick="updateCartItemQuantity(${product.id}, -1)">-</button>
                <input type="number" value="${product.quantidade || 1}" min="1" id="qty-${product.id}" readonly>
                <button onclick="updateCartItemQuantity(${product.id}, 1)">+</button>
            </div>
        </div>
        <button class="mini-cart-item-remove" onclick="removeFromMiniCart(${product.id})">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    console.log('Elemento do item criado:', itemElement);
    
    itemsContainer.appendChild(itemElement);
    
    console.log('Elemento adicionado ao DOM');
    
    // Abrir mini carrinho
    console.log('Abrindo mini carrinho...');
    toggleMiniCart();
    
    // Atualizar totais
    console.log('Atualizando totais...');
    updateMiniCartTotals();
}

// Função para atualizar totais do mini carrinho
function updateMiniCartTotals() {
    // Aqui você fará uma chamada AJAX para buscar os totais atuais
    fetch('/api/carrinho/totais')
        .then(response => response.json())
        .then(data => {
            document.getElementById('mini-cart-subtotal').textContent = `R$ ${parseFloat(data.subtotal).toFixed(2)}`;
            document.getElementById('mini-cart-total').textContent = `R$ ${parseFloat(data.total).toFixed(2)}`;
        })
        .catch(error => {
            console.error('Erro ao buscar totais:', error);
        });
}

// Função para atualizar quantidade
function updateCartItemQuantity(productId, change) {
    const input = document.getElementById(`qty-${productId}`);
    const newQuantity = Math.max(1, parseInt(input.value) + change);
    input.value = newQuantity;
    
    // Fazer chamada AJAX para atualizar no backend
    fetch('/api/carrinho/atualizar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            produto_id: productId,
            quantidade: newQuantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateMiniCartTotals();
        }
    })
    .catch(error => {
        console.error('Erro ao atualizar quantidade:', error);
    });
}

// Função para remover item
function removeFromMiniCart(productId) {
    if (confirm('Tem certeza que deseja remover este item?')) {
        fetch('/api/carrinho/remover', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                produto_id: productId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remover elemento do DOM
                const item = document.querySelector(`.mini-cart-item:has(button[onclick*="${productId}"])`);
                if (item) {
                    item.remove();
                }
                
                // Verificar se carrinho ficou vazio
                const itemsContainer = document.getElementById('mini-cart-items');
                if (itemsContainer.children.length === 0) {
                    itemsContainer.innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Seu carrinho está vazio</p>
                        </div>
                    `;
                }
                
                updateMiniCartTotals();
            }
        })
        .catch(error => {
            console.error('Erro ao remover item:', error);
        });
    }
}

// Fechar mini carrinho ao pressionar ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const miniCart = document.getElementById('mini-cart');
        if (miniCart.classList.contains('active')) {
            toggleMiniCart();
        }
    }
});
</script>
