<?php ob_start(); ?>

<!-- jQuery e Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

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
            
            <!-- Informações de Acesso -->
            <div class="alert alert-warning mt-4">
                <h6><i class="fas fa-info-circle"></i> Informações de Acesso</h6>
                <hr>
                <p class="mb-2"><strong>Email:</strong> admin@onsolutions.com</p>
                <p class="mb-2"><strong>Senha:</strong> admin123</p>
                <small class="text-muted">Use estas credenciais para acessar o painel administrativo</small>
            </div>
        </div>
    </div>
</div>

<!-- jQuery e Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
    $('#adminLoginForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#loginBtn');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Autenticando...');
        
        $.ajax({
            url: '/loginadmin',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect || '/admin/dashboard';
                } else {
                    showAlert('danger', response.error || 'Erro ao fazer login administrativo');
                }
            },
            error: function(xhr, status, error) {
                console.log('Erro:', xhr.responseText);
                showAlert('danger', 'Erro de conexão. Tente novamente.');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
});

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
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
    border-radius: 50px;
    padding: 12px 30px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(220, 53, 69, 0.3);
}

.text-decoration-none:hover {
    text-decoration: underline !important;
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
