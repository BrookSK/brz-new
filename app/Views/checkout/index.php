<?php ob_start(); ?>
<div class="container-fluid px-0">
    <div class="row g-0">
        <!-- Formulário Principal -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form id="checkout-form">
                        <!-- Campo oculto para moeda -->
                        <input type="hidden" name="moeda" id="moeda_hidden" value="BRL">
                        
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
                                        <small class="item-price">$ <?= number_format($item['subtotal'], 2, '.', ',') ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <hr>

                            <!-- Informações de Pagamento -->
                            <div class="mb-3">
                                <h6><i class="fas fa-credit-card"></i> Informações de Pagamento</h6>
                                <div class="border rounded p-3 bg-light">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label">Forma de Pagamento</label>
                                            <select name="forma_pagamento" class="form-select" id="forma_pagamento" required onchange="atualizarFormaPagamento()">
                                                <option value="">Selecione...</option>
                                                <option value="cartao_credito">Cartão de Crédito</option>
                                                <option value="boleto">Boleto Bancário</option>
                                                <option value="pix">PIX</option>
                                                <option value="transferencia">Transferência Bancária</option>
                                                <option value="pagamento_entrega">Pagamento na Entrega</option>
                                            </select>
                                        </div>
                                        <div class="col-12" id="campos-cartao" style="display: none;">
                                            <label class="form-label">Número do Cartão</label>
                                            <input type="text" name="cartao_numero" class="form-control" placeholder="0000 0000 0000 0000">
                                            <div class="row g-2 mt-2">
                                                <div class="col-6">
                                                    <label class="form-label">Nome no Cartão</label>
                                                    <input type="text" name="cartao_nome" class="form-control" placeholder="Nome como está no cartão">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label">Validade</label>
                                                    <input type="text" name="cartao_validade" class="form-control" placeholder="MM/AA">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12" id="campos-boleto" style="display: none;">
                                            <label class="form-label">CPF/CNPJ do Titular</label>
                                            <input type="text" name="boleto_cpf" class="form-control" placeholder="000.000.000-00">
                                        </div>
                                        <div class="col-12" id="campos-pix" style="display: none;">
                                            <label class="form-label">Chave PIX (opcional)</label>
                                            <input type="text" name="pix_chave" class="form-control" placeholder="Chave aleatória">
                                            <div class="form-text">Deixe em branco para gerar automaticamente</div>
                                        </div>
                                        <div class="col-12" id="campos-transferencia" style="display: none;">
                                            <label class="form-label">Banco</label>
                                            <select name="banco" class="form-select">
                                                <option value="">Selecione...</option>
                                                <option value="001">Banco do Brasil</option>
                                                <option value="104">Caixa Econômica Federal</option>
                                                <option value="237">Banco Bradesco</option>
                                                <option value="341">Itaú</option>
                                                <option value="033">Santander</option>
                                            </select>
                                        </div>
                                        <div class="col-12" id="campos-pagamento-entrega" style="display: none;">
                                            <label class="form-label">Forma de Pagamento na Entrega</label>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i>
                                                <strong>Pagamento na entrega:</strong> 
                                                Você pode pagar com dinheiro, cartão ou maquininha na entrega.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Valores -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal Produtos:</span>
                                    <span id="subtotal">$ <?= number_format($subtotal, 2, '.', ',') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Frete:</span>
                                    <span id="frete">$ <?= number_format($peso_total * 15, 2, '.', ',') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Taxa de Serviço:</span>
                                    <span id="taxa-servico">$ <?= number_format($peso_total * 39, 2, '.', ',') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Impostos:</span>
                                    <span id="impostos">$ <?= number_format($subtotal * 0.80, 2, '.', ',') ?></span>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between mb-3">
                                <h6>Total:</h6>
                                <h6 class="text-primary" id="total">$ <?= number_format($subtotal + ($peso_total * 15) + ($peso_total * 39) + ($subtotal * 0.80), 2, '.', ',') ?></h6>
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
// Usar taxas de conversão globais se existirem, senão definir locais
if (typeof exchangeRates === 'undefined') {
    window.exchangeRates = {
        'BRL': 5.50,
        'USD': 1.00
    };
}

