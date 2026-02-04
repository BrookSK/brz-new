<?php ob_start(); ?>
<div class="auth-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-key fa-3x text-primary mb-3"></i>
                        <h3 class="mb-0">Redefinir Senha</h3>
                        <p class="text-muted">Crie uma nova senha para sua conta</p>
                    </div>

                    <form method="POST" action="/redefinir-senha/<?= htmlspecialchars((string) ($token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label for="senha" class="form-label">Nova senha *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="senha" name="senha" placeholder="••••••••" required minlength="6">
                            </div>
                            <div class="form-text">Mínimo de 6 caracteres.</div>
                        </div>

                        <div class="mb-3">
                            <label for="senha_confirmacao" class="form-label">Confirmar nova senha *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="senha_confirmacao" name="senha_confirmacao" placeholder="••••••••" required minlength="6">
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Salvar nova senha
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
