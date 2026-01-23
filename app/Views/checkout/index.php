<?php ob_start(); ?>
<div class="container-fluid px-0">
    <div class="row g-0">
        <!-- Formulário Principal -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form id="checkout-form">
                        <!-- Dados Pessoais -->
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-user"></i> Dados Pessoais</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nome Completo *</label>
                                    <input type="text" class="form-control" name="nome" required 
                                           value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">E-mail *</label>
                                    <input type="email" class="form-control" name="email" required 
                                           value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CPF/CNPJ *</label>
                                    <input type="text" class="form-control" name="documento" required 
                                           value="<?= htmlspecialchars($usuario['documento'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Telefone com WhatsApp *</label>
                                    <input type="tel" class="form-control" name="telefone" required 
                                           value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Endereço de Entrega -->
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-map-marker-alt"></i> Endereço de Entrega</h6>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">CEP *</label>
                                    <input type="text" class="form-control" name="cep" required 
                                           id="cep" maxlength="9">
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label class="form-label">Endereço *</label>
                                    <input type="text" class="form-control" name="endereco" required id="endereco">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Número *</label>
                                    <input type="text" class="form-control" name="numero" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Complemento</label>
                                    <input type="text" class="form-control" name="complemento">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Bairro *</label>
                                    <input type="text" class="form-control" name="bairro" required id="bairro">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Cidade *</label>
                                    <input type="text" class="form-control" name="cidade" required id="cidade">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Estado *</label>
                                    <select class="form-select" name="estado" required id="estado">
                                        <option value="">Selecione...</option>
                                        <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                            <option value="<?= $uf ?>"><?= $uf ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Dados de Pagamento -->
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-credit-card"></i> Dados de Pagamento</h6>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Método de Pagamento *</label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="CREDIT_CARD">Cartão de Crédito</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Dados do Cartão -->
                            <div id="cartao-dados" class="mt-3">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Nome no Cartão *</label>
                                        <input type="text" class="form-control" name="card_holder_name" required>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Número do Cartão *</label>
                                        <input type="text" class="form-control" name="card_number" required 
                                               id="card-number" maxlength="19">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Validade *</label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <select class="form-select" name="card_expiry_month" required>
                                                    <option value="">Mês</option>
                                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>"><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <select class="form-select" name="card_expiry_year" required>
                                                    <option value="">Ano</option>
                                                    <?php for ($y = date('Y'); $y <= date('Y') + 10; $y++): ?>
                                                        <option value="<?= $y ?>"><?= $y ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">CVV *</label>
                                        <input type="text" class="form-control" name="card_cvv" required 
                                               maxlength="4">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Senha (se não logado) -->
                        <?php if (empty($usuario)): ?>
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-lock"></i> Criar Conta</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Senha *</label>
                                    <input type="password" class="form-control" name="senha" required minlength="6">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmar Senha *</label>
                                    <input type="password" class="form-control" name="senha_confirmacao" required>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Resumo do Pedido (Fixo) -->
        <div class="col-lg-4">
            <div class="checkout-sticky">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-receipt"></i> Resumo do Pedido</h6>
                    </div>
                    <div class="card-body">
                        <div id="resumo-pedido">
                            <!-- Itens do Carrinho -->
                            <div class="mb-3">
                                <h6>Itens do Pedido</h6>
                                <div id="items-resumo">
                                    <?php foreach ($items as $item): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <small><?= htmlspecialchars($item['nome']) ?> (<?= $item['quantidade'] ?>x)</small>
                                        <small>USD <?= number_format($item['subtotal'], 2, ',', '.') ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <hr>

                            <!-- Valores -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal Produtos:</span>
                                    <span id="subtotal">USD <?= number_format($subtotal, 2, ',', '.') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Frete:</span>
                                    <span id="frete">USD <?= number_format($peso_total * 15, 2, ',', '.') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Taxa de Serviço:</span>
                                    <span id="taxa-servico">USD <?= number_format($peso_total * 39, 2, ',', '.') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Impostos:</span>
                                    <span id="impostos">USD <?= number_format($subtotal * 0.80, 2, ',', '.') ?></span>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between mb-3">
                                <h6>Total:</h6>
                                <h6 class="text-primary" id="total">USD <?= number_format($subtotal + ($peso_total * 15) + ($peso_total * 39) + ($subtotal * 0.80), 2, ',', '.') ?></h6>
                            </div>

                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Peso Total:</strong> <?= number_format($peso_total, 3, ',', '.') ?> kg
                            </div>

                            <!-- Termos Legais -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consentimento_legal" id="consentimento_legal" required onchange="toggleButton()">
                                    <label class="form-check-label small" for="consentimento_legal">
                                        Li e aceito os <a href="#" data-bs-toggle="modal" data-bs-target="#termosModal">termos e condições</a> de uso e política de privacidade. *
                                    </label>
                                </div>
                            </div>

                            <!-- Botão Finalizar -->
                            <button type="submit" class="btn btn-secondary btn-lg w-100" id="btn-finalizar" disabled>
                                <i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Selos de Segurança -->
                <div class="text-center mt-3">
                    <div class="d-flex justify-content-center gap-3">
                        <i class="fas fa-lock fa-2x text-success"></i>
                        <i class="fas fa-shield-alt fa-2x text-primary"></i>
                        <i class="fab fa-cc-visa fa-2x text-info"></i>
                        <i class="fab fa-cc-mastercard fa-2x text-warning"></i>
                    </div>
                    <small class="text-muted d-block mt-2">Pagamento 100% seguro</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Termos -->