// Função para atualizar valores com base na moeda
function updatePrices(currency) {
    const currencySymbol = currency === 'BRL' ? 'R$' : '$';
    const rate = window.exchangeRates[currency];
    
    console.log('Convertendo para:', currency, 'Taxa:', rate); // Debug
    
    // Valores originais em USD (fixos)
    const originalValues = {
        subtotal: <?= $subtotal ?>,
        frete: <?= $peso_total * 15 ?>,
        taxaServico: <?= $peso_total * 39 ?>,
        impostos: <?= $subtotal * 0.80 ?>
    };
    
    // Calcular valores convertidos dos originais
    const convertedValues = {
        subtotal: originalValues.subtotal * rate,
        frete: originalValues.frete * rate,
        taxaServico: originalValues.taxaServico * rate,
        impostos: originalValues.impostos * rate,
        total: (originalValues.subtotal + originalValues.frete + originalValues.taxaServico + originalValues.impostos) * rate
    };
    
    console.log('Valores originais:', originalValues); // Debug
    console.log('Valores convertidos:', convertedValues); // Debug
    
    // Atualizar valores do resumo com os valores convertidos
    const subtotalElement = document.getElementById('subtotal');
    const freteElement = document.getElementById('frete');
    const taxaServicoElement = document.getElementById('taxa-servico');
    const impostosElement = document.getElementById('impostos');
    const totalElement = document.getElementById('total');
    
    if (subtotalElement) {
        subtotalElement.textContent = currencySymbol + ' ' + convertedValues.subtotal.toFixed(2).replace('.', ',');
    }
    
    if (freteElement) {
        freteElement.textContent = currencySymbol + ' ' + convertedValues.frete.toFixed(2).replace('.', ',');
    }
    
    if (taxaServicoElement) {
        taxaServicoElement.textContent = currencySymbol + ' ' + convertedValues.taxaServico.toFixed(2).replace('.', ',');
    }
    
    if (impostosElement) {
        impostosElement.textContent = currencySymbol + ' ' + convertedValues.impostos.toFixed(2).replace('.', ',');
    }
    
    if (totalElement) {
        totalElement.textContent = currencySymbol + ' ' + convertedValues.total.toFixed(2).replace('.', ',');
    }
    
    // Atualizar itens com valores originais
    const originalItems = <?php echo json_encode($items); ?>;
    const itemPrices = document.querySelectorAll('.item-price');
    itemPrices.forEach(function(element, index) {
        if (originalItems[index]) {
            const originalValue = originalItems[index]['subtotal'];
            const convertedValue = originalValue * rate;
            element.textContent = currencySymbol + ' ' + convertedValue.toFixed(2).replace('.', ',');
            console.log('Item', index, 'convertido de', originalValue, 'para', convertedValue); // Debug
        }
    });
    
    console.log('Conversão concluída para:', currency); // Debug
}

// Inicializar com a moeda do header
function initCurrency() {
    var headerCurrency = document.getElementById('current-currency');
    var currentCurrency = headerCurrency ? headerCurrency.textContent : 'BRL';
    
    console.log('Header currency encontrado:', headerCurrency ? 'sim' : 'não'); // Debug
    console.log('Moeda inicial:', currentCurrency); // Debug
    
    // Atualizar campo oculto
    var hiddenField = document.getElementById('moeda_hidden');
    if (hiddenField) {
        hiddenField.value = currentCurrency;
        console.log('Campo oculto atualizado para:', currentCurrency); // Debug
    }
    
    // Atualizar preços
    updatePrices(currentCurrency);
}

// Função global para atualizar moeda no checkout
window.updateCheckoutCurrency = function(currency) {
    console.log('Atualizando checkout para:', currency); // Debug
    
    // Atualizar campo oculto
    var hiddenField = document.getElementById('moeda_hidden');
    if (hiddenField) {
        hiddenField.value = currency;
        console.log('Campo oculto atualizado para:', currency); // Debug
    }
    
    // Atualizar preços
    updatePrices(currency);
};

