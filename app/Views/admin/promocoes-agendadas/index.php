<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css" rel="stylesheet">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title">Promoções Agendadas</h1>
        <p class="page-subtitle">Agende campanhas de desconto com início e fim automáticos</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaPromo">
        <i class="fas fa-plus me-1"></i>Nova Promoção
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
    <div class="card-header bg-white fw-semibold">Visualização em Calendário</div>
    <div class="card-body">
        <div id="calendario" style="min-height:500px;"></div>
    </div>
</div>

<!-- Lista de Promoções -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Todas as Promoções</div>
    <div class="card-body">
        <?php if (empty($promocoes)): ?>
            <div class="text-muted text-center py-4">Nenhuma promoção agendada ainda.</div>
        <?php else: ?>
            <!-- Desktop: Table -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Desconto</th>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Produtos</th>
                            <th>Status</th>
                            <th>Criado por</th>
                            <th class="text-end">Ações</th>
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
                            <td><span class="badge bg-light text-dark"><?= (int) $p['total_produtos'] ?> produtos</span></td>
                            <td><span class="badge bg-<?= $statusCor ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars((string) ($p['criado_por_nome'] ?? '-')) ?></td>
                            <td class="text-end">
                                <?php if (in_array($p['status'], ['agendada', 'ativa'])): ?>
                                    <form method="POST" action="/admin/promocoes-agendadas/cancelar/<?= (int) $p['id'] ?>" class="d-inline" onsubmit="return confirm('Cancelar esta promoção? Os preços dos produtos serão restaurados.')">
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
                            <span><?= (int) $p['total_produtos'] ?> produtos</span>
                        </div>
                        <div class="small text-muted mt-1">
                            <?= date('d/m/Y H:i', strtotime($p['inicio'])) ?> → <?= date('d/m/Y H:i', strtotime($p['fim'])) ?>
                        </div>
                        <?php if (in_array($p['status'], ['agendada', 'ativa'])): ?>
                            <form method="POST" action="/admin/promocoes-agendadas/cancelar/<?= (int) $p['id'] ?>" class="mt-2" onsubmit="return confirm('Cancelar esta promoção?')">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">Cancelar promoção</button>
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
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Nova Promoção Agendada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nome da campanha</label>
                            <input type="text" class="form-control" name="nome" required placeholder="Ex: Black Friday, Promoção de Inverno...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipo de desconto</label>
                            <select class="form-select" name="desconto_tipo" id="promoDescontoTipo">
                                <option value="percentual">Percentual (%)</option>
                                <option value="fixo">Valor fixo (US$)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Valor do desconto</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="desconto_valor" step="0.01" min="0.01" required placeholder="10">
                                <span class="input-group-text" id="promoDescontoSuffix">%</span>
                            </div>
                        </div>
                        <div class="col-md-4"></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Início</label>
                            <input type="datetime-local" class="form-control" name="inicio" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fim</label>
                            <input type="datetime-local" class="form-control" name="fim" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Produtos (selecione os que participam)</label>
                            <div class="mb-2">
                                <input type="text" class="form-control" id="buscaProdutoPromo" placeholder="Digite para buscar produtos..." autocomplete="off">
                            </div>
                            <div id="produtosSelecionados" class="mb-2"></div>
                            <div class="border rounded p-2" style="max-height:250px;overflow-y:auto;" id="listaProdutosPromo">
                                <div class="text-muted small text-center py-3">Digite pelo menos 2 caracteres para buscar...</div>
                            </div>
                            <div class="mt-1 small text-muted" id="contadorProdutos">0 produtos selecionados</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Criar Promoção</button>
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
        if (contadorEl) contadorEl.textContent = n + ' produto' + (n !== 1 ? 's' : '') + ' selecionado' + (n !== 1 ? 's' : '');
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
                listaEl.innerHTML = '<div class="text-muted small text-center py-3">Digite pelo menos 2 caracteres para buscar...</div>';
                return;
            }
            listaEl.innerHTML = '<div class="text-muted small text-center py-2"><i class="fas fa-spinner fa-spin me-1"></i>Buscando...</div>';
            buscaTimer = setTimeout(function() {
                fetch('/admin/promocoes-agendadas/buscar-produtos?q=' + encodeURIComponent(term))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var prods = data.produtos || [];
                        if (prods.length === 0) {
                            listaEl.innerHTML = '<div class="text-muted small text-center py-3">Nenhum produto encontrado.</div>';
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
                        html += '<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll(\'#listaProdutosPromo input[type=checkbox]\').forEach(function(c){c.checked=true;toggleProdutoPromo(Number(c.value),c.nextElementSibling.nextElementSibling.textContent.split(\'(\')[0].trim(),0,true)})">Selecionar todos visíveis</button></div>';
                        listaEl.innerHTML = html;
                    })
                    .catch(function() {
                        listaEl.innerHTML = '<div class="text-danger small text-center py-3">Erro ao buscar.</div>';
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
