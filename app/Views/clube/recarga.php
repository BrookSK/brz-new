<?php
$siteLogo = '';
try {
    $raw = '';
    $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
    foreach ($tablesToTry as $t) {
        if ($raw !== '') break;
        try {
            $pdo = \Config\Database::getConnection();
            $stmtT = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmtT->execute([$t]);
            if (!$stmtT->fetchColumn()) {
                continue;
            }
            $stmtCols = $pdo->query('DESCRIBE ' . $t);
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            if (!is_array($cols)) {
                $cols = [];
            }
            if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                if ($valCol !== '') {
                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                    $stmt->execute(['layout', 'logo']);
                    $raw = (string) ($stmt->fetchColumn() ?: '');
                    if ($raw !== '') break;
                }
            }
            $keyCol = '';
            if (in_array('chave', $cols, true)) $keyCol = 'chave';
            elseif (in_array('key', $cols, true)) $keyCol = 'key';
            elseif (in_array('nome', $cols, true)) $keyCol = 'nome';
            elseif (in_array('config_key', $cols, true)) $keyCol = 'config_key';
            $valCol = '';
            if (in_array('valor', $cols, true)) $valCol = 'valor';
            elseif (in_array('value', $cols, true)) $valCol = 'value';
            elseif (in_array('conteudo', $cols, true)) $valCol = 'conteudo';
            if ($keyCol !== '' && $valCol !== '') {
                $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                $stmt->execute(['layout_logo']);
                $raw = (string) ($stmt->fetchColumn() ?: '');
                if ($raw !== '') break;
            }
            if (in_array('layout_logo', $cols, true)) {
                $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                $stmt2 = $pdo->query('SELECT layout_logo AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                $raw = (string) ($stmt2 ? ($stmt2->fetchColumn() ?: '') : '');
                if ($raw !== '') break;
            }
        } catch (\Exception $e) {
        }
    }
    $siteLogo = is_string($raw) ? trim($raw) : '';
} catch (\Exception $e) {
    $siteLogo = '';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recarga do Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; }
    </style>
</head>
<body>

