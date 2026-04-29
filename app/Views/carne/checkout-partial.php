<!-- Carnê Braziliana - Componente de Checkout -->
<div id="carne-braziliana-section" style="display: none;">
    <div class="card border-primary mb-3">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Carnê Braziliana</h6>
        </div>
        <div class="card-body">
            <!-- Aviso de valor mínimo (mostrado quando total está abaixo) -->
            <div id="carne-aviso-minimo" class="alert alert-warning mb-3" style="display:none;">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <span id="carne-aviso-minimo-texto"></span>
            </div>

            <!-- Conteúdo normal do carnê (escondido quando abaixo do mínimo) -->
            <div id="carne-conteudo-normal">
                <p class="small text-muted mb-3">
                    Parcele sua compra em até 12x via boleto bancário. Cada parcela gera dois boletos: um para produtos (Câmbio Real) e outro para taxas (Appmax).
                    <strong>O envio ocorre somente após a quitação total.</strong>
                </p>

                <!-- Seleção de Parcelas -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Quantidade de Parcelas</label>
                    <select name="carne_parcelas" id="carne-parcelas-select" class="form-select">
                        <!-- Preenchido via JS -->
                    </select>
                </div>

                <!-- Resumo do Parcelamento -->
                <div id="carne-resumo" class="border rounded p-3 bg-light mb-3" style="display: none;">
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted">Boleto Produtos (Câmbio Real)</small>
                            <p class="fw-bold text-primary mb-0" id="carne-valor-produtos">R$ 0,00</p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Boleto Taxas (Appmax)</small>
                            <p class="fw-bold text-primary mb-0" id="carne-valor-taxas">R$ 0,00</p>
                        </div>
                    </div>
                    <hr class="my-2">
                    <p class="text-center mb-0"><strong>Total por parcela: <span id="carne-valor-total">R$ 0,00</span></strong></p>
                </div>

                <!-- Aceite dos Termos -->
                <div class="form-check mb-2">
                    <input type="checkbox" name="carne_termos_aceitos" id="carne-termos-check" class="form-check-input" value="1" required>
                    <label class="form-check-label" for="carne-termos-check">
                        Li e aceito os <a href="/carne/termos" target="_blank">termos e condições do Carnê Braziliana</a>
                    </label>
                </div>

                <div class="alert alert-info small mb-0">
                    <i class="fas fa-info-circle"></i> Disponível apenas para pagamentos em Reais (BRL) e envios para o Brasil.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const section    = document.getElementById('carne-braziliana-section');
    const avisoBox   = document.getElementById('carne-aviso-minimo');
    const avisoTexto = document.getElementById('carne-aviso-minimo-texto');
    const conteudo   = document.getElementById('carne-conteudo-normal');
    const select     = document.getElementById('carne-parcelas-select');
    const resumo     = document.getElementById('carne-resumo');
    const valProds   = document.getElementById('carne-valor-produtos');
    const valTaxas   = document.getElementById('carne-valor-taxas');
    const valTotal   = document.getElementById('carne-valor-total');
    const termos     = document.getElementById('carne-termos-check');

    // Função chamada pelo checkout ao selecionar/desselecionar carnê
    window.toggleCarneBraziliana = function(show, totalProdutos, totalTaxas) {
        section.style.display = show ? 'block' : 'none';
        if (show) {
            carregarParcelas(totalProdutos, totalTaxas);
        } else {
            // Limpar estado ao esconder
            avisoBox.style.display = 'none';
            conteudo.style.display = 'block';
            if (termos) termos.required = false;
        }
    };

    function mostrarAvisoMinimo(faltaReais) {
        // Mostrar aviso, esconder formulário de parcelas
        avisoBox.style.display = 'block';
        avisoTexto.textContent = 'Adicione pelo menos R$ ' + formatMoney(faltaReais) + ' ao carrinho para usar o Carnê Braziliana.';
        conteudo.style.display = 'none';
        // Desabilitar o checkbox de termos para não bloquear o submit
        if (termos) { termos.required = false; termos.checked = false; }
        // Trocar forma de pagamento de volta para "Selecione..."
        const formaSel = document.getElementById('forma_pagamento');
        if (formaSel && formaSel.value === 'carne_braziliana') {
            formaSel.value = '';
            // Disparar evento para o checkout atualizar o estado do botão
            formaSel.dispatchEvent(new Event('change'));
        }
    }

    function mostrarConteudoNormal() {
        avisoBox.style.display = 'none';
        conteudo.style.display = 'block';
        if (termos) termos.required = true;
    }

    function carregarParcelas(totalProdutos, totalTaxas) {
        fetch('/carne/calcular-parcelas?total_produtos=' + totalProdutos + '&total_taxas=' + totalTaxas)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                const valorMinimo  = data.valor_minimo || 0;
                const totalPedido  = data.total || (totalProdutos + totalTaxas);

                // Checar valor mínimo configurado
                if (valorMinimo > 0 && totalPedido < valorMinimo) {
                    mostrarAvisoMinimo(valorMinimo - totalPedido);
                    return;
                }

                // Checar se há parcelas disponíveis (mínimo por boleto R$20)
                if (!data.parcelas || data.parcelas.length === 0) {
                    // Calcular quanto falta para ter pelo menos 2x com R$20 cada boleto
                    // Mínimo total = 2 * R$20 = R$40 por boleto de produto + R$40 por boleto de taxa
                    const minimoParaParcela = 40; // R$40 mínimo para 2x
                    const falta = Math.max(0, minimoParaParcela - totalPedido);
                    mostrarAvisoMinimo(falta > 0 ? falta : 0.01);
                    return;
                }

                // Tudo ok — mostrar parcelas normalmente
                mostrarConteudoNormal();
                select.innerHTML = '';
                data.parcelas.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.parcelas;
                    opt.textContent = p.parcelas + 'x de R$ ' + formatMoney(p.valor_parcela_total);
                    opt.dataset.produtos = p.valor_parcela_produtos;
                    opt.dataset.taxas    = p.valor_parcela_taxas;
                    opt.dataset.total    = p.valor_parcela_total;
                    select.appendChild(opt);
                });
                atualizarResumo();
            })
            .catch(() => {
                // Erro de rede — não sumir, só não mostrar parcelas
                mostrarAvisoMinimo(0);
                avisoTexto.textContent = 'Não foi possível carregar as opções de parcelamento. Tente novamente.';
            });
    }

    select.addEventListener('change', atualizarResumo);

    function atualizarResumo() {
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.dataset.total) { resumo.style.display = 'none'; return; }
        resumo.style.display = 'block';
        valProds.textContent = 'R$ ' + formatMoney(opt.dataset.produtos);
        valTaxas.textContent = 'R$ ' + formatMoney(opt.dataset.taxas);
        valTotal.textContent = 'R$ ' + formatMoney(opt.dataset.total);
    }

    function formatMoney(val) {
        return parseFloat(val || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
})();
</script>
