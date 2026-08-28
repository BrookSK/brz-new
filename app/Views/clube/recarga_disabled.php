<?php
ob_start();
$clubeWhatsapp = $clube_whatsapp ?? '13053638204';
$clubeWhatsappLabel = $clube_whatsapp_label ?? '+1 305-363-8204';
$clubeWhatsappMsg = rawurlencode('Olá, gostaria de saber mais sobre o meu Clube Braziliana.');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-sm border-0 text-center p-5" style="border-radius:16px;">
                <div class="mb-3"><i class="fas fa-pause-circle fa-3x" style="color:#b45309;"></i></div>
                <h1 class="h4 fw-bold mb-2" style="color:#0b1f3a;">Recargas pausadas</h1>
                <p class="text-muted mb-4">
                    No momento não estamos aceitando novas recargas do Clube Braziliana.
                    Se você já é membro e deseja utilizar seus créditos, entre em contato
                    com a nossa equipe pelo WhatsApp para mais detalhes.
                </p>
                <a href="https://wa.me/<?= htmlspecialchars($clubeWhatsapp) ?>?text=<?= $clubeWhatsappMsg ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-success btn-lg px-4">
                    <i class="fab fa-whatsapp me-2"></i>Falar no WhatsApp
                </a>
                <p class="text-muted small mt-3 mb-0">
                    <i class="fas fa-phone-alt me-1"></i><?= htmlspecialchars($clubeWhatsappLabel) ?>
                </p>
                <div class="mt-4">
                    <a href="/como-funciona-clube" class="text-decoration-none small">
                        <i class="fas fa-info-circle me-1"></i>Saiba como funciona o Clube
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