<div class="modal fade" id="termosModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Termos e Condições</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Aceitação dos Termos</h6>
                <p>Ao utilizar nossos serviços, você concorda com estes termos e condições.</p>
                
                <h6>2. Produtos e Serviços</h6>
                <p>Oferecemos produtos internacionais com serviço completo de importação.</p>
                
                <h6>3. Pagamentos</h6>
                <p>O pagamento é processado 100% no checkout através de gateways seguros.</p>
                
                <h6>4. Importação e Impostos</h6>
                <p>Todos os impostos são calculados e cobrados no momento da compra.</p>
                
                <h6>5. Entrega</h6>
                <p>O prazo de entrega estimado é de até 30 dias após a aprovação do pagamento.</p>
                
                <h6>6. Privacidade</h6>
                <p>Seus dados são protegidos conforme nossa política de privacidade.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Taxa de conversão (1 USD = 5.50 BRL)
    const exchangeRates = {
        'BRL': 5.50,
        'USD': 1.00
    };
    
    // Valores originais em USD
    const originalValues = {
        subtotal: <?= $subtotal ?>,
        pesoTotal: <?= $peso_total ?>
    };
    
    // Função para atualizar valores com base na moeda
    function updatePrices(currency) {
        const currencySymbol = currency === 'BRL' ? 'R$' : '$';
        const rate = exchangeRates[currency];
        
        // Calcular valores convertidos
        const subtotal = originalValues.subtotal * rate;
        const frete = (originalValues.pesoTotal * 15) * rate;
        const taxaServico = (originalValues.pesoTotal * 39) * rate;
        const impostos = (originalValues.subtotal * 0.80) * rate;
        const total = subtotal + frete + taxaServico + impostos;
        
        // Atualizar valores no resumo
        $('#subtotal').text(currencySymbol + ' ' + subtotal.toFixed(2).replace('.', ','));
        $('#frete').text(currencySymbol + ' ' + frete.toFixed(2).replace('.', ','));
        $('#taxa-servico').text(currencySymbol + ' ' + taxaServico.toFixed(2).replace('.', ','));
        $('#impostos').text(currencySymbol + ' ' + impostos.toFixed(2).replace('.', ','));
        $('#total').text(currencySymbol + ' ' + total.toFixed(2).replace('.', ','));
        
        // Atualizar itens
        $('#items-resumo small:last-child').each(function() {
            const originalValue = parseFloat($(this).text().replace(/[^\d.]/g, ''));
            if (!isNaN(originalValue)) {
                const convertedValue = originalValue * rate;
                $(this).text(currencySymbol + ' ' + convertedValue.toFixed(2).replace('.', ','));
            }
        });
    }
    
    // Inicializar com a moeda do header
    var currentCurrency = document.getElementById('current-currency') ? 
                         document.getElementById('current-currency').textContent : 'BRL';
    updatePrices(currentCurrency);
    
    // Adicionar campo oculto para enviar a moeda no formulário
    $('<input>').attr({
        type: 'hidden',
        name: 'moeda',
        id: 'moeda_hidden',
        value: currentCurrency
    }).appendTo('#checkout-form');
    
    // Atualizar moeda oculta quando mudar no header
    function updateHiddenCurrency() {
        var headerCurrency = document.getElementById('current-currency');
        if (headerCurrency) {
            var currency = headerCurrency.textContent;
            $('#moeda_hidden').val(currency);
            updatePrices(currency);
        }
    }
    
    // Verificar mudanças na moeda do header
    setInterval(updateHiddenCurrency, 1000);
    
    // Máscara para CEP
    $('#cep').mask('00000-000');
    
    // Máscara para CPF/CNPJ
    $('input[name="documento"]').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length <= 11) {
            $(this).mask('000.000.000-00');
        } else {
            $(this).mask('00.000.000/0000-00');
        }
    });
    
    // Máscara para telefone
    $('input[name="telefone"]').mask('(00) 00000-0000');
    
    // Máscara para cartão
    $('#card-number').mask('0000 0000 0000 0000');
    
    // Buscar CEP
    $('#cep').on('blur', function() {
        var cep = $(this).val().replace(/\D/g, '');
        if (cep.length === 8) {
            $.getJSON('https://viacep.com.br/ws/' + cep + '/json/', function(data) {
                if (!data.erro) {
                    $('#endereco').val(data.logradouro);
                    $('#bairro').val(data.bairro);
                    $('#cidade').val(data.localidade);
                    $('#estado').val(data.uf);
                }
            });
        }
    });
    
    // Processar checkout
    $('#checkout-form').on('submit', function(e) {
        e.preventDefault();
        
        var btn = $('#btn-finalizar');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processando...');
        
        $.ajax({
            url: '/checkout/processar',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    setTimeout(function() {
                        window.location.href = response.redirect;
                    }, 2000);
                } else {
                    showAlert('danger', response.error);
                }
            },
            error: function() {
                showAlert('danger', 'Erro ao processar pedido. Tente novamente.');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro');
            }
        });
    });
    
    function showAlert(type, message) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                       message +
                       '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                       '</div>';
        
        $('main').prepend(alertHtml);
        
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
});

