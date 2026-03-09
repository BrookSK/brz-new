<?php ob_start(); ?>

<div class="auth-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-lg border-danger">
                <div class="card-header bg-danger text-white text-center">
                    <i class="fas fa-user-shield fa-2x mb-2"></i>
                    <h4 class="mb-0">Acesso Administrativo</h4>
                    <small>Área restrita para administradores</small>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/loginadmin" id="adminLoginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail Administrativo</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="admin@dominio.com" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="senha" name="senha" 
                                       placeholder="••••••••" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar">
                            <label class="form-check-label" for="lembrar">
                                Manter conectado
                            </label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-danger btn-lg" id="loginBtn">
                                <i class="fas fa-sign-in-alt me-2"></i> Entrar como Admin
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <p class="mb-0">
                                <small class="text-muted">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Acesso restrito e monitorado
                                </small>
                            </p>
                            <p class="mt-2">
                                <a href="/login" class="text-decoration-none">
                                    <i class="fas fa-arrow-left"></i> Voltar para login normal
                                </a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Toggle password visibility
    $('#togglePassword').click(function() {
        const senhaField = $('#senha');
        const icon = $(this).find('i');
        
        if (senhaField.attr('type') === 'password') {
            senhaField.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            senhaField.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});
</script>

<style>
.auth-page .card {
    border: none;
    border-radius: 15px;
    transition: none;
}

.auth-page .card:hover {
    transform: none;
}

.auth-page .input-group-text {
    background: #f8f9fa;
    border-right: none;
}

.auth-page .form-control:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.auth-page .btn-danger {
    background: #0b1f3a;
    border: 1px solid rgba(11, 31, 58, 0.22);
    border-radius: 50px;
    padding: 12px 30px;
    font-weight: 600;
    color: #ffffff;
    transition: filter 0.2s ease, background-color 0.2s ease;
}

.auth-page .btn-danger:hover {
    transform: none;
    box-shadow: none;
    filter: brightness(1.03);
}

.auth-page .auth-alert {
    margin: 0;
    border-radius: 0;
    border-left: 0;
    border-right: 0;
}

.auth-page .text-decoration-none:hover {
    text-decoration: underline !important;
}
</style>
<?php $content = ob_get_clean(); ?>
</div>
<?php include __DIR__ . '/../layouts/main.php'; ?>
