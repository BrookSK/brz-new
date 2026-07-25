<?php
$eventos = $eventos ?? [];
$ano = $ano ?? date('Y');
$mes = $mes ?? 0;
$pais = $pais ?? '';

$meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$categorias = ['comemorativa' => 'Comemorativa', 'promocional' => 'Promocional', 'sazonal' => 'Sazonal', 'custom' => 'Personalizado'];
$paisLabels = ['BR' => 'Brasil', 'US' => 'Estados Unidos', 'GLOBAL' => 'Global'];

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
            <h1 class="page-title">Calendário de Marketing</h1>
            <p class="page-subtitle">Gerencie datas comemorativas e oportunidades de campanha</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="gerarComIA()">
                <i class="fas fa-robot me-1"></i>Gerar com IA
            </button>
            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalEvento" onclick="novoEvento()">
                <i class="fas fa-plus me-1"></i>Novo Evento
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="d-flex align-items-end flex-wrap gap-3">
                <div>
                    <label class="form-label small text-muted mb-1">Ano</label>
                    <select name="ano" class="form-select form-select-sm" style="width:100px;">
                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $ano ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Mês</label>
                    <select name="mes" class="form-select form-select-sm" style="width:140px;">
                        <option value="0">Todos</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">País</label>
                    <select name="pais" class="form-select form-select-sm" style="width:140px;">
                        <option value="">Todos</option>
                        <option value="BR" <?= $pais === 'BR' ? 'selected' : '' ?>>Brasil</option>
                        <option value="US" <?= $pais === 'US' ? 'selected' : '' ?>>Estados Unidos</option>
                        <option value="GLOBAL" <?= $pais === 'GLOBAL' ? 'selected' : '' ?>>Global</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-dark btn-sm px-3"><i class="fas fa-filter me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-primary"><?= count($eventos) ?></div>
                <div class="text-muted small">Eventos no período</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-success"><?= count(array_filter($eventos, fn($e) => $e['pais'] === 'BR')) ?></div>
                <div class="text-muted small">Brasil</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-info"><?= count(array_filter($eventos, fn($e) => $e['pais'] === 'US')) ?></div>
                <div class="text-muted small">EUA</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <?php
                $proximos = array_filter($eventos, fn($e) => $e['data_evento'] >= date('Y-m-d') && $e['ativo']);
                ?>
                <div class="fs-3 fw-bold text-warning"><?= count($proximos) ?></div>
                <div class="text-muted small">Próximos</div>
            </div>
        </div>
    </div>

    <!-- Eventos por mês -->
    <?php if (empty($eventos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-calendar-alt text-muted d-block mb-3" style="font-size:48px;opacity:.3;"></i>
            <h5 class="text-muted">Nenhum evento cadastrado</h5>
            <p class="text-muted small">Clique em "Gerar com IA" para criar sugestões automáticas ou adicione manualmente.</p>
            <button class="btn btn-primary btn-sm" onclick="gerarComIA()"><i class="fas fa-robot me-1"></i>Gerar Sugestões com IA</button>
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
                                <th>Evento</th>
                                <th>Data</th>
                                <th>País</th>
                                <th>Categoria</th>
                                <th>Origem</th>
                                <th class="text-end">Ações</th>
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
                                    <?php if ($isToday): ?><span class="badge bg-success ms-1">Hoje</span>
                                    <?php elseif (!$isPast && $daysUntil <= 7): ?><span class="badge bg-danger ms-1"><?= $daysUntil ?>d</span>
                                    <?php elseif (!$isPast && $daysUntil <= 30): ?><span class="badge bg-warning text-dark ms-1"><?= $daysUntil ?>d</span>
                                    <?php elseif (!$isPast): ?><span class="badge bg-light text-dark ms-1"><?= $daysUntil ?>d</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ev['pais'] === 'BR'): ?><span class="badge bg-success">BR</span>
                                    <?php elseif ($ev['pais'] === 'US'): ?><span class="badge bg-primary">US</span>
                                    <?php else: ?><span class="badge bg-secondary">Global</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-muted small"><?= htmlspecialchars($categorias[$ev['categoria']] ?? $ev['categoria']) ?></span></td>
                                <td>
                                    <?php if ($ev['origem'] === 'ia'): ?><span class="badge bg-info text-dark"><i class="fas fa-robot me-1"></i>IA</span>
                                    <?php elseif ($ev['origem'] === 'sistema'): ?><span class="badge bg-secondary">Sistema</span>
                                    <?php else: ?><span class="badge bg-dark">Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editarEvento(<?= htmlspecialchars(json_encode($ev)) ?>)" title="Editar"><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-sm btn-outline-<?= $ev['ativo'] ? 'warning' : 'success' ?>" onclick="toggleEvento(<?= $ev['id'] ?>)" title="<?= $ev['ativo'] ? 'Desativar' : 'Ativar' ?>"><i class="fas fa-<?= $ev['ativo'] ? 'eye-slash' : 'eye' ?>"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="excluirEvento(<?= $ev['id'] ?>, '<?= htmlspecialchars(addslashes($ev['titulo'])) ?>')" title="Excluir"><i class="fas fa-trash"></i></button>
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
                <h5 class="modal-title" id="modalEventoTitle">Novo Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="evento_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Título *</label>
                    <input type="text" class="form-control" id="evento_titulo" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Data *</label>
                        <input type="date" class="form-control" id="evento_data" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">País</label>
                        <select class="form-select" id="evento_pais">
                            <option value="BR">Brasil</option>
                            <option value="US">Estados Unidos</option>
                            <option value="GLOBAL">Global</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <label class="form-label">Emoji</label>
                        <input type="text" class="form-control" id="evento_emoji" value="📅" maxlength="4">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Cor</label>
                        <input type="color" class="form-control form-control-color" id="evento_cor" value="#3b82f6">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" id="evento_categoria">
                            <option value="comemorativa">Comemorativa</option>
                            <option value="promocional">Promocional</option>
                            <option value="sazonal">Sazonal</option>
                            <option value="custom">Personalizado</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição / Dica de Marketing</label>
                    <textarea class="form-control" id="evento_descricao" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarEvento()"><i class="fas fa-save me-1"></i>Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal IA -->
<div class="modal fade" id="modalIA" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-robot me-2"></i>Gerar com IA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">A IA vai sugerir datas comemorativas e oportunidades de marketing. Eventos duplicados serão ignorados.</p>
                <div class="mb-3">
                    <label class="form-label">Ano</label>
                    <input type="number" class="form-control" id="ia_ano" value="<?= $ano ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">País (opcional)</label>
                    <select class="form-select" id="ia_pais">
                        <option value="">Ambos (BR + US)</option>
                        <option value="BR">Apenas Brasil</option>
                        <option value="US">Apenas EUA</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGerarIA" onclick="confirmarGerarIA()"><i class="fas fa-magic me-1"></i>Gerar</button>
            </div>
        </div>
    </div>
</div>

<script>
function novoEvento() {
    document.getElementById('modalEventoTitle').textContent = 'Novo Evento';
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
    document.getElementById('modalEventoTitle').textContent = 'Editar Evento';
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
            else { alert(d.error || 'Erro ao salvar'); }
        })
        .catch(e => alert('Erro: ' + e.message));
}

