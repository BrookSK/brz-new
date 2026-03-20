<?php
$sidebarActive = 'redirecionamento-envios';
$title = 'Novo Envio';
$redirecionadores = is_array($redirecionadores ?? null) ? $redirecionadores : [];
$tabela = is_array($tabela ?? null) ? $tabela : [];
$stripePublicKey = $stripePublicKey ?? '';
$redirecionadorFixo = $redirecionadorFixo ?? null;
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/admin/redirecionamento/envios" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="h2 mb-0">Novo Envio</h1>
            <div class="text-muted small">Preencha os passos abaixo</div>
        </div>
    </div>

    <!-- Steps nav -->
    <div class="d-flex gap-2 mb-4 flex-wrap" id="stepsNav">
        <?php foreach ([1=>'Pedido',2=>'Destinatário',3=>'Envio',4=>'Produtos',5=>'Pagamento'] as $n=>$l): ?>
        <div class="step-pill d-flex align-items-center gap-2 px-3 py-2 rounded-pill border <?= $n===1?'border-primary bg-primary text-white':'border-secondary text-muted' ?>" data-step="<?= $n ?>" style="cursor:pointer;font-size:.85rem">
            <span class="fw-bold"><?= $n ?></span> <?= $l ?>
        </div>
        <?php endforeach; ?>
    </div>

    <form id="formEnvio">
    <!-- STEP 1: Pedido -->
    <div class="step-content" data-step="1">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Informações do pedido</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Redirecionador <span class="text-danger">*</span></label>
                        <?php if ($redirecionadorFixo): ?>
                        <input class="form-control" type="text" value="<?= htmlspecialchars($redirecionadorFixo['nome'],ENT_QUOTES,'UTF-8') ?>" readonly>
                        <input type="hidden" name="redirecionador_id" value="<?= (int)$redirecionadorFixo['id'] ?>">
                        <?php else: ?>
                        <select class="form-select" name="redirecionador_id" id="selRedirecionador" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($redirecionadores as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome'],ENT_QUOTES,'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID do pedido (site do cliente) <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="id_pedido_cliente" required placeholder="Ex: 1234">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 2: Destinatário -->
    <div class="step-content d-none" data-step="2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Destinatário</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm" id="selCliente" style="min-width:220px">
                            <option value="">Selecionar cliente cadastrado...</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNovoCliente">
                            <i class="fas fa-plus me-1"></i>Novo cliente
                        </button>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Nome <span class="text-danger">*</span></label><input class="form-control" type="text" name="destinatario_nome" id="destNome" required></div>
                    <div class="col-md-3"><label class="form-label">CPF</label><input class="form-control" type="text" name="destinatario_cpf" id="destCpf"></div>
                    <div class="col-md-3"><label class="form-label">Data de nascimento</label><input class="form-control" type="date" name="destinatario_data_nascimento" id="destNasc"></div>
                    <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control" type="email" name="destinatario_email" id="destEmail"></div>
                    <div class="col-md-6"><label class="form-label">Telefone</label><input class="form-control" type="text" name="destinatario_telefone" id="destTel"></div>
                    <div class="col-md-8"><label class="form-label">Logradouro</label><input class="form-control" type="text" name="dest_logradouro" id="destLogr"></div>
                    <div class="col-md-2"><label class="form-label">Número</label><input class="form-control" type="text" name="dest_numero" id="destNum"></div>
                    <div class="col-md-2"><label class="form-label">Complemento</label><input class="form-control" type="text" name="dest_complemento" id="destComp"></div>
                    <div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control" type="text" name="dest_bairro" id="destBairro"></div>
                    <div class="col-md-4"><label class="form-label">Cidade</label><input class="form-control" type="text" name="dest_cidade" id="destCidade"></div>
                    <div class="col-md-2"><label class="form-label">Estado</label><input class="form-control" type="text" name="dest_estado" id="destEstado" maxlength="2"></div>
                    <div class="col-md-2"><label class="form-label">CEP</label><input class="form-control" type="text" name="dest_cep" id="destCep"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 3: Envio -->
    <div class="step-content d-none" data-step="3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Informações de envio</h5>
                <div class="alert alert-warning small mb-3"><i class="fas fa-triangle-exclamation me-2"></i>Preencha o peso e dimensões corretos da caixa. Essas informações serão usadas para conferência, verificação e cobrança do redirecionamento.</div>
                <div class="row g-3">
                    <div class="col-md-2"><label class="form-label">Moeda</label><input class="form-control" type="text" value="USD" readonly></div>
                    <div class="col-md-3">
                        <label class="form-label">Peso total (kg) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" step="0.001" min="0.001" name="peso_kg" id="inputPeso" required>
                        <div class="form-text">Preencha com precisão — base da cobrança.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor do frete cobrado ao cliente (USD)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="valor_frete_usd" id="inputFrete">
                        <div class="form-text">Informe o valor cobrado do cliente no seu site.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor a pagar (calculado)</label>
                        <div class="input-group">
                            <span class="input-group-text">US$</span>
                            <input class="form-control fw-bold" type="text" id="valorCalculado" readonly placeholder="—">
                        </div>
                        <div class="form-text" id="faixaInfo"></div>
                    </div>
                    <div class="col-md-4"><label class="form-label">Largura (cm) <span class="text-danger">*</span></label><input class="form-control" type="number" step="0.1" min="1" name="largura_cm" required></div>
                    <div class="col-md-4"><label class="form-label">Altura (cm) <span class="text-danger">*</span></label><input class="form-control" type="number" step="0.1" min="1" name="altura_cm" required></div>
                    <div class="col-md-4"><label class="form-label">Comprimento (cm) <span class="text-danger">*</span></label><input class="form-control" type="number" step="0.1" min="1" name="comprimento_cm" required></div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 4: Produtos -->
    <div class="step-content d-none" data-step="4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Produtos</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddProduto"><i class="fas fa-plus me-1"></i>Adicionar produto</button>
                </div>
                <div id="listaProdutos"></div>
            </div>
        </div>
    </div>

    <!-- STEP 5: Pagamento -->
    <div class="step-content d-none" data-step="5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Pagamento</h5>
                <div id="resumoPagamento" class="alert alert-info mb-3"></div>
                <div id="stripeContainer" class="mb-3"></div>
                <div id="msgPagamento"></div>
                <div class="mt-3">
                    <label class="form-label">Upload do comprovante de pagamento</label>
                    <input type="file" class="form-control" id="inputComprovante" accept=".jpg,.jpeg,.png,.pdf">
                    <div class="form-text">Após o pagamento, envie o comprovante.</div>
                </div>
                <div class="alert alert-warning small mt-3"><i class="fas fa-info-circle me-2"></i>Após a coleta, verificaremos as dimensões e peso reais. Se houver diferença, será gerada uma cobrança ou reembolso proporcional.</div>
            </div>
        </div>
    </div>

    <!-- Navegação -->
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary" id="btnAnterior" style="display:none!important">Anterior</button>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-primary" id="btnProximo">Próximo</button>
            <button type="button" class="btn btn-success d-none" id="btnGerarEnvio"><i class="fas fa-check me-1"></i>Gerar envio</button>
        </div>
    </div>
    </form>
</div>

<!-- Modal novo cliente -->
<div class="modal fade" id="modalNovoCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Cadastrar cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Nome <span class="text-danger">*</span></label><input class="form-control" type="text" id="ncNome"></div>
                    <div class="col-md-3"><label class="form-label">CPF</label><input class="form-control" type="text" id="ncCpf"></div>
                    <div class="col-md-3"><label class="form-label">Data nascimento</label><input class="form-control" type="date" id="ncNasc"></div>
                    <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control" type="email" id="ncEmail"></div>
                    <div class="col-md-6"><label class="form-label">Telefone</label><input class="form-control" type="text" id="ncTel"></div>
                    <div class="col-12"><hr class="my-1"><small class="text-muted">Endereço principal</small></div>
                    <div class="col-md-8"><label class="form-label">Logradouro</label><input class="form-control" type="text" id="ncLogr"></div>
                    <div class="col-md-2"><label class="form-label">Número</label><input class="form-control" type="text" id="ncNum"></div>
                    <div class="col-md-2"><label class="form-label">Complemento</label><input class="form-control" type="text" id="ncComp"></div>
                    <div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control" type="text" id="ncBairro"></div>
                    <div class="col-md-4"><label class="form-label">Cidade</label><input class="form-control" type="text" id="ncCidade"></div>
                    <div class="col-md-2"><label class="form-label">Estado</label><input class="form-control" type="text" id="ncEstado" maxlength="2"></div>
                    <div class="col-md-2"><label class="form-label">CEP</label><input class="form-control" type="text" id="ncCep"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarCliente">Salvar cliente</button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($stripePublicKey)): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>
<script>
const STRIPE_PK = <?= json_encode($stripePublicKey) ?>;
const TABELA = <?= json_encode(array_map(fn($r)=>['peso'=>(float)$r['peso_ate_kg'],'valor'=>(float)$r['valor_usd']],$tabela)) ?>;
let currentStep = 1, totalSteps = 5, envioId = null, valorUsd = 0;
let stripe = STRIPE_PK ? Stripe(STRIPE_PK) : null;
let elements = null, cardElement = null;

function calcularValorTabela(peso) {
    for (const row of TABELA) { if (peso <= row.peso) return row; }
    return TABELA.length ? TABELA[TABELA.length-1] : null;
}

document.getElementById('inputPeso')?.addEventListener('input', function() {
    const peso = parseFloat(this.value) || 0;
    const row = calcularValorTabela(peso);
    if (row) {
        document.getElementById('valorCalculado').value = row.valor.toFixed(2);
        document.getElementById('faixaInfo').textContent = 'Faixa: até ' + row.peso.toFixed(1) + ' kg';
        valorUsd = row.valor;
    } else {
        document.getElementById('valorCalculado').value = '';
        document.getElementById('faixaInfo').textContent = '';
    }
});

function showStep(n) {
    document.querySelectorAll('.step-content').forEach(el => el.classList.add('d-none'));
    document.querySelector(`.step-content[data-step="${n}"]`)?.classList.remove('d-none');
    document.querySelectorAll('.step-pill').forEach(el => {
        const s = parseInt(el.dataset.step);
        el.className = 'step-pill d-flex align-items-center gap-2 px-3 py-2 rounded-pill border ' + (s===n?'border-primary bg-primary text-white':'border-secondary text-muted');
    });
    document.getElementById('btnAnterior').style.display = n>1?'':'none';
    document.getElementById('btnProximo').classList.toggle('d-none', n===totalSteps);
    document.getElementById('btnGerarEnvio').classList.toggle('d-none', n!==4);
    if (n===5) setupPagamento();
}

document.getElementById('btnProximo').addEventListener('click', () => { if (currentStep < totalSteps) { currentStep++; showStep(currentStep); } });
document.getElementById('btnAnterior').addEventListener('click', () => { if (currentStep > 1) { currentStep--; showStep(currentStep); } });
document.querySelectorAll('.step-pill').forEach(el => el.addEventListener('click', () => { currentStep=parseInt(el.dataset.step); showStep(currentStep); }));

// Produtos
let prodIdx = 0;
document.getElementById('btnAddProduto').addEventListener('click', () => {
    const i = prodIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 align-items-end produto-row';
    div.innerHTML = `
        <div class="col-md-2"><label class="form-label small">NCM</label><input class="form-control form-control-sm" type="text" name="produtos[${i}][ncm]"></div>
        <div class="col-md-4"><label class="form-label small">Descrição *</label><input class="form-control form-control-sm" type="text" name="produtos[${i}][descricao]" required></div>
        <div class="col-md-2"><label class="form-label small">Preço (USD)</label><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="produtos[${i}][preco_usd]" value="0"></div>
        <div class="col-md-2"><label class="form-label small">Peso (kg)</label><input class="form-control form-control-sm" type="number" step="0.001" min="0" name="produtos[${i}][peso_kg]" value="0"></div>
        <div class="col-md-1"><label class="form-label small">Qtd</label><input class="form-control form-control-sm" type="number" min="1" name="produtos[${i}][quantidade]" value="1"></div>
        <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.produto-row').remove()"><i class="fas fa-trash"></i></button></div>`;
    document.getElementById('listaProdutos').appendChild(div);
});

// Gerar envio
document.getElementById('btnGerarEnvio').addEventListener('click', async () => {
    const form = document.getElementById('formEnvio');
    const data = new FormData(form);
    const resp = await fetch('/admin/redirecionamento/envios/salvar', {method:'POST',body:data});
    const json = await resp.json();
    if (json.ok) {
        envioId = json.envio_id; valorUsd = json.valor_usd;
        currentStep = 5; showStep(5);
    } else { alert('Erro: ' + (json.msg||'Tente novamente')); }
});

function setupPagamento() {
    document.getElementById('resumoPagamento').innerHTML = `Valor a pagar: <strong>US$ ${valorUsd.toFixed(2)}</strong> — Envio #${envioId||'(pendente)'}`;
    if (!stripe || !envioId) return;
    fetch('/admin/redirecionamento/pagamento/criar-intent', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'envio_id='+envioId})
        .then(r=>r.json()).then(data => {
            if (!data.ok) { document.getElementById('msgPagamento').innerHTML='<div class="alert alert-danger">'+data.msg+'</div>'; return; }
            elements = stripe.elements();
            cardElement = elements.create('card');
            document.getElementById('stripeContainer').innerHTML = '<div id="cardElement" class="form-control p-3"></div><button type="button" class="btn btn-success mt-3 w-100" id="btnPagar"><i class="fas fa-lock me-2"></i>Pagar US$ '+valorUsd.toFixed(2)+'</button>';
            cardElement.mount('#cardElement');
            document.getElementById('btnPagar').addEventListener('click', async () => {
                const {paymentIntent, error} = await stripe.confirmCardPayment(data.client_secret, {payment_method:{card:cardElement}});
                if (error) { document.getElementById('msgPagamento').innerHTML='<div class="alert alert-danger">'+error.message+'</div>'; return; }
                const conf = await fetch('/admin/redirecionamento/pagamento/confirmar',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'envio_id='+envioId+'&payment_intent_id='+paymentIntent.id});
                const cj = await conf.json();
                document.getElementById('msgPagamento').innerHTML = cj.ok ? '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Pagamento confirmado! Agora envie o comprovante abaixo.</div>' : '<div class="alert alert-warning">Pagamento processado mas não confirmado ainda.</div>';
            });
        });
}

