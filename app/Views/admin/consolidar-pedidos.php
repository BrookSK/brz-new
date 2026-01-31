<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-invoice me-2"></i>
            Consolidar Pedidos
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" id="formConsolidar">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Data Início</label>
                        <input type="date" name="data_inicio" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Data Fim</label>
                        <input type="date" name="data_fim" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="pago">Pagos</option>
                            <option value="processando">Em Processamento</option>
                            <option value="enviado">Enviados</option>
                            <option value="entregue">Entregues</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calculator me-2"></i>Consolidar Pedidos
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
                    Resumo da Consolidação
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h3 class="text-primary" id="totalPedidos">0</h3>
                                <p class="text-muted">Total de Pedidos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h3 class="text-success" id="valorTotal">R$ 0,00</h3>
                                <p class="text-muted">Valor Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h3 class="text-info" id="pesoTotal">0 kg</h3>
                                <p class="text-muted">Peso Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body">
                                <h3 class="text-warning" id="totalItens">0</h3>
                                <p class="text-muted">Total de Itens</p>
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
                    Pedidos Selecionados
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Peso</th>
                                <th>Itens</th>
                                <th>Status</th>
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
                            Exportar para Excel
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-primary btn-lg w-100" onclick="imprimirRelatorio()">
                            <i class="fas fa-print me-2"></i>
                            Imprimir Relatório
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
            <span class="sr-only">Carregando...</span>
        </div>
    </div>
</div>

<script>
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
            // Atualizar resumo
            document.getElementById('totalPedidos').textContent = data.resumo.total_pedidos;
            document.getElementById('valorTotal').textContent = 'R$ ' + number_format(data.resumo.valor_total, 2, ',', '.');
            document.getElementById('pesoTotal').textContent = number_format(data.resumo.peso_total, 3, ',', '.') + ' kg';
            document.getElementById('totalItens').textContent = data.resumo.total_itens;
            
            // Atualizar tabela
            const tbody = document.getElementById('tabelaPedidos');
            tbody.innerHTML = '';
            
            data.pedidos.forEach(pedido => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>#${pedido.id}</td>
                    <td>${pedido.nome_cliente}</td>
                    <td>${new Date(pedido.data_criacao).toLocaleDateString('pt-BR')}</td>
                    <td>R$ ${number_format(pedido.valor_total, 2, ',', '.')}</td>
                    <td>${number_format(pedido.peso_total, 3, ',', '.')} kg</td>
                    <td>${pedido.total_itens}</td>
                    <td><span class="badge" style="background: ${pedido.status == 'pago' ? 'rgba(16, 185, 129, 0.12)' : 'rgba(245, 158, 11, 0.12)'}; border: 1px solid ${pedido.status == 'pago' ? 'rgba(16, 185, 129, 0.22)' : 'rgba(245, 158, 11, 0.22)'}; color: ${pedido.status == 'pago' ? '#065f46' : '#7c2d12'};">${pedido.status}</span></td>
                `;
                tbody.appendChild(row);
            });
        } else {
            alert('Erro ao consolidar pedidos: ' + data.error);
        }
    })
    .catch(error => {
        loading.style.display = 'none';
        alert('Erro ao processar requisição: ' + error.message);
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
