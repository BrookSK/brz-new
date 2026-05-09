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

    var modal = new bootstrap.Modal(document.getElementById('productSheetModal'));
    modal.show();
}

function closeProductSheet() {
    var modal = bootstrap.Modal.getInstance(document.getElementById('productSheetModal'));
    if (modal) modal.hide();
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

    // Confirmar direto
    if (!confirm('Confirmar compra de ' + selectedProductForBuy.name + ' por R$ ' + formatPrice(selectedProductForBuy.price) + '?')) return;

    confirmPurchase();
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

    var btn = document.getElementById('btnBuyNow');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processando...';
    }

    var idempotencyKey = generateUUID();

    try {
        var res = await fetch('/api/live/' + LIVE_ID + '/buy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: selectedProductForBuy.id,
                idempotency_key: idempotencyKey
            })
        });

        var data = await res.json();

        closeProductSheet();

        if (data.success) {
            showToast('✓ Pedido #' + data.order_id + ' confirmado!', 'success');
        } else if (data.error === 'requires_card') {
            showToast('Cadastre um cartão para comprar', 'error');
        } else if (data.error === 'requires_address') {
            showToast('Cadastre um endereço para comprar', 'error');
        } else {
            showToast(data.error || 'Erro ao processar compra', 'error');
        }

    } catch (e) {
        closeProductSheet();
        showToast('Erro de conexão', 'error');
    }

    setTimeout(function() {
        buyInProgress = false;
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt me-2"></i> Comprar agora';
        }
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
