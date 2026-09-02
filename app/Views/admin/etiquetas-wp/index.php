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
        <h1 class="h4 mb-0"><i class="fas fa-tags me-2 text-primary"></i><?= __('admin.labels_wp.title','Etiquetas') ?></h1>
        <div class="d-flex gap-2 align-items-center">
            <span id="ewp-conn-status" class="ewp-status"></span>
            <small id="ewp-conn-text" class="text-muted"><?= __('admin.labels_wp.connecting','Conectando...') ?></small>
            <span id="ewp-ambiente" class="ewp-badge" style="display:none;"></span>
            <small id="ewp-saldo" class="text-muted" style="display:none;"></small>
            <button class="btn btn-xs btn-outline-secondary" onclick="testarConexaoDetalhado()" title="<?= htmlspecialchars(__('admin.labels_wp.diagnostics','Diagnóstico'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-stethoscope"></i></button>
        </div>
    </div>
    <div id="ewp-diagnostico" class="card ewp-card mb-3" style="display:none;">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong class="small"><?= __('admin.labels_wp.diagnostics','Diagnóstico') ?></strong>
            <button class="btn btn-xs btn-outline-secondary" onclick="document.getElementById('ewp-diagnostico').style.display='none'">✕</button>
        </div>
        <div class="card-body p-2" id="ewp-diagnostico-body"></div>
    </div>
    <div class="ewp-tabs mb-3 border-bottom">
        <button class="ewp-tab-btn active" onclick="switchTab('etiquetas')">📦 <?= __('admin.labels_wp.tab_labels','Etiquetas') ?></button>
        <button class="ewp-tab-btn" onclick="switchTab('containers')">📋 <?= __('admin.labels_wp.tab_containers','Containers') ?></button>
        <button class="ewp-tab-btn" onclick="switchTab('faturas')">🧾 <?= __('admin.labels_wp.tab_invoices','Faturas') ?></button>
        <button class="ewp-tab-btn" onclick="switchTab('embarques')">✈️ <?= __('admin.labels_wp.tab_shipments','Embarques') ?></button>
        <button class="ewp-tab-btn" onclick="switchTab('documentacao')">📄 <?= __('admin.labels_wp.tab_documentation','Documentação') ?></button>
    </div>
    <!-- ETIQUETAS -->
    <div class="ewp-panel" id="panel-etiquetas">
        <!-- Campo de busca -->
        <div class="mb-3 d-flex gap-2 align-items-center">
            <div class="input-group flex-grow-1">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="etiquetas-busca" placeholder="<?= htmlspecialchars(__('admin.labels_wp.search_placeholder','Buscar por pedido, cliente, tracking...'), ENT_QUOTES, 'UTF-8') ?>" oninput="filtrarEtiquetas(this.value)">
            </div>
            <button class="btn btn-success btn-sm flex-shrink-0" onclick="abrirModalNovaMala()"><i class="fas fa-suitcase me-1"></i><?= __('admin.labels_wp.new_bag','Nova Mala') ?></button>
        </div>
        <div class="card ewp-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong class="small"><?= __('admin.labels_wp.closed_box_orders','Pedidos em Caixa Fechada') ?></strong>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm" id="mala-geracaoMassa" style="width:auto;min-width:140px;display:none;"><option value=""><?= __('admin.labels_wp.no_bag','Sem mala') ?></option></select>
                    <button class="btn btn-sm btn-warning" id="btnGerarMassa" style="display:none;" onclick="gerarEtiquetasMassa()"><i class="fas fa-bolt me-1"></i><span id="btnGerarMassaText"><?= __('admin.labels_wp.generate','Gerar') ?></span></button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="pedidos-resultado" class="p-3" style="display:none;"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th style="width:30px"><input type="checkbox" id="checkAllPedidos" onclick="toggleAllPedidos()"></th><th><?= __('admin.labels_wp.col_order','Pedido') ?></th><th class="d-none d-md-table-cell"><?= __('admin.labels_wp.col_customer','Cliente') ?></th><th><?= __('admin.labels_wp.col_weight','Peso') ?></th><th class="d-none d-md-table-cell"><?= __('admin.labels_wp.col_dimensions','Medidas') ?></th></tr></thead>
                        <tbody id="pedidos-body">
