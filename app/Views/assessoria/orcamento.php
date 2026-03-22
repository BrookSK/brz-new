<?php
ob_start();
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Seu Orçamento Personalizado</h1>
                    <p class="text-muted mb-0">Revise os produtos e selecione os que deseja comprar</p>
                </div>
                <div>
                    <a href="/assessoria" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Novo Orçamento
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($orcamento['erros'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                <h5 class="alert-heading">
                    <i class="fas fa-exclamation-triangle me-2"></i>Alguns produtos não puderam ser processados
                </h5>
                <hr>
                <?php foreach ($orcamento['erros'] as $erro): ?>
                    <div class="mb-2">
                        <strong>Link:</strong> <?= htmlspecialchars(substr($erro['link'], 0, 80)) ?>...<br>
                        <strong>Erro:</strong> <?= htmlspecialchars($erro['error']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
        $jobId = (string) ($job_id ?? '');
        $jobData = is_array(($job ?? null)) ? $job : null;
        $jobRunning = ($jobId !== '' && is_array($jobData) && (($jobData['status'] ?? '') !== 'done'));
        $jobTotal = (int) ($jobData['total'] ?? 0);
        $jobProcessed = (int) ($jobData['processed'] ?? 0);
    ?>

    <?php if (empty($orcamento['produtos']) && $jobRunning): ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-box me-2"></i>Processando produtos</h5>
                    <span class="badge" id="jobProgressText" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                        <?= $jobProcessed ?> / <?= max(1, $jobTotal) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar" id="jobProgressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="jobPlaceholders">
                        <?php for ($i = 0; $i < max(1, min(5, $jobTotal)); $i++): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="spinner-border text-primary me-3" role="status" style="width: 1.5rem; height: 1.5rem;"></div>
                                    <div>
                                        <div class="fw-bold">Carregando produto...</div>
                                        <div class="text-muted small">Aguardando análise e extração de dados</div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div class="card-header py-3">
                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Resumo do Orçamento</h5>
                </div>
                <div class="card-body">
                    <div class="text-muted">O orçamento será exibido automaticamente quando finalizar.</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function() {
            var jobId = <?= json_encode($jobId) ?>;
            var total = <?= json_encode(max(1, $jobTotal)) ?>;
            var processed = <?= json_encode(max(0, $jobProcessed)) ?>;

            function renderProgress(p, t) {
                var pct = Math.round((p / t) * 100);
                var bar = document.getElementById('jobProgressBar');
                var txt = document.getElementById('jobProgressText');
                if (bar) bar.style.width = pct + '%';
                if (txt) txt.textContent = p + ' / ' + t;
            }

            renderProgress(processed, total);

            function poll() {
                fetch('/assessoria/status?job_id=' + encodeURIComponent(jobId))
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        if (!resp || resp.success !== true || !resp.data) {
                            setTimeout(poll, 2000);
                            return;
                        }

                        var d = resp.data;
                        var p = typeof d.processed === 'number' ? d.processed : 0;
                        var t = typeof d.total === 'number' && d.total > 0 ? d.total : total;
                        renderProgress(p, t);

                        if (d.status === 'done') {
                            window.location.href = '/assessoria/orcamento?job_id=' + encodeURIComponent(jobId);
                            return;
                        }

                        setTimeout(poll, 2000);
                    })
                    .catch(function() {
                        setTimeout(poll, 3000);
                    });
            }

            poll();
        })();
    </script>
    <?php elseif (empty($orcamento['produtos'])): ?>
    <div class="row">
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                <h3>Nenhum produto processado</h3>
                <p class="text-muted">Todos os links apresentaram erros. Tente novamente com outros links.</p>
                <a href="/assessoria" class="btn btn-primary">
                    <i class="fas fa-redo me-2"></i>Tentar Novamente
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <form id="orcamentoForm">
        <div class="row">
            <div class="col-lg-8">
                <!-- Lista de Produtos -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-box me-2"></i>Produtos Disponíveis
                            <span class="badge ms-2" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                <?= count($orcamento['produtos']) ?>
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($orcamento['produtos'] as $index => $produto): ?>
                        <div class="product-item border rounded p-3 mb-3">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input product-checkbox" 
                                               type="checkbox" 
                                               id="produto_<?= $index ?>" 
                                               value="<?= $index ?>"
                                               checked>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <?php if (!empty($produto['imagens'])): ?>
                                        <img src="<?= htmlspecialchars($produto['imagens'][0]) ?>" 
                                             alt="<?= htmlspecialchars($produto['nome']) ?>" 
                                             class="img-thumbnail" 
                                             style="width: 80px; height: 80px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" 
                                             style="width: 80px; height: 80px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col">
                                    <h6 class="mb-1"><?= htmlspecialchars($produto['nome']) ?></h6>
                                    <p class="text-muted small mb-1"><?= htmlspecialchars($produto['descricao']) ?></p>
                                    <?php if (!empty($produto['variacoes']) && is_array($produto['variacoes'])): ?>
                                        <div class="mt-2 variation-combo" data-index="<?= $index ?>"></div>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-tag me-1"></i><?= htmlspecialchars($produto['sku']) ?>
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-weight me-1"></i><span class="peso-text" data-base-peso="<?= htmlspecialchars((string) $produto['peso']) ?>"><?= number_format($produto['peso'], 2) ?></span> kg
                                        </span>
                                        <small class="text-muted">
                                            <i class="fas fa-link me-1"></i>
                                            <a href="<?= htmlspecialchars($produto['url_original']) ?>" 
                                               target="_blank" class="text-decoration-none">
                                                Ver original
                                            </a>
                                        </small>
                                    </div>
                                    <div class="mt-2">
                                        <label class="form-label small text-muted mb-1" for="quantidade_<?= $index ?>">Quantidade</label>
                                        <input type="number" class="form-control form-control-sm quantidade-input" id="quantidade_<?= $index ?>" data-index="<?= $index ?>" value="1" min="1" step="1" style="max-width: 110px;">
                                    </div>
                                </div>
                                <div class="col-auto text-end">
                                    <div class="fw-bold text-primary h5">
                                        $<span class="valor-text" data-base-valor="<?= htmlspecialchars((string) $produto['valor']) ?>"><?= number_format($produto['valor'], 2) ?></span>
                                    </div>
                                    <small class="text-muted">USD</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Termos e Condições -->
                <div class="card shadow-sm border-0">
                    <div class="card-header py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>Termos Importantes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-3">
                            <h6 class="alert-heading">Atenção!</h6>
                            <p class="mb-2">Este é um <strong>recurso experimental</strong> que utiliza inteligência artificial para extrair informações dos produtos.</p>
                            <ul class="mb-0">
                                <li>Os valores estão sujeitos a revisão e podem variar</li>
                                <li>Podem ocorrer cobranças adicionais ou reembolsos</li>
                                <li>A precisão dos dados depende da qualidade da fonte</li>
                                <li>Verifique todas as informações antes de finalizar</li>
                            </ul>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="termosAceitos" required>
                            <label class="form-check-label" for="termosAceitos">
                                Li e aceito os termos acima. Estou ciente de que se trata de um recurso experimental 
                                e que os valores podem ser revisados. Concordo com possíveis ajustes no valor final.
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Resumo do Orçamento -->
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                    <div class="card-header py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-calculator me-2"></i>Resumo do Orçamento
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Produtos selecionados:</span>
                                <span id="produtosCount"><?= count($orcamento['produtos']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span id="subtotal">$<?= number_format($totais['subtotal'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Taxa de Serviço:</span>
                                <span id="taxaServico">$<?= number_format($totais['taxa_servico'], 2) ?></span>
                            </div>
                            <?php if (!empty($totais['pix_desconto_taxa_servico_percent']) && (float) $totais['pix_desconto_taxa_servico_percent'] > 0): ?>
                            <div class="alert alert-info small py-2 px-2 mb-2">
                                Pagando com <strong>PIX</strong> você ganha <strong><?= number_format((float) $totais['pix_desconto_taxa_servico_percent'], 2) ?>%</strong> de desconto na taxa de serviço.
                                Taxa com desconto: <strong>$<span id="taxaServicoPix"><?= number_format((float) ($totais['taxa_servico_pix'] ?? 0), 2) ?></span></strong>.
                            </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Frete:</span>
                                <span id="frete">$<?= number_format($totais['frete'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Impostos:</span>
                                <span id="impostos">$<?= number_format($totais['impostos'], 2) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold h5">
                                <span>Total:</span>
                                <span class="text-primary" id="total">$<?= number_format($totais['total'], 2) ?></span>
                            </div>
                            <?php if (!empty($totais['pix_desconto_taxa_servico_percent']) && (float) $totais['pix_desconto_taxa_servico_percent'] > 0): ?>
                            <div class="d-flex justify-content-between mt-1">
                                <span class="text-muted small">Total com PIX:</span>
                                <span class="small"><strong>$<span id="totalPix"><?= number_format((float) ($totais['total_pix'] ?? 0), 2) ?></span></strong></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="addToCartBtn" disabled>
                                <i class="fas fa-shopping-cart me-2"></i>Adicionar ao Carrinho
                            </button>
                            <div class="alert alert-warning small mt-2 d-none" id="variacao-warning" role="alert">
                                Para continuar, selecione a variação obrigatória (ex.: tamanho/cor) dos produtos selecionados.
                            </div>
                            <a href="/carrinho" class="btn btn-outline-secondary">
                                <i class="fas fa-eye me-2"></i>Ver Carrinho
                            </a>
                        </div>

                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="fas fa-lock me-1"></i>Pagamento seguro via checkout
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- Container de Notificações -->
<div id="notificationContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 9998;">
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    const produtos = <?= json_encode($orcamento['produtos']) ?>;
    const totaisOriginais = <?= json_encode($totais) ?>;

    const PIX_PCT = <?= json_encode((float) ($totais['pix_desconto_taxa_servico_percent'] ?? 0)) ?>;

    const selections = {};

    function getVariationKeys(index) {
        const p = produtos[index];
        if (!p || !Array.isArray(p.variacoes)) return [];
        const keys = new Set();
        p.variacoes.forEach(v => {
            const attrs = v && typeof v === 'object' ? (v.atributos || {}) : {};
            if (attrs && typeof attrs === 'object') {
                Object.keys(attrs).forEach(k => {
                    if (k && String(k).trim() !== '') keys.add(String(k).trim());
                });
            }
        });
        return Array.from(keys);
    }

    function getValueSetForKey(index, key, partial) {
        const p = produtos[index];
        const out = new Set();
        if (!p || !Array.isArray(p.variacoes)) return out;
        p.variacoes.forEach(v => {
            if (!v || typeof v !== 'object') return;
            const attrs = v.atributos || {};
            if (!attrs || typeof attrs !== 'object') return;
            const val = attrs[key];
            if (val === undefined || val === null || String(val).trim() === '') return;
            if (partial && typeof partial === 'object') {
                for (const pk of Object.keys(partial)) {
                    if (pk === key) continue;
                    const pv = partial[pk];
                    if (pv === null || pv === undefined) continue;
                    if (String((attrs || {})[pk] ?? '') !== String(pv)) {
                        return;
                    }
                }
            }
            out.add(String(val));
        });
        return out;
    }

    function resolveVariant(index) {
        const p = produtos[index];
        if (!p || !Array.isArray(p.variacoes) || p.variacoes.length === 0) {
            return { variacao_id: null, valor: p ? p.valor : 0, peso: p ? p.peso : 0, complete: true };
        }

        const keys = getVariationKeys(index);
        if (keys.length === 0) {
            return { variacao_id: null, valor: p.valor, peso: p.peso, complete: true };
        }

        const sel = selections[index] || {};
        const complete = keys.every(k => sel[k] !== undefined && sel[k] !== null && String(sel[k]).trim() !== '');
        const matches = p.variacoes.filter(v => {
            if (!v || typeof v !== 'object') return false;
            const attrs = v.atributos || {};
            if (!attrs || typeof attrs !== 'object') return false;
            for (const k of keys) {
                const want = sel[k];
                if (want === undefined || want === null || String(want).trim() === '') return false;
                if (String((attrs || {})[k] ?? '') !== String(want)) return false;
            }
            return true;
        });

        if (!complete) {
            return { variacao_id: null, valor: p.valor, peso: p.peso, complete: false };
        }

        if (matches.length >= 1) {
            // Alguns sites retornam variantes duplicadas para a mesma combinação;
            // escolher a melhor (com preço/peso) ao invés de falhar.
            const best = matches.reduce((acc, cur) => {
                if (!acc) return cur;
                const accPrice = acc && acc.valor !== null && acc.valor !== undefined && !isNaN(parseFloat(acc.valor)) ? parseFloat(acc.valor) : 0;
                const curPrice = cur && cur.valor !== null && cur.valor !== undefined && !isNaN(parseFloat(cur.valor)) ? parseFloat(cur.valor) : 0;
                if (accPrice <= 0 && curPrice > 0) return cur;
                const accWeight = acc && acc.peso !== null && acc.peso !== undefined && !isNaN(parseFloat(acc.peso)) ? parseFloat(acc.peso) : 0;
                const curWeight = cur && cur.peso !== null && cur.peso !== undefined && !isNaN(parseFloat(cur.peso)) ? parseFloat(cur.peso) : 0;
                if (accWeight <= 0 && curWeight > 0) return cur;
                return acc;
            }, null);

            const valor = best && best.valor !== null && best.valor !== undefined && !isNaN(parseFloat(best.valor)) && parseFloat(best.valor) > 0 ? parseFloat(best.valor) : p.valor;
            const peso = best && best.peso !== null && best.peso !== undefined && !isNaN(parseFloat(best.peso)) && parseFloat(best.peso) > 0 ? parseFloat(best.peso) : p.peso;
            return { variacao_id: String((best && best.id) ?? ''), valor, peso, complete: true };
        }

        return { variacao_id: null, valor: p.valor, peso: p.peso, complete: false };
    }

    function updateComboUI(index) {
        const container = document.querySelector('.variation-combo[data-index="' + index + '"]');
        if (!container) return;
        const p = produtos[index];
        if (!p || !Array.isArray(p.variacoes) || p.variacoes.length === 0) {
            container.innerHTML = '';
            return;
        }

        const keys = getVariationKeys(index);
        if (keys.length === 0) {
            container.innerHTML = '';
            return;
        }

        if (!selections[index]) selections[index] = {};

        const sel = selections[index];
        let html = '';
        keys.forEach((k, i) => {
            const partial = { ...sel };
            delete partial[k];
            const values = Array.from(getValueSetForKey(index, k, partial));
            values.sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));

            html += '<div class="mb-2">';
            html += '<div class="small text-muted mb-1">' + $('<div>').text(k).html() + '</div>';
            html += '<div class="d-flex flex-wrap gap-2">';
            values.forEach(v => {
                const isActive = String(sel[k] ?? '') === String(v);
                // Disponibilidade depende das escolhas atuais (exceto o próprio key)
                const candidate = { ...sel, [k]: v };
                const hasAny = p.variacoes.some(variant => {
                    if (!variant || typeof variant !== 'object') return false;
                    const attrs = variant.atributos || {};
                    if (!attrs || typeof attrs !== 'object') return false;
                    for (const kk of keys) {
                        const want = candidate[kk];
                        if (want === undefined || want === null || String(want).trim() === '') continue;
                        if (String((attrs || {})[kk] ?? '') !== String(want)) return false;
                    }
                    return true;
                });
                const disabled = !hasAny;
                const classes = 'btn btn-sm ' + (isActive ? 'btn-primary' : 'btn-outline-secondary');
                html += '<button type="button" class="' + classes + ' variation-btn" data-index="' + index + '" data-key="' + encodeURIComponent(String(k)) + '" data-value="' + encodeURIComponent(String(v)) + '" ' + (disabled ? 'disabled' : '') + '>' + $('<div>').text(v).html() + '</button>';
            });
            html += '</div>';
            html += '</div>';
        });

        const status = resolveVariant(index);
        if (!status.complete) {
            html += '<div class="small text-muted">Selecione todas as opções para definir a variação.</div>';
        }
        container.innerHTML = html;
    }

    function atualizarCardProduto(index) {
        const container = document.getElementById('produto_' + index) ? document.getElementById('produto_' + index).closest('.product-item') : null;
        if (!container) return;
        const data = resolveVariant(index);
        const valorEl = container.querySelector('.valor-text');
        const pesoEl = container.querySelector('.peso-text');
        if (valorEl) {
            const oldVal = parseFloat(valorEl.textContent) || 0;
            const newVal = data.valor || 0;
            valorEl.textContent = newVal.toFixed(2);
            // Feedback visual quando preço muda
            if (Math.abs(oldVal - newVal) > 0.01) {
                valorEl.closest('.fw-bold').style.transition = 'color 0.3s';
                valorEl.closest('.fw-bold').style.color = '#198754';
                setTimeout(() => { valorEl.closest('.fw-bold').style.color = ''; }, 1000);
            }
        }
        if (pesoEl) {
            const oldPeso = parseFloat(pesoEl.textContent) || 0;
            const newPeso = data.peso || 0;
            pesoEl.textContent = newPeso.toFixed(2);
            if (Math.abs(oldPeso - newPeso) > 0.01) {
                pesoEl.parentElement.style.transition = 'color 0.3s';
                pesoEl.parentElement.style.color = '#198754';
                setTimeout(() => { pesoEl.parentElement.style.color = ''; }, 1000);
            }
        }
    }

    // Calcular totais baseado nos produtos selecionados
    function calcularTotaisSelecionados() {
        const selecionados = [];
        const selecionadosPayload = [];
        let allComplete = true;
        $('.product-checkbox:checked').each(function() {
            const index = parseInt($(this).val());
            const p = produtos[index];
            if (!p) return;
            const d = resolveVariant(index);

            const qEl = document.querySelector('.quantidade-input[data-index="' + index + '"]');
            let quantidade = 1;
            if (qEl) {
                const qv = parseInt(qEl.value, 10);
                quantidade = !isNaN(qv) && qv > 0 ? qv : 1;
            }

            selecionados.push({
                ...produtos[index],
                valor: d.valor,
                peso: d.peso,
                quantidade: quantidade
            });

            selecionadosPayload.push({
                index: index,
                variacao_id: d.variacao_id,
                quantidade: quantidade
            });

            if (Array.isArray(p.variacoes) && p.variacoes.length > 0) {
                const keys = getVariationKeys(index);
                if (keys.length > 0 && !d.complete) {
                    allComplete = false;
                }
            }
        });

        const subtotal = selecionados.reduce((sum, p) => sum + (p.valor * (p.quantidade || 1)), 0);
        const pesoTotal = selecionados.reduce((sum, p) => sum + (p.peso * (p.quantidade || 1)), 0);
        
        // Recalcular taxas proporcionalmente
        const taxaServicoOriginal = totaisOriginais.taxa_servico;
        const freteOriginal = totaisOriginais.frete;
        const impostosOriginais = totaisOriginais.impostos;
        
        const proporcao = subtotal > 0 ? subtotal / totaisOriginais.subtotal : 0;
        
        const taxaServico = taxaServicoOriginal * proporcao;
        const frete = freteOriginal * proporcao;
        const impostos = impostosOriginais * proporcao;
        const total = subtotal + taxaServico + frete + impostos;

        let taxaPix = taxaServico;
        let totalPix = total;
        if (PIX_PCT > 0) {
            taxaPix = Math.max(0, taxaServico * (1 - (PIX_PCT / 100)));
            totalPix = subtotal + taxaPix + frete + impostos;
        }

        // Atualizar interface
        $('#produtosCount').text(selecionados.length);
        $('#subtotal').text('$' + subtotal.toFixed(2));
        $('#taxaServico').text('$' + taxaServico.toFixed(2));
        if (PIX_PCT > 0) {
            $('#taxaServicoPix').text(taxaPix.toFixed(2));
            $('#totalPix').text(totalPix.toFixed(2));
        }
        $('#frete').text('$' + frete.toFixed(2));
        $('#impostos').text('$' + impostos.toFixed(2));
        $('#total').text('$' + total.toFixed(2));

        // Habilitar/desabilitar botão
        const termosAceitos = $('#termosAceitos').is(':checked');
        const temSelecionados = selecionados.length > 0;
        $('#addToCartBtn').prop('disabled', !(termosAceitos && temSelecionados && allComplete));

        const showVariacaoWarning = temSelecionados && !allComplete;
        $('#variacao-warning').toggleClass('d-none', !showVariacaoWarning);

        // Cache payload no botão
        $('#addToCartBtn').data('selecionados', selecionadosPayload);
    }

    // Event listeners
    $('.product-checkbox').change(calcularTotaisSelecionados);
    $('#termosAceitos').change(calcularTotaisSelecionados);
    $(document).on('input change', '.quantidade-input', calcularTotaisSelecionados);

    $(document).on('click', '.variation-btn', function() {
        const index = parseInt($(this).data('index'));
        let key = $(this).data('key');
        let value = $(this).data('value');
        if (isNaN(index) || !key) return;
        try {
            key = decodeURIComponent(String(key));
            value = decodeURIComponent(String(value ?? ''));
        } catch (e) {
            key = String(key);
            value = String(value ?? '');
        }
        if (!selections[index]) selections[index] = {};

        // Toggle: clicar na opção ativa remove a seleção daquela chave
        if (String(selections[index][String(key)] ?? '') === String(value)) {
            delete selections[index][String(key)];
        } else {
            selections[index][String(key)] = String(value);
        }

        // Se alguma seleção atual ficou inválida por causa dessa escolha, limpar
        const p = produtos[index];
        const keys = getVariationKeys(index);
        if (p && Array.isArray(p.variacoes) && keys.length > 0) {
            keys.forEach(k => {
                const selv = selections[index][k];
                if (selv === undefined || selv === null || String(selv).trim() === '') return;
                const candidate = { ...selections[index] };
                const hasAny = p.variacoes.some(variant => {
                    if (!variant || typeof variant !== 'object') return false;
                    const attrs = variant.atributos || {};
                    if (!attrs || typeof attrs !== 'object') return false;
                    for (const kk of keys) {
                        const want = candidate[kk];
                        if (want === undefined || want === null || String(want).trim() === '') continue;
                        if (String((attrs || {})[kk] ?? '') !== String(want)) return false;
                    }
                    return true;
                });
                if (!hasAny) {
                    delete selections[index][k];
                }
            });
        }
        updateComboUI(index);
        atualizarCardProduto(index);
        calcularTotaisSelecionados();
    });

    // Submit do formulário
    $('#orcamentoForm').submit(function(e) {
        e.preventDefault();

        if (!$('#termosAceitos').is(':checked')) {
            Swal.fire({
                icon: 'warning',
                title: 'Termos Obrigatórios',
                text: 'Você precisa aceitar os termos para prosseguir.',
                confirmButtonColor: '#0b1f3a'
            });
            return;
        }

        let selecionados = $('#addToCartBtn').data('selecionados') || [];
        if (!Array.isArray(selecionados) || selecionados.length === 0) {
            selecionados = [];
            $('.product-checkbox:checked').each(function() {
                selecionados.push({ index: parseInt($(this).val()), variacao_id: null });
            });
        }

        if (selecionados.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Nenhum Produto Selecionado',
                text: 'Selecione pelo menos um produto para adicionar ao carrinho.',
                confirmButtonColor: '#0b1f3a'
            });
            return;
        }

        // Enviar requisição
        $.ajax({
            url: '/assessoria/adicionar-ao-carrinho',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                orcamento_id: <?= json_encode((int) ($orcamento_id ?? 0)) ?>,
                termos_aceitos: true,
                produtos_selecionados: selecionados
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Produtos Adicionados!',
                        text: response.message,
                        confirmButtonColor: '#0b1f3a',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = response.redirect;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: response.message,
                        confirmButtonColor: '#0b1f3a'
                    }).then(() => {
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        }
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Ocorreu um erro ao adicionar os produtos ao carrinho. Tente novamente.',
                    confirmButtonColor: '#0b1f3a'
                });
            }
        });
    });

    // Inicializar cálculos
    calcularTotaisSelecionados();
    $('.variation-combo').each(function() {
        const index = parseInt($(this).data('index'));
        if (!isNaN(index)) {
            updateComboUI(index);
            atualizarCardProduto(index);
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
