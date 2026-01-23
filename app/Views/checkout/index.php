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
                                            <script>
                                            // Adicionar listener para debug
                                            document.getElementById('forma_pagamento').addEventListener('change', function() {
                                                console.log('🔍 [DEBUG] Forma de pagamento alterada para:', this.value);
                                                console.log('🔍 [DEBUG] Chamando atualizarFormaPagamento()');
                                                atualizarFormaPagamento();
                                            });
                                            
                                            // Verificar se já há uma forma de pagamento selecionada ao carregar
                                            document.addEventListener('DOMContentLoaded', function() {
                                                const formaPagamentoSelect = document.getElementById('forma_pagamento');
                                                if (formaPagamentoSelect && formaPagamentoSelect.value) {
                                                    console.log('🔍 [INIT] Forma de pagamento já selecionada:', formaPagamentoSelect.value);
                                                    atualizarFormaPagamento();
                                                }
                                            });
                                            
                                            // Fallback para garantir que a função esteja disponível
                                            setTimeout(function() {
                                                if (typeof atualizarFormaPagamento === 'function') {
                                                    console.log('🔍 [VERIFY] Função atualizarFormaPagamento está disponível');
                                                } else {
                                                    console.error('❌ [ERROR] Função atualizarFormaPagamento não está disponível');
                                                }
                                            }, 100);
                                            </script>
                                        </div>
                                        <div class="col-12" id="campos-cartao" style="display: none;">
                                            <label class="form-label">Nome no Cartão</label>
                                            <input type="text" name="card_holder_name" class="form-control" placeholder="Nome como está no cartão" required>
                                            <div class="row g-2 mt-2">
                                                <div class="col-6">
                                                    <label class="form-label">Número do Cartão</label>
                                                    <input type="text" name="card_number" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19" required>
                                                </div>
                                                <div class="col-3">
                                                    <label class="form-label">Validade</label>
                                                    <select name="card_expiry_month" class="form-select" required>
                                                        <option value="">Mês</option>
                                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                                            <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>"><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-3">
                                                    <label class="form-label">&nbsp;</label>
                                                    <select name="card_expiry_year" class="form-select" required>
                                                        <option value="">Ano</option>
                                                        <?php for ($y = date('Y'); $y <= date('Y') + 10; $y++): ?>
                                                            <option value="<?= $y ?>"><?= $y ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row g-2 mt-2">
                                                <div class="col-6">
                                                    <label class="form-label">CVV</label>
                                                    <input type="text" name="card_cvv" class="form-control" placeholder="123" maxlength="4" required>
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
                                    <span id="subtotal" class="cart-currency" data-original-value="<?= $subtotal ?>"><?= number_format($subtotal, 2, '.', ',') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Frete:</span>
                                    <span id="frete" class="cart-currency" data-original-value="<?= $peso_total * 15 ?>"><?= number_format($peso_total * 15, 2, '.', ',') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Taxa de Serviço:</span>
                                    <span id="taxa-servico" class="cart-currency" data-original-value="<?= $peso_total * 39 ?>"><?= number_format($peso_total * 39, 2, '.', ',') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Impostos:</span>
                                    <span id="impostos" class="cart-currency" data-original-value="<?= $subtotal * 0.80 ?>"><?= number_format($subtotal * 0.80, 2, '.', ',') ?></span>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between mb-3">
                                <h6>Total:</h6>
                                <h6 class="text-primary" id="total" class="cart-currency" data-original-value="<?= $subtotal + ($peso_total * 15) + ($peso_total * 39) + ($subtotal * 0.80) ?>"><?= number_format($subtotal + ($peso_total * 15) + ($peso_total * 39) + ($subtotal * 0.80), 2, '.', ',') ?></h6>
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
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript para processar o formulário -->
<script>
document.getElementById('checkout-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    console.log('🔍 [FORM] Formulário submetido');
    
    const botao = document.getElementById('btn-finalizar');
    const formData = new FormData(this);
    
    // Debug: Mostrar todos os dados do formulário
    console.log('🔍 [FORM] Dados do formulário:');
    for (let [key, value] of formData.entries()) {
        console.log(`🔍 [FORM] ${key}: ${value}`);
    }
    
    // Debug: Verificar se há carrinho na sessão
    console.log('🔍 [FORM] Verificando carrinho...');
    
    // Desabilitar botão e mostrar loading
    botao.disabled = true;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    
    // Enviar requisição AJAX
    console.log('🔍 [AJAX] Enviando requisição para /checkout/processar');
    
    fetch('/checkout/processar', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('🔍 [AJAX] Resposta recebida:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('🔍 [AJAX] Dados recebidos:', data);
        
        if (data.success) {
            console.log('✅ [PEDIDO] Pedido criado com sucesso:', data.pedido_id);
            
            // Mostrar mensagem de sucesso
            Swal.fire({
                icon: 'success',
                title: 'Pedido Confirmado!',
                text: 'Seu pedido #' + data.pedido_id + ' foi processado com sucesso.',
                showConfirmButton: false,
                timer: 2000
            });
            
            // Redirecionar para página de conclusão
            setTimeout(function() {
                console.log('🔍 [REDIRECT] Redirecionando para:', data.redirect || '/checkout/conclusao/' + data.pedido_id);
                window.location.href = data.redirect || '/checkout/conclusao/' + data.pedido_id;
            }, 2000);
        } else {
            console.error('❌ [PEDIDO] Erro ao processar pedido:', data.error);
            
            // Mostrar mensagem de erro
            Swal.fire({
                icon: 'error',
                title: 'Erro no Processamento',
                text: data.error || 'Ocorreu um erro ao processar seu pedido. Tente novamente.',
                confirmButtonText: 'OK'
            });
            
            // Restaurar botão
            botao.disabled = false;
            botao.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
        }
    })
    .catch(error => {
        console.error('❌ [PEDIDO] Erro na requisição:', error);
        console.error('❌ [PEDIDO] Stack:', error.stack);
        
        // Mostrar mensagem de erro genérico
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Ocorreu um erro ao processar seu pedido. Verifique sua conexão e tente novamente.',
            confirmButtonText: 'OK'
        });
        
        // Restaurar botão
        botao.disabled = false;
        botao.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
    });
});

