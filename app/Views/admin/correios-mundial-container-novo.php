<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Novo container (Unitizador) - Correios Mundial (PACKET)</h1>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial/containers">Voltar</a>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/correios-mundial/containers/criar" class="card border-0 shadow-sm">
        <div class="card-header"><strong>Dados do container</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Número da remessa (dispatchNumber)</label>
                    <input type="number" class="form-control" name="dispatchNumber" value="<?= htmlspecialchars((string) ($defaults['dispatchNumber'] ?? '')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">País de origem</label>
                    <input type="text" class="form-control" name="originCountry" value="US" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Operador origem</label>
                    <input type="text" class="form-control" name="originOperatorName" value="<?= htmlspecialchars((string) ($defaults['originOperatorName'] ?? 'BRAS')) ?>" maxlength="4" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Operador destino</label>
                    <?php $dop = (string) ($defaults['destinationOperatorName'] ?? 'SAOD'); ?>
                    <select class="form-select" name="destinationOperatorName" required>
                        <option value="SAOD" <?= $dop === 'SAOD' ? 'selected' : '' ?>>SAOD - Guarulhos</option>
                        <option value="CWBA" <?= $dop === 'CWBA' ? 'selected' : '' ?>>CWBA - Curitiba</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Categoria postal</label>
                    <?php $pcc = (string) ($defaults['postalCategoryCode'] ?? 'A'); ?>
                    <select class="form-select" name="postalCategoryCode" required>
                        <option value="A" <?= $pcc === 'A' ? 'selected' : '' ?>>A – Airmail ou Priority Mail</option>
                        <option value="B" <?= $pcc === 'B' ? 'selected' : '' ?>>B – S.A.L Mail ou Non-Priority Mail</option>
                        <option value="C" <?= $pcc === 'C' ? 'selected' : '' ?>>C – Surface Mail ou Non-Priority Mail</option>
                        <option value="D" <?= $pcc === 'D' ? 'selected' : '' ?>>D – Priority Mail (terrestre)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Subclasse serviço</label>
                    <select class="form-select" name="serviceSubclassCode" required>
                        <?php $ssc = (string) ($defaults['serviceSubclassCode'] ?? 'NX'); ?>
                        <option value="NX" <?= $ssc === 'NX' ? 'selected' : '' ?>>NX (padrão)</option>
                        <option value="IX" <?= $ssc === 'IX' ? 'selected' : '' ?>>IX (expresso)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo unidade</label>
                    <select class="form-select" name="unitType" required>
                        <?php $ut = (string) ($defaults['unitType'] ?? '2'); ?>
                        <option value="1" <?= $ut === '1' ? 'selected' : '' ?>>1 (saco até 30kg)</option>
                        <option value="2" <?= $ut === '2' ? 'selected' : '' ?>>2 (pallet até 500kg)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">AWB (nº do voo)</label>
                    <input type="text" class="form-control" name="awb" value="<?= htmlspecialchars((string) ($defaults['awb'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Grupo de triagem</label>
                    <?php $tg = (string) ($defaults['triageGroup'] ?? '1'); ?>
                    <select class="form-select" name="triageGroup" required>
                        <option value="" <?= $tg === '' ? 'selected' : '' ?>>Selecione o grupo</option>
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
                    <label class="form-label">Colar lista (pedidos ou tracking)</label>
                    <textarea class="form-control" name="bulk" id="cm_bulk" rows="5" placeholder="Cole aqui: ex. #12345\n12345\nNC000005113BR\n..."><?= htmlspecialchars((string) ($defaults['bulk'] ?? '')) ?></textarea>
                    <div class="form-text">
                        Você pode colar IDs de pedido (números) e/ou trackingNumbers. O sistema tenta resolver e seleciona automaticamente.
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="cm_bulk_select">Selecionar</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cm_bulk_clear">Limpar seleção</button>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Pacotes disponíveis</label>
                    <select class="form-select" name="trackingNumbers[]" multiple size="12" required>
                        <?php $available = isset($availablePackages) && is_array($availablePackages) ? $availablePackages : []; ?>
                        <?php $pre = isset($preselected) && is_array($preselected) ? $preselected : []; ?>
                        <?php if (empty($available)): ?>
                            <option value="">Nenhum pacote disponível</option>
                        <?php else: ?>
                            <?php foreach ($available as $p): ?>
                                <?php $trk = (string) ($p['tracking_number'] ?? ''); ?>
                                <?php $pid = (int) ($p['pedido_id'] ?? 0); ?>
                                <?php if ($trk === '') continue; ?>
                                <option value="<?= htmlspecialchars($trk) ?>" data-pedido-id="<?= (int) $pid ?>" data-tracking="<?= htmlspecialchars(strtoupper($trk)) ?>" <?= in_array($trk, $pre, true) ? 'selected' : '' ?>>Pedido #<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($trk) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Use Ctrl/Shift para selecionar múltiplos.</div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><strong>Resumo seleção rápida</strong></div>
                        <div class="card-body">
                            <div class="small text-muted">Total informado</div>
                            <div class="h5 mb-2" id="cm_bulk_total">0</div>
                            <div class="small text-muted">Encontrados/selecionados</div>
                            <div class="h5 mb-0" id="cm_bulk_found">0</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><strong>Encontrados</strong></div>
                        <div class="card-body">
                            <pre id="cm_bulk_found_list" style="margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header"><strong>Não encontrados</strong></div>
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
                            <div class="card-header"><strong>Encontrados</strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['found'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><strong>Não encontrados</strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['not_found'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><strong>Já usados em container</strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['already_used'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Criar container</button>
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
