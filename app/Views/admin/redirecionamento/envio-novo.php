<?php
$sidebarActive = 'redirecionamento-envios';
$title = 'Novo Envio';
$redirecionadores = is_array($redirecionadores ?? null) ? $redirecionadores : [];
$tabela = is_array($tabela ?? null) ? $tabela : [];
$stripePublicKey = $stripePublicKey ?? '';
$redirecionadorFixo = $redirecionadorFixo ?? null;

// Lista NCM
$ncmOpcoes = [
    '09019000'=>'Cafés','17041000'=>'Chicletes','17049020'=>'Balas, Confeitos, Pastilhas, Pirulitos',
    '18063110'=>'Chocolates','19012090'=>'Massas para Bolos, Panquecas, Paes','19041000'=>'Pipocas e Cereais',
    '19049000'=>'Salgadinhos e Snacks','19059090'=>'Bolachas e Biscoitos','21011110'=>'Cafés Soluveis',
    '21039021'=>'Temperos e Preparações','21039099'=>'Molhos (geral)','21069030'=>'Suplementos',
    '23091000'=>'Petisco para caes e gatos','30051090'=>'Curativos','33030010'=>'Perfumes',
    '33041000'=>'Maquiagem para os Labios','33042090'=>'Maquiagem para os Olhos','33043000'=>'Manicure e Pedicure',
    '33049910'=>'Cremes de Beleza, Tonicos','33049990'=>'Protetor Solar e Bronzeadores','33051000'=>'Shampoos',
    '33059000'=>'Produtos p Cabelo (geral)','33061000'=>'Creme Dental','33062000'=>'Fio Dental',
    '33069000'=>'Enxaguante Bucal (outros)','33071000'=>'Creme de Barbear','33072090'=>'Desodorantes',
    '33073000'=>'Sais de Banho e outras Preparações','33074900'=>'Cheirinho de Carro e Casa',
    '34013000'=>'Sabonete Liquido, Detergente','34024190'=>'Produtos de Limpeza','34060000'=>'Velas e Pavis',
    '38229000'=>'NIMA, Fita Teste e outros medidores','39232190'=>'Sacos de Lixo/ Sacos Ziplock',
    '39241000'=>'Utensilios de Cozinha, Banheiro e Geral (PLÁSTICO)','39264000'=>'Decorações e Estatuetas',
    '40149090'=>'Chupetas','42029900'=>'Bolsas (geral)','48202000'=>'Cadernos',
    '48236900'=>'Forro de AirFryer (papel)','62099090'=>'Roupas Bebe (geral)','63022900'=>'Roupas de Cama',
    '63071000'=>'Pano de Chão, Pano Prato, Esponja de Louça','63090090'=>'Roupas (geral)',
    '67049000'=>'Cílios Postiços, Perucas','70109090'=>'Recipientes de Vidro',
    '70134210'=>'Cafeteiras e Chaleiras (VIDRO)','70134900'=>'Utensilios de Cozinha, Banheiro e Geral (VIDRO)',
    '73102190'=>'Latas (Lavanderia)','84433240'=>'Impressoras a folhas','85086000'=>'Aspiradores',
    '85094010'=>'Liquidificadores','85094020'=>'Batedeiras','85094040'=>'Extratores de Suco e Polpas',
    '85094050'=>'Processadores de Alimentos','85101000'=>'Aparelhos de Barbear',
    '85102000'=>'Máquinas de Cortar Cabelo','85103000'=>'Aparelhos de Depilar',
    '85163100'=>'Secadores de Cabelo','85163200'=>'Outros Aparelhos para Arranjo de Cabelo',
    '85166000'=>'Fornos, Grelhas e Assadeiras','85167100'=>'Aparelhos para fazer chás e cafés',
    '85167990'=>'Dash, Panela Ninja','85171300'=>'Celulares','94055000'=>'Luminarias não eletricas',
    '95030022'=>'Bonecos','95030099'=>'Brinquedos em Geral','95069100'=>'Produtos de Ginastica e Esportes',
    '96032100'=>'Escova de Dentes','96033000'=>'Pincéis de Maquiagem/ Pincéis Artista',
    '96081000'=>'Canetas Esferograficas','96082000'=>'Canetas e Marcadores','96084000'=>'Lapiseiras',
    '96159000'=>'Pentes, Presilhas, Grampos','96170010'=>'Garrafas e Recipientes Termicos',
    '85098090'=>'Esfregões e Escovas Elétricas de Limpeza','84141000'=>'Bombas de Leite Materno',
    '85183000'=>'Fones de Ouvido','85044010'=>'Carregadores (geral)','85068090'=>'Pilhas e Baterias',
    '76151000'=>'Utensilios de Cozinha (Panelas), Banheiro e Geral (METAL)',
    '94049000'=>'Travesseiros, Almofadas, Puffes e Edredões','85235190'=>'Cartão de Memória',
    '85393120'=>'Lampadas','94052900'=>'Luminária e Abajures Elétricos','34011190'=>'Lenços Umedecidos',
    '38099190'=>'Lenços Para Secadora','84672100'=>'Furadeiras','94017100'=>'Cadeiras de Alimentação',
    '90251990'=>'Termometro Digital','95045000'=>'Video Games','85269200'=>'Controles Remotos',
    '64059000'=>'Sapatos em Geral','49019900'=>'Livros','84145990'=>'Ventiladores',
    '90191000'=>'Massageadores','48182000'=>'Lenços e Toalhas de Mão','85258929'=>'Cameras e Baba Eletronicas',
    '71179000'=>'Bijuterias','82059000'=>'Ferramentas Manuais','48025610'=>'Folha Sulfite',
    '63079090'=>'Brinquedo Pelucia Pet','84148019'=>'Compressores de Ar',
    '96200000'=>'Monopés, bipés, tripés e artigos semelhantes','39204390'=>'Plastico Filme e Semelhantes',
    '96162000'=>'Esponja de Maquiagem','69119000'=>'Utensilios de Cozinha, Banheiro e Geral (PORCELANA)',
    '84716053'=>'Mouses e Canetas Digitais','84716052'=>'Teclados',
    '48201000'=>'Post It, Papel para Cartas e Agendas','62121000'=>'Sutias e Topes',
    '82159990'=>'Talheres (Aço)','82119290'=>'Facas de Cozinha','82130000'=>'Tesouras',
    '63080000'=>'Capas de Tecido, Tapetes, Toalhas de Mesa, Guardanapos, Cestos',
    '38089199'=>'Repelentes para Corpo','87150000'=>'Carrinhos de Bebê e Suas Partes',
    '94012000'=>'Cadeirinhas e Assentos para Carros','42010090'=>'Coleiras de Cachorro',
    '85164000'=>'Ferros de Passar Roupas','96190000'=>'Absorventes, Tampões e Fraldas',
    '09024000'=>'Chá Preto','63026000'=>'Toalhas de Banho','85444200'=>'Fios, Cabos e Outros Condutores',
];
ksort($ncmOpcoes);
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/admin/redirecionamento/envios" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="h2 mb-0">Novo Envio</h1>
            <div class="text-muted small">Preencha os passos abaixo</div>
        </div>
    </div>

    <!-- Steps nav -->
    <div class="d-flex gap-2 mb-4 flex-wrap" id="stepsNav">
        <?php foreach ([1=>'Pedido',2=>'Destinatário',3=>'Envio',4=>'Produtos',5=>'Pagamento'] as $n=>$l): ?>
        <div class="step-pill d-flex align-items-center gap-2 px-3 py-2 rounded-pill border <?= $n===1?'border-primary bg-primary text-white':'border-secondary text-muted' ?>"
             data-step="<?= $n ?>" style="font-size:.85rem">
            <span class="fw-bold"><?= $n ?></span> <?= $l ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="stepError" class="alert alert-danger d-none mb-3"></div>

    <form id="formEnvio">
    <!-- STEP 1: Pedido -->
    <div class="step-content" data-step="1">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Informações do pedido</h5>
                <div class="row g-3">
                    <?php if ($redirecionadorFixo): ?>
                    <input type="hidden" name="redirecionador_id" value="<?= (int)$redirecionadorFixo['id'] ?>">
                    <?php else: ?>
                    <div class="col-md-6">
                        <label class="form-label">Redirecionador <span class="text-danger">*</span></label>
                        <select class="form-select" name="redirecionador_id" id="selRedirecionador" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($redirecionadores as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome'],ENT_QUOTES,'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label">ID do pedido (site do cliente) <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="id_pedido_cliente" id="idPedidoCliente" required placeholder="Ex: 1234">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 2: Destinatário -->
    <div class="step-content d-none" data-step="2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Destinatário</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm" id="selCliente" style="min-width:240px">
                            <option value="">— Selecione um cliente —</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNovoCliente">
                            <i class="fas fa-plus me-1"></i>Novo cliente
                        </button>
                        <a id="linkEditarCliente" href="/admin/redirecionamento/clientes" target="_blank"
                           class="btn btn-sm btn-outline-secondary d-none" title="Editar dados do cliente">
                            <i class="fas fa-pen"></i> Editar cliente
                        </a>
                    </div>
                </div>
                <div id="destPlaceholder" class="text-center py-5 text-muted">
                    <i class="fas fa-user-circle fa-3x mb-3 d-block opacity-25"></i>
                    Selecione um cliente cadastrado ou cadastre um novo para continuar.
                </div>
                <div id="destDados" class="d-none">
                    <input type="hidden" name="cliente_id" id="destClienteId">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control bg-light" type="text" name="destinatario_nome" id="destNome" readonly></div>
                        <div class="col-md-3"><label class="form-label">CPF</label><input class="form-control bg-light" type="text" name="destinatario_cpf" id="destCpf" readonly></div>
                        <div class="col-md-3"><label class="form-label">Data de nascimento</label><input class="form-control bg-light" type="date" name="destinatario_data_nascimento" id="destNasc" readonly></div>
                        <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control bg-light" type="email" name="destinatario_email" id="destEmail" readonly></div>
                        <div class="col-md-6"><label class="form-label">Telefone</label><input class="form-control bg-light" type="text" name="destinatario_telefone" id="destTel" readonly></div>
                        <div class="col-md-8"><label class="form-label">Logradouro</label><input class="form-control bg-light" type="text" name="dest_logradouro" id="destLogr" readonly></div>
                        <div class="col-md-2"><label class="form-label">Número</label><input class="form-control bg-light" type="text" name="dest_numero" id="destNum" readonly></div>
                        <div class="col-md-2"><label class="form-label">Complemento</label><input class="form-control bg-light" type="text" name="dest_complemento" id="destComp" readonly></div>
                        <div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control bg-light" type="text" name="dest_bairro" id="destBairro" readonly></div>
                        <div class="col-md-4"><label class="form-label">Cidade</label><input class="form-control bg-light" type="text" name="dest_cidade" id="destCidade" readonly></div>
                        <div class="col-md-2"><label class="form-label">Estado</label><input class="form-control bg-light" type="text" name="dest_estado" id="destEstado" readonly></div>
                        <div class="col-md-2"><label class="form-label">CEP</label><input class="form-control bg-light" type="text" name="dest_cep" id="destCep" readonly></div>
                    </div>
                    <div class="mt-3 small text-muted"><i class="fas fa-info-circle me-1"></i>Para alterar os dados, clique em "Editar cliente".</div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 3: Envio -->
    <div class="step-content d-none" data-step="3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Informações de envio</h5>
                <div class="alert alert-warning small mb-3"><i class="fas fa-triangle-exclamation me-2"></i>Preencha o peso e dimensões corretos da caixa.</div>
                <div class="row g-3">
                    <div class="col-md-2"><label class="form-label">Moeda</label><input class="form-control" type="text" value="USD" readonly></div>
                    <div class="col-md-3">
                        <label class="form-label">Peso total (kg) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" step="0.001" min="0.001" name="peso_kg" id="inputPeso" required>
                        <div class="form-text">Preencha com precisão — base da cobrança.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor do frete cobrado ao cliente (USD)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="valor_frete_usd" id="inputFrete">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor a pagar (calculado)</label>
                        <div class="input-group">
                            <span class="input-group-text">US$</span>
                            <input class="form-control fw-bold" type="text" id="valorCalculado" readonly placeholder="—">
                        </div>
                        <div class="form-text" id="faixaInfo"></div>
                    </div>
                    <div class="col-md-4"><label class="form-label">Largura (cm) <span class="text-danger">*</span></label><input class="form-control" type="number" step="0.1" min="1" name="largura_cm" id="larguraCm" required></div>
                    <div class="col-md-4"><label class="form-label">Altura (cm) <span class="text-danger">*</span></label><input class="form-control" type="number" step="0.1" min="1" name="altura_cm" id="alturaCm" required></div>
                    <div class="col-md-4"><label class="form-label">Comprimento (cm) <span class="text-danger">*</span></label><input class="form-control" type="number" step="0.1" min="1" name="comprimento_cm" id="comprimentoCm" required></div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 4: Produtos -->
    <div class="step-content d-none" data-step="4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Produtos <span class="text-danger">*</span></h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddProduto"><i class="fas fa-plus me-1"></i>Adicionar produto</button>
                </div>
                <div id="listaProdutos"></div>
                <div class="text-muted small mt-2">Adicione ao menos um produto para continuar.</div>
            </div>
        </div>
    </div>

    <!-- STEP 5: Pagamento -->
    <div class="step-content d-none" data-step="5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Pagamento</h5>
                <div id="resumoPagamento" class="alert alert-info mb-3"></div>
                <div id="stripeContainer" class="mb-3"></div>
                <div id="msgPagamento"></div>
                <div class="mt-3">
                    <label class="form-label">Upload do comprovante de pagamento</label>
                    <input type="file" class="form-control" id="inputComprovante" accept=".jpg,.jpeg,.png,.pdf">
                    <div class="form-text">Após o pagamento, envie o comprovante.</div>
                </div>
                <div class="alert alert-warning small mt-3"><i class="fas fa-info-circle me-2"></i>Após a coleta, verificaremos as dimensões e peso reais.</div>
            </div>
        </div>
    </div>

    <!-- Navegação -->
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary" id="btnAnterior" style="display:none!important">Anterior</button>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-primary" id="btnProximo">Próximo</button>
            <button type="button" class="btn btn-success d-none" id="btnGerarEnvio"><i class="fas fa-check me-1"></i>Gerar envio e ir para pagamento</button>
        </div>
    </div>
    </form>
</div>

<!-- Modal novo cliente -->
<div class="modal fade" id="modalNovoCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Cadastrar cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Nome <span class="text-danger">*</span></label><input class="form-control" type="text" id="ncNome"></div>
                    <div class="col-md-3">
                        <label class="form-label">CPF <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="ncCpf" placeholder="000.000.000-00" maxlength="14">
                        <div class="invalid-feedback" id="ncCpfFeedback"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data nascimento <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" id="ncNasc">
                        <div class="invalid-feedback" id="ncNascFeedback"></div>
                    </div>
                    <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control" type="email" id="ncEmail"></div>
                    <div class="col-md-6"><label class="form-label">Telefone</label><input class="form-control" type="text" id="ncTel"></div>
                    <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Endereço principal</small></div>
                    <div class="col-md-3">
                        <label class="form-label">CEP</label>
                        <div class="input-group">
                            <input class="form-control" type="text" id="ncCep" placeholder="00000-000" maxlength="9">
                            <span class="input-group-text" id="ncCepSpinner" style="display:none"><i class="fas fa-spinner fa-spin"></i></span>
                        </div>
                    </div>
                    <div class="col-md-7"><label class="form-label">Logradouro</label><input class="form-control" type="text" id="ncLogr"></div>
                    <div class="col-md-2"><label class="form-label">Número</label><input class="form-control" type="text" id="ncNum"></div>
                    <div class="col-md-3"><label class="form-label">Complemento</label><input class="form-control" type="text" id="ncComp"></div>
                    <div class="col-md-3"><label class="form-label">Bairro</label><input class="form-control" type="text" id="ncBairro"></div>
                    <div class="col-md-4"><label class="form-label">Cidade <span class="text-danger">*</span></label><input class="form-control" type="text" id="ncCidade"></div>
                    <div class="col-md-2"><label class="form-label">Estado</label><input class="form-control" type="text" id="ncEstado" maxlength="2"></div>
                </div>
                <div id="msgCliente" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarCliente">Salvar cliente</button>
            </div>
        </div>
    </div>
</div>

<!-- Datalist NCM -->
<datalist id="ncmDatalist">
    <?php foreach ($ncmOpcoes as $cod => $desc): ?>
    <option value="<?= htmlspecialchars($cod,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($cod.' — '.$desc,ENT_QUOTES,'UTF-8') ?></option>
    <?php endforeach; ?>
</datalist>

<?php if (!empty($stripePublicKey)): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>
<script>
const STRIPE_PK = <?= json_encode($stripePublicKey) ?>;
const TABELA = <?= json_encode(array_map(fn($r)=>['peso'=>(float)$r['peso_ate_kg'],'valor'=>(float)$r['valor_usd']],$tabela)) ?>;
const RED_FIXO_ID = <?= $redirecionadorFixo ? (int)$redirecionadorFixo['id'] : 'null' ?>;
let currentStep = 1, totalSteps = 5, envioId = null, valorUsd = 0;
let stripe = STRIPE_PK ? Stripe(STRIPE_PK) : null;
let elements = null, cardElement = null;

// ── CPF / Idade / CEP ────────────────────────────────────────────────────────
function validarCPF(cpf) {
    cpf = cpf.replace(/\D/g,'');
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    let s=0; for(let i=0;i<9;i++) s+=parseInt(cpf[i])*(10-i);
    let r=(s*10)%11; if(r===10||r===11) r=0; if(r!==parseInt(cpf[9])) return false;
    s=0; for(let i=0;i<10;i++) s+=parseInt(cpf[i])*(11-i);
    r=(s*10)%11; if(r===10||r===11) r=0; return r===parseInt(cpf[10]);
}
function mascaraCPF(v){ v=v.replace(/\D/g,'').slice(0,11); if(v.length>9) v=v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/,'$1.$2.$3-$4'); else if(v.length>6) v=v.replace(/(\d{3})(\d{3})(\d{1,3})/,'$1.$2.$3'); else if(v.length>3) v=v.replace(/(\d{3})(\d{1,3})/,'$1.$2'); return v; }
function validarIdade18(data){ if(!data) return false; const n=new Date(data),h=new Date(),l=new Date(n); l.setFullYear(l.getFullYear()+18); return h>=l; }
async function buscarCep(cep){ cep=cep.replace(/\D/g,''); if(cep.length!==8) return null; try{ const r=await fetch('https://viacep.com.br/ws/'+cep+'/json/'); const d=await r.json(); return d.erro?null:d; }catch(e){ return null; } }

