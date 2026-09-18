<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><?= __('admin.correios_mundial.new_invoice_title','Correios Mundial (PACKET) - Nova Fatura (CN38)') ?></h1>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial/faturas"><?= __('admin.correios_mundial.back','Voltar') ?></a>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>

    <?php
        $balance = isset($balance) && is_array($balance) ? $balance : [];
        $balanceOk = !empty($balance['success']);
        $currentBalance = (int) ($balance['currentBalance'] ?? 0);
    ?>

    <div class="row mb-3">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= __('admin.correios_mundial.current_balance','Saldo atual') ?></div>
                    <div class="h4 mb-0"><?= $balanceOk ? (int) $currentBalance : '-' ?></div>
                    <?php if (!$balanceOk): ?>
                        <div class="small text-danger mt-1"><?= htmlspecialchars((string) ($balance['error'] ?? __('admin.correios_mundial.balance_query_failed_dot','Falha ao consultar saldo.'))) ?></div>
                    <?php else: ?>
                        <div class="small text-muted mt-1"><?= __('admin.correios_mundial.balance_needed','Precisa de saldo para gerar a fatura.') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong><?= __('admin.correios_mundial.add_new_invoice','Adicionar Nova Fatura') ?></strong></div>
        <div class="card-body">
            <form method="post" action="/admin/correios-mundial/faturas/criar" onsubmit="return confirm(<?= htmlspecialchars(json_encode(__('admin.correios_mundial.confirm_generate_cn38','Gerar fatura CN38? Esta operação é irreversível e acarretará em custos.')), ENT_QUOTES, 'UTF-8') ?>);">
                <?php $containers = isset($containers) && is_array($containers) ? $containers : []; ?>

                <div class="row g-3">
                    <div class="col-12">
                        <div class="mb-2"><strong><?= __('admin.correios_mundial.invoice_details','Detalhes da Fatura') ?></strong></div>

                        <label class="form-label" for="cn38_dispatch_numbers"><strong><?= __('admin.correios_mundial.dispatch_numbers_required','Números de Remessa*') ?></strong></label>
                        <select
                            class="form-select"
                            multiple
                            size="10"
                            id="cn38_dispatch_numbers"
                            aria-describedby="cn38_dispatch_numbers_help"
                            <?= (!$balanceOk || $currentBalance <= 0) ? 'disabled' : '' ?>
                        >
                            <?php if (empty($containers)): ?>
                                <option value="" disabled><?= __('admin.correios_mundial.no_containers_for_invoice','Nenhum container disponível para faturamento.') ?></option>
                            <?php else: ?>
                                <?php foreach ($containers as $c): ?>
                                    <?php $cid = (int) ($c['id'] ?? 0); ?>
                                    <?php $unitCode = (string) ($c['unit_code'] ?? ''); ?>
                                    <?php $dispatchNumber = (string) ($c['dispatch_number'] ?? ''); ?>
                                    <?php $tc = (int) ($c['tracking_count'] ?? 0); ?>
                                    <option
                                        value="<?= $cid ?>"
                                        data-tracking-count="<?= $tc ?>"
                                        data-dispatch-number="<?= htmlspecialchars($dispatchNumber) ?>"
                                        data-unit-code="<?= htmlspecialchars($unitCode) ?>"
                                    >
                                        <?= htmlspecialchars($dispatchNumber) ?> (<?= $tc ?>)  [#<?= $cid ?>]
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div id="cn38_dispatch_numbers_help" class="form-text"><?= __('admin.correios_mundial.ctrl_multi_dispatch','Segure Ctrl para selecionar múltiplos números de remessa.') ?></div>

                        <div class="mt-3">
                            <label class="form-label" for="cn38_paste_list"><strong><?= __('admin.correios_mundial.paste_list_dispatch','Colar lista (remessas / ids / unit codes)') ?></strong></label>
                            <textarea class="form-control" rows="4" id="cn38_paste_list" placeholder="<?= htmlspecialchars(__('admin.correios_mundial.paste_placeholder_dispatch','Cole aqui (um por linha, separado por vírgula/space)...'), ENT_QUOTES, 'UTF-8') ?>" <?= (!$balanceOk || $currentBalance <= 0) ? 'disabled' : '' ?>></textarea>
                            <div class="form-text"><?= __('admin.correios_mundial.paste_help_dispatch','Ao colar, o sistema tenta selecionar automaticamente os containers correspondentes.') ?></div>
                        </div>

                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-secondary" id="cn38_apply_paste" <?= (!$balanceOk || $currentBalance <= 0) ? 'disabled' : '' ?>><?= __('admin.correios_mundial.select','Selecionar') ?></button>
                            <button type="button" class="btn btn-outline-secondary" id="cn38_clear_selection" <?= (!$balanceOk || $currentBalance <= 0) ? 'disabled' : '' ?>><?= __('admin.correios_mundial.clear_selection','Limpar seleção') ?></button>
                        </div>

                        <div class="mt-3" id="cn38_selection_review" style="display:none;">
                            <div class="text-muted small mb-1"><strong><?= __('admin.correios_mundial.review','Conferência') ?></strong></div>
                            <div class="small">
                                <?= __('admin.correios_mundial.found','Encontrados') ?>: <span id="cn38_found_count">0</span> | <?= __('admin.correios_mundial.not_found','Não encontrados') ?>: <span id="cn38_not_found_count">0</span>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-lg-6">
                                    <div class="text-muted small"><?= __('admin.correios_mundial.found','Encontrados') ?></div>
                                    <textarea class="form-control form-control-sm" rows="3" id="cn38_found_list" readonly></textarea>
                                </div>
                                <div class="col-lg-6">
                                    <div class="text-muted small"><?= __('admin.correios_mundial.not_found','Não encontrados') ?></div>
                                    <textarea class="form-control form-control-sm" rows="3" id="cn38_not_found_list" readonly></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                <?= __('admin.correios_mundial.selected','Selecionados:') ?> <span id="cn38_selected_units">0</span> <?= __('admin.correios_mundial.dispatches','remessas') ?> | <span id="cn38_selected_trackings">0</span> <?= __('admin.correios_mundial.trackings_lower','trackings') ?>
                                <span class="ms-2" id="cn38_limit_hint"></span>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary" id="cn38_submit" <?= (!$balanceOk || $currentBalance <= 0) ? 'disabled' : '' ?>><?= __('admin.correios_mundial.generate_invoice','Gerar fatura') ?></button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="text-danger" style="font-weight: 600;"><?= __('admin.correios_mundial.irreversible_warning','Aviso: Esta operação é irreversível e acarretará em custos.') ?></div>
                        </div>
                    </div>
                </div>

                <div id="cn38_hidden_inputs"></div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    function getSelectedOptions(selectEl) {
        if (!selectEl) return [];
        var out = [];
        for (var i = 0; i < selectEl.options.length; i++) {
            var o = selectEl.options[i];
            if (o.selected && o.value) out.push(o);
        }
        return out;
    }

    function ensureHiddenInputsFromSelected() {
        var selectEl = document.getElementById('cn38_dispatch_numbers');
        var wrap = document.getElementById('cn38_hidden_inputs');
        if (!wrap) return;
        wrap.innerHTML = '';

        var selected = getSelectedOptions(selectEl);
        selected.forEach(function(o) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'containerIds[]';
            input.value = o.value;
            wrap.appendChild(input);
        });
    }

    function recalc() {
        var selectEl = document.getElementById('cn38_dispatch_numbers');
        var selected = getSelectedOptions(selectEl);
        var units = selected.length;
        var trackings = 0;
        selected.forEach(function(o) {
            var tc = parseInt(o.getAttribute('data-tracking-count') || '0', 10);
            if (!isNaN(tc)) trackings += tc;
        });

        var uEl = document.getElementById('cn38_selected_units');
        var tEl = document.getElementById('cn38_selected_trackings');
        if (uEl) uEl.textContent = String(units);
        if (tEl) tEl.textContent = String(trackings);

        var hint = document.getElementById('cn38_limit_hint');
        if (hint) {
            hint.textContent = (trackings > 5000) ? <?= json_encode(__('admin.correios_mundial.limit_exceeded','(limite excedido: máximo 5000)')) ?> : '';
            hint.className = (trackings > 5000) ? 'text-danger' : 'text-muted';
        }

        var btn = document.getElementById('cn38_submit');
        if (btn) {
            btn.disabled = (units <= 0 || trackings <= 0 || trackings > 5000);
        }

        ensureHiddenInputsFromSelected();
    }

    function normalizeTokens(text) {
        text = String(text || '');
        var parts = text
            .replace(/\r/g, '\n')
            .replace(/[;,]+/g, '\n')
            .split(/\n|\s+/g)
            .map(function(s) { return String(s || '').trim(); })
            .filter(function(s) { return s.length > 0; });
        var seen = {};
        var out = [];
        parts.forEach(function(p) {
            if (!seen[p]) { seen[p] = true; out.push(p); }
        });
        return out;
    }

    function applyPasteSelection() {
        var ta = document.getElementById('cn38_paste_list');
        var selectEl = document.getElementById('cn38_dispatch_numbers');
        if (!ta || !selectEl) return;

        var tokens = normalizeTokens(ta.value);
        var review = document.getElementById('cn38_selection_review');
        var foundListEl = document.getElementById('cn38_found_list');
        var notFoundListEl = document.getElementById('cn38_not_found_list');
        var foundCountEl = document.getElementById('cn38_found_count');
        var notFoundCountEl = document.getElementById('cn38_not_found_count');

        if (tokens.length <= 0) {
            if (review) review.style.display = 'none';
            return;
        }

        var tokenSet = {};
        tokens.forEach(function(t) { tokenSet[t] = true; });

        var foundTokens = {};

        for (var i = 0; i < selectEl.options.length; i++) {
            var o = selectEl.options[i];
            var cid = String(o.value || '');
            var dispatch = String(o.getAttribute('data-dispatch-number') || '');
            var unit = String(o.getAttribute('data-unit-code') || '');
            if (tokenSet[cid] || tokenSet[dispatch] || tokenSet[unit]) {
                o.selected = true;
                if (tokenSet[cid]) foundTokens[cid] = true;
                if (tokenSet[dispatch]) foundTokens[dispatch] = true;
                if (tokenSet[unit]) foundTokens[unit] = true;
            }
        }

        var foundArr = [];
        var notFoundArr = [];
        tokens.forEach(function(t) {
            if (foundTokens[t]) foundArr.push(t);
            else notFoundArr.push(t);
        });

        if (review) review.style.display = '';
        if (foundListEl) foundListEl.value = foundArr.join("\n");
        if (notFoundListEl) notFoundListEl.value = notFoundArr.join("\n");
        if (foundCountEl) foundCountEl.textContent = String(foundArr.length);
        if (notFoundCountEl) notFoundCountEl.textContent = String(notFoundArr.length);
    }

    function clearSelection() {
        var selectEl = document.getElementById('cn38_dispatch_numbers');
        if (!selectEl) return;
        for (var i = 0; i < selectEl.options.length; i++) {
            selectEl.options[i].selected = false;
        }
        var review = document.getElementById('cn38_selection_review');
        if (review) review.style.display = 'none';
        var ta = document.getElementById('cn38_paste_list');
        if (ta) ta.value = '';
    }

    document.addEventListener('change', function(ev) {
        var t = ev.target;
        if (t && t.id === 'cn38_dispatch_numbers') {
            recalc();
        }
    });

    document.addEventListener('blur', function(ev) {
        var t = ev.target;
        if (t && t.id === 'cn38_paste_list') {
            recalc();
        }
    }, true);

    document.addEventListener('paste', function(ev) {
        var t = ev.target;
        if (t && t.id === 'cn38_paste_list') {
            setTimeout(function() {
                recalc();
            }, 10);
        }
    });

    document.addEventListener('click', function(ev) {
        var t = ev.target;
        if (t && t.id === 'cn38_apply_paste') {
            applyPasteSelection();
            recalc();
        }
        if (t && t.id === 'cn38_clear_selection') {
            clearSelection();
            recalc();
        }
    });

    recalc();
})();
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
