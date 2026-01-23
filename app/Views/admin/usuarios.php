<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-users me-2"></i>
            Gerenciamento de Usuários
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                <i class="fas fa-plus me-2"></i>Novo Usuário
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
                        <option value="ativo" <?= $status == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= $status == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="bloqueado" <?= $status == 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Perfil</label>
                    <select name="perfil" class="form-select">
                        <option value="">Todos</option>
                        <option value="admin" <?= $perfil == 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="cliente" <?= $perfil == 'cliente' ? 'selected' : '' ?>>Cliente</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" class="form-control" placeholder="Nome, email ou CPF" value="<?= $busca ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Usuários -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>CPF/CNPJ</th>
                            <th>Telefone</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Nenhum usuário encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?= $usuario['id'] ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
                                            <small class="text-muted d-block"><?= htmlspecialchars($usuario['email']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['documento']) ?></td>
                                    <td><?= htmlspecialchars($usuario['telefone']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $usuario['perfil'] == 'admin' ? 'danger' : 'primary' ?>">
                                            <?= ucfirst($usuario['perfil']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $usuario['status'] == 'ativo' ? 'success' : ($usuario['status'] == 'inativo' ? 'secondary' : 'warning') ?>">
                                            <?= ucfirst($usuario['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($usuario['data_criacao'])) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarUsuario(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-<?= $usuario['status'] == 'ativo' ? 'warning' : 'success' ?>" onclick="alterarStatus(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-<?= $usuario['status'] == 'ativo' ? 'ban' : 'check' ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirUsuario(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-trash"></i>
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
                                <a class="page-link" href="/admin/usuarios?pagina=<?= $i ?><?= $status ? '&status=' . $status : '' ?><?= $perfil ? '&perfil=' . $perfil : '' ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>">
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

<!-- Modal Usuário -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuarioTitle">Novo Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formUsuario">
                    <input type="hidden" name="id" id="usuario_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome Completo *</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CPF/CNPJ *</label>
                            <input type="text" name="documento" class="form-control" required>
                        </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" class="form-control">
                            </div>
                        <div class="col-md-6">
                            <label class="form-label">Senha</label>
                            <input type="password" name="senha" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmar Senha</label>
                            <input type="password" name="senha_confirmacao" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Perfil</label>
                            <select name="perfil" class="form-select" required>
                                <option value="cliente">Cliente</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                                <option value="bloqueado">Bloqueado</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarUsuario()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
function editarUsuario(id) {
    fetch(`/admin/usuarios/${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalUsuarioTitle').textContent = 'Editar Usuário';
            document.getElementById('usuario_id').value = data.id;
            document.querySelector('input[name="nome"]').value = data.nome;
            document.querySelector('input[name="email"]').value = data.email;
            document.querySelector('input[name="documento"]').value = data.documento;
            document.querySelector('input[name="telefone"]').value = data.telefone;
            document.querySelector('select[name="perfil"]').value = data.perfil;
            document.querySelector('select[name="status"]').value = data.status;
            document.querySelector('input[name="senha"]').value = '';
            document.querySelector('input[name="senha_confirmacao"]').value = '';
            
            new bootstrap.Modal(document.getElementById('modalUsuario')).show();
        });
}

function alterarStatus(id) {
    if (confirm('Deseja realmente alterar o status deste usuário?')) {
        fetch(`/admin/alterar-status-usuario/${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status alterado com sucesso!');
                location.reload();
            } else {
                alert('Erro ao alterar status: ' + data.error);
            }
        });
    }
}

function excluirUsuario(id) {
    if (confirm('Deseja realmente excluir este usuário? Esta ação não pode ser desfeita!')) {
        fetch(`/admin/excluir-usuario/${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Usuário excluído com sucesso!');
                location.reload();
            } else {
                alert('Erro ao excluir usuário: ' + data.error);
            }
        });
    }
}

function salvarUsuario() {
    const form = document.getElementById('formUsuario');
    const formData = new FormData(form);
    
    const senha = formData.get('senha');
    const senhaConfirmacao = formData.get('senha_confirmacao');
    
    if (senha && senha !== senhaConfirmacao) {
        alert('As senhas não conferem!');
        return;
    }
    
    const id = formData.get('id');
    const url = id ? `/admin/atualizar-usuario/${id}` : '/admin/salvar-usuario';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Usuário salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro ao salvar usuário: ' + data.error);
        }
    });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
