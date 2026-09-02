<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css" rel="stylesheet">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title"><?= __('admin.scheduled_promos.title', 'Promoções Agendadas') ?></h1>
        <p class="page-subtitle"><?= __('admin.scheduled_promos.subtitle', 'Agende campanhas de desconto com início e fim automáticos') ?></p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaPromo">
        <i class="fas fa-plus me-1"></i><?= __('admin.scheduled_promos.new_promo', 'Nova Promoção') ?>
    </button>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($_SESSION['flash_success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($_SESSION['flash_error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Calendário (desktop only) -->
<div class="card border-0 shadow-sm mb-4 d-none d-md-block">
    <div class="card-header bg-white fw-semibold"><?= __('admin.scheduled_promos.calendar_view', 'Visualização em Calendário') ?></div>
    <div class="card-body">
        <div id="calendario" style="min-height:500px;"></div>
    </div>
</div>

<!-- Lista de Promoções -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= __('admin.scheduled_promos.all_promos', 'Todas as Promoções') ?></div>
    <div class="card-body">
        <?php if (empty($promocoes)): ?>
            <div class="text-muted text-center py-4"><?= __('admin.scheduled_promos.empty', 'Nenhuma promoção agendada ainda.') ?></div>
        <?php else: ?>
            <!-- Desktop: Table -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= __('admin.scheduled_promos.col_name', 'Nome') ?></th>
                            <th><?= __('admin.scheduled_promos.col_discount', 'Desconto') ?></th>
                            <th><?= __('admin.scheduled_promos.col_start', 'Início') ?></th>
                            <th><?= __('admin.scheduled_promos.col_end', 'Fim') ?></th>
                            <th><?= __('admin.scheduled_promos.col_products', 'Produtos') ?></th>
                            <th><?= __('admin.scheduled_promos.col_status', 'Status') ?></th>
                            <th><?= __('admin.scheduled_promos.col_created_by', 'Criado por') ?></th>
                            <th class="text-end"><?= __('admin.scheduled_promos.col_actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promocoes as $p):
                            $statusCor = match($p['status']) {
                                'ativa' => 'success', 'agendada' => 'primary',
                                'finalizada' => 'secondary', 'cancelada' => 'danger',
                                default => 'secondary'
                            };
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($p['nome']) ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= $p['desconto_tipo'] === 'percentual' ? $p['desconto_valor'] . '%' : 'US$ ' . number_format((float) $p['desconto_valor'], 2) ?>
                                </span>
                            </td>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($p['inicio'])) ?></td>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($p['fim'])) ?></td>
                            <td><span class="badge bg-light text-dark"><?= __('admin.scheduled_promos.products_count', '{n} produtos', ['n' => (int) $p['total_produtos']]) ?></span></td>
                            <td><span class="badge bg-<?= $statusCor ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars((string) ($p['criado_por_nome'] ?? '-')) ?></td>
                            <td class="text-end">
                                <?php if (in_array($p['status'], ['agendada', 'ativa'])): ?>
                                    <form method="POST" action="/admin/promocoes-agendadas/cancelar/<?= (int) $p['id'] ?>" class="d-inline" onsubmit="return confirm('<?= htmlspecialchars(__('admin.scheduled_promos.confirm_cancel_restore', 'Cancelar esta promoção? Os preços dos produtos serão restaurados.'), ENT_QUOTES, 'UTF-8') ?>')">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile: Cards -->
            <div class="d-md-none">
                <?php foreach ($promocoes as $p):
                    $statusCor = match($p['status']) {
                        'ativa' => 'success', 'agendada' => 'primary',
                        'finalizada' => 'secondary', 'cancelada' => 'danger',
                        default => 'secondary'
                    };
                ?>
                <div class="card border-0 shadow-sm mb-2">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div class="fw-semibold" style="word-break:break-word;"><?= htmlspecialchars($p['nome']) ?></div>
                            <span class="badge bg-<?= $statusCor ?> ms-2"><?= ucfirst($p['status']) ?></span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 small text-muted">
                            <span><span class="badge bg-info"><?= $p['desconto_tipo'] === 'percentual' ? $p['desconto_valor'] . '%' : 'US$ ' . number_format((float) $p['desconto_valor'], 2) ?></span></span>
                            <span><?= __('admin.scheduled_promos.products_count', '{n} produtos', ['n' => (int) $p['total_produtos']]) ?></span>
                        </div>
                        <div class="small text-muted mt-1">
                            <?= date('d/m/Y H:i', strtotime($p['inicio'])) ?> → <?= date('d/m/Y H:i', strtotime($p['fim'])) ?>
                        </div>
                        <?php if (in_array($p['status'], ['agendada', 'ativa'])): ?>
                            <form method="POST" action="/admin/promocoes-agendadas/cancelar/<?= (int) $p['id'] ?>" class="mt-2" onsubmit="return confirm('<?= htmlspecialchars(__('admin.scheduled_promos.confirm_cancel', 'Cancelar esta promoção?'), ENT_QUOTES, 'UTF-8') ?>')">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100"><?= __('admin.scheduled_promos.cancel_promo', 'Cancelar promoção') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nova Promoção -->
<div class="modal fade" id="modalNovaPromo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/admin/promocoes-agendadas/criar">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i><?= __('admin.scheduled_promos.new_promo_modal', 'Nova Promoção Agendada') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?= __('admin.scheduled_promos.campaign_name', 'Nome da campanha') ?></label>
                            <input type="text" class="form-control" name="nome" required placeholder="<?= htmlspecialchars(__('admin.scheduled_promos.campaign_name_placeholder', 'Ex: Black Friday, Promoção de Inverno...'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold"><?= __('admin.scheduled_promos.discount_type', 'Tipo de desconto') ?></label>
                            <select class="form-select" name="desconto_tipo" id="promoDescontoTipo">
                                <option value="percentual"><?= __('admin.scheduled_promos.discount_percent', 'Percentual (%)') ?></option>
                                <option value="fixo"><?= __('admin.scheduled_promos.discount_fixed', 'Valor fixo (US$)') ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold"><?= __('admin.scheduled_promos.discount_value', 'Valor do desconto') ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="desconto_valor" step="0.01" min="0.01" required placeholder="10">
                                <span class="input-group-text" id="promoDescontoSuffix">%</span>
                            </div>
                        </div>
                        <div class="col-md-4"></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?= __('admin.scheduled_promos.col_start', 'Início') ?></label>
                            <input type="datetime-local" class="form-control" name="inicio" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?= __('admin.scheduled_promos.col_end', 'Fim') ?></label>
                            <input type="datetime-local" class="form-control" name="fim" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?= __('admin.scheduled_promos.products_select', 'Produtos (selecione os que participam)') ?></label>
                            <div class="mb-2">
                                <input type="text" class="form-control" id="buscaProdutoPromo" placeholder="<?= htmlspecialchars(__('admin.scheduled_promos.search_products_placeholder', 'Digite para buscar produtos...'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                            </div>
                            <div id="produtosSelecionados" class="mb-2"></div>
                            <div class="border rounded p-2" style="max-height:250px;overflow-y:auto;" id="listaProdutosPromo">
                                <div class="text-muted small text-center py-3"><?= __('admin.scheduled_promos.type_2_chars', 'Digite pelo menos 2 caracteres para buscar...') ?></div>
                            </div>
                            <div class="mt-1 small text-muted" id="contadorProdutos"><?= __('admin.scheduled_promos.products_selected', '0 produtos selecionados') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('admin.scheduled_promos.cancel', 'Cancelar') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= __('admin.scheduled_promos.create_promo', 'Criar Promoção') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calendário
    var calEl = document.getElementById('calendario');
    if (calEl) {
        var cal = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
            events: <?= json_encode($eventosCalendario, JSON_UNESCAPED_UNICODE) ?>,
            eventClick: function(info) {
                if (info.event.url) { window.location.href = info.event.url; info.jsEvent.preventDefault(); }
            },
            height: 'auto'
        });
        cal.render();
    }

    // Busca de produtos via AJAX
    var buscaInp = document.getElementById('buscaProdutoPromo');
    var listaEl = document.getElementById('listaProdutosPromo');
    var selecionadosEl = document.getElementById('produtosSelecionados');
    var contadorEl = document.getElementById('contadorProdutos');
    var produtosSelecionados = {};
    var buscaTimer = null;

    function atualizarContador() {
        var n = Object.keys(produtosSelecionados).length;
        if (contadorEl) contadorEl.textContent = n + ' <?= htmlspecialchars(__('admin.scheduled_promos.js_product', 'produto'), ENT_QUOTES, 'UTF-8') ?>' + (n !== 1 ? 's' : '') + ' <?= htmlspecialchars(__('admin.scheduled_promos.js_selected', 'selecionado'), ENT_QUOTES, 'UTF-8') ?>' + (n !== 1 ? 's' : '');
    }

    function renderSelecionados() {
        if (!selecionadosEl) return;
        var html = '';
        for (var id in produtosSelecionados) {
            var p = produtosSelecionados[id];
            html += '<span class="badge bg-primary me-1 mb-1" style="font-size:12px;">'
                + p.nome + ' <button type="button" class="btn-close btn-close-white ms-1" style="font-size:8px;" onclick="removerProdutoPromo(' + id + ')"></button>'
                + '<input type="hidden" name="produto_ids[]" value="' + id + '">'
                + '</span>';
        }
        selecionadosEl.innerHTML = html;
        atualizarContador();
    }

    window.removerProdutoPromo = function(id) {
        delete produtosSelecionados[id];
        renderSelecionados();
        // Desmarcar checkbox se visível
        var cb = document.getElementById('prod_ajax_' + id);
        if (cb) cb.checked = false;
    };

    window.toggleProdutoPromo = function(id, nome, preco, checked) {
        if (checked) {
            produtosSelecionados[id] = { nome: nome, preco: preco };
        } else {
            delete produtosSelecionados[id];
        }
        renderSelecionados();
    };

    if (buscaInp) {
        buscaInp.addEventListener('input', function() {
            var term = this.value.trim();
            if (buscaTimer) clearTimeout(buscaTimer);
            if (term.length < 2) {
                listaEl.innerHTML = '<div class="text-muted small text-center py-3"><?= htmlspecialchars(__('admin.scheduled_promos.type_2_chars', 'Digite pelo menos 2 caracteres para buscar...'), ENT_QUOTES, 'UTF-8') ?></div>';
                return;
            }
            listaEl.innerHTML = '<div class="text-muted small text-center py-2"><i class="fas fa-spinner fa-spin me-1"></i><?= htmlspecialchars(__('admin.scheduled_promos.js_searching', 'Buscando...'), ENT_QUOTES, 'UTF-8') ?></div>';
            buscaTimer = setTimeout(function() {
                fetch('/admin/promocoes-agendadas/buscar-produtos?q=' + encodeURIComponent(term))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var prods = data.produtos || [];
                        if (prods.length === 0) {
                            listaEl.innerHTML = '<div class="text-muted small text-center py-3"><?= htmlspecialchars(__('admin.scheduled_promos.js_no_products', 'Nenhum produto encontrado.'), ENT_QUOTES, 'UTF-8') ?></div>';
                            return;
                        }
                        var html = '';
                        prods.forEach(function(p) {
                            var checked = produtosSelecionados[p.id] ? ' checked' : '';
                            var imgSrc = p.imagem || '/uploads/produtos/placeholder.jpg';
                            html += '<div class="form-check d-flex align-items-center gap-2 py-1 border-bottom">'
                                + '<input class="form-check-input mt-0" type="checkbox" id="prod_ajax_' + p.id + '" value="' + p.id + '"' + checked
                                + ' onchange="toggleProdutoPromo(' + p.id + ',\'' + p.nome.replace(/'/g, "\\'") + '\',' + (p.preco||0) + ',this.checked)">'
                                + '<img src="' + imgSrc + '" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;" onerror="this.src=\'/uploads/produtos/placeholder.jpg\'">'
                                + '<label class="form-check-label small flex-grow-1" for="prod_ajax_' + p.id + '">'
                                + p.nome + ' <span class="text-muted">(US$ ' + Number(p.preco||0).toFixed(2) + ')</span>'
                                + '</label></div>';
                        });
                        html += '<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll(\'#listaProdutosPromo input[type=checkbox]\').forEach(function(c){c.checked=true;toggleProdutoPromo(Number(c.value),c.nextElementSibling.nextElementSibling.textContent.split(\'(\')[0].trim(),0,true)})"><?= htmlspecialchars(__('admin.scheduled_promos.js_select_all_visible', 'Selecionar todos visíveis'), ENT_QUOTES, 'UTF-8') ?></button></div>';
                        listaEl.innerHTML = html;
                    })
                    .catch(function() {
                        listaEl.innerHTML = '<div class="text-danger small text-center py-3"><?= htmlspecialchars(__('admin.scheduled_promos.js_error_search', 'Erro ao buscar.'), ENT_QUOTES, 'UTF-8') ?></div>';
                    });
            }, 300);
        });
    }

    // Sufixo do desconto
    var tipoSel = document.getElementById('promoDescontoTipo');
    var suffix = document.getElementById('promoDescontoSuffix');
    if (tipoSel && suffix) {
        tipoSel.addEventListener('change', function() {
            suffix.textContent = this.value === 'percentual' ? '%' : 'US$';
        });
    }
});
</script>