// Upload comprovante
document.getElementById('inputComprovante')?.addEventListener('change', async function() {
    if (!envioId || !this.files[0]) return;
    const fd = new FormData(); fd.append('comprovante',this.files[0]); fd.append('envio_id',envioId); fd.append('tipo','envio');
    const r = await fetch('/admin/redirecionamento/comprovantes/upload',{method:'POST',body:fd});
    const j = await r.json();
    if (j.ok) { const el=document.createElement('div'); el.className='alert alert-success mt-2'; el.textContent='Comprovante enviado.'; this.parentNode.appendChild(el); }
});

// Carregar clientes ao selecionar redirecionador
<?php if ($redirecionadorFixo): ?>
// Redirecionador fixo — carregar clientes automaticamente
(async () => {
    const r = await fetch('/admin/redirecionamento/clientes?redirecionador_id=<?= (int)$redirecionadorFixo['id'] ?>&format=json');
    document.getElementById('selCliente').innerHTML = '<option value="">Selecionar cliente cadastrado...</option>';
})();
<?php else: ?>
document.getElementById('selRedirecionador')?.addEventListener('change', async function() {
    const redId = this.value;
    if (!redId) return;
    const r = await fetch('/admin/redirecionamento/clientes?redirecionador_id='+redId+'&format=json');
    document.getElementById('selCliente').innerHTML = '<option value="">Selecionar cliente cadastrado...</option>';
});
<?php endif; ?>

