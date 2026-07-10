<?php
ob_start();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-sm border-0 text-center p-5">
                <div class="mb-4">
                    <i class="fas fa-tools text-muted" style="font-size: 3.5rem;"></i>
                </div>
                <h2 class="fw-bold mb-3">Redirecionamento em manutenção</h2>
                <p class="text-muted mb-4">
                    Para solicitar redirecionamento, entre em contato com nosso atendimento pelo WhatsApp.
                </p>
                <a href="https://wa.me/5517996203062?text=Ol%C3%A1%2C%20gostaria%20de%20solicitar%20um%20redirecionamento" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="btn btn-success btn-lg px-4">
                    <i class="fab fa-whatsapp me-2"></i>Falar no WhatsApp
                </a>
                <p class="text-muted small mt-3 mb-0">
                    <i class="fas fa-phone-alt me-1"></i>+55 17 99620-3062
                </p>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