<?php
$pedidosCF=[];
try{$conn=\Config\Database::getConnection();$st=$conn->prepare("SELECT p.id,p.created_at,p.peso_total,p.altura,p.largura,p.comprimento,u.nome as cliente_nome FROM pedidos p LEFT JOIN usuarios u ON u.id=p.usuario_id LEFT JOIN correios_packet_etiquetas cpe ON cpe.pedido_id=p.id WHERE LOWER(COALESCE(p.status,'')) IN ('produto_consolidado','consolidado') AND cpe.id IS NULL ORDER BY p.created_at ASC LIMIT 200");$st->execute();$pedidosCF=$st->fetchAll(\PDO::FETCH_ASSOC)?:[];}catch(\Exception $e){}
if(empty($pedidosCF)):?><tr><td colspan="5" class="ewp-empty"><i class="fas fa-check-circle"></i><?= __('admin.labels_wp.no_orders_waiting','Nenhum pedido aguardando') ?></td></tr>
<?php else:foreach($pedidosCF as $ped):?>
<tr><td><input type="checkbox" class="chk-pedido" value="<?=(int)$ped['id']?>"></td><td><strong>#<?=str_pad((string)$ped['id'],6,'0',STR_PAD_LEFT)?></strong></td><td class="d-none d-md-table-cell"><?=htmlspecialchars((string)($ped['cliente_nome']??'-'))?></td><td><?=!empty($ped['peso_total'])?number_format((float)$ped['peso_total'],2,',','.').'kg':'<span class="text-danger">—</span>'?></td><td class="d-none d-md-table-cell"><?=(!empty($ped['comprimento'])&&!empty($ped['largura'])&&!empty($ped['altura']))?$ped['comprimento'].'×'.$ped['largura'].'×'.$ped['altura'].'cm':'<span class="text-danger">—</span>'?></td></tr>
<?php endforeach;endif;?>
                        </tbody></table></div></div></div>
        <div class="card ewp-card">
            <div class="card-header d-flex justify-content-between align-items-center py-2 flex-wrap gap-2">
                <strong class="small"><?= __('admin.labels_wp.packages_no_container','Pacotes gerados (sem container)') ?></strong>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select class="form-select form-select-sm" id="mala-mover-select" style="width:auto;min-width:160px;display:none;"><option value=""><?= __('admin.labels_wp.select_bag','Selecione a mala...') ?></option></select>
                    <button class="btn btn-sm btn-primary" id="btnMoverParaMala" style="display:none;" onclick="moverParaMala()"><i class="fas fa-suitcase me-1"></i><span id="btnMoverParaMalaText"><?= __('admin.labels_wp.move_to_bag','Mover para mala') ?></span></button>
                    <button class="btn btn-sm btn-outline-danger" id="btnBaixarMassa" style="display:none;" onclick="baixarEtiquetasMassa()"><i class="fas fa-download me-1"></i><span id="btnBaixarMassaText"><?= __('admin.labels_wp.download_labels','Baixar Etiquetas') ?></span></button>
                </div>
            </div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th style="width:30px"><input type="checkbox" id="checkAllPacotes" onclick="toggleAllPacotes()"></th><th><?= __('admin.labels_wp.col_order','Pedido') ?></th><th><?= __('admin.labels_wp.col_customer','Cliente') ?></th><th><?= __('admin.labels_wp.col_tracking','Tracking') ?></th><th class="d-none d-md-table-cell"><?= __('admin.labels_wp.col_weight','Peso') ?></th><th><?= __('admin.labels_wp.col_pdf','PDF') ?></th><th><?= __('admin.labels_wp.col_actions','Ações') ?></th></tr></thead>
                <tbody id="pacotes-body"><tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr></tbody>
            </table></div>
            <div id="pacotes-pagination" class="d-flex align-items-center justify-content-center py-2" style="display:none;"></div>
            </div></div>
        <!-- Malas -->
        <div class="card ewp-card" id="malas-section">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong class="small"><i class="fas fa-suitcase me-1"></i><?= __('admin.labels_wp.bags','Malas') ?></strong>
            </div>
            <div class="card-body p-0">
                <div id="malas-lista" class="p-3"><span class="text-muted small"><?= __('admin.labels_wp.no_bags','Nenhuma mala cadastrada.') ?></span></div>
            </div>
        </div>
    </div>

    <!-- CONTAINERS -->
    <div class="ewp-panel" id="panel-containers" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2"><strong class="small"><?= __('admin.labels_wp.create_container','Criar Container') ?></strong></div>
            <div class="card-body">
                <form id="form-container" onsubmit="criarContainer(event)">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.dispatch_number','Nº Remessa*') ?></label><input type="number" class="form-control form-control-sm" id="cnt-dispatch" required min="1"></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.origin_country','País de Origem*') ?></label><input type="text" class="form-control form-control-sm" id="cnt-origin-country" value="US" maxlength="2" readonly></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.origin_operator','Operador Origem*') ?></label><input type="text" class="form-control form-control-sm" id="cnt-origin-operator" value="BRAZ" maxlength="10" readonly></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.dest_operator','Operador Destino*') ?></label><select class="form-select form-select-sm" id="cnt-dest-operator" required><option value="SAOD" selected>SAOD - Guarulhos</option><option value="CWBA">CWBA - Curitiba</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.postal_category','Categoria Postal*') ?></label><select class="form-select form-select-sm" id="cnt-postal-category" required><option value="A" selected><?= __('admin.labels_wp.postal_cat_a','A – Airmail ou Priority Mail') ?></option><option value="B"><?= __('admin.labels_wp.postal_cat_b','B – S.A.L Mail') ?></option><option value="C"><?= __('admin.labels_wp.postal_cat_c','C – Surface Mail') ?></option><option value="D"><?= __('admin.labels_wp.postal_cat_d','D – Priority terrestre') ?></option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.subclass','Subclasse*') ?></label><select class="form-select form-select-sm" id="cnt-subclass" required><option value="NX" selected><?= __('admin.labels_wp.subclass_nx','NX – Serviço padrão') ?></option><option value="IX"><?= __('admin.labels_wp.subclass_ix','IX – Serviço expresso') ?></option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.unit_type','Tipo Unidade*') ?></label><select class="form-select form-select-sm" id="cnt-unit-type" required><option value="2" selected><?= __('admin.labels_wp.unit_type_2','2 - Caixa pallet até 500kg') ?></option><option value="1"><?= __('admin.labels_wp.unit_type_1','1 - Saco até 30kg') ?></option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.awb','N° AWB*') ?></label><input type="text" class="form-control form-control-sm" id="cnt-awb" placeholder="" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.triage_group','Grupo Triagem') ?></label><select class="form-select form-select-sm" id="cnt-triage" required><option value="1">1 - São Paulo/SP</option><option value="2">2 - Valinhos/SP</option><option value="3">3 - Rio de Janeiro/RJ</option><option value="4">4 - Curitiba/PR</option><option value="5" selected>5 - Curitiba/PR</option></select></div>
                    </div>
                    <label class="form-label small fw-bold"><?= __('admin.labels_wp.packages','Pacotes:') ?></label>
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="cnt-selecionar-mala" onchange="selecionarPacotesPorMala()">
                                <option value=""><?= __('admin.labels_wp.select_by_bag','Selecionar por Mala...') ?></option>
                            </select>
                        </div>
                        <div class="col-md-5"><textarea class="form-control form-control-sm" id="cnt-paste-trackings" rows="2" placeholder="<?= htmlspecialchars(__('admin.labels_wp.paste_trackings','Cole tracking codes (um por linha)'), ENT_QUOTES, 'UTF-8') ?>"></textarea></div>
                        <div class="col-md-4 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-primary" onclick="validarESelecionar()"><i class="fas fa-check-double me-1"></i><?= __('admin.labels_wp.validate_select','Validar e Selecionar') ?></button></div>
                    </div>
                    <div id="cnt-validacao" class="mb-2" style="display:none;"></div>
                    <div id="cnt-pacotes-lista" class="border rounded p-2 mb-2" style="max-height:200px;overflow-y:auto;"><span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i></span></div>
                    <div id="cnt-selected-count" class="small text-muted mb-2"></div>
                    <div id="container-resultado" class="mb-2" style="display:none;"></div>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-criar-container"><i class="fas fa-box me-1"></i><?= __('admin.labels_wp.create_container','Criar Container') ?></button>
                </form>
            </div>
        </div>
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small"><?= __('admin.labels_wp.containers','Containers') ?></strong></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle" id="containers-table">
                <thead class="table-light"><tr><th style="width:24px"></th><th><?= __('admin.labels_wp.col_dispatch','Remessa') ?></th><th><?= __('admin.labels_wp.col_unit_code','Unit Code') ?></th><th><?= __('admin.labels_wp.col_packages','Pacotes') ?></th><th><?= __('admin.labels_wp.col_status','Status') ?></th><th class="d-none d-md-table-cell"><?= __('admin.labels_wp.col_date','Data') ?></th><th><?= __('admin.labels_wp.col_pdf','PDF') ?></th><th><?= __('admin.labels_wp.col_actions','Ações') ?></th></tr></thead>
                <tbody id="containers-body"><tr><td colspan="8" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr></tbody>
            </table></div></div>
        </div>
    </div>

    <!-- FATURAS -->
    <div class="ewp-panel" id="panel-faturas" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2"><strong class="small"><?= __('admin.labels_wp.create_invoice','Criar Fatura (CN38)') ?></strong></div>
            <div class="card-body">
                <label class="form-label small"><?= __('admin.labels_wp.select_containers_invoice','Selecione containers para faturar:') ?></label>
                <div id="fatura-containers-lista" class="border rounded p-2 mb-2" style="max-height:200px;overflow-y:auto;"><span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i></span></div>
                <div id="fatura-resultado" class="mb-2" style="display:none;"></div>
                <button class="btn btn-warning btn-sm" onclick="criarFatura()" id="btn-criar-fatura"><i class="fas fa-file-invoice me-1"></i><?= __('admin.labels_wp.create_invoice_btn','Criar Fatura') ?></button>
            </div>
        </div>
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small"><?= __('admin.labels_wp.invoices','Faturas') ?></strong></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th><?= __('admin.labels_wp.col_cn38','CN38') ?></th><th><?= __('admin.labels_wp.col_dispatches','Remessas') ?></th><th><?= __('admin.labels_wp.col_status','Status') ?></th><th class="d-none d-md-table-cell"><?= __('admin.labels_wp.col_date','Data') ?></th><th><?= __('admin.labels_wp.col_pdf','PDF') ?></th><th><?= __('admin.labels_wp.col_actions','Ações') ?></th></tr></thead>
                <tbody id="faturas-body"><tr><td colspan="6" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr></tbody>
            </table></div></div>
        </div>
    </div>

    <!-- EMBARQUES -->
    <div class="ewp-panel" id="panel-embarques" style="display:none;">
        <div class="card ewp-card mb-3">
            <div class="card-header py-2"><strong class="small"><?= __('admin.labels_wp.confirm_shipment','Confirmar Embarque') ?></strong></div>
            <div class="card-body">
                <form id="form-embarque" onsubmit="criarEmbarque(event)">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.flight_number','Nº Voo*') ?></label><input type="number" class="form-control form-control-sm" id="emb-flight" required max="999999"></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.airline','Cia Aérea*') ?></label><select class="form-select form-select-sm" id="emb-airline" required><option value="M3" selected>LATAM CARGO BRASIL (M3)</option><option value="AA">American Airlines (AA)</option><option value="AD">Azul (AD)</option><option value="AF">Air France (AF)</option><option value="BA">British Airways (BA)</option><option value="CV">Cargolux (CV)</option><option value="DL">Delta (DL)</option><option value="EK">Emirates (EK)</option><option value="FX">FedEx (FX)</option><option value="G3">Gol (G3)</option><option value="KL">KLM (KL)</option><option value="LA">LATAM (LA)</option><option value="LH">Lufthansa (LH)</option><option value="M6">Amerijet (M6)</option><option value="QR">Qatar (QR)</option><option value="QT">Tampa Cargo (QT)</option><option value="TK">Turkish (TK)</option><option value="TP">TAP (TP)</option><option value="UA">United (UA)</option><option value="UC">Latam Cargo (UC)</option><option value="5X">UPS (5X)</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.departure','Partida*') ?></label><input type="datetime-local" class="form-control form-control-sm" id="emb-departure-date" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.departure_airport','Aeroporto Partida*') ?></label><input type="text" class="form-control form-control-sm" id="emb-departure-airport" value="MIA" required maxlength="3"></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.arrival','Chegada*') ?></label><input type="datetime-local" class="form-control form-control-sm" id="emb-arrival-date" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small"><?= __('admin.labels_wp.arrival_airport','Aeroporto Chegada*') ?></label><input type="text" class="form-control form-control-sm" id="emb-arrival-airport" value="GRU" required maxlength="3"></div>
                    </div>
                    <label class="form-label small"><?= __('admin.labels_wp.invoices_to_ship','Faturas para embarcar:') ?></label>
                    <div id="emb-faturas-lista" class="border rounded p-2 mb-2" style="max-height:150px;overflow-y:auto;"><span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i></span></div>
                    <div id="embarque-resultado" class="mb-2" style="display:none;"></div>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-criar-embarque"><i class="fas fa-plane me-1"></i><?= __('admin.labels_wp.confirm_shipment','Confirmar Embarque') ?></button>
                </form>
            </div>
        </div>
        <div class="card ewp-card">
            <div class="card-header py-2"><strong class="small"><?= __('admin.labels_wp.shipments','Embarques') ?></strong></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th><?= __('admin.labels_wp.col_flight','Voo') ?></th><th><?= __('admin.labels_wp.col_airline','Cia') ?></th><th><?= __('admin.labels_wp.col_departure','Partida') ?></th><th class="d-none d-md-table-cell"><?= __('admin.labels_wp.col_arrival','Chegada') ?></th><th><?= __('admin.labels_wp.col_cn38','CN38') ?></th><th><?= __('admin.labels_wp.col_status','Status') ?></th><th><?= __('admin.labels_wp.col_actions','Ações') ?></th></tr></thead>
                <tbody id="embarques-body"><tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr></tbody>
            </table></div></div>
        </div>
    </div>

    <!-- DOCUMENTAÇÃO -->
    <div class="ewp-panel" id="panel-documentacao" style="display:none;">
        <div class="card ewp-card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong class="small"><?= __('admin.labels_wp.documentation_by_invoice','Documentação por Fatura') ?></strong>
                <button class="btn btn-sm btn-outline-primary" onclick="carregarDocumentacaoTab()"><i class="fas fa-sync me-1"></i><?= __('admin.labels_wp.refresh','Atualizar') ?></button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3"><?= __('admin.labels_wp.documentation_help','Clique em uma fatura para expandir e ver os documentos. Use "Baixar" para baixar todos os PDFs de uma vez.') ?></p>
                <div id="doc-tab-loading" class="text-center py-3" style="display:none;"><i class="fas fa-spinner fa-spin me-1"></i> <?= __('admin.labels_wp.loading','Carregando...') ?></div>
                <div id="doc-tab-lista"></div>
                <div id="doc-tab-pagination"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Mala -->
<div class="modal fade" id="modalNovaMala" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#1e293b 0%,#334155 100%);border-radius:12px 12px 0 0;padding:16px 20px;">
                <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-suitcase me-2"></i><?= __('admin.labels_wp.new_bag','Nova Mala') ?></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= __('admin.labels_wp.bag_name','Nome da Mala *') ?></label>
                    <input type="text" class="form-control" id="modal-mala-nome" placeholder="<?= htmlspecialchars(__('admin.labels_wp.bag_name_placeholder','Ex: Mala 1, Lote SP, Envio 15/08...'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold"><?= __('admin.labels_wp.description','Descrição') ?></label>
                    <input type="text" class="form-control" id="modal-mala-descricao" placeholder="<?= htmlspecialchars(__('admin.labels_wp.description_placeholder','Opcional - descrição ou observação'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('admin.labels_wp.cancel','Cancelar') ?></button>
                <button type="button" class="btn btn-success" onclick="salvarMalaModal()"><i class="fas fa-check me-1"></i><?= __('admin.labels_wp.create_bag','Criar Mala') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE='/admin/etiquetas-wp';

// Filtro de busca client-side
let filtroDebounce = null;
function filtrarEtiquetas(termo) {
    termo = termo.trim();
    // Filtrar tabela Caixa Fechada (sempre client-side)
    const termoLower = termo.toLowerCase();
    document.querySelectorAll('#pedidos-body tr').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        tr.style.display = (!termoLower || text.includes(termoLower)) ? '' : 'none';
    });

    // Para pacotes: se tem 3+ chars, buscar direto no WP com debounce
    clearTimeout(filtroDebounce);
    if (termo.length >= 3) {
        document.getElementById('pacotes-pagination').style.display = 'none';
        filtroDebounce = setTimeout(() => buscarNoWordPress(termo), 400);
    } else if (termo.length === 0) {
        // Sem filtro: voltar pra listagem paginada normal
        carregarPacotes(1);
    } else {
        // 1-2 chars: filtrar client-side na página atual
        document.querySelectorAll('#pacotes-body tr').forEach(tr => {
            const text = tr.textContent.toLowerCase();
            tr.style.display = (!termoLower || text.includes(termoLower)) ? '' : 'none';
        });
    }
}

