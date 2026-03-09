<?php ob_start(); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h1 class="h4 mb-1" style="color:#0b1f3a; font-weight: 800;">Recarga do Clube</h1>
                    <div class="text-muted">Checkout rápido (sem login)</div>
                </div>
                <div class="small text-muted">
                    <a href="/clube-brasiliana" class="text-decoration-none">Como funciona o Clube</a>
                    <span class="mx-2">·</span>
                    <a href="/termos-uso" class="text-decoration-none">Termos</a>
                    <span class="mx-2">·</span>
                    <a href="/politica-privacidade" class="text-decoration-none">Privacidade</a>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label class="form-label">E-mail *</label>
                                <input type="email" class="form-control" id="qc_email" placeholder="seuemail@exemplo.com" required>
                                <div class="form-text" id="qc_email_hint"></div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nome *</label>
                                    <input type="text" class="form-control" id="qc_nome" placeholder="Nome" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sobrenome *</label>
                                    <input type="text" class="form-control" id="qc_sobrenome" placeholder="Sobrenome" required>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label">Telefone *</label>
                                    <div class="input-group w-100" style="flex-wrap: nowrap;">
                                        <select class="form-select" id="qc_telefone_ddi" style="flex: 0 0 76px; min-width: 76px; padding-left: 8px; padding-right: 24px;">
                                            <option value="55" selected>+55</option>
                                            <option value="1">+1</option>
                                            <option value="44">+44</option>
                                            <option value="49">+49</option>
                                            <option value="33">+33</option>
                                            <option value="34">+34</option>
                                            <option value="39">+39</option>
                                            <option value="351">+351</option>
                                            <option value="54">+54</option>
                                            <option value="56">+56</option>
                                            <option value="57">+57</option>
                                            <option value="0">Outro</option>
                                        </select>
                                        <input type="text" class="form-control" id="qc_telefone_numero" placeholder="Número" required>
                                    </div>
                                    <div class="input-group mt-2" id="qc_telefone_ddi_outro_box" style="display:none;">
                                        <span class="input-group-text">DDI</span>
                                        <input type="text" class="form-control" id="qc_telefone_ddi_outro" placeholder="Ex: 81">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Data de nascimento *</label>
                                    <input type="date" class="form-control" id="qc_data_nascimento" required>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label">País *</label>
                                    <?php require __DIR__ . '/../_countries.php'; ?>
                                    <select class="form-select" id="qc_pais" required>
                                        <?php foreach ($countries as $code => $name): ?>
                                            <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper((string) ($code ?? '')) === 'BR' ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" class="form-control mt-2" id="qc_pais_search" placeholder="Digite para filtrar países...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" id="qc_label_documento">CPF/CNPJ *</label>
                                    <input type="text" class="form-control" id="qc_documento" placeholder="CPF ou CNPJ">
                                    <div class="form-text" id="qc_hint_documento" style="display:none;">Obrigatório apenas para residentes no Brasil.</div>
                                </div>
                            </div>

                            <div class="mt-3" id="qc_senha_wrap" style="display:none;">
                                <div class="alert alert-info mb-3">Não encontramos uma conta com este e-mail. Crie uma senha para finalizar.</div>
                                <label class="form-label">Senha *</label>
                                <input type="password" class="form-control" id="qc_senha" minlength="6" placeholder="Mínimo 6 caracteres">
                            </div>

                            <hr class="my-4" />

                            <div class="mb-3">
                                <label class="form-label">Forma de pagamento</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary" id="qc_metodo_pix">PIX (Stripe)</button>
                                    <button type="button" class="btn btn-outline-secondary" id="qc_metodo_card">Cartão</button>
                                </div>
                                <div class="form-text">Padrão: Pix</div>
                            </div>

                            <div id="qc_pix_box" class="border rounded p-3" style="display:none; background: rgba(11, 31, 58, 0.03);">
                                <div class="fw-bold mb-2">PIX</div>
                                <div id="qc_pix_status" class="text-muted small">Gere o pagamento para ver o QR Code.</div>
                                <div id="qc_pix_qr" class="mt-2" style="display:none;">
                                    <a href="#" target="_blank" id="qc_pix_hosted" class="btn btn-sm btn-outline-primary">Abrir instruções Pix</a>
                                    <div class="small text-muted mt-2">Copiar e colar:</div>
                                    <textarea class="form-control" id="qc_pix_copypaste" rows="3" readonly></textarea>
                                </div>
                            </div>

                            <div class="mt-3" id="qc_card_box" style="display:none;">
                                <div class="fw-bold mb-2">Cartão</div>
                                <div id="qc_stripe_card" class="form-control" style="padding: 12px; background: #fff;"></div>
                                <div id="qc_stripe_errors" class="text-danger small mt-2" style="display:none;"></div>
                            </div>

                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" value="1" id="qc_termos">
                                <label class="form-check-label" for="qc_termos">
                                    Li e aceito os <a href="/termos-uso" target="_blank">termos e condições</a> de uso e a <a href="/politica-privacidade" target="_blank">política de privacidade</a>. Concordo que li os termos de como funciona o clube Braziliana (<a href="/clube-brasiliana" target="_blank">ver</a>).
                                </label>
                            </div>

                            <div class="alert alert-danger mt-3" id="qc_error" style="display:none;"></div>

                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-primary" id="qc_btn_pagar">Finalizar pagamento</button>
                                <a class="btn btn-outline-secondary" href="/">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="fw-bold">Resumo</div>
                                <div class="badge" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">USD</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Valor da recarga (mín. $39) *</label>
                                <input type="number" class="form-control" id="qc_valor_usd" min="<?= (float) ($min_usd ?? 39.0) ?>" step="0.01" value="<?= (float) ($min_usd ?? 39.0) ?>">
                                <div class="form-text" id="qc_rate_hint"></div>
                            </div>

                            <div class="border rounded p-3" style="background: rgba(16, 185, 129, 0.06); border-color: rgba(16, 185, 129, 0.18) !important;">
                                <div class="small text-muted">Equivalente em BRL (taxa Braziliana)</div>
                                <div class="h5 mb-0" id="qc_equiv_brl">-</div>
                                <div class="small text-muted" id="qc_rate_text">-</div>
                            </div>

                            <div class="mt-3 small text-muted">
                                O pagamento é processado via Stripe em BRL para habilitar Pix. O crédito do Clube é contabilizado em USD.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function(){
    const MIN_USD = <?= json_encode((float) ($min_usd ?? 39.0)) ?>;
    const USD_BRL_RATE = <?= json_encode((float) ($usd_brl_rate ?? 5.5)) ?>;
    const STRIPE_ENABLED = <?= json_encode((bool) ($stripe_enabled ?? false)) ?>;
    const STRIPE_PUBLISHABLE_KEY = <?= json_encode((string) ($stripe_publishable_key ?? '')) ?>;

    let metodo = 'pix';
    let stripe = null;
    let elements = null;
    let cardEl = null;

    function setError(msg){
        const el = document.getElementById('qc_error');
        if(!el) return;
        el.textContent = msg || '';
        el.style.display = msg ? '' : 'none';
    }

    function syncDdiOutro(){
        const sel = document.getElementById('qc_telefone_ddi');
        const box = document.getElementById('qc_telefone_ddi_outro_box');
        if(!sel || !box) return;
        box.style.display = (sel.value === '0') ? 'flex' : 'none';
    }

    function getDdi(){
        const sel = document.getElementById('qc_telefone_ddi');
        if(!sel) return '';
        let ddi = (sel.value || '').toString();
        if(ddi === '0'){
            ddi = (document.getElementById('qc_telefone_ddi_outro')?.value || '').toString();
        }
        return ddi.replace(/\D/g,'');
    }

    function filterSelectOptions(selectEl, query) {
        if (!selectEl) return;
        query = (query || '').toString().trim().toLowerCase();
        const opts = selectEl.querySelectorAll('option');
        for (let i = 0; i < opts.length; i++) {
            const o = opts[i];
            const txt = (o.textContent || '').toString().toLowerCase();
            const val = (o.value || '').toString().toLowerCase();
            const match = (query === '') || (txt.indexOf(query) !== -1) || (val.indexOf(query) !== -1);
            o.style.display = match ? '' : 'none';
        }
    }

    function syncDocumentoRules(){
        const paisEl = document.getElementById('qc_pais');
        const docEl = document.getElementById('qc_documento');
        const labelEl = document.getElementById('qc_label_documento');
        const hintEl = document.getElementById('qc_hint_documento');
        if(!paisEl || !docEl || !labelEl) return;
        const br = ((paisEl.value || '').toString().toUpperCase() === 'BR');
        labelEl.textContent = br ? 'CPF/CNPJ *' : 'CPF/CNPJ (opcional)';
        docEl.required = br;
        if(hintEl) hintEl.style.display = br ? 'none' : 'block';
    }

    function updateEquiv(){
        const v = parseFloat((document.getElementById('qc_valor_usd')?.value || '0').toString().replace(',','.')) || 0;
        const usd = Math.max(v, MIN_USD);
        const brl = usd * USD_BRL_RATE;
        document.getElementById('qc_equiv_brl').textContent = 'R$ ' + brl.toFixed(2).replace('.', ',');
        document.getElementById('qc_rate_text').textContent = 'Taxa: 1 USD = R$ ' + USD_BRL_RATE.toFixed(4).replace('.', ',');
        document.getElementById('qc_rate_hint').textContent = 'Mínimo: $' + MIN_USD.toFixed(2);
    }

    async function emailCheck(){
        const email = (document.getElementById('qc_email')?.value || '').toString().trim();
        if(!email) return;
        try{
            const r = await fetch('/clube/recarga/email-check?email=' + encodeURIComponent(email));
            const data = await r.json();
            if(!data || !data.success){
                document.getElementById('qc_email_hint').textContent = data && (data.error || data.message) ? (data.error || data.message) : 'Falha ao validar e-mail';
                document.getElementById('qc_senha_wrap').style.display = 'none';
                return;
            }

            const has = !!data.has_internal_account;
            document.getElementById('qc_email_hint').textContent = has ? 'Conta encontrada. A recarga vai cair na sua carteira.' : 'Nenhuma conta encontrada. Você vai criar uma conta agora.';
            document.getElementById('qc_senha_wrap').style.display = has ? 'none' : '';

            // Prefill quando vier do WP
            const prof = data.wp_profile || {};
            if(prof.first_name && !document.getElementById('qc_nome').value) document.getElementById('qc_nome').value = prof.first_name;
            if(prof.last_name && !document.getElementById('qc_sobrenome').value) document.getElementById('qc_sobrenome').value = prof.last_name;
            if(prof.birth_date && !document.getElementById('qc_data_nascimento').value){
                // aceita YYYY-MM-DD
                if(/^\d{4}-\d{2}-\d{2}$/.test(prof.birth_date)){
                    document.getElementById('qc_data_nascimento').value = prof.birth_date;
                }
            }
            if(prof.country){
                const c = (prof.country || '').toString().toUpperCase();
                const paisEl = document.getElementById('qc_pais');
                if(paisEl && c && !paisEl.value) paisEl.value = c;
            }
            if(prof.cpf && !document.getElementById('qc_documento').value) document.getElementById('qc_documento').value = prof.cpf;
            syncDocumentoRules();
        }catch(e){
        }
    }

    function setMetodo(next){
        metodo = next;
        document.getElementById('qc_metodo_pix').className = (metodo==='pix') ? 'btn btn-primary' : 'btn btn-outline-primary';
        document.getElementById('qc_metodo_card').className = (metodo==='card') ? 'btn btn-primary' : 'btn btn-outline-secondary';
        document.getElementById('qc_pix_box').style.display = (metodo==='pix') ? '' : 'none';
        document.getElementById('qc_card_box').style.display = (metodo==='card') ? '' : 'none';

        if(metodo==='card'){
            ensureStripe();
        }
    }

    function ensureStripe(){
        if(stripe) return true;
        if(!STRIPE_ENABLED || !STRIPE_PUBLISHABLE_KEY) {
            setError('Stripe não configurado.');
            return false;
        }
        if(typeof Stripe !== 'function') {
            setError('Falha ao carregar Stripe.');
            return false;
        }
        stripe = Stripe(STRIPE_PUBLISHABLE_KEY);
        elements = stripe.elements();
        cardEl = elements.create('card');
        cardEl.mount('#qc_stripe_card');
        return true;
    }

    async function pagar(){
        setError('');

        const email = (document.getElementById('qc_email')?.value || '').toString().trim();
        const nome = (document.getElementById('qc_nome')?.value || '').toString().trim();
        const sobrenome = (document.getElementById('qc_sobrenome')?.value || '').toString().trim();
        const telefone_ddi = getDdi();
        const telefone_numero = (document.getElementById('qc_telefone_numero')?.value || '').toString().trim();
        const data_nascimento = (document.getElementById('qc_data_nascimento')?.value || '').toString().trim();
        const pais = (document.getElementById('qc_pais')?.value || 'BR').toString().toUpperCase();
        const documento = (document.getElementById('qc_documento')?.value || '').toString().trim();
        const senha = (document.getElementById('qc_senha')?.value || '').toString();
        const aceitou_termos = !!document.getElementById('qc_termos')?.checked;

        let valor = parseFloat((document.getElementById('qc_valor_usd')?.value || '0').toString().replace(',','.')) || 0;
        if(valor < MIN_USD) valor = MIN_USD;

        if(!email){ setError('Informe o e-mail'); return; }
        if(!nome || !sobrenome){ setError('Informe nome e sobrenome'); return; }
        if(!telefone_numero){ setError('Informe o telefone'); return; }
        if(!data_nascimento){ setError('Informe a data de nascimento'); return; }
        if(!aceitou_termos){ setError('Você precisa aceitar os termos'); return; }
        if(pais === 'BR'){
            const docDigits = documento.replace(/\D/g,'');
            if(!docDigits || docDigits.length < 11){ setError('CPF/CNPJ é obrigatório para Brasil'); return; }
        }

        if(metodo==='card'){
            if(!ensureStripe()) return;
        }

        const payload = {
            email,
            nome,
            sobrenome,
            telefone_ddi,
            telefone_numero,
            data_nascimento,
            pais,
            documento,
            senha,
            aceitou_termos,
            metodo,
            valor_usd: valor
        };

        const r = await fetch('/clube/recarga/criar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await r.json();
        if(!data || !data.success){
            setError(data && (data.error || data.message) ? (data.error || data.message) : 'Falha ao iniciar pagamento');
            return;
        }

        if(metodo==='pix'){
            const pix = data.pix || {};
            document.getElementById('qc_pix_status').textContent = 'Pagamento gerado. Faça o Pix para concluir.';
            document.getElementById('qc_pix_qr').style.display = '';
            const hosted = (pix.hosted_instructions_url || '').toString();
            document.getElementById('qc_pix_hosted').href = hosted || '#';
            document.getElementById('qc_pix_hosted').style.display = hosted ? '' : 'none';
            document.getElementById('qc_pix_copypaste').value = (pix.copy_paste || '').toString();
            return;
        }

        // Cartão: confirmar no frontend
        const cs = (data.client_secret || '').toString();
        if(!cs){
            setError('Stripe: client_secret ausente');
            return;
        }

        const confirmRes = await stripe.confirmCardPayment(cs, {
            payment_method: { card: cardEl }
        });
        if(confirmRes.error){
            const errEl = document.getElementById('qc_stripe_errors');
            if(errEl){
                errEl.textContent = confirmRes.error.message || 'Pagamento não autorizado';
                errEl.style.display = '';
            }
            return;
        }

        // Sucesso: o webhook credita automaticamente
        alert('Pagamento aprovado. A recarga será creditada em instantes.');
    }

    document.addEventListener('DOMContentLoaded', function(){
        updateEquiv();
        setMetodo('pix');
        syncDdiOutro();
        syncDocumentoRules();

        document.getElementById('qc_valor_usd')?.addEventListener('input', updateEquiv);
        document.getElementById('qc_telefone_ddi')?.addEventListener('change', syncDdiOutro);
        document.getElementById('qc_pais')?.addEventListener('change', syncDocumentoRules);
        document.getElementById('qc_email')?.addEventListener('blur', emailCheck);
        document.getElementById('qc_metodo_pix')?.addEventListener('click', function(){ setMetodo('pix'); });
        document.getElementById('qc_metodo_card')?.addEventListener('click', function(){ setMetodo('card'); });
        document.getElementById('qc_btn_pagar')?.addEventListener('click', pagar);

        document.getElementById('qc_pais_search')?.addEventListener('input', function(e){
            filterSelectOptions(document.getElementById('qc_pais'), e.target.value);
        });
    });
})();
</script>

<?php $content = ob_get_clean(); ?>
<?php $title = 'Recarga do Clube'; ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
