<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><?= __('admin.correios_mundial.title','Correios Mundial (PACKET)') ?></h1>
        <div class="d-flex gap-2 align-items-center">
            <div class="input-group input-group-sm" style="width:180px">
                <input type="text" class="form-control" id="buscarPedidoPacket" placeholder="<?= htmlspecialchars(__('admin.correios_mundial.order_number_placeholder','Nº pedido...'), ENT_QUOTES, 'UTF-8') ?>" onkeydown="if(event.key==='Enter'){irParaPedidoPacket();event.preventDefault();}">
                <button class="btn btn-outline-primary" type="button" onclick="irParaPedidoPacket()"><i class="fas fa-search"></i></button>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/containers"><?= __('admin.correios_mundial.containers','Containers') ?></a>
            <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/faturas"><?= __('admin.correios_mundial.invoices_cn38','Faturas (CN38)') ?></a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= __('admin.correios_mundial.current_balance','Saldo atual') ?></div>
                    <div class="h4 mb-0" id="cm_balance">-</div>
                    <div class="small text-muted mt-1" id="cm_balance_hint"><?= __('admin.correios_mundial.loading','Carregando...') ?></div>
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?= __('admin.correios_mundial.closed_box_orders','Pedidos (Caixa Fechada) - prontos para etiqueta') ?></strong>
            <?php if (!empty($pedidos)): ?>
                <button class="btn btn-sm btn-warning" id="btnToggleMassa" onclick="toggleModoMassa()"><i class="fas fa-bolt me-1"></i><?= __('admin.correios_mundial.bulk_generate','Gerar em Massa') ?></button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <!-- Desktop: Table Normal -->
            <div class="table-responsive d-none d-md-block" id="tabelaNormal">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><?= __('admin.correios_mundial.col_order','Pedido') ?></th>
                            <th><?= __('admin.correios_mundial.col_customer','Cliente') ?></th>
                            <th><?= __('admin.correios_mundial.col_date','Data') ?></th>
                            <th><?= __('admin.correios_mundial.col_action','Ação') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $pedidos = isset($pedidos) && is_array($pedidos) ? $pedidos : []; ?>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="4" class="text-muted"><?= __('admin.correios_mundial.no_pending_orders','Nenhum pedido aguardando etiqueta.') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $p): ?>
                                <?php $pid = (int) ($p['pedido_id'] ?? 0); ?>
                                <tr>
                                    <td>#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) ?></td>
                                    <td><?= !empty($p['created_at']) ? date('d/m/Y H:i', strtotime((string) $p['created_at'])) : '-' ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="/admin/correios-mundial/pedido/<?= $pid ?>"><?= __('admin.correios_mundial.open','Abrir') ?></a>
                                        <?php if (!$cmIsRedirecionador): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="/admin/pedidos/detalhes/<?= $pid ?>" target="_blank"><?= __('admin.correios_mundial.order','Pedido') ?></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Desktop: Table Modo Massa (hidden by default) -->
            <div class="table-responsive d-none" id="tabelaMassa">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <input type="checkbox" id="checkAllMassa" onclick="toggleAllMassaCf(this)" class="form-check-input me-2">
                        <label for="checkAllMassa" class="form-check-label small"><?= __('admin.correios_mundial.select_all','Selecionar todos') ?></label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-success" id="btnGerarMassa" onclick="gerarEtiquetasMassa()" disabled>
                            <i class="fas fa-tags me-1"></i><?= __('admin.correios_mundial.generate_labels','Gerar Etiquetas') ?> (<span id="massaCount">0</span>)
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleModoMassa()"><?= __('admin.correios_mundial.back','Voltar') ?></button>
                    </div>
                </div>
                <table class="table table-sm align-middle table-bordered" style="font-size:.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px;"></th>
                            <th><?= __('admin.correios_mundial.col_order','Pedido') ?></th>
                            <th><?= __('admin.correios_mundial.col_customer','Cliente') ?></th>
                            <th><?= __('admin.correios_mundial.col_weight_kg','Peso (kg)') ?></th>
                            <th><?= __('admin.correios_mundial.col_dimensions','L×A×C (cm)') ?></th>
                            <th><?= __('admin.correios_mundial.col_freight_usd','Frete USD') ?></th>
                            <th><?= __('admin.correios_mundial.col_data_ok','Dados OK') ?></th>
                            <th><?= __('admin.correios_mundial.col_status','Status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="8" class="text-muted"><?= __('admin.correios_mundial.no_orders','Nenhum pedido.') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $p): ?>
                                <?php
                                    $pid = (int) ($p['pedido_id'] ?? 0);
                                    $peso = isset($p['peso_total']) ? (float) $p['peso_total'] : 0;
                                    $alt = isset($p['altura']) ? (float) $p['altura'] : 0;
                                    $larg = isset($p['largura']) ? (float) $p['largura'] : 0;
                                    $comp = isset($p['comprimento']) ? (float) $p['comprimento'] : 0;
                                    $frete = 0.01;
                                    $temPeso = $peso > 0;
                                    $temDim = ($alt >= 2 && $larg >= 11 && $comp >= 16);
                                    $temEmail = !empty($p['cliente_email']);
                                    $temTel = !empty($p['cliente_telefone']);
                                    $temCpf = !empty($p['cliente_cpf']);
                                    $dadosOk = $temPeso && $temDim && $temEmail && $temTel && $temCpf;
                                ?>
                                <tr data-pid="<?= $pid ?>" data-ok="<?= $dadosOk ? '1' : '0' ?>">
                                    <td><input type="checkbox" class="form-check-input massa-check" value="<?= $pid ?>" onchange="updateMassaCount()" <?= !$dadosOk ? 'disabled title="Dados incompletos"' : '' ?>></td>
                                    <td><a href="/admin/correios-mundial/pedido/<?= $pid ?>" target="_blank">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></a></td>
                                    <td><?= htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) ?></td>
                                    <td class="<?= $temPeso ? '' : 'text-danger fw-bold' ?>"><?= $temPeso ? number_format($peso, 2, ',', '.') : '⚠ 0' ?></td>
                                    <td class="<?= $temDim ? '' : 'text-danger' ?>"><?= ($larg > 0 ? (int)$larg : '?') ?>×<?= ($alt > 0 ? (int)$alt : '?') ?>×<?= ($comp > 0 ? (int)$comp : '?') ?></td>
                                    <td><?= $frete > 0 ? number_format($frete, 2, '.', '') : '-' ?></td>
                                    <td>
                                        <?php if ($dadosOk): ?>
                                            <span class="badge bg-success"><?= __('admin.correios_mundial.ok','OK') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" title="<?= htmlspecialchars(implode(', ', array_filter([!$temPeso?__('admin.correios_mundial.field_weight','Peso'):'', !$temDim?__('admin.correios_mundial.field_dimensions','Dimensões'):'', !$temEmail?__('admin.correios_mundial.field_email','Email'):'', !$temTel?__('admin.correios_mundial.field_phone','Telefone'):'', !$temCpf?__('admin.correios_mundial.field_cpf','CPF'):''])), ENT_QUOTES, 'UTF-8') ?>"><?= __('admin.correios_mundial.incomplete','Incompleto') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="massa-status text-muted">-</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: Cards -->
            <div class="d-md-none">
                <?php if (empty($pedidos)): ?>
                    <div class="text-muted small py-3"><?= __('admin.correios_mundial.no_pending_orders','Nenhum pedido aguardando etiqueta.') ?></div>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <?php $pid = (int) ($p['pedido_id'] ?? 0); ?>
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold small">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></span>
                                    <span class="small ms-2" style="word-break:break-word;"><?= htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) ?></span>
                                </div>
                                <a class="btn btn-sm btn-primary py-0 px-2" href="/admin/correios-mundial/pedido/<?= $pid ?>"><?= __('admin.correios_mundial.open','Abrir') ?></a>
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
            <strong><?= __('admin.correios_mundial.generated_labels','Etiquetas geradas (PACKET)') ?></strong>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-success" onclick="exportarCsvSelecionadas()" id="btnExportarCsv" disabled><i class="fas fa-file-csv me-1"></i><?= __('admin.correios_mundial.export_selected','Exportar selecionadas') ?></button>
                <button class="btn btn-sm btn-warning" onclick="regerarSelecionadas()" id="btnRegerarMassa" disabled><i class="fas fa-redo me-1"></i><?= __('admin.correios_mundial.regenerate_selected','Regerar selecionadas') ?></button>
            </div>
        </div>
        <div class="card-body">
            <!-- Desktop: Table -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAllPacket" onclick="toggleAllPacket(this)"></th>
                            <th><?= __('admin.correios_mundial.col_order','Pedido') ?></th>
                            <th><?= __('admin.correios_mundial.col_customer','Cliente') ?></th>
                            <th><?= __('admin.correios_mundial.col_tracking','Rastreio') ?></th>
                            <th><?= __('admin.correios_mundial.col_label','Etiqueta') ?></th>
                            <th><?= __('admin.correios_mundial.col_date','Data') ?></th>
                            <th><?= __('admin.correios_mundial.col_actions','Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $etiquetas = isset($etiquetas) && is_array($etiquetas) ? $etiquetas : []; ?>
                        <?php if (empty($etiquetas)): ?>
                            <tr><td colspan="7" class="text-muted"><?= __('admin.correios_mundial.no_labels','Nenhuma etiqueta gerada.') ?></td></tr>
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
                                        <button class="btn btn-sm btn-warning" onclick="regerarPacket(<?= $pid ?>)" title="<?= htmlspecialchars(__('admin.correios_mundial.regenerate_label','Regerar etiqueta'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-redo"></i></button>
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
                    <div class="text-muted small py-3"><?= __('admin.correios_mundial.no_labels','Nenhuma etiqueta gerada.') ?></div>
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
        setHint(<?= json_encode(__('admin.correios_mundial.loading','Carregando...')) ?>);
        try{
            const r = await fetch('/admin/correios-mundial/balance', { headers: { 'Accept': 'application/json' } });
            const data = await r.json();
            if(!data || !data.success){
                setBalance('-');
                setHint(<?= json_encode(__('admin.correios_mundial.load_failed','Falha ao carregar')) ?>);
                setError((data && (data.error || data.message)) ? (data.error || data.message) : <?= json_encode(__('admin.correios_mundial.balance_query_failed','Falha ao consultar saldo')) ?>);
                return;
            }
            setBalance(data.currentBalance);
            setHint(<?= json_encode(__('admin.correios_mundial.updated_now','Atualizado agora')) ?>);
        }catch(e){
            setBalance('-');
            setHint(<?= json_encode(__('admin.correios_mundial.load_failed','Falha ao carregar')) ?>);
            setError(<?= json_encode(__('admin.correios_mundial.balance_query_failed','Falha ao consultar saldo')) ?>);
        }
    }

    document.addEventListener('DOMContentLoaded', loadBalance);
})();

