<?php ob_start(); ?>
<div class="auth-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-unlock-alt fa-3x text-primary mb-3"></i>
                        <h3 class="mb-0">Recuperar Senha</h3>
                        <p class="text-muted">Informe seu e-mail para receber instruções</p>
                    </div>

                    <form method="POST" action="/recuperar-senha">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" required>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i> Enviar
                            </button>
                        </div>

                        <div class="text-center">
                            <small class="text-muted">
                                <a href="/login" class="text-decoration-none">Voltar para o login</a>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
</div>
<?php include __DIR__ . '/../layouts/main.php'; ?>