// Máscara e validação CPF
const ncCpf = document.getElementById('ncCpf');
if(ncCpf){
    ncCpf.addEventListener('input', function(){ this.value=mascaraCPF(this.value); });
    ncCpf.addEventListener('blur', function(){
        const cpf=this.value.replace(/\D/g,''), fb=document.getElementById('ncCpfFeedback');
        if(cpf && !validarCPF(cpf)){ this.classList.add('is-invalid'); if(fb) fb.textContent='CPF inválido.'; }
        else { this.classList.remove('is-invalid'); if(fb) fb.textContent=''; }
    });
}

// Validação data nascimento
const ncNasc = document.getElementById('ncNasc');
if(ncNasc){
    ncNasc.addEventListener('change', function(){
        const fb=document.getElementById('ncNascFeedback');
        if(this.value && !validarIdade18(this.value)){ this.classList.add('is-invalid'); if(fb) fb.textContent='Cliente deve ter pelo menos 18 anos.'; }
        else { this.classList.remove('is-invalid'); if(fb) fb.textContent=''; }
    });
}

// Autocomplete CEP
const ncCep = document.getElementById('ncCep');
if(ncCep){
    ncCep.addEventListener('input', function(){ let v=this.value.replace(/\D/g,'').slice(0,8); if(v.length>5) v=v.slice(0,5)+'-'+v.slice(5); this.value=v; });
    ncCep.addEventListener('blur', async function(){
        const cep=this.value.replace(/\D/g,''); if(cep.length!==8) return;
        const sp=document.getElementById('ncCepSpinner'); if(sp) sp.style.display='';
        const d=await buscarCep(cep); if(sp) sp.style.display='none';
        if(!d) return;
        const g=id=>document.getElementById(id);
        if(d.logradouro) g('ncLogr').value=d.logradouro;
        if(d.bairro)     g('ncBairro').value=d.bairro;
        if(d.localidade) g('ncCidade').value=d.localidade;
        if(d.uf)         g('ncEstado').value=d.uf;
        g('ncNum')?.focus();
    });
}

