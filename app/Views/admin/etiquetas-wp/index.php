<?php ob_start(); ?>
<style>
    .ewp-card{border:none;box-shadow:0 1px 3px rgba(0,0,0,.08);border-radius:8px}
    .ewp-badge{font-size:.7rem;padding:3px 8px;border-radius:4px}
    .ewp-tab-btn{border:none;background:none;padding:10px 18px;font-weight:500;color:#666;border-bottom:2px solid transparent}
    .ewp-tab-btn.active{color:#0d6efd;border-bottom-color:#0d6efd}
    .ewp-tab-btn:hover{color:#0d6efd}
    .ewp-status{display:inline-block;width:8px;height:8px;border-radius:50%}
    .ewp-status.ok{background:#28a745}.ewp-status.err{background:#dc3545}
    .ewp-empty{text-align:center;padding:30px 20px;color:#999}
    .ewp-empty i{font-size:1.5rem;margin-bottom:8px;display:block}
    .ewp-tabs{overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch}
    @media(max-width:768px){.ewp-tab-btn{padding:8px 12px;font-size:.85rem}}
    .cnt-row{cursor:pointer;transition:background .15s}
    .cnt-row:hover{background:#f0f4ff}
    .cnt-row .cnt-chevron{display:inline-flex;align-items:center;justify-content:center;transition:transform .2s;color:#6c757d;width:16px;height:16px}
    .cnt-row.expanded .cnt-chevron{transform:rotate(90deg);color:#0d6efd}
    .cnt-detail-row{display:none}
    .cnt-detail-row.show{display:table-row}
    .cnt-detail-row td{padding:0!important}
    .cnt-detail-wrap{padding:.75rem 1rem;background:#f8fafe;border-left:3px solid #0d6efd}
    .cnt-detail-wrap .cnt-info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.4rem 1.2rem;margin-bottom:.75rem}
    .cnt-detail-wrap .cnt-info-item{font-size:.8rem}
    .cnt-detail-wrap .cnt-info-item label{font-weight:600;color:#6c757d;margin-bottom:0;display:block;font-size:.7rem;text-transform:uppercase}
    .cnt-detail-wrap .cnt-info-item span{color:#212529}
    .cnt-detail-wrap .cnt-pkg-table{font-size:.8rem}
    .cnt-detail-wrap .cnt-pkg-table th{font-size:.7rem;text-transform:uppercase;color:#6c757d}
</style>
<div class="container-fluid px-2 px-md-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom flex-wrap gap-2">
        <h1 class="h4 mb-0"><i class="fas fa-tags me-2 text-primary"></i>Etiquetas</h1>
        <div class="d-flex gap-2 align-items-center">
            <span id="ewp-conn-status" class="ewp-status"></span>
            <small id="ewp-conn-text" class="text-muted">Conectando...</small>
            <span id="ewp-ambiente" class="ewp-badge" style="display:none;"></span>
            <small id="ewp-saldo" class="text-muted" style="display:none;"></small>
            <button class="btn btn-xs btn-outline-secondary" onclick="testarConexaoDetalhado()" title="Diagnóstico"><i class="fas fa-stethoscope"></i></button>
        </div>
    </div>
    <div id="ewp-diagnostico" class="card ewp-card mb-3" style="display:none;">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong class="small">Diagnóstico</strong>
            <button class="btn btn-xs btn-outline-secondary" onclick="document.getElementById('ewp-diagnostico').style.display='none'">✕</button>
        </div>
        <div class="card-body p-2" id="ewp-diagnostico-body"></div>
    </div>
    <div class="ewp-tabs mb-3 border-bottom">
        <button class="ewp-tab-btn active" onclick="switchTab('etiquetas')">📦 Etiquetas</button>
        <button class="ewp-tab-btn" onclick="switchTab('containers')">📋 Containers</button>
        <button class="ewp-tab-btn" onclick="switchTab('faturas')">🧾 Faturas</button>
        <button class="ewp-tab-btn" onclick="switchTab('embarques')">✈️ Embarques</button>
        <button class="ewp-tab-btn" onclick="switchTab('documentacao')">📄 Documentação</button>
    </div>
    <!-- ETIQUETAS -->
    <div class="ewp-panel" id="panel-etiquetas">
        <!-- Campo de busca -->
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="etiquetas-busca" placeholder="Buscar por pedido, cliente, tracking..." oninput="filtrarEtiquetas(this.value)">
            </div>
        </div>
        <div class="card ewp-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong class="small">Pedidos em Caixa Fechada</strong>
                <button class="btn btn-sm btn-warning" id="btnGerarMassa" style="display:none;" onclick="gerarEtiquetasMassa()"><i class="fas fa-bolt me-1"></i><span id="btnGerarMassaText">Gerar</span></button>
            </div>
            <div class="card-body p-0">
                <div id="pedidos-resultado" class="p-3" style="display:none;"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th style="width:30px"><input type="checkbox" id="checkAllPedidos" onclick="toggleAllPedidos()"></th><th>Pedido</th><th class="d-none d-md-table-cell">Cliente</th><th>Peso</th><th class="d-none d-md-table-cell">Medidas</th></tr></thead>
                        <tbody id="pedidos-body">
<?php
$pedidosCF=[];
try{$conn=\Config\Database::getConnection();$st=$conn->prepare("SELECT p.id,p.created_at,p.peso_total,p.altura,p.largura,p.comprimento,u.nome as cliente_nome FROM pedidos p LEFT JOIN usuarios u ON u.id=p.usuario_id LEFT JOIN correios_packet_etiquetas cpe ON cpe.pedido_id=p.id WHERE LOWER(COALESCE(p.status,'')) IN ('produto_consolidado','consolidado') AND cpe.id IS NULL ORDER BY p.created_at ASC LIMIT 200");$st->execute();$pedidosCF=$st->fetchAll(\PDO::FETCH_ASSOC)?:[];}catch(\Exception $e){}
if(empty($pedidosCF)):?><tr><td colspan="5" class="ewp-empty"><i class="fas fa-check-circle"></i>Nenhum pedido aguardando</td></tr>
<?php else:foreach($pedidosCF as $ped):?>
<tr><td><input type="checkbox" class="chk-pedido" value="<?=(int)$ped['id']?>"></td><td><strong>#<?=str_pad((string)$ped['id'],6,'0',STR_PAD_LEFT)?></strong></td><td class="d-none d-md-table-cell"><?=htmlspecialchars((string)($ped['cliente_nome']??'-'))?></td><td><?=!empty($ped['peso_total'])?number_format((float)$ped['peso_total'],2,',','.').'kg':'<span class="text-danger">—</span>'?></td><td class="d-none d-md-table-cell"><?=(!empty($ped['comprimento'])&&!empty($ped['largura'])&&!empty($ped['altura']))?$ped['comprimento'].'×'.$ped['largura'].'×'.$ped['altura'].'cm':'<span class="text-danger">—</span>'?></td></tr>
<?php endforeach;endif;?>
                        </tbody></table></div></div></div>
        <div class="card ewp-card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong class="small">Pacotes gerados (sem container)</strong>
                <button class="btn btn-sm btn-outline-danger" id="btnBaixarMassa" style="display:none;" onclick="baixarEtiquetasMassa()"><i class="fas fa-download me-1"></i><span id="btnBaixarMassaText">Baixar Etiquetas</span></button>
            </div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th style="width:30px"><input type="checkbox" id="checkAllPacotes" onclick="toggleAllPacotes()"></th><th>Pedido</th><th>Cliente</th><th>Tracking</th><th class="d-none d-md-table-cell">Peso</th><th>PDF</th><th>Ações</th></tr></thead>
                <tbody id="pacotes-body"><tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr></tbody>
            </table></div></div></div>
    </div>

    <!-- CONTAINERS -->
    <div class="ewp-panel" id="panel-containers" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2"><strong class="small">Criar Container</strong></div>
            <div class="card-body">
                <form id="form-container" onsubmit="criarContainer(event)">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-2"><label class="form-label small">Nº Remessa*</label><input type="number" class="form-control form-control-sm" id="cnt-dispatch" required min="1"></div>
                        <div class="col-6 col-md-2"><label class="form-label small">País de Origem*</label><input type="text" class="form-control form-control-sm" id="cnt-origin-country" value="US" maxlength="2" readonly></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Operador Origem*</label><input type="text" class="form-control form-control-sm" id="cnt-origin-operator" value="BRAZ" maxlength="10" readonly></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Operador Destino*</label><select class="form-select form-select-sm" id="cnt-dest-operator" required><option value="SAOD" selected>SAOD - Guarulhos</option><option value="CWBA">CWBA - Curitiba</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Categoria Postal*</label><select class="form-select form-select-sm" id="cnt-postal-category" required><option value="A" selected>A – Airmail ou Priority Mail</option><option value="B">B – S.A.L Mail</option><option value="C">C – Surface Mail</option><option value="D">D – Priority terrestre</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Subclasse*</label><select class="form-select form-select-sm" id="cnt-subclass" required><option value="NX" selected>NX – Serviço padrão</option><option value="IX">IX – Serviço expresso</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Tipo Unidade*</label><select class="form-select form-select-sm" id="cnt-unit-type" required><option value="2" selected>2 - Caixa pallet até 500kg</option><option value="1">1 - Saco até 30kg</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small">N° AWB*</label><input type="text" class="form-control form-control-sm" id="cnt-awb" placeholder="" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Grupo Triagem</label><select class="form-select form-select-sm" id="cnt-triage" required><option value="1">1 - São Paulo/SP</option><option value="2">2 - Valinhos/SP</option><option value="3">3 - Rio de Janeiro/RJ</option><option value="4">4 - Curitiba/PR</option><option value="5" selected>5 - Curitiba/PR</option></select></div>
                    </div>
                    <label class="form-label small fw-bold">Pacotes:</label>
                    <div class="row g-2 mb-2"><div class="col-md-8"><textarea class="form-control form-control-sm" id="cnt-paste-trackings" rows="2" placeholder="Cole tracking codes (um por linha)"></textarea></div><div class="col-md-4 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-primary" onclick="validarESelecionar()"><i class="fas fa-check-double me-1"></i>Validar e Selecionar</button></div></div>
                    <div id="cnt-validacao" class="mb-2" style="display:none;"></div>
                    <div id="cnt-pacotes-lista" class="border rounded p-2 mb-2" style="max-height:200px;overflow-y:auto;"><span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i></span></div>
                    <div id="cnt-selected-count" class="small text-muted mb-2"></div>
                    <div id="container-resultado" class="mb-2" style="display:none;"></div>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-criar-container"><i class="fas fa-box me-1"></i>Criar Container</button>
                </form>
            </div>
        </div>
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small">Containers</strong></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle" id="containers-table">
                <thead class="table-light"><tr><th style="width:24px"></th><th>Remessa</th><th>Unit Code</th><th>Pacotes</th><th>Status</th><th class="d-none d-md-table-cell">Data</th><th>PDF</th><th>Ações</th></tr></thead>
                <tbody id="containers-body"><tr><td colspan="8" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr></tbody>
            </table></div></div>
        </div>
    </div>

    <!-- FATURAS -->
    <div class="ewp-panel" id="panel-faturas" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2"><strong class="small">Criar Fatura (CN38)</strong></div>
            <div class="card-body">
                <label class="form-label small">Selecione containers para faturar:</label>
                <div id="fatura-containers-lista" class="border rounded p-2 mb-2" style="max-height:200px;overflow-y:auto;"><span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i></span></div>
                <div id="fatura-resultado" class="mb-2" style="display:none;"></div>
                <button class="btn btn-warning btn-sm" onclick="criarFatura()" id="btn-criar-fatura"><i class="fas fa-file-invoice me-1"></i>Criar Fatura</button>
            </div>
        </div>
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small">Faturas</strong></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>CN38</th><th>Remessas</th><th>Status</th><th class="d-none d-md-table-cell">Data</th><th>PDF</th><th>Ações</th></tr></thead>
                <tbody id="faturas-body"><tr><td colspan="6" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr></tbody>
            </table></div></div>
        </div>
    </div>

    <!-- EMBARQUES -->
    <div class="ewp-panel" id="panel-embarques" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2"><strong class="small">Confirmar Embarque</strong></div>
            <div class="card-body">
                <form id="form-embarque" onsubmit="criarEmbarque(event)">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-2"><label class="form-label small">Nº Voo*</label><input type="number" class="form-control form-control-sm" id="emb-flight" required max="999999"></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Cia Aérea*</label><select class="form-select form-select-sm" id="emb-airline" required><option value="M3" selected>LATAM CARGO BRASIL (M3)</option><option value="AA">American Airlines (AA)</option><option value="AD">Azul (AD)</option><option value="AF">Air France (AF)</option><option value="BA">British Airways (BA)</option><option value="CV">Cargolux (CV)</option><option value="DL">Delta (DL)</option><option value="EK">Emirates (EK)</option><option value="FX">FedEx (FX)</option><option value="G3">Gol (G3)</option><option value="KL">KLM (KL)</option><option value="LA">LATAM (LA)</option><option value="LH">Lufthansa (LH)</option><option value="M6">Amerijet (M6)</option><option value="QR">Qatar (QR)</option><option value="QT">Tampa Cargo (QT)</option><option value="TK">Turkish (TK)</option><option value="TP">TAP (TP)</option><option value="UA">United (UA)</option><option value="UC">Latam Cargo (UC)</option><option value="5X">UPS (5X)</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Partida*</label><input type="datetime-local" class="form-control form-control-sm" id="emb-departure-date" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Aeroporto Partida*</label><input type="text" class="form-control form-control-sm" id="emb-departure-airport" value="MIA" required maxlength="3"></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Chegada*</label><input type="datetime-local" class="form-control form-control-sm" id="emb-arrival-date" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Aeroporto Chegada*</label><input type="text" class="form-control form-control-sm" id="emb-arrival-airport" value="GRU" required maxlength="3"></div>
                    </div>
                    <label class="form-label small">Faturas para embarcar:</label>
                    <div id="emb-faturas-lista" class="border rounded p-2 mb-2" style="max-height:150px;overflow-y:auto;"><span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i></span></div>
                    <div id="embarque-resultado" class="mb-2" style="display:none;"></div>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-criar-embarque"><i class="fas fa-plane me-1"></i>Confirmar Embarque</button>
                </form>
            </div>
        </div>
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small">Embarques</strong></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Voo</th><th>Cia</th><th>Partida</th><th class="d-none d-md-table-cell">Chegada</th><th>CN38</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody id="embarques-body"><tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr></tbody>
            </table></div></div>
        </div>
    </div>

    <!-- DOCUMENTAÇÃO -->
    <div class="ewp-panel" id="panel-documentacao" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong class="small">Documentação por Embarque</strong>
                <button class="btn btn-sm btn-outline-primary" onclick="carregarDocumentacao()"><i class="fas fa-sync me-1"></i>Atualizar</button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Baixe os PDFs dos containers e da fatura de cada embarque. Selecione um embarque para ver os documentos disponíveis.</p>
                <div id="doc-loading" class="text-center py-3" style="display:none;"><i class="fas fa-spinner fa-spin me-1"></i> Carregando...</div>
                <div id="doc-lista"></div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE='/admin/etiquetas-wp';

// Filtro de busca client-side
function filtrarEtiquetas(termo) {
    termo = termo.toLowerCase().trim();
    // Filtrar tabela Caixa Fechada
    document.querySelectorAll('#pedidos-body tr').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        tr.style.display = (!termo || text.includes(termo)) ? '' : 'none';
    });
    // Filtrar tabela Pacotes gerados
    document.querySelectorAll('#pacotes-body tr').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        tr.style.display = (!termo || text.includes(termo)) ? '' : 'none';
    });
}

function switchTab(t){document.querySelectorAll('.ewp-panel').forEach(p=>p.style.display='none');document.querySelectorAll('.ewp-tab-btn').forEach(b=>b.classList.remove('active'));document.getElementById('panel-'+t).style.display='block';event.target.classList.add('active');if(t==='containers'){carregarPacotesParaContainer();carregarContainers();}if(t==='faturas'){carregarContainersParaFatura();carregarFaturas();}if(t==='embarques'){carregarFaturasParaEmbarque();carregarEmbarques();}if(t==='documentacao'){carregarDocumentacao();}}
document.addEventListener('DOMContentLoaded',()=>{checkConnection();carregarPacotes();});
document.addEventListener('change',e=>{if(e.target.classList.contains('chk-pedido'))updateMassBtn();if(e.target.classList.contains('chk-cnt-pacote'))updateCntCount();});

// CONNECTION
async function checkConnection(){try{const r=await fetch(BASE+'/testar-conexao');const d=await r.json();const el=document.getElementById('ewp-conn-status');const txt=document.getElementById('ewp-conn-text');const amb=document.getElementById('ewp-ambiente');const saldo=document.getElementById('ewp-saldo');if(d.success){el.className='ewp-status ok';txt.textContent='Conectado';if(d.ambiente){amb.style.display='inline-block';amb.textContent=d.ambiente==='HOMOLOGACAO'?'⚠️ HOMOLOGAÇÃO':'🟢 PRODUÇÃO';amb.className='ewp-badge '+(d.ambiente==='HOMOLOGACAO'?'bg-warning text-dark':'bg-success text-white');}}else{el.className='ewp-status err';txt.textContent='Erro';}}catch(e){document.getElementById('ewp-conn-status').className='ewp-status err';document.getElementById('ewp-conn-text').textContent='Offline';}carregarSaldoFinanceiro();}
async function carregarSaldoFinanceiro(){const saldo=document.getElementById('ewp-saldo');try{const r=await fetch(BASE+'/saldo');const d=await r.json();if(d.success&&d.currentBalance!==null&&d.currentBalance!==undefined){const val=parseFloat(d.currentBalance);const fmt=val.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});saldo.innerHTML='| Saldo: <strong>'+fmt+'</strong>';saldo.style.display='inline';}}catch(e){}}
async function testarConexaoDetalhado(){const p=document.getElementById('ewp-diagnostico');const b=document.getElementById('ewp-diagnostico-body');p.style.display='block';b.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Testando...';try{const r=await fetch(BASE+'/testar-conexao');const d=await r.json();let h='<div class="alert py-2 small '+(d.success?'alert-success':'alert-danger')+'">'+(d.success?'✅ Todos OK':'❌ Falhas')+'</div><table class="table table-sm small mb-0"><thead><tr><th>Endpoint</th><th>Status</th><th>Tempo</th><th>Detalhes</th></tr></thead><tbody>';const labels={balance:'Saldo',list_packages:'Pacotes',list_containers:'Containers',list_bills:'Faturas',list_departures:'Embarques'};for(const[k,v]of Object.entries(d.results||{})){const badge=v.success?'<span class="badge bg-success">OK</span>':'<span class="badge bg-danger">FALHA</span>';let det='';if(v.total!==undefined)det='Total: '+v.total;if(!v.success&&v.data&&v.data.error)det=v.data.error;h+='<tr><td>'+(labels[k]||k)+'</td><td>'+badge+'</td><td>'+(v.time_ms||'-')+'ms</td><td>'+det+'</td></tr>';}h+='</tbody></table>';b.innerHTML=h;}catch(e){b.innerHTML='<div class="alert alert-danger py-2 small">'+e.message+'</div>';}}

// PEDIDOS
function toggleAllPedidos(){const c=document.getElementById('checkAllPedidos').checked;document.querySelectorAll('.chk-pedido').forEach(el=>el.checked=c);updateMassBtn();}
function updateMassBtn(){const n=document.querySelectorAll('.chk-pedido:checked').length;const btn=document.getElementById('btnGerarMassa');if(n>0){btn.style.display='';document.getElementById('btnGerarMassaText').textContent='Gerar '+n+' etiqueta(s)';}else{btn.style.display='none';}}
async function gerarEtiquetasMassa(){const ids=[...document.querySelectorAll('.chk-pedido:checked')].map(e=>parseInt(e.value));if(!ids.length)return;if(!confirm('Gerar '+ids.length+' etiqueta(s) via WordPress?'))return;const btn=document.getElementById('btnGerarMassa');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';try{const r=await fetch(BASE+'/gerar-etiquetas-massa',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids})});const d=await r.json();const el=document.getElementById('pedidos-resultado');el.style.display='block';if(d.success){let h='<div class="alert alert-'+(d.failed>0?'warning':'success')+' py-2 small"><strong>'+d.generated+' gerada(s)</strong>'+(d.failed>0?', '+d.failed+' falha(s)':'');if(d.results)d.results.forEach(r=>{if(r.tracking_number)h+='<br><code>'+r.tracking_number+'</code>';if(r.error)h+='<br><span class="text-danger">#'+r.pedido_id+': '+r.error+'</span>';});h+='</div>';el.innerHTML=h;if(d.generated>0)setTimeout(()=>location.reload(),2000);}else{el.innerHTML='<div class="alert alert-danger py-2 small">'+(d.error||'Erro')+'</div>';}}catch(e){alert('Erro: '+e.message);}btn.disabled=false;updateMassBtn();}

// PACOTES
async function carregarPacotes(){const tbody=document.getElementById('pacotes-body');try{const r=await fetch(BASE+'/listar-pacotes?without_container=1');const d=await r.json();tbody.innerHTML='';if(d.success&&d.data&&d.data.length>0){d.data.forEach(p=>{let pedidoLabel=p.order_id||'-';let pedidoIdLocal=p.pedido_id_local||0;if(p.pedido_id_local&&!String(p.order_id||'').toUpperCase().startsWith('REDIR')){pedidoLabel='#'+String(p.pedido_id_local).padStart(6,'0');}else if(pedidoLabel!=='-'&&pedidoLabel.toUpperCase().startsWith('REDIR')){/* manter como está */}const clienteNome=p.recipient_name||'-';const isCancelado=p.package_status==='cancelado';const rowClass=isCancelado?' class="text-muted" style="opacity:0.5;text-decoration:line-through;"':'';const badge=isCancelado?' <span class="badge bg-secondary" style="font-size:0.65rem;">Cancelado</span>':'';const pdfUrl=(p.wp_post_id&&!isCancelado)?BASE+'/pdf/pacote/'+p.wp_post_id:'';const checkboxHtml=(!isCancelado&&pdfUrl)?'<input type="checkbox" class="form-check-input chk-pacote-dl" data-pdf-url="'+pdfUrl+'" data-pedido="'+pedidoLabel+'" onchange="updateBaixarMassa()">':'';tbody.innerHTML+='<tr'+rowClass+'><td>'+checkboxHtml+'</td><td>'+pedidoLabel+badge+'</td><td>'+clienteNome+'</td><td><code class="small">'+(p.tracking_code||'-')+'</code></td><td class="d-none d-md-table-cell">'+(p.total_weight?(p.total_weight/1000).toFixed(1)+'kg':'-')+'</td><td>'+(p.wp_post_id&&!isCancelado?'<a href="'+BASE+'/pdf/pacote/'+p.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf"></i></a>':'')+'</td><td>'+(pedidoIdLocal&&!isCancelado?'<button class="btn btn-xs btn-outline-warning" onclick="regerarEtiquetaWp('+pedidoIdLocal+')" title="Regerar etiqueta"><i class="fas fa-redo"></i></button>':'')+'</td></tr>';});}else{tbody.innerHTML='<tr><td colspan="7" class="ewp-empty"><i class="fas fa-inbox"></i>Nenhum pacote sem container</td></tr>';}}catch(e){tbody.innerHTML='<tr><td colspan="7" class="text-danger">'+e.message+'</td></tr>';}}

// TOGGLE ALL PACOTES (download em massa)
function toggleAllPacotes(){const checked=document.getElementById('checkAllPacotes').checked;document.querySelectorAll('.chk-pacote-dl').forEach(cb=>{cb.checked=checked;});updateBaixarMassa();}
function updateBaixarMassa(){const checked=document.querySelectorAll('.chk-pacote-dl:checked').length;const btn=document.getElementById('btnBaixarMassa');const txt=document.getElementById('btnBaixarMassaText');if(btn){btn.style.display=checked>0?'':'none';}if(txt){txt.textContent=checked>1?'Baixar '+checked+' Etiquetas':'Baixar Etiqueta';}}
async function baixarEtiquetasMassa(){const checks=[...document.querySelectorAll('.chk-pacote-dl:checked')];if(!checks.length){alert('Selecione pelo menos 1 etiqueta.');return;}const btn=document.getElementById('btnBaixarMassa');if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Baixando...';}let downloaded=0;for(const cb of checks){const url=cb.getAttribute('data-pdf-url');const pedido=cb.getAttribute('data-pedido')||'etiqueta';if(!url)continue;try{const resp=await fetch(url);if(!resp.ok)continue;const blob=await resp.blob();const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='etiqueta_'+pedido.replace('#','')+'.pdf';document.body.appendChild(link);link.click();document.body.removeChild(link);URL.revokeObjectURL(link.href);downloaded++;await new Promise(r=>setTimeout(r,300));}catch(e){console.error('Erro baixando '+url,e);}}if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-download me-1"></i><span id="btnBaixarMassaText">Baixar Etiquetas</span>';updateBaixarMassa();}if(downloaded>0)alert(downloaded+' etiqueta(s) baixada(s) com sucesso!');}

// CONTAINERS
async function carregarPacotesParaContainer(){const el=document.getElementById('cnt-pacotes-lista');try{const r=await fetch(BASE+'/listar-pacotes?without_container=1&per_page=200');const d=await r.json();if(d.success&&d.data&&d.data.length>0){const available=d.data.filter(p=>p.package_status!=='cancelado');if(available.length>0){let h='<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+available.length+' pacote(s)</small><a href="#" onclick="document.querySelectorAll(\'.chk-cnt-pacote\').forEach(e=>e.checked=true);updateCntCount();return false;" class="small">Todos</a></div>';available.forEach(p=>{h+='<div class="form-check"><input class="form-check-input chk-cnt-pacote" type="checkbox" value="'+p.tracking_code+'"><label class="form-check-label small"><code>'+p.tracking_code+'</code> — '+(p.order_id||'')+'</label></div>';});el.innerHTML=h;}else{el.innerHTML='<span class="small text-muted">Nenhum pacote disponível (todos cancelados ou já em container)</span>';}}else{el.innerHTML='<span class="small text-muted">Nenhum pacote disponível</span>';}}catch(e){el.innerHTML='<span class="text-danger small">'+e.message+'</span>';}autoPreencherRemessa();}
async function autoPreencherRemessa(){try{const r=await fetch(BASE+'/listar-containers?per_page=200');const d=await r.json();let max=0;if(d.success&&d.data)d.data.forEach(c=>{const dn=parseInt(c.dispatch_number)||0;if(dn>max)max=dn;});const input=document.getElementById('cnt-dispatch');if(!input.value)input.value=max+1;}catch(e){}}
function updateCntCount(){const n=document.querySelectorAll('.chk-cnt-pacote:checked').length;document.getElementById('cnt-selected-count').textContent=n>0?n+' pacote(s) selecionado(s)':'';}
function validarESelecionar(){const raw=document.getElementById('cnt-paste-trackings').value.trim();if(!raw){alert('Cole pelo menos 1 tracking code.');return;}const colados=raw.split(/[\n,;\s]+/).map(s=>s.trim().toUpperCase()).filter(s=>s.length>5);const checkboxes=document.querySelectorAll('.chk-cnt-pacote');const disp={};checkboxes.forEach(el=>{disp[el.value.toUpperCase()]=el;});let enc=[],nao=[];colados.forEach(c=>{if(disp[c]){disp[c].checked=true;enc.push(c);}else{nao.push(c);}});updateCntCount();const el=document.getElementById('cnt-validacao');el.style.display='block';let h='';if(enc.length)h+='<div class="text-success small"><i class="fas fa-check-circle me-1"></i>'+enc.length+' selecionado(s)</div>';if(nao.length){h+='<div class="text-danger small mt-1"><i class="fas fa-times-circle me-1"></i>'+nao.length+' não encontrado(s):';nao.forEach(c=>{h+='<br><code>'+c+'</code>';});h+='</div>';}el.innerHTML=h;}
async function criarContainer(event){event.preventDefault();const codes=[...document.querySelectorAll('.chk-cnt-pacote:checked')].map(e=>e.value);if(!codes.length){alert('Selecione pelo menos 1 pacote.');return;}const data={dispatchNumber:parseInt(document.getElementById('cnt-dispatch').value),trackingCodes:codes,originCountry:document.getElementById('cnt-origin-country').value,originOperatorName:document.getElementById('cnt-origin-operator').value,destinationOperatorName:document.getElementById('cnt-dest-operator').value,postalCategoryCode:document.getElementById('cnt-postal-category').value,serviceSubclassCode:document.getElementById('cnt-subclass').value,unitType:document.getElementById('cnt-unit-type').value,triageGroup:document.getElementById('cnt-triage').value,awb:document.getElementById('cnt-awb').value};const btn=document.getElementById('btn-criar-container');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Criando...';try{const r=await fetch(BASE+'/criar-container',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const d=await r.json();const el=document.getElementById('container-resultado');el.style.display='block';if(d.success){el.innerHTML='<div class="alert alert-success py-2 small"><strong>Container criado!</strong> Unit Code: <code>'+d.unit_code+'</code></div>';carregarPacotesParaContainer();carregarContainers();}else{el.innerHTML='<div class="alert alert-danger py-2 small">'+d.error+'</div>';}}catch(e){alert('Erro: '+e.message);}btn.disabled=false;btn.innerHTML='<i class="fas fa-box me-1"></i>Criar Container';}
async function carregarContainers(){const tbody=document.getElementById('containers-body');try{const r=await fetch(BASE+'/listar-containers?per_page=50');const d=await r.json();tbody.innerHTML='';if(d.success&&d.data&&d.data.length>0){d.data.forEach((c,idx)=>{const tks=Array.isArray(c.tracking_codes)?c.tracking_codes:[];const tksCount=tks.length;const status=c.bill_id?'<span class="badge bg-success">Faturado</span>':'<span class="badge bg-warning text-dark">Aguardando fatura</span>';const isFirst=idx===0;const rowId='cnt-row-'+idx;const detailId='cnt-detail-'+idx;tbody.innerHTML+='<tr class="cnt-row '+(isFirst?'table-info':'')+'" data-cnt-idx="'+idx+'" onclick="toggleContainerDetail('+idx+')"><td><span class="cnt-chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></span></td><td><strong>'+c.dispatch_number+'</strong></td><td><code class="small">'+(c.unit_code||'-')+'</code></td><td><span class="badge bg-secondary">'+tksCount+'</span></td><td>'+status+'</td><td class="d-none d-md-table-cell">'+(c.created_at||'-')+'</td><td>'+(c.wp_post_id?'<a href="'+BASE+'/pdf/container/'+c.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation();"><i class="fas fa-file-pdf"></i></a>':'')+'</td><td><button class="btn btn-xs btn-outline-secondary" onclick="event.stopPropagation();deletarContainer('+c.wp_post_id+')" title="Deletar"><i class="fas fa-trash"></i></button></td></tr>';tbody.innerHTML+='<tr class="cnt-detail-row" id="'+detailId+'"><td colspan="8"><div class="cnt-detail-wrap" id="cnt-detail-content-'+idx+'"><div class="text-center text-muted py-2"><i class="fas fa-spinner fa-spin me-1"></i>Carregando...</div></div></td></tr>';});window._containersData=d.data;}else{tbody.innerHTML='<tr><td colspan="8" class="ewp-empty"><i class="fas fa-inbox"></i>Nenhum container</td></tr>';}}catch(e){tbody.innerHTML='<tr><td colspan="8" class="text-danger">'+e.message+'</td></tr>';}}
const _cntDetailsLoaded={};
function toggleContainerDetail(idx){const rows=document.querySelectorAll('.cnt-row');const detailRow=document.getElementById('cnt-detail-'+idx);const mainRow=document.querySelector('.cnt-row[data-cnt-idx="'+idx+'"]');if(!mainRow||!detailRow)return;const isOpen=mainRow.classList.contains('expanded');document.querySelectorAll('.cnt-row.expanded').forEach(r=>{r.classList.remove('expanded');const i=r.dataset.cntIdx;const dr=document.getElementById('cnt-detail-'+i);if(dr)dr.classList.remove('show');});if(!isOpen){mainRow.classList.add('expanded');detailRow.classList.add('show');if(!_cntDetailsLoaded[idx])loadContainerDetail(idx);}}
async function loadContainerDetail(idx){const c=window._containersData[idx];if(!c)return;const el=document.getElementById('cnt-detail-content-'+idx);const tks=Array.isArray(c.tracking_codes)?c.tracking_codes:[];let html='<div class="cnt-info-grid">';const fields=[['País Origem',c.origin_country],['Operador Origem',c.origin_operator],['Operador Destino',c.destination_operator],['Categoria Postal',c.postal_category],['Subclasse',c.service_subclass],['Tipo Unidade',formatCntUnitType(c.unit_type)],['AWB',c.awb],['Grupo Triagem',c.triage_group],['Total Pacotes',tks.length]];fields.forEach(f=>{const val=f[1];if(val!==null&&val!==undefined&&val!==''&&val!=='-'&&val!==0){html+=cntInfoItem(f[0],val);}});html+='</div>';if(tks.length>0){html+='<h6 class="mt-2 mb-1" style="font-size:.85rem"><i class="fas fa-box me-1"></i>Pacotes ('+tks.length+')</h6>';html+='<div class="table-responsive"><table class="table table-sm table-bordered cnt-pkg-table mb-0"><thead><tr><th>#</th><th>Tracking Code</th></tr></thead><tbody>';tks.forEach((tk,i)=>{html+='<tr><td>'+(i+1)+'</td><td><code>'+escHtmlCnt(tk)+'</code></td></tr>';});html+='</tbody></table></div>';}else{html+='<p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>Nenhum pacote vinculado.</p>';}el.innerHTML=html;_cntDetailsLoaded[idx]=true;}
function cntInfoItem(label,value){return '<div class="cnt-info-item"><label>'+escHtmlCnt(label)+'</label><span>'+escHtmlCnt(String(value))+'</span></div>';}
function formatCntUnitType(t){const m={'1':'1 - Saco até 30kg','2':'2 - Caixa pallet até 500kg','3':'3 - Caixa pallet até 1000kg'};return m[t]||t||'-';}
function escHtmlCnt(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
async function deletarContainer(id){if(!confirm('Deletar container? Pacotes desvinculados, unitizador cancelado.'))return;try{const r=await fetch(BASE+'/deletar-container',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({wp_post_id:id})});const txt=await r.text();let d;try{d=JSON.parse(txt);}catch(pe){alert('Resposta não-JSON do servidor (HTTP '+r.status+'):\n\n'+txt.substring(0,500));return;}if(d.success){alert('Deletado!');carregarContainers();carregarPacotesParaContainer();}else{alert('Erro: '+(d.error||d.message||JSON.stringify(d)));}}catch(e){alert('Exceção JS: '+e.message);}}

// FATURAS
async function carregarContainersParaFatura(){const el=document.getElementById('fatura-containers-lista');try{const r=await fetch(BASE+'/listar-containers?without_bill=1');const d=await r.json();if(d.success&&d.data&&d.data.length>0){let h='<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+d.data.length+' disponível(is)</small><a href="#" onclick="document.querySelectorAll(\'.chk-fatura-cnt\').forEach(e=>e.checked=true);return false;" class="small">Todos</a></div>';d.data.forEach(c=>{const tks=Array.isArray(c.tracking_codes)?c.tracking_codes.length:0;h+='<div class="form-check"><input class="form-check-input chk-fatura-cnt" type="checkbox" value="'+c.wp_post_id+'"><label class="form-check-label small">Remessa <strong>'+c.dispatch_number+'</strong> — <code>'+(c.unit_code||'')+'</code> — '+tks+' pacotes</label></div>';});el.innerHTML=h;}else{el.innerHTML='<span class="small text-muted">Nenhum container disponível</span>';}}catch(e){el.innerHTML='<span class="text-danger small">'+e.message+'</span>';}}
async function criarFatura(){const ids=[...document.querySelectorAll('.chk-fatura-cnt:checked')].map(e=>parseInt(e.value));if(!ids.length){alert('Selecione containers.');return;}if(!confirm('Criar fatura com '+ids.length+' container(s)? Irreversível.'))return;const btn=document.getElementById('btn-criar-fatura');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Processando...';try{const r=await fetch(BASE+'/criar-fatura',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({containerIds:ids})});const d=await r.json();const el=document.getElementById('fatura-resultado');el.style.display='block';if(d.success){el.innerHTML='<div class="alert alert-success py-2 small">Fatura: <code>'+d.cn38_code+'</code></div>';carregarContainersParaFatura();carregarFaturas();carregarContainers();}else{el.innerHTML='<div class="alert alert-danger py-2 small">'+d.error+'</div>';}}catch(e){alert(e.message);}btn.disabled=false;btn.innerHTML='<i class="fas fa-file-invoice me-1"></i>Criar Fatura';}
async function carregarFaturas(){const tbody=document.getElementById('faturas-body');try{const r=await fetch(BASE+'/listar-faturas?per_page=50');const d=await r.json();tbody.innerHTML='';if(d.success&&d.data&&d.data.length>0){d.data.forEach((b,idx)=>{const dns=Array.isArray(b.dispatch_numbers)?b.dispatch_numbers.join(', '):'-';const status=b.departure_id?'<span class="badge bg-success">Embarcado</span>':'<span class="badge bg-warning text-dark">Aguardando embarque</span>';const isFirst=idx===0;tbody.innerHTML+='<tr class="'+(isFirst?'table-info':'')+'"><td><code class="small">'+(b.cn38_code||'-')+'</code>'+(isFirst?' <span class="badge bg-info text-dark">Última</span>':'')+'</td><td>'+dns+'</td><td>'+status+'</td><td class="d-none d-md-table-cell">'+(b.created_at||'-')+'</td><td>'+(b.wp_post_id?'<a href="'+BASE+'/pdf/fatura/'+b.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf"></i></a>':'')+'</td><td><button class="btn btn-xs btn-outline-secondary" onclick="deletarFatura('+b.wp_post_id+')" title="Deletar"><i class="fas fa-trash"></i></button></td></tr>';});}else{tbody.innerHTML='<tr><td colspan="6" class="ewp-empty"><i class="fas fa-inbox"></i>Nenhuma fatura</td></tr>';}}catch(e){tbody.innerHTML='<tr><td colspan="6" class="text-danger">'+e.message+'</td></tr>';}}
async function deletarFatura(id){if(!confirm('Deletar fatura? Containers desvinculados.'))return;try{const r=await fetch(BASE+'/deletar-fatura',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({wp_post_id:id})});const d=await r.json();if(d.success){alert('Deletada!');carregarFaturas();carregarContainersParaFatura();carregarContainers();}else alert('Erro: '+d.error);}catch(e){alert(e.message);}}

// EMBARQUES
async function carregarFaturasParaEmbarque(){const el=document.getElementById('emb-faturas-lista');try{const r=await fetch(BASE+'/listar-faturas?without_departure=1');const d=await r.json();if(d.success&&d.data&&d.data.length>0){let h='<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+d.data.length+' fatura(s)</small><a href="#" onclick="document.querySelectorAll(\'.chk-emb-fatura\').forEach(e=>e.checked=true);return false;" class="small">Todas</a></div>';d.data.forEach((b,i)=>{const ultimaBadge=i===0?' <span class="badge bg-info text-dark" style="font-size:.65rem;">Última gerada</span>':'';h+='<div class="form-check"><input class="form-check-input chk-emb-fatura" type="checkbox" value="'+b.wp_post_id+'"><label class="form-check-label small">CN38: <code>'+(b.cn38_code||'-')+'</code>'+ultimaBadge+'</label></div>';});el.innerHTML=h;}else{el.innerHTML='<span class="small text-muted">Nenhuma fatura sem embarque</span>';}}catch(e){el.innerHTML='<span class="text-danger small">'+e.message+'</span>';}}
async function criarEmbarque(event){event.preventDefault();const billIds=[...document.querySelectorAll('.chk-emb-fatura:checked')].map(e=>parseInt(e.value));if(!billIds.length){alert('Selecione faturas.');return;}const data={billIds,flightNumber:parseInt(document.getElementById('emb-flight').value),airlineCode:document.getElementById('emb-airline').value,departureDate:new Date(document.getElementById('emb-departure-date').value).toISOString(),departureAirportCode:document.getElementById('emb-departure-airport').value.toUpperCase(),arrivalDate:new Date(document.getElementById('emb-arrival-date').value).toISOString(),arrivalAirportCode:document.getElementById('emb-arrival-airport').value.toUpperCase()};const btn=document.getElementById('btn-criar-embarque');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Confirmando...';try{const r=await fetch(BASE+'/criar-embarque',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const d=await r.json();const el=document.getElementById('embarque-resultado');el.style.display='block';if(d.success){el.innerHTML='<div class="alert alert-success py-2 small">Embarque confirmado!</div>';carregarFaturasParaEmbarque();carregarEmbarques();carregarFaturas();}else{el.innerHTML='<div class="alert alert-danger py-2 small">'+d.error+'</div>';}}catch(e){alert(e.message);}btn.disabled=false;btn.innerHTML='<i class="fas fa-plane me-1"></i>Confirmar Embarque';}
async function carregarEmbarques(){const tbody=document.getElementById('embarques-body');try{const r=await fetch(BASE+'/listar-embarques');const d=await r.json();tbody.innerHTML='';if(d.success&&d.data&&d.data.length>0){d.data.forEach(dep=>{const fl=dep.flight||{};const codes=Array.isArray(dep.cn38_codes)?dep.cn38_codes.join(', '):'-';const st=dep.status==='confirmed'?'<span class="badge bg-success">OK</span>':'<span class="badge bg-danger">Erro</span>';const errMsg=(dep.status!=='confirmed'&&dep.error_message)?'<br><small class="text-danger">'+dep.error_message+'</small>':'';tbody.innerHTML+='<tr><td>'+(fl.flightNumber||'-')+'</td><td>'+(fl.airlineCode||'-')+'</td><td>'+(fl.departureDate?new Date(fl.departureDate).toLocaleDateString('pt-BR'):'-')+'</td><td class="d-none d-md-table-cell">'+(fl.arrivalDate?new Date(fl.arrivalDate).toLocaleDateString('pt-BR'):'-')+'</td><td><code class="small">'+codes+'</code></td><td>'+st+errMsg+'</td><td><button class="btn btn-xs btn-outline-secondary" onclick="deletarEmbarque('+dep.wp_post_id+')" title="Deletar"><i class="fas fa-trash"></i></button></td></tr>';});}else{tbody.innerHTML='<tr><td colspan="7" class="ewp-empty"><i class="fas fa-inbox"></i>Nenhum embarque</td></tr>';}}catch(e){tbody.innerHTML='<tr><td colspan="7" class="text-danger">'+e.message+'</td></tr>';}}
async function deletarEmbarque(id){if(!confirm('Deletar embarque? Faturas desvinculadas.'))return;try{const r=await fetch(BASE+'/deletar-embarque',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({wp_post_id:id})});const d=await r.json();if(d.success){alert('Deletado!');carregarEmbarques();carregarFaturasParaEmbarque();carregarFaturas();}else alert('Erro: '+d.error);}catch(e){alert(e.message);}}

// DOCUMENTAÇÃO
async function carregarDocumentacao(){const el=document.getElementById('doc-lista');const loading=document.getElementById('doc-loading');el.innerHTML='';loading.style.display='block';try{const [rEmb,rCnt,rFat]=await Promise.all([fetch(BASE+'/listar-embarques'),fetch(BASE+'/listar-containers?per_page=200'),fetch(BASE+'/listar-faturas?per_page=200')]);const dEmb=await rEmb.json();const dCnt=await rCnt.json();const dFat=await rFat.json();loading.style.display='none';const embarques=(dEmb.success&&dEmb.data)?dEmb.data:[];const containers=(dCnt.success&&dCnt.data)?dCnt.data:[];const faturas=(dFat.success&&dFat.data)?dFat.data:[];if(!embarques.length){el.innerHTML='<div class="text-muted text-center py-4"><i class="fas fa-inbox d-block mb-2" style="font-size:1.5rem;"></i>Nenhum embarque encontrado.</div>';return;}let html='';embarques.forEach((emb,idx)=>{const fl=emb.flight||{};const cn38Codes=Array.isArray(emb.cn38_codes)?emb.cn38_codes:[];const depDate=fl.departureDate?new Date(fl.departureDate).toLocaleDateString('pt-BR'):'?';const status=emb.status==='confirmed'?'<span class="badge bg-success">OK</span>':'<span class="badge bg-danger">Erro</span>';html+='<div class="card border mb-3"><div class="card-header d-flex justify-content-between align-items-center py-2"><div><strong>Embarque - Voo '+escDocHtml(String(fl.flightNumber||'-'))+'</strong> <small class="text-muted ms-2">'+escDocHtml(fl.airlineCode||'')+'</small> <small class="text-muted ms-2">'+depDate+'</small> '+status+'</div><button class="btn btn-sm btn-primary" onclick="baixarDocumentosEmbarque('+idx+')"><i class="fas fa-download me-1"></i>Baixar Docs</button></div><div class="card-body p-3">';html+='<div class="row">';html+='<div class="col-md-6 mb-2"><h6 class="small fw-bold mb-2"><i class="fas fa-file-invoice me-1"></i>Faturas (CN38)</h6>';const embFaturas=faturas.filter(f=>cn38Codes.includes(f.cn38_code));if(embFaturas.length>0){embFaturas.forEach(f=>{html+='<div class="d-flex align-items-center gap-2 mb-1"><code class="small">'+escDocHtml(f.cn38_code||'-')+'</code>';if(f.wp_post_id){html+=' <a href="'+BASE+'/pdf/fatura/'+f.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger doc-pdf-link" data-url="'+BASE+'/pdf/fatura/'+f.wp_post_id+'" data-name="fatura_'+escDocHtml(f.cn38_code||'')+'"><i class="fas fa-file-pdf"></i> PDF</a>';}html+='</div>';});}else{html+='<span class="text-muted small">Nenhuma fatura vinculada</span>';}html+='</div>';html+='<div class="col-md-6 mb-2"><h6 class="small fw-bold mb-2"><i class="fas fa-box me-1"></i>Containers</h6>';const embContainerDns=[];embFaturas.forEach(f=>{if(Array.isArray(f.dispatch_numbers))f.dispatch_numbers.forEach(d=>{if(!embContainerDns.includes(d))embContainerDns.push(d);});});const embContainers=containers.filter(c=>embContainerDns.includes(c.dispatch_number)||(c.bill_id&&embFaturas.some(f=>f.wp_post_id===c.bill_id)));if(embContainers.length>0){embContainers.forEach(c=>{html+='<div class="d-flex align-items-center gap-2 mb-1"><span class="small">Remessa <strong>'+c.dispatch_number+'</strong></span> <code class="small">'+escDocHtml(c.unit_code||'')+'</code>';if(c.wp_post_id){html+=' <a href="'+BASE+'/pdf/container/'+c.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger doc-pdf-link" data-url="'+BASE+'/pdf/container/'+c.wp_post_id+'" data-name="container_'+c.dispatch_number+'"><i class="fas fa-file-pdf"></i> PDF</a>';}html+='</div>';});}else{html+='<span class="text-muted small">Nenhum container vinculado</span>';}html+='</div></div></div></div>';});el.innerHTML=html;window._docEmbarques={embarques,containers,faturas};}catch(e){loading.style.display='none';el.innerHTML='<div class="alert alert-danger">Erro ao carregar: '+e.message+'</div>';}}
async function baixarDocumentosEmbarque(idx){const card=document.querySelectorAll('#doc-lista .card')[idx];if(!card)return;const links=card.querySelectorAll('.doc-pdf-link');if(!links.length){alert('Nenhum PDF disponível para este embarque.');return;}const btn=card.querySelector('button');if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Baixando...';}let downloaded=0;for(const link of links){const url=link.getAttribute('data-url');const name=link.getAttribute('data-name')||'documento';if(!url)continue;try{const resp=await fetch(url);if(!resp.ok)continue;const blob=await resp.blob();const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name+'.pdf';document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(a.href);downloaded++;await new Promise(r=>setTimeout(r,400));}catch(e){console.error('Erro baixando '+url,e);}}if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-download me-1"></i>Baixar Docs';}if(downloaded>0)alert(downloaded+' documento(s) baixado(s)!');}
function escDocHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

// REGERAR ETIQUETA
async function regerarEtiquetaWp(pedidoId){if(!confirm('Regerar etiqueta do pedido #'+String(pedidoId).padStart(6,'0')+'?\n\nA etiqueta atual será deletada e uma nova será gerada com os dados atuais do pedido.'))return;try{const r=await fetch(BASE+'/regerar-etiqueta',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pedido_id:pedidoId})});const d=await r.json();if(d.success){alert('Etiqueta regerada com sucesso!\nNovo rastreio: '+(d.tracking_number||''));carregarPacotes();}else{alert('Erro ao regerar: '+(d.error||'Falha desconhecida'));}}catch(e){alert('Erro de rede: '+e.message);}}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