// Selecionar cliente e preencher campos
document.getElementById('selCliente')?.addEventListener('change', async function() {
    if (!this.value) return;
    const r = await fetch('/admin/redirecionamento/clientes/get?id='+this.value);
    const c = await r.json();
    if (!c.id) return;
    document.getElementById('destNome').value = c.nome||'';
    document.getElementById('destCpf').value = c.cpf||'';
    document.getElementById('destEmail').value = c.email||'';
    document.getElementById('destTel').value = c.telefone||'';
    document.getElementById('destNasc').value = c.data_nascimento||'';
    document.getElementById('destLogr').value = c.logradouro||'';
    document.getElementById('destNum').value = c.numero||'';
    document.getElementById('destComp').value = c.complemento||'';
    document.getElementById('destBairro').value = c.bairro||'';
    document.getElementById('destCidade').value = c.cidade||'';
    document.getElementById('destEstado').value = c.estado||'';
    document.getElementById('destCep').value = c.cep||'';
});

// Salvar novo cliente
document.getElementById('btnSalvarCliente')?.addEventListener('click', async () => {
    <?php if ($redirecionadorFixo): ?>
    const redId = '<?= (int)$redirecionadorFixo['id'] ?>';
    <?php else: ?>
    const redId = document.getElementById('selRedirecionador').value;
    <?php endif; ?>
    const fd = new FormData();
    fd.append('redirecionador_id', redId);
    fd.append('nome', document.getElementById('ncNome').value);
    fd.append('cpf', document.getElementById('ncCpf').value);
    fd.append('email', document.getElementById('ncEmail').value);
    fd.append('telefone', document.getElementById('ncTel').value);
    fd.append('data_nascimento', document.getElementById('ncNasc').value);
    fd.append('logradouro', document.getElementById('ncLogr').value);
    fd.append('numero', document.getElementById('ncNum').value);
    fd.append('complemento', document.getElementById('ncComp').value);
    fd.append('bairro', document.getElementById('ncBairro').value);
    fd.append('cidade', document.getElementById('ncCidade').value);
    fd.append('estado', document.getElementById('ncEstado').value);
    fd.append('cep', document.getElementById('ncCep').value);
    const r = await fetch('/admin/redirecionamento/clientes/salvar',{method:'POST',body:fd});
    const j = await r.json();
    if (j.ok) {
        const opt = new Option(j.nome, j.id, true, true);
        document.getElementById('selCliente').appendChild(opt);
        bootstrap.Modal.getInstance(document.getElementById('modalNovoCliente')).hide();
        // Preencher campos
        document.getElementById('destNome').value = j.nome;
    } else { alert(j.msg||'Erro ao salvar'); }
});

showStep(1);
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
