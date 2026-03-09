<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-invoice me-2"></i>
            <?= __('admin.consolidate_orders.title', 'Consolidar Pedidos') ?>
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= __('common.back', 'Voltar') ?>
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" id="formConsolidar">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?= __('admin.consolidate_orders.filters.start_date', 'Data Início') ?></label>
                        <input type="date" name="data_inicio" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('admin.consolidate_orders.filters.end_date', 'Data Fim') ?></label>
                        <input type="date" name="data_fim" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('common.status', 'Status') ?></label>
                        <select name="status" class="form-select">
                            <option value=""><?= __('common.all', 'Todos') ?></option>
                            <option value="pago"><?= __('admin.orders.status.paid', 'Pago') ?></option>
                            <option value="processando"><?= __('admin.orders.status.processing', 'Processando') ?></option>
                            <option value="enviado"><?= __('admin.orders.status.shipped', 'Etiqueta gerada') ?></option>
                            <option value="entregue"><?= __('admin.orders.status.delivered', 'Entregue') ?></option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calculator me-2"></i><?= __('admin.consolidate_orders.actions.consolidate', 'Consolidar Pedidos') ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resultados -->
    <div id="resultados" style="display: none;">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    <?= __('admin.consolidate_orders.summary.title', 'Resumo da Consolidação') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h3 class="text-primary" id="totalPedidos">0</h3>
                                <p class="text-muted"><?= __('admin.consolidate_orders.summary.total_orders', 'Total de Pedidos') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h3 class="text-success" id="valorTotal"><?= __('admin.orders.js.currency_brl', 'R$') ?> 0,00</h3>
                                <p class="text-muted"><?= __('admin.consolidate_orders.summary.total_value', 'Valor Total') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h3 class="text-info" id="pesoTotal">0 <?= __('admin.consolidate_orders.js.weight_unit', 'kg') ?></h3>
                                <p class="text-muted"><?= __('admin.consolidate_orders.summary.total_weight', 'Peso Total') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h3 class="text-warning" id="totalItens">0</h3>
                                <p class="text-muted"><?= __('admin.consolidate_orders.summary.total_items', 'Total de Itens') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Pedidos -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    <?= __('admin.consolidate_orders.table.title', 'Pedidos Selecionados') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th><?= __('admin.orders.table.id', 'ID') ?></th>
                                <th><?= __('admin.orders.table.customer', 'Cliente') ?></th>
                                <th><?= __('common.date', 'Data') ?></th>
                                <th><?= __('admin.consolidate_orders.table.value', 'Valor') ?></th>
                                <th><?= __('admin.consolidate_orders.table.weight', 'Peso') ?></th>
                                <th><?= __('admin.consolidate_orders.table.items', 'Itens') ?></th>
                                <th><?= __('common.status', 'Status') ?></th>
                            </tr>
                        </thead>
                        <tbody id="tabelaPedidos">
                            <!-- Conteúdo carregado via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Ações -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-success btn-lg w-100" onclick="exportarExcel()">
                            <i class="fas fa-file-excel me-2"></i>
                            <?= __('admin.consolidate_orders.actions.export_excel', 'Exportar para Excel') ?>
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-primary btn-lg w-100" onclick="imprimirRelatorio()">
                            <i class="fas fa-print me-2"></i>
                            <?= __('admin.consolidate_orders.actions.print_report', 'Imprimir Relatório') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading -->
<div id="loading" style="display: none;">
    <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only"><?= __('common.loading', 'Carregando...') ?></span>
        </div>
    </div>
</div>

<script>
window.ADMIN_CONSOLIDATE_ORDERS_I18N = {
    error_consolidate_prefix: <?= json_encode(__('admin.consolidate_orders.js.error_consolidate_prefix', 'Erro ao consolidar pedidos:'), JSON_UNESCAPED_UNICODE) ?>,
    error_process_request_prefix: <?= json_encode(__('admin.consolidate_orders.js.error_process_request_prefix', 'Erro ao processar requisição:'), JSON_UNESCAPED_UNICODE) ?>,
    currency_brl: <?= json_encode(__('admin.orders.js.currency_brl', 'R$'), JSON_UNESCAPED_UNICODE) ?>,
    weight_unit: <?= json_encode(__('admin.consolidate_orders.js.weight_unit', 'kg'), JSON_UNESCAPED_UNICODE) ?>,
    status_paid: <?= json_encode(__('admin.orders.status.paid', 'Pago'), JSON_UNESCAPED_UNICODE) ?>,
    status_processing: <?= json_encode(__('admin.orders.status.processing', 'Processando'), JSON_UNESCAPED_UNICODE) ?>,
    status_shipped: <?= json_encode(__('admin.orders.status.shipped', 'Enviado'), JSON_UNESCAPED_UNICODE) ?>,
    status_delivered: <?= json_encode(__('admin.orders.status.delivered', 'Entregue'), JSON_UNESCAPED_UNICODE) ?>,
    locale: <?= json_encode(\App\Core\I18n::getLocaleHtml(), JSON_UNESCAPED_UNICODE) ?>
};