// Debug: Verificar se o formulário existe
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 [DEBUG] DOMContentLoaded - Verificando formulário');
    
    const form = document.getElementById('checkout-form');
    const botao = document.getElementById('btn-finalizar');
    
    console.log('🔍 [DEBUG] Formulário encontrado:', !!form);
    console.log('🔍 [DEBUG] Botão encontrado:', !!botao);
    
    if (form) {
        console.log('🔍 [DEBUG] Formulário OK');
    } else {
        console.error('❌ [DEBUG] Formulário não encontrado!');
    }
    
    if (botao) {
        console.log('🔍 [DEBUG] Botão OK - Estado:', botao.disabled);
    } else {
        console.error('❌ [DEBUG] Botão não encontrado!');
    }
});
</script>

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

<script>
// Função para atualizar campos de pagamento
function atualizarFormaPagamento() {
    console.log('🔍 [INÍCIO] atualizarFormaPagamento() chamada');
    
    const formaPagamentoElement = document.getElementById('forma_pagamento');
    console.log('🔍 [DEBUG] Elemento forma_pagamento:', !!formaPagamentoElement);
    
    if (!formaPagamentoElement) {
        console.error('❌ [ERRO] Elemento forma_pagamento não encontrado');
        return;
    }
    
    const formaPagamento = formaPagamentoElement.value;
    console.log('🔍 [DEBUG] Valor selecionado:', formaPagamento);
    
    // Verificar se os elementos dos campos existem
    const camposCartao = document.getElementById('campos-cartao');
    const camposBoleto = document.getElementById('campos-boleto');
    const camposPix = document.getElementById('campos-pix');
    const camposTransferencia = document.getElementById('campos-transferencia');
    const camposPagamentoEntrega = document.getElementById('campos-pagamento-entrega');
    
    console.log('🔍 [DEBUG] Elementos dos campos:');
    console.log('🔍 [DEBUG] campos-cartao:', !!camposCartao);
    console.log('🔍 [DEBUG] campos-boleto:', !!camposBoleto);
    console.log('🔍 [DEBUG] campos-pix:', !!camposPix);
    console.log('🔍 [DEBUG] campos-transferencia:', !!camposTransferencia);
    console.log('🔍 [DEBUG] campos-pagamento-entrega:', !!camposPagamentoEntrega);
    
    // Esconder todos os campos específicos primeiro
    if (camposCartao) {
        camposCartao.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de cartão escondidos');
    }
    if (camposBoleto) {
        camposBoleto.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de boleto escondidos');
    }
    if (camposPix) {
        camposPix.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de PIX escondidos');
    }
    if (camposTransferencia) {
        camposTransferencia.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de transferência escondidos');
    }
    if (camposPagamentoEntrega) {
        camposPagamentoEntrega.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de pagamento na entrega escondidos');
    }
    
    console.log('🔍 [DEBUG] Todos os campos foram escondidos');
    
    // Mostrar campos específicos conforme a forma de pagamento
    switch(formaPagamento) {
        case 'cartao_credito':
            if (camposCartao) {
                camposCartao.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de cartão exibidos');
                
                // Garantir que os campos obrigatórios estejam visíveis
                const nomeCartao = camposCartao.querySelector('input[name="card_holder_name"]');
                const numeroCartao = camposCartao.querySelector('input[name="card_number"]');
                const cvvCartao = camposCartao.querySelector('input[name="card_cvv"]');
                
                if (nomeCartao) nomeCartao.required = true;
                if (numeroCartao) numeroCartao.required = true;
                if (cvvCartao) cvvCartao.required = true;
                
                console.log('🔍 [PAGAMENTO] Campos obrigatórios do cartão marcados');
            } else {
                console.error('❌ [ERRO] Elemento campos-cartao não encontrado');
            }
            break;
        case 'boleto':
            if (camposBoleto) {
                camposBoleto.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de boleto exibidos');
            } else {
                console.error('❌ [ERRO] Elemento campos-boleto não encontrado');
            }
            break;
        case 'pix':
            if (camposPix) {
                camposPix.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de PIX exibidos');
            } else {
                console.error('❌ [ERRO] Elemento campos-pix não encontrado');
            }
            break;
        case 'transferencia':
            if (camposTransferencia) {
                camposTransferencia.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de transferência exibidos');
            } else {
                console.error('❌ [ERRO] Elemento campos-transferencia não encontrado');
            }
            break;
        case 'pagamento_entrega':
            if (camposPagamentoEntrega) {
                camposPagamentoEntrega.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de pagamento na entrega exibidos');
            } else {
                console.error('❌ [ERRO] Elemento campos-pagamento-entrega não encontrado');
            }
            break;
        default:
            console.log('🔍 [PAGAMENTO] Nenhuma forma de pagamento selecionada');
    }
    
    // Atualizar texto do botão conforme a forma de pagamento
    const botaoFinalizar = document.getElementById('btn-finalizar');
    console.log('🔍 [DEBUG] Botão btn-finalizar:', !!botaoFinalizar);
    
    if (botaoFinalizar) {
        switch(formaPagamento) {
            case 'cartao_credito':
                botaoFinalizar.innerHTML = '<i class="fas fa-credit-card"></i> Finalizar com Cartão de Crédito';
                console.log('🔍 [BOTÃO] Texto atualizado para cartão de crédito');
                break;
            case 'boleto':
                botaoFinalizar.innerHTML = '<i class="fas fa-barcode"></i> Gerar Boleto';
                console.log('🔍 [BOTÃO] Texto atualizado para boleto');
                break;
            case 'pix':
                botaoFinalizar.innerHTML = '<i class="fas fa-qrcode"></i> Gerar PIX';
                console.log('🔍 [BOTÃO] Texto atualizado para PIX');
                break;
            case 'transferencia':
                botaoFinalizar.innerHTML = '<i class="fas fa-university"></i> Finalizar com Transferência';
                console.log('🔍 [BOTÃO] Texto atualizado para transferência');
                break;
            case 'pagamento_entrega':
                botaoFinalizar.innerHTML = '<i class="fas fa-truck"></i> Finalizar para Pagamento na Entrega';
                console.log('🔍 [BOTÃO] Texto atualizado para pagamento na entrega');
                break;
            default:
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
                console.log('🔍 [BOTÃO] Texto padrão definido');
        }
    } else {
        console.error('❌ [ERRO] Botão btn-finalizar não encontrado');
    }
    
    console.log('🔍 [FIM] atualizarFormaPagamento() concluída');
}

