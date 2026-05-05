<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css" rel="stylesheet">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Promoções Agendadas</h2>
        <p class="text-muted mb-0">Agende campanhas de desconto com início e fim automáticos</p>
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

<!-- Calendário -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="fas fa-calendar me-2"></i>Visualização em Calendário</div>
    <div class="card-body">
        <div id="calendario" style="min-height:500px;"></div>
    </div>
</div>

<!-- Lista de Promoções -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="fas fa-list me-2"></i>Todas as Promoções</div>
    <div class="card-body">
        <?php if (empty($promocoes)): ?>
            <div class="text-muted text-center py-4">Nenhuma promoção agendada ainda.</div>
        <?php else: ?>
            <div class="table-responsive">
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
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                                <input type="text" class="form-control form-control-sm" id="buscaProdutoPromo" placeholder="Buscar produto por nome ou SKU...">
                            </div>
                            <div class="border rounded p-2" style="max-height:250px;overflow-y:auto;" id="listaProdutosPromo">
                                <?php foreach ($produtos as $prod): ?>
                                    <div class="form-check produto-item" data-search="<?= htmlspecialchars(strtolower(($prod['nome'] ?? '') . ' ' . ($prod['sku'] ?? ''))) ?>">
                                        <input class="form-check-input" type="checkbox" name="produto_ids[]" value="<?= (int) $prod['id'] ?>" id="prod_<?= (int) $prod['id'] ?>">
                                        <label class="form-check-label small" for="prod_<?= (int) $prod['id'] ?>">
                                            <?= htmlspecialchars($prod['nome'] ?? '') ?>
                                            <span class="text-muted">(US$ <?= number_format((float) ($prod['preco'] ?? 0), 2) ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2 d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('#listaProdutosPromo input[type=checkbox]').forEach(c=>{if(c.closest('.produto-item').style.display!=='none')c.checked=true})">Selecionar todos visíveis</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('#listaProdutosPromo input[type=checkbox]').forEach(c=>c.checked=false)">Limpar seleção</button>
                            </div>
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

    // Busca de produtos
    var buscaInp = document.getElementById('buscaProdutoPromo');
    if (buscaInp) {
        buscaInp.addEventListener('input', function() {
            var term = this.value.toLowerCase();
            document.querySelectorAll('.produto-item').forEach(function(el) {
                var search = el.getAttribute('data-search') || '';
                el.style.display = (!term || search.indexOf(term) !== -1) ? '' : 'none';
            });
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