function validarFormCliente(){
    let ok=true;
    const g=id=>document.getElementById(id);
    const nome=g('ncNome'); if(!nome?.value.trim()){ nome?.classList.add('is-invalid'); ok=false; } else nome?.classList.remove('is-invalid');
    const cpfEl=g('ncCpf'), fb=g('ncCpfFeedback'), cpf=cpfEl?.value.replace(/\D/g,'')||'';
    if(!cpf){ cpfEl?.classList.add('is-invalid'); if(fb) fb.textContent='CPF obrigatório.'; ok=false; }
    else if(!validarCPF(cpf)){ cpfEl?.classList.add('is-invalid'); if(fb) fb.textContent='CPF inválido.'; ok=false; }
    else { cpfEl?.classList.remove('is-invalid'); if(fb) fb.textContent=''; }
    const nascEl=g('ncNasc'), fb2=g('ncNascFeedback');
    if(!nascEl?.value){ nascEl?.classList.add('is-invalid'); if(fb2) fb2.textContent='Data obrigatória.'; ok=false; }
    else if(!validarIdade18(nascEl.value)){ nascEl?.classList.add('is-invalid'); if(fb2) fb2.textContent='Cliente deve ter pelo menos 18 anos.'; ok=false; }
    else { nascEl?.classList.remove('is-invalid'); if(fb2) fb2.textContent=''; }
    const cidade=g('ncCidade'); if(!cidade?.value.trim()){ cidade?.classList.add('is-invalid'); ok=false; } else cidade?.classList.remove('is-invalid');
    return ok;
}

