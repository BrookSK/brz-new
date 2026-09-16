<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><?= __('admin.correios_mundial.new_container_title','Novo container (Unitizador) - Correios Mundial (PACKET)') ?></h1>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial/containers"><?= __('admin.correios_mundial.back','Voltar') ?></a>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/correios-mundial/containers/criar" class="card border-0 shadow-sm">
        <div class="card-header"><strong><?= __('admin.correios_mundial.container_data','Dados do container') ?></strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.correios_mundial.dispatch_number_label','Número da remessa (dispatchNumber)') ?></label>
                    <input type="number" class="form-control" name="dispatchNumber" value="<?= htmlspecialchars((string) ($defaults['dispatchNumber'] ?? '')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.correios_mundial.origin_country_label','País de origem') ?></label>
                    <input type="text" class="form-control" name="originCountry" value="US" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.correios_mundial.origin_operator_label','Operador origem') ?></label>
                    <input type="text" class="form-control" name="originOperatorName" value="<?= htmlspecialchars((string) ($defaults['originOperatorName'] ?? 'BRAS')) ?>" maxlength="4" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.correios_mundial.destination_operator_label','Operador destino') ?></label>
                    <?php $dop = (string) ($defaults['destinationOperatorName'] ?? 'SAOD'); ?>
                    <select class="form-select" name="destinationOperatorName" required>
                        <option value="SAOD" <?= $dop === 'SAOD' ? 'selected' : '' ?>>SAOD - Guarulhos</option>
                        <option value="CWBA" <?= $dop === 'CWBA' ? 'selected' : '' ?>>CWBA - Curitiba</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label"><?= __('admin.correios_mundial.postal_category_label','Categoria postal') ?></label>
                    <?php $pcc = (string) ($defaults['postalCategoryCode'] ?? 'A'); ?>
                    <select class="form-select" name="postalCategoryCode" required>
                        <option value="A" <?= $pcc === 'A' ? 'selected' : '' ?>><?= __('admin.correios_mundial.postal_cat_a','A – Airmail ou Priority Mail') ?></option>
                        <option value="B" <?= $pcc === 'B' ? 'selected' : '' ?>><?= __('admin.correios_mundial.postal_cat_b','B – S.A.L Mail ou Non-Priority Mail') ?></option>
                        <option value="C" <?= $pcc === 'C' ? 'selected' : '' ?>><?= __('admin.correios_mundial.postal_cat_c','C – Surface Mail ou Non-Priority Mail') ?></option>
                        <option value="D" <?= $pcc === 'D' ? 'selected' : '' ?>><?= __('admin.correios_mundial.postal_cat_d','D – Priority Mail (terrestre)') ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('admin.correios_mundial.service_subclass_label','Subclasse serviço') ?></label>
                    <select class="form-select" name="serviceSubclassCode" required>
                        <?php $ssc = (string) ($defaults['serviceSubclassCode'] ?? 'NX'); ?>
                        <option value="NX" <?= $ssc === 'NX' ? 'selected' : '' ?>><?= __('admin.correios_mundial.subclass_nx','NX (padrão)') ?></option>
                        <option value="IX" <?= $ssc === 'IX' ? 'selected' : '' ?>><?= __('admin.correios_mundial.subclass_ix','IX (expresso)') ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('admin.correios_mundial.unit_type_label','Tipo unidade') ?></label>
                    <select class="form-select" name="unitType" required>
                        <?php $ut = (string) ($defaults['unitType'] ?? '2'); ?>
                        <option value="1" <?= $ut === '1' ? 'selected' : '' ?>><?= __('admin.correios_mundial.unit_type_1_bag','1 (saco até 30kg)') ?></option>
                        <option value="2" <?= $ut === '2' ? 'selected' : '' ?>><?= __('admin.correios_mundial.unit_type_2_pallet','2 (pallet até 500kg)') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.correios_mundial.awb_label','AWB (nº do voo)') ?></label>
                    <input type="text" class="form-control" name="awb" value="<?= htmlspecialchars((string) ($defaults['awb'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.correios_mundial.triage_group_label','Grupo de triagem') ?></label>
                    <?php $tg = (string) ($defaults['triageGroup'] ?? '1'); ?>
                    <select class="form-select" name="triageGroup" required>
                        <option value="" <?= $tg === '' ? 'selected' : '' ?>><?= __('admin.correios_mundial.select_group','Selecione o grupo') ?></option>
                        <option value="1" <?= $tg === '1' ? 'selected' : '' ?>>1 - São Paulo/SP</option>
                        <option value="2" <?= $tg === '2' ? 'selected' : '' ?>>2 - Valinhos/SP</option>
                        <option value="3" <?= $tg === '3' ? 'selected' : '' ?>>3 - Rio de Janeiro/RJ</option>
                        <option value="4" <?= $tg === '4' ? 'selected' : '' ?>>4 - Curitiba/PR</option>
                        <option value="5" <?= $tg === '5' ? 'selected' : '' ?>>5 - Curitiba/PR</option>
                    </select>
                </div>
            </div>

            <hr>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label"><?= __('admin.correios_mundial.paste_list_label','Colar lista (pedidos ou tracking)') ?></label>
                    <textarea class="form-control" name="bulk" id="cm_bulk" rows="5" placeholder="<?= htmlspecialchars(__('admin.correios_mundial.paste_placeholder','Cole aqui: ex. #12345\n12345\nNC000005113BR\n...'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($defaults['bulk'] ?? '')) ?></textarea>
                    <div class="form-text">
                        <?= __('admin.correios_mundial.paste_help','Você pode colar IDs de pedido (números) e/ou trackingNumbers. O sistema tenta resolver e seleciona automaticamente.') ?>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="cm_bulk_select"><?= __('admin.correios_mundial.select','Selecionar') ?></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cm_bulk_clear"><?= __('admin.correios_mundial.clear_selection','Limpar seleção') ?></button>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label"><?= __('admin.correios_mundial.available_packages','Pacotes disponíveis') ?></label>
                    <select class="form-select" name="trackingNumbers[]" multiple size="12" required>
                        <?php $available = isset($availablePackages) && is_array($availablePackages) ? $availablePackages : []; ?>
                        <?php $pre = isset($preselected) && is_array($preselected) ? $preselected : []; ?>
                        <?php if (empty($available)): ?>
                            <option value=""><?= __('admin.correios_mundial.no_packages_available','Nenhum pacote disponível') ?></option>
                        <?php else: ?>
                            <?php foreach ($available as $p): ?>
                                <?php $trk = (string) ($p['tracking_number'] ?? ''); ?>
                                <?php $pid = (int) ($p['pedido_id'] ?? 0); ?>
                                <?php if ($trk === '') continue; ?>
                                <option value="<?= htmlspecialchars($trk) ?>" data-pedido-id="<?= (int) $pid ?>" data-tracking="<?= htmlspecialchars(strtoupper($trk)) ?>" <?= in_array($trk, $pre, true) ? 'selected' : '' ?>><?= __('admin.correios_mundial.order','Pedido') ?> #<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($trk) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="form-text"><?= __('admin.correios_mundial.multi_select_help','Use Ctrl/Shift para selecionar múltiplos.') ?></div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><strong><?= __('admin.correios_mundial.quick_selection_summary','Resumo seleção rápida') ?></strong></div>
                        <div class="card-body">
                            <div class="small text-muted"><?= __('admin.correios_mundial.total_informed','Total informado') ?></div>
                            <div class="h5 mb-2" id="cm_bulk_total">0</div>
                            <div class="small text-muted"><?= __('admin.correios_mundial.found_selected','Encontrados/selecionados') ?></div>
                            <div class="h5 mb-0" id="cm_bulk_found">0</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><strong><?= __('admin.correios_mundial.found','Encontrados') ?></strong></div>
                        <div class="card-body">
                            <pre id="cm_bulk_found_list" style="margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><strong><?= __('admin.correios_mundial.not_found','Não encontrados') ?></strong></div>
                        <div class="card-body">
                            <pre id="cm_bulk_not_found_list" style="margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($bulkResult) && is_array($bulkResult)): ?>
                <hr>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><strong><?= __('admin.correios_mundial.found','Encontrados') ?></strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['found'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><strong><?= __('admin.correios_mundial.not_found','Não encontrados') ?></strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['not_found'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><strong><?= __('admin.correios_mundial.already_used','Já usados em container') ?></strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['already_used'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary" type="submit"><?= __('admin.correios_mundial.create_container','Criar container') ?></button>
        </div>
    </form>
</div>

<script>
(function(){
    function tokenize(raw){
        raw = (raw || '').toString().trim();
        if(!raw) return [];
        raw = raw.replace(/\r\n/g,'\n').replace(/\r/g,'\n');
        const parts = raw.split(/[\n\t\s,;]+/g).map(s => s.trim()).filter(Boolean);
        const uniq = [];
        const seen = new Set();
        for(const p of parts){
            if(!seen.has(p)){
                seen.add(p);
                uniq.push(p);
            }
        }
        return uniq;
    }

    function normToken(t){
        t = (t || '').toString().trim();
        if(!t) return '';
        // aceita "#123", "Pedido #123", etc.
        t = t.replace(/^pedido\s*/i,'');
        t = t.replace(/^#/, '');
        t = t.replace(/^\D*(\d+)\D*$/, '$1');
        return t.trim();
    }

    function isNumeric(s){
        return /^[0-9]+$/.test(s);
    }

    function setText(id, v){
        const el = document.getElementById(id);
        if(el) el.textContent = (v === undefined || v === null) ? '' : v;
    }

    function setPre(id, lines){
        const el = document.getElementById(id);
        if(!el) return;
        if(!lines || !lines.length){
            el.textContent = '';
            return;
        }
        el.textContent = lines.join('\n');
    }

    function buildMaps(selectEl){
        const byPedido = new Map();
        const byTracking = new Map();
        const opts = Array.from(selectEl.options || []);
        for(const o of opts){
            const pid = o.getAttribute('data-pedido-id');
            const trk = (o.getAttribute('data-tracking') || '').toUpperCase();
            if(pid) byPedido.set(pid.toString(), o);
            if(trk) byTracking.set(trk, o);
        }
        return {byPedido, byTracking};
    }

    function findSelect(){
        return document.querySelector('select[name="trackingNumbers[]"]');
    }

    function applyBulk(){
        const bulk = document.getElementById('cm_bulk');
        const select = findSelect();
        if(!bulk || !select) return;

        const tokens = tokenize(bulk.value);
        const {byPedido, byTracking} = buildMaps(select);

        const found = [];
        const notFound = [];

        for(const raw of tokens){
            const trimmed = (raw || '').toString().trim();
            if(!trimmed) continue;

            const upper = trimmed.toUpperCase();
            const normalized = normToken(trimmed);

            let opt = null;
            if(isNumeric(normalized)){
                opt = byPedido.get(normalized);
            }
            if(!opt){
                opt = byTracking.get(upper);
            }

            if(opt){
                opt.selected = true;
                const pid = opt.getAttribute('data-pedido-id') || '';
                const trk = opt.getAttribute('data-tracking') || opt.value;
                found.push((pid ? ('#' + pid + ' -> ') : '') + trk);
            } else {
                notFound.push(trimmed);
            }
        }

        setText('cm_bulk_total', tokens.length);
        setText('cm_bulk_found', found.length);
        setPre('cm_bulk_found_list', found);
        setPre('cm_bulk_not_found_list', notFound);
    }

    function clearSelection(){
        const select = findSelect();
        if(!select) return;
        for(const o of Array.from(select.options || [])){
            o.selected = false;
        }
        setText('cm_bulk_total', 0);
        setText('cm_bulk_found', 0);
        setPre('cm_bulk_found_list', []);
        setPre('cm_bulk_not_found_list', []);
    }

    document.addEventListener('DOMContentLoaded', function(){
        const btn = document.getElementById('cm_bulk_select');
        if(btn) btn.addEventListener('click', applyBulk);
        const clr = document.getElementById('cm_bulk_clear');
        if(clr) clr.addEventListener('click', clearSelection);
    });
})();
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
