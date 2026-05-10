<?php
/** @var array $live */
/** @var int $liveId */
/** @var string $title */
ob_start();
?>

<div class="container py-5" style="max-width:500px">
    <div class="text-center mb-4">
        <div class="mb-3">
            <span class="badge bg-danger" style="font-size:14px"><i class="fas fa-video me-1"></i> LIVE</span>
        </div>
        <h2><?= htmlspecialchars($live['title']) ?></h2>
        <p class="text-muted">Para participar da live e poder comprar produtos com 1 clique, cadastre seu cartão de crédito.</p>
    </div>

    <div class="card border-0 shadow" style="border-radius:16px">
        <div class="card-body p-4">
            <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i>Cadastrar Cartão</h5>
            
            <form id="cardForm">
                <div class="mb-3">
                    <label class="form-label">Nome no cartão</label>
                    <input type="text" class="form-control" id="holderName" placeholder="Como está no cartão" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Número do cartão</label>
                    <input type="text" class="form-control" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19" required
                           oninput="this.value=this.value.replace(/[^\d]/g,'').replace(/(.{4})/g,'$1 ').trim()">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Validade</label>
                        <input type="text" class="form-control" id="expiry" placeholder="MM/AA" maxlength="5" required
                               oninput="this.value=this.value.replace(/[^\d]/g,'').replace(/^(\d{2})(\d)/,'$1/$2')">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">CVV</label>
                        <input type="text" class="form-control" id="cvv" placeholder="123" maxlength="4" required
                               oninput="this.value=this.value.replace(/[^\d]/g,'')">
                    </div>
                </div>

                <button type="submit" class="btn btn-danger btn-lg w-100" id="btnSaveCard" style="border-radius:12px">
                    <i class="fas fa-lock me-2"></i>Salvar e entrar na live
                </button>
            </form>

            <p class="text-center text-muted small mt-3 mb-0">
                <i class="fas fa-shield-alt me-1"></i>Seus dados são criptografados e seguros. O cartão será usado apenas para compras durante a live.
            </p>
        </div>
    </div>

    <div class="text-center mt-3">
        <a href="/lives" class="text-muted small">← Voltar para lives</a>
    </div>
</div>

<script>
document.getElementById('cardForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var btn = document.getElementById('btnSaveCard');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Salvando...';

    var cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
    var expiry = document.getElementById('expiry').value.split('/');
    var holderName = document.getElementById('holderName').value.trim();
    var lastFour = cardNumber.slice(-4);
    
    // Detectar bandeira
    var brand = 'unknown';
    if (cardNumber.startsWith('4')) brand = 'visa';
    else if (cardNumber.startsWith('5') || cardNumber.startsWith('2')) brand = 'mastercard';
    else if (cardNumber.startsWith('3')) brand = 'amex';

    // TODO: Aqui deveria usar o SDK do gateway (Stripe Elements, Pagar.me, etc.)
    // para tokenizar o cartão sem enviar o número pro backend.
    // Por ora, salvamos um token simulado para desenvolvimento.
    
    fetch('/api/me/payment-methods', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'gateway=live_<?= $liveId ?>&token=tok_dev_' + Date.now() + '&brand=' + brand + '&last_four=' + lastFour + '&holder_name=' + encodeURIComponent(holderName) + '&expiry_month=' + (expiry[0] || '') + '&expiry_year=20' + (expiry[1] || '')
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            // Redirecionar para a live
            window.location.href = '/lives/<?= $liveId ?>';
        } else {
            alert(data.error || 'Erro ao salvar cartão');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-2"></i>Salvar e entrar na live';
        }
    })
    .catch(function() {
        alert('Erro de conexão');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock me-2"></i>Salvar e entrar na live';
    });
});
</script>

<?php
$content = ob_get_clean();
$title = $title ?? 'Cadastrar Cartão';
include __DIR__ . '/../layouts/main.php';
?>
