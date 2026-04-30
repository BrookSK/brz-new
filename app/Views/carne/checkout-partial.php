<!-- Carnê Braziliana - Componente de Checkout -->
<div id="carne-braziliana-section" style="display: none;">
    <div class="card border-primary mb-3">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Carnê Braziliana</h6>
        </div>
        <div class="card-body">

            <!-- Aviso de valor mínimo / loading (mostrado quando total está abaixo ou carregando) -->
            <div id="carne-aviso-minimo" class="alert alert-warning mb-0" style="display:none;">
                <span id="carne-aviso-icone"><i class="fas fa-exclamation-triangle me-1"></i></span>
                <span id="carne-aviso-minimo-texto"></span>
            </div>

            <!-- Conteúdo normal do carnê -->
            <div id="carne-conteudo-normal" style="display:none;">
                <p class="small text-muted mb-3">
                    Parcele sua compra em até 12x via boleto bancário. Cada parcela gera dois boletos: um para produtos (Câmbio Real) e outro para taxas (Câmbio Real).
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
                            <small class="text-muted">Boleto Taxas (Câmbio Real)</small>
                            <p class="fw-bold text-primary mb-0" id="carne-valor-taxas">R$ 0,00</p>
                        </div>
                    </div>
                    <hr class="my-2">
                    <p class="text-center mb-0"><strong>Total por parcela: <span id="carne-valor-total">R$ 0,00</span></strong></p>
                </div>

                <!-- Aceite dos Termos -->
                <div class="form-check mb-2">
                    <input type="checkbox" name="carne_termos_aceitos" id="carne-termos-check" class="form-check-input" value="1">
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

    // Chamada pelo checkout ao selecionar/desselecionar carnê
    window.toggleCarneBraziliana = function(show, totalProdutos, totalTaxas) {
        if (!show) {
            section.style.display = 'none';
            avisoBox.style.display = 'none';
            conteudo.style.display = 'none';
            return;
        }
        section.style.display = 'block';
        carregarParcelas(totalProdutos, totalTaxas);
    };

    function mostrarAviso(texto) {
        avisoBox.className = 'alert alert-warning mb-0';
        document.getElementById('carne-aviso-icone').innerHTML = '<i class="fas fa-cart-plus me-1"></i>';
        avisoTexto.textContent = texto;
        avisoBox.style.display = 'block';
        conteudo.style.display = 'none';
    }

    function mostrarParcelas() {
        avisoBox.style.display = 'none';
        conteudo.style.display = 'block';
    }

    function carregarParcelas(totalProdutos, totalTaxas) {
        // Mostrar loading enquanto carrega
        avisoBox.style.display = 'none';
        conteudo.style.display = 'none';
        // Mostrar spinner temporário
        avisoTexto.textContent = 'Calculando parcelas...';
        avisoBox.style.display = 'block';
        avisoBox.className = 'alert alert-secondary mb-0';
        document.getElementById('carne-aviso-icone').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>';

        fetch('/carne/calcular-parcelas?total_produtos=' + totalProdutos + '&total_taxas=' + totalTaxas)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    mostrarAviso('Não foi possível carregar as opções de parcelamento. Tente novamente.');
                    return;
                }

                const valorMinimo = data.valor_minimo || 0;
                const totalPedido = data.total || (totalProdutos + totalTaxas);

                // Checar valor mínimo configurado pelo admin
                if (valorMinimo > 0 && totalPedido < valorMinimo) {
                    const falta = valorMinimo - totalPedido;
                    mostrarAviso('Adicione R$ ' + formatMoney(falta) + ' ao carrinho para usar o Carnê Braziliana (mínimo R$ ' + formatMoney(valorMinimo) + ').');
                    return;
                }

                // Checar se há parcelas disponíveis (mínimo R$20 por boleto)
                if (!data.parcelas || data.parcelas.length === 0) {
                    // Calcular quanto falta especificamente no boleto de produtos
                    // (é o gargalo mais comum — taxa de serviço costuma ser alta)
                    const minimoBoleto = 20;
                    const faltaProd = Math.max(0, (minimoBoleto * 2) - totalProdutos);
                    const faltaTaxa = Math.max(0, (minimoBoleto * 2) - totalTaxas);
                    // O gargalo é o que ainda falta para atingir o mínimo
                    const falta = Math.max(faltaProd, faltaTaxa);
                    if (falta > 0.01) {
                        mostrarAviso('Adicione R$ ' + formatMoney(falta) + ' ao carrinho para usar o Carnê Braziliana (cada parcela precisa ter no mínimo R$ ' + formatMoney(minimoBoleto) + ' por boleto).');
                    } else {
                        mostrarAviso('O valor do pedido não permite parcelamento. Adicione mais produtos ao carrinho.');
                    }
                    return;
                }

                // Tudo ok — mostrar parcelas
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
                mostrarParcelas();
                atualizarResumo();
            })
            .catch(() => {
                mostrarAviso('Não foi possível carregar as opções de parcelamento. Verifique sua conexão e tente novamente.');
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
