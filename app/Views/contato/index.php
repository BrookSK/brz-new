<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">Entre em Contato</h1>
                <p class="lead text-muted">Estamos aqui para ajudar. Fale conosco!</p>
            </div>
            
            <div class="row g-3 mb-4 contact-info-cards">
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-map-marker-alt text-primary"></i> Endereço</h5>
                            <p class="text-muted mb-0">
                                Estados Unidos - Carolina do Norte
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fab fa-whatsapp text-success"></i> WhatsApp</h5>
                            <div class="text-muted contact-lines">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <strong class="contact-label">Suporte comercial:</strong>
                                    <a class="contact-wa-link" href="https://wa.me/5517991098286" target="_blank" rel="noopener">+55 17 99109-8286</a>
                                </div>
                                <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                                    <strong class="contact-label">Vendas:</strong>
                                    <a class="contact-wa-link" href="https://wa.me/5517996203062" target="_blank" rel="noopener">+55 17 99620-3062</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-envelope text-warning"></i> E-mail comercial</h5>
                            <p class="text-muted mb-0">
                                <a class="contact-email-link" href="mailto:contato@brazilianashop.com.br">contato@brazilianashop.com.br</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

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
    transition: none;
}

.card:hover {
    transform: none;
}

.btn-primary {
    transition: none;
}

.btn-primary:hover {
    transform: none;
    box-shadow: none;
}

.contact-info-cards .card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.contact-info-cards .card-title i {
    font-size: 1.15rem;
}

.contact-info-cards .card-body {
    padding: 18px;
}

.contact-lines {
    font-size: 0.98rem;
}

.contact-label {
    font-weight: 600;
    color: rgba(15, 23, 42, 0.78);
}

.contact-wa-link {
    color: rgba(15, 23, 42, 0.86);
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.contact-wa-link:hover {
    text-decoration: underline;
}

.contact-email-link {
    color: inherit;
    text-decoration: none;
    font-weight: 600;
    word-break: break-word;
}

.contact-email-link:hover {
    text-decoration: underline;
}

@media (max-width: 575.98px) {
    .contact-lines .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
}

</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
