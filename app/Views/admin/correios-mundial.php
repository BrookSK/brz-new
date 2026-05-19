<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Correios Mundial (PACKET)</h1>
        <div class="d-flex gap-2 align-items-center">
            <div class="input-group input-group-sm" style="width:180px">
                <input type="text" class="form-control" id="buscarPedidoPacket" placeholder="Nº pedido..." onkeydown="if(event.key==='Enter'){irParaPedidoPacket();event.preventDefault();}">
                <button class="btn btn-outline-primary" type="button" onclick="irParaPedidoPacket()"><i class="fas fa-search"></i></button>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/containers">Containers</a>
            <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/faturas">Faturas (CN38)</a>
        </div>
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

    <?php
        $cmPerfil = '';
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $cmPerfil = (string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_role'] ?? ''));
        } catch (\Exception $e) {
            $cmPerfil = '';
        }
        $cmPerfil = strtolower(trim($cmPerfil));
        $cmIsRedirecionador = ($cmPerfil === 'redirecionador');
    ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><strong>Pedidos (Caixa Fechada) - prontos para etiqueta</strong></div>
        <div class="card-body">
            <!-- Desktop: Table -->
            <div class="table-responsive d-none d-md-block">
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
                                        <?php if (!$cmIsRedirecionador): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="/admin/pedidos/detalhes/<?= $pid ?>" target="_blank">Pedido</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile: Cards -->
            <div class="d-md-none">
                <?php if (empty($pedidos)): ?>
                    <div class="text-muted small py-3">Nenhum pedido aguardando etiqueta.</div>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <?php $pid = (int) ($p['pedido_id'] ?? 0); ?>
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold small">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></span>
                                    <span class="small ms-2" style="word-break:break-word;"><?= htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) ?></span>
                                </div>
                                <a class="btn btn-sm btn-primary py-0 px-2" href="/admin/correios-mundial/pedido/<?= $pid ?>">Abrir</a>
                            </div>
                            <div class="text-muted small"><?= !empty($p['created_at']) ? date('d/m/Y H:i', strtotime((string) $p['created_at'])) : '' ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Etiquetas geradas (PACKET)</strong>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-success" onclick="exportarCsvSelecionadas()" id="btnExportarCsv" disabled><i class="fas fa-file-csv me-1"></i>Exportar selecionadas</button>
                <button class="btn btn-sm btn-warning" onclick="regerarSelecionadas()" id="btnRegerarMassa" disabled><i class="fas fa-redo me-1"></i>Regerar selecionadas</button>
            </div>
        </div>
        <div class="card-body">
            <!-- Desktop: Table -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAllPacket" onclick="toggleAllPacket(this)"></th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Rastreio</th>
                            <th>Etiqueta</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $etiquetas = isset($etiquetas) && is_array($etiquetas) ? $etiquetas : []; ?>
                        <?php if (empty($etiquetas)): ?>
                            <tr><td colspan="7" class="text-muted">Nenhuma etiqueta gerada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($etiquetas as $e): ?>
                                <?php $pid = (int) ($e['pedido_id'] ?? 0); ?>
                                <?php $trk = (string) ($e['tracking_number'] ?? ''); ?>
                                <tr>
                                    <td><input type="checkbox" class="packet-check" value="<?= $pid ?>" onchange="updateBtnMassa()"></td>
                                    <td><a href="/admin/correios-mundial/pedido/<?= $pid ?>">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></a></td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars($trk) ?></td>
                                    <td>
                                        <?php if ($trk !== ''): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/etiqueta/<?= rawurlencode($trk) ?>.pdf" target="_blank">PDF</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($e['created_at']) ? date('d/m/Y H:i', strtotime((string) $e['created_at'])) : '-' ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="regerarPacket(<?= $pid ?>)" title="Regerar etiqueta"><i class="fas fa-redo"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile: Cards -->
            <div class="d-md-none">
                <?php if (empty($etiquetas)): ?>
                    <div class="text-muted small py-3">Nenhuma etiqueta gerada.</div>
                <?php else: ?>
                    <?php foreach ($etiquetas as $e): ?>
                        <?php $pid = (int) ($e['pedido_id'] ?? 0); ?>
                        <?php $trk = (string) ($e['tracking_number'] ?? ''); ?>
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="min-width:0;flex:1;">
                                    <span class="fw-bold small">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></span>
                                    <span class="small ms-1" style="word-break:break-word;"><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></span>
                                    <?php if ($trk !== ''): ?>
                                        <div class="text-muted small" style="word-break:break-all;"><?= htmlspecialchars($trk) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($trk !== ''): ?>
                                    <a class="btn btn-sm btn-outline-primary py-0 px-2 ms-2" href="/admin/correios-mundial/etiqueta/<?= rawurlencode($trk) ?>.pdf" target="_blank">PDF</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

