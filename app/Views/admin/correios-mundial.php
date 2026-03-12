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

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><strong>Pedidos (Caixa Fechada) - prontos para etiqueta</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $pedidos = isset($pedidos) && is_array($pedidos) ? $pedidos : []; ?>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="4" class="text-muted">Nenhum pedido aguardando etiqueta.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $p): ?>
                                <?php $pid = (int) ($p['pedido_id'] ?? 0); ?>
                                <tr>
                                    <td>#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) ?></td>
                                    <td><?= !empty($p['created_at']) ? date('d/m/Y H:i', strtotime((string) $p['created_at'])) : '-' ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="/admin/correios-mundial/pedido/<?= $pid ?>">Abrir</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="/admin/pedidos/detalhes/<?= $pid ?>" target="_blank">Pedido</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong>Etiquetas geradas (PACKET)</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Rastreio</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $etiquetas = isset($etiquetas) && is_array($etiquetas) ? $etiquetas : []; ?>
                        <?php if (empty($etiquetas)): ?>
                            <tr><td colspan="4" class="text-muted">Nenhuma etiqueta gerada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($etiquetas as $e): ?>
                                <?php $pid = (int) ($e['pedido_id'] ?? 0); ?>
                                <tr>
                                    <td><a href="/admin/correios-mundial/pedido/<?= $pid ?>">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></a></td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) ($e['tracking_number'] ?? '')) ?></td>
                                    <td><?= !empty($e['created_at']) ? date('d/m/Y H:i', strtotime((string) $e['created_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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
