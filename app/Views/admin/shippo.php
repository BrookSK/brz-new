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
                <button class="btn btn-sm btn-success" id="btnGerarShippo" style="display:none;" onclick="gerarEtiquetasShippoSelecionadas()"><i class="fas fa-bolt me-1"></i><span id="btnGerarShippoText">Gerar Etiquetas</span></button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <!-- Desktop: Table Normal -->
            <div class="table-responsive d-none d-md-block" id="tabelaNormal">
                <table class="table table-sm align-middle" id="tabelaPedidos">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" class="form-check-input" id="checkAllPedidos" onclick="toggleAllPedidosShippo(this)"></th>
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
                                        <input type="checkbox" class="form-check-input pedido-check" value="<?= $pid ?>">
                                    </td>
                                    <td><span class="fw-bold">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></span></td>
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
                                    <td><span class="fw-bold">#<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?></span></td>
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

// ===== Checkbox de pedidos =====
function toggleAllPedidosShippo(el) {
    document.querySelectorAll('.pedido-check').forEach(function(cb) { cb.checked = el.checked; });
    updateBtnGerarShippo();
}

function updateBtnGerarShippo() {
    var checked = document.querySelectorAll('.pedido-check:checked').length;
    var btn = document.getElementById('btnGerarShippo');
    var txt = document.getElementById('btnGerarShippoText');
    if (btn) btn.style.display = checked > 0 ? '' : 'none';
    if (txt) txt.textContent = 'Gerar ' + checked + ' Etiqueta(s)';
}

// Adicionar listener nos checkboxes
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('pedido-check')) updateBtnGerarShippo();
});

async function gerarEtiquetasShippoSelecionadas() {
    var checks = document.querySelectorAll('.pedido-check:checked');
    if (checks.length === 0) return;
    var ids = Array.from(checks).map(function(cb) { return parseInt(cb.value); });
    if (!confirm('Gerar etiquetas Shippo para ' + ids.length + ' pedido(s) selecionados?\n\nSerá selecionada automaticamente a opção de frete mais barata para cada pedido.')) return;

    var btn = document.getElementById('btnGerarShippo');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...'; }

    // Overlay
    var overlay = document.createElement('div');
    overlay.id = 'massaOverlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML = '<div style="background:#fff;border-radius:12px;padding:32px 48px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.2);"><div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status"></div><div style="font-size:1.1rem;font-weight:600;color:#333;">Gerando etiquetas via Shippo...</div><div class="text-muted small mt-2">Não feche esta página</div></div>';
    document.body.appendChild(overlay);

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
                if (res.success) {
                    ok++;
                } else {
                    erros.push('#' + res.pedido_id + ': ' + (res.error || 'erro'));
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

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt me-1"></i><span id="btnGerarShippoText">Gerar Etiquetas</span>'; updateBtnGerarShippo(); }
}
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


</script>

<?php
$content = ob_get_clean();
$title = 'Shippo - Etiquetas Internacionais';
$sidebarActive = $sidebarActive ?? 'shippo';
require __DIR__ . '/../layouts/admin.php';
?>