// Busca direta no WordPress
async function buscarNoWordPress(termo) {
    const tbody = document.getElementById('pacotes-body');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i> ' + <?= json_encode(__('admin.labels_wp.searching','Buscando...')) ?> + '</td></tr>';
    try {
        const r = await fetch(BASE + '/listar-pacotes?search=' + encodeURIComponent(termo) + '&per_page=50');
        const d = await r.json();
        tbody.innerHTML = '';
        if (d.success && d.data && d.data.length > 0) {
            d.data.forEach(p => renderPacoteRow(tbody, p));
            document.getElementById('pacotes-pagination').innerHTML = '<small class="text-muted"><i class="fas fa-cloud me-1"></i>' + <?= json_encode(__('admin.labels_wp.results_for','{n} resultado(s) para "{q}"')) ?>.replace('{n}', d.data.length).replace('{q}', termo) + '</small>';
            document.getElementById('pacotes-pagination').style.display = 'flex';
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="ewp-empty"><i class="fas fa-search"></i> ' + <?= json_encode(__('admin.labels_wp.no_packages_for','Nenhum pacote encontrado para "{q}"')) ?>.replace('{q}', termo) + '</td></tr>';
            document.getElementById('pacotes-pagination').style.display = 'none';
        }
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-danger">' + e.message + '</td></tr>';
    }
}

// Render de uma linha de pacote (7 colunas: checkbox download em massa + badge de mala)
// tbody opcional: se informado, faz append; sempre retorna a string HTML da linha.
function renderPacoteRow(tbody, p) {
    // Compatibilidade: permite chamada renderPacoteRow(p) sem tbody
    if (tbody && p === undefined) { p = tbody; tbody = null; }
    let pedidoLabel = p.order_id || '-';
    let pedidoIdLocal = p.pedido_id_local || 0;
    if (p.pedido_id_local && !String(p.order_id || '').toUpperCase().startsWith('REDIR')) {
        pedidoLabel = '#' + String(p.pedido_id_local).padStart(6, '0');
    }
    const clienteNome = p.recipient_name || '-';
    const isCancelado = p.package_status === 'cancelado';
    const rowClass = isCancelado ? ' class="text-muted" style="opacity:0.5;text-decoration:line-through;"' : '';
    const badge = isCancelado ? ' <span class="badge bg-secondary" style="font-size:0.65rem;">' + <?= json_encode(__('admin.labels_wp.cancelled','Cancelado')) ?> + '</span>' : '';
    const malaBadge = (p.mala_nome && !isCancelado) ? ' <span class="badge bg-info text-dark" style="font-size:0.6rem;">' + escHtmlCnt(p.mala_nome) + '</span>' : '';
    const pdfUrl = (p.wp_post_id && !isCancelado) ? BASE + '/pdf/pacote/' + p.wp_post_id : '';
    const checkboxHtml = (!isCancelado && pdfUrl) ? '<input type="checkbox" class="form-check-input chk-pacote-dl" data-pdf-url="' + pdfUrl + '" data-pedido="' + pedidoLabel + '" data-tracking="' + (p.tracking_code || '') + '" data-pedido-id="' + (pedidoIdLocal || '') + '" data-mala-nome="' + escHtmlCnt(p.mala_nome || '') + '" onchange="updateBaixarMassa();updateMoverMala()">' : '';
    const row = '<tr' + rowClass + '><td>' + checkboxHtml + '</td><td>' + pedidoLabel + badge + malaBadge + '</td><td>' + clienteNome + '</td><td><code class="small">' + (p.tracking_code || '-') + '</code></td><td class="d-none d-md-table-cell">' + (p.total_weight ? (p.total_weight / 1000).toFixed(1) + 'kg' : '-') + '</td><td>' + (p.wp_post_id && !isCancelado ? '<a href="' + BASE + '/pdf/pacote/' + p.wp_post_id + '" target="_blank" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf"></i></a>' : '') + '</td><td>' + (pedidoIdLocal && !isCancelado ? '<button class="btn btn-xs btn-outline-warning" onclick="regerarEtiquetaWp(' + pedidoIdLocal + ')" title="' + <?= json_encode(__('admin.labels_wp.regenerate_label','Regerar etiqueta')) ?> + '"><i class="fas fa-redo"></i></button>' : '') + '</td></tr>';
    if (tbody) tbody.innerHTML += row;
    return row;
}

// Paginação
let pacotesPage = 1;
const pacotesPerPage = 50;
let pacotesTotal = 0;
let pacotesPages = 1;

async function carregarPacotes(page) {
    if (page) pacotesPage = page;
    const tbody = document.getElementById('pacotes-body');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i></td></tr>';
    try {
        const r = await fetch(BASE + '/listar-pacotes?without_container=1&per_page=' + pacotesPerPage + '&page=' + pacotesPage);
        const d = await r.json();
        tbody.innerHTML = '';
        if (d.success && d.data && d.data.length > 0) {
            pacotesTotal = d.total || d.data.length;
            pacotesPages = d.pages || Math.ceil(pacotesTotal / pacotesPerPage);
            // Agrupar os pacotes da página atual por mala (mantendo a paginação server-side)
            const byMala = {};
            const semMala = [];
            d.data.forEach(p => {
                if (p.mala_nome) {
                    if (!byMala[p.mala_nome]) byMala[p.mala_nome] = { pacotes: [], pesoTotal: 0 };
                    byMala[p.mala_nome].pacotes.push(p);
                    const tw = parseInt(p.total_weight) || 0;
                    if (tw > 0 && tw < 100000) byMala[p.mala_nome].pesoTotal += tw;
                } else {
                    semMala.push(p);
                }
            });
            let html = '';
            Object.keys(byMala).forEach(function(malaNome) {
                const grupo = byMala[malaNome];
                const pesoKg = (grupo.pesoTotal / 1000).toFixed(1);
                html += '<tr><td colspan="7" style="background:#eef2ff;padding:6px 12px;border-left:3px solid #3b82f6;"><strong class="small"><i class="fas fa-suitcase me-1 text-primary"></i>' + escHtmlCnt(malaNome) + '</strong> <span class="badge bg-primary ms-1">' + grupo.pacotes.length + ' pct</span> <span class="text-muted small ms-2">' + pesoKg + 'kg</span></td></tr>';
                grupo.pacotes.forEach(function(p) { html += renderPacoteRow(p); });
            });
            if (semMala.length > 0) {
                if (Object.keys(byMala).length > 0) {
                    html += '<tr><td colspan="7" style="background:#f8fafc;padding:6px 12px;border-left:3px solid #94a3b8;"><strong class="small text-muted"><i class="fas fa-inbox me-1"></i>' + <?= json_encode(__('admin.labels_wp.no_bag','Sem mala')) ?> + '</strong> <span class="badge bg-secondary ms-1">' + semMala.length + '</span></td></tr>';
                }
                semMala.forEach(function(p) { html += renderPacoteRow(p); });
            }
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="ewp-empty"><i class="fas fa-inbox"></i>' + <?= json_encode(__('admin.labels_wp.no_packages_without_container','Nenhum pacote sem container')) ?> + '</td></tr>';
            pacotesTotal = 0;
            pacotesPages = 1;
        }
        renderPacotesPagination();
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-danger">' + e.message + '</td></tr>';
    }
}

function renderPacotesPagination() {
    const el = document.getElementById('pacotes-pagination');
    if (pacotesPages <= 1) { el.style.display = 'none'; return; }
    el.style.display = 'flex';
    let h = '<small class="text-muted me-2">' + <?= json_encode(__('admin.labels_wp.packages_count','{n} pacote(s)')) ?>.replace('{n}', pacotesTotal) + '</small>';
    if (pacotesPage > 1) h += '<button class="btn btn-xs btn-outline-secondary me-1" onclick="carregarPacotes(' + (pacotesPage - 1) + ')"><i class="fas fa-chevron-left"></i></button>';
    h += '<span class="small mx-1">' + pacotesPage + ' / ' + pacotesPages + '</span>';
    if (pacotesPage < pacotesPages) h += '<button class="btn btn-xs btn-outline-secondary ms-1" onclick="carregarPacotes(' + (pacotesPage + 1) + ')"><i class="fas fa-chevron-right"></i></button>';
    el.innerHTML = h;
}

function switchTab(t){document.querySelectorAll('.ewp-panel').forEach(p=>p.style.display='none');document.querySelectorAll('.ewp-tab-btn').forEach(b=>b.classList.remove('active'));document.getElementById('panel-'+t).style.display='block';event.target.classList.add('active');if(t==='containers'){carregarMalasParaContainer();carregarPacotesParaContainer();carregarContainers();}if(t==='faturas'){carregarContainersParaFatura();carregarFaturas();}if(t==='embarques'){carregarFaturasParaEmbarque();carregarEmbarques();}if(t==='documentacao'){carregarDocumentacaoTab();}}
document.addEventListener('DOMContentLoaded',()=>{checkConnection();carregarPacotes();carregarMalasSelect();carregarMalasMoverSelect();carregarMalas();});
document.addEventListener('change',e=>{if(e.target.classList.contains('chk-pedido'))updateMassBtn();if(e.target.classList.contains('chk-cnt-pacote'))updateCntCount();});

