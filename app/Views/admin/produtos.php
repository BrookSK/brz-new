<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-box me-2"></i>
            Gerenciamento de Produtos
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProduto">
                <i class="fas fa-plus me-2"></i>Novo Produto
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <select name="categoria_id" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= $categoria['id'] ?>" <?= $categoria_id == $categoria['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($categoria['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="ativo" <?= $status == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= $status == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" class="form-control" placeholder="Nome ou SKU" value="<?= $busca ?>">
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

    <!-- Lista de Produtos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>SKU</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Nenhum produto encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produtos as $produto): ?>
                                <tr>
                                    <td><?= $produto['id'] ?></td>
                                    <td>
                                        <?php if ($produto['foto_principal']): ?>
                                            <img src="/uploads/produtos/<?= $produto['foto_principal'] ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/50x50?text=Sem+Imagem" alt="<?= htmlspecialchars($produto['nome']) ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($produto['nome']) ?></strong>
                                            <small class="text-muted d-block">SKU: <?= htmlspecialchars($produto['sku']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($produto['sku']) ?></td>
                                    <td><?= htmlspecialchars($produto['categoria_nome']) ?></td>
                                    <td>
                                        <span class="badge bg-success">R$ <?= number_format($produto['valor'], 2, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $produto['estoque'] > 0 ? 'success' : 'danger' ?>">
                                            <?= $produto['estoque'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $produto['status'] == 'ativo' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($produto['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarProduto(<?= $produto['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="alterarStatus(<?= $produto['id'] ?>)">
                                                <i class="fas fa-<?= $produto['status'] == 'ativo' ? 'ban' : 'check' ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="gerenciarImagens(<?= $produto['id'] ?>)">
                                                <i class="fas fa-images"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirProduto(<?= $produto['id'] ?>)">
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
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>>
                                <a class="page-link" href="/admin/produtos?pagina=<?= $i ?><?= $categoria_id ? '&categoria_id=' . $categoria_id : '' ?><?= $status ? '&status=' . $status : '' ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>">
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

<!-- Modal Produto -->
<div class="modal fade" id="modalProduto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProdutoTitle">Novo Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formProduto" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="produto_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SKU *</label>
                            <input type="text" name="sku" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição Curta *</label>
                            <textarea name="descricao_curta" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição Completa</label>
                            <textarea name="descricao_completa" class="form-control" rows="5"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Categoria *</label>
                            <select name="categoria_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor *</label>
                            <input type="number" name="valor" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Moeda</label>
                            <select name="moeda" class="form-select">
                                <option value="BRL">Real Brasileiro (BRL)</option>
                                <option value="USD">Dólar Americano (USD)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Peso (kg)</label>
                            <input type="number" name="peso" class="form-control" step="0.001" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estoque</label>
                            <input type="number" name="estoque" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Imagem Principal</label>
                            <input type="file" name="imagem_principal" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Imagens Adicionais</label>
                            <input type="file" name="imagens[]" class="form-control" accept="image/*" multiple>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarProduto()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
function editarProduto(id) {
    fetch(`/admin/produtos/${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalProdutoTitle').textContent = 'Editar Produto';
            document.getElementById('produto_id').value = data.id;
            document.querySelector('input[name="nome"]').value = data.nome;
            document.querySelector('input[name="sku"]').value = data.sku;
            document.querySelector('textarea[name="descricao_curta"]').value = data.descricao_curta;
            document.querySelector('textarea[name="descricao_completa"]').value = data.descricao_completa;
            document.querySelector('select[name="categoria_id"]').value = data.categoria_id;
            document.querySelector('input[name="valor"]').value = data.valor;
            document.querySelector('select[name="moeda"]').value = data.moeda;
            document.querySelector('input[name="peso"]').value = data.peso;
            document.querySelector('input[name="estoque"]').value = data.estoque;
            document.querySelector('select[name="status"]').value = data.status;
            
            new bootstrap.Modal(document.getElementById('modalProduto')).show();
        });
}

function alterarStatus(id) {
    if (confirm('Deseja realmente alterar o status deste produto?')) {
        fetch(`/admin/alterar-status-produto/${id}`, {
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

function excluirProduto(id) {
    if (confirm('Deseja realmente excluir este produto? Esta ação não pode ser desfeita!')) {
        fetch(`/admin/excluir-produto/${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Produto excluído com sucesso!');
                location.reload();
            } else {
                alert('Erro ao excluir produto: ' + data.error);
            }
        });
    }
}

function gerenciarImagens(id) {
    window.open(`/admin/gerenciar-imagens/${id}`, '_blank');
}

function salvarProduto() {
    const form = document.getElementById('formProduto');
    const formData = new FormData(form);
    
    const id = formData.get('id');
    const url = id ? `/admin/atualizar-produto/${id}` : '/admin/salvar-produto';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Produto salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro ao salvar produto: ' + data.error);
        }
    });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