// ── Validação por step ──────────────────────────────────────────────────────
function validarStep(n) {
    const err = document.getElementById('stepError');
    err.classList.add('d-none'); err.textContent = '';
    if (n === 1) {
        const idPedido = document.getElementById('idPedidoCliente')?.value.trim();
        if (!idPedido) { mostrarErro('Informe o ID do pedido.'); return false; }
        const selRed = document.getElementById('selRedirecionador');
        if (selRed && !selRed.value) { mostrarErro('Selecione o redirecionador.'); return false; }
    }
    if (n === 2) {
        const clienteId = document.getElementById('destClienteId')?.value;
        if (!clienteId) { mostrarErro('Selecione um cliente ou cadastre um novo antes de continuar.'); return false; }
    }
    if (n === 3) {
        const peso = parseFloat(document.getElementById('inputPeso')?.value) || 0;
        const larg = parseFloat(document.getElementById('larguraCm')?.value) || 0;
        const alt  = parseFloat(document.getElementById('alturaCm')?.value) || 0;
        const comp = parseFloat(document.getElementById('comprimentoCm')?.value) || 0;
        if (peso <= 0) { mostrarErro('Informe o peso total.'); return false; }
        if (larg <= 0 || alt <= 0 || comp <= 0) { mostrarErro('Informe largura, altura e comprimento.'); return false; }
    }
    if (n === 4) {
        const rows = document.querySelectorAll('.produto-row');
        if (rows.length === 0) { mostrarErro('Adicione ao menos um produto.'); return false; }
        for (const row of rows) {
            const desc = row.querySelector('[name*="[descricao]"]')?.value.trim();
            if (!desc) { mostrarErro('Preencha a descrição de todos os produtos.'); return false; }
        }
    }
    return true;
}
function mostrarErro(msg) {
    const err = document.getElementById('stepError');
    err.textContent = msg; err.classList.remove('d-none');
    err.scrollIntoView({behavior:'smooth', block:'nearest'});
}

