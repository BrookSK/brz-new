<?php
$eventos = $eventos ?? [];
$ano = $ano ?? date('Y');
$mes = $mes ?? 0;
$pais = $pais ?? '';

$meses = ['', __('admin.mkt_calendar.month.jan','Janeiro'), __('admin.mkt_calendar.month.feb','Fevereiro'), __('admin.mkt_calendar.month.mar','Março'), __('admin.mkt_calendar.month.apr','Abril'), __('admin.mkt_calendar.month.may','Maio'), __('admin.mkt_calendar.month.jun','Junho'), __('admin.mkt_calendar.month.jul','Julho'), __('admin.mkt_calendar.month.aug','Agosto'), __('admin.mkt_calendar.month.sep','Setembro'), __('admin.mkt_calendar.month.oct','Outubro'), __('admin.mkt_calendar.month.nov','Novembro'), __('admin.mkt_calendar.month.dec','Dezembro')];
$categorias = ['comemorativa' => __('admin.mkt_calendar.cat.commemorative','Comemorativa'), 'promocional' => __('admin.mkt_calendar.cat.promotional','Promocional'), 'sazonal' => __('admin.mkt_calendar.cat.seasonal','Sazonal'), 'custom' => __('admin.mkt_calendar.cat.custom','Personalizado')];
$paisLabels = ['BR' => __('admin.mkt_calendar.country.br','Brasil'), 'US' => __('admin.mkt_calendar.country.us','Estados Unidos'), 'GLOBAL' => __('admin.mkt_calendar.country.global','Global')];