function irParaPedidoPacket(){
    var v = document.getElementById('buscarPedidoPacket').value.replace(/\D/g,'');
    if(v===''){alert(<?= json_encode(__('admin.correios_mundial.enter_order_number','Digite o número do pedido')) ?>);return;}
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
        btn.textContent = checked > 0 ? <?= json_encode(__('admin.correios_mundial.regenerate_selected_count','Regerar selecionadas ({n})')) ?>.replace('{n}', checked) : <?= json_encode(__('admin.correios_mundial.regenerate_selected','Regerar selecionadas')) ?>;
    }
    if (btnExport) {
        btnExport.disabled = checked === 0;
        btnExport.innerHTML = checked > 0
            ? '<i class="fas fa-file-csv me-1"></i>' + <?= json_encode(__('admin.correios_mundial.export_selected_count','Exportar selecionadas ({n})')) ?>.replace('{n}', checked)
            : '<i class="fas fa-file-csv me-1"></i>' + <?= json_encode(__('admin.correios_mundial.export_selected','Exportar selecionadas')) ?>;
    }
}

async function regerarPacket(pedidoId) {
    if (!confirm(<?= json_encode(__('admin.correios_mundial.confirm_regenerate','Regerar etiqueta do pedido #{n}? A etiqueta atual será deletada e uma nova será gerada.')) ?>.replace('{n}', pedidoId))) return;
    try {
        const r = await fetch('/admin/correios-mundial/pedido/' + pedidoId + '/regerar-etiqueta', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await r.json();
        if (data && data.success) {
            alert(<?= json_encode(__('admin.correios_mundial.label_regenerated','Etiqueta regerada! Novo rastreio: {n}')) ?>.replace('{n}', (data.tracking_number || '')));
            location.reload();
        } else {
            alert(<?= json_encode(__('admin.correios_mundial.error_prefix','Erro:')) ?> + ' ' + (data.error || data.message || <?= json_encode(__('admin.correios_mundial.regenerate_failed','Falha ao regerar')) ?>));
        }
    } catch (e) {
        alert(<?= json_encode(__('admin.correios_mundial.network_error','Erro de rede:')) ?> + ' ' + e.message);
    }
}

async function regerarSelecionadas() {
    const checks = document.querySelectorAll('.packet-check:checked');
    if (checks.length === 0) return;
    const ids = Array.from(checks).map(cb => parseInt(cb.value));
    if (!confirm(<?= json_encode(__('admin.correios_mundial.confirm_regenerate_bulk','Regerar etiquetas de {n} pedido(s) selecionados? As etiquetas atuais serão deletadas e novas serão geradas.')) ?>.replace('{n}', ids.length))) return;

    const btn = document.getElementById('btnRegerarMassa');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + <?= json_encode(__('admin.correios_mundial.regenerating','Regerando...')) ?>; }

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
                erros.push('#' + pid + ': ' + (data.error || <?= json_encode(__('admin.correios_mundial.error_generic','erro')) ?>));
            }
        } catch (e) {
            erros.push('#' + pid + ': ' + e.message);
        }
    }

    let msg = <?= json_encode(__('admin.correios_mundial.labels_regenerated_success','{n} etiqueta(s) regerada(s) com sucesso.')) ?>.replace('{n}', ok);
    if (erros.length > 0) msg += '\n\n' + <?= json_encode(__('admin.correios_mundial.errors','Erros:')) ?> + '\n' + erros.join('\n');
    alert(msg);
    location.reload();
}