// CONNECTION
async function checkConnection(){try{const r=await fetch(BASE+'/testar-conexao');const d=await r.json();const el=document.getElementById('ewp-conn-status');const txt=document.getElementById('ewp-conn-text');const amb=document.getElementById('ewp-ambiente');const saldo=document.getElementById('ewp-saldo');if(d.success){el.className='ewp-status ok';txt.textContent=<?= json_encode(__('admin.labels_wp.connected','Conectado')) ?>;if(d.ambiente){amb.style.display='inline-block';amb.textContent=d.ambiente==='HOMOLOGACAO'?('⚠️ '+<?= json_encode(__('admin.labels_wp.env_staging','HOMOLOGAÇÃO')) ?>):('🟢 '+<?= json_encode(__('admin.labels_wp.env_production','PRODUÇÃO')) ?>);amb.className='ewp-badge '+(d.ambiente==='HOMOLOGACAO'?'bg-warning text-dark':'bg-success text-white');}}else{el.className='ewp-status err';txt.textContent=<?= json_encode(__('admin.labels_wp.error','Erro')) ?>;}}catch(e){document.getElementById('ewp-conn-status').className='ewp-status err';document.getElementById('ewp-conn-text').textContent=<?= json_encode(__('admin.labels_wp.offline','Offline')) ?>;}carregarSaldoFinanceiro();}
async function carregarSaldoFinanceiro(){const saldo=document.getElementById('ewp-saldo');try{const r=await fetch(BASE+'/saldo');const d=await r.json();if(d.success&&d.currentBalance!==null&&d.currentBalance!==undefined){const val=parseFloat(d.currentBalance);const fmt=val.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});saldo.innerHTML='| '+<?= json_encode(__('admin.labels_wp.balance','Saldo:')) ?>+' <strong>'+fmt+'</strong>';saldo.style.display='inline';}}catch(e){}}
async function testarConexaoDetalhado(){const p=document.getElementById('ewp-diagnostico');const b=document.getElementById('ewp-diagnostico-body');p.style.display='block';b.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>'+<?= json_encode(__('admin.labels_wp.testing','Testando...')) ?>;try{const r=await fetch(BASE+'/testar-conexao');const d=await r.json();let h='<div class="alert py-2 small '+(d.success?'alert-success':'alert-danger')+'">'+(d.success?('✅ '+<?= json_encode(__('admin.labels_wp.all_ok','Todos OK')) ?>):('❌ '+<?= json_encode(__('admin.labels_wp.failures','Falhas')) ?>))+'</div><table class="table table-sm small mb-0"><thead><tr><th>'+<?= json_encode(__('admin.labels_wp.col_endpoint','Endpoint')) ?>+'</th><th>'+<?= json_encode(__('admin.labels_wp.col_status','Status')) ?>+'</th><th>'+<?= json_encode(__('admin.labels_wp.col_time','Tempo')) ?>+'</th><th>'+<?= json_encode(__('admin.labels_wp.col_details','Detalhes')) ?>+'</th></tr></thead><tbody>';const labels={balance:<?= json_encode(__('admin.labels_wp.balance_short','Saldo')) ?>,list_packages:<?= json_encode(__('admin.labels_wp.packages_short','Pacotes')) ?>,list_containers:<?= json_encode(__('admin.labels_wp.containers','Containers')) ?>,list_bills:<?= json_encode(__('admin.labels_wp.invoices','Faturas')) ?>,list_departures:<?= json_encode(__('admin.labels_wp.shipments','Embarques')) ?>};for(const[k,v]of Object.entries(d.results||{})){const badge=v.success?'<span class="badge bg-success">'+<?= json_encode(__('admin.labels_wp.ok','OK')) ?>+'</span>':'<span class="badge bg-danger">'+<?= json_encode(__('admin.labels_wp.fail','FALHA')) ?>+'</span>';let det='';if(v.total!==undefined)det=<?= json_encode(__('admin.labels_wp.total','Total: {n}')) ?>.replace('{n}', v.total);if(!v.success&&v.data&&v.data.error)det=v.data.error;h+='<tr><td>'+(labels[k]||k)+'</td><td>'+badge+'</td><td>'+(v.time_ms||'-')+'ms</td><td>'+det+'</td></tr>';}h+='</tbody></table>';b.innerHTML=h;}catch(e){b.innerHTML='<div class="alert alert-danger py-2 small">'+e.message+'</div>';}}

// PEDIDOS
function toggleAllPedidos(){const c=document.getElementById('checkAllPedidos').checked;document.querySelectorAll('.chk-pedido').forEach(el=>el.checked=c);updateMassBtn();}
function updateMassBtn(){const n=document.querySelectorAll('.chk-pedido:checked').length;const btn=document.getElementById('btnGerarMassa');const malaSelect=document.getElementById('mala-geracaoMassa');if(n>0){btn.style.display='';if(malaSelect)malaSelect.style.display='';document.getElementById('btnGerarMassaText').textContent=<?= json_encode(__('admin.labels_wp.generate_n_labels','Gerar {n} etiqueta(s)')) ?>.replace('{n}', n);}else{btn.style.display='none';if(malaSelect)malaSelect.style.display='none';}}
async function gerarEtiquetasMassa(){const ids=[...document.querySelectorAll('.chk-pedido:checked')].map(e=>parseInt(e.value));if(!ids.length)return;const malaId=document.getElementById('mala-geracaoMassa').value;const malaMsg=malaId?(' '+<?= json_encode(__('admin.labels_wp.assign_selected_bag','(atribuir à mala selecionada)')) ?>):'';if(!confirm(<?= json_encode(__('admin.labels_wp.confirm_generate_wp','Gerar {n} etiqueta(s) via WordPress?')) ?>.replace('{n}', ids.length)+malaMsg))return;const btn=document.getElementById('btnGerarMassa');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>'+<?= json_encode(__('admin.labels_wp.generating','Gerando...')) ?>;try{const r=await fetch(BASE+'/gerar-etiquetas-massa',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids})});const d=await r.json();const el=document.getElementById('pedidos-resultado');el.style.display='block';if(d.success){let h='<div class="alert alert-'+(d.failed>0?'warning':'success')+' py-2 small"><strong>'+<?= json_encode(__('admin.labels_wp.n_generated','{n} gerada(s)')) ?>.replace('{n}', d.generated)+'</strong>'+(d.failed>0?(', '+<?= json_encode(__('admin.labels_wp.n_failures','{n} falha(s)')) ?>.replace('{n}', d.failed)):'');if(d.results)d.results.forEach(r=>{if(r.tracking_number)h+='<br><code>'+r.tracking_number+'</code>';if(r.error)h+='<br><span class="text-danger">#'+r.pedido_id+': '+r.error+'</span>';});h+='</div>';el.innerHTML=h;if(d.generated>0&&malaId){const trackings=d.results.filter(r=>r.success&&r.tracking_number).map(r=>r.tracking_number);const pedidoIds=d.results.filter(r=>r.success).map(r=>r.pedido_id);if(trackings.length>0){try{await fetch(BASE+'/atribuir-mala',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mala_id:parseInt(malaId),tracking_codes:trackings,pedido_ids:pedidoIds})});}catch(e){}}}if(d.generated>0)setTimeout(()=>location.reload(),2000);}else{el.innerHTML='<div class="alert alert-danger py-2 small">'+(d.error||<?= json_encode(__('admin.labels_wp.error','Erro')) ?>)+'</div>';}}catch(e){alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+e.message);}btn.disabled=false;updateMassBtn();}

// TOGGLE ALL PACOTES (download em massa)
function toggleAllPacotes(){const checked=document.getElementById('checkAllPacotes').checked;document.querySelectorAll('.chk-pacote-dl').forEach(cb=>{cb.checked=checked;});updateBaixarMassa();updateMoverMala();}
function updateBaixarMassa(){const checked=document.querySelectorAll('.chk-pacote-dl:checked').length;const btn=document.getElementById('btnBaixarMassa');const txt=document.getElementById('btnBaixarMassaText');if(btn){btn.style.display=checked>0?'':'none';}if(txt){txt.textContent=checked>1?(<?= json_encode(__('admin.labels_wp.download_n_labels','Baixar {n} Etiquetas')) ?>.replace('{n}', checked)):<?= json_encode(__('admin.labels_wp.download_label','Baixar Etiqueta')) ?>;}}
async function baixarEtiquetasMassa(){const checks=[...document.querySelectorAll('.chk-pacote-dl:checked')];if(!checks.length){alert(<?= json_encode(__('admin.labels_wp.select_at_least_one_label','Selecione pelo menos 1 etiqueta.')) ?>);return;}const btn=document.getElementById('btnBaixarMassa');if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>'+<?= json_encode(__('admin.labels_wp.downloading','Baixando...')) ?>;}let downloaded=0;for(const cb of checks){const url=cb.getAttribute('data-pdf-url');const pedido=cb.getAttribute('data-pedido')||'etiqueta';if(!url)continue;try{const resp=await fetch(url);if(!resp.ok)continue;const blob=await resp.blob();const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='etiqueta_'+pedido.replace('#','')+'.pdf';document.body.appendChild(link);link.click();document.body.removeChild(link);URL.revokeObjectURL(link.href);downloaded++;await new Promise(r=>setTimeout(r,300));}catch(e){console.error('Erro baixando '+url,e);}}if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-download me-1"></i><span id="btnBaixarMassaText">'+<?= json_encode(__('admin.labels_wp.download_labels','Baixar Etiquetas')) ?>+'</span>';updateBaixarMassa();}if(downloaded>0)alert(<?= json_encode(__('admin.labels_wp.n_labels_downloaded','{n} etiqueta(s) baixada(s) com sucesso!')) ?>.replace('{n}', downloaded));}

// MOVER ETIQUETAS PARA MALA
// Popula o select de malas do card "Pacotes gerados".
async function carregarMalasMoverSelect(){try{const r=await fetch(BASE+'/listar-malas');const d=await r.json();const sel=document.getElementById('mala-mover-select');if(!sel)return;const atual=sel.value;sel.innerHTML='<option value="">'+<?= json_encode(__('admin.labels_wp.select_bag','Selecione a mala...')) ?>+'</option>';if(d.success&&d.data){d.data.forEach(function(m){sel.innerHTML+='<option value="'+m.id+'">'+escHtmlCnt(m.nome)+' ('+m.pacotes_count+' pct)</option>';});}sel.value=atual;}catch(e){}}
// Mostra/esconde o select+botão conforme houver etiquetas selecionadas.
function updateMoverMala(){const n=document.querySelectorAll('.chk-pacote-dl:checked').length;const sel=document.getElementById('mala-mover-select');const btn=document.getElementById('btnMoverParaMala');const txt=document.getElementById('btnMoverParaMalaText');if(sel)sel.style.display=n>0?'':'none';if(btn)btn.style.display=n>0?'':'none';if(txt)txt.textContent=n>1?(<?= json_encode(__('admin.labels_wp.move_n_to_bag','Mover {n} para mala')) ?>.replace('{n}', n)):<?= json_encode(__('admin.labels_wp.move_to_bag','Mover para mala')) ?>;}
// Atribui as etiquetas selecionadas à mala escolhida.
async function moverParaMala(){const checks=[...document.querySelectorAll('.chk-pacote-dl:checked')];if(!checks.length){alert(<?= json_encode(__('admin.labels_wp.select_at_least_one_label','Selecione pelo menos 1 etiqueta.')) ?>);return;}const sel=document.getElementById('mala-mover-select');const malaId=sel?parseInt(sel.value):0;if(!malaId){alert(<?= json_encode(__('admin.labels_wp.select_destination_bag','Selecione a mala de destino.')) ?>);return;}const trackings=[];const pedidoIds=[];checks.forEach(function(cb){const tc=cb.getAttribute('data-tracking')||'';if(tc){trackings.push(tc);pedidoIds.push(parseInt(cb.getAttribute('data-pedido-id'))||0);}});if(!trackings.length){alert(<?= json_encode(__('admin.labels_wp.selected_no_valid_tracking','Etiquetas selecionadas sem tracking válido.')) ?>);return;}const btn=document.getElementById('btnMoverParaMala');if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>'+<?= json_encode(__('admin.labels_wp.moving','Movendo...')) ?>;}try{const r=await fetch(BASE+'/atribuir-mala',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mala_id:malaId,tracking_codes:trackings,pedido_ids:pedidoIds})});const d=await r.json();if(d.success){alert(<?= json_encode(__('admin.labels_wp.n_labels_moved','{n} etiqueta(s) movida(s) para a mala com sucesso!')) ?>.replace('{n}', (d.added||trackings.length)));carregarPacotes();carregarMalas();carregarMalasMoverSelect();carregarMalasSelect();}else{alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+(d.error||<?= json_encode(__('admin.labels_wp.move_failed','Falha ao mover')) ?>));}}catch(e){alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+e.message);}if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-suitcase me-1"></i><span id="btnMoverParaMalaText">'+<?= json_encode(__('admin.labels_wp.move_to_bag','Mover para mala')) ?>+'</span>';}updateMoverMala();}

// MALAS
function abrirModalNovaMala(){document.getElementById('modal-mala-nome').value='';document.getElementById('modal-mala-descricao').value='';var m=new bootstrap.Modal(document.getElementById('modalNovaMala'));m.show();}
async function carregarMalasSelect(){try{const r=await fetch(BASE+'/listar-malas');const d=await r.json();const sel=document.getElementById('mala-geracaoMassa');if(!sel)return;sel.innerHTML='<option value="">'+<?= json_encode(__('admin.labels_wp.no_bag','Sem mala')) ?>+'</option>';if(d.success&&d.data){d.data.forEach(function(m){sel.innerHTML+='<option value="'+m.id+'">'+escHtmlCnt(m.nome)+' ('+m.pacotes_count+' pct)</option>';});}}catch(e){}}
async function salvarMalaModal(){const nome=document.getElementById('modal-mala-nome').value.trim();const desc=document.getElementById('modal-mala-descricao').value.trim();if(!nome){alert(<?= json_encode(__('admin.labels_wp.name_required','Nome é obrigatório.')) ?>);return;}try{const r=await fetch(BASE+'/criar-mala',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({nome:nome,descricao:desc})});const d=await r.json();if(d.success){bootstrap.Modal.getInstance(document.getElementById('modalNovaMala')).hide();carregarMalas();carregarMalasSelect();carregarMalasMoverSelect();}else{alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+(d.error||<?= json_encode(__('admin.labels_wp.failure','Falha')) ?>));}}catch(e){alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+e.message);}}
function mostrarFormMala(){abrirModalNovaMala();}
async function salvarMala(){await salvarMalaModal();}
async function carregarMalas(){const el=document.getElementById('malas-lista');try{const r=await fetch(BASE+'/listar-malas');const d=await r.json();if(!d.success||!d.data||!d.data.length){el.innerHTML='<span class="text-muted small">'+<?= json_encode(__('admin.labels_wp.no_bags_create','Nenhuma mala cadastrada. Clique em "Nova Mala" para criar.')) ?>+'</span>';return;}let h='';d.data.forEach(function(m,idx){var pesoKg=parseFloat(m.peso_total_kg)||0;const pacotes=m.pacotes||[];h+='<div class="border rounded mb-2" style="overflow:hidden;">';h+='<div class="d-flex align-items-center justify-content-between px-3 py-2" style="cursor:pointer;background:#fff;" onclick="toggleMalaDetail('+idx+')">';h+='<div class="d-flex align-items-center gap-2"><i class="fas fa-chevron-right mala-chev-'+idx+'" style="font-size:.65rem;color:#64748b;transition:transform .2s;"></i><strong>'+escHtmlCnt(m.nome)+'</strong><span class="text-muted small">'+escHtmlCnt(m.descricao||'')+'</span><span class="badge bg-primary">'+m.pacotes_count+' pacote(s)</span></div>';h+='<div class="d-flex align-items-center gap-2"><button class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation();deletarMala('+m.id+',\''+escHtmlCnt(m.nome).replace(/'/g,"\\'")+'\')"><i class="fas fa-trash"></i></button></div>';h+='</div>';h+='<div id="mala-detail-'+idx+'" style="display:none;padding:10px 16px;background:#f8fafc;border-top:1px solid #e2e8f0;">';if(pacotes.length>0){h+='<table class="table table-sm table-bordered mb-2" style="font-size:.8rem;"><thead class="table-light"><tr><th>'+<?= json_encode(__('admin.labels_wp.col_tracking','Tracking')) ?>+'</th><th>'+<?= json_encode(__('admin.labels_wp.col_order','Pedido')) ?>+'</th><th>'+<?= json_encode(__('admin.labels_wp.col_weight','Peso')) ?>+'</th></tr></thead><tbody>';pacotes.forEach(function(p){const pesoP=p.peso_kg?parseFloat(p.peso_kg).toFixed(2)+'kg':'-';h+='<tr><td><code>'+escHtmlCnt(p.tracking_code||'-')+'</code></td><td>'+(p.pedido_id?'#'+String(p.pedido_id).padStart(6,'0'):'-')+'</td><td>'+pesoP+'</td></tr>';});h+='</tbody></table>';}else{h+='<span class="text-muted small">'+<?= json_encode(__('admin.labels_wp.no_packages_in_bag','Nenhum pacote nesta mala.')) ?>+'</span>';}h+='<div class="text-end small fw-bold text-primary">'+<?= json_encode(__('admin.labels_wp.total_weight','Peso total:')) ?>+' '+pesoKg.toFixed(2)+'kg</div>';h+='</div></div>';});el.innerHTML=h;}catch(e){el.innerHTML='<span class="text-danger small">'+e.message+'</span>';}}
function toggleMalaDetail(idx){var body=document.getElementById('mala-detail-'+idx);var chev=document.querySelector('.mala-chev-'+idx);if(!body)return;var isOpen=body.style.display!=='none';body.style.display=isOpen?'none':'';if(chev)chev.style.transform=isOpen?'rotate(0deg)':'rotate(90deg)';}
async function deletarMala(id,nome){if(!confirm(<?= json_encode(__('admin.labels_wp.confirm_delete_bag','Deletar mala "{n}"? Os pacotes serão desvinculados.')) ?>.replace('{n}', nome)))return;try{const r=await fetch(BASE+'/deletar-mala',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mala_id:id})});const d=await r.json();if(d.success){carregarMalas();carregarMalasParaContainer();carregarMalasSelect();carregarMalasMoverSelect();carregarPacotes();}else{alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+(d.error||<?= json_encode(__('admin.labels_wp.failure','Falha')) ?>));}}catch(e){alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+e.message);}}
async function carregarMalasParaContainer(){const sel=document.getElementById('cnt-selecionar-mala');if(!sel)return;try{const r=await fetch(BASE+'/listar-malas');const d=await r.json();sel.innerHTML='<option value="">'+<?= json_encode(__('admin.labels_wp.select_by_bag','Selecionar por Mala...')) ?>+'</option>';if(d.success&&d.data){d.data.forEach(function(m){const pesoKg=(parseFloat(m.peso_total_kg)||0).toFixed(1)+'kg';sel.innerHTML+='<option value="'+m.id+'" data-trackings="'+m.pacotes.map(function(p){return p.tracking_code;}).join(',')+'">'+escHtmlCnt(m.nome)+' ('+m.pacotes_count+' pct, '+pesoKg+')</option>';});}}catch(e){}}
function selecionarPacotesPorMala(){const sel=document.getElementById('cnt-selecionar-mala');if(!sel||!sel.value)return;const opt=sel.options[sel.selectedIndex];const trackings=(opt.getAttribute('data-trackings')||'').split(',').filter(function(t){return t.length>0;});if(!trackings.length){alert(<?= json_encode(__('admin.labels_wp.no_packages_in_that_bag','Nenhum pacote nessa mala.')) ?>);return;}const checkboxes=document.querySelectorAll('.chk-cnt-pacote');checkboxes.forEach(function(cb){cb.checked=false;});let found=0;trackings.forEach(function(tc){checkboxes.forEach(function(cb){if(cb.value.toUpperCase()===tc.toUpperCase()){cb.checked=true;found++;}});});updateCntCount();if(found===0){alert(<?= json_encode(__('admin.labels_wp.no_packages_available_bag','Nenhum pacote dessa mala está disponível (já em container ou cancelado).')) ?>);}}