// ── Tabela de preços ────────────────────────────────────────────────────────
function calcularValorTabela(peso) {
    for (const row of TABELA) { if (peso <= row.peso) return row; }
    return TABELA.length ? TABELA[TABELA.length-1] : null;
}
document.getElementById('inputPeso')?.addEventListener('input', function() {
    const peso = parseFloat(this.value) || 0;
    const row = calcularValorTabela(peso);
    if (row) { document.getElementById('valorCalculado').value = row.valor.toFixed(2); document.getElementById('faixaInfo').textContent = 'Faixa: até ' + row.peso.toFixed(1) + ' kg'; valorUsd = row.valor; }
    else { document.getElementById('valorCalculado').value = ''; document.getElementById('faixaInfo').textContent = ''; }
});

// ── Navegação entre steps ───────────────────────────────────────────────────
function showStep(n) {
    document.querySelectorAll('.step-content').forEach(el => el.classList.add('d-none'));
    document.querySelector(`.step-content[data-step="${n}"]`)?.classList.remove('d-none');
    document.querySelectorAll('.step-pill').forEach(el => {
        const s = parseInt(el.dataset.step);
        el.className = 'step-pill d-flex align-items-center gap-2 px-3 py-2 rounded-pill border ' + (s===n?'border-primary bg-primary text-white':'border-secondary text-muted');
    });
    document.getElementById('btnAnterior').style.display = n > 1 ? '' : 'none';
    document.getElementById('btnProximo').classList.toggle('d-none', n >= 4);
    document.getElementById('btnGerarEnvio').classList.toggle('d-none', n !== 4);
    document.getElementById('stepError').classList.add('d-none');
    if (n === 5) setupPagamento();
}
document.getElementById('btnProximo').addEventListener('click', () => { if (!validarStep(currentStep)) return; if (currentStep < totalSteps) { currentStep++; showStep(currentStep); } });
document.getElementById('btnAnterior').addEventListener('click', () => { if (currentStep > 1) { currentStep--; showStep(currentStep); } });
document.querySelectorAll('.step-pill').forEach(el => { el.addEventListener('click', () => { const t=parseInt(el.dataset.step); if(t<currentStep){ currentStep=t; showStep(currentStep); } }); });