async function exportarCsvSelecionadas() {
    const checks = document.querySelectorAll('.packet-check:checked');
    if (checks.length === 0) return;
    const ids = Array.from(checks).map(cb => parseInt(cb.value));

    const btn = document.getElementById('btnExportarCsv');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + <?= json_encode(__('admin.correios_mundial.exporting','Exportando...')) ?>; }

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
            alert(<?= json_encode(__('admin.correios_mundial.export_done','Exportação concluída! {n} etiqueta(s) exportada(s).\nOs arquivos wp_posts.csv e wp_postmeta.csv foram baixados.')) ?>.replace('{n}', data.total_etiquetas));
        } else {
            alert(<?= json_encode(__('admin.correios_mundial.error_prefix','Erro:')) ?> + ' ' + (data.error || data.message || <?= json_encode(__('admin.correios_mundial.export_failed','Falha ao exportar')) ?>));
        }
    } catch (e) {
        alert(<?= json_encode(__('admin.correios_mundial.network_error','Erro de rede:')) ?> + ' ' + e.message);
    }

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-csv me-1"></i>' + <?= json_encode(__('admin.correios_mundial.export_selected','Exportar selecionadas')) ?>; }
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

// ===== MODO MASSA - Gerar etiquetas em massa =====
function toggleModoMassa() {
    var normal = document.getElementById('tabelaNormal');
    var massa = document.getElementById('tabelaMassa');
    var btn = document.getElementById('btnToggleMassa');
    if (!normal || !massa) return;
    var isActive = !massa.classList.contains('d-none');
    if (isActive) {
        massa.classList.add('d-none');
        normal.classList.remove('d-none');
        if (btn) btn.innerHTML = '<i class="fas fa-bolt me-1"></i>' + <?= json_encode(__('admin.correios_mundial.bulk_generate','Gerar em Massa')) ?>;
    } else {
        normal.classList.add('d-none');
        massa.classList.remove('d-none');
        if (btn) btn.innerHTML = '<i class="fas fa-arrow-left me-1"></i>' + <?= json_encode(__('admin.correios_mundial.back_to_normal','Voltar ao Normal')) ?>;
    }
}