// CONTAINERS
async function carregarPacotesParaContainer(){const el=document.getElementById('cnt-pacotes-lista');try{const r=await fetch(BASE+'/listar-pacotes?without_container=1&per_page=200');const d=await r.json();if(d.success&&d.data&&d.data.length>0){const available=d.data.filter(p=>p.package_status!=='cancelado');if(available.length>0){let h='<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+<?= json_encode(__('admin.labels_wp.packages_count','{n} pacote(s)')) ?>.replace('{n}', available.length)+'</small><a href="#" onclick="document.querySelectorAll(\'.chk-cnt-pacote\').forEach(e=>e.checked=true);updateCntCount();return false;" class="small">'+<?= json_encode(__('admin.labels_wp.all','Todos')) ?>+'</a></div>';available.forEach(p=>{h+='<div class="form-check"><input class="form-check-input chk-cnt-pacote" type="checkbox" value="'+p.tracking_code+'"><label class="form-check-label small"><code>'+p.tracking_code+'</code> — '+(p.order_id||'')+'</label></div>';});el.innerHTML=h;}else{el.innerHTML='<span class="small text-muted">'+<?= json_encode(__('admin.labels_wp.no_packages_available_all','Nenhum pacote disponível (todos cancelados ou já em container)')) ?>+'</span>';}}else{el.innerHTML='<span class="small text-muted">'+<?= json_encode(__('admin.labels_wp.no_packages_available','Nenhum pacote disponível')) ?>+'</span>';}}catch(e){el.innerHTML='<span class="text-danger small">'+e.message+'</span>';}autoPreencherRemessa();}
async function autoPreencherRemessa(){try{const r=await fetch(BASE+'/listar-containers?per_page=200');const d=await r.json();let max=0;if(d.success&&d.data)d.data.forEach(c=>{const dn=parseInt(c.dispatch_number)||0;if(dn>max)max=dn;});const input=document.getElementById('cnt-dispatch');if(!input.value)input.value=max+1;}catch(e){}}
function updateCntCount(){const n=document.querySelectorAll('.chk-cnt-pacote:checked').length;document.getElementById('cnt-selected-count').textContent=n>0?(<?= json_encode(__('admin.labels_wp.n_packages_selected','{n} pacote(s) selecionado(s)')) ?>.replace('{n}', n)):'';}
function validarESelecionar(){const raw=document.getElementById('cnt-paste-trackings').value.trim();if(!raw){alert(<?= json_encode(__('admin.labels_wp.paste_at_least_one','Cole pelo menos 1 tracking code.')) ?>);return;}const colados=raw.split(/[\n,;\s]+/).map(s=>s.trim().toUpperCase()).filter(s=>s.length>5);const checkboxes=document.querySelectorAll('.chk-cnt-pacote');const disp={};checkboxes.forEach(el=>{disp[el.value.toUpperCase()]=el;});let enc=[],nao=[];colados.forEach(c=>{if(disp[c]){disp[c].checked=true;enc.push(c);}else{nao.push(c);}});updateCntCount();const el=document.getElementById('cnt-validacao');el.style.display='block';let h='';if(enc.length)h+='<div class="text-success small"><i class="fas fa-check-circle me-1"></i>'+<?= json_encode(__('admin.labels_wp.n_selected','{n} selecionado(s)')) ?>.replace('{n}', enc.length)+'</div>';if(nao.length){h+='<div class="text-danger small mt-1"><i class="fas fa-times-circle me-1"></i>'+<?= json_encode(__('admin.labels_wp.n_not_found','{n} não encontrado(s):')) ?>.replace('{n}', nao.length);nao.forEach(c=>{h+='<br><code>'+c+'</code>';});h+='</div>';}el.innerHTML=h;}
async function criarContainer(event){event.preventDefault();const codes=[...document.querySelectorAll('.chk-cnt-pacote:checked')].map(e=>e.value);if(!codes.length){alert(<?= json_encode(__('admin.labels_wp.select_at_least_one_package','Selecione pelo menos 1 pacote.')) ?>);return;}const data={dispatchNumber:parseInt(document.getElementById('cnt-dispatch').value),trackingCodes:codes,originCountry:document.getElementById('cnt-origin-country').value,originOperatorName:document.getElementById('cnt-origin-operator').value,destinationOperatorName:document.getElementById('cnt-dest-operator').value,postalCategoryCode:document.getElementById('cnt-postal-category').value,serviceSubclassCode:document.getElementById('cnt-subclass').value,unitType:document.getElementById('cnt-unit-type').value,triageGroup:document.getElementById('cnt-triage').value,awb:document.getElementById('cnt-awb').value};const btn=document.getElementById('btn-criar-container');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>'+<?= json_encode(__('admin.labels_wp.creating','Criando...')) ?>;try{const r=await fetch(BASE+'/criar-container',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const d=await r.json();const el=document.getElementById('container-resultado');el.style.display='block';if(d.success){el.innerHTML='<div class="alert alert-success py-2 small"><strong>'+<?= json_encode(__('admin.labels_wp.container_created','Container criado!')) ?>+'</strong> '+<?= json_encode(__('admin.labels_wp.unit_code_label','Unit Code:')) ?>+' <code>'+d.unit_code+'</code></div>';carregarPacotesParaContainer();carregarContainers();}else{el.innerHTML='<div class="alert alert-danger py-2 small">'+d.error+'</div>';}}catch(e){alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+e.message);}btn.disabled=false;btn.innerHTML='<i class="fas fa-box me-1"></i>'+<?= json_encode(__('admin.labels_wp.create_container','Criar Container')) ?>;}
async function carregarContainers(){const tbody=document.getElementById('containers-body');try{const r=await fetch(BASE+'/listar-containers?per_page=50');const d=await r.json();tbody.innerHTML='';if(d.success&&d.data&&d.data.length>0){d.data.forEach((c,idx)=>{const tks=Array.isArray(c.tracking_codes)?c.tracking_codes:[];const tksCount=tks.length;const status=c.bill_id?('<span class="badge bg-success">'+<?= json_encode(__('admin.labels_wp.invoiced','Faturado')) ?>+'</span>'):('<span class="badge bg-warning text-dark">'+<?= json_encode(__('admin.labels_wp.awaiting_invoice','Aguardando fatura')) ?>+'</span>');const isFirst=idx===0;const rowId='cnt-row-'+idx;const detailId='cnt-detail-'+idx;tbody.innerHTML+='<tr class="cnt-row '+(isFirst?'table-info':'')+'" data-cnt-idx="'+idx+'" onclick="toggleContainerDetail('+idx+')"><td><span class="cnt-chevron"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></span></td><td><strong>'+c.dispatch_number+'</strong></td><td><code class="small">'+(c.unit_code||'-')+'</code></td><td><span class="badge bg-secondary">'+tksCount+'</span></td><td>'+status+'</td><td class="d-none d-md-table-cell">'+(c.created_at||'-')+'</td><td>'+(c.wp_post_id?'<a href="'+BASE+'/pdf/container/'+c.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation();"><i class="fas fa-file-pdf"></i></a>':'')+'</td><td><button class="btn btn-xs btn-outline-secondary" onclick="event.stopPropagation();deletarContainer('+c.wp_post_id+')" title="'+<?= json_encode(__('admin.labels_wp.delete','Deletar')) ?>+'"><i class="fas fa-trash"></i></button></td></tr>';tbody.innerHTML+='<tr class="cnt-detail-row" id="'+detailId+'"><td colspan="8"><div class="cnt-detail-wrap" id="cnt-detail-content-'+idx+'"><div class="text-center text-muted py-2"><i class="fas fa-spinner fa-spin me-1"></i>'+<?= json_encode(__('admin.labels_wp.loading','Carregando...')) ?>+'</div></div></td></tr>';});window._containersData=d.data;}else{tbody.innerHTML='<tr><td colspan="8" class="ewp-empty"><i class="fas fa-inbox"></i>'+<?= json_encode(__('admin.labels_wp.no_container','Nenhum container')) ?>+'</td></tr>';}}catch(e){tbody.innerHTML='<tr><td colspan="8" class="text-danger">'+e.message+'</td></tr>';}}
const _cntDetailsLoaded={};
function toggleContainerDetail(idx){const rows=document.querySelectorAll('.cnt-row');const detailRow=document.getElementById('cnt-detail-'+idx);const mainRow=document.querySelector('.cnt-row[data-cnt-idx="'+idx+'"]');if(!mainRow||!detailRow)return;const isOpen=mainRow.classList.contains('expanded');document.querySelectorAll('.cnt-row.expanded').forEach(r=>{r.classList.remove('expanded');const i=r.dataset.cntIdx;const dr=document.getElementById('cnt-detail-'+i);if(dr)dr.classList.remove('show');});if(!isOpen){mainRow.classList.add('expanded');detailRow.classList.add('show');if(!_cntDetailsLoaded[idx])loadContainerDetail(idx);}}
async function loadContainerDetail(idx){const c=window._containersData[idx];if(!c)return;const el=document.getElementById('cnt-detail-content-'+idx);const tks=Array.isArray(c.tracking_codes)?c.tracking_codes:[];let html='<div class="cnt-info-grid">';const fields=[[<?= json_encode(__('admin.labels_wp.origin_country_short','País Origem')) ?>,c.origin_country],[<?= json_encode(__('admin.labels_wp.origin_operator_short','Operador Origem')) ?>,c.origin_operator],[<?= json_encode(__('admin.labels_wp.dest_operator_short','Operador Destino')) ?>,c.destination_operator],[<?= json_encode(__('admin.labels_wp.postal_category_short','Categoria Postal')) ?>,c.postal_category],[<?= json_encode(__('admin.labels_wp.subclass_short','Subclasse')) ?>,c.service_subclass],[<?= json_encode(__('admin.labels_wp.unit_type_short','Tipo Unidade')) ?>,formatCntUnitType(c.unit_type)],[<?= json_encode(__('admin.labels_wp.awb_short','AWB')) ?>,c.awb],[<?= json_encode(__('admin.labels_wp.triage_group_short','Grupo Triagem')) ?>,c.triage_group],[<?= json_encode(__('admin.labels_wp.total_packages','Total Pacotes')) ?>,tks.length]];fields.forEach(f=>{const val=f[1];if(val!==null&&val!==undefined&&val!==''&&val!=='-'&&val!==0){html+=cntInfoItem(f[0],val);}});html+='</div>';if(tks.length>0){html+='<h6 class="mt-2 mb-1" style="font-size:.85rem"><i class="fas fa-box me-1"></i>'+<?= json_encode(__('admin.labels_wp.packages_paren','Pacotes ({n})')) ?>.replace('{n}', tks.length)+'</h6>';html+='<div class="table-responsive"><table class="table table-sm table-bordered cnt-pkg-table mb-0"><thead><tr><th>#</th><th>'+<?= json_encode(__('admin.labels_wp.tracking_code','Tracking Code')) ?>+'</th></tr></thead><tbody>';tks.forEach((tk,i)=>{html+='<tr><td>'+(i+1)+'</td><td><code>'+escHtmlCnt(tk)+'</code></td></tr>';});html+='</tbody></table></div>';}else{html+='<p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>'+<?= json_encode(__('admin.labels_wp.no_packages_linked','Nenhum pacote vinculado.')) ?>+'</p>';}el.innerHTML=html;_cntDetailsLoaded[idx]=true;}
function cntInfoItem(label,value){return '<div class="cnt-info-item"><label>'+escHtmlCnt(label)+'</label><span>'+escHtmlCnt(String(value))+'</span></div>';}
function formatCntUnitType(t){const m={'1':<?= json_encode(__('admin.labels_wp.unit_type_1','1 - Saco até 30kg')) ?>,'2':<?= json_encode(__('admin.labels_wp.unit_type_2','2 - Caixa pallet até 500kg')) ?>,'3':<?= json_encode(__('admin.labels_wp.unit_type_3','3 - Caixa pallet até 1000kg')) ?>};return m[t]||t||'-';}
function escHtmlCnt(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
async function deletarContainer(id){if(!confirm(<?= json_encode(__('admin.labels_wp.confirm_delete_container','Deletar container? Pacotes desvinculados, unitizador cancelado.')) ?>))return;try{const r=await fetch(BASE+'/deletar-container',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({wp_post_id:id})});const txt=await r.text();let d;try{d=JSON.parse(txt);}catch(pe){alert(<?= json_encode(__('admin.labels_wp.non_json_response','Resposta não-JSON do servidor (HTTP {s}):')) ?>.replace('{s}', r.status)+'\n\n'+txt.substring(0,500));return;}if(d.success){alert(<?= json_encode(__('admin.labels_wp.deleted','Deletado!')) ?>);carregarContainers();carregarPacotesParaContainer();}else{alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+(d.error||d.message||JSON.stringify(d)));}}catch(e){alert(<?= json_encode(__('admin.labels_wp.js_exception','Exceção JS:')) ?>+' '+e.message);}}

// FATURAS
async function carregarContainersParaFatura(){const el=document.getElementById('fatura-containers-lista');try{const r=await fetch(BASE+'/listar-containers?without_bill=1');const d=await r.json();if(d.success&&d.data&&d.data.length>0){let h='<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+<?= json_encode(__('admin.labels_wp.n_available','{n} disponível(is)')) ?>.replace('{n}', d.data.length)+'</small><a href="#" onclick="document.querySelectorAll(\'.chk-fatura-cnt\').forEach(e=>e.checked=true);return false;" class="small">'+<?= json_encode(__('admin.labels_wp.all','Todos')) ?>+'</a></div>';d.data.forEach(c=>{const tks=Array.isArray(c.tracking_codes)?c.tracking_codes.length:0;h+='<div class="form-check"><input class="form-check-input chk-fatura-cnt" type="checkbox" value="'+c.wp_post_id+'"><label class="form-check-label small">'+<?= json_encode(__('admin.labels_wp.dispatch_word','Remessa')) ?>+' <strong>'+c.dispatch_number+'</strong> — <code>'+(c.unit_code||'')+'</code> — '+tks+' '+<?= json_encode(__('admin.labels_wp.packages_word','pacotes')) ?>+'</label></div>';});el.innerHTML=h;}else{el.innerHTML='<span class="small text-muted">'+<?= json_encode(__('admin.labels_wp.no_container_available','Nenhum container disponível')) ?>+'</span>';}}catch(e){el.innerHTML='<span class="text-danger small">'+e.message+'</span>';}}
async function criarFatura(){const ids=[...document.querySelectorAll('.chk-fatura-cnt:checked')].map(e=>parseInt(e.value));if(!ids.length){alert(<?= json_encode(__('admin.labels_wp.select_containers','Selecione containers.')) ?>);return;}if(!confirm(<?= json_encode(__('admin.labels_wp.confirm_create_invoice','Criar fatura com {n} container(s)? Irreversível.')) ?>.replace('{n}', ids.length)))return;const btn=document.getElementById('btn-criar-fatura');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>'+<?= json_encode(__('admin.labels_wp.processing','Processando...')) ?>;try{const r=await fetch(BASE+'/criar-fatura',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({containerIds:ids})});const d=await r.json();const el=document.getElementById('fatura-resultado');el.style.display='block';if(d.success){el.innerHTML='<div class="alert alert-success py-2 small">'+<?= json_encode(__('admin.labels_wp.invoice_label','Fatura:')) ?>+' <code>'+d.cn38_code+'</code></div>';carregarContainersParaFatura();carregarFaturas();carregarContainers();}else{el.innerHTML='<div class="alert alert-danger py-2 small">'+d.error+'</div>';}}catch(e){alert(e.message);}btn.disabled=false;btn.innerHTML='<i class="fas fa-file-invoice me-1"></i>'+<?= json_encode(__('admin.labels_wp.create_invoice_btn','Criar Fatura')) ?>;}
async function carregarFaturas(){const tbody=document.getElementById('faturas-body');try{const r=await fetch(BASE+'/listar-faturas?per_page=50');const d=await r.json();tbody.innerHTML='';if(d.success&&d.data&&d.data.length>0){d.data.forEach((b,idx)=>{const dns=Array.isArray(b.dispatch_numbers)?b.dispatch_numbers.join(', '):'-';const status=b.departure_id?('<span class="badge bg-success">'+<?= json_encode(__('admin.labels_wp.shipped','Embarcado')) ?>+'</span>'):('<span class="badge bg-warning text-dark">'+<?= json_encode(__('admin.labels_wp.awaiting_shipment','Aguardando embarque')) ?>+'</span>');const isFirst=idx===0;tbody.innerHTML+='<tr class="'+(isFirst?'table-info':'')+'"><td><code class="small">'+(b.cn38_code||'-')+'</code>'+(isFirst?(' <span class="badge bg-info text-dark">'+<?= json_encode(__('admin.labels_wp.latest','Última')) ?>+'</span>'):'')+'</td><td>'+dns+'</td><td>'+status+'</td><td class="d-none d-md-table-cell">'+(b.created_at||'-')+'</td><td>'+(b.wp_post_id?'<a href="'+BASE+'/pdf/fatura/'+b.wp_post_id+'" target="_blank" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf"></i></a>':'')+'</td><td><button class="btn btn-xs btn-outline-secondary" onclick="deletarFatura('+b.wp_post_id+')" title="'+<?= json_encode(__('admin.labels_wp.delete','Deletar')) ?>+'"><i class="fas fa-trash"></i></button></td></tr>';});}else{tbody.innerHTML='<tr><td colspan="6" class="ewp-empty"><i class="fas fa-inbox"></i>'+<?= json_encode(__('admin.labels_wp.no_invoice','Nenhuma fatura')) ?>+'</td></tr>';}}catch(e){tbody.innerHTML='<tr><td colspan="6" class="text-danger">'+e.message+'</td></tr>';}}
async function deletarFatura(id){if(!confirm(<?= json_encode(__('admin.labels_wp.confirm_delete_invoice','Deletar fatura? Containers desvinculados.')) ?>))return;try{const r=await fetch(BASE+'/deletar-fatura',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({wp_post_id:id})});const d=await r.json();if(d.success){alert(<?= json_encode(__('admin.labels_wp.deleted_f','Deletada!')) ?>);carregarFaturas();carregarContainersParaFatura();carregarContainers();}else alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+d.error);}catch(e){alert(e.message);}}

// EMBARQUES
async function carregarFaturasParaEmbarque(){const el=document.getElementById('emb-faturas-lista');try{const r=await fetch(BASE+'/listar-faturas?without_departure=1');const d=await r.json();if(d.success&&d.data&&d.data.length>0){let h='<div class="d-flex justify-content-between mb-1"><small class="text-muted">'+<?= json_encode(__('admin.labels_wp.n_invoices','{n} fatura(s)')) ?>.replace('{n}', d.data.length)+'</small><a href="#" onclick="document.querySelectorAll(\'.chk-emb-fatura\').forEach(e=>e.checked=true);return false;" class="small">'+<?= json_encode(__('admin.labels_wp.all_f','Todas')) ?>+'</a></div>';d.data.forEach((b,i)=>{const ultimaBadge=i===0?(' <span class="badge bg-info text-dark" style="font-size:.65rem;">'+<?= json_encode(__('admin.labels_wp.latest_generated','Última gerada')) ?>+'</span>'):'';h+='<div class="form-check"><input class="form-check-input chk-emb-fatura" type="checkbox" value="'+b.wp_post_id+'"><label class="form-check-label small">CN38: <code>'+(b.cn38_code||'-')+'</code>'+ultimaBadge+'</label></div>';});el.innerHTML=h;}else{el.innerHTML='<span class="small text-muted">'+<?= json_encode(__('admin.labels_wp.no_invoice_without_shipment','Nenhuma fatura sem embarque')) ?>+'</span>';}}catch(e){el.innerHTML='<span class="text-danger small">'+e.message+'</span>';}}
async function criarEmbarque(event){event.preventDefault();const billIds=[...document.querySelectorAll('.chk-emb-fatura:checked')].map(e=>parseInt(e.value));if(!billIds.length){alert(<?= json_encode(__('admin.labels_wp.select_invoices','Selecione faturas.')) ?>);return;}const data={billIds,flightNumber:parseInt(document.getElementById('emb-flight').value),airlineCode:document.getElementById('emb-airline').value,departureDate:new Date(document.getElementById('emb-departure-date').value).toISOString(),departureAirportCode:document.getElementById('emb-departure-airport').value.toUpperCase(),arrivalDate:new Date(document.getElementById('emb-arrival-date').value).toISOString(),arrivalAirportCode:document.getElementById('emb-arrival-airport').value.toUpperCase()};const btn=document.getElementById('btn-criar-embarque');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>'+<?= json_encode(__('admin.labels_wp.confirming','Confirmando...')) ?>;try{const r=await fetch(BASE+'/criar-embarque',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const d=await r.json();const el=document.getElementById('embarque-resultado');el.style.display='block';if(d.success){el.innerHTML='<div class="alert alert-success py-2 small">'+<?= json_encode(__('admin.labels_wp.shipment_confirmed','Embarque confirmado!')) ?>+'</div>';carregarFaturasParaEmbarque();carregarEmbarques();carregarFaturas();}else{el.innerHTML='<div class="alert alert-danger py-2 small">'+d.error+'</div>';}}catch(e){alert(e.message);}btn.disabled=false;btn.innerHTML='<i class="fas fa-plane me-1"></i>'+<?= json_encode(__('admin.labels_wp.confirm_shipment','Confirmar Embarque')) ?>;}
async function carregarEmbarques(){const tbody=document.getElementById('embarques-body');try{const r=await fetch(BASE+'/listar-embarques');const d=await r.json();tbody.innerHTML='';if(d.success&&d.data&&d.data.length>0){d.data.forEach(dep=>{const fl=dep.flight||{};const codes=Array.isArray(dep.cn38_codes)?dep.cn38_codes.join(', '):'-';const st=dep.status==='confirmed'?('<span class="badge bg-success">'+<?= json_encode(__('admin.labels_wp.ok','OK')) ?>+'</span>'):('<span class="badge bg-danger">'+<?= json_encode(__('admin.labels_wp.error','Erro')) ?>+'</span>');const errMsg=(dep.status!=='confirmed'&&dep.error_message)?'<br><small class="text-danger">'+dep.error_message+'</small>':'';tbody.innerHTML+='<tr><td>'+(fl.flightNumber||'-')+'</td><td>'+(fl.airlineCode||'-')+'</td><td>'+(fl.departureDate?new Date(fl.departureDate).toLocaleDateString('pt-BR'):'-')+'</td><td class="d-none d-md-table-cell">'+(fl.arrivalDate?new Date(fl.arrivalDate).toLocaleDateString('pt-BR'):'-')+'</td><td><code class="small">'+codes+'</code></td><td>'+st+errMsg+'</td><td><button class="btn btn-xs btn-outline-secondary" onclick="deletarEmbarque('+dep.wp_post_id+')" title="'+<?= json_encode(__('admin.labels_wp.delete','Deletar')) ?>+'"><i class="fas fa-trash"></i></button></td></tr>';});}else{tbody.innerHTML='<tr><td colspan="7" class="ewp-empty"><i class="fas fa-inbox"></i>'+<?= json_encode(__('admin.labels_wp.no_shipment','Nenhum embarque')) ?>+'</td></tr>';}}catch(e){tbody.innerHTML='<tr><td colspan="7" class="text-danger">'+e.message+'</td></tr>';}}
async function deletarEmbarque(id){if(!confirm(<?= json_encode(__('admin.labels_wp.confirm_delete_shipment','Deletar embarque? Faturas desvinculadas.')) ?>))return;try{const r=await fetch(BASE+'/deletar-embarque',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({wp_post_id:id})});const d=await r.json();if(d.success){alert(<?= json_encode(__('admin.labels_wp.deleted','Deletado!')) ?>);carregarEmbarques();carregarFaturasParaEmbarque();carregarFaturas();}else alert(<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+d.error);}catch(e){alert(e.message);}}

// DOCUMENTACAO (aba interna - accordion + paginacao)
function escHtmlDoc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
var docAllFaturas=[];var docAllContainers=[];var docPerPage=10;var docCurrentPage=1;
async function carregarDocumentacaoTab(){var el=document.getElementById('doc-tab-lista');var loading=document.getElementById('doc-tab-loading');el.innerHTML='';document.getElementById('doc-tab-pagination').innerHTML='';loading.style.display='block';try{var r=await Promise.all([fetch(BASE+'/listar-containers?per_page=500'),fetch(BASE+'/listar-faturas?per_page=500')]);var dCnt=await r[0].json();var dFat=await r[1].json();loading.style.display='none';docAllContainers=(dCnt.success&&dCnt.data)?dCnt.data:[];docAllFaturas=(dFat.success&&dFat.data)?dFat.data:[];if(!docAllFaturas.length){el.innerHTML='<div class="text-muted text-center py-4">'+<?= json_encode(__('admin.labels_wp.no_invoice_found','Nenhuma fatura encontrada.')) ?>+'</div>';return;}docCurrentPage=1;renderDocPage();}catch(e){loading.style.display='none';el.innerHTML='<div class="alert alert-danger small">'+<?= json_encode(__('admin.labels_wp.error_prefix','Erro:')) ?>+' '+e.message+'</div>';}}
function renderDocPage(){var el=document.getElementById('doc-tab-lista');var totalPages=Math.ceil(docAllFaturas.length/docPerPage);var start=(docCurrentPage-1)*docPerPage;var end=Math.min(start+docPerPage,docAllFaturas.length);var items=docAllFaturas.slice(start,end);var html='';items.forEach(function(fat,li){var gi=start+li;var cn38=fat.cn38_code||'-';var dns=Array.isArray(fat.dispatch_numbers)?fat.dispatch_numbers.map(function(d){return String(d);}):[];var isFirst=gi===0;var badge=isFirst?(' <span class="badge bg-info text-dark">'+<?= json_encode(__('admin.labels_wp.latest','Última')) ?>+'</span>'):'';var status=fat.departure_id?('<span class="badge bg-success">'+<?= json_encode(__('admin.labels_wp.shipped','Embarcado')) ?>+'</span>'):('<span class="badge bg-warning text-dark">'+<?= json_encode(__('admin.labels_wp.awaiting','Aguardando')) ?>+'</span>');var fatCnts=docAllContainers.filter(function(c){return dns.indexOf(String(c.dispatch_number))!==-1;});var docsCount=(fat.wp_post_id?1:0)+fatCnts.filter(function(c){return c.wp_post_id;}).length;html+='<div class="border rounded mb-3 shadow-sm" id="dti-'+gi+'" style="overflow:hidden;">';html+='<div class="d-flex align-items-center justify-content-between px-3 py-3" style="cursor:pointer;background:#fff;" onclick="toggleDti('+gi+')">';html+='<div class="d-flex align-items-center gap-2 flex-wrap" style="min-width:0;flex:1;"><i class="fas fa-chevron-right dti-chev-'+gi+'" style="font-size:.75rem;color:#64748b;transition:transform .2s;'+(gi===start?'transform:rotate(90deg);':'')+'"></i> <span style="font-size:.95rem;font-weight:600;">'+escHtmlDoc(cn38)+'</span> '+badge+' '+status+' <span class="text-muted" style="font-size:.85rem;">'+<?= json_encode(__('admin.labels_wp.dispatches_label','Remessas:')) ?>+' '+escHtmlDoc(dns.join(', ')||'-')+'</span></div>';html+='<div class="d-flex align-items-center gap-2 flex-shrink-0"><span class="badge bg-light text-dark border" style="font-size:.8rem;padding:5px 10px;">'+docsCount+' PDF'+(docsCount!==1?'s':'')+'</span><button class="btn btn-primary" onclick="event.stopPropagation();baixarDocsFaturaTab('+gi+')" style="font-size:.85rem;padding:6px 14px;"><i class="fas fa-download me-1"></i>'+<?= json_encode(__('admin.labels_wp.download','Baixar')) ?>+'</button></div>';html+='</div>';html+='<div id="dti-body-'+gi+'" style="'+(gi===start?'':'display:none;')+'padding:16px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;"><div class="row"><div class="col-md-4 mb-2"><div style="font-size:.8rem;text-transform:uppercase;font-weight:700;color:#475569;margin-bottom:8px;"><i class="fas fa-file-invoice me-1 text-danger"></i>'+<?= json_encode(__('admin.labels_wp.invoice_word','Fatura')) ?>+'</div>';if(fat.wp_post_id){html+='<a href="'+BASE+'/pdf/fatura/'+fat.wp_post_id+'" target="_blank" class="btn btn-outline-danger dti-pdf-'+gi+'" data-url="'+BASE+'/pdf/fatura/'+fat.wp_post_id+'" data-name="fatura_'+escHtmlDoc(cn38)+'" style="font-size:.85rem;padding:8px 16px;"><i class="fas fa-file-pdf me-2"></i>'+escHtmlDoc(cn38)+'</a>';}else{html+='<span class="text-muted">-</span>';}html+='</div><div class="col-md-8 mb-2"><div style="font-size:.8rem;text-transform:uppercase;font-weight:700;color:#475569;margin-bottom:8px;"><i class="fas fa-box me-1 text-primary"></i>'+<?= json_encode(__('admin.labels_wp.containers','Containers')) ?>+'</div><div class="d-flex flex-wrap gap-2">';if(fatCnts.length>0){fatCnts.forEach(function(c){var tk=Array.isArray(c.tracking_codes)?c.tracking_codes.length:0;if(c.wp_post_id){html+='<a href="'+BASE+'/pdf/container/'+c.wp_post_id+'" target="_blank" class="btn btn-outline-primary dti-pdf-'+gi+'" data-url="'+BASE+'/pdf/container/'+c.wp_post_id+'" data-name="container_'+c.dispatch_number+'" style="font-size:.85rem;padding:8px 16px;"><i class="fas fa-file-pdf me-2"></i>'+<?= json_encode(__('admin.labels_wp.dispatch_abbr','Rem')) ?>+' '+c.dispatch_number+' <span class="text-muted ms-1">('+tk+' pct)</span></a>';}else{html+='<span class="btn btn-outline-secondary disabled" style="font-size:.85rem;padding:8px 16px;">'+<?= json_encode(__('admin.labels_wp.dispatch_abbr','Rem')) ?>+' '+c.dispatch_number+'</span>';}});}else{html+='<span class="text-muted">'+<?= json_encode(__('admin.labels_wp.no_container_linked','Nenhum container vinculado')) ?>+'</span>';}html+='</div></div></div></div>';html+='</div>';});el.innerHTML=html;renderDocPagination(totalPages);}
function renderDocPagination(tp){var pag=document.getElementById('doc-tab-pagination');if(tp<=1){pag.innerHTML='';return;}var html='<div class="d-flex align-items-center justify-content-center gap-2 mt-4">';html+='<button class="btn btn-outline-secondary" onclick="docGoPage('+(docCurrentPage-1)+')" '+(docCurrentPage===1?'disabled':'')+' style="padding:6px 12px;"><i class="fas fa-chevron-left"></i></button>';for(var i=1;i<=tp;i++){if(tp<=7||i<=2||i>=tp-1||Math.abs(i-docCurrentPage)<=1){html+='<button class="btn '+(i===docCurrentPage?'btn-primary':'btn-outline-secondary')+'" onclick="docGoPage('+i+')" style="padding:6px 12px;min-width:38px;">'+i+'</button>';}else if(i===3&&docCurrentPage>4){html+='<span class="text-muted px-1">...</span>';}else if(i===tp-2&&docCurrentPage<tp-3){html+='<span class="text-muted px-1">...</span>';}}html+='<button class="btn btn-outline-secondary" onclick="docGoPage('+(docCurrentPage+1)+')" '+(docCurrentPage===tp?'disabled':'')+' style="padding:6px 12px;"><i class="fas fa-chevron-right"></i></button>';html+='<span class="text-muted ms-3">'+<?= json_encode(__('admin.labels_wp.n_invoices','{n} fatura(s)')) ?>.replace('{n}', docAllFaturas.length)+'</span></div>';pag.innerHTML=html;}
function docGoPage(p){var tp=Math.ceil(docAllFaturas.length/docPerPage);if(p<1||p>tp)return;docCurrentPage=p;renderDocPage();}
function toggleDti(idx){var body=document.getElementById('dti-body-'+idx);var chev=document.querySelector('.dti-chev-'+idx);if(!body)return;var isOpen=body.style.display!=='none';body.style.display=isOpen?'none':'';if(chev)chev.style.transform=isOpen?'rotate(0deg)':'rotate(90deg)';}
async function baixarDocsFaturaTab(gi){var links=document.querySelectorAll('.dti-pdf-'+gi);if(!links.length){alert(<?= json_encode(__('admin.labels_wp.no_pdf','Nenhum PDF.')) ?>);return;}var item=document.getElementById('dti-'+gi);var btn=item?item.querySelector('.btn-primary'):null;if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';}var dl=0;for(var i=0;i<links.length;i++){var url=links[i].getAttribute('data-url');var name=links[i].getAttribute('data-name')||'doc';if(!url)continue;try{var resp=await fetch(url);if(!resp.ok)continue;var blob=await resp.blob();var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name+'.pdf';document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(a.href);dl++;await new Promise(function(r){setTimeout(r,400);});}catch(e){}}if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-download me-1"></i>'+<?= json_encode(__('admin.labels_wp.download','Baixar')) ?>;}if(dl>0)alert(<?= json_encode(__('admin.labels_wp.n_pdfs_downloaded','{n} PDF(s) baixado(s)!')) ?>.replace('{n}', dl));}
// REGERAR ETIQUETA
async function regerarEtiquetaWp(pedidoId){if(!confirm(<?= json_encode(__('admin.labels_wp.confirm_regenerate','Regerar etiqueta do pedido #{n}?\n\nA etiqueta atual será deletada e uma nova será gerada com os dados atuais do pedido.')) ?>.replace('{n}', String(pedidoId).padStart(6,'0'))))return;try{const r=await fetch(BASE+'/regerar-etiqueta',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pedido_id:pedidoId})});const d=await r.json();if(d.success){alert(<?= json_encode(__('admin.labels_wp.label_regenerated','Etiqueta regerada com sucesso!\nNovo rastreio: {n}')) ?>.replace('{n}', (d.tracking_number||'')));carregarPacotes();}else{alert(<?= json_encode(__('admin.labels_wp.regenerate_error','Erro ao regerar: ')) ?>+(d.error||<?= json_encode(__('admin.labels_wp.unknown_failure','Falha desconhecida')) ?>));}}catch(e){alert(<?= json_encode(__('admin.labels_wp.network_error','Erro de rede:')) ?>+' '+e.message);}}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