<div class="container" style="padding: 22px 0 0;">
    <div class="text-center mb-3">
        <?php if (!empty($siteLogo)): ?>
            <img src="<?= htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Braziliana" style="max-height: 52px; max-width: 100%; object-fit: contain;">
        <?php else: ?>
            <div style="font-weight:800; color:#0b1f3a; font-size: 20px;">Braziliana</div>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php if (!empty($clube_cap_reached)): ?>
                <div class="alert alert-warning" style="border-radius:14px;">
                    <div class="fw-bold">Recargas temporariamente suspensas</div>
                    <div class="small text-muted">O limite de captação do Clube foi atingido. Novas recargas pelo checkout rápido estão suspensas.</div>
                </div>
            <?php endif; ?>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h1 class="h4 mb-1" style="color:#0b1f3a; font-weight: 800;">Recarga do Clube</h1>
                    <div class="text-muted">Checkout rápido (sem login)</div>
                </div>
                <div class="small text-muted">
                    <a href="/como-funciona-clube" class="text-decoration-none">Como funciona o Clube</a>
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
                                    <div class="d-flex justify-content-center" id="qc_pix_img_wrap" style="display:none;">
                                        <img id="qc_pix_img" alt="QR Code Pix" style="max-width: 220px; width: 100%; height: auto; border-radius: 10px; border: 1px solid rgba(11,31,58,0.14); background: #fff; padding: 10px;" />
                                    </div>
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
                                    Concordo que li os termos de como funciona o Clube Brasiliana: <a href="/como-funciona-clube" target="_blank" class="text-primary text-decoration-none">Como funciona o Clube Brasiliana</a>.
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
                                <div class="small text-muted">Equivalente em BRL</div>
                                <div class="h5 mb-0" id="qc_equiv_brl">-</div>
                                <div class="small text-muted mt-1" id="qc_pix_fee">-</div>
                                <div class="small" style="font-weight:700; color:#0b1f3a;" id="qc_pix_total">-</div>
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

    let recargaPollTimer = null;
    let paymentLocked = false;

    function lockPaymentUi(){
        paymentLocked = true;
        const btnPix = document.getElementById('qc_metodo_pix');
        const btnCard = document.getElementById('qc_metodo_card');
        const inputValor = document.getElementById('qc_valor_usd');
        if(btnPix) btnPix.disabled = true;
        if(btnCard) btnCard.disabled = true;
        if(inputValor) inputValor.disabled = true;
    }

    function startRecargaPolling(recargaId, token){
        if(recargaPollTimer) {
            clearInterval(recargaPollTimer);
            recargaPollTimer = null;
        }
        if(!recargaId || !token) return;

        lockPaymentUi();

        const btn = document.getElementById('qc_btn_pagar');
        if(btn){
            btn.disabled = true;
            btn.textContent = 'Aguardando confirmação';
        }

        recargaPollTimer = setInterval(async function(){
            try{
                const r = await fetch('/clube/recarga/status?recarga_id=' + encodeURIComponent(recargaId) + '&token=' + encodeURIComponent(token));
                const data = await r.json();
                if(data && data.success && data.is_paid && data.redirect_url){
                    window.location.href = data.redirect_url;
                }
            }catch(e){}
        }, 4000);
    }

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

    function parseDateYmd(ymd){
        ymd = (ymd || '').toString().trim();
        if(!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return null;
        const parts = ymd.split('-').map(p => parseInt(p, 10));
        const y = parts[0], m = parts[1], d = parts[2];
        if(!y || !m || !d) return null;
        const dt = new Date(Date.UTC(y, m - 1, d));
        // valida se não virou (ex: 2025-02-31)
        if(dt.getUTCFullYear() !== y || (dt.getUTCMonth() + 1) !== m || dt.getUTCDate() !== d) return null;
        return dt;
    }

    function validateAdultDob(ymd){
        const dt = parseDateYmd(ymd);
        if(!dt) return { ok:false, error:'Data de nascimento inválida' };
        const now = new Date();
        const year = dt.getUTCFullYear();
        if(year < 1900) return { ok:false, error:'Data de nascimento inválida' };
        if(dt.getTime() > now.getTime()) return { ok:false, error:'Data de nascimento inválida' };

        let age = now.getFullYear() - year;
        const m = (now.getMonth() + 1) - (dt.getUTCMonth() + 1);
        if(m < 0 || (m === 0 && now.getDate() < dt.getUTCDate())) age--;
        if(age < 18) return { ok:false, error:'Você precisa ter no mínimo 18 anos' };
        return { ok:true };
    }

    function isValidCpf(cpf){
        cpf = (cpf || '').toString().replace(/\D/g,'');
        if(cpf.length !== 11) return false;
        if(/^([0-9])\1{10}$/.test(cpf)) return false;
        const nums = cpf.split('').map(n => parseInt(n,10));
        let sum = 0;
        for(let i=0, w=10; i<9; i++, w--) sum += nums[i] * w;
        let d1 = 11 - (sum % 11);
        if(d1 >= 10) d1 = 0;
        if(nums[9] !== d1) return false;
        sum = 0;
        for(let i=0, w=11; i<10; i++, w--) sum += nums[i] * w;
        let d2 = 11 - (sum % 11);
        if(d2 >= 10) d2 = 0;
        return nums[10] === d2;
    }

    function isValidCnpj(cnpj){
        cnpj = (cnpj || '').toString().replace(/\D/g,'');
        if(cnpj.length !== 14) return false;
        if(/^([0-9])\1{13}$/.test(cnpj)) return false;
        const nums = cnpj.split('').map(n => parseInt(n,10));
        const w1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        let sum = 0;
        for(let i=0;i<12;i++) sum += nums[i] * w1[i];
        let r = sum % 11;
        let d1 = (r < 2) ? 0 : (11 - r);
        if(nums[12] !== d1) return false;
        const w2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
        sum = 0;
        for(let i=0;i<13;i++) sum += nums[i] * w2[i];
        r = sum % 11;
        let d2 = (r < 2) ? 0 : (11 - r);
        return nums[13] === d2;
    }

    function validateCpfCnpj(doc){
        const d = (doc || '').toString().replace(/\D/g,'');
        if(d.length === 11) return isValidCpf(d);
        if(d.length === 14) return isValidCnpj(d);
        return false;
    }

    function updateEquiv(){
        const v = parseFloat((document.getElementById('qc_valor_usd')?.value || '0').toString().replace(',','.')) || 0;
        const usd = Math.max(v, MIN_USD);
        const brl = usd * USD_BRL_RATE;
        const feeRate = 0.035;
        const fee = brl * feeRate;
        const totalPix = brl + fee;
        document.getElementById('qc_equiv_brl').textContent = 'R$ ' + brl.toFixed(2).replace('.', ',');
        document.getElementById('qc_pix_fee').textContent = 'Taxa Stripe (3,5%): R$ ' + fee.toFixed(2).replace('.', ',');
        document.getElementById('qc_pix_total').textContent = 'Valor final no Pix: R$ ' + totalPix.toFixed(2).replace('.', ',');
        document.getElementById('qc_rate_text').textContent = 'Taxa: 1 USD = R$ ' + USD_BRL_RATE.toFixed(2).replace('.', ',');
        document.getElementById('qc_rate_hint').textContent = 'Mínimo: $' + MIN_USD.toFixed(2);
    }

    function clampValorMinimo(){
        const inputEl = document.getElementById('qc_valor_usd');
        if(!inputEl) return;
        let v = parseFloat((inputEl.value || '0').toString().replace(',','.')) || 0;
        if(v + 0.00001 < MIN_USD){
            inputEl.value = MIN_USD.toFixed(2);
        }
        updateEquiv();
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
        if(paymentLocked) {
            return;
        }
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
        cardEl = elements.create('card', { hidePostalCode: true });
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
        if(valor + 0.00001 < MIN_USD){
            clampValorMinimo();
            setError('O valor mínimo é $' + MIN_USD.toFixed(2));
            return;
        }

        if(!email){ setError('Informe o e-mail'); return; }
        if(!nome || !sobrenome){ setError('Informe nome e sobrenome'); return; }
        if(!telefone_numero){ setError('Informe o telefone'); return; }
        if(!data_nascimento){ setError('Informe a data de nascimento'); return; }
        const dobCheck = validateAdultDob(data_nascimento);
        if(!dobCheck.ok){ setError(dobCheck.error || 'Data de nascimento inválida'); return; }
        if(!aceitou_termos){ setError('Você precisa aceitar os termos'); return; }
        if(pais === 'BR'){
            const docDigits = documento.replace(/\D/g,'');
            if(!docDigits || docDigits.length < 11){ setError('CPF/CNPJ é obrigatório para Brasil'); return; }
            if(!validateCpfCnpj(docDigits)){ setError('CPF/CNPJ inválido'); return; }
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
            valor_usd: valor,
            usd_brl_rate: USD_BRL_RATE
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
            const imgUrl = (pix.image_url_png || pix.image_url_svg || '').toString();
            const imgWrap = document.getElementById('qc_pix_img_wrap');
            const imgEl = document.getElementById('qc_pix_img');
            if(imgWrap && imgEl){
                if(imgUrl){
                    imgEl.src = imgUrl;
                    imgWrap.style.display = '';
                } else {
                    imgEl.removeAttribute('src');
                    imgWrap.style.display = 'none';
                }
            }
            document.getElementById('qc_pix_copypaste').value = (pix.copy_paste || '').toString();

            document.getElementById('qc_pix_status').textContent = 'Pagamento gerado. Após você pagar o Pix, esta página vai confirmar automaticamente e abrir o comprovante.';
            startRecargaPolling(data.recarga_id, data.public_token);
            return;
        }

        // Cartão: confirmar no frontend
        const cs = (data.client_secret || '').toString();
        if(!cs){
            setError('Stripe: client_secret ausente');
            return;
        }

        const confirmRes = await stripe.confirmCardPayment(cs, {
            payment_method: { 
                card: cardEl,
                billing_details: {
                    name: (nome + ' ' + sobrenome).trim(),
                    email: email
                }
            }
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
        const okMsg = document.getElementById('qc_pix_status');
        if(okMsg){
            okMsg.textContent = 'Pagamento aprovado no cartão. Estamos confirmando e vamos abrir o comprovante automaticamente.';
        }
        startRecargaPolling(data.recarga_id, data.public_token);
    }

    document.addEventListener('DOMContentLoaded', function(){
        const CAP_REACHED = <?= json_encode(!empty($clube_cap_reached)) ?>;
        if(CAP_REACHED){
            const btn = document.getElementById('qc_btn_pagar');
            if(btn){
                btn.disabled = true;
                btn.textContent = 'Recargas suspensas';
            }
        }
        updateEquiv();
        setMetodo('pix');
        syncDdiOutro();
        syncDocumentoRules();

        document.getElementById('qc_valor_usd')?.addEventListener('input', updateEquiv);
        document.getElementById('qc_valor_usd')?.addEventListener('blur', clampValorMinimo);
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

</body>
</html>
