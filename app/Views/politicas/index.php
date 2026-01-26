<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-9 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-5 mb-3">Políticas</h1>
                <p class="lead text-muted">Acesse documentos e diretrizes importantes.</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <p class="text-muted mb-4">
                        Aqui você encontra as principais políticas e documentos institucionais.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="/politica-privacidade" class="text-decoration-none">
                                <div class="p-4 bg-light rounded h-100">
                                    <div class="fw-bold mb-1"><i class="fas fa-user-shield me-2"></i> Política de Privacidade</div>
                                    <div class="text-muted">Como coletamos, usamos e protegemos seus dados.</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="/termos-uso" class="text-decoration-none">
                                <div class="p-4 bg-light rounded h-100">
                                    <div class="fw-bold mb-1"><i class="fas fa-file-contract me-2"></i> Termos de Uso</div>
                                    <div class="text-muted">Condições para utilização da plataforma.</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 text-muted">
                        Se precisar de ajuda, visite a página de <a href="/suporte">Suporte</a>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
