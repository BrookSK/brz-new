<?php
$sidebarActive = 'redirecionamento-clientes';
$title = 'Clientes dos Redirecionadores';
$clientes = is_array($clientes ?? null) ? $clientes : [];
$redirecionadores = is_array($redirecionadores ?? null) ? $redirecionadores : [];
$busca = htmlspecialchars($busca ?? '', ENT_QUOTES, 'UTF-8');
$redirecionadorFixo = $redirecionadorFixo ?? null;
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Clientes dos Redirecionadores</h1>
            <div class="text-muted small"><?= count($clientes) ?> cliente(s)</div>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoCliente"><i class="fas fa-plus me-1"></i>Novo cliente</button>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-8"><input class="form-control form-control-sm" type="text" name="busca" value="<?= $busca ?>" placeholder="Buscar por nome, CPF ou e-mail..."></div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="fas fa-search me-1"></i>Buscar</button>
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/redirecionamento/clientes">Limpar</a>
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
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>E-mail</th>
                            <?php if (!$redirecionadorFixo): ?><th>Redirecionador</th><?php endif; ?>
                            <th>Endereços</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                        <tr><td colspan="<?= $redirecionadorFixo ? 5 : 6 ?>" class="text-center text-muted py-4">Nenhum cliente cadastrado.</td></tr>
                        <?php else: foreach ($clientes as $c): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$c['id'] ?></td>
                            <td><?= htmlspecialchars($c['nome'],ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($c['cpf']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($c['email']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <?php if (!$redirecionadorFixo): ?><td><?= htmlspecialchars($c['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td><?php endif; ?>
                            <td><?= (int)($c['enderecos_count']??0) ?></td>
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
            <div class="modal-header"><h5 class="modal-title">Novo cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <?php if ($redirecionadorFixo): ?>
                    <input type="hidden" id="ncRedId" value="<?= (int)$redirecionadorFixo['id'] ?>">
                    <?php else: ?>
                    <div class="col-md-6">
                        <label class="form-label">Redirecionador <span class="text-danger">*</span></label>
                        <select class="form-select" id="ncRedId">
                            <option value="">Selecione...</option>
                            <?php foreach ($redirecionadores as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome'],ENT_QUOTES,'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="<?= $redirecionadorFixo ? 'col-md-8' : 'col-md-6' ?>"><label class="form-label">Nome <span class="text-danger">*</span></label><input class="form-control" type="text" id="ncNome"></div>
                    <div class="col-md-4">
                        <label class="form-label">CPF <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="ncCpf" placeholder="000.000.000-00" maxlength="14">
                        <div class="invalid-feedback" id="ncCpfFeedback"></div>
                    </div>
                    <div class="col-md-4"><label class="form-label">E-mail</label><input class="form-control" type="email" id="ncEmail"></div>
                    <div class="col-md-4"><label class="form-label">Telefone</label><input class="form-control" type="text" id="ncTel"></div>
                    <div class="col-md-3">
                        <label class="form-label">Data nascimento <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" id="ncNasc">
                        <div class="invalid-feedback" id="ncNascFeedback"></div>
                    </div>
                    <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Endereço principal</small></div>
                    <div class="col-md-3">
                        <label class="form-label">CEP</label>
                        <div class="input-group">
                            <input class="form-control" type="text" id="ncCep" placeholder="00000-000" maxlength="9">
                            <span class="input-group-text" id="ncCepSpinner" style="display:none"><i class="fas fa-spinner fa-spin"></i></span>
                        </div>
                    </div>
                    <div class="col-md-6"><label class="form-label">Logradouro</label><input class="form-control" type="text" id="ncLogr"></div>
                    <div class="col-md-3"><label class="form-label">Número</label><input class="form-control" type="text" id="ncNum"></div>
                    <div class="col-md-3"><label class="form-label">Complemento</label><input class="form-control" type="text" id="ncComp"></div>
                    <div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control" type="text" id="ncBairro"></div>
                    <div class="col-md-3"><label class="form-label">Cidade <span class="text-danger">*</span></label><input class="form-control" type="text" id="ncCidade"></div>
                    <div class="col-md-2"><label class="form-label">Estado</label><input class="form-control" type="text" id="ncEstado" maxlength="2"></div>
                </div>
                <div id="msgCliente" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarCliente">Salvar</button>
            </div>
        </div>
    </div>
</div>
<script src="/js/cliente-form.js"></script>
<script>
const validarFormCliente = initModalNovoCliente({ prefixo: 'nc' });

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
    else { document.getElementById('msgCliente').innerHTML='<div class="alert alert-danger py-1 small">'+(j.msg||'Erro')+'</div>'; }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