// ── Produtos com NCM autocomplete ──────────────────────────────────────────
let prodIdx = 0;
document.getElementById('btnAddProduto').addEventListener('click', () => {
    const i = prodIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 align-items-end produto-row border-bottom pb-2';
    div.innerHTML = `
        <div class="col-md-3"><label class="form-label small">NCM</label>
            <div class="position-relative">
                <input class="form-control form-control-sm ncm-input" type="text" name="produtos[${i}][ncm]" placeholder="Código ou descrição..." autocomplete="off">
                <div class="ncm-dropdown list-group position-absolute w-100 shadow-sm" style="z-index:1000;max-height:200px;overflow-y:auto;display:none"></div>
            </div></div>
        <div class="col-md-4"><label class="form-label small">Descrição <span class="text-danger">*</span></label>
            <input class="form-control form-control-sm" type="text" name="produtos[${i}][descricao]" required></div>
        <div class="col-md-2"><label class="form-label small">Preço (USD)</label>
            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="produtos[${i}][preco_usd]" value="0"></div>
        <div class="col-md-1"><label class="form-label small">Peso (kg)</label>
            <input class="form-control form-control-sm" type="number" step="0.001" min="0" name="produtos[${i}][peso_kg]" value="0"></div>
        <div class="col-md-1"><label class="form-label small">Qtd</label>
            <input class="form-control form-control-sm" type="number" min="1" name="produtos[${i}][quantidade]" value="1"></div>
        <div class="col-md-1 text-end pt-3"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.produto-row').remove()"><i class="fas fa-trash"></i></button></div>`;
    document.getElementById('listaProdutos').appendChild(div);
    initNcmInput(div.querySelector('.ncm-input'), div.querySelector('.ncm-dropdown'));
});

