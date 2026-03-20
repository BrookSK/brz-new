/**
 * cliente-form.js
 * Validação de CPF, idade mínima 18 anos e autocomplete de CEP via ViaCEP.
 * Usado no modal de cadastro de cliente do módulo Redirecionamento.
 */

// ── Validação CPF ────────────────────────────────────────────────────────────
function validarCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    let soma = 0;
    for (let i = 0; i < 9; i++) soma += parseInt(cpf[i]) * (10 - i);
    let r = (soma * 10) % 11;
    if (r === 10 || r === 11) r = 0;
    if (r !== parseInt(cpf[9])) return false;
    soma = 0;
    for (let i = 0; i < 10; i++) soma += parseInt(cpf[i]) * (11 - i);
    r = (soma * 10) % 11;
    if (r === 10 || r === 11) r = 0;
    return r === parseInt(cpf[10]);
}

// ── Máscara CPF ──────────────────────────────────────────────────────────────
function mascaraCPF(input) {
    input.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
        else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
        else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
        this.value = v;
    });
}

// ── Validação idade mínima ───────────────────────────────────────────────────
function validarIdadeMinima(dataNasc, idadeMin = 18) {
    if (!dataNasc) return false;
    const nasc = new Date(dataNasc);
    const hoje = new Date();
    const limite = new Date(nasc);
    limite.setFullYear(limite.getFullYear() + idadeMin);
    return hoje >= limite;
}

// ── Autocomplete CEP via ViaCEP ──────────────────────────────────────────────
function initCepAutocomplete(inputCep, spinnerEl, fields) {
    // fields: { logradouro, bairro, cidade, estado }
    inputCep.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 8);
        if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
        this.value = v;
    });

    inputCep.addEventListener('blur', async function () {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        if (spinnerEl) spinnerEl.style.display = '';
        try {
            const r = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
            const d = await r.json();
            if (!d.erro) {
                if (fields.logradouro) fields.logradouro.value = d.logradouro || '';
                if (fields.bairro)     fields.bairro.value     = d.bairro     || '';
                if (fields.cidade)     fields.cidade.value     = d.localidade || '';
                if (fields.estado)     fields.estado.value     = d.uf         || '';
                // Focar no número após preencher
                if (fields.numero) fields.numero.focus();
            }
        } catch (e) { /* silencioso */ }
        finally { if (spinnerEl) spinnerEl.style.display = 'none'; }
    });
}

// ── Inicializar modal de novo cliente ────────────────────────────────────────
function initModalNovoCliente(opts) {
    // opts: { prefixo } — prefixo dos IDs dos campos (ex: 'nc')
    const p = opts.prefixo || 'nc';
    const get = id => document.getElementById(id);

    const cpfInput  = get(p + 'Cpf');
    const nascInput = get(p + 'Nasc');
    const cepInput  = get(p + 'Cep');
    const spinner   = get(p + 'CepSpinner');

    if (cpfInput)  mascaraCPF(cpfInput);
    if (cepInput)  initCepAutocomplete(cepInput, spinner, {
        logradouro: get(p + 'Logr'),
        bairro:     get(p + 'Bairro'),
        cidade:     get(p + 'Cidade'),
        estado:     get(p + 'Estado'),
        numero:     get(p + 'Num'),
    });

    // Validação inline CPF
    if (cpfInput) {
        cpfInput.addEventListener('blur', function () {
            const cpf = this.value.replace(/\D/g, '');
            const fb  = get(p + 'CpfFeedback');
            if (cpf && !validarCPF(cpf)) {
                this.classList.add('is-invalid');
                if (fb) fb.textContent = 'CPF inválido.';
            } else {
                this.classList.remove('is-invalid');
                if (fb) fb.textContent = '';
            }
        });
    }

    // Validação inline data nascimento
    if (nascInput) {
        nascInput.addEventListener('change', function () {
            const fb = get(p + 'NascFeedback');
            if (this.value && !validarIdadeMinima(this.value, 18)) {
                this.classList.add('is-invalid');
                if (fb) fb.textContent = 'O cliente deve ter pelo menos 18 anos.';
            } else {
                this.classList.remove('is-invalid');
                if (fb) fb.textContent = '';
            }
        });
    }

    // Retorna função de validação para usar antes de salvar
    return function validarFormCliente() {
        let ok = true;

        const nome = get(p + 'Nome');
        if (!nome?.value.trim()) {
            nome?.classList.add('is-invalid');
            ok = false;
        } else {
            nome?.classList.remove('is-invalid');
        }

        if (cpfInput) {
            const cpf = cpfInput.value.replace(/\D/g, '');
            const fb  = get(p + 'CpfFeedback');
            if (!cpf) {
                cpfInput.classList.add('is-invalid');
                if (fb) fb.textContent = 'CPF obrigatório.';
                ok = false;
            } else if (!validarCPF(cpf)) {
                cpfInput.classList.add('is-invalid');
                if (fb) fb.textContent = 'CPF inválido.';
                ok = false;
            } else {
                cpfInput.classList.remove('is-invalid');
                if (fb) fb.textContent = '';
            }
        }

        if (nascInput) {
            const fb = get(p + 'NascFeedback');
            if (!nascInput.value) {
                nascInput.classList.add('is-invalid');
                if (fb) fb.textContent = 'Data de nascimento obrigatória.';
                ok = false;
            } else if (!validarIdadeMinima(nascInput.value, 18)) {
                nascInput.classList.add('is-invalid');
                if (fb) fb.textContent = 'O cliente deve ter pelo menos 18 anos.';
                ok = false;
            } else {
                nascInput.classList.remove('is-invalid');
                if (fb) fb.textContent = '';
            }
        }

        const cidade = get(p + 'Cidade');
        if (!cidade?.value.trim()) {
            cidade?.classList.add('is-invalid');
            ok = false;
        } else {
            cidade?.classList.remove('is-invalid');
        }

        return ok;
    };
}
