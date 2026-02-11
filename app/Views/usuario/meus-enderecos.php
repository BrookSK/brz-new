<?php
ob_start();
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1">Endereços</h1>
            <div class="text-muted small">Gerencie seus endereços de entrega.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary" id="btn-novo-endereco">
                <i class="fas fa-plus me-2"></i> Novo endereço
            </button>
        </div>
    </div>

    <div class="row g-4">
        <?php $activePage = 'enderecos'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>

        <div class="col-lg-9">
            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-<?= htmlspecialchars((string) ($_SESSION['message_type'] ?? 'info')) ?>">
                    <?= htmlspecialchars((string) ($_SESSION['message'] ?? '')) ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i> Seus endereços</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($enderecos)): ?>
                        <div class="text-muted">Você ainda não possui endereços cadastrados.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Endereço</th>
                                        <th>País</th>
                                        <th>Principal</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enderecos as $e): ?>
                                        <?php
                                            $pais = strtoupper((string) ($e['pais'] ?? 'BR'));
                                            $linha1 = trim((string) ($e['endereco'] ?? ($e['logradouro'] ?? '')));
                                            $num = trim((string) ($e['numero'] ?? ''));
                                            if ($linha1 !== '' && $num !== '') {
                                                $linha1 .= ', ' . $num;
                                            }
                                            $cidade = trim((string) ($e['cidade'] ?? ''));
                                            $estado = trim((string) ($e['estado'] ?? ($e['uf'] ?? '')));
                                            $cep = trim((string) ($e['cep'] ?? ''));
                                            $principal = !empty($e['principal']) || !empty($e['is_principal']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($linha1 !== '' ? $linha1 : '—') ?></div>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars($cidade) ?><?= ($estado !== '' ? (' / ' . htmlspecialchars($estado)) : '') ?>
                                                    <?= ($cep !== '' ? (' • ' . htmlspecialchars($cep)) : '') ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($pais) ?></td>
                                            <td>
                                                <?php if ($principal): ?>
                                                    <span class="badge" style="background: rgba(16,185,129,0.10); border: 1px solid rgba(16,185,129,0.18); color: rgba(6,78,59,1);">Principal</span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary btn-editar"
                                                    data-id="<?= (int) ($e['id'] ?? 0) ?>"
                                                    data-tipo="<?= htmlspecialchars((string) ($e['tipo'] ?? 'entrega')) ?>"
                                                    data-pais="<?= htmlspecialchars((string) ($e['pais'] ?? 'BR')) ?>"
                                                    data-cep="<?= htmlspecialchars((string) ($e['cep'] ?? '')) ?>"
                                                    data-endereco="<?= htmlspecialchars((string) ($e['endereco'] ?? ($e['logradouro'] ?? ''))) ?>"
                                                    data-numero="<?= htmlspecialchars((string) ($e['numero'] ?? '')) ?>"
                                                    data-complemento="<?= htmlspecialchars((string) ($e['complemento'] ?? '')) ?>"
                                                    data-bairro="<?= htmlspecialchars((string) ($e['bairro'] ?? '')) ?>"
                                                    data-cidade="<?= htmlspecialchars((string) ($e['cidade'] ?? '')) ?>"
                                                    data-estado="<?= htmlspecialchars((string) ($e['estado'] ?? ($e['uf'] ?? ''))) ?>"
                                                    data-principal="<?= $principal ? '1' : '0' ?>"
                                                >
                                                    Editar
                                                </button>
                                                <?php if (!$principal): ?>
                                                    <form method="POST" action="/meus-enderecos/principal/<?= (int) ($e['id'] ?? 0) ?>" class="d-inline">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">Tornar principal</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" action="/meus-enderecos/excluir/<?= (int) ($e['id'] ?? 0) ?>" class="d-inline" onsubmit="return confirm('Excluir este endereço?');">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm" id="endereco-editor" style="display:none;">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i> <span id="editor-title">Novo endereço</span></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/meus-enderecos/salvar" id="endereco-form">
                        <input type="hidden" name="id" id="endereco_id" value="">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">País / Country *</label>
                                <?php require __DIR__ . '/../_countries.php'; ?>
                                <select class="form-select" name="pais" id="pais" required>
                                    <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control mt-2" id="pais_search" placeholder="Digite para filtrar países...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" id="label-cep">CEP / ZIP Code *</label>
                                <input type="text" class="form-control" name="cep" id="cep" required maxlength="12" value="">
                            </div>
                            <div class="col-md-9">
                                <label class="form-label" id="label-endereco">Rua / Street *</label>
                                <input type="text" class="form-control" name="endereco" id="endereco" required value="">
                            </div>
                            <div class="col-md-3" id="numero-wrap">
                                <label class="form-label" id="label-numero">Número / Number *</label>
                                <input type="text" class="form-control" name="numero" id="numero" value="">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" id="label-complemento">Complemento / Complement</label>
                                <input type="text" class="form-control" name="complemento" id="complemento" value="">
                            </div>
                            <div class="col-md-3" id="bairro-wrap">
                                <label class="form-label" id="label-bairro">Bairro / District</label>
                                <input type="text" class="form-control" name="bairro" id="bairro" value="">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cidade / City *</label>
                                <input type="text" class="form-control" name="cidade" id="cidade" required value="">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" id="label-estado">Estado / State</label>
                                <select class="form-select" name="estado" id="estado">
                                    <option value="">Selecione...</option>
                                </select>
                                <input type="text" class="form-control" id="estado_text" name="estado_text" style="display:none;" value="">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="principal" name="principal">
                                    <label class="form-check-label" for="principal">Definir como principal</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">Salvar</button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-cancelar">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function(){
    function filterSelectOptions(selectEl, query) {
        if (!selectEl) return;
        query = (query || '').toString().trim().toLowerCase();
        var opts = selectEl.querySelectorAll('option');
        for (var i = 0; i < opts.length; i++) {
            var o = opts[i];
            var txt = (o.textContent || '').toString().toLowerCase();
            var val = (o.value || '').toString().toLowerCase();
            var match = (query === '') || (txt.indexOf(query) !== -1) || (val.indexOf(query) !== -1);
            o.style.display = match ? '' : 'none';
        }
    }

    function atualizarEnderecoPorPais() {
        var pais = (document.getElementById('pais')?.value || 'BR').toUpperCase();
        var cep = document.getElementById('cep');
        var enderecoLabel = document.getElementById('label-endereco');
        var numeroWrap = document.getElementById('numero-wrap');
        var numeroInput = document.getElementById('numero');
        var numeroLabel = document.getElementById('label-numero');
        var compLabel = document.getElementById('label-complemento');
        var bairroWrap = document.getElementById('bairro-wrap');
        var bairroInput = document.getElementById('bairro');

        var estadoSelect = document.getElementById('estado');
        var estadoText = document.getElementById('estado_text');

        var statesByCountry = {
            BR: ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'],
            US: ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'],
            CA: ['AB','BC','MB','NB','NL','NS','NT','NU','ON','PE','QC','SK','YT']
        };

        if (cep) {
            if (pais === 'BR') { cep.placeholder = '00000-000'; cep.maxLength = 9; }
            else if (pais === 'US') { cep.placeholder = '00000'; cep.maxLength = 10; }
            else { cep.placeholder = ''; cep.maxLength = 12; }
        }

        if (enderecoLabel) {
            enderecoLabel.textContent = (pais === 'BR') ? 'Rua / Street *' : 'Address line 1 *';
        }
        if (compLabel) {
            compLabel.textContent = (pais === 'BR') ? 'Complemento / Complement' : 'Address line 2 (optional)';
        }

        if (numeroWrap && numeroInput && numeroLabel) {
            if (pais === 'BR') {
                numeroWrap.style.display = '';
                numeroInput.required = true;
                numeroLabel.textContent = 'Número / Number *';
            } else {
                numeroWrap.style.display = 'none';
                numeroInput.required = false;
                numeroInput.value = '';
            }
        }

        if (bairroWrap && bairroInput) {
            if (pais === 'BR') {
                bairroWrap.style.display = '';
                bairroInput.required = true;
            } else {
                bairroWrap.style.display = 'none';
                bairroInput.required = false;
                bairroInput.value = '';
            }
        }

        if (estadoSelect && estadoText) {
            var list = statesByCountry[pais] || null;
            var shouldUseSelect = Array.isArray(list) && list.length > 0;
            var estadoRequired = (pais === 'BR' || pais === 'US' || pais === 'CA');

            if (shouldUseSelect) {
                var current = String(estadoSelect.value || estadoText.value || '').trim();
                while (estadoSelect.options.length > 0) estadoSelect.remove(0);
                var optEmpty = document.createElement('option');
                optEmpty.value = ''; optEmpty.textContent = 'Selecione...';
                estadoSelect.appendChild(optEmpty);
                list.forEach(function(uf){
                    var opt = document.createElement('option');
                    opt.value = uf; opt.textContent = uf;
                    if (current && uf === current.toUpperCase()) opt.selected = true;
                    estadoSelect.appendChild(opt);
                });

                estadoSelect.style.display = '';
                estadoText.style.display = 'none';

                estadoSelect.name = 'estado';
                estadoSelect.required = estadoRequired;
                estadoSelect.disabled = false;

                estadoText.name = 'estado_text';
                estadoText.required = false;
                estadoText.disabled = true;
            } else {
                estadoSelect.style.display = 'none';
                estadoText.style.display = '';

                estadoSelect.name = 'estado_ui';
                estadoSelect.required = false;
                estadoSelect.disabled = true;

                estadoText.name = 'estado';
                estadoText.required = estadoRequired;
                estadoText.disabled = false;
            }
        }
    }

    function openEditor(title) {
        var box = document.getElementById('endereco-editor');
        var t = document.getElementById('editor-title');
        if (t) t.textContent = title || 'Novo endereço';
        if (box) box.style.display = 'block';
        atualizarEnderecoPorPais();
        box?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function clearEditor() {
        document.getElementById('endereco_id').value = '';
        document.getElementById('tipo')?.value;
        document.getElementById('cep').value = '';
        document.getElementById('endereco').value = '';
        document.getElementById('numero').value = '';
        document.getElementById('complemento').value = '';
        document.getElementById('bairro').value = '';
        document.getElementById('cidade').value = '';
        document.getElementById('estado').value = '';
        document.getElementById('estado_text').value = '';
        document.getElementById('principal').checked = false;
        var paisSel = document.getElementById('pais');
        if (paisSel) paisSel.value = 'BR';
        atualizarEnderecoPorPais();
    }

    document.addEventListener('DOMContentLoaded', function(){
        var paisSel = document.getElementById('pais');
        var paisSearch = document.getElementById('pais_search');
        if (paisSel) paisSel.addEventListener('change', atualizarEnderecoPorPais);
        if (paisSearch) paisSearch.addEventListener('input', function(){ filterSelectOptions(paisSel, paisSearch.value); });

        var btnNovo = document.getElementById('btn-novo-endereco');
        if (btnNovo) {
            btnNovo.addEventListener('click', function(){
                clearEditor();
                openEditor('Novo endereço');
            });
        }

        var btnCancel = document.getElementById('btn-cancelar');
        if (btnCancel) {
            btnCancel.addEventListener('click', function(){
                var box = document.getElementById('endereco-editor');
                if (box) box.style.display = 'none';
            });
        }

        document.querySelectorAll('.btn-editar').forEach(function(btn){
            btn.addEventListener('click', function(){
                clearEditor();
                document.getElementById('endereco_id').value = btn.getAttribute('data-id') || '';
                var pais = (btn.getAttribute('data-pais') || 'BR').toUpperCase();
                document.getElementById('pais').value = pais;
                document.getElementById('cep').value = btn.getAttribute('data-cep') || '';
                document.getElementById('endereco').value = btn.getAttribute('data-endereco') || '';
                document.getElementById('numero').value = btn.getAttribute('data-numero') || '';
                document.getElementById('complemento').value = btn.getAttribute('data-complemento') || '';
                document.getElementById('bairro').value = btn.getAttribute('data-bairro') || '';
                document.getElementById('cidade').value = btn.getAttribute('data-cidade') || '';
                document.getElementById('estado_text').value = btn.getAttribute('data-estado') || '';
                document.getElementById('principal').checked = (btn.getAttribute('data-principal') === '1');
                atualizarEnderecoPorPais();
                var stSel = document.getElementById('estado');
                if (stSel && stSel.style.display !== 'none') {
                    stSel.value = (btn.getAttribute('data-estado') || '').toUpperCase();
                }
                openEditor('Editar endereço');
            });
        });

        atualizarEnderecoPorPais();
    });
})();
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
