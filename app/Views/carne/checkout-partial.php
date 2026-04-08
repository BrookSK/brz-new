<!-- Carnê Braziliana - Componente de Checkout -->
<div id="carne-braziliana-section" style="display: none;">
    <div class="card border-primary mb-3">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Carnê Braziliana</h6>
        </div>
        <div class="card-body">
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

<script>
(function() {
    const section = document.getElementById('carne-braziliana-section');
    const select = document.getElementById('carne-parcelas-select');
    const resumo = document.getElementById('carne-resumo');
    const valProds = document.getElementById('carne-valor-produtos');
    const valTaxas = document.getElementById('carne-valor-taxas');
    const valTotal = document.getElementById('carne-valor-total');

    // Função para mostrar/esconder o carnê baseado no método selecionado
    window.toggleCarneBraziliana = function(show, totalProdutos, totalTaxas) {
        section.style.display = show ? 'block' : 'none';
        if (show && totalProdutos > 0) {
            carregarParcelas(totalProdutos, totalTaxas);
        }
    };

    function carregarParcelas(totalProdutos, totalTaxas) {
        fetch('/carne/calcular-parcelas?total_produtos=' + totalProdutos + '&total_taxas=' + totalTaxas)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                select.innerHTML = '';
                data.parcelas.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.parcelas;
                    opt.textContent = p.parcelas + 'x de R$ ' + formatMoney(p.valor_parcela_total);
                    opt.dataset.produtos = p.valor_parcela_produtos;
                    opt.dataset.taxas = p.valor_parcela_taxas;
                    opt.dataset.total = p.valor_parcela_total;
                    select.appendChild(opt);
                });
                atualizarResumo();
            });
    }

    select.addEventListener('change', atualizarResumo);

    function atualizarResumo() {
        const opt = select.options[select.selectedIndex];
        if (!opt) return;
        resumo.style.display = 'block';
        valProds.textContent = 'R$ ' + formatMoney(opt.dataset.produtos);
        valTaxas.textContent = 'R$ ' + formatMoney(opt.dataset.taxas);
        valTotal.textContent = 'R$ ' + formatMoney(opt.dataset.total);
    }

    function formatMoney(val) {
        return parseFloat(val).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
})();
</script>
