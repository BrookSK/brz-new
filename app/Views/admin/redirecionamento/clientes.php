<?php
$sidebarActive = 'redirecionamento-clientes';
$title = __('admin.redirect.redirector_clients', 'Clientes dos Redirecionadores');
$clientes = is_array($clientes ?? null) ? $clientes : [];
$redirecionadores = is_array($redirecionadores ?? null) ? $redirecionadores : [];
$busca = htmlspecialchars($busca ?? '', ENT_QUOTES, 'UTF-8');
$redirecionadorFixo = $redirecionadorFixo ?? null;
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><?= __('admin.redirect.redirector_clients', 'Clientes dos Redirecionadores') ?></h1>
            <div class="text-muted small"><?= count($clientes) ?> <?= __('admin.redirect.clients_count_suffix', 'cliente(s)') ?></div>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoCliente"><i class="fas fa-plus me-1"></i><?= __('admin.redirect.new_client', 'Novo cliente') ?></button>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-8"><input class="form-control form-control-sm" type="text" name="busca" value="<?= $busca ?>" placeholder="<?= htmlspecialchars(__('admin.redirect.search_name_cpf_email', 'Buscar por nome, CPF ou e-mail...'), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="fas fa-search me-1"></i><?= __('admin.redirect.search', 'Buscar') ?></button>
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/redirecionamento/clientes"><?= __('admin.redirect.clear', 'Limpar') ?></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th><?= __('admin.redirect.name', 'Nome') ?></th>
                            <th>CPF</th>
                            <th><?= __('admin.redirect.email', 'E-mail') ?></th>
                            <?php if (!$redirecionadorFixo): ?><th><?= __('admin.redirect.redirector', 'Redirecionador') ?></th><?php endif; ?>
                            <th><?= __('admin.redirect.addresses', 'Endereços') ?></th>
                            <th class="pe-3"><?= __('admin.redirect.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                        <tr><td colspan="<?= $redirecionadorFixo ? 6 : 7 ?>" class="text-center text-muted py-4"><?= __('admin.redirect.no_clients_registered', 'Nenhum cliente cadastrado.') ?></td></tr>
                        <?php else: foreach ($clientes as $c): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$c['id'] ?></td>
                            <td><?= htmlspecialchars($c['nome'],ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($c['cpf']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($c['email']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <?php if (!$redirecionadorFixo): ?><td><?= htmlspecialchars($c['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td><?php endif; ?>
                            <td><?= (int)($c['enderecos_count']??0) ?></td>
                            <td class="pe-3">
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary js-editar-cliente"
                                        data-id="<?= (int)$c['id'] ?>"
                                        data-nome="<?= htmlspecialchars($c['nome']??'',ENT_QUOTES,'UTF-8') ?>"
                                        data-cpf="<?= htmlspecialchars($c['cpf']??'',ENT_QUOTES,'UTF-8') ?>"
                                        data-email="<?= htmlspecialchars($c['email']??'',ENT_QUOTES,'UTF-8') ?>"
                                        data-telefone="<?= htmlspecialchars($c['telefone']??'',ENT_QUOTES,'UTF-8') ?>"
                                        data-nascimento="<?= htmlspecialchars($c['data_nascimento']??'',ENT_QUOTES,'UTF-8') ?>"
                                        title="<?= htmlspecialchars(__('admin.redirect.edit', 'Editar'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-pen"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-excluir-cliente"
                                        data-id="<?= (int)$c['id'] ?>"
                                        data-nome="<?= htmlspecialchars($c['nome']??'',ENT_QUOTES,'UTF-8') ?>"
                                        title="<?= htmlspecialchars(__('admin.redirect.delete', 'Excluir'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal novo cliente -->
<div class="modal fade" id="modalNovoCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= __('admin.redirect.new_client', 'Novo cliente') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <?php if ($redirecionadorFixo): ?>
                    <input type="hidden" id="ncRedId" value="<?= (int)$redirecionadorFixo['id'] ?>">
                    <?php else: ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= __('admin.redirect.redirector', 'Redirecionador') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="ncRedId">
                            <option value=""><?= __('admin.redirect.select', 'Selecione...') ?></option>
                            <?php foreach ($redirecionadores as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome'],ENT_QUOTES,'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="<?= $redirecionadorFixo ? 'col-md-8' : 'col-md-6' ?>"><label class="form-label"><?= __('admin.redirect.name', 'Nome') ?> <span class="text-danger">*</span></label><input class="form-control" type="text" id="ncNome"></div>
                    <div class="col-md-4">
                        <label class="form-label">CPF <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="ncCpf" placeholder="000.000.000-00" maxlength="14">
                        <div class="invalid-feedback" id="ncCpfFeedback"></div>
                    </div>
                    <div class="col-md-4"><label class="form-label"><?= __('admin.redirect.email', 'E-mail') ?></label><input class="form-control" type="email" id="ncEmail"></div>
                    <div class="col-md-4"><label class="form-label"><?= __('admin.redirect.phone', 'Telefone') ?></label><input class="form-control" type="text" id="ncTel"></div>
                    <div class="col-md-3">
                        <label class="form-label"><?= __('admin.redirect.birth_date_short', 'Data nascimento') ?> <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" id="ncNasc">
                        <div class="invalid-feedback" id="ncNascFeedback"></div>
                    </div>
                    <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold"><?= __('admin.redirect.main_address', 'Endereço principal') ?></small></div>
                    <div class="col-md-3">
                        <label class="form-label">CEP</label>
                        <div class="input-group">
                            <input class="form-control" type="text" id="ncCep" placeholder="00000-000" maxlength="9">
                            <span class="input-group-text" id="ncCepSpinner" style="display:none"><i class="fas fa-spinner fa-spin"></i></span>
                        </div>
                    </div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.street', 'Logradouro') ?></label><input class="form-control" type="text" id="ncLogr"></div>
                    <div class="col-md-3"><label class="form-label"><?= __('admin.redirect.number', 'Número') ?></label><input class="form-control" type="text" id="ncNum"></div>
                    <div class="col-md-3"><label class="form-label"><?= __('admin.redirect.complement', 'Complemento') ?></label><input class="form-control" type="text" id="ncComp"></div>
                    <div class="col-md-4"><label class="form-label"><?= __('admin.redirect.neighborhood', 'Bairro') ?></label><input class="form-control" type="text" id="ncBairro"></div>
                    <div class="col-md-3"><label class="form-label"><?= __('admin.redirect.city', 'Cidade') ?> <span class="text-danger">*</span></label><input class="form-control" type="text" id="ncCidade"></div>
                    <div class="col-md-2"><label class="form-label"><?= __('admin.redirect.state', 'Estado') ?></label><input class="form-control" type="text" id="ncEstado" maxlength="2"></div>
                </div>
                <div id="msgCliente" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('admin.redirect.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" id="btnSalvarCliente"><?= __('admin.redirect.save', 'Salvar') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
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

// Máscara CPF
const ncCpf = document.getElementById('ncCpf');
if(ncCpf){
    ncCpf.addEventListener('input', function(){ this.value=mascaraCPF(this.value); });
    ncCpf.addEventListener('blur', function(){
        const cpf=this.value.replace(/\D/g,''), fb=document.getElementById('ncCpfFeedback');
        if(cpf && !validarCPF(cpf)){ this.classList.add('is-invalid'); if(fb) fb.textContent='<?= htmlspecialchars(__('admin.redirect.cpf_invalid', 'CPF inválido.'), ENT_QUOTES, 'UTF-8') ?>'; }
        else { this.classList.remove('is-invalid'); if(fb) fb.textContent=''; }
    });
}

// Validação data nascimento
const ncNasc = document.getElementById('ncNasc');
if(ncNasc){
    ncNasc.addEventListener('change', function(){
        const fb=document.getElementById('ncNascFeedback');
        if(this.value && !validarIdade18(this.value)){ this.classList.add('is-invalid'); if(fb) fb.textContent='<?= htmlspecialchars(__('admin.redirect.client_min_18', 'Cliente deve ter pelo menos 18 anos.'), ENT_QUOTES, 'UTF-8') ?>'; }
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
    if(!cpf){ cpfEl?.classList.add('is-invalid'); if(fb) fb.textContent='<?= htmlspecialchars(__('admin.redirect.cpf_required', 'CPF obrigatório.'), ENT_QUOTES, 'UTF-8') ?>'; ok=false; }
    else if(!validarCPF(cpf)){ cpfEl?.classList.add('is-invalid'); if(fb) fb.textContent='<?= htmlspecialchars(__('admin.redirect.cpf_invalid', 'CPF inválido.'), ENT_QUOTES, 'UTF-8') ?>'; ok=false; }
    else { cpfEl?.classList.remove('is-invalid'); if(fb) fb.textContent=''; }
    const nascEl=g('ncNasc'), fb2=g('ncNascFeedback');
    if(!nascEl?.value){ nascEl?.classList.add('is-invalid'); if(fb2) fb2.textContent='<?= htmlspecialchars(__('admin.redirect.date_required', 'Data obrigatória.'), ENT_QUOTES, 'UTF-8') ?>'; ok=false; }
    else if(!validarIdade18(nascEl.value)){ nascEl?.classList.add('is-invalid'); if(fb2) fb2.textContent='<?= htmlspecialchars(__('admin.redirect.client_min_18', 'Cliente deve ter pelo menos 18 anos.'), ENT_QUOTES, 'UTF-8') ?>'; ok=false; }
    else { nascEl?.classList.remove('is-invalid'); if(fb2) fb2.textContent=''; }
    const cidade=g('ncCidade'); if(!cidade?.value.trim()){ cidade?.classList.add('is-invalid'); ok=false; } else cidade?.classList.remove('is-invalid');
    return ok;
}

document.getElementById('btnSalvarCliente')?.addEventListener('click', async () => {
    if (!validarFormCliente()) return;
    const fd = new FormData();
    fd.append('redirecionador_id', document.getElementById('ncRedId').value);
    fd.append('nome', document.getElementById('ncNome').value);
    fd.append('cpf', document.getElementById('ncCpf').value.replace(/\D/g,''));
    fd.append('email', document.getElementById('ncEmail').value);
    fd.append('telefone', document.getElementById('ncTel').value);
    fd.append('data_nascimento', document.getElementById('ncNasc').value);
    fd.append('logradouro', document.getElementById('ncLogr').value);
    fd.append('numero', document.getElementById('ncNum').value);
    fd.append('complemento', document.getElementById('ncComp').value);
    fd.append('bairro', document.getElementById('ncBairro').value);
    fd.append('cidade', document.getElementById('ncCidade').value);
    fd.append('estado', document.getElementById('ncEstado').value);
    fd.append('cep', document.getElementById('ncCep').value.replace(/\D/g,''));
    const r = await fetch('/admin/redirecionamento/clientes/salvar',{method:'POST',body:fd});
    const j = await r.json();
    if (j.ok) { location.reload(); }
    else { document.getElementById('msgCliente').innerHTML='<div class="alert alert-danger py-1 small">'+(j.msg||'<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>')+'</div>'; }
});

// ── Editar cliente ──
document.querySelectorAll('.js-editar-cliente').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        document.getElementById('editClienteId').value = id;
        document.getElementById('editNome').value = this.dataset.nome || '';
        document.getElementById('editCpf').value = this.dataset.cpf || '';
        document.getElementById('editEmail').value = this.dataset.email || '';
        document.getElementById('editTelefone').value = this.dataset.telefone || '';
        document.getElementById('editNasc').value = this.dataset.nascimento || '';
        document.getElementById('editMsg').innerHTML = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarCliente')).show();
    });
});

document.getElementById('btnSalvarEdicao')?.addEventListener('click', async () => {
    const id = document.getElementById('editClienteId').value;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('nome', document.getElementById('editNome').value);
    fd.append('cpf', document.getElementById('editCpf').value);
    fd.append('email', document.getElementById('editEmail').value);
    fd.append('telefone', document.getElementById('editTelefone').value);
    fd.append('data_nascimento', document.getElementById('editNasc').value);
    const r = await fetch('/admin/redirecionamento/clientes/atualizar', {method:'POST', body:fd});
    const j = await r.json();
    if (j.ok) { location.reload(); }
    else { document.getElementById('editMsg').innerHTML = '<div class="alert alert-danger py-1 small">'+(j.msg||'<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>')+'</div>'; }
});

// ── Excluir cliente ──
document.querySelectorAll('.js-excluir-cliente').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const nome = this.dataset.nome;
        if (!confirm('<?= htmlspecialchars(__('admin.redirect.delete_client_q', 'Excluir o cliente'), ENT_QUOTES, 'UTF-8') ?> "' + nome + '"? <?= htmlspecialchars(__('admin.redirect.action_cannot_be_undone', 'Esta ação não pode ser desfeita.'), ENT_QUOTES, 'UTF-8') ?>')) return;
        const fd = new FormData();
        fd.append('id', id);
        const r = await fetch('/admin/redirecionamento/clientes/excluir', {method:'POST', body:fd});
        const j = await r.json();
        if (j.ok) { location.reload(); }
        else { alert(j.msg || '<?= htmlspecialchars(__('admin.redirect.error_deleting', 'Erro ao excluir'), ENT_QUOTES, 'UTF-8') ?>'); }
    });
});
</script>

<!-- Modal editar cliente -->
<div class="modal fade" id="modalEditarCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= __('admin.redirect.edit_client', 'Editar cliente') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="editClienteId">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label"><?= __('admin.redirect.name', 'Nome') ?></label><input class="form-control" type="text" id="editNome"></div>
                    <div class="col-md-6"><label class="form-label">CPF</label><input class="form-control" type="text" id="editCpf"></div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.birth_date_short', 'Data nascimento') ?></label><input class="form-control" type="date" id="editNasc"></div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.email', 'E-mail') ?></label><input class="form-control" type="email" id="editEmail"></div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.phone', 'Telefone') ?></label><input class="form-control" type="text" id="editTelefone"></div>
                </div>
                <div id="editMsg" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('admin.redirect.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" id="btnSalvarEdicao"><?= __('admin.redirect.save', 'Salvar') ?></button>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