function irParaPedidoPacket(){
    var v = document.getElementById('buscarPedidoPacket').value.replace(/\D/g,'');
    if(v===''){alert('Digite o número do pedido');return;}
    window.location.href='/admin/correios-mundial/pedido/'+parseInt(v,10);
}

function toggleAllPacket(el) {
    document.querySelectorAll('.packet-check').forEach(cb => cb.checked = el.checked);
    updateBtnMassa();
}

function updateBtnMassa() {
    const checked = document.querySelectorAll('.packet-check:checked').length;
    const btn = document.getElementById('btnRegerarMassa');
    const btnExport = document.getElementById('btnExportarCsv');
    if (btn) {
        btn.disabled = checked === 0;
        btn.textContent = checked > 0 ? 'Regerar selecionadas (' + checked + ')' : 'Regerar selecionadas';
    }
    if (btnExport) {
        btnExport.disabled = checked === 0;
        btnExport.innerHTML = checked > 0
            ? '<i class="fas fa-file-csv me-1"></i>Exportar selecionadas (' + checked + ')'
            : '<i class="fas fa-file-csv me-1"></i>Exportar selecionadas';
    }
}

async function regerarPacket(pedidoId) {
    if (!confirm('Regerar etiqueta do pedido #' + pedidoId + '? A etiqueta atual será deletada e uma nova será gerada.')) return;
    try {
        const r = await fetch('/admin/correios-mundial/pedido/' + pedidoId + '/regerar-etiqueta', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await r.json();
        if (data && data.success) {
            alert('Etiqueta regerada! Novo rastreio: ' + (data.tracking_number || ''));
            location.reload();
        } else {
            alert('Erro: ' + (data.error || data.message || 'Falha ao regerar'));
        }
    } catch (e) {
        alert('Erro de rede: ' + e.message);
    }
}

async function regerarSelecionadas() {
    const checks = document.querySelectorAll('.packet-check:checked');
    if (checks.length === 0) return;
    const ids = Array.from(checks).map(cb => parseInt(cb.value));
    if (!confirm('Regerar etiquetas de ' + ids.length + ' pedido(s) selecionados? As etiquetas atuais serão deletadas e novas serão geradas.')) return;

    const btn = document.getElementById('btnRegerarMassa');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Regerando...'; }

    let ok = 0, erros = [];
    for (const pid of ids) {
        try {
            const r = await fetch('/admin/correios-mundial/pedido/' + pid + '/regerar-etiqueta', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await r.json();
            if (data && data.success) {
                ok++;
            } else {
                erros.push('#' + pid + ': ' + (data.error || 'erro'));
            }
        } catch (e) {
            erros.push('#' + pid + ': ' + e.message);
        }
    }

    let msg = ok + ' etiqueta(s) regerada(s) com sucesso.';
    if (erros.length > 0) msg += '\n\nErros:\n' + erros.join('\n');
    alert(msg);
    location.reload();
}

async function exportarCsvSelecionadas() {
    const checks = document.querySelectorAll('.packet-check:checked');
    if (checks.length === 0) return;
    const ids = Array.from(checks).map(cb => parseInt(cb.value));

    const btn = document.getElementById('btnExportarCsv');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exportando...'; }

    try {
        const r = await fetch('/admin/correios-mundial/exportar-csv', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        });
        const data = await r.json();
        if (data && data.success) {
            // Download wp_posts.csv
            downloadCsvFile('wp_posts.csv', data.wp_posts_csv);
            // Pequeno delay para não conflitar downloads
            setTimeout(function() {
                downloadCsvFile('wp_postmeta.csv', data.wp_postmeta_csv);
            }, 500);
            alert('Exportação concluída! ' + data.total_etiquetas + ' etiqueta(s) exportada(s).\nOs arquivos wp_posts.csv e wp_postmeta.csv foram baixados.');
        } else {
            alert('Erro: ' + (data.error || data.message || 'Falha ao exportar'));
        }
    } catch (e) {
        alert('Erro de rede: ' + e.message);
    }

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-csv me-1"></i>Exportar selecionadas'; }
    updateBtnMassa();
}

function downloadCsvFile(filename, csvContent) {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
