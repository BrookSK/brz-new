<?php ob_start(); ?>
<style>
    .container-row { cursor: pointer; transition: background-color 0.15s; }
    .container-row:hover { background-color: #f8f9fa; }
    .container-row .chevron { transition: transform 0.2s; display: inline-block; }
    .container-row.expanded .chevron { transform: rotate(90deg); }
    .container-detail-row { display: none; }
    .container-detail-row.show { display: table-row; }
    .container-detail-row td { padding: 0 !important; }
    .detail-wrapper { padding: 1rem 1.5rem; background: #f8fafe; border-left: 3px solid #0d6efd; }
    .detail-wrapper .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem 1.5rem; margin-bottom: 1rem; }
    .detail-wrapper .info-grid .info-item { font-size: 0.85rem; }
    .detail-wrapper .info-grid .info-item label { font-weight: 600; color: #6c757d; margin-bottom: 0; display: block; font-size: 0.75rem; text-transform: uppercase; }
    .detail-wrapper .info-grid .info-item span { color: #212529; }
    .detail-wrapper .packages-table { font-size: 0.85rem; }
    .detail-wrapper .packages-table th { font-size: 0.75rem; text-transform: uppercase; color: #6c757d; border-bottom: 2px solid #dee2e6; }
    .detail-loading { text-align: center; padding: 2rem; color: #6c757d; }
    .badge-status-gerada { background-color: #198754; color: #fff; }
    .badge-status-created { background-color: #0d6efd; color: #fff; }
    .badge-status-cancelled { background-color: #dc3545; color: #fff; }
    .badge-status-default { background-color: #6c757d; color: #fff; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><?= __('admin.correios_mundial.containers_title','Correios Mundial (PACKET) - Containers') ?></h1>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial"><?= __('admin.correios_mundial.back','Voltar') ?></a>
            <a class="btn btn-sm btn-primary" href="/admin/correios-mundial/containers/novo"><?= __('admin.correios_mundial.new_container','Novo container') ?></a>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong><?= __('admin.correios_mundial.containers_created','Containers criados') ?></strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:30px"></th>
                            <th><?= __('admin.correios_mundial.col_dispatch','Remessa') ?></th>
                            <th><?= __('admin.correios_mundial.col_unit_code','Unit Code') ?></th>
                            <th><?= __('admin.correios_mundial.col_packages','Pacotes') ?></th>
                            <th><?= __('admin.correios_mundial.col_status','Status') ?></th>
                            <th><?= __('admin.correios_mundial.col_date','Data') ?></th>
                            <th><?= __('admin.correios_mundial.col_pdf','PDF') ?></th>
                            <th><?= __('admin.correios_mundial.col_actions','Acoes') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $containers = isset($containers) && is_array($containers) ? $containers : []; ?>
                        <?php if (empty($containers)): ?>
                            <tr><td colspan="8" class="text-muted"><?= __('admin.correios_mundial.no_containers','Nenhum container criado.') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($containers as $c): ?>
                                <?php $cid = (int) ($c['id'] ?? 0); ?>
                                <?php $unitCode = (string) ($c['unit_code'] ?? ''); ?>
                                <?php $dispatchNumber = (string) ($c['dispatch_number'] ?? ''); ?>
                                <?php $status = strtolower(trim((string) ($c['status'] ?? ''))); ?>
                                <?php $packagesCount = (int) ($c['packages_count'] ?? 0); ?>
                                <tr class="container-row" data-container-id="<?= $cid ?>">
                                    <td><span class="chevron">&#9654;</span></td>
                                    <td><strong><?= htmlspecialchars($dispatchNumber) ?></strong></td>
                                    <td><code><?= htmlspecialchars($unitCode) ?></code></td>
                                    <td><span class="badge bg-secondary"><?= $packagesCount ?></span></td>
                                    <td>
                                        <?php if ($status === 'created'): ?>
                                            <span class="badge badge-status-created"><?= __('admin.correios_mundial.status_created','Criado') ?></span>
                                        <?php elseif ($status === 'faturado'): ?>
                                            <span class="badge badge-status-gerada"><?= __('admin.correios_mundial.status_invoiced','Faturado') ?></span>
                                        <?php elseif ($status === 'cancelled'): ?>
                                            <span class="badge badge-status-cancelled"><?= __('admin.correios_mundial.status_cancelled','Cancelado') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-status-default"><?= htmlspecialchars($status !== '' ? $status : '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($c['created_at']) ? date('d/m/Y H:i', strtotime((string) $c['created_at'])) : '-' ?></td>
                                    <td>
                                        <?php if ($cid > 0): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/container/<?= $cid ?>.pdf" target="_blank" onclick="event.stopPropagation();">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cid > 0): ?>
                                            <form method="post" action="/admin/correios-mundial/container/<?= $cid ?>/cancelar" style="display:inline-block" onsubmit="event.stopPropagation(); return confirm(<?= htmlspecialchars(json_encode(__('admin.correios_mundial.confirm_cancel_dispatch','Cancelar o despacho (dispatch) deste container?')), ENT_QUOTES, 'UTF-8') ?>);">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" <?= $status === 'cancelled' ? 'disabled' : '' ?> onclick="event.stopPropagation();"><?= __('admin.correios_mundial.cancel','Cancelar') ?></button>
                                            </form>
                                            <form method="post" action="/admin/correios-mundial/container/<?= $cid ?>/deletar" style="display:inline-block" onsubmit="event.stopPropagation(); return confirm(<?= htmlspecialchars(json_encode(__('admin.correios_mundial.confirm_delete_container','Deletar o container? Isso vai liberar os pacotes para uso em outro container.')), ENT_QUOTES, 'UTF-8') ?>);">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" <?= $status === 'cancelled' ? '' : 'disabled' ?> onclick="event.stopPropagation();"><?= __('admin.correios_mundial.delete','Deletar') ?></button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="container-detail-row" id="detail-row-<?= $cid ?>">
                                    <td colspan="8">
                                        <div class="detail-wrapper" id="detail-content-<?= $cid ?>">
                                            <div class="detail-loading">
                                                <i class="fas fa-spinner fa-spin"></i> <?= __('admin.correios_mundial.loading_details','Carregando detalhes...') ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadedContainers = {};

    document.querySelectorAll('.container-row').forEach(function(row) {
        row.addEventListener('click', function() {
            const containerId = this.dataset.containerId;
            const detailRow = document.getElementById('detail-row-' + containerId);
            const isExpanded = this.classList.contains('expanded');

            // Fechar todos os outros
            document.querySelectorAll('.container-row.expanded').forEach(function(r) {
                if (r !== row) {
                    r.classList.remove('expanded');
                    const otherId = r.dataset.containerId;
                    const otherDetail = document.getElementById('detail-row-' + otherId);
                    if (otherDetail) otherDetail.classList.remove('show');
                }
            });

            if (isExpanded) {
                this.classList.remove('expanded');
                detailRow.classList.remove('show');
            } else {
                this.classList.add('expanded');
                detailRow.classList.add('show');

                // Carregar dados se ainda nao carregado
                if (!loadedContainers[containerId]) {
                    fetchContainerDetails(containerId);
                }
            }
        });
    });

    function fetchContainerDetails(containerId) {
        fetch('/admin/correios-mundial/container/' + containerId + '/detalhes')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.error) {
                    renderError(containerId, data.error);
                    return;
                }
                loadedContainers[containerId] = true;
                renderDetails(containerId, data);
            })
            .catch(function() {
                renderError(containerId, <?= json_encode(__('admin.correios_mundial.load_details_error','Erro ao carregar detalhes.')) ?>);
            });
    }

    function renderError(containerId, msg) {
        const el = document.getElementById('detail-content-' + containerId);
        el.innerHTML = '<div class="alert alert-danger mb-0">' + escHtml(msg) + '</div>';
    }

    function renderDetails(containerId, data) {
        const el = document.getElementById('detail-content-' + containerId);
        let html = '';

        // Info grid
        html += '<div class="info-grid">';
        html += infoItem(<?= json_encode(__('admin.correios_mundial.origin_country','Pais Origem')) ?>, data.origin_country || '-');
        html += infoItem(<?= json_encode(__('admin.correios_mundial.origin_operator','Operador Origem')) ?>, data.origin_operator_name || '-');
        html += infoItem(<?= json_encode(__('admin.correios_mundial.destination_operator','Operador Destino')) ?>, data.destination_operator_name || '-');
        html += infoItem(<?= json_encode(__('admin.correios_mundial.postal_category','Categoria Postal')) ?>, data.postal_category_code || '-');
        html += infoItem(<?= json_encode(__('admin.correios_mundial.subclass','Subclasse')) ?>, data.service_subclass_code || '-');
        html += infoItem(<?= json_encode(__('admin.correios_mundial.unit_type','Tipo Unidade')) ?>, formatUnitType(data.unit_type));
        html += infoItem(<?= json_encode(__('admin.correios_mundial.awb','AWB')) ?>, data.awb || '-');
        html += infoItem(<?= json_encode(__('admin.correios_mundial.triage_group','Grupo Triagem')) ?>, data.triage_group || '-');
        html += infoItem(<?= json_encode(__('admin.correios_mundial.total_packages','Total Pacotes')) ?>, data.packages_count || 0);
        html += '</div>';

        // Tabela de pacotes/etiquetas
        if (data.etiquetas && data.etiquetas.length > 0) {
            html += '<h6 class="mt-3 mb-2"><i class="fas fa-box"></i> ' + <?= json_encode(__('admin.correios_mundial.packages_labels','Pacotes / Etiquetas ({n})')) ?>.replace('{n}', data.etiquetas.length) + '</h6>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-sm table-bordered packages-table mb-0">';
            html += '<thead><tr>';
            html += '<th>' + <?= json_encode(__('admin.correios_mundial.col_tracking_short','Tracking')) ?> + '</th>';
            html += '<th>' + <?= json_encode(__('admin.correios_mundial.col_order','Pedido')) ?> + '</th>';
            html += '<th>' + <?= json_encode(__('admin.correios_mundial.col_customer','Cliente')) ?> + '</th>';
            html += '<th>' + <?= json_encode(__('admin.correios_mundial.col_weight_g','Peso (g)')) ?> + '</th>';
            html += '<th>' + <?= json_encode(__('admin.correios_mundial.col_status','Status')) ?> + '</th>';
            html += '<th>' + <?= json_encode(__('admin.correios_mundial.col_date','Data')) ?> + '</th>';
            html += '</tr></thead><tbody>';

            data.etiquetas.forEach(function(et) {
                html += '<tr>';
                html += '<td><code>' + escHtml(et.tracking_number || '-') + '</code></td>';
                html += '<td>' + (et.pedido_id ? '<a href="/admin/pedido/' + et.pedido_id + '" onclick="event.stopPropagation();">#' + et.pedido_id + '</a>' : '-') + '</td>';
                html += '<td>' + escHtml(et.cliente_nome || '-') + '</td>';
                html += '<td>' + (et.peso_total ? parseFloat(et.peso_total).toFixed(0) : '-') + '</td>';
                html += '<td>' + statusBadge(et.status) + '</td>';
                html += '<td>' + formatDate(et.created_at) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
        } else if (data.tracking_numbers && data.tracking_numbers.length > 0) {
            // Se nao achou etiquetas no banco mas tem tracking numbers
            html += '<h6 class="mt-3 mb-2"><i class="fas fa-box"></i> ' + <?= json_encode(__('admin.correios_mundial.tracking_numbers','Tracking Numbers ({n})')) ?>.replace('{n}', data.tracking_numbers.length) + '</h6>';
            html += '<div class="d-flex flex-wrap gap-1">';
            data.tracking_numbers.forEach(function(tn) {
                html += '<span class="badge bg-light text-dark border"><code>' + escHtml(tn) + '</code></span>';
            });
            html += '</div>';
        } else {
            html += '<p class="text-muted mb-0"><i class="fas fa-info-circle"></i> ' + <?= json_encode(__('admin.correios_mundial.no_packages_linked','Nenhum pacote vinculado a este container.')) ?> + '</p>';
        }

        el.innerHTML = html;
    }

    function infoItem(label, value) {
        return '<div class="info-item"><label>' + escHtml(label) + '</label><span>' + escHtml(String(value)) + '</span></div>';
    }

    function formatUnitType(type) {
        const map = {'1': <?= json_encode(__('admin.correios_mundial.unit_type_bag','1 - Saco')) ?>, '2': <?= json_encode(__('admin.correios_mundial.unit_type_pallet_500','2 - Caixa pallet ate 500kg')) ?>, '3': <?= json_encode(__('admin.correios_mundial.unit_type_pallet_1000','3 - Caixa pallet ate 1000kg')) ?>};
        return map[type] || type || '-';
    }

    function statusBadge(status) {
        if (!status) return '<span class="badge badge-status-default">-</span>';
        const s = status.toLowerCase();
        if (s === 'gerada') return '<span class="badge badge-status-gerada">' + <?= json_encode(__('admin.correios_mundial.status_generated','Gerada')) ?> + '</span>';
        if (s === 'created') return '<span class="badge badge-status-created">' + <?= json_encode(__('admin.correios_mundial.status_created','Criado')) ?> + '</span>';
        if (s === 'cancelled') return '<span class="badge badge-status-cancelled">' + <?= json_encode(__('admin.correios_mundial.status_cancelled','Cancelado')) ?> + '</span>';
        return '<span class="badge badge-status-default">' + escHtml(status) + '</span>';
    }

    function formatDate(dt) {
        if (!dt) return '-';
        try {
            const d = new Date(dt);
            return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        } catch(e) { return dt; }
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