function excluirEvento(id, titulo) {
    if (!confirm('Excluir evento "' + titulo + '"?')) return;
    const body = new FormData();
    body.append('id', id);
    fetch('/admin/marketing-calendar/excluir', { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); else alert(d.error || 'Erro'); })
        .catch(e => alert('Erro: ' + e.message));
}

function toggleEvento(id) {
    const body = new FormData();
    body.append('id', id);
    fetch('/admin/marketing-calendar/toggle', { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); else alert(d.error || 'Erro'); })
        .catch(e => alert('Erro: ' + e.message));
}

function gerarComIA() {
    new bootstrap.Modal(document.getElementById('modalIA')).show();
}

function confirmarGerarIA() {
    const btn = document.getElementById('btnGerarIA');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';

    const body = new FormData();
    body.append('ano', document.getElementById('ia_ano').value);
    body.append('pais', document.getElementById('ia_pais').value);

    fetch('/admin/marketing-calendar/gerar-ia', { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic me-1"></i>Gerar';
            if (d.ok) {
                alert('Gerado com sucesso! ' + d.inseridos + ' novos eventos adicionados (de ' + d.total_gerados + ' sugeridos).');
                location.reload();
            } else {
                alert(d.error || 'Erro ao gerar');
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic me-1"></i>Gerar';
            alert('Erro: ' + e.message);
        });
}
</script>
