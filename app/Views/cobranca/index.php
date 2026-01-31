<?php ob_start(); ?>
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
        <h1 class="h4 mb-0"><i class="fas fa-calculator"></i> Cálculo de Cobrança</h1>
        <a href="/produtos" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Continuar Comprando
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-shopping-cart"></i> Itens do Carrinho</h5>
            </div>
            <div class="card-body">
                <?php if (empty($carrinho)): ?>
                    <p class="text-muted">Seu carrinho está vazio.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Quantidade</th>
                                    <th>Valor Unit.</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carrinho as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['nome']) ?></td>
                                        <td><?= $item['quantidade'] ?></td>
                                        <td>R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                                        <td>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: rgba(11, 31, 58, 0.06);">
                                    <th colspan="3">Subtotal Produtos:</th>
                                    <th>R$ <?= number_format($subtotal, 2, ',', '.') ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-cogs"></i> Serviços Adicionais</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="despacho" id="servico-despacho" checked>
                            <label class="form-check-label" for="servico-despacho">
                                <strong>Despacho para MIA:</strong> R$ 150,00
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="translado" id="servico-translado" checked>
                            <label class="form-check-label" for="servico-translado">
                                <strong>Translado Miami-Brasil:</strong> R$ 350,00
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="armazenamento" id="servico-armazenamento" checked>
                            <label class="form-check-label" for="servico-armazenamento">
                                <strong>Armazenamento:</strong> R$ 50,00
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="envio" id="servico-envio" checked>
                            <label class="form-check-label" for="servico-envio">
                                <strong>Envio Correios:</strong> R$ 25,00
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-user"></i> Dados do Cliente</h5>
            </div>
            <div class="card-body">
                <form id="cliente-form">
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
                            <label for="documento" class="form-label">CPF/CNPJ</label>
                            <input type="text" class="form-control" id="documento" name="documento" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="endereco" class="form-label">Endereço Completo</label>
                        <textarea class="form-control" id="endereco" name="endereco" rows="3" required></textarea>
                    </div>
                </form>
            </div>
        </div>
    </div>

        </div>

        <div class="col-lg-4">
        <div class="card sticky-top border-0 shadow-sm" style="top: 20px;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-receipt"></i> Resumo do Pedido</h5>
            </div>
            <div class="card-body">
                <div id="calculo-resumo">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal Produtos:</span>
                        <strong>R$ <span id="subtotal-valor"><?= number_format($subtotal, 2, ',', '.') ?></span></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Serviços:</span>
                        <strong>R$ <span id="servicos-valor">575,00</span></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Impostos (80,65%):</span>
                        <strong>R$ <span id="impostos-valor">0,00</span></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <h5>Total:</h5>
                        <h5 class="text-primary">R$ <span id="total-valor">0,00</span></h5>
                    </div>
                </div>
                
                <button type="button" id="btn-calcular" class="btn btn-outline-primary w-100 mb-2">
                    <i class="fas fa-calculator"></i> Calcular Total
                </button>
                
                <button type="button" id="btn-processar" class="btn btn-primary w-100" disabled>
                    <i class="fas fa-check"></i> Processar Pedido
                </button>
            </div>
        </div>
    </div>
</div>

</div>

<script>
$(document).ready(function() {
    $('#btn-calcular').click(function() {
        if (!validateForm()) {
            return;
        }
        
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Calculando...');
        
        var servicos = [];
        $('input[type="checkbox"]:checked').each(function() {
            servicos.push($(this).val());
        });
        
        var clienteData = $('#cliente-form').serialize();
        
        $.post('/cobranca/calcular', clienteData + '&servicos=' + servicos.join(','), function(response) {
            if (response.success) {
                $('#subtotal-valor').text(response.subtotal);
                $('#servicos-valor').text(response.servicos);
                $('#impostos-valor').text(response.impostos);
                $('#total-valor').text(response.total);
                $('#btn-processar').prop('disabled', false);
                
                showAlert('success', 'Cálculo realizado com sucesso!');
            } else {
                showAlert('danger', response.error);
            }
        }).fail(function() {
            showAlert('danger', 'Erro ao calcular valores');
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-calculator"></i> Calcular Total');
        });
    });
    
    $('#btn-processar').click(function() {
        if (!validateForm()) {
            return;
        }
        
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processando...');
        
        var servicos = [];
        $('input[type="checkbox"]:checked').each(function() {
            servicos.push($(this).val());
        });
        
        var clienteData = $('#cliente-form').serialize();
        
        $.post('/processar', clienteData + '&servicos=' + servicos.join(','), function(response) {
            if (response.success) {
                showAlert('success', response.message);
                setTimeout(function() {
                    window.location.href = response.redirect;
                }, 2000);
            } else {
                showAlert('danger', response.error);
            }
        }).fail(function() {
            showAlert('danger', 'Erro ao processar pedido');
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-check"></i> Processar Pedido');
        });
    });
    
    function validateForm() {
        var nome = $('#nome').val();
        var email = $('#email').val();
        var documento = $('#documento').val();
        var endereco = $('#endereco').val();
        
        if (!nome || !email || !documento || !endereco) {
            showAlert('warning', 'Preencha todos os dados do cliente');
            return false;
        }
        
        return true;
    }
    
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
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
