<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-shopping-cart me-2"></i>
            Gerenciamento de Pedidos
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCriarPedido">
                <i class="fas fa-plus me-2"></i>Novo Pedido
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendente" <?= $status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="pago" <?= $status == 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="processando" <?= $status == 'processando' ? 'selected' : '' ?>>Processando</option>
                        <option value="enviado" <?= $status == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                        <option value="entregue" <?= $status == 'entregue' ? 'selected' : '' ?>>Entregue</option>
                        <option value="cancelado" <?= $status == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Final</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" class="form-control" placeholder="ID, nome ou email" value="<?= $busca ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                    <a href="/admin/pedidos" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Pedidos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Nenhum pedido encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td>#<?= str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($pedido['nome_cliente']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($pedido['email_cliente']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($pedido['data_criacao'])) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $pedido['status'] == 'pago' ? 'success' : ($pedido['status'] == 'pendente' ? 'warning' : ($pedido['status'] == 'cancelado' ? 'danger' : 'info')) ?>">
                                            <?= ucfirst($pedido['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarUsuario(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="verPerfilUsuario(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-user"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="gerenciarCreditos(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-credit-card"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="atualizarStatus(<?= $pedido['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="gerarEtiqueta(<?= $pedido['id'] ?>)">
                                                <i class="fas fa-tag"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginação">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/pedidos?pagina=<?= $i ?><?= $status ? '&status=' . $status : '' ?><?= $data_inicio ? '&data_inicio=' . $data_inicio : '' ?><?= $data_fim ? '&data_fim=' . $data_fim : '' ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Criar Pedido -->
<div class="modal fade" id="modalCriarPedido" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Novo Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCriarPedido">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Selecionar Cliente *</label>
                            <select name="usuario_id" class="form-select" required>
                                <option value="">Selecione um cliente...</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data do Pedido</label>
                            <input type="datetime-local" name="data_pedido" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3" placeholder="Observações do pedido"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" onclick="criarPedido()">
                                <i class="fas fa-shopping-cart me-2"></i> Criar Pedido
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes do Pedido -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conteudoDetalhes">
                <!-- Conteúdo carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Atualizar Status -->
<div class="modal fade" id="modalStatus" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Atualizar Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formStatus">
                    <input type="hidden" name="pedido_id" id="pedido_id">
                    <div class="mb-3">
                        <label class="form-label">Novo Status</label>
                        <select name="status" class="form-select" required>
                            <option value="">Selecione...</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="processando">Processando</option>
                            <option value="enviado">Enviado</option>
                            <option value="entregue">Entregue</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarStatus()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
function criarPedido() {
    const form = document.getElementById('formCriarPedido');
    const formData = new FormData(form);
    
    fetch('/admin/criar-pedido', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pedido criado com sucesso! ID: ' + data.pedido_id);
            new bootstrap.Modal(document.getElementById('modalCriarPedido')).hide();
            document.getElementById('formCriarPedido').reset();
            location.reload();
        } else {
            alert('Erro ao criar pedido: ' + data.error);
        }
    });
}

function verDetalhes(pedidoId) {
    fetch(`/admin/pedido-detalhes/${pedidoId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('conteudoDetalhes').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        });
}

function atualizarStatus(pedidoId) {
    document.getElementById('pedido_id').value = pedidoId;
    new bootstrap.Modal(document.getElementById('modalStatus')).show();
}

function salvarStatus() {
    const form = document.getElementById('formStatus');
    const formData = new FormData(form);
    
    fetch('/admin/atualizar-status', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status atualizado com sucesso!');
            location.reload();
        } else {
            alert('Erro ao atualizar status: ' + data.error);
        }
    });
}

function gerarEtiqueta(pedidoId) {
    window.open(`/admin/gerar-etiqueta/${pedidoId}`, '_blank');
}

function excluirUsuario(id) {
    if (confirm('Deseja realmente excluir este usuário? Esta ação não pode ser desfeita!')) {
        fetch(`/admin/excluir-usuario/${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Usuário excluído com sucesso!');
                carregarUsuarios();
            } else {
                alert('Erro ao excluir usuário: ' + data.error);
            }
        });
    }
}

function atualizarEstatisticasUsuarios() {
    fetch('/admin/estatisticas-usuarios')
        .then(response => response.json())
        .then(data => {
            document.getElementById('total-usuarios').textContent = data.total_usuarios;
            document.getElementById('usuarios-ativos').textContent = data.usuarios_ativos;
            document.getElementById('usuarios-mes').textContent = data.usuarios_mes;
        });
}

// Funções de Créditos
function abrirModalCreditos() {
    carregarUsuarios();
    new bootstrap.Modal(document.getElementById('modalCreditos')).show();
}

function carregarUsuariosSelect() {
    fetch('/admin/usuarios-json')
        .then(response => response.json())
        .then(data => {
            const select = document.querySelector('#formCreditos select[name="usuario_id"]');
            select.innerHTML = '<option value="">Selecione um usuário...</option>';
            
            data.usuarios.forEach(usuario => {
                const option = document.createElement('option');
                option.value = usuario.id;
                option.textContent = `${usuario.nome} (Saldo: R$ ${number_format(usuario.creditos_disponiveis, 2, ',', '.')})`;
                select.appendChild(option);
            });
        });
}

function salvarCreditos() {
    const form = document.getElementById('formCreditos');
    const formData = new FormData(form);
    
    fetch('/admin/adicionar-creditos', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Créditos adicionados com sucesso!');
            new bootstrap.Modal(document.getElementById('modalCreditos')).hide();
            carregarLogsCreditos();
            carregarUsuarios();
        } else {
            alert('Erro ao adicionar créditos: ' + data.error);
        }
    });
}

function carregarLogsCreditos() {
    fetch('/admin/logs-creditos')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('creditos-lista');
            tbody.innerHTML = '';
            
            data.logs.forEach(log => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${log.id}</td>
                    <td>${log.usuario_nome}</td>
                    <td>R$ ${number_format(log.valor, 2, ',', '.')}</td>
                    <td>${new Date(log.data_criacao).toLocaleString('pt-BR')}</td>
                    <td><span class="badge badge-${log.status == 'ativo' ? 'success' : 'danger'}">${log.status}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="verDetalhesCredito(${log.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => {
            console.error('Erro ao carregar logs de créditos:', error);
        });
}

function verDetalhesCredito(id) {
    fetch(`/admin/credito-detalhes/${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.log) {
                alert(`Detalhes do Crédito #${id}:\n\n` +
                    `Usuário: ${data.log.usuario_nome}\n` +
                    `Valor: R$ ${number_format(data.log.valor, 2, ',', '.')}\n` +
                    `Data: ${new Date(data.log.data_criacao).toLocaleString('pt-BR')}\n` +
                    `Status: ${data.log.status}\n` +
                    `Descrição: ${data.log.descricao}\n` +
                    `Válido até: ${data.validade_dias} dias`);
            }
        });
}

function imprimirPedido() {
    const pedidoId = document.getElementById('pedido-id').textContent;
    window.open(`/admin/imprimir-pedido/${pedidoId}`, '_blank');
}

// Carregar dados ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    carregarUsuarios();
    carregarLogsCreditos();
    atualizarEstatisticasUsuarios();
});

// Mudar para aba de criação ao clicar em "Novo Pedido"
document.querySelector('[data-bs-target="#criar"]').addEventListener('click', function() {
    document.getElementById('formCriarPedido').reset();
});
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
