<?php ob_start(); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-globe-americas fa-3x text-primary mb-3"></i>
                        <h3 class="mb-0">Bem-vindo de Volta</h3>
                        <p class="text-muted">Faça login para acessar sua conta</p>
                    </div>
                    
                    <form method="POST" action="/login" id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="seu@email.com" required>
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
                                Lembrar-me
                            </label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                <i class="fas fa-sign-in-alt me-2"></i> Entrar
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <p class="mb-0">
                                Não tem uma conta? 
                                <a href="/register" class="text-decoration-none">Cadastre-se</a>
                            </p>
                            <small class="text-muted">
                                <a href="/recuperar-senha" class="text-decoration-none">Esqueceu a senha?</a>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Login Admin -->
            <div class="card shadow-lg mt-4">
                <div class="card-body p-4">
                    <div class="text-center">
                        <h5 class="mb-3">Acesso Administrativo</h5>
                        <p class="text-muted small">Acesso restrito para administradores</p>
                        <button class="btn btn-outline-secondary btn-sm" onclick="toggleAdminLogin()">
                            <i class="fas fa-user-shield me-2"></i> Login Admin
                        </button>
                    </div>
                    
                    <div id="adminLoginForm" style="display: none;" class="mt-3">
                        <form method="POST" action="/login">
                            <input type="hidden" name="admin_login" value="1">
                            <div class="mb-3">
                                <label class="form-label">E-mail Admin</label>
                                <input type="email" class="form-control" name="email" 
                                       value="admin@onsolutions.com" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Senha</label>
                                <input type="password" class="form-control" name="senha" 
                                       placeholder="••••••••" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-dark">
                                    <i class="fas fa-shield-alt me-2"></i> Entrar como Admin
                                </button>
                            </div>
                        </form>
                    </div>
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
    
    // Form submission
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#loginBtn');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Entrando...');
        
        $.ajax({
            url: '/login',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect || '/minha-conta';
                } else {
                    showAlert('danger', response.error || 'Erro ao fazer login');
                }
            },
            error: function() {
                showAlert('danger', 'Erro de conexão. Tente novamente.');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
});

function toggleAdminLogin() {
    $('#adminLoginForm').slideToggle();
}

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('.card').first().prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}
</script>

<style>
.card {
    border: none;
    border-radius: 15px;
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.input-group-text {
    background: #f8f9fa;
    border-right: none;
}

.form-control:focus {
    border-color: #6c63ff;
    box-shadow: 0 0 0 0.2rem rgba(108, 99, 255, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 50px;
    padding: 12px 30px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.text-decoration-none:hover {
    text-decoration: underline !important;
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
