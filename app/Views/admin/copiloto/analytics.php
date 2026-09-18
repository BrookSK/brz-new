<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title"><?= __('admin.copilot.analytics_page_title','Analytics — Co-Piloto Bri') ?></h1>
            <p class="page-subtitle"><?= __('admin.copilot.analytics_subtitle','Relatório de uso, perguntas frequentes e impacto em vendas') ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <select class="form-select form-select-sm" id="periodo-select" style="width:auto">
                <option value="7" <?= ($periodo ?? 7) == 7 ? 'selected' : '' ?>><?= __('admin.copilot.last_7_days','Últimos 7 dias') ?></option>
                <option value="30" <?= ($periodo ?? 7) == 30 ? 'selected' : '' ?>><?= __('admin.copilot.last_30_days','Últimos 30 dias') ?></option>
                <option value="90" <?= ($periodo ?? 7) == 90 ? 'selected' : '' ?>><?= __('admin.copilot.last_90_days','Últimos 90 dias') ?></option>
                <option value="365" <?= ($periodo ?? 7) == 365 ? 'selected' : '' ?>><?= __('admin.copilot.last_year','Último ano') ?></option>
            </select>
            <a href="/admin/copiloto" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i><?= __('common.back','Voltar') ?>
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center p-3 border-0 shadow-sm">
                <div class="fs-2 fw-bold text-primary"><?= number_format($kpis['total_sessoes'] ?? 0) ?></div>
                <small class="text-muted"><?= __('admin.copilot.sessions_in_period','Sessões no período') ?></small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3 border-0 shadow-sm">
                <div class="fs-2 fw-bold text-success"><?= number_format($kpis['total_mensagens'] ?? 0) ?></div>
                <small class="text-muted"><?= __('admin.copilot.messages_exchanged','Mensagens trocadas') ?></small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3 border-0 shadow-sm">
                <div class="fs-2 fw-bold text-warning"><?= number_format($kpis['total_usuarios_unicos'] ?? 0) ?></div>
                <small class="text-muted"><?= __('admin.copilot.unique_users','Usuários únicos') ?></small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3 border-0 shadow-sm">
                <div class="fs-2 fw-bold text-info"><?= number_format($kpis['pedidos_com_bot'] ?? 0) ?></div>
                <small class="text-muted"><?= __('admin.copilot.orders_via_bri','Pedidos via Bri') ?></small>
                <?php if (($kpis['total_pedidos_periodo'] ?? 0) > 0): ?>
                <small class="text-success d-block"><?= round(($kpis['pedidos_com_bot'] / $kpis['total_pedidos_periodo']) * 100) ?><?= __('admin.copilot.percent_of_total','% do total') ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Gráfico de mensagens por dia -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i><?= __('admin.copilot.messages_per_day','Mensagens por dia') ?></h6>
                </div>
                <div class="card-body">
                    <canvas id="chart-mensagens-dia" height="100"></canvas>
                </div>
            </div>
        </div>
        <!-- Horários de pico -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="mb-0"><i class="fas fa-clock me-2 text-warning"></i><?= __('admin.copilot.peak_hours','Horários de pico') ?></h6>
                </div>
                <div class="card-body">
                    <canvas id="chart-horarios" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Ações mais usadas -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2 text-success"></i><?= __('admin.copilot.most_executed_actions','Ações mais executadas') ?></h6>
                </div>
                <div class="card-body">
                    <canvas id="chart-acoes" height="220"></canvas>
                </div>
            </div>
        </div>
        <!-- Páginas com mais interação -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3">
                    <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2 text-danger"></i><?= __('admin.copilot.pages_most_interaction','Páginas com mais interação') ?></h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th><?= __('admin.copilot.col_page','Página') ?></th><th class="text-end"><?= __('admin.copilot.col_sessions','Sessões') ?></th><th class="text-end"><?= __('admin.copilot.col_msgs','Msgs') ?></th></tr></thead>
                        <tbody>
                        <?php foreach (($paginas ?? []) as $p): ?>
                        <tr>
                            <td class="small text-truncate" style="max-width:220px" title="<?= htmlspecialchars($p['pagina'] ?? '') ?>">
                                <?= htmlspecialchars($p['pagina'] ?? '/') ?>
                            </td>
                            <td class="text-end small"><?= number_format($p['sessoes'] ?? 0) ?></td>
                            <td class="text-end small"><?= number_format($p['mensagens'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Perguntas mais frequentes -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-question-circle me-2 text-info"></i><?= __('admin.copilot.most_frequent_questions','Perguntas mais frequentes dos usuários') ?></h6>
            <small class="text-muted"><?= __('admin.copilot.based_on_last_messages','Baseado nas últimas mensagens dos usuários') ?></small>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light"><tr><th>#</th><th><?= __('admin.copilot.col_message','Mensagem') ?></th><th><?= __('admin.copilot.col_page','Página') ?></th><th class="text-end"><?= __('admin.copilot.col_occurrences','Ocorrências') ?></th></tr></thead>
                <tbody>
                <?php foreach (($perguntas_frequentes ?? []) as $i => $p): ?>
                <tr>
                    <td class="text-muted small"><?= $i + 1 ?></td>
                    <td class="small"><?= htmlspecialchars(mb_substr($p['conteudo'] ?? '', 0, 120)) ?><?= mb_strlen($p['conteudo'] ?? '') > 120 ? '...' : '' ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($p['pagina'] ?? '') ?></td>
                    <td class="text-end small fw-bold"><?= $p['total'] ?? 1 ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($perguntas_frequentes)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3"><?= __('admin.copilot.no_messages_yet','Nenhuma mensagem registrada ainda') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pedidos com influência do bot -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-shopping-cart me-2 text-success"></i><?= __('admin.copilot.orders_influenced_by_bri','Pedidos com influência da Bri') ?></h6>
            <small class="text-muted"><?= __('admin.copilot.orders_same_day_chat','Pedidos feitos por usuários que usaram o chat no mesmo dia') ?></small>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= __('admin.copilot.col_order','Pedido') ?></th>
                        <th><?= __('admin.copilot.col_customer','Cliente') ?></th>
                        <th><?= __('admin.copilot.col_date','Data') ?></th>
                        <th><?= __('admin.copilot.col_total','Total') ?></th>
                        <th><?= __('common.status','Status') ?></th>
                        <th><?= __('admin.copilot.col_msgs_in_chat','Msgs no chat') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (($pedidos_bot ?? []) as $p): ?>
                <tr>
                    <td class="small"><a href="/admin/pedidos/<?= $p['pedido_id'] ?>" target="_blank">#<?= $p['pedido_id'] ?></a></td>
                    <td class="small"><?= htmlspecialchars($p['cliente_nome'] ?? '') ?></td>
                    <td class="small"><?= isset($p['data_pedido']) ? date('d/m/Y H:i', strtotime($p['data_pedido'])) : '' ?></td>
                    <td class="small fw-bold">
                        <?= $p['moeda'] === 'BRL' ? 'R$ ' : 'US$ ' ?>
                        <?= number_format((float)($p['total'] ?? 0), 2, ',', '.') ?>
                    </td>
                    <td class="small"><span class="badge bg-secondary"><?= htmlspecialchars($p['status'] ?? '') ?></span></td>
                    <td class="small text-center"><?= $p['msgs_chat'] ?? 0 ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pedidos_bot)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3"><?= __('admin.copilot.no_orders_influenced','Nenhum pedido com influência do bot no período') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($pedidos_bot)): ?>
        <div class="card-footer bg-white border-0 text-muted small">
            <?= __('admin.copilot.total_orders_influenced_by_bri','Total de pedidos com influência da Bri:') ?> <strong><?= count($pedidos_bot) ?></strong>
            <?php if (($kpis['valor_total_bot'] ?? 0) > 0): ?>
            | <?= __('admin.copilot.total_value','Valor total:') ?> <strong>R$ <?= number_format($kpis['valor_total_bot'], 2, ',', '.') ?></strong>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Dados do PHP para JS
var dadosDia = <?= json_encode($grafico_dias ?? [], JSON_UNESCAPED_UNICODE) ?>;
var dadosHorarios = <?= json_encode($grafico_horarios ?? [], JSON_UNESCAPED_UNICODE) ?>;
var dadosAcoes = <?= json_encode($grafico_acoes ?? [], JSON_UNESCAPED_UNICODE) ?>;

// Gráfico mensagens por dia
if (dadosDia.length > 0) {
    new Chart(document.getElementById('chart-mensagens-dia'), {
        type: 'line',
        data: {
            labels: dadosDia.map(function(d) { return d.dia }),
            datasets: [{
                label: '<?= htmlspecialchars(__('admin.copilot.chart_messages','Mensagens'), ENT_QUOTES, 'UTF-8') ?>',
                data: dadosDia.map(function(d) { return d.total }),
                borderColor: '#1d4ed8',
                backgroundColor: 'rgba(29,78,216,.08)',
                fill: true,
                tension: 0.3,
                pointRadius: 3
            }, {
                label: '<?= htmlspecialchars(__('admin.copilot.chart_sessions','Sessões'), ENT_QUOTES, 'UTF-8') ?>',
                data: dadosDia.map(function(d) { return d.sessoes }),
                borderColor: '#16a34a',
                backgroundColor: 'rgba(22,163,74,.08)',
                fill: true,
                tension: 0.3,
                pointRadius: 3
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });
}

// Gráfico horários de pico
if (dadosHorarios.length > 0) {
    var horas = Array.from({length: 24}, function(_, i) { return i + 'h' });
    var valoresHora = Array(24).fill(0);
    dadosHorarios.forEach(function(h) { valoresHora[parseInt(h.hora)] = parseInt(h.total) });
    new Chart(document.getElementById('chart-horarios'), {
        type: 'bar',
        data: {
            labels: horas,
            datasets: [{ label: '<?= htmlspecialchars(__('admin.copilot.col_msgs','Msgs'), ENT_QUOTES, 'UTF-8') ?>', data: valoresHora, backgroundColor: 'rgba(245,158,11,.7)', borderRadius: 3 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
}

// Gráfico ações
if (dadosAcoes.length > 0) {
    var cores = ['#1d4ed8','#16a34a','#dc2626','#d97706','#7c3aed','#0891b2','#be185d','#065f46','#92400e','#1e3a5f'];
    new Chart(document.getElementById('chart-acoes'), {
        type: 'doughnut',
        data: {
            labels: dadosAcoes.map(function(a) { return a.acao }),
            datasets: [{ data: dadosAcoes.map(function(a) { return a.total }), backgroundColor: cores }]
        },
        options: { responsive: true, plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } } }
    });
}

// Filtro de período
document.getElementById('periodo-select').addEventListener('change', function() {
    window.location.href = '/admin/copiloto/analytics?periodo=' + this.value;
});
</script>
