<?php ob_start(); ?>
<div class="auth-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                        <h3 class="mb-0">Criar Conta</h3>
                        <p class="text-muted">Junte-se a milhares de clientes satisfeitos</p>
                    </div>
                    
                    <form method="POST" action="/register" id="registerForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="nome" name="nome" 
                                           placeholder="Seu nome" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="telefone" class="form-label">Telefone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="telefone" name="telefone" 
                                           placeholder="(00) 00000-0000" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="seu@email.com" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="documento" class="form-label">CPF/CNPJ</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="documento" name="documento" 
                                       placeholder="000.000.000-00" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="senha" class="form-label">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="senha" name="senha" 
                                           placeholder="Mínimo 6 caracteres" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirmar_senha" 
                                           name="senha_confirmacao" placeholder="Confirme sua senha" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="termos" name="termos" required>
                                <label class="form-check-label" for="termos">
                                    Li e aceito os <a href="/termos-uso" class="text-decoration-none">Termos de Uso</a> e 
                                    <a href="/politica-privacidade" class="text-decoration-none">Política de Privacidade</a>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="newsletter" name="newsletter">
                                <label class="form-check-label" for="newsletter">
                                    Desejo receber ofertas exclusivas por e-mail
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="registerBtn">
                                <i class="fas fa-user-plus me-2"></i> Criar Conta
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <p class="mb-0">
                                Já tem uma conta? 
                                <a href="/login" class="text-decoration-none">Faça login</a>
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
    
    // Form validation
    $('#registerForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validar senhas
        const senha = $('#senha').val();
        const confirmarSenha = $('#confirmar_senha').val();
        
        if (senha !== confirmarSenha) {
            showAlert('danger', 'As senhas não coincidem');
            return;
        }
        
        if (senha.length < 6) {
            showAlert('danger', 'A senha deve ter no mínimo 6 caracteres');
            return;
        }
        
        const btn = $('#registerBtn');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Criando conta...');
        
        $.ajax({
            url: '/register',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'Conta criada com sucesso! Redirecionando...');
                    setTimeout(function() {
                        window.location.href = response.redirect || '/login';
                    }, 2000);
                } else {
                    showAlert('danger', response.error || 'Erro ao criar conta');
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
    
    // CPF/CNPJ mask
    $('#documento').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        
        if (value.length <= 11) {
            // CPF
            value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        } else {
            // CNPJ
            value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
        }
        
        $(this).val(value);
    });
    
    // Phone mask
    $('#telefone').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        
        if (value.length <= 10) {
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        } else {
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        }
        
        $(this).val(value);
    });
});

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show auth-alert" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    const $card = $('.card').first();
    $card.find('.auth-alert').remove();

    const $header = $card.find('.card-header').first();
    if ($header.length) {
        $header.after(alertHtml);
    } else {
        const $body = $card.find('.card-body').first();
        if ($body.length) {
            $body.prepend(alertHtml);
        } else {
            $card.prepend(alertHtml);
        }
    }
    
    setTimeout(function() {
        $card.find('.auth-alert').alert('close');
    }, 5000);
}
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
    border-color: rgba(29, 78, 216, 0.55);
    box-shadow: 0 0 0 0.2rem rgba(29, 78, 216, 0.18);
}

.auth-page .btn-primary {
    background: var(--primary-color);
    border: 1px solid rgba(11, 31, 58, 0.22);
    border-radius: var(--radius-lg);
    padding: 12px 30px;
    font-weight: 600;
    color: #ffffff;
    transition: filter 0.2s ease, background-color 0.2s ease;
}

.auth-page .btn-primary:hover {
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
