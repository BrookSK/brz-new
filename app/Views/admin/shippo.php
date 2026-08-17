<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Shippo - Etiquetas Internacionais</h1>
        <div class="d-flex gap-2 align-items-center">
            <div class="input-group input-group-sm" style="width:280px">
                <input type="text" class="form-control" id="buscarShippo" placeholder="Buscar por pedido, cliente, tracking..." onkeydown="if(event.key==='Enter'){filtrarShippo();event.preventDefault();}">
                <button class="btn btn-outline-primary" type="button" onclick="filtrarShippo()"><i class="fas fa-search"></i></button>
            </div>
        </div>
    </div>

    <div class="alert alert-info small mb-3">
        <i class="fas fa-globe me-1"></i>
        Envios internacionais via Shippo para o mundo todo, <strong>exceto Brasil</strong>. Entregas para o Brasil devem usar o <a href="/admin/etiquetas-wp">Correio Internacional</a>.
    </div>

    <div class="alert alert-danger" id="shippo_error" style="display:none;"></div>

    <!-- Pedidos em Caixa Fechada (prontos para etiqueta) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Pedidos em Caixa Fechada</strong>
            <?php if (!empty($pedidos)): ?>
                <button class="btn btn-sm btn-warning" id="btnToggleMassa" onclick="toggleModoMassa()"><i class="fas fa-bolt me-1"></i>Gerar em Massa</button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <!-- Desktop: Table Normal -->
            <div class="table-responsive d-none d-md-block" id="tabelaNormal">
                <table class="table table-sm align-middle" id="tabelaPedidos">
                    <thead>
                        <tr>
                            <th style="width:30px;"></th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Peso</th>
                            <th>Medidas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $pedidos = isset($pedidos) && is_array($pedidos) ? $pedidos : []; ?>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="5" class="text-muted">Nenhum pedido aguardando etiqueta.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $p): ?>
                                <?php
                                    $pid = (int) ($p['pedido_id'] ?? 0);
                                    $peso = isset($p['peso_total']) ? (float) $p['peso_total'] : 0;
                                    $alt = isset($p['altura']) ? (float) $p['altura'] : 0;
                                    $larg = isset($p['largura']) ? (float) $p['largura'] : 0;
                                    $comp = isset($p['comprimento']) ? (float) $p['comprimento'] : 0;
                                ?>
                                <tr class="pedido-row" data-search="<?= htmlspecialchars(strtolower(($p['cliente_nome'] ?? '') . ' ' . $pid)) ?>">
                                    <td>
                                        <a class="btn btn-sm btn-primary py-0 px-2" href="/admin/shippo/pedido/<?= $pid ?>"><i class="fas fa-external-link-alt"></i></a>
                                    </td>
                                    <td><a href="/admin/shippo/pedido/<?= $pid ?>">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></a></td>
                                    <td><?= htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) ?></td>
                                    <td>
                                        <?php if ($peso > 0): ?>
                                            <span><?= number_format($peso, 2, ',', '.') ?>kg</span>
                                        <?php else: ?>
                                            <span class="text-danger">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($larg > 0 && $alt > 0 && $comp > 0): ?>
                                            <span><?= number_format($larg, 2, '.', '') ?>×<?= number_format($alt, 2, '.', '') ?>×<?= number_format($comp, 2, '.', '') ?>cm</span>
                                        <?php else: ?>
                                            <span class="text-danger">--</span>
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
                        <input type="checkbox" id="checkAllMassa" onclick="toggleAllMassaShippo(this)" class="form-check-input me-2">
                        <label for="checkAllMassa" class="form-check-label small">Selecionar todos</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-success" id="btnGerarMassa" onclick="gerarEtiquetasMassaShippo()" disabled>
                            <i class="fas fa-tags me-1"></i>Gerar Etiquetas (<span id="massaCount">0</span>)
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleModoMassa()">Voltar</button>
                    </div>
                </div>
                <table class="table table-sm align-middle table-bordered" style="font-size:.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px;"></th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Peso (kg)</th>
                            <th>L×A×C (cm)</th>
                            <th>País</th>
                            <th>Dados OK</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="8" class="text-muted">Nenhum pedido.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $p): ?>
                                <?php
                                    $pid = (int) ($p['pedido_id'] ?? 0);
                                    $peso = isset($p['peso_total']) ? (float) $p['peso_total'] : 0;
                                    $alt = isset($p['altura']) ? (float) $p['altura'] : 0;
                                    $larg = isset($p['largura']) ? (float) $p['largura'] : 0;
                                    $comp = isset($p['comprimento']) ? (float) $p['comprimento'] : 0;
                                    $pais = strtoupper(trim((string) ($p['pais_destino'] ?? '')));
                                    $temPeso = $peso > 0;
                                    $temDim = ($alt > 0 && $larg > 0 && $comp > 0);
                                    $temNome = !empty($p['cliente_nome']);
                                    $dadosOk = $temPeso && $temDim && $temNome;
                                ?>
                                <tr data-pid="<?= $pid ?>" data-ok="<?= $dadosOk ? '1' : '0' ?>">
                                    <td><input type="checkbox" class="form-check-input massa-check" value="<?= $pid ?>" onchange="updateMassaCountShippo()" <?= !$dadosOk ? 'disabled title="Dados incompletos"' : '' ?>></td>
                                    <td><a href="/admin/shippo/pedido/<?= $pid ?>" target="_blank">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></a></td>
                                    <td><?= htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) ?></td>
                                    <td class="<?= $temPeso ? '' : 'text-danger fw-bold' ?>"><?= $temPeso ? number_format($peso, 2, ',', '.') : '⚠ 0' ?></td>
                                    <td class="<?= $temDim ? '' : 'text-danger' ?>"><?= ($larg > 0 ? number_format($larg, 1) : '?') ?>×<?= ($alt > 0 ? number_format($alt, 1) : '?') ?>×<?= ($comp > 0 ? number_format($comp, 1) : '?') ?></td>
                                    <td><?= htmlspecialchars($pais ?: '?') ?></td>
                                    <td>
                                        <?php if ($dadosOk): ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" title="<?= implode(', ', array_filter([!$temPeso?'Peso':'', !$temDim?'Dimensões':'', !$temNome?'Nome':''])) ?>">Incompleto</span>
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
                    <div class="text-muted small py-3">Nenhum pedido aguardando etiqueta.</div>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <?php
                            $pid = (int) ($p['pedido_id'] ?? 0);
                            $peso = isset($p['peso_total']) ? (float) $p['peso_total'] : 0;
                            $alt = isset($p['altura']) ? (float) $p['altura'] : 0;
                            $larg = isset($p['largura']) ? (float) $p['largura'] : 0;
                            $comp = isset($p['comprimento']) ? (float) $p['comprimento'] : 0;
                        ?>
                        <div class="border-bottom py-2 pedido-row" data-search="<?= htmlspecialchars(strtolower(($p['cliente_nome'] ?? '') . ' ' . $pid)) ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold small">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></span>
                                    <span class="small ms-2"><?= htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) ?></span>
                                </div>
                                <a class="btn btn-sm btn-primary py-0 px-2" href="/admin/shippo/pedido/<?= $pid ?>">Abrir</a>
                            </div>
                            <div class="text-muted small">
                                <?= $peso > 0 ? number_format($peso, 2, ',', '.') . 'kg' : '--' ?>
                                &middot;
                                <?= ($larg > 0 && $alt > 0 && $comp > 0) ? number_format($larg, 1) . '×' . number_format($alt, 1) . '×' . number_format($comp, 1) . 'cm' : '--' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Etiquetas geradas -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Pacotes gerados (com etiqueta)</strong>
        </div>
        <div class="card-body">
            <!-- Desktop: Table -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm align-middle" id="tabelaEtiquetas">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Tracking</th>
                            <th>Carrier</th>
                            <th>Peso</th>
                            <th>PDF</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $etiquetas = isset($etiquetas) && is_array($etiquetas) ? $etiquetas : []; ?>
                        <?php if (empty($etiquetas)): ?>
                            <tr><td colspan="7" class="text-muted">Nenhuma etiqueta gerada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($etiquetas as $e): ?>
                                <?php
                                    $pid = (int) ($e['pedido_id'] ?? 0);
                                    $trk = (string) ($e['tracking_number'] ?? '');
                                    $labelUrl = (string) ($e['label_url'] ?? '');
                                    $carrier = (string) ($e['carrier'] ?? '');
                                    $serviceLevel = (string) ($e['service_level'] ?? '');
                                    $rateAmount = (float) ($e['rate_amount'] ?? 0);
                                    $rateCurrency = (string) ($e['rate_currency'] ?? 'USD');
                                ?>
                                <tr class="etiqueta-row" data-search="<?= htmlspecialchars(strtolower(($e['cliente_nome'] ?? '') . ' ' . $pid . ' ' . $trk)) ?>">
                                    <td><a href="/admin/shippo/pedido/<?= $pid ?>">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></a></td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></td>
                                    <td>
                                        <?php if ($trk !== ''): ?>
                                            <?php if (!empty($e['tracking_url'])): ?>
                                                <a href="<?= htmlspecialchars((string) $e['tracking_url']) ?>" target="_blank" class="text-primary"><?= htmlspecialchars($trk) ?></a>
                                            <?php else: ?>
                                                <span><?= htmlspecialchars($trk) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small"><?= htmlspecialchars($carrier) ?></span>
                                        <?php if ($rateAmount > 0): ?>
                                            <br><span class="text-muted small"><?= $rateCurrency ?> <?= number_format($rateAmount, 2) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            // Tentar pegar peso da resposta salva ou do pedido
                                            $pesoEtq = 0;
                                            if (!empty($e['last_response_json'])) {
                                                $respData = json_decode((string) $e['last_response_json'], true);
                                                if (is_array($respData) && isset($respData['parcel']['weight'])) {
                                                    $pesoEtq = (float) $respData['parcel']['weight'];
                                                }
                                            }
                                        ?>
                                        <?= $pesoEtq > 0 ? number_format($pesoEtq, 2) . 'kg' : '<span class="text-muted">--</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($labelUrl !== ''): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($labelUrl) ?>" target="_blank"><i class="fas fa-file-pdf me-1"></i>PDF</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="regerarShippo(<?= $pid ?>)" title="Regerar etiqueta"><i class="fas fa-redo"></i></button>
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
                        <?php
                            $pid = (int) ($e['pedido_id'] ?? 0);
                            $trk = (string) ($e['tracking_number'] ?? '');
                            $labelUrl = (string) ($e['label_url'] ?? '');
                        ?>
                        <div class="border-bottom py-2 etiqueta-row" data-search="<?= htmlspecialchars(strtolower(($e['cliente_nome'] ?? '') . ' ' . $pid . ' ' . $trk)) ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="min-width:0;flex:1;">
                                    <span class="fw-bold small">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></span>
                                    <span class="small ms-1"><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></span>
                                    <?php if ($trk !== ''): ?>
                                        <div class="text-muted small" style="word-break:break-all;"><?= htmlspecialchars($trk) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($labelUrl !== ''): ?>
                                    <a class="btn btn-sm btn-outline-primary py-0 px-2 ms-2" href="<?= htmlspecialchars($labelUrl) ?>" target="_blank">PDF</a>
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
// ===== Busca / Filtro =====
function filtrarShippo() {
    var query = (document.getElementById('buscarShippo').value || '').toLowerCase().trim();
    document.querySelectorAll('.pedido-row, .etiqueta-row').forEach(function(row) {
        var data = (row.getAttribute('data-search') || '');
        row.style.display = (!query || data.indexOf(query) !== -1) ? '' : 'none';
    });
}

