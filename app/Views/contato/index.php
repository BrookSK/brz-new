<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">Entre em Contato</h1>
                <p class="lead text-muted">Estamos aqui para ajudar. Fale conosco!</p>
            </div>
            
            <div class="row">
                <!-- Formulário de Contato -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST" action="/contato" id="contactForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nome" class="form-label">Nome Completo</label>
                                        <input type="text" class="form-control" id="nome" name="nome" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">E-mail</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="telefone" class="form-label">Telefone</label>
                                        <input type="tel" class="form-control" id="telefone" name="telefone">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="assunto" class="form-label">Assunto</label>
                                        <select class="form-select" id="assunto" name="assunto" required>
                                            <option value="">Selecione...</option>
                                            <option value="duvida">Dúvida</option>
                                            <option value="suporte">Suporte Técnico</option>
                                            <option value="pedido">Sobre Pedido</option>
                                            <option value="parceria">Parceria</option>
                                            <option value="outro">Outro</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mensagem" class="form-label">Mensagem</label>
                                    <textarea class="form-control" id="mensagem" name="mensagem" rows="5" required></textarea>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" id="contactBtn">
                                        <i class="fas fa-paper-plane me-2"></i> Enviar Mensagem
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Informações de Contato -->
                <div class="col-lg-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-map-marker-alt text-primary"></i> Endereço</h5>
                            <p class="text-muted">
                                Av. Paulista, 1000<br>
                                Bela Vista, São Paulo - SP<br>
                                CEP: 01310-100
                            </p>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-phone text-success"></i> Telefones</h5>
                            <p class="text-muted">
                                <strong>Comercial:</strong> (11) 9999-8888<br>
                                <strong>Suporte:</strong> (11) 9999-7777<br>
                                <strong>WhatsApp:</strong> (11) 9999-6666
                            </p>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-envelope text-warning"></i> E-mails</h5>
                            <p class="text-muted">
                                <strong>Comercial:</strong> comercial@brzlogistics.com<br>
                                <strong>Suporte:</strong> suporte@brzlogistics.com<br>
                                <strong>Financeiro:</strong> financeiro@brzlogistics.com
                            </p>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-clock text-info"></i> Horário de Atendimento</h5>
                            <p class="text-muted">
                                <strong>Segunda a Sexta:</strong> 9h às 18h<br>
                                <strong>Sábado:</strong> 9h às 13h<br>
                                <strong>Domingo:</strong> Fechado
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Redes Sociais -->
            <div class="text-center mt-5">
                <h4 class="mb-3">Siga-nos nas Redes Sociais</h4>
                <div class="d-flex justify-content-center gap-3">
                    <a href="#" class="btn btn-outline-primary btn-lg">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="btn btn-outline-info btn-lg">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="btn btn-outline-danger btn-lg">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="btn btn-outline-dark btn-lg">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="#" class="btn btn-outline-success btn-lg">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#contactForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#contactBtn');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Enviando...');
        
        $.ajax({
            url: '/contato',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'Mensagem enviada com sucesso! Entraremos em contato em breve.');
                    $('#contactForm')[0].reset();
                } else {
                    showAlert('danger', response.error || 'Erro ao enviar mensagem');
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
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
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

.btn-outline-primary:hover,
.btn-outline-info:hover,
.btn-outline-danger:hover,
.btn-outline-dark:hover,
.btn-outline-success:hover {
    transform: translateY(-2px);
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