// Inicializar
initCurrency();

// Verificar mudanças na moeda do header a cada 200ms (mais rápido)
setInterval(function() {
    var headerCurrency = document.getElementById('current-currency');
    if (headerCurrency) {
        var newCurrency = headerCurrency.textContent;
        var currentHiddenCurrency = document.getElementById('moeda_hidden').value;
        
        if (newCurrency !== currentHiddenCurrency) {
            console.log('Moeda mudou de', currentHiddenCurrency, 'para', newCurrency); // Debug
            document.getElementById('moeda_hidden').value = newCurrency;
            
            // Usar função global do header se existir
            if (typeof updateAllPrices === 'function') {
                updateAllPrices();
            } else {
                updatePrices(newCurrency);
            }
        }
    }
}, 200);

// Também verificar mudanças no localStorage
setInterval(function() {
    var storedCurrency = localStorage.getItem('selected_currency');
    var currentHiddenCurrency = document.getElementById('moeda_hidden').value;
    
    if (storedCurrency && storedCurrency !== currentHiddenCurrency) {
        console.log('Moeda mudou no localStorage de', currentHiddenCurrency, 'para', storedCurrency); // Debug
        document.getElementById('moeda_hidden').value = storedCurrency;
        
        // Usar função global do header se existir
        if (typeof updateAllPrices === 'function') {
            updateAllPrices();
        } else {
            updatePrices(storedCurrency);
        }
    }
}, 200);

// Função simples para o botão
function toggleButton() {
    var checkbox = document.getElementById('consentimento_legal');
    var botao = document.getElementById('btn-finalizar');
    
    if (checkbox && botao) {
        botao.disabled = !checkbox.checked;
    }
}

// Função para atualizar campos de pagamento
function atualizarFormaPagamento() {
    const formaPagamento = document.getElementById('forma_pagamento').value;
    
    // Esconder todos os campos específicos
    document.getElementById('campos-cartao').style.display = 'none';
    document.getElementById('campos-boleto').style.display = 'none';
    document.getElementById('campos-pix').style.display = 'none';
    document.getElementById('campos-transferencia').style.display = 'none';
    document.getElementById('campos-pagamento-entrega').style.display = 'none';
    
    // Mostrar campos específicos conforme a forma de pagamento
    switch(formaPagamento) {
        case 'cartao_credito':
            document.getElementById('campos-cartao').style.display = 'block';
            break;
        case 'boleto':
            document.getElementById('campos-boleto').style.display = 'block';
            break;
        case 'pix':
            document.getElementById('campos-pix').style.display = 'block';
            break;
        case 'transferencia':
            document.getElementById('campos-transferencia').style.display = 'block';
            break;
        case 'pagamento_entrega':
            document.getElementById('campos-pagamento-entrega').style.display = 'block';
            break;
    }
    
    // Atualizar texto do botão conforme a forma de pagamento
    const botaoFinalizar = document.getElementById('btn-finalizar');
    if (botaoFinalizar) {
        switch(formaPagamento) {
            case 'cartao_credito':
                botaoFinalizar.innerHTML = '<i class="fas fa-credit-card"></i> Finalizar com Cartão de Crédito';
                break;
            case 'boleto':
                botaoFinalizar.innerHTML = '<i class="fas fa-barcode"></i> Gerar Boleto';
                break;
            case 'pix':
                botaoFinalizar.innerHTML = '<i class="fas fa-qrcode"></i> Gerar PIX';
                break;
            case 'transferencia':
                botaoFinalizar.innerHTML = '<i class="fas fa-university"></i> Finalizar com Transferência';
                break;
            case 'pagamento_entrega':
                botaoFinalizar.innerHTML = '<i class="fas fa-truck"></i> Finalizar para Pagamento na Entrega';
                break;
            default:
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
        }
    }
}
    
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