// Função para habilitar/desabilitar botão finalizar
function toggleButton() {
    const checkbox = document.getElementById('consentimento_legal');
    const botao = document.getElementById('btn-finalizar');
    
    console.log('🔍 [BOTÃO] toggleButton() chamada');
    console.log('🔍 [BOTÃO] Checkbox marcado:', checkbox ? checkbox.checked : 'não');
    
    if (checkbox && botao) {
        const isChecked = checkbox.checked;
        botao.disabled = !isChecked;
        
        if (isChecked) {
            botao.className = 'btn btn-primary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão habilitado');
        } else {
            botao.className = 'btn btn-secondary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão desabilitado');
        }
        
        console.log('🔍 [BOTÃO] Estado final do botão:', !botao.disabled);
    } else {
        console.error('❌ [BOTÃO] Checkbox ou botão não encontrado');
    }
}
</script>

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
    console.log('🔍 [MOEDA] updatePrices() chamada com currency:', currency);
    console.log('🔍 [MOEDA] window.exchangeRates:', window.exchangeRates);
    
    const currencySymbol = currency === 'BRL' ? 'R$' : '$';
    const rate = window.exchangeRates[currency];
    
    console.log('🔍 [MOEDA] currencySymbol:', currencySymbol);
    console.log('🔍 [MOEDA] rate:', rate);
    
    if (!rate) {
        console.error('❌ [MOEDA] Taxa de conversão não encontrada para:', currency);
        return;
    }
    
    // Valores originais em USD (fixos)
    const originalValues = {
        subtotal: <?= $subtotal ?>,
        frete: <?= $peso_total * 15 ?>,
        taxaServico: <?= $peso_total * 39 ?>,
        impostos: <?= $subtotal * 0.80 ?>
    };
    
    console.log('🔍 [MOEDA] Valores originais:', originalValues);
    
    // Calcular valores convertidos dos originais
    const convertedValues = {
        subtotal: originalValues.subtotal * rate,
        frete: originalValues.frete * rate,
        taxaServico: originalValues.taxaServico * rate,
        impostos: originalValues.impostos * rate,
        total: (originalValues.subtotal + originalValues.frete + originalValues.taxaServico + originalValues.impostos) * rate
    };
    
    console.log('🔍 [MOEDA] Valores convertidos:', convertedValues);
    
    // Atualizar elementos do resumo do pedido
    const elements = {
        subtotal: document.getElementById('subtotal'),
        frete: document.getElementById('frete'),
        taxaServico: document.getElementById('taxa-servico'),
        impostos: document.getElementById('impostos'),
        total: document.getElementById('total')
    };
    
    console.log('🔍 [MOEDA] Elementos do resumo encontrados:');
    for (const [key, element] of Object.entries(elements)) {
        console.log(`🔍 [MOEDA] ${key}:`, !!element);
    }
    
    // Atualizar cada elemento do resumo
    for (const [key, element] of Object.entries(elements)) {
        if (element) {
            const value = convertedValues[key];
            const formattedValue = currencySymbol + ' ' + value.toFixed(2).replace('.', ',');
            element.textContent = formattedValue;
            console.log(`🔍 [MOEDA] ${key} atualizado para:`, formattedValue);
        } else {
            console.error(`❌ [MOEDA] Elemento ${key} não encontrado`);
        }
    }
    
    // Atualizar elementos ocultos se existirem
    const hiddenSubtotal = document.getElementById('subtotal_hidden');
    const hiddenFrete = document.getElementById('frete_hidden');
    const hiddenTotal = document.getElementById('total_hidden');
    
    if (hiddenSubtotal) {
        hiddenSubtotal.value = convertedValues.subtotal.toFixed(2);
        console.log('🔍 [MOEDA] Campo oculto subtotal atualizado para:', convertedValues.subtotal.toFixed(2));
    }
    
    if (hiddenFrete) {
        hiddenFrete.value = convertedValues.frete.toFixed(2);
        console.log('🔍 [MOEDA] Campo oculto frete atualizado para:', convertedValues.frete.toFixed(2));
    }
    
    if (hiddenTotal) {
        hiddenTotal.value = convertedValues.total.toFixed(2);
        console.log('🔍 [MOEDA] Campo oculto total atualizado para:', convertedValues.total.toFixed(2));
    }
    
    // Atualizar elementos com classe cart-currency (itens do carrinho)
    const cartCurrencyElements = document.querySelectorAll('.cart-currency');
    console.log('🔍 [MOEDA] Elementos .cart-currency encontrados:', cartCurrencyElements.length);
    
    cartCurrencyElements.forEach((element, index) => {
        if (element) {
            const originalValue = parseFloat(element.getAttribute('data-original-value'));
            console.log(`🔍 [MOEDA] Elemento cart-currency ${index} - Valor original:`, originalValue);
            
            if (!isNaN(originalValue)) {
                const convertedPrice = originalValue * rate;
                const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
                element.textContent = formattedPrice;
                console.log(`🔍 [MOEDA] Elemento cart-currency ${index} atualizado para:`, formattedPrice);
            } else {
                console.error(`❌ [MOEDA] Elemento cart-currency ${index} - Valor original inválido:`, element.getAttribute('data-original-value'));
            }
        }
    });
    
    // Atualizar botão finalizar
    const botaoFinalizar = document.getElementById('btn-finalizar');
    if (botaoFinalizar) {
        switch(currency) {
            case 'BRL':
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro (R$)';
                break;
            case 'USD':
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro ($)';
                break;
            default:
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
        }
        console.log('🔍 [MOEDA] Botão finalizar atualizado para moeda:', currency);
    }
    
    // Atualizar selo de moeda se existir
    const moedaSelect = document.getElementById('moeda_select');
    if (moedaSelect) {
        moedaSelect.value = currency;
        console.log('🔍 [MOEDA] Select de moeda atualizado para:', currency);
    }
    
    // Atualizar campo oculto de moeda
    const moedaHidden = document.getElementById('moeda_hidden');
    if (moedaHidden) {
        moedaHidden.value = currency;
        console.log('🔍 [MOEDA] Campo oculto de moeda atualizado para:', currency);
    }
    
    // Atualizar símbolo da moeda no header se existir
    const currentCurrencyElement = document.getElementById('current-currency');
    if (currentCurrencyElement) {
        currentCurrencyElement.textContent = currency;
        console.log('🔍 [MOEDA] Símbolo no header atualizado para:', currency);
    }
    
    console.log('🔍 [MOEDA] updatePrices() concluída com sucesso');
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

// Função para habilitar/desabilitar botão finalizar
function toggleButton() {
    const checkbox = document.getElementById('consentimento_legal');
    const botao = document.getElementById('btn-finalizar');
    
    console.log('🔍 [BOTÃO] toggleButton() chamada');
    console.log('🔍 [BOTÃO] Checkbox marcado:', checkbox ? checkbox.checked : 'não');
    
    if (checkbox && botao) {
        const isChecked = checkbox.checked;
        botao.disabled = !isChecked;
        
        if (isChecked) {
            botao.className = 'btn btn-primary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão habilitado');
        } else {
            botao.className = 'btn btn-secondary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão desabilitado');
        }
        
        console.log('🔍 [BOTÃO] Estado final do botão:', !botao.disabled);
    } else {
        console.error('❌ [BOTÃO] Checkbox ou botão não encontrado');
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