const NCM_LIST = <?= json_encode(array_map(fn($cod,$desc)=>['cod'=>$cod,'desc'=>$desc], array_keys($ncmOpcoes), array_values($ncmOpcoes))) ?>;
function initNcmInput(input, dropdown) {
    input.addEventListener('input', function() {
        const q=this.value.toLowerCase().trim(); dropdown.innerHTML='';
        if(!q){ dropdown.style.display='none'; return; }
        const matches=NCM_LIST.filter(n=>n.cod.includes(q)||n.desc.toLowerCase().includes(q)).slice(0,15);
        if(!matches.length){ dropdown.style.display='none'; return; }
        matches.forEach(n=>{ const a=document.createElement('button'); a.type='button'; a.className='list-group-item list-group-item-action py-1 px-2 small'; a.textContent=n.cod+' — '+n.desc;
            a.addEventListener('click',()=>{ input.value=n.cod; const row=input.closest('.produto-row'); const d=row?.querySelector('[name*="[descricao]"]'); if(d&&!d.value) d.value=n.desc; dropdown.style.display='none'; }); dropdown.appendChild(a); });
        dropdown.style.display='block';
    });
    document.addEventListener('click', e=>{ if(!input.contains(e.target)&&!dropdown.contains(e.target)) dropdown.style.display='none'; });
}

// ── Gerar envio ─────────────────────────────────────────────────────────────
document.getElementById('btnGerarEnvio').addEventListener('click', async () => {
    if (!validarStep(4)) return;
    const btn=document.getElementById('btnGerarEnvio');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';
    const resp=await fetch('/admin/redirecionamento/envios/salvar',{method:'POST',body:new FormData(document.getElementById('formEnvio'))});
    const json=await resp.json();
    btn.disabled=false; btn.innerHTML='<i class="fas fa-check me-1"></i>Gerar envio e ir para pagamento';
    if(json.ok){ envioId=json.envio_id; valorUsd=json.valor_usd; currentStep=5; showStep(5); }
    else { mostrarErro('Erro: '+(json.msg||'Tente novamente')); }
});

// ── Pagamento Stripe ────────────────────────────────────────────────────────
function setupPagamento() {
    document.getElementById('resumoPagamento').innerHTML=`Envio <strong>#${envioId}</strong> gerado. Valor a pagar: <strong>US$ ${valorUsd.toFixed(2)}</strong>`;
    if(!stripe||!envioId){ document.getElementById('stripeContainer').innerHTML='<div class="alert alert-warning">Pagamento via Stripe não configurado. Envie o comprovante abaixo.</div>'; return; }
    document.getElementById('stripeContainer').innerHTML='<div class="text-muted small mb-2"><i class="fas fa-spinner fa-spin me-1"></i>Carregando formulário de pagamento...</div>';
    fetch('/admin/redirecionamento/pagamento/criar-intent',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'envio_id='+envioId})
    .then(r=>r.json()).then(data=>{
        if(!data.ok){ document.getElementById('stripeContainer').innerHTML='<div class="alert alert-danger">'+(data.msg||'Erro')+'</div>'; return; }
        elements=stripe.elements(); cardElement=elements.create('card',{style:{base:{fontSize:'16px'}}});
        document.getElementById('stripeContainer').innerHTML='<div id="cardElement" class="form-control p-3 mb-3"></div><button type="button" class="btn btn-success w-100" id="btnPagar"><i class="fas fa-lock me-2"></i>Pagar US$ '+valorUsd.toFixed(2)+'</button>';
        cardElement.mount('#cardElement');
        document.getElementById('btnPagar').addEventListener('click',async()=>{
            const btn=document.getElementById('btnPagar'); btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin me-2"></i>Processando...';
            const{paymentIntent,error}=await stripe.confirmCardPayment(data.client_secret,{payment_method:{card:cardElement}});
            if(error){ document.getElementById('msgPagamento').innerHTML='<div class="alert alert-danger">'+error.message+'</div>'; btn.disabled=false; btn.innerHTML='<i class="fas fa-lock me-2"></i>Pagar US$ '+valorUsd.toFixed(2); return; }
            const conf=await fetch('/admin/redirecionamento/pagamento/confirmar',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'envio_id='+envioId+'&payment_intent_id='+paymentIntent.id});
            const cj=await conf.json();
            document.getElementById('msgPagamento').innerHTML=cj.ok?'<div class="alert alert-success"><i class="fas fa-check me-2"></i>Pagamento confirmado! Envie o comprovante abaixo.</div>':'<div class="alert alert-warning">Pagamento processado, aguardando confirmação.</div>';
            btn.style.display='none';
        });
    }).catch(()=>{ document.getElementById('stripeContainer').innerHTML='<div class="alert alert-danger">Erro ao conectar com o Stripe.</div>'; });
}

