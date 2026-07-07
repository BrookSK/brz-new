<?php ob_start(); ?>
<style>
    .ewp-card { border: none; box-shadow: 0 1px 3px rgba(0,0,0,.08); border-radius: 8px; }
    .ewp-badge { font-size: .7rem; padding: 3px 8px; border-radius: 4px; }
    .ewp-tab-btn { border: none; background: none; padding: 10px 18px; font-weight: 500; color: #666; border-bottom: 2px solid transparent; }
    .ewp-tab-btn.active { color: #0d6efd; border-bottom-color: #0d6efd; }
    .ewp-tab-btn:hover { color: #0d6efd; }
    .ewp-status { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
    .ewp-status.ok { background: #28a745; }
    .ewp-status.err { background: #dc3545; }
    .ewp-empty { text-align: center; padding: 40px 20px; color: #999; }
    .ewp-empty i { font-size: 2rem; margin-bottom: 10px; display: block; }
    @media (max-width: 768px) {
        .ewp-tabs { overflow-x: auto; white-space: nowrap; }
        .ewp-tab-btn { padding: 8px 12px; font-size: .85rem; }
    }
</style>

<div class="container-fluid px-2 px-md-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom flex-wrap gap-2">
        <h1 class="h4 mb-0"><i class="fas fa-tags me-2 text-primary"></i>Etiquetas</h1>
        <div class="d-flex gap-2 align-items-center">
            <span id="ewp-conn-status" class="ewp-status"></span>
            <small id="ewp-conn-text" class="text-muted">Conectando...</small>
            <span id="ewp-ambiente" class="ewp-badge" style="display:none;"></span>
            <small id="ewp-saldo" class="text-muted" style="display:none;"></small>
            <button class="btn btn-xs btn-outline-secondary" onclick="testarConexaoDetalhado()" title="Testar conexão detalhada">
                <i class="fas fa-stethoscope"></i>
            </button>
        </div>
    </div>

    <!-- Painel de diagnóstico (oculto por padrão) -->
    <div id="ewp-diagnostico" class="card ewp-card mb-3" style="display:none;">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong class="small">Diagnóstico de Conexão</strong>
            <button class="btn btn-xs btn-outline-secondary" onclick="document.getElementById('ewp-diagnostico').style.display='none'">✕</button>
        </div>
        <div class="card-body p-2" id="ewp-diagnostico-body">
            <i class="fas fa-spinner fa-spin me-1"></i>Testando...
        </div>
    </div>

    <!-- Tabs -->
    <div class="ewp-tabs mb-3 border-bottom">
        <button class="ewp-tab-btn active" onclick="switchTab('etiquetas')">📦 Etiquetas</button>
        <button class="ewp-tab-btn" onclick="switchTab('containers')">📋 Containers</button>
        <button class="ewp-tab-btn" onclick="switchTab('faturas')">🧾 Faturas</button>
        <button class="ewp-tab-btn" onclick="switchTab('embarques')">✈️ Embarques</button>
    </div>

    <!-- TAB: ETIQUETAS -->
    <div class="ewp-panel" id="panel-etiquetas">
        <!-- Pedidos prontos para gerar -->
        <div class="card ewp-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong class="small">Pedidos em Caixa Fechada</strong>
                <button class="btn btn-sm btn-warning" id="btnGerarMassa" style="display:none;" onclick="gerarEtiquetasMassa()">
                    <i class="fas fa-bolt me-1"></i><span id="btnGerarMassaText">Gerar</span>
                </button>
            </div>
            <div class="card-body p-0">
                <div id="pedidos-resultado" class="p-3" style="display:none;"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30px"><input type="checkbox" id="checkAllPedidos" onclick="toggleAllPedidos()"></th>
                                <th>Pedido</th>
                                <th class="d-none d-md-table-cell">Cliente</th>
                                <th>Peso</th>
                                <th class="d-none d-md-table-cell">Medidas</th>
                            </tr>
                        </thead>
                        <tbody id="pedidos-body">
                            <?php
                            $pedidosCaixaFechada = [];
                            try {
                                $conn = \Config\Database::getConnection();
                                $sql = "SELECT p.id, p.created_at, p.peso_total, p.altura, p.largura, p.comprimento, u.nome as cliente_nome
                                        FROM pedidos p
                                        LEFT JOIN usuarios u ON u.id = p.usuario_id
                                        LEFT JOIN correios_packet_etiquetas cpe ON cpe.pedido_id = p.id
                                        WHERE LOWER(COALESCE(p.status,'')) IN ('produto_consolidado','consolidado')
                                          AND cpe.id IS NULL
                                        ORDER BY p.created_at ASC LIMIT 200";
                                $st = $conn->prepare($sql);
                                $st->execute();
                                $pedidosCaixaFechada = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                            } catch (\Exception $e) { $pedidosCaixaFechada = []; }
                            if (empty($pedidosCaixaFechada)): ?>
                                <tr><td colspan="5" class="ewp-empty"><i class="fas fa-check-circle"></i>Nenhum pedido aguardando etiqueta</td></tr>
                            <?php else: foreach ($pedidosCaixaFechada as $ped): ?>
                                <tr>
                                    <td><input type="checkbox" class="chk-pedido" value="<?= (int)$ped['id'] ?>"></td>
                                    <td><strong>#<?= str_pad((string)$ped['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td class="d-none d-md-table-cell"><?= htmlspecialchars((string)($ped['cliente_nome'] ?? '-')) ?></td>
                                    <td><?= !empty($ped['peso_total']) ? number_format((float)$ped['peso_total'], 2, ',', '.') . 'kg' : '<span class="text-danger">—</span>' ?></td>
                                    <td class="d-none d-md-table-cell"><?= (!empty($ped['comprimento']) && !empty($ped['largura']) && !empty($ped['altura'])) ? $ped['comprimento'].'×'.$ped['largura'].'×'.$ped['altura'].'cm' : '<span class="text-danger">—</span>' ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Pacotes gerados (sem container) -->
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small">Pacotes gerados (sem container)</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Pedido</th><th>Tracking</th><th class="d-none d-md-table-cell">Peso</th><th>PDF</th></tr>
                        </thead>
                        <tbody id="pacotes-body">
                            <tr><td colspan="4" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: CONTAINERS -->
    <div class="ewp-panel" id="panel-containers" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2">
                <strong class="small">Criar Container</strong>
            </div>
            <div class="card-body">
                <form id="form-container" onsubmit="criarContainer(event)">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Nº Remessa*</label>
                            <input type="number" class="form-control form-control-sm" id="cnt-dispatch" required min="1">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">País de Origem*</label>
                            <input type="text" class="form-control form-control-sm" id="cnt-origin-country" value="US" maxlength="2" readonly>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Nome do Operador de Origem*</label>
                            <input type="text" class="form-control form-control-sm" id="cnt-origin-operator" value="BRAZ" maxlength="10" readonly>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Nome do Operador de Destino*</label>
                            <select class="form-select form-select-sm" id="cnt-dest-operator" required>
                                <option value="SAOD" selected>SAOD - Guarulhos</option>
                                <option value="CWBA">CWBA - Curitiba</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Código da Categoria Postal*</label>
                            <select class="form-select form-select-sm" id="cnt-postal-category" required>
                                <option value="A" selected>A – Airmail ou Priority Mail</option>
                                <option value="B">B – S.A.L Mail ou Non-Priority Mail</option>
                                <option value="C">C – Surface Mail ou Non-Priority Mail</option>
                                <option value="D">D – Priority Mail enviado por transporte terrestre</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Código da Subclasse do Serviço*</label>
                            <select class="form-select form-select-sm" id="cnt-subclass" required>
                                <option value="NX" selected>NX – Serviço padrão</option>
                                <option value="IX">IX – Serviço expresso</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Tipo de Unidade*</label>
                            <select class="form-select form-select-sm" id="cnt-unit-type" required>
                                <option value="2" selected>2 - Caixa com base pallet até 500kg</option>
                                <option value="1">1 - Saco até 30kg</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">N° AWB</label>
                            <input type="text" class="form-control form-control-sm" id="cnt-awb" placeholder="(opcional)">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Grupos de Triagem</label>
                            <select class="form-select form-select-sm" id="cnt-triage" required>
                                <option value="1">1 - São Paulo/SP</option>
                                <option value="2">2 - Valinhos/SP</option>
                                <option value="3">3 - Rio de Janeiro/RJ</option>
                                <option value="4">4 - Curitiba/PR</option>
                                <option value="5" selected>5 - Curitiba/PR</option>
                            </select>
                        </div>
                    </div>
                    <!-- Seleção de pacotes -->
                    <label class="form-label small fw-bold">Pacotes para este container:</label>
                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <textarea class="form-control form-control-sm" id="cnt-paste-trackings" rows="2" placeholder="Cole tracking codes aqui (um por linha, separados por vírgula ou espaço)"></textarea>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="validarESelecionar()">
                                <i class="fas fa-check-double me-1"></i>Validar e Selecionar
                            </button>
                        </div>
                    </div>
                    <div id="cnt-validacao" class="mb-2" style="display:none;"></div>
                    <div id="cnt-pacotes-lista" class="border rounded p-2 mb-2" style="max-height:250px; overflow-y:auto;">
                        <span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Carregando pacotes...</span>
                    </div>
                    <div id="cnt-selected-count" class="small text-muted mb-2"></div>
                    <div id="container-resultado" class="mb-2" style="display:none;"></div>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-criar-container">
                        <i class="fas fa-box me-1"></i>Criar Container
                    </button>
                </form>
            </div>
        </div>

        <!-- Containers existentes -->
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small">Containers sem Fatura</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30px"><input type="checkbox" id="checkAllContainers" onclick="toggleAllContainers()"></th>
                                <th>Remessa</th><th>Unit Code</th><th>Pacotes</th><th class="d-none d-md-table-cell">Data</th><th>PDF</th>
                            </tr>
                        </thead>
                        <tbody id="containers-body">
                            <tr><td colspan="6" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: FATURAS -->
    <div class="ewp-panel" id="panel-faturas" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2"><strong class="small">Criar Fatura (CN38)</strong></div>
            <div class="card-body">
                <p class="small text-muted mb-2">Selecione os containers abaixo para faturar:</p>
                <div id="fatura-containers-lista" class="border rounded p-2 mb-2" style="max-height:200px; overflow-y:auto;">
                    <span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Carregando containers...</span>
                </div>
                <div id="fatura-resultado" class="mb-2" style="display:none;"></div>
                <button class="btn btn-warning btn-sm" onclick="criarFatura()" id="btn-criar-fatura">
                    <i class="fas fa-file-invoice me-1"></i>Criar Fatura
                </button>
            </div>
        </div>
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small">Faturas disponíveis</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30px"><input type="checkbox" id="checkAllFaturas" onclick="toggleAllFaturas()"></th>
                                <th>CN38</th><th class="d-none d-md-table-cell">Remessas</th><th class="d-none d-md-table-cell">Data</th><th>PDF</th>
                            </tr>
                        </thead>
                        <tbody id="faturas-body">
                            <tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: EMBARQUES -->
    <div class="ewp-panel" id="panel-embarques" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2"><strong class="small">Confirmar Embarque</strong></div>
            <div class="card-body">
                <form id="form-embarque" onsubmit="criarEmbarque(event)">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Nº Voo*</label>
                            <input type="number" class="form-control form-control-sm" id="emb-flight" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Cia Aérea*</label>
                            <input type="text" class="form-control form-control-sm" id="emb-airline" value="M3" required maxlength="3">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Partida*</label>
                            <input type="datetime-local" class="form-control form-control-sm" id="emb-departure-date" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Aeroporto Partida*</label>
                            <input type="text" class="form-control form-control-sm" id="emb-departure-airport" value="MIA" required maxlength="3">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Chegada*</label>
                            <input type="datetime-local" class="form-control form-control-sm" id="emb-arrival-date" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Aeroporto Chegada*</label>
                            <input type="text" class="form-control form-control-sm" id="emb-arrival-airport" value="GRU" required maxlength="3">
                        </div>
                    </div>
                    <div id="emb-faturas-lista" class="border rounded p-2 mb-2" style="max-height:150px; overflow-y:auto;">
                        <span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Carregando faturas...</span>
                    </div>
                    <div id="embarque-resultado" class="mb-2" style="display:none;"></div>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-criar-embarque">
                        <i class="fas fa-plane me-1"></i>Confirmar Embarque
                    </button>
                </form>
            </div>
        </div>
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small">Embarques confirmados</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Voo</th><th>Cia</th><th>Partida</th><th class="d-none d-md-table-cell">Chegada</th><th>CN38</th><th>Status</th></tr>
                        </thead>
                        <tbody id="embarques-body">
                            <tr><td colspan="6" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '/admin/etiquetas-wp';
let currentTab = 'etiquetas';

// ============================================================
// TABS
// ============================================================
function switchTab(tab) {
    document.querySelectorAll('.ewp-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.ewp-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + tab).style.display = 'block';
    event.target.classList.add('active');
    // Auto-load data on tab switch
    if (tab === 'containers') { carregarPacotesParaContainer(); carregarContainers(); }
    if (tab === 'faturas') { carregarContainersParaFatura(); carregarFaturas(); }
    if (tab === 'embarques') { carregarFaturasParaEmbarque(); carregarEmbarques(); }
}

// ============================================================
// INIT - auto load on page ready
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    checkConnection();
    carregarPacotes();
});

async function checkConnection() {
    try {
        const r = await fetch(BASE + '/testar-conexao');
        const d = await r.json();
        const el = document.getElementById('ewp-conn-status');
        const txt = document.getElementById('ewp-conn-text');
        const amb = document.getElementById('ewp-ambiente');
        if (d.success) {
            el.className = 'ewp-status ok';
            txt.textContent = 'Conectado';
            // Mostrar ambiente
            if (d.ambiente) {
                amb.style.display = 'inline-block';
                amb.textContent = d.ambiente === 'HOMOLOGACAO' ? '⚠️ HOMOLOGAÇÃO' : '🟢 PRODUÇÃO';
                amb.className = 'ewp-badge ' + (d.ambiente === 'HOMOLOGACAO' ? 'bg-warning text-dark' : 'bg-success text-white');
            }
            // Mostrar saldo
            if (d.results && d.results.balance && d.results.balance.data) {
                const balData = d.results.balance.data;
                const saldoEl = document.getElementById('ewp-saldo');
                if (balData.data) {
                    const avail = balData.data.availableQuantity ?? balData.data.currentBalance ?? '?';
                    saldoEl.innerHTML = '| Saldo: <strong>' + avail + '</strong>';
                } else if (balData.currentBalance !== undefined) {
                    saldoEl.innerHTML = '| Saldo: <strong>' + balData.currentBalance + '</strong>';
                }
                saldoEl.style.display = 'inline';
            }
        } else { el.className = 'ewp-status err'; txt.textContent = 'Erro'; }
    } catch(e) {
        document.getElementById('ewp-conn-status').className = 'ewp-status err';
        document.getElementById('ewp-conn-text').textContent = 'Offline';
    }
}

async function testarConexaoDetalhado() {
    const painel = document.getElementById('ewp-diagnostico');
    const body = document.getElementById('ewp-diagnostico-body');
    painel.style.display = 'block';
    body.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testando conexão...';
    
    try {
        const r = await fetch(BASE + '/testar-conexao');
        const d = await r.json();
        
        let html = '<div class="alert py-2 small ' + (d.success ? 'alert-success' : 'alert-danger') + '">';
        html += d.success ? '<i class="fas fa-check-circle me-1"></i>Todos os testes passaram!' : '<i class="fas fa-times-circle me-1"></i>Alguns testes falharam';
        html += '</div>';
        html += '<table class="table table-sm small mb-0"><thead><tr><th>Endpoint</th><th>Status</th><th>Tempo</th><th>Detalhes</th></tr></thead><tbody>';
        
        const labels = {balance:'Saldo', list_packages:'Pacotes', list_containers:'Containers', list_bills:'Faturas', list_departures:'Embarques'};
        for (const [key, result] of Object.entries(d.results || {})) {
            const badge = result.success ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">FALHA</span>';
            let detail = '';
            if (result.total !== undefined) detail = 'Total: ' + result.total;
            if (!result.success && result.data && result.data.error) detail = result.data.error;
            html += '<tr><td>'+(labels[key]||key)+'</td><td>'+badge+'</td><td>'+(result.time_ms||'-')+'ms</td><td>'+detail+'</td></tr>';
        }
        html += '</tbody></table>';
        body.innerHTML = html;
    } catch(e) {
        body.innerHTML = '<div class="alert alert-danger py-2 small">Erro de conexão: '+e.message+'</div>';
    }
}

// ============================================================
// PEDIDOS - GERAR ETIQUETAS
// ============================================================
function toggleAllPedidos() {
    const c = document.getElementById('checkAllPedidos').checked;
    document.querySelectorAll('.chk-pedido').forEach(el => el.checked = c);
    updateMassBtn();
}
document.addEventListener('change', e => {
    if (e.target.classList.contains('chk-pedido')) updateMassBtn();
    if (e.target.classList.contains('chk-cnt-pacote')) updateCntCount();
});
function updateMassBtn() {
    const n = document.querySelectorAll('.chk-pedido:checked').length;
    const btn = document.getElementById('btnGerarMassa');
    if (n > 0) { btn.style.display = ''; document.getElementById('btnGerarMassaText').textContent = 'Gerar ' + n + ' etiqueta(s)'; }
    else { btn.style.display = 'none'; }
}
async function gerarEtiquetasMassa() {
    const ids = [...document.querySelectorAll('.chk-pedido:checked')].map(e => parseInt(e.value));
    if (!ids.length) return;
    if (!confirm('Gerar ' + ids.length + ' etiqueta(s) via WordPress?')) return;
    const btn = document.getElementById('btnGerarMassa');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';
    try {
        const r = await fetch(BASE + '/gerar-etiquetas-massa', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids})});
        const d = await r.json();
        const el = document.getElementById('pedidos-resultado'); el.style.display = 'block';
        if (d.success) {
            let h = '<div class="alert alert-' + (d.failed > 0 ? 'warning' : 'success') + ' py-2 small">';
            h += '<strong>' + d.generated + ' gerada(s)</strong>' + (d.failed > 0 ? ', ' + d.failed + ' falha(s)' : '');
            if (d.results) d.results.forEach(r => { if (r.tracking_number) h += '<br><code>' + r.tracking_number + '</code> — #' + r.pedido_id; if (r.error) h += '<br><span class="text-danger">#' + r.pedido_id + ': ' + r.error + '</span>'; });
            h += '</div>'; el.innerHTML = h;
            if (d.generated > 0) setTimeout(() => location.reload(), 2000);
        } else { el.innerHTML = '<div class="alert alert-danger py-2 small">' + (d.error || 'Erro') + '</div>'; }
    } catch(e) { alert('Erro: ' + e.message); }
    btn.disabled = false; updateMassBtn();
}

// ============================================================
// PACOTES (auto-load)
// ============================================================
async function carregarPacotes() {
    const tbody = document.getElementById('pacotes-body');
    try {
        const r = await fetch(BASE + '/listar-pacotes?without_container=1');
        const d = await r.json();
        tbody.innerHTML = '';
        if (d.success && d.data && d.data.length > 0) {
            d.data.forEach(p => {
                tbody.innerHTML += '<tr><td>' + (p.order_id||'-') + '</td><td><code class="small">' + (p.tracking_code||'-') + '</code></td><td class="d-none d-md-table-cell">' + (p.total_weight ? (p.total_weight/1000).toFixed(1)+'kg' : '-') + '</td><td>' + (p.wp_post_id ? '<a href="'+BASE+'/pdf/pacote/'+p.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf"></i></a>' : '') + '</td></tr>';
            });
        } else { tbody.innerHTML = '<tr><td colspan="4" class="ewp-empty"><i class="fas fa-inbox"></i>Nenhum pacote sem container</td></tr>'; }
    } catch(e) { tbody.innerHTML = '<tr><td colspan="4" class="text-danger">Erro: '+e.message+'</td></tr>'; }
}

// ============================================================
// CONTAINERS
// ============================================================
async function carregarPacotesParaContainer() {
    const el = document.getElementById('cnt-pacotes-lista');
    try {
        const r = await fetch(BASE + '/listar-pacotes?without_container=1&per_page=200');
        const d = await r.json();
        if (d.success && d.data && d.data.length > 0) {
            let h = '<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+d.data.length+' pacote(s)</small><a href="#" onclick="document.querySelectorAll(\'.chk-cnt-pacote\').forEach(e=>e.checked=true);updateCntCount();return false;" class="small">Todos</a></div>';
            d.data.forEach(p => { h += '<div class="form-check"><input class="form-check-input chk-cnt-pacote" type="checkbox" value="'+p.tracking_code+'"><label class="form-check-label small"><code>'+p.tracking_code+'</code> — '+(p.order_id||'')+'</label></div>'; });
            el.innerHTML = h;
        } else { el.innerHTML = '<span class="small text-muted">Nenhum pacote disponível</span>'; }
    } catch(e) { el.innerHTML = '<span class="text-danger small">'+e.message+'</span>'; }
    
    // Auto-preencher nº remessa com próximo disponível
    autoPreencherRemessa();
}

async function autoPreencherRemessa() {
    try {
        const r = await fetch(BASE + '/listar-containers?per_page=200');
        const d = await r.json();
        let max = 0;
        if (d.success && d.data) {
            d.data.forEach(c => {
                const dn = parseInt(c.dispatch_number) || 0;
                if (dn > max) max = dn;
            });
        }
        const input = document.getElementById('cnt-dispatch');
        if (!input.value) input.value = max + 1;
    } catch(e) {}
}
function updateCntCount() {
    const n = document.querySelectorAll('.chk-cnt-pacote:checked').length;
    document.getElementById('cnt-selected-count').textContent = n > 0 ? n + ' pacote(s) selecionado(s)' : '';
}

function validarESelecionar() {
    const raw = document.getElementById('cnt-paste-trackings').value.trim();
    if (!raw) { alert('Cole pelo menos 1 tracking code.'); return; }
    
    const colados = raw.split(/[\n,;\s]+/).map(s => s.trim().toUpperCase()).filter(s => s.length > 5);
    if (!colados.length) { alert('Nenhum tracking code válido encontrado.'); return; }
    
    const checkboxes = document.querySelectorAll('.chk-cnt-pacote');
    const disponiveis = {};
    checkboxes.forEach(el => { disponiveis[el.value.toUpperCase()] = el; });
    
    let encontrados = [];
    let naoEncontrados = [];
    
    colados.forEach(code => {
        if (disponiveis[code]) {
            disponiveis[code].checked = true;
            encontrados.push(code);
        } else {
            naoEncontrados.push(code);
        }
    });
    
    updateCntCount();
    
    const el = document.getElementById('cnt-validacao');
    el.style.display = 'block';
    let html = '';
    if (encontrados.length > 0) {
        html += '<div class="text-success small"><i class="fas fa-check-circle me-1"></i><strong>' + encontrados.length + ' selecionado(s)</strong></div>';
    }
    if (naoEncontrados.length > 0) {
        html += '<div class="text-danger small mt-1"><i class="fas fa-times-circle me-1"></i><strong>' + naoEncontrados.length + ' NÃO encontrado(s):</strong>';
        naoEncontrados.forEach(c => { html += '<br><code>' + c + '</code> — não disponível (já em container ou não existe)'; });
        html += '</div>';
    }
    el.innerHTML = html;
}

async function criarContainer(event) {
    event.preventDefault();
    const codes = [...document.querySelectorAll('.chk-cnt-pacote:checked')].map(e => e.value);
    if (!codes.length) { alert('Selecione pelo menos 1 pacote.'); return; }
    const data = {
        dispatchNumber: parseInt(document.getElementById('cnt-dispatch').value),
        trackingCodes: codes,
        originCountry: document.getElementById('cnt-origin-country').value.toUpperCase(),
        originOperatorName: document.getElementById('cnt-origin-operator').value,
        destinationOperatorName: document.getElementById('cnt-dest-operator').value,
        postalCategoryCode: document.getElementById('cnt-postal-category').value,
        serviceSubclassCode: document.getElementById('cnt-subclass').value,
        unitType: document.getElementById('cnt-unit-type').value,
        triageGroup: document.getElementById('cnt-triage').value,
        awb: document.getElementById('cnt-awb').value,
    };
    const btn = document.getElementById('btn-criar-container');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Criando...';
    try {
        const r = await fetch(BASE + '/criar-container', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
        const d = await r.json();
        const el = document.getElementById('container-resultado'); el.style.display = 'block';
        if (d.success) {
            el.innerHTML = '<div class="alert alert-success py-2 small"><strong>Container criado!</strong> Unit Code: <code>'+d.unit_code+'</code></div>';
            carregarPacotesParaContainer(); carregarContainers();
        } else { el.innerHTML = '<div class="alert alert-danger py-2 small">'+d.error+'</div>'; }
    } catch(e) { alert('Erro: '+e.message); }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-box me-1"></i>Criar Container';
}

async function carregarContainers() {
    const tbody = document.getElementById('containers-body');
    try {
        const r = await fetch(BASE + '/listar-containers?without_bill=1');
        const d = await r.json();
        tbody.innerHTML = '';
        if (d.success && d.data && d.data.length > 0) {
            d.data.forEach(c => {
                const tks = Array.isArray(c.tracking_codes) ? c.tracking_codes.length : 0;
                tbody.innerHTML += '<tr><td><input type="checkbox" class="chk-container" value="'+c.wp_post_id+'"></td><td>'+c.dispatch_number+'</td><td><code class="small">'+( c.unit_code||'-')+'</code></td><td>'+tks+'</td><td class="d-none d-md-table-cell">'+(c.created_at||'-')+'</td><td>'+(c.wp_post_id?'<a href="'+BASE+'/pdf/container/'+c.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf"></i></a>':'')+'</td></tr>';
            });
        } else { tbody.innerHTML = '<tr><td colspan="6" class="ewp-empty"><i class="fas fa-inbox"></i>Nenhum container sem fatura</td></tr>'; }
    } catch(e) { tbody.innerHTML = '<tr><td colspan="6" class="text-danger">'+e.message+'</td></tr>'; }
}
function toggleAllContainers() { const c = document.getElementById('checkAllContainers').checked; document.querySelectorAll('.chk-container').forEach(e=>e.checked=c); }

// ============================================================
// FATURAS
// ============================================================
async function carregarContainersParaFatura() {
    const el = document.getElementById('fatura-containers-lista');
    try {
        const r = await fetch(BASE + '/listar-containers?without_bill=1');
        const d = await r.json();
        if (d.success && d.data && d.data.length > 0) {
            let h = '<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+d.data.length+' container(s) disponível(is)</small><a href="#" onclick="document.querySelectorAll(\'.chk-fatura-cnt\').forEach(e=>e.checked=true);return false;" class="small">Todos</a></div>';
            d.data.forEach(c => {
                const tks = Array.isArray(c.tracking_codes) ? c.tracking_codes.length : 0;
                h += '<div class="form-check"><input class="form-check-input chk-fatura-cnt" type="checkbox" value="'+c.wp_post_id+'"><label class="form-check-label small">Remessa <strong>'+c.dispatch_number+'</strong> — <code>'+( c.unit_code||'')+'</code> — '+tks+' pacotes</label></div>';
            });
            el.innerHTML = h;
        } else { el.innerHTML = '<span class="small text-muted">Nenhum container sem fatura disponível.</span>'; }
    } catch(e) { el.innerHTML = '<span class="text-danger small">'+e.message+'</span>'; }
}

async function criarFatura() {
    const ids = [...document.querySelectorAll('.chk-fatura-cnt:checked')].map(e => parseInt(e.value));
    if (!ids.length) { alert('Selecione pelo menos 1 container.'); return; }
    if (!confirm('Criar fatura com '+ids.length+' container(s)? Operação irreversível.')) return;
    const btn = document.getElementById('btn-criar-fatura');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processando...';
    try {
        const r = await fetch(BASE + '/criar-fatura', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({containerIds:ids})});
        const d = await r.json();
        const el = document.getElementById('fatura-resultado'); el.style.display = 'block';
        if (d.success) { el.innerHTML = '<div class="alert alert-success py-2 small">Fatura criada: <code>'+d.cn38_code+'</code></div>'; carregarContainersParaFatura(); carregarFaturas(); }
        else { el.innerHTML = '<div class="alert alert-danger py-2 small">'+d.error+'</div>'; }
    } catch(e) { alert('Erro: '+e.message); }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-invoice me-1"></i>Criar Fatura';
}
async function carregarFaturas() {
    const tbody = document.getElementById('faturas-body');
    try {
        const r = await fetch(BASE + '/listar-faturas?without_departure=1');
        const d = await r.json();
        tbody.innerHTML = '';
        if (d.success && d.data && d.data.length > 0) {
            d.data.forEach(b => {
                const dns = Array.isArray(b.dispatch_numbers) ? b.dispatch_numbers.join(', ') : '-';
                tbody.innerHTML += '<tr><td><input type="checkbox" class="chk-fatura" value="'+b.wp_post_id+'"></td><td><code class="small">'+(b.cn38_code||'-')+'</code></td><td class="d-none d-md-table-cell">'+dns+'</td><td class="d-none d-md-table-cell">'+(b.created_at||'-')+'</td><td>'+(b.wp_post_id?'<a href="'+BASE+'/pdf/fatura/'+b.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf"></i></a>':'')+'</td></tr>';
            });
        } else { tbody.innerHTML = '<tr><td colspan="5" class="ewp-empty"><i class="fas fa-inbox"></i>Nenhuma fatura disponível</td></tr>'; }
    } catch(e) { tbody.innerHTML = '<tr><td colspan="5" class="text-danger">'+e.message+'</td></tr>'; }
}
function toggleAllFaturas() { const c = document.getElementById('checkAllFaturas').checked; document.querySelectorAll('.chk-fatura').forEach(e=>e.checked=c); }

// ============================================================
// EMBARQUES
// ============================================================
async function carregarFaturasParaEmbarque() {
    const el = document.getElementById('emb-faturas-lista');
    try {
        const r = await fetch(BASE + '/listar-faturas?without_departure=1');
        const d = await r.json();
        if (d.success && d.data && d.data.length > 0) {
            let h = '<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+d.data.length+' fatura(s)</small><a href="#" onclick="document.querySelectorAll(\'.chk-emb-fatura\').forEach(e=>e.checked=true);return false;" class="small">Todas</a></div>';
            d.data.forEach(b => { h += '<div class="form-check"><input class="form-check-input chk-emb-fatura" type="checkbox" value="'+b.wp_post_id+'"><label class="form-check-label small">CN38: <code>'+(b.cn38_code||'-')+'</code></label></div>'; });
            el.innerHTML = h;
        } else { el.innerHTML = '<span class="small text-muted">Nenhuma fatura sem embarque.</span>'; }
    } catch(e) { el.innerHTML = '<span class="text-danger small">'+e.message+'</span>'; }
}

async function criarEmbarque(event) {
    event.preventDefault();
    const billIds = [...document.querySelectorAll('.chk-emb-fatura:checked')].map(e=>parseInt(e.value));
    if (!billIds.length) { alert('Selecione pelo menos 1 fatura.'); return; }
    const data = {
        billIds: billIds,
        flightNumber: parseInt(document.getElementById('emb-flight').value),
        airlineCode: document.getElementById('emb-airline').value.toUpperCase(),
        departureDate: new Date(document.getElementById('emb-departure-date').value).toISOString(),
        departureAirportCode: document.getElementById('emb-departure-airport').value.toUpperCase(),
        arrivalDate: new Date(document.getElementById('emb-arrival-date').value).toISOString(),
        arrivalAirportCode: document.getElementById('emb-arrival-airport').value.toUpperCase(),
    };
    const btn = document.getElementById('btn-criar-embarque');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Confirmando...';
    try {
        const r = await fetch(BASE + '/criar-embarque', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
        const d = await r.json();
        const el = document.getElementById('embarque-resultado'); el.style.display = 'block';
        if (d.success) { el.innerHTML = '<div class="alert alert-success py-2 small">Embarque confirmado!</div>'; carregarFaturas(); carregarEmbarques(); }
        else { el.innerHTML = '<div class="alert alert-danger py-2 small">'+d.error+'</div>'; }
    } catch(e) { alert('Erro: '+e.message); }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-plane me-1"></i>Confirmar Embarque';
}
async function carregarEmbarques() {
    const tbody = document.getElementById('embarques-body');
    try {
        const r = await fetch(BASE + '/listar-embarques');
        const d = await r.json();
        tbody.innerHTML = '';
        if (d.success && d.data && d.data.length > 0) {
            d.data.forEach(dep => {
                const fl = dep.flight || {};
                const codes = Array.isArray(dep.cn38_codes) ? dep.cn38_codes.join(', ') : '-';
                const st = dep.status === 'confirmed' ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Erro</span>';
                tbody.innerHTML += '<tr><td>'+(fl.flightNumber||'-')+'</td><td>'+(fl.airlineCode||'-')+'</td><td>'+(fl.departureDate?new Date(fl.departureDate).toLocaleDateString('pt-BR'):'-')+'</td><td class="d-none d-md-table-cell">'+(fl.arrivalDate?new Date(fl.arrivalDate).toLocaleDateString('pt-BR'):'-')+'</td><td><code class="small">'+codes+'</code></td><td>'+st+'</td></tr>';
            });
        } else { tbody.innerHTML = '<tr><td colspan="6" class="ewp-empty"><i class="fas fa-inbox"></i>Nenhum embarque</td></tr>'; }
    } catch(e) { tbody.innerHTML = '<tr><td colspan="6" class="text-danger">'+e.message+'</td></tr>'; }
}
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
