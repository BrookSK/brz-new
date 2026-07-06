<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><i class="fab fa-wordpress me-2"></i>Etiquetas via WordPress</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial">← Correios Mundial (antigo)</a>
            <a class="btn btn-sm btn-outline-primary" href="https://etiquetas.brazilianashop.com.br/wp-admin/" target="_blank"><i class="fas fa-external-link-alt me-1"></i>Abrir WP Admin</a>
        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-teste">🧪 Teste de Integração</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-etiquetas">📦 Etiquetas</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-containers">📋 Containers</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-faturas">🧾 Faturas</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-embarques">✈️ Embarques</a></li>
    </ul>

    <div class="tab-content">
        <!-- TAB: TESTE DE INTEGRAÇÃO -->
        <div class="tab-pane fade show active" id="tab-teste">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Teste de Conexão com WordPress</strong>
                    <button class="btn btn-sm btn-primary" onclick="executarTeste()">
                        <i class="fas fa-play me-1"></i>Executar Teste
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted">Clique em "Executar Teste" para verificar a conexão com o WordPress Etiquetas.</p>
                    <div id="teste-resultado" style="display:none;">
                        <div id="teste-status" class="alert"></div>
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Endpoint</th><th>Status</th><th>Tempo</th><th>Detalhes</th></tr>
                            </thead>
                            <tbody id="teste-tabela"></tbody>
                        </table>
                    </div>
                    <div id="teste-loading" style="display:none;" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2">Testando conexão...</div>
                    </div>
                </div>
            </div>

            <!-- Fluxo explicativo -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header"><strong>📋 Fluxo de Uso</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-body text-center">
                                    <div class="h1 text-primary">1️⃣</div>
                                    <h6>Gerar Etiquetas</h6>
                                    <p class="small text-muted">Selecione os pedidos em Caixa Fechada e gere etiquetas em massa. O WordPress cria no Correios e retorna o rastreio.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-body text-center">
                                    <div class="h1 text-success">2️⃣</div>
                                    <h6>Criar Container</h6>
                                    <p class="small text-muted">Agrupe os pacotes gerados em um container (unitizador). Informe o nº da remessa e selecione os tracking codes.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-warning">
                                <div class="card-body text-center">
                                    <div class="h1 text-warning">3️⃣</div>
                                    <h6>Criar Fatura (CN38)</h6>
                                    <p class="small text-muted">Selecione os containers prontos e gere a fatura. O código CN38 é retornado automaticamente.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-danger">
                                <div class="card-body text-center">
                                    <div class="h1 text-danger">4️⃣</div>
                                    <h6>Confirmar Embarque</h6>
                                    <p class="small text-muted">Informe dados do voo e confirme o embarque com as faturas. Depois é só gerar os PDFs no WordPress.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: ETIQUETAS -->
        <div class="tab-pane fade" id="tab-etiquetas">
            <!-- SEÇÃO 1: Pedidos prontos para gerar etiqueta -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Pedidos em Caixa Fechada (prontos para gerar etiqueta)</strong>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-warning" id="btnGerarMassa" onclick="gerarEtiquetasMassa()" style="display:none;">
                            <i class="fas fa-bolt me-1"></i>Gerar Selecionados
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="pedidos-loading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div> Gerando etiquetas...
                    </div>
                    <div id="pedidos-resultado" class="mb-3" style="display:none;"></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="checkAllPedidos" onclick="toggleAllPedidos()"></th>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Peso</th>
                                    <th>Medidas (CxLxA)</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody id="pedidos-body">
                                <?php
                                // Buscar pedidos em Caixa Fechada sem etiqueta
                                $pedidosCaixaFechada = [];
                                try {
                                    $conn = \Config\Database::getConnection();
                                    $sql = "SELECT p.id, p.created_at, p.peso_total, p.altura, p.largura, p.comprimento, u.nome as cliente_nome
                                            FROM pedidos p
                                            LEFT JOIN usuarios u ON u.id = p.usuario_id
                                            LEFT JOIN correios_packet_etiquetas cpe ON cpe.pedido_id = p.id
                                            WHERE LOWER(COALESCE(p.status,'')) IN ('produto_consolidado','consolidado')
                                              AND cpe.id IS NULL
                                            ORDER BY p.created_at ASC
                                            LIMIT 200";
                                    $st = $conn->prepare($sql);
                                    $st->execute();
                                    $pedidosCaixaFechada = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                                } catch (\Exception $e) {
                                    $pedidosCaixaFechada = [];
                                }

                                if (empty($pedidosCaixaFechada)): ?>
                                    <tr><td colspan="6" class="text-muted">Nenhum pedido em Caixa Fechada aguardando etiqueta.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pedidosCaixaFechada as $ped): ?>
                                        <tr>
                                            <td><input type="checkbox" class="chk-pedido" value="<?= (int) $ped['id'] ?>"></td>
                                            <td><strong>#<?= str_pad((string) $ped['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                            <td><?= htmlspecialchars((string) ($ped['cliente_nome'] ?? '-')) ?></td>
                                            <td><?= !empty($ped['peso_total']) ? number_format((float) $ped['peso_total'], 2, ',', '.') . ' kg' : '<span class="text-danger">-</span>' ?></td>
                                            <td>
                                                <?php if (!empty($ped['comprimento']) && !empty($ped['largura']) && !empty($ped['altura'])): ?>
                                                    <?= $ped['comprimento'] ?>x<?= $ped['largura'] ?>x<?= $ped['altura'] ?> cm
                                                <?php else: ?>
                                                    <span class="text-danger">Sem medidas</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= !empty($ped['created_at']) ? date('d/m/Y', strtotime((string) $ped['created_at'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SEÇÃO 2: Pacotes já gerados no WordPress (sem container) -->
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Pacotes sem Container (disponíveis)</strong>
                    <button class="btn btn-sm btn-outline-primary" onclick="carregarPacotes()"><i class="fas fa-sync me-1"></i>Atualizar</button>
                </div>
                <div class="card-body">
                    <div id="pacotes-loading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div> Carregando...
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" id="tabela-pacotes">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="checkAllPacotes" onclick="toggleAllPacotes()"></th>
                                    <th>Pedido</th>
                                    <th>Tracking Code</th>
                                    <th>Peso</th>
                                    <th>Data</th>
                                    <th>PDF</th>
                                </tr>
                            </thead>
                            <tbody id="pacotes-body">
                                <tr><td colspan="6" class="text-muted">Clique em "Atualizar" para carregar os pacotes do WordPress.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: CONTAINERS -->
        <div class="tab-pane fade" id="tab-containers">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><strong>Criar Novo Container</strong></div>
                <div class="card-body">
                    <form id="form-container" onsubmit="criarContainer(event)">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Nº Remessa (Dispatch Number)*</label>
                                <input type="number" class="form-control" id="cnt-dispatch" required min="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Operador Destino*</label>
                                <select class="form-select" id="cnt-dest-operator">
                                    <option value="CWBA">CWBA - Curitiba</option>
                                    <option value="SAOD">SAOD - Guarulhos</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Subclasse*</label>
                                <select class="form-select" id="cnt-subclass">
                                    <option value="NX">NX - Standard</option>
                                    <option value="IX">IX - Express</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tipo Unidade*</label>
                                <select class="form-select" id="cnt-unit-type">
                                    <option value="2">Caixa (até 500kg)</option>
                                    <option value="1">Saco (até 30kg)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Grupo Triagem*</label>
                                <select class="form-select" id="cnt-triage">
                                    <option value="1">1 - São Paulo</option>
                                    <option value="2">2 - Valinhos</option>
                                    <option value="3">3 - Rio de Janeiro</option>
                                    <option value="4">4 - Curitiba</option>
                                    <option value="5">5 - Curitiba</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">AWB</label>
                                <input type="text" class="form-control" id="cnt-awb" placeholder="Número AWB (opcional)">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Tracking Codes Selecionados</label>
                                <div class="mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="carregarPacotesParaContainer()">
                                        <i class="fas fa-sync me-1"></i>Carregar pacotes disponíveis
                                    </button>
                                </div>
                                <div id="cnt-pacotes-lista" class="mb-2" style="max-height:200px; overflow-y:auto; border:1px solid #dee2e6; border-radius:4px; padding:8px; display:none;"></div>
                                <textarea class="form-control" id="cnt-trackings" rows="3" placeholder="Selecione acima ou cole os tracking codes aqui (um por linha)"></textarea>
                                <small class="text-muted">Marque os pacotes acima ou cole manualmente os tracking codes.</small>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success" id="btn-criar-container">
                                    <i class="fas fa-box me-1"></i>Criar Container no WordPress
                                </button>
                            </div>
                        </div>
                    </form>
                    <div id="container-resultado" class="mt-3" style="display:none;"></div>
                </div>
            </div>

            <!-- Lista de containers existentes -->
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Containers sem Fatura</strong>
                    <button class="btn btn-sm btn-outline-primary" onclick="carregarContainers()"><i class="fas fa-sync me-1"></i>Atualizar</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="checkAllContainers" onclick="toggleAllContainers()"></th>
                                    <th>Remessa</th>
                                    <th>Unit Code</th>
                                    <th>Pacotes</th>
                                    <th>Data</th>
                                    <th>PDF</th>
                                </tr>
                            </thead>
                            <tbody id="containers-body">
                                <tr><td colspan="6" class="text-muted">Clique em "Atualizar" para carregar.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: FATURAS -->
        <div class="tab-pane fade" id="tab-faturas">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><strong>Criar Nova Fatura (CN38)</strong></div>
                <div class="card-body">
                    <p class="text-muted">Selecione containers na aba Containers (marque o checkbox) e clique abaixo.</p>
                    <div id="faturas-containers-selecionados" class="mb-3"></div>
                    <button class="btn btn-warning" onclick="criarFatura()" id="btn-criar-fatura">
                        <i class="fas fa-file-invoice me-1"></i>Criar Fatura no WordPress
                    </button>
                    <div id="fatura-resultado" class="mt-3" style="display:none;"></div>
                </div>
            </div>

            <!-- Lista de faturas -->
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Faturas sem Embarque</strong>
                    <button class="btn btn-sm btn-outline-primary" onclick="carregarFaturas()"><i class="fas fa-sync me-1"></i>Atualizar</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="checkAllFaturas" onclick="toggleAllFaturas()"></th>
                                    <th>CN38 Code</th>
                                    <th>Remessas</th>
                                    <th>Data</th>
                                    <th>PDF</th>
                                </tr>
                            </thead>
                            <tbody id="faturas-body">
                                <tr><td colspan="5" class="text-muted">Clique em "Atualizar" para carregar.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: EMBARQUES -->
        <div class="tab-pane fade" id="tab-embarques">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><strong>Confirmar Embarque</strong></div>
                <div class="card-body">
                    <form id="form-embarque" onsubmit="criarEmbarque(event)">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Nº do Voo*</label>
                                <input type="number" class="form-control" id="emb-flight" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Companhia Aérea (código)*</label>
                                <input type="text" class="form-control" id="emb-airline" placeholder="Ex: LA, AA, G3" required maxlength="3">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Partida*</label>
                                <input type="datetime-local" class="form-control" id="emb-departure-date" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Aeroporto Partida*</label>
                                <input type="text" class="form-control" id="emb-departure-airport" placeholder="Ex: MIA" required maxlength="3">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Chegada*</label>
                                <input type="datetime-local" class="form-control" id="emb-arrival-date" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Aeroporto Chegada*</label>
                                <input type="text" class="form-control" id="emb-arrival-airport" placeholder="Ex: GRU" required maxlength="3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Faturas Selecionadas</label>
                                <div id="emb-faturas-selecionadas" class="text-muted">Selecione faturas na aba Faturas</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-danger" id="btn-criar-embarque">
                                    <i class="fas fa-plane me-1"></i>Confirmar Embarque no WordPress
                                </button>
                            </div>
                        </div>
                    </form>
                    <div id="embarque-resultado" class="mt-3" style="display:none;"></div>
                </div>
            </div>

            <!-- Lista de embarques -->
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Embarques</strong>
                    <button class="btn btn-sm btn-outline-primary" onclick="carregarEmbarques()"><i class="fas fa-sync me-1"></i>Atualizar</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr><th>Voo</th><th>Companhia</th><th>Partida</th><th>Chegada</th><th>CN38 Codes</th><th>Status</th><th>Data</th></tr>
                            </thead>
                            <tbody id="embarques-body">
                                <tr><td colspan="7" class="text-muted">Clique em "Atualizar" para carregar.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '/admin/etiquetas-wp';

// Estado global
let selectedTrackings = [];
let selectedContainerIds = [];
let selectedBillIds = [];

// ============================================================
// TESTE DE INTEGRAÇÃO
// ============================================================
async function executarTeste() {
    document.getElementById('teste-loading').style.display = 'block';
    document.getElementById('teste-resultado').style.display = 'none';
    
    try {
        const resp = await fetch(BASE + '/testar-conexao');
        const data = await resp.json();
        
        document.getElementById('teste-loading').style.display = 'none';
        document.getElementById('teste-resultado').style.display = 'block';
        
        const statusEl = document.getElementById('teste-status');
        statusEl.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
        statusEl.innerHTML = data.success 
            ? '<i class="fas fa-check-circle me-2"></i>' + data.message
            : '<i class="fas fa-times-circle me-2"></i>' + data.message;
        
        const tbody = document.getElementById('teste-tabela');
        tbody.innerHTML = '';
        
        const labels = {
            'balance': 'Saldo',
            'list_packages': 'Listar Pacotes',
            'list_containers': 'Listar Containers',
            'list_bills': 'Listar Faturas',
            'list_departures': 'Listar Embarques'
        };
        
        for (const [key, result] of Object.entries(data.results || {})) {
            const row = document.createElement('tr');
            const badge = result.success 
                ? '<span class="badge bg-success">OK</span>' 
                : '<span class="badge bg-danger">FALHA</span>';
            let detail = '';
            if (result.total !== undefined) detail = 'Total: ' + result.total;
            if (result.data && result.data.data) detail = JSON.stringify(result.data.data).substring(0, 100);
            if (!result.success && result.data && result.data.error) detail = result.data.error;
            
            row.innerHTML = '<td>' + (labels[key] || key) + '</td><td>' + badge + '</td><td>' + (result.time_ms || '-') + 'ms</td><td class="small">' + detail + '</td>';
            tbody.appendChild(row);
        }
    } catch (e) {
        document.getElementById('teste-loading').style.display = 'none';
        document.getElementById('teste-resultado').style.display = 'block';
        document.getElementById('teste-status').className = 'alert alert-danger';
        document.getElementById('teste-status').innerHTML = '<i class="fas fa-times-circle me-2"></i>Erro de conexão: ' + e.message;
    }
}

// ============================================================
// PEDIDOS → GERAR ETIQUETAS
// ============================================================
function toggleAllPedidos() {
    const checked = document.getElementById('checkAllPedidos').checked;
    document.querySelectorAll('.chk-pedido').forEach(el => el.checked = checked);
    atualizarBotaoMassa();
}

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('chk-pedido')) {
        atualizarBotaoMassa();
    }
    if (e.target.classList.contains('chk-pacote')) {
        atualizarTrackingsSelecionados();
    }
    if (e.target.classList.contains('chk-container')) {
        atualizarContainersSelecionados();
    }
    if (e.target.classList.contains('chk-fatura')) {
        atualizarFaturasSelecionadas();
    }
});