// ── Upload comprovante ──────────────────────────────────────────────────────
document.getElementById('inputComprovante')?.addEventListener('change', async function() {
    if(!envioId||!this.files[0]) return;
    const fd=new FormData(); fd.append('comprovante',this.files[0]); fd.append('envio_id',envioId); fd.append('tipo','envio');
    const r=await fetch('/admin/redirecionamento/comprovantes/upload',{method:'POST',body:fd});
    const j=await r.json();
    if(j.ok){ const el=document.createElement('div'); el.className='alert alert-success mt-2'; el.innerHTML='<i class="fas fa-check me-2"></i>Comprovante enviado.'; this.parentNode.appendChild(el); }
});

// ── Clientes ────────────────────────────────────────────────────────────────
<?php if ($redirecionadorFixo): ?>
(async()=>{
    const redId = <?= (int)$redirecionadorFixo['id'] ?>;
    const url = '/admin/redirecionamento/clientes/lista' + (redId > 0 ? '?redirecionador_id=' + redId : '');
    const r=await fetch(url);
    const j=await r.json();
    if(j.ok&&j.clientes){ const sel=document.getElementById('selCliente'); j.clientes.forEach(c=>sel.appendChild(new Option(c.nome,c.id))); }
})();
<?php else: ?>
document.getElementById('selRedirecionador')?.addEventListener('change',async function(){
    const sel=document.getElementById('selCliente'); sel.innerHTML='<option value="">— Selecione um cliente —</option>';
    if(!this.value) return;
    const r=await fetch('/admin/redirecionamento/clientes/lista?redirecionador_id='+this.value);
    const j=await r.json(); if(j.ok&&j.clientes) j.clientes.forEach(c=>sel.appendChild(new Option(c.nome,c.id)));
});
<?php endif; ?>

document.getElementById('selCliente')?.addEventListener('change', async function() {
    if(!this.value){
        document.getElementById('destDados').classList.add('d-none');
        document.getElementById('destPlaceholder').classList.remove('d-none');
        document.getElementById('linkEditarCliente').classList.add('d-none');
        document.getElementById('destClienteId').value=''; return;
    }
    const r=await fetch('/admin/redirecionamento/clientes/get?id='+this.value);
    const c=await r.json(); if(!c.id) return;
    const g=id=>document.getElementById(id);
    g('destClienteId').value=c.id; g('destNome').value=c.nome||''; g('destCpf').value=c.cpf||'';
    g('destEmail').value=c.email||''; g('destTel').value=c.telefone||''; g('destNasc').value=c.data_nascimento||'';
    g('destLogr').value=c.logradouro||''; g('destNum').value=c.numero||''; g('destComp').value=c.complemento||'';
    g('destBairro').value=c.bairro||''; g('destCidade').value=c.cidade||''; g('destEstado').value=c.estado||''; g('destCep').value=c.cep||'';
    g('destDados').classList.remove('d-none'); g('destPlaceholder').classList.add('d-none');
    g('linkEditarCliente').href='/admin/redirecionamento/clientes'; g('linkEditarCliente').classList.remove('d-none');
});

// ── Salvar novo cliente ──────────────────────────────────────────────────────
document.getElementById('btnSalvarCliente')?.addEventListener('click', async () => {
    if(!validarFormCliente()) return;
    const redId=RED_FIXO_ID||document.getElementById('selRedirecionador')?.value;
    const msgEl=document.getElementById('msgCliente'); if(msgEl) msgEl.innerHTML='';
    const g=id=>document.getElementById(id);
    const fd=new FormData();
    fd.append('redirecionador_id',redId); fd.append('nome',g('ncNome').value);
    fd.append('cpf',g('ncCpf').value.replace(/\D/g,'')); fd.append('email',g('ncEmail').value);
    fd.append('telefone',g('ncTel').value); fd.append('data_nascimento',g('ncNasc').value);
    fd.append('logradouro',g('ncLogr').value); fd.append('numero',g('ncNum').value);
    fd.append('complemento',g('ncComp').value); fd.append('bairro',g('ncBairro').value);
    fd.append('cidade',g('ncCidade').value); fd.append('estado',g('ncEstado').value);
    fd.append('cep',g('ncCep').value.replace(/\D/g,''));
    const r=await fetch('/admin/redirecionamento/clientes/salvar',{method:'POST',body:fd});
    const j=await r.json();
    if(j.ok){
        document.getElementById('selCliente').appendChild(new Option(j.nome,j.id,true,true));
        bootstrap.Modal.getInstance(document.getElementById('modalNovoCliente')).hide();
        document.getElementById('selCliente').value=j.id;
        document.getElementById('selCliente').dispatchEvent(new Event('change'));
    } else { if(msgEl) msgEl.innerHTML='<div class="alert alert-danger py-1 small">'+(j.msg||'Erro ao salvar')+'</div>'; }
});

showStep(1);
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