function toggleAllMassaCf(el) {
    document.querySelectorAll('.massa-check:not(:disabled)').forEach(function(cb) { cb.checked = el.checked; });
    updateMassaCount();
}

function updateMassaCount() {
    var checked = document.querySelectorAll('.massa-check:checked').length;
    var countEl = document.getElementById('massaCount');
    var btn = document.getElementById('btnGerarMassa');
    if (countEl) countEl.textContent = checked;
    if (btn) btn.disabled = (checked === 0);
}

async function gerarEtiquetasMassa() {
    var checks = document.querySelectorAll('.massa-check:checked');
    if (checks.length === 0) return;
    var ids = Array.from(checks).map(function(cb) { return parseInt(cb.value); });
    if (!confirm(<?= json_encode(__('admin.correios_mundial.confirm_bulk_generate','Gerar etiquetas PACKET para {n} pedido(s) selecionados?\n\nO sistema fará todas as validações automaticamente.')) ?>.replace('{n}', ids.length))) return;

    var btn = document.getElementById('btnGerarMassa');
    var tabelaMassa = document.getElementById('tabelaMassa');

    // Bloquear toda a interface
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + <?= json_encode(__('admin.correios_mundial.generating_progress','Gerando... {done}/{total}')) ?>.replace('{done}', '0').replace('{total}', ids.length); }
    // Desabilitar todos os checkboxes e botões dentro da tabela
    tabelaMassa.querySelectorAll('input, button, a').forEach(function(el) { el.style.pointerEvents = 'none'; el.style.opacity = '0.5'; });
    // Overlay de loading
    var overlay = document.createElement('div');
    overlay.id = 'massaOverlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML = '<div style="background:#fff;border-radius:12px;padding:32px 48px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.2);"><div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status"></div><div id="massaOverlayText" style="font-size:1.1rem;font-weight:600;color:#333;">' + <?= json_encode(__('admin.correios_mundial.generating_labels_progress','Gerando etiquetas... {done}/{total}')) ?>.replace('{done}', '0').replace('{total}', ids.length) + '</div><div class="text-muted small mt-2">' + <?= json_encode(__('admin.correios_mundial.do_not_close','Não feche esta página')) ?> + '</div></div>';
    document.body.appendChild(overlay);

    // Marcar todos como "processando"
    ids.forEach(function(pid) {
        var row = document.querySelector('tr[data-pid="' + pid + '"]');
        if (row) {
            var st = row.querySelector('.massa-status');
            if (st) { st.textContent = '⏳'; st.className = 'massa-status text-warning'; }
        }
    });

    try {
        var r = await fetch('/admin/correios-mundial/gerar-etiquetas-massa', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        });
        var data = await r.json();

        // Remover overlay
        var ov = document.getElementById('massaOverlay');
        if (ov) ov.remove();

        if (data && data.results) {
            var ok = 0, erros = [];
            data.results.forEach(function(res) {
                var row = document.querySelector('tr[data-pid="' + res.pedido_id + '"]');
                var st = row ? row.querySelector('.massa-status') : null;
                if (res.success) {
                    ok++;
                    if (st) { st.innerHTML = '✅ ' + res.tracking_number; st.className = 'massa-status text-success fw-bold'; }
                } else {
                    erros.push('#' + res.pedido_id + ': ' + res.error);
                    if (st) { st.innerHTML = '❌ ' + res.error; st.className = 'massa-status text-danger'; }
                }
            });

            if (btn) { btn.innerHTML = '<i class="fas fa-check me-1"></i>' + <?= json_encode(__('admin.correios_mundial.generated_count','{n} gerada(s)')) ?>.replace('{n}', ok); }

            var msg = <?= json_encode(__('admin.correios_mundial.labels_generated_success','{n} etiqueta(s) gerada(s) com sucesso.')) ?>.replace('{n}', ok);
            if (erros.length > 0) msg += '\n\n' + <?= json_encode(__('admin.correios_mundial.errors_count','{n} erro(s):')) ?>.replace('{n}', erros.length) + '\n' + erros.join('\n');
            alert(msg);

            if (ok > 0) {
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                // Desbloquear interface
                tabelaMassa.querySelectorAll('input, button, a').forEach(function(el) { el.style.pointerEvents = ''; el.style.opacity = ''; });
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-tags me-1"></i>' + <?= json_encode(__('admin.correios_mundial.generate_labels','Gerar Etiquetas')) ?> + ' (<span id="massaCount">' + ids.length + '</span>)'; }
            }
        } else {
            alert(<?= json_encode(__('admin.correios_mundial.error_prefix','Erro:')) ?> + ' ' + (data.error || <?= json_encode(__('admin.correios_mundial.unknown_failure','Falha desconhecida')) ?>));
            var ov2 = document.getElementById('massaOverlay');
            if (ov2) ov2.remove();
            tabelaMassa.querySelectorAll('input, button, a').forEach(function(el) { el.style.pointerEvents = ''; el.style.opacity = ''; });
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-tags me-1"></i>' + <?= json_encode(__('admin.correios_mundial.generate_labels','Gerar Etiquetas')) ?> + ' (<span id="massaCount">' + ids.length + '</span>)'; }
        }
    } catch (e) {
        alert(<?= json_encode(__('admin.correios_mundial.network_error','Erro de rede:')) ?> + ' ' + e.message);
        var ov3 = document.getElementById('massaOverlay');
        if (ov3) ov3.remove();
        tabelaMassa.querySelectorAll('input, button, a').forEach(function(el) { el.style.pointerEvents = ''; el.style.opacity = ''; });
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-tags me-1"></i>' + <?= json_encode(__('admin.correios_mundial.generate_labels','Gerar Etiquetas')) ?> + ' (<span id="massaCount">' + ids.length + '</span>)'; }
    }
}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