// ===== Regerar =====
async function regerarShippo(pedidoId) {
    if (!confirm('Remover etiqueta do pedido #' + pedidoId + '? Você poderá gerar uma nova em seguida.')) return;
    try {
        const r = await fetch('/admin/shippo/pedido/' + pedidoId + '/regerar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await r.json();
        if (data && data.success) {
            alert('Etiqueta removida. Recarregando...');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Falha ao regerar'));
        }
    } catch (e) {
        alert('Erro de rede: ' + e.message);
    }
}

// ===== Modo Massa =====
function toggleModoMassa() {
    var normal = document.getElementById('tabelaNormal');
    var massa = document.getElementById('tabelaMassa');
    var btn = document.getElementById('btnToggleMassa');
    if (!normal || !massa) return;
    var isActive = !massa.classList.contains('d-none');
    if (isActive) {
        massa.classList.add('d-none');
        normal.classList.remove('d-none');
        if (btn) btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Gerar em Massa';
    } else {
        normal.classList.add('d-none');
        massa.classList.remove('d-none');
        if (btn) btn.innerHTML = '<i class="fas fa-arrow-left me-1"></i>Voltar ao Normal';
    }
}

function toggleAllMassaShippo(el) {
    document.querySelectorAll('.massa-check:not(:disabled)').forEach(function(cb) { cb.checked = el.checked; });
    updateMassaCountShippo();
}

function updateMassaCountShippo() {
    var checked = document.querySelectorAll('.massa-check:checked').length;
    var countEl = document.getElementById('massaCount');
    var btn = document.getElementById('btnGerarMassa');
    if (countEl) countEl.textContent = checked;
    if (btn) btn.disabled = (checked === 0);
}

async function gerarEtiquetasMassaShippo() {
    var checks = document.querySelectorAll('.massa-check:checked');
    if (checks.length === 0) return;
    var ids = Array.from(checks).map(function(cb) { return parseInt(cb.value); });
    if (!confirm('Gerar etiquetas Shippo para ' + ids.length + ' pedido(s) selecionados?\n\nSerá selecionada automaticamente a opção de frete mais barata para cada pedido.')) return;

    var btn = document.getElementById('btnGerarMassa');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...'; }

    // Overlay
    var overlay = document.createElement('div');
    overlay.id = 'massaOverlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML = '<div style="background:#fff;border-radius:12px;padding:32px 48px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.2);"><div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status"></div><div id="massaOverlayText" style="font-size:1.1rem;font-weight:600;color:#333;">Gerando etiquetas via Shippo...</div><div class="text-muted small mt-2">Não feche esta página</div></div>';
    document.body.appendChild(overlay);

    // Marcar status
    ids.forEach(function(pid) {
        var row = document.querySelector('tr[data-pid="' + pid + '"]');
        if (row) {
            var st = row.querySelector('.massa-status');
            if (st) { st.textContent = '⏳'; st.className = 'massa-status text-warning'; }
        }
    });

    try {
        var r = await fetch('/admin/shippo/gerar-etiquetas-massa', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pedido_ids: ids })
        });
        var data = await r.json();

        var ov = document.getElementById('massaOverlay');
        if (ov) ov.remove();

        if (data && data.results) {
            var ok = 0, erros = [];
            data.results.forEach(function(res) {
                var row = document.querySelector('tr[data-pid="' + res.pedido_id + '"]');
                if (row) {
                    var st = row.querySelector('.massa-status');
                    if (res.success) {
                        ok++;
                        if (st) { st.innerHTML = '✅ ' + (res.tracking_number || 'OK'); st.className = 'massa-status text-success'; }
                    } else {
                        erros.push('#' + res.pedido_id + ': ' + (res.error || 'erro'));
                        if (st) { st.textContent = '❌ ' + (res.error || 'erro'); st.className = 'massa-status text-danger'; }
                    }
                }
            });

            var msg = ok + ' etiqueta(s) gerada(s) com sucesso.';
            if (erros.length > 0) msg += '\n\nErros (' + erros.length + '):\n' + erros.slice(0, 10).join('\n');
            alert(msg);
            if (ok > 0) setTimeout(function() { location.reload(); }, 1000);
        } else {
            alert('Erro: ' + ((data && data.error) || 'Resposta inesperada'));
        }
    } catch (e) {
        var ov = document.getElementById('massaOverlay');
        if (ov) ov.remove();
        alert('Erro de rede: ' + e.message);
    }

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-tags me-1"></i>Gerar Etiquetas (<span id="massaCount">0</span>)'; }
}
</script>

<?php
$content = ob_get_clean();
$title = 'Shippo - Etiquetas Internacionais';
$sidebarActive = $sidebarActive ?? 'shippo';
require __DIR__ . '/../layouts/admin.php';
?>
