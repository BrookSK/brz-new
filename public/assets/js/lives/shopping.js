/**
 * Live Shopping — Compra 1-clique
 */

let buyInProgress = false;
let selectedProductForBuy = null;

// ─── Mostrar Detalhe do Produto ─────────────────────────────
function showProductDetail(product) {
    selectedProductForBuy = product;

    document.getElementById('sheetProductImg').src = product.image || '';
    document.getElementById('sheetProductName').textContent = product.name || '';
    document.getElementById('sheetProductDesc').textContent = product.description || '';
    document.getElementById('sheetProductPrice').textContent = 'R$ ' + formatPrice(product.price);

    document.getElementById('productSheet').classList.add('open');
}

function closeProductSheet() {
    document.getElementById('productSheet').classList.remove('open');
}

// ─── Comprar Agora ──────────────────────────────────────────
function buyNow() {
    if (!IS_LOGGED_IN) {
        window.location.href = '/login?redirect=/lives/' + LIVE_ID;
        return;
    }

    if (!HAS_CARD) {
        showCardForm();
        return;
    }

    if (!selectedProductForBuy) return;

    // Mostrar confirmação
    closeProductSheet();
    showConfirmation();
}

function showConfirmation() {
    const product = selectedProductForBuy;
    const details = `1x ${product.name}<br><strong>R$ ${formatPrice(product.price)}</strong>`;
    document.getElementById('confirmDetails').innerHTML = details;
    document.getElementById('confirmSheet').classList.add('open');
}

function closeConfirmSheet() {
    document.getElementById('confirmSheet').classList.remove('open');
}

// ─── Confirmar Compra ───────────────────────────────────────
async function confirmPurchase() {
    if (buyInProgress) return;
    buyInProgress = true;

    const btn = document.getElementById('btnConfirm');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processando...';

    const idempotencyKey = generateUUID();

    try {
        const res = await fetch(`/api/live/${LIVE_ID}/buy`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: selectedProductForBuy.id,
                idempotency_key: idempotencyKey
            })
        });

        const data = await res.json();

        closeConfirmSheet();

        if (data.success) {
            showToast(`✓ Pedido #${data.order_id} confirmado!`, 'success');
        } else if (data.error === 'requires_card') {
            showToast('Cadastre um cartão para comprar', 'error');
            showCardForm();
        } else if (data.error === 'requires_address') {
            showToast('Cadastre um endereço para comprar', 'error');
            // TODO: Abrir form de endereço
        } else {
            showToast(data.error || 'Erro ao processar compra', 'error');
        }

    } catch (e) {
        closeConfirmSheet();
        showToast('Erro de conexão', 'error');
    }

    // Cooldown de 3s para evitar duplo clique
    setTimeout(() => {
        buyInProgress = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-2"></i> Confirmar';
    }, 3000);
}

// ─── Formulário de Cartão ───────────────────────────────────
function showCardForm() {
    // TODO: Implementar bottom sheet com SDK do gateway para tokenização
    // Por ora, redirecionar para página de perfil
    showToast('Cadastre um cartão no seu perfil para compra rápida', 'info');
    // window.location.href = '/minha-conta/cartoes?redirect=/lives/' + LIVE_ID;
}

// ─── Helpers ────────────────────────────────────────────────
function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}