// Agrupar eventos por mês
$eventosPorMes = [];
foreach ($eventos as $ev) {
    $m = (int) date('m', strtotime($ev['data_evento']));
    $eventosPorMes[$m][] = $ev;
}
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title"><?= htmlspecialchars(__('admin.mkt_calendar.title', 'Calendário de Marketing'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="page-subtitle"><?= htmlspecialchars(__('admin.mkt_calendar.subtitle', 'Gerencie datas comemorativas e oportunidades de campanha'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="gerarComIA()">
                <i class="fas fa-robot me-1"></i><?= htmlspecialchars(__('admin.mkt_calendar.generate_ai', 'Gerar com IA'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalEvento" onclick="novoEvento()">
                <i class="fas fa-plus me-1"></i><?= htmlspecialchars(__('admin.mkt_calendar.new_event', 'Novo Evento'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="d-flex align-items-end flex-wrap gap-3">
                <div>
                    <label class="form-label small text-muted mb-1"><?= htmlspecialchars(__('admin.mkt_calendar.year', 'Ano'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="ano" class="form-select form-select-sm" style="width:100px;">
                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $ano ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1"><?= htmlspecialchars(__('admin.mkt_calendar.month', 'Mês'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="mes" class="form-select form-select-sm" style="width:140px;">
                        <option value="0"><?= htmlspecialchars(__('common.all', 'Todos'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1"><?= htmlspecialchars(__('admin.mkt_calendar.country', 'País'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="pais" class="form-select form-select-sm" style="width:140px;">
                        <option value=""><?= htmlspecialchars(__('common.all', 'Todos'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="BR" <?= $pais === 'BR' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.mkt_calendar.country.br', 'Brasil'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="US" <?= $pais === 'US' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.mkt_calendar.country.us', 'Estados Unidos'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="GLOBAL" <?= $pais === 'GLOBAL' ? 'selected' : '' ?>><?= htmlspecialchars(__('admin.mkt_calendar.country.global', 'Global'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-dark btn-sm px-3"><i class="fas fa-filter me-1"></i><?= htmlspecialchars(__('admin.mkt_calendar.filter', 'Filtrar'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-primary"><?= count($eventos) ?></div>
                <div class="text-muted small"><?= htmlspecialchars(__('admin.mkt_calendar.stats.events_period', 'Eventos no período'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-success"><?= count(array_filter($eventos, fn($e) => $e['pais'] === 'BR')) ?></div>
                <div class="text-muted small"><?= htmlspecialchars(__('admin.mkt_calendar.country.br', 'Brasil'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-info"><?= count(array_filter($eventos, fn($e) => $e['pais'] === 'US')) ?></div>
                <div class="text-muted small"><?= htmlspecialchars(__('admin.mkt_calendar.stats.usa', 'EUA'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <?php
                $proximos = array_filter($eventos, fn($e) => $e['data_evento'] >= date('Y-m-d') && $e['ativo']);
                ?>
                <div class="fs-3 fw-bold text-warning"><?= count($proximos) ?></div>
                <div class="text-muted small"><?= htmlspecialchars(__('admin.mkt_calendar.stats.upcoming', 'Próximos'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
    </div>

    <!-- Eventos por mês -->
    <?php if (empty($eventos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-calendar-alt text-muted d-block mb-3" style="font-size:48px;opacity:.3;"></i>
            <h5 class="text-muted"><?= htmlspecialchars(__('admin.mkt_calendar.empty', 'Nenhum evento cadastrado'), ENT_QUOTES, 'UTF-8') ?></h5>
            <p class="text-muted small"><?= htmlspecialchars(__('admin.mkt_calendar.empty_hint', 'Clique em "Gerar com IA" para criar sugestões automáticas ou adicione manualmente.'), ENT_QUOTES, 'UTF-8') ?></p>
            <button class="btn btn-primary btn-sm" onclick="gerarComIA()"><i class="fas fa-robot me-1"></i><?= htmlspecialchars(__('admin.mkt_calendar.generate_suggestions', 'Gerar Sugestões com IA'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
    <?php else: ?>
        <?php for ($m = 1; $m <= 12; $m++):
            if (empty($eventosPorMes[$m])) continue;
            $mesAtual = ($m == (int)date('m') && $ano == (int)date('Y'));
        ?>
        <div class="card border-0 shadow-sm mb-3" <?= $mesAtual ? 'style="border-left:4px solid #3b82f6 !important;"' : '' ?>>
            <div class="card-header bg-white border-0 py-2 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 small">
                    <?= $mesAtual ? '<i class="fas fa-circle text-primary me-1" style="font-size:8px;"></i>' : '' ?>
                    <?= $meses[$m] ?> <?= $ano ?>
                    <span class="badge bg-secondary ms-1"><?= count($eventosPorMes[$m]) ?></span>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;"></th>
                                <th><?= htmlspecialchars(__('admin.mkt_calendar.col.event', 'Evento'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.mkt_calendar.col.date', 'Data'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.mkt_calendar.col.country', 'País'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.mkt_calendar.col.category', 'Categoria'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.mkt_calendar.col.origin', 'Origem'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th class="text-end"><?= htmlspecialchars(__('admin.mkt_calendar.col.actions', 'Ações'), ENT_QUOTES, 'UTF-8') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($eventosPorMes[$m] as $ev):
                            $isPast = $ev['data_evento'] < date('Y-m-d');
                            $isToday = $ev['data_evento'] === date('Y-m-d');
                            $daysUntil = $isToday ? 0 : (int)(new DateTime($ev['data_evento']))->diff(new DateTime())->days;
                            $rowStyle = !$ev['ativo'] ? 'opacity:.5;' : ($isPast ? 'opacity:.6;' : '');
                        ?>
                            <tr style="<?= $rowStyle ?>">
                                <td class="text-center" style="font-size:1.2rem;"><?= htmlspecialchars($ev['emoji'] ?: '📅') ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($ev['titulo']) ?></div>
                                    <?php if (!empty($ev['descricao'])): ?>
                                    <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars(mb_substr($ev['descricao'], 0, 80)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?= date('d/m', strtotime($ev['data_evento'])) ?>
                                    <?php if ($isToday): ?><span class="badge bg-success ms-1"><?= htmlspecialchars(__('admin.mkt_calendar.today', 'Hoje'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php elseif (!$isPast && $daysUntil <= 7): ?><span class="badge bg-danger ms-1"><?= $daysUntil ?>d</span>
                                    <?php elseif (!$isPast && $daysUntil <= 30): ?><span class="badge bg-warning text-dark ms-1"><?= $daysUntil ?>d</span>
                                    <?php elseif (!$isPast): ?><span class="badge bg-light text-dark ms-1"><?= $daysUntil ?>d</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ev['pais'] === 'BR'): ?><span class="badge bg-success">BR</span>
                                    <?php elseif ($ev['pais'] === 'US'): ?><span class="badge bg-primary">US</span>
                                    <?php else: ?><span class="badge bg-secondary"><?= htmlspecialchars(__('admin.mkt_calendar.country.global', 'Global'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-muted small"><?= htmlspecialchars($categorias[$ev['categoria']] ?? $ev['categoria']) ?></span></td>
                                <td>
                                    <?php if ($ev['origem'] === 'ia'): ?><span class="badge bg-info text-dark"><i class="fas fa-robot me-1"></i><?= htmlspecialchars(__('admin.mkt_calendar.origin.ai', 'IA'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php elseif ($ev['origem'] === 'sistema'): ?><span class="badge bg-secondary"><?= htmlspecialchars(__('admin.mkt_calendar.origin.system', 'Sistema'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?><span class="badge bg-dark"><?= htmlspecialchars(__('admin.mkt_calendar.origin.manual', 'Manual'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editarEvento(<?= htmlspecialchars(json_encode($ev)) ?>)" title="<?= htmlspecialchars(__('common.edit', 'Editar'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-sm btn-outline-<?= $ev['ativo'] ? 'warning' : 'success' ?>" onclick="toggleEvento(<?= $ev['id'] ?>)" title="<?= $ev['ativo'] ? htmlspecialchars(__('admin.mkt_calendar.deactivate', 'Desativar'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(__('admin.mkt_calendar.activate', 'Ativar'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-<?= $ev['ativo'] ? 'eye-slash' : 'eye' ?>"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="excluirEvento(<?= $ev['id'] ?>, '<?= htmlspecialchars(addslashes($ev['titulo'])) ?>')" title="<?= htmlspecialchars(__('common.delete', 'Excluir'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    <?php endif; ?>
</div>

<!-- Modal Criar/Editar -->
<div class="modal fade" id="modalEvento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEventoTitle"><?= htmlspecialchars(__('admin.mkt_calendar.new_event', 'Novo Evento'), ENT_QUOTES, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="evento_id" value="0">
                <div class="mb-3">
                    <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.field.title', 'Título *'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" class="form-control" id="evento_titulo" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.field.date', 'Data *'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="date" class="form-control" id="evento_data" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.country', 'País'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="evento_pais">
                            <option value="BR"><?= htmlspecialchars(__('admin.mkt_calendar.country.br', 'Brasil'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="US"><?= htmlspecialchars(__('admin.mkt_calendar.country.us', 'Estados Unidos'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="GLOBAL"><?= htmlspecialchars(__('admin.mkt_calendar.country.global', 'Global'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.field.emoji', 'Emoji'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="text" class="form-control" id="evento_emoji" value="📅" maxlength="4">
                    </div>
                    <div class="col-4">
                        <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.field.color', 'Cor'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="color" class="form-control form-control-color" id="evento_cor" value="#3b82f6">
                    </div>
                    <div class="col-4">
                        <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.col.category', 'Categoria'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="evento_categoria">
                            <option value="comemorativa"><?= htmlspecialchars(__('admin.mkt_calendar.cat.commemorative', 'Comemorativa'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="promocional"><?= htmlspecialchars(__('admin.mkt_calendar.cat.promotional', 'Promocional'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="sazonal"><?= htmlspecialchars(__('admin.mkt_calendar.cat.seasonal', 'Sazonal'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="custom"><?= htmlspecialchars(__('admin.mkt_calendar.cat.custom', 'Personalizado'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.field.description', 'Descrição / Dica de Marketing'), ENT_QUOTES, 'UTF-8') ?></label>
                    <textarea class="form-control" id="evento_descricao" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars(__('common.cancel', 'Cancelar'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn btn-primary" onclick="salvarEvento()"><i class="fas fa-save me-1"></i><?= htmlspecialchars(__('common.save', 'Salvar'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal IA -->
<div class="modal fade" id="modalIA" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-robot me-2"></i><?= htmlspecialchars(__('admin.mkt_calendar.generate_ai', 'Gerar com IA'), ENT_QUOTES, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted"><?= htmlspecialchars(__('admin.mkt_calendar.ai_hint', 'A IA vai sugerir datas comemorativas e oportunidades de marketing. Eventos duplicados serão ignorados.'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="mb-3">
                    <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.year', 'Ano'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="number" class="form-control" id="ia_ano" value="<?= $ano ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= htmlspecialchars(__('admin.mkt_calendar.country_optional', 'País (opcional)'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select class="form-select" id="ia_pais">
                        <option value=""><?= htmlspecialchars(__('admin.mkt_calendar.both_br_us', 'Ambos (BR + US)'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="BR"><?= htmlspecialchars(__('admin.mkt_calendar.only_brazil', 'Apenas Brasil'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="US"><?= htmlspecialchars(__('admin.mkt_calendar.only_usa', 'Apenas EUA'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars(__('common.cancel', 'Cancelar'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn btn-primary" id="btnGerarIA" onclick="confirmarGerarIA()"><i class="fas fa-magic me-1"></i><?= htmlspecialchars(__('admin.mkt_calendar.generate', 'Gerar'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.MKT_CAL_I18N = {
    new_event: <?= json_encode(__('admin.mkt_calendar.new_event', 'Novo Evento'), JSON_UNESCAPED_UNICODE) ?>,
    edit_event: <?= json_encode(__('admin.mkt_calendar.edit_event', 'Editar Evento'), JSON_UNESCAPED_UNICODE) ?>,
    error_save: <?= json_encode(__('admin.mkt_calendar.js.error_save', 'Erro ao salvar'), JSON_UNESCAPED_UNICODE) ?>,
    error: <?= json_encode(__('admin.mkt_calendar.js.error', 'Erro'), JSON_UNESCAPED_UNICODE) ?>,
    error_prefix: <?= json_encode(__('admin.mkt_calendar.js.error_prefix', 'Erro: '), JSON_UNESCAPED_UNICODE) ?>,
    confirm_delete: <?= json_encode(__('admin.mkt_calendar.js.confirm_delete', 'Excluir evento "{t}"?'), JSON_UNESCAPED_UNICODE) ?>,
    generating: <?= json_encode(__('admin.mkt_calendar.js.generating', 'Gerando...'), JSON_UNESCAPED_UNICODE) ?>,
    generate: <?= json_encode(__('admin.mkt_calendar.generate', 'Gerar'), JSON_UNESCAPED_UNICODE) ?>,
    error_generate: <?= json_encode(__('admin.mkt_calendar.js.error_generate', 'Erro ao gerar'), JSON_UNESCAPED_UNICODE) ?>,
    gen_success: <?= json_encode(__('admin.mkt_calendar.js.gen_success', 'Gerado com sucesso! {ins} novos eventos adicionados (de {tot} sugeridos).'), JSON_UNESCAPED_UNICODE) ?>
};
function novoEvento() {
    document.getElementById('modalEventoTitle').textContent = window.MKT_CAL_I18N.new_event;
    document.getElementById('evento_id').value = '0';
    document.getElementById('evento_titulo').value = '';
    document.getElementById('evento_data').value = '';
    document.getElementById('evento_pais').value = 'BR';
    document.getElementById('evento_emoji').value = '📅';
    document.getElementById('evento_cor').value = '#3b82f6';
    document.getElementById('evento_categoria').value = 'comemorativa';
    document.getElementById('evento_descricao').value = '';
}

function editarEvento(ev) {
    document.getElementById('modalEventoTitle').textContent = window.MKT_CAL_I18N.edit_event;
    document.getElementById('evento_id').value = ev.id;
    document.getElementById('evento_titulo').value = ev.titulo;
    document.getElementById('evento_data').value = ev.data_evento;
    document.getElementById('evento_pais').value = ev.pais;
    document.getElementById('evento_emoji').value = ev.emoji || '📅';
    document.getElementById('evento_cor').value = ev.cor || '#3b82f6';
    document.getElementById('evento_categoria').value = ev.categoria || 'comemorativa';
    document.getElementById('evento_descricao').value = ev.descricao || '';
    new bootstrap.Modal(document.getElementById('modalEvento')).show();
}

function salvarEvento() {
    const body = new FormData();
    body.append('id', document.getElementById('evento_id').value);
    body.append('titulo', document.getElementById('evento_titulo').value);
    body.append('data_evento', document.getElementById('evento_data').value);
    body.append('pais', document.getElementById('evento_pais').value);
    body.append('emoji', document.getElementById('evento_emoji').value);
    body.append('cor', document.getElementById('evento_cor').value);
    body.append('categoria', document.getElementById('evento_categoria').value);
    body.append('descricao', document.getElementById('evento_descricao').value);

    fetch('/admin/marketing-calendar/salvar', { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            if (d.ok) { location.reload(); }
            else { alert(d.error || window.MKT_CAL_I18N.error_save); }
        })
        .catch(e => alert(window.MKT_CAL_I18N.error_prefix + e.message));
}

function excluirEvento(id, titulo) {
    if (!confirm(window.MKT_CAL_I18N.confirm_delete.replace('{t}', titulo))) return;
    const body = new FormData();
    body.append('id', id);
    fetch('/admin/marketing-calendar/excluir', { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); else alert(d.error || window.MKT_CAL_I18N.error); })
        .catch(e => alert(window.MKT_CAL_I18N.error_prefix + e.message));
}

function toggleEvento(id) {
    const body = new FormData();
    body.append('id', id);
    fetch('/admin/marketing-calendar/toggle', { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); else alert(d.error || window.MKT_CAL_I18N.error); })
        .catch(e => alert(window.MKT_CAL_I18N.error_prefix + e.message));
}

function gerarComIA() {
    new bootstrap.Modal(document.getElementById('modalIA')).show();
}

function confirmarGerarIA() {
    const btn = document.getElementById('btnGerarIA');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + window.MKT_CAL_I18N.generating;

    const body = new FormData();
    body.append('ano', document.getElementById('ia_ano').value);
    body.append('pais', document.getElementById('ia_pais').value);

    fetch('/admin/marketing-calendar/gerar-ia', { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic me-1"></i>' + window.MKT_CAL_I18N.generate;
            if (d.ok) {
                alert(window.MKT_CAL_I18N.gen_success.replace('{ins}', d.inseridos).replace('{tot}', d.total_gerados));
                location.reload();
            } else {
                alert(d.error || window.MKT_CAL_I18N.error_generate);
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic me-1"></i>' + window.MKT_CAL_I18N.generate;
            alert(window.MKT_CAL_I18N.error_prefix + e.message);
        });
}
</script>
