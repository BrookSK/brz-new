<?php ob_start(); ?>
<div class="auth-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-globe-americas fa-3x text-primary mb-3"></i>
                        <h3 class="mb-0"><?= __('auth.welcome_back', 'Bem-vindo de Volta') ?></h3>
                        <p class="text-muted"><?= __('auth.login_subtitle', 'Faça login para acessar sua conta') ?></p>
                    </div>
                    
                    <form method="POST" action="/login" id="loginForm">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars((string) ($_GET['redirect'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label"><?= __('auth.email', 'E-mail') ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="seu@email.com" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="senha" class="form-label"><?= __('auth.password', 'Senha') ?></label>
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
                                <?= __('auth.remember_me', 'Lembrar-me') ?>
                            </label>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="consentimento_legal" name="consentimento_legal">
                            <label class="form-check-label" for="consentimento_legal">
                                <?php
                                    $termsLink = '<a href="/termos-uso" class="text-decoration-none">' . __('auth.terms', 'Termos de Uso') . '</a>';
                                    $privacyLink = '<a href="/politica-privacidade" class="text-decoration-none">' . __('auth.privacy', 'Política de Privacidade') . '</a>';
                                    echo __('auth.legal_consent', 'Li e aceito os {terms} e {privacy}', ['terms' => $termsLink, 'privacy' => $privacyLink]);
                                ?>
                            </label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                <i class="fas fa-sign-in-alt me-2"></i> <?= __('auth.login', 'Entrar') ?>
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <p class="mb-0">
                                <?= __('auth.no_account', 'Não tem uma conta?') ?>
                                <a href="/register" class="text-decoration-none"><?= __('auth.sign_up', 'Cadastre-se') ?></a>
                            </p>
                            <small class="text-muted">
                                <a href="/recuperar-senha" class="text-decoration-none"><?= __('auth.forgot_password', 'Esqueceu a senha?') ?></a>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Login Admin -->
            <div class="card shadow-lg mt-4">
                <div class="card-body p-4">
                    <div class="text-center">
                        <h5 class="mb-3"><?= __('auth.admin_access', 'Acesso Administrativo') ?></h5>
                        <p class="text-muted small"><?= __('auth.admin_access_subtitle', 'Acesso restrito para administradores') ?></p>
                        <button class="btn btn-outline-secondary btn-sm" onclick="toggleAdminLogin()">
                            <i class="fas fa-user-shield me-2"></i> <?= __('auth.admin_login', 'Login Admin') ?>
                        </button>
                    </div>
                    
                    <div id="adminLoginForm" style="display: none;" class="mt-3">
                        <form method="POST" action="/login">
                            <input type="hidden" name="admin_login" value="1">
                            <div class="mb-3">
                                <label class="form-label"><?= __('auth.admin_email', 'E-mail Admin') ?></label>
                                <input type="email" class="form-control" name="email" 
                                       value="" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __('auth.password', 'Senha') ?></label>
                                <input type="password" class="form-control" name="senha" 
                                       placeholder="••••••••" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-dark">
                                    <i class="fas fa-shield-alt me-2"></i> <?= __('auth.login_as_admin', 'Entrar como Admin') ?>
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
    const I18N = {
        logging_in: <?= json_encode(__('auth.logging_in', 'Entrando...'), JSON_UNESCAPED_UNICODE) ?>,
        error_login: <?= json_encode(__('auth.error_login', 'Erro ao fazer login'), JSON_UNESCAPED_UNICODE) ?>,
        error_connection: <?= json_encode(__('auth.error_connection', 'Erro de conexão. Tente novamente.'), JSON_UNESCAPED_UNICODE) ?>
    };

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
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> ' + I18N.logging_in);
        
        $.ajax({
            url: '/login',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect || '/minha-conta';
                } else {
                    showAlert('danger', response.error || I18N.error_login);
                }
            },
            error: function() {
                showAlert('danger', I18N.error_connection);
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
    }, 15000);
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
