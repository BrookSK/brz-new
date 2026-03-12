<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Correios Mundial (PACKET)</h1>
    </div>

    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Saldo atual</div>
                    <div class="h4 mb-0" id="cm_balance">-</div>
                    <div class="small text-muted mt-1" id="cm_balance_hint">Carregando...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-danger" id="cm_error" style="display:none;"></div>
</div>

<script>
(function(){
    function setError(msg){
        const el = document.getElementById('cm_error');
        if(!el) return;
        el.textContent = msg || '';
        el.style.display = msg ? '' : 'none';
    }

    function setHint(msg){
        const el = document.getElementById('cm_balance_hint');
        if(!el) return;
        el.textContent = msg || '';
    }

    function setBalance(v){
        const el = document.getElementById('cm_balance');
        if(!el) return;
        if(v === null || v === undefined || v === ''){
            el.textContent = '-';
            return;
        }
        let num = null;
        if(typeof v === 'number'){
            num = v;
        } else {
            const s = v.toString().replace(/[^0-9,\.\-]/g,'').replace(',','.');
            const p = parseFloat(s);
            if(!isNaN(p)) num = p;
        }
        if(num === null){
            el.textContent = v.toString();
            return;
        }
        el.textContent = 'R$ ' + num.toFixed(2).replace('.', ',');
    }

    async function loadBalance(){
        setError('');
        setHint('Carregando...');
        try{
            const r = await fetch('/admin/correios-mundial/balance', { headers: { 'Accept': 'application/json' } });
            const data = await r.json();
            if(!data || !data.success){
                setBalance('-');
                setHint('Falha ao carregar');
                setError((data && (data.error || data.message)) ? (data.error || data.message) : 'Falha ao consultar saldo');
                return;
            }
            setBalance(data.currentBalance);
            setHint('Atualizado agora');
        }catch(e){
            setBalance('-');
            setHint('Falha ao carregar');
            setError('Falha ao consultar saldo');
        }
    }

    document.addEventListener('DOMContentLoaded', loadBalance);
})();
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