document.getElementById('formConsolidar').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const loading = document.getElementById('loading');
    const resultados = document.getElementById('resultados');
    
    loading.style.display = 'block';
    resultados.style.display = 'none';
    
    fetch('/admin/consolidar-pedidos', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loading.style.display = 'none';
        resultados.style.display = 'block';
        
        if (data.success) {
            const brl = (window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.currency_brl) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.currency_brl : 'R$';
            const unitKg = (window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.weight_unit) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.weight_unit : 'kg';
            const locale = (window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.locale) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.locale : ((document.documentElement && document.documentElement.lang) ? document.documentElement.lang : 'pt-BR');

            // Atualizar resumo
            document.getElementById('totalPedidos').textContent = data.resumo.total_pedidos;
            document.getElementById('valorTotal').textContent = brl + ' ' + number_format(data.resumo.valor_total, 2, ',', '.');
            document.getElementById('pesoTotal').textContent = number_format(data.resumo.peso_total, 3, ',', '.') + ' ' + unitKg;
            document.getElementById('totalItens').textContent = data.resumo.total_itens;
            
            // Atualizar tabela
            const tbody = document.getElementById('tabelaPedidos');
            tbody.innerHTML = '';

            const statusLabels = {
                'pago': (window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.status_paid) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.status_paid : 'Pago',
                'processando': (window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.status_processing) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.status_processing : 'Processando',
                'enviado': (window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.status_shipped) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.status_shipped : 'Enviado',
                'entregue': (window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.status_delivered) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.status_delivered : 'Entregue'
            };
            
            data.pedidos.forEach(pedido => {
                const row = document.createElement('tr');
                const statusTxt = statusLabels[pedido.status] || pedido.status;
                row.innerHTML = `
                    <td>#${pedido.id}</td>
                    <td>${pedido.nome_cliente}</td>
                    <td>${new Date(pedido.data_criacao).toLocaleDateString(locale)}</td>
                    <td>${brl} ${number_format(pedido.valor_total, 2, ',', '.')}</td>
                    <td>${number_format(pedido.peso_total, 3, ',', '.')} ${unitKg}</td>
                    <td>${pedido.total_itens}</td>
                    <td><span class="badge" style="background: ${pedido.status == 'pago' ? 'rgba(16, 185, 129, 0.12)' : 'rgba(245, 158, 11, 0.12)'}; border: 1px solid ${pedido.status == 'pago' ? 'rgba(16, 185, 129, 0.22)' : 'rgba(245, 158, 11, 0.22)'}; color: ${pedido.status == 'pago' ? '#065f46' : '#7c2d12'};">${statusTxt}</span></td>
                `;
                tbody.appendChild(row);
            });
        } else {
            alert(((window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.error_consolidate_prefix) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.error_consolidate_prefix : 'Erro ao consolidar pedidos:') + ' ' + data.error);
        }
    })
    .catch(error => {
        loading.style.display = 'none';
        alert(((window.ADMIN_CONSOLIDATE_ORDERS_I18N && window.ADMIN_CONSOLIDATE_ORDERS_I18N.error_process_request_prefix) ? window.ADMIN_CONSOLIDATE_ORDERS_I18N.error_process_request_prefix : 'Erro ao processar requisição:') + ' ' + error.message);
    });
});

function exportarExcel() {
    const form = document.getElementById('formConsolidar');
    const formData = new FormData(form);
    
    // Criar formulário temporário para exportação
    const tempForm = document.createElement('form');
    tempForm.method = 'POST';
    tempForm.action = '/admin/consolidar-pedidos/exportar';
    tempForm.style.display = 'none';
    
    for (let [key, value] of formData.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        tempForm.appendChild(input);
    }
    
    document.body.appendChild(tempForm);
    tempForm.submit();
    document.body.removeChild(tempForm);
}

function imprimirRelatorio() {
    window.print();
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