function atualizarBotaoMassa() {
    const selecionados = document.querySelectorAll('.chk-pedido:checked').length;
    const btn = document.getElementById('btnGerarMassa');
    if (selecionados > 0) {
        btn.style.display = 'inline-block';
        btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Gerar ' + selecionados + ' Etiqueta(s)';
    } else {
        btn.style.display = 'none';
    }
}

async function gerarEtiquetasMassa() {
    const ids = [];
    document.querySelectorAll('.chk-pedido:checked').forEach(el => {
        ids.push(parseInt(el.value));
    });
    
    if (ids.length === 0) {
        alert('Selecione pelo menos 1 pedido.');
        return;
    }
    
    if (!confirm('Gerar etiquetas para ' + ids.length + ' pedido(s) via WordPress?')) return;
    
    const btn = document.getElementById('btnGerarMassa');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Gerando...';
    document.getElementById('pedidos-loading').style.display = 'block';
    
    try {
        const resp = await fetch(BASE + '/gerar-etiquetas-massa', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ ids: ids })
        });
        const data = await resp.json();
        
        const el = document.getElementById('pedidos-resultado');
        el.style.display = 'block';
        
        if (data.success) {
            let html = '<div class="alert alert-' + (data.failed > 0 ? 'warning' : 'success') + '">';
            html += '<strong>Resultado:</strong> ' + data.generated + ' gerada(s), ' + data.failed + ' falha(s)</div>';
            
            if (data.results && data.results.length > 0) {
                html += '<table class="table table-sm"><thead><tr><th>Pedido</th><th>Status</th><th>Tracking</th><th>Erro</th></tr></thead><tbody>';
                data.results.forEach(r => {
                    const badge = r.success ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">ERRO</span>';
                    html += '<tr><td>#' + String(r.pedido_id).padStart(6, '0') + '</td><td>' + badge + '</td><td><code>' + (r.tracking_number || '-') + '</code></td><td class="small text-danger">' + (r.error || '') + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            el.innerHTML = html;
            
            // Recarregar a página após 3 segundos para atualizar a lista
            if (data.generated > 0) {
                setTimeout(() => location.reload(), 3000);
            }
        } else {
            el.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Erro desconhecido') + '</div>';
        }
    } catch (e) {
        document.getElementById('pedidos-resultado').style.display = 'block';
        document.getElementById('pedidos-resultado').innerHTML = '<div class="alert alert-danger">Erro: ' + e.message + '</div>';
    }
    
    btn.disabled = false;
    atualizarBotaoMassa();
    document.getElementById('pedidos-loading').style.display = 'none';
}

// ============================================================
// PACOTES (ETIQUETAS)
// ============================================================
async function carregarPacotes() {
    document.getElementById('pacotes-loading').style.display = 'block';
    try {
        const resp = await fetch(BASE + '/listar-pacotes?without_container=1');
        const data = await resp.json();
        
        const tbody = document.getElementById('pacotes-body');
        tbody.innerHTML = '';
        
        if (data.success && data.data && data.data.length > 0) {
            data.data.forEach(pkg => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="checkbox" class="chk-pacote" value="${pkg.tracking_code}" data-wpid="${pkg.wp_post_id}"></td>
                    <td>${pkg.order_id || '-'}</td>
                    <td><code>${pkg.tracking_code || '-'}</code></td>
                    <td>${pkg.total_weight ? (pkg.total_weight / 1000).toFixed(2) + 'kg' : '-'}</td>
                    <td>${pkg.created_at || '-'}</td>
                    <td>${pkg.wp_post_id ? '<a href="' + BASE + '/pdf/pacote/' + pkg.wp_post_id + '" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i></a>' : ''}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Nenhum pacote sem container encontrado.</td></tr>';
        }
    } catch (e) {
        alert('Erro ao carregar pacotes: ' + e.message);
    }
    document.getElementById('pacotes-loading').style.display = 'none';
}

function toggleAllPacotes() {
    const checked = document.getElementById('checkAllPacotes').checked;
    document.querySelectorAll('.chk-pacote').forEach(el => el.checked = checked);
    atualizarTrackingsSelecionados();
}

function atualizarTrackingsSelecionados() {
    selectedTrackings = [];
    document.querySelectorAll('.chk-pacote:checked').forEach(el => {
        selectedTrackings.push(el.value);
    });
    document.getElementById('cnt-trackings').value = selectedTrackings.join('\n');
}

// Listener para checkboxes de pacotes (handled by main listener above)

// ============================================================
// CONTAINERS
// ============================================================
async function carregarPacotesParaContainer() {
    const lista = document.getElementById('cnt-pacotes-lista');
    lista.style.display = 'block';
    lista.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Carregando...</span>';
    
    try {
        const resp = await fetch(BASE + '/listar-pacotes?without_container=1');
        const data = await resp.json();
        
        if (data.success && data.data && data.data.length > 0) {
            let html = '';
            data.data.forEach(pkg => {
                html += `<div class="form-check">
                    <input class="form-check-input chk-cnt-pacote" type="checkbox" value="${pkg.tracking_code}" id="cnt-pkg-${pkg.wp_post_id}" onchange="atualizarTrackingsContainer()">
                    <label class="form-check-label" for="cnt-pkg-${pkg.wp_post_id}">
                        <code>${pkg.tracking_code}</code> — Pedido: ${pkg.order_id || '-'}
                    </label>
                </div>`;
            });
            html = '<div class="mb-1"><small class="text-muted">' + data.data.length + ' pacote(s) disponível(is)</small></div>' + html;
            html += '<div class="mt-2"><button type="button" class="btn btn-xs btn-outline-secondary" onclick="document.querySelectorAll(\'.chk-cnt-pacote\').forEach(e=>e.checked=true);atualizarTrackingsContainer();">Selecionar todos</button></div>';
            lista.innerHTML = html;
        } else {
            lista.innerHTML = '<span class="text-muted">Nenhum pacote sem container disponível.</span>';
        }
    } catch (e) {
        lista.innerHTML = '<span class="text-danger">Erro: ' + e.message + '</span>';
    }
}

function atualizarTrackingsContainer() {
    const codes = [];
    document.querySelectorAll('.chk-cnt-pacote:checked').forEach(el => {
        codes.push(el.value);
    });
    document.getElementById('cnt-trackings').value = codes.join('\n');
}

async function criarContainer(event) {
    event.preventDefault();
    
    const trackingsText = document.getElementById('cnt-trackings').value.trim();
    const trackingCodes = trackingsText.split(/[\n,;]+/).map(s => s.trim()).filter(s => s.length > 0);
    
    if (trackingCodes.length === 0) {
        alert('Selecione ou cole pelo menos 1 tracking code.');
        return;
    }
    
    const data = {
        dispatchNumber: parseInt(document.getElementById('cnt-dispatch').value),
        trackingCodes: trackingCodes,
        destinationOperatorName: document.getElementById('cnt-dest-operator').value,
        serviceSubclassCode: document.getElementById('cnt-subclass').value,
        unitType: document.getElementById('cnt-unit-type').value,
        triageGroup: document.getElementById('cnt-triage').value,
        awb: document.getElementById('cnt-awb').value,
        originCountry: 'US',
        originOperatorName: 'USPS',
        postalCategoryCode: 'A',
    };
    
    document.getElementById('btn-criar-container').disabled = true;
    document.getElementById('btn-criar-container').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Criando...';
    
    try {
        const resp = await fetch(BASE + '/criar-container', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await resp.json();
        
        const el = document.getElementById('container-resultado');
        el.style.display = 'block';
        
        if (result.success) {
            el.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><strong>Container criado!</strong><br>Unit Code: <code>${result.unit_code}</code><br>Dispatch: ${result.dispatch_number}<br>Pacotes: ${trackingCodes.length}</div>`;
        } else {
            el.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>${result.error}</div>`;
        }
    } catch (e) {
        document.getElementById('container-resultado').style.display = 'block';
        document.getElementById('container-resultado').innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
    }
    
    document.getElementById('btn-criar-container').disabled = false;
    document.getElementById('btn-criar-container').innerHTML = '<i class="fas fa-box me-1"></i>Criar Container no WordPress';
}

async function carregarContainers() {
    try {
        const resp = await fetch(BASE + '/listar-containers?without_bill=1');
        const data = await resp.json();
        
        const tbody = document.getElementById('containers-body');
        tbody.innerHTML = '';
        
        if (data.success && data.data && data.data.length > 0) {
            data.data.forEach(cnt => {
                const row = document.createElement('tr');
                const tks = Array.isArray(cnt.tracking_codes) ? cnt.tracking_codes.length : 0;
                row.innerHTML = `
                    <td><input type="checkbox" class="chk-container" value="${cnt.wp_post_id}"></td>
                    <td>${cnt.dispatch_number || '-'}</td>
                    <td><code>${cnt.unit_code || '-'}</code></td>
                    <td>${tks} pacotes</td>
                    <td>${cnt.created_at || '-'}</td>
                    <td>${cnt.wp_post_id ? '<a href="' + BASE + '/pdf/container/' + cnt.wp_post_id + '" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i></a>' : ''}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Nenhum container sem fatura.</td></tr>';
        }
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}

function toggleAllContainers() {
    const checked = document.getElementById('checkAllContainers').checked;
    document.querySelectorAll('.chk-container').forEach(el => el.checked = checked);
    atualizarContainersSelecionados();
}

function atualizarContainersSelecionados() {
    selectedContainerIds = [];
    document.querySelectorAll('.chk-container:checked').forEach(el => {
        selectedContainerIds.push(parseInt(el.value));
    });
    document.getElementById('faturas-containers-selecionados').innerHTML = selectedContainerIds.length > 0
        ? '<span class="badge bg-success">' + selectedContainerIds.length + ' container(s) selecionado(s)</span>'
        : '<span class="text-muted">Nenhum container selecionado</span>';
}

// ============================================================
// FATURAS
// ============================================================
async function criarFatura() {
    if (selectedContainerIds.length === 0) {
        alert('Selecione pelo menos 1 container na aba Containers.');
        return;
    }
    
    if (!confirm('Criar fatura com ' + selectedContainerIds.length + ' container(s)? Esta operação é irreversível e acarreta custos.')) {
        return;
    }
    
    document.getElementById('btn-criar-fatura').disabled = true;
    document.getElementById('btn-criar-fatura').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processando (pode demorar até 2min)...';
    
    try {
        const resp = await fetch(BASE + '/criar-fatura', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ containerIds: selectedContainerIds })
        });
        const result = await resp.json();
        
        const el = document.getElementById('fatura-resultado');
        el.style.display = 'block';
        
        if (result.success) {
            el.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><strong>Fatura criada!</strong><br>CN38 Code: <code>${result.cn38_code}</code></div>`;
        } else {
            el.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>${result.error}</div>`;
        }
    } catch (e) {
        document.getElementById('fatura-resultado').style.display = 'block';
        document.getElementById('fatura-resultado').innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
    }
    
    document.getElementById('btn-criar-fatura').disabled = false;
    document.getElementById('btn-criar-fatura').innerHTML = '<i class="fas fa-file-invoice me-1"></i>Criar Fatura no WordPress';
}

async function carregarFaturas() {
    try {
        const resp = await fetch(BASE + '/listar-faturas?without_departure=1');
        const data = await resp.json();
        
        const tbody = document.getElementById('faturas-body');
        tbody.innerHTML = '';
        
        if (data.success && data.data && data.data.length > 0) {
            data.data.forEach(bill => {
                const row = document.createElement('tr');
                const dns = Array.isArray(bill.dispatch_numbers) ? bill.dispatch_numbers.join(', ') : '-';
                row.innerHTML = `
                    <td><input type="checkbox" class="chk-fatura" value="${bill.wp_post_id}"></td>
                    <td><code>${bill.cn38_code || '-'}</code></td>
                    <td>${dns}</td>
                    <td>${bill.created_at || '-'}</td>
                    <td>${bill.wp_post_id ? '<a href="' + BASE + '/pdf/fatura/' + bill.wp_post_id + '" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i></a>' : ''}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Nenhuma fatura sem embarque.</td></tr>';
        }
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}

function toggleAllFaturas() {
    const checked = document.getElementById('checkAllFaturas').checked;
    document.querySelectorAll('.chk-fatura').forEach(el => el.checked = checked);
    atualizarFaturasSelecionadas();
}

function atualizarFaturasSelecionadas() {
    selectedBillIds = [];
    document.querySelectorAll('.chk-fatura:checked').forEach(el => {
        selectedBillIds.push(parseInt(el.value));
    });
    document.getElementById('emb-faturas-selecionadas').innerHTML = selectedBillIds.length > 0
        ? '<span class="badge bg-warning text-dark">' + selectedBillIds.length + ' fatura(s) selecionada(s)</span>'
        : '<span class="text-muted">Selecione faturas na aba Faturas</span>';
}

// ============================================================
// EMBARQUES
// ============================================================
async function criarEmbarque(event) {
    event.preventDefault();
    
    if (selectedBillIds.length === 0) {
        alert('Selecione pelo menos 1 fatura na aba Faturas.');
        return;
    }
    
    const depDate = document.getElementById('emb-departure-date').value;
    const arrDate = document.getElementById('emb-arrival-date').value;
    
    const data = {
        billIds: selectedBillIds,
        flightNumber: parseInt(document.getElementById('emb-flight').value),
        airlineCode: document.getElementById('emb-airline').value.toUpperCase(),
        departureDate: new Date(depDate).toISOString(),
        departureAirportCode: document.getElementById('emb-departure-airport').value.toUpperCase(),
        arrivalDate: new Date(arrDate).toISOString(),
        arrivalAirportCode: document.getElementById('emb-arrival-airport').value.toUpperCase(),
    };
    
    document.getElementById('btn-criar-embarque').disabled = true;
    document.getElementById('btn-criar-embarque').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirmando...';
    
    try {
        const resp = await fetch(BASE + '/criar-embarque', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await resp.json();
        
        const el = document.getElementById('embarque-resultado');
        el.style.display = 'block';
        
        if (result.success) {
            el.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><strong>Embarque confirmado!</strong><br>Status: ${result.status}<br>CN38 Codes: ${(result.cn38_codes || []).join(', ')}</div>`;
        } else {
            el.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>${result.error}</div>`;
        }
    } catch (e) {
        document.getElementById('embarque-resultado').style.display = 'block';
        document.getElementById('embarque-resultado').innerHTML = `<div class="alert alert-danger">Erro: ${e.message}</div>`;
    }
    
    document.getElementById('btn-criar-embarque').disabled = false;
    document.getElementById('btn-criar-embarque').innerHTML = '<i class="fas fa-plane me-1"></i>Confirmar Embarque no WordPress';
}

async function carregarEmbarques() {
    try {
        const resp = await fetch(BASE + '/listar-embarques');
        const data = await resp.json();
        
        const tbody = document.getElementById('embarques-body');
        tbody.innerHTML = '';
        
        if (data.success && data.data && data.data.length > 0) {
            data.data.forEach(dep => {
                const row = document.createElement('tr');
                const fl = dep.flight || {};
                const codes = Array.isArray(dep.cn38_codes) ? dep.cn38_codes.join(', ') : '-';
                const statusBadge = dep.status === 'confirmed' 
                    ? '<span class="badge bg-success">Confirmado</span>' 
                    : '<span class="badge bg-danger">Erro</span>';
                row.innerHTML = `
                    <td>${fl.flightNumber || '-'}</td>
                    <td>${fl.airlineCode || '-'}</td>
                    <td>${fl.departureDate ? new Date(fl.departureDate).toLocaleString('pt-BR') : '-'}</td>
                    <td>${fl.arrivalDate ? new Date(fl.arrivalDate).toLocaleString('pt-BR') : '-'}</td>
                    <td><code>${codes}</code></td>
                    <td>${statusBadge}</td>
                    <td>${dep.created_at || '-'}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Nenhum embarque encontrado.</td></tr>';
        }
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/admin.php';
?>
