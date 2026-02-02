/**
 * SISTEMA DE CONVERSÃO DE MOEDAS - ARQUIVO EXCLUSIVO
 * Força a conversão em todo o sistema
 */

// Variáveis globais
window.CurrencyConverter = {
    currentCurrency: 'BRL',
    exchangeRates: {
        BRL: 5.50,
        USD: 1.00
    },
    
    init: function() {
        console.log('=== INICIANDO SISTEMA DE CONVERSÃO EXCLUSIVO ===');
        
        // Recuperar moeda salva
        this.currentCurrency = localStorage.getItem('selected_currency') || 'BRL';
        console.log('Moeda inicial:', this.currentCurrency);
        
        // Inicializar seletor
        this.initSelector();
        
        // Forçar atualização inicial
        setTimeout(() => {
            this.updateAllPrices();
        }, 100);
        
        // Adicionar botões de teste
        this.addTestButtons();
    },
    
    initSelector: function() {
        console.log('Inicializando seletor de moeda...');
        
        // Remover eventos antigos
        const selectors = document.querySelectorAll('.currency-selector');
        console.log('Seletores encontrados:', selectors.length);
        
        selectors.forEach((selector, index) => {
            console.log(`Seletor [${index}]:`, selector.getAttribute('data-currency'));
            
            // Remover eventos anteriores
            selector.removeEventListener('click', this.handleCurrencyClick);
            
            // Adicionar novo evento
            selector.addEventListener('click', (e) => this.handleCurrencyClick(e));
            
            // Fallback
            selector.onclick = (e) => this.handleCurrencyClick(e);
        });
    },
    
    handleCurrencyClick: function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const newCurrency = e.target.getAttribute('data-currency');
        console.log('=== CLIQUE NO SELETOR ===');
        console.log('Moeda selecionada:', newCurrency);
        console.log('Moeda atual:', this.currentCurrency);
        
        if (newCurrency !== this.currentCurrency) {
            this.currentCurrency = newCurrency;
            localStorage.setItem('selected_currency', newCurrency);
            console.log('Moeda atualizada para:', this.currentCurrency);
            
            this.updateCurrencyDisplay();
            this.updateAllPrices();
            this.showNotification(newCurrency);
        } else {
            console.log('Moeda já é a mesma');
        }
    },
    
    updateCurrencyDisplay: function() {
        const span = document.getElementById('current-currency');
        if (span) {
            span.textContent = this.currentCurrency;
            console.log('Display atualizado para:', this.currentCurrency);
        }
    },
    
    updateAllPrices: function() {
        console.log('=== ATUALIZANDO TODOS OS PREÇOS ===');
        console.log('Moeda:', this.currentCurrency);
        console.log('Taxa:', this.exchangeRates[this.currentCurrency]);
        
        const symbol = this.currentCurrency === 'BRL' ? 'R$' : '$';
        const rate = this.exchangeRates[this.currentCurrency];
        
        // Atualizar elementos com data-original-price
        const elements = document.querySelectorAll('[data-original-price]');
        console.log('Elementos com data-original-price:', elements.length);
        
        elements.forEach((element, index) => {
            const originalPrice = parseFloat(element.getAttribute('data-original-price'));
            if (!isNaN(originalPrice)) {
                const convertedPrice = originalPrice * rate;
                const newPrice = `${symbol} ${convertedPrice.toFixed(2).replace('.', ',')}`;
                element.textContent = newPrice;
                console.log(`[${index}] ${originalPrice} → ${newPrice}`);
            }
        });
        
        // Atualizar elementos .currency
        const currencyElements = document.querySelectorAll('.currency');
        currencyElements.forEach(element => {
            element.textContent = this.currentCurrency;
        });
        
        // Atualizar carrinho
        this.updateCart();
        
        console.log('=== ATUALIZAÇÃO CONCLUÍDA ===');
    },
    
    updateCart: function() {
        console.log('Atualizando carrinho...');
        
        const symbol = this.currentCurrency === 'BRL' ? 'R$' : '$';
        const rate = this.exchangeRates[this.currentCurrency];
        
        // Atualizar valores do carrinho
        const cartValues = document.querySelectorAll('.cart-currency');
        cartValues.forEach(element => {
            const originalValue = parseFloat(element.getAttribute('data-original-value'));
            if (!isNaN(originalValue)) {
                if ((element.classList.contains('frete-value') || element.id === 'frete') && originalValue === 0) {
                    element.textContent = 'Frete grátis';
                    return;
                }
                const convertedValue = originalValue * rate;
                element.textContent = `${symbol} ${convertedValue.toFixed(2).replace('.', ',')}`;
            }
        });
        
        // Atualizar itens do carrinho
        const itemPrices = document.querySelectorAll('.cart-item-price, .cart-item-subtotal, .cart-item-unit');
        itemPrices.forEach(element => {
            const originalPrice = parseFloat(element.getAttribute('data-original-price'));
            if (!isNaN(originalPrice)) {
                const convertedPrice = originalPrice * rate;
                element.textContent = `${symbol} ${convertedPrice.toFixed(2).replace('.', ',')}`;
            }
        });
    },
    
    showNotification: function(currency) {
        const name = currency === 'BRL' ? 'Real Brasileiro' : 'Dólar Americano';
        console.log('Moeda alterada para:', name);
        
        // Criar notificação
        const notification = document.createElement('div');
        notification.className = 'alert alert-info alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 70px; right: 20px; z-index: 9999; min-width: 250px;';
        notification.innerHTML = `
            <i class="fas fa-coins me-2"></i>
            Moeda alterada para ${name}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    },
    
    addTestButtons: function() {
        setTimeout(() => {
            console.log('Adicionando botões de teste...');
            
            // Botão USD
            const btnUSD = document.createElement('button');
            btnUSD.textContent = 'FORÇAR USD';
            btnUSD.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 9999; background: red; color: white; padding: 10px; border: none; cursor: pointer;';
            btnUSD.onclick = () => {
                console.log('=== FORÇANDO USD ===');
                this.currentCurrency = 'USD';
                localStorage.setItem('selected_currency', 'USD');
                this.updateCurrencyDisplay();
                this.updateAllPrices();
                this.showNotification('USD');
            };
            
            // Botão BRL
            const btnBRL = document.createElement('button');
            btnBRL.textContent = 'FORÇAR BRL';
            btnBRL.style.cssText = 'position: fixed; top: 140px; right: 20px; z-index: 9999; background: green; color: white; padding: 10px; border: none; cursor: pointer;';
            btnBRL.onclick = () => {
                console.log('=== FORÇANDO BRL ===');
                this.currentCurrency = 'BRL';
                localStorage.setItem('selected_currency', 'BRL');
                this.updateCurrencyDisplay();
                this.updateAllPrices();
                this.showNotification('BRL');
            };
            
            // Botão Debug
            const btnDebug = document.createElement('button');
            btnDebug.textContent = 'DEBUG';
            btnDebug.style.cssText = 'position: fixed; top: 180px; right: 20px; z-index: 9999; background: blue; color: white; padding: 10px; border: none; cursor: pointer;';
            btnDebug.onclick = () => {
                console.log('=== DEBUG INFO ===');
                console.log('Moeda atual:', this.currentCurrency);
                console.log('LocalStorage:', localStorage.getItem('selected_currency'));
                console.log('Elementos com data-original-price:', document.querySelectorAll('[data-original-price]').length);
                console.log('Elementos .currency:', document.querySelectorAll('.currency').length);
                console.log('Elementos .cart-currency:', document.querySelectorAll('.cart-currency').length);
            };
            
            document.body.appendChild(btnUSD);
            document.body.appendChild(btnBRL);
            document.body.appendChild(btnDebug);
            
            console.log('Botões de teste adicionados');
        }, 1000);
    },
    
    // Forçar atualização manual
    forceUpdate: function() {
        console.log('=== FORÇANDO ATUALIZAÇÃO MANUAL ===');
        this.updateAllPrices();
    }
};

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.CurrencyConverter.init();
    });
} else {
    window.CurrencyConverter.init();
}

// Forçar atualização quando a página carregar
window.addEventListener('load', () => {
    setTimeout(() => {
        window.CurrencyConverter.forceUpdate();
    }, 500);
});

// Disponibilizar globalmente
window.forceCurrencyUpdate = () => {
    window.CurrencyConverter.forceUpdate();
};

console.log('Arquivo de conversão carregado com sucesso!');