// Função simples para o botão
function toggleButton() {
    var checkbox = document.getElementById('consentimento_legal');
    var botao = document.getElementById('btn-finalizar');
    
    if (checkbox.checked) {
        botao.disabled = false;
        botao.className = 'btn btn-primary btn-lg w-100';
    } else {
        botao.disabled = true;
        botao.className = 'btn btn-secondary btn-lg w-100';
    }
}
</script>

<style>
.sticky-top {
    position: -webkit-sticky;
    position: sticky;
}

/* Garantir que o resumo não sobreponha o header */
@media (min-width: 992px) {
    .sticky-top {
        top: 100px !important;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }
}

@media (max-width: 768px) {
    .container-fluid {
        padding: 0;
    }
    
    .card {
        border-radius: 0;
    }
    
    .sticky-top {
        top: 0 !important;
        position: relative !important;
    }
}

/* Evitar sobreposição com elementos fixos */
#resumo-pedido {
    z-index: 10;
    position: relative;
}

/* Garantir que o header fique acima do conteúdo */
header {
    z-index: 1000;
    position: relative;
}

/* Ajustar para não interferir com o seletor de moeda */
.currency-selector {
    z-index: 1001;
}

/* Ajuste específico para o header fixo */
.navbar {
    z-index: 1030 !important;
}

/* Garantir que o conteúdo principal fique abaixo do header */
main {
    margin-top: 0 !important;
    padding-top: 20px !important;
}

/* Container do checkout com espaçamento correto */
.container-fluid {
    padding-top: 20px;
    margin: 0 !important;
}

/* Ajustar o sticky-top do checkout para considerar o header fixo */
.checkout-sticky {
    position: sticky;
    top: 90px; /* Ajustado para header fixo */
    z-index: 10;
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
