<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-box me-2"></i>
            <?= __('admin.products.title', 'Gerenciamento de Produtos') ?>
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= __('common.back', 'Voltar') ?>
            </a>
            <a href="/produtos/arquivados" class="btn btn-outline-dark" target="_blank">
                <i class="fas fa-archive me-2"></i><?= __('admin.products.archived_site', 'Arquivados (site)') ?>
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProduto">
                <i class="fas fa-plus me-2"></i><?= __('admin.products.new', 'Novo Produto') ?>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.products.filter.category', 'Categoria') ?></label>
                    <select name="categoria_id" class="form-select">
                        <option value=""><?= __('common.all', 'Todas') ?></option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= $categoria['id'] ?>" <?= $categoria_id == $categoria['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($categoria['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('common.status', 'Status') ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= __('common.all', 'Todos') ?></option>
                        <option value="ativo" <?= $status == 'ativo' ? 'selected' : '' ?>><?= __('admin.products.status.active', 'Ativo') ?></option>
                        <option value="inativo" <?= $status == 'inativo' ? 'selected' : '' ?>><?= __('admin.products.status.inactive', 'Inativo') ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= __('common.search', 'Buscar') ?></label>
                    <input type="text" name="busca" class="form-control" placeholder="<?= htmlspecialchars(__('admin.products.search_placeholder', 'Nome ou SKU'), ENT_QUOTES, 'UTF-8') ?>" value="<?= $busca ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i><?= __('common.filter', 'Filtrar') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Produtos -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><?= __('admin.products.table.id', 'ID') ?></th>
                            <th><?= __('admin.products.table.image', 'Imagem') ?></th>
                            <th><?= __('common.name', 'Nome') ?></th>
                            <th><?= __('admin.products.table.sku', 'SKU') ?></th>
                            <th><?= __('admin.products.table.category', 'Categoria') ?></th>
                            <th><?= __('admin.products.table.price', 'Preço') ?></th>
                            <th><?= __('admin.products.table.stock', 'Estoque') ?></th>
                            <th><?= __('common.status', 'Status') ?></th>
                            <th><?= __('common.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr>
                                <td colspan="9" class="text-center"><?= __('admin.products.empty', 'Nenhum produto encontrado.') ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produtos as $produto): ?>
                                <tr>
                                    <td><?= $produto['id'] ?></td>
                                    <td>
                                        <?php 
                                        // Usar apenas imagens reais, sem placeholder
                                        $fotoUrl = !empty($produto['foto_principal']) ? $produto['foto_principal'] : null;
                                        ?>
                                        <?php if ($fotoUrl): ?>
                                            <a href="<?= $fotoUrl ?>" target="_blank" class="text-decoration-none">
                                                <img src="<?= $fotoUrl ?>" 
                                                     alt="<?= htmlspecialchars($produto['nome']) ?>"
                                                     class="img-thumbnail"
                                                     style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                                                     title="<?= htmlspecialchars(__('product_details.click_full_size', 'Clique para ver imagem em tamanho real'), ENT_QUOTES, 'UTF-8') ?>">
                                            </a>
                                        <?php else: ?>
                                            <div class="img-thumbnail d-flex align-items-center justify-content-center bg-light" 
                                                 style="width: 60px; height: 60px; cursor: not-allowed;"
                                                 title="<?= htmlspecialchars(__('admin.products.no_image', 'Sem imagem'), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($produto['nome']) ?></strong>
                                            <small class="text-muted d-block"><?= __('admin.products.sku', 'SKU') ?>: <?= htmlspecialchars($produto['sku']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($produto['sku']) ?></td>
                                    <td><?= htmlspecialchars($produto['categoria_nome']) ?></td>
                                    <td>
                                        <span class="badge product-price" data-original-value="<?= $produto['valor'] ?>" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                            $ <?= number_format($produto['valor'], 2, '.', ',') ?> USD
                                        </span>
                                        <?php if ($produto['moeda'] === 'BRL'): ?>
                                        <br><small class="text-warning">⚠️ <?= __('admin.products.wrong_currency_brl', 'Moeda incorreta: BRL') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $estoqueStyle = ($produto['estoque'] ?? 0) > 0
                                            ? 'background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.22); color: #065f46;'
                                            : 'background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.22); color: #7f1d1d;'; ?>
                                        <span class="badge" style="<?= $estoqueStyle ?>">
                                            <?= $produto['estoque'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php $statusStyle = ($produto['status'] ?? '') == 'ativo'
                                            ? 'background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.22); color: #065f46;'
                                            : 'background: rgba(148, 163, 184, 0.16); border: 1px solid rgba(148, 163, 184, 0.28); color: #334155;'; ?>
                                        <span class="badge" style="<?= $statusStyle ?>">
                                            <?= ucfirst($produto['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/admin/editar-produto/<?= $produto['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-edit"></i>
                                            </a>
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
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="<?= htmlspecialchars(__('common.pagination', 'Paginação'), ENT_QUOTES, 'UTF-8') ?>">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
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
                <h5 class="modal-title" id="modalProdutoTitle"><?= __('admin.products.new', 'Novo Produto') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formProduto" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="produto_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.products.form.name_required', 'Nome *') ?></label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.products.form.sku_required', 'SKU *') ?></label>
                            <input type="text" name="sku" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('admin.products.form.description_required', 'Descrição *') ?></label>
                            <textarea name="descricao_curta" class="form-control" rows="3" required placeholder="<?= htmlspecialchars(__('admin.products.form.description_placeholder', 'Descreva o produto brevemente'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('admin.products.filter.category_required', 'Categoria *') ?></label>
                            <select name="categoria_id" class="form-select" required>
                                <option value=""><?= __('common.select', 'Selecione...') ?></option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('admin.products.form.value_usd_required', 'Valor (USD) *') ?></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="valor" class="form-control" step="0.01" placeholder="0.00" required>
                                <span class="input-group-text">USD</span>
                            </div>
                            <small class="text-muted"><?= __('admin.products.form.usd_only_hint', 'Todos os produtos devem ser cadastrados em Dólar Americano (USD)') ?></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('admin.products.form.currency', 'Moeda') ?></label>
                            <select name="moeda" class="form-select" required>
                                <option value="USD" selected><?= __('admin.products.currency.usd_default', 'Dólar Americano (USD) - Padrão') ?></option>
                                <option value="BRL" disabled><?= __('admin.products.currency.brl_disabled', 'Real Brasileiro (BRL) - Desativado') ?></option>
                            </select>
                            <small class="text-muted"><?= __('admin.products.form.currency_fixed_usd_hint', 'Moeda padrão fixada em USD para todos os produtos') ?></small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= __('admin.products.form.weight_kg', 'Peso (kg)') ?></label>
                            <input type="number" name="peso" class="form-control" step="0.001" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= __('admin.products.form.stock', 'Estoque') ?></label>
                            <input type="number" name="estoque" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.status', 'Status') ?></label>
                            <select name="status" class="form-select" required>
                                <option value="ativo"><?= __('admin.products.status.active', 'Ativo') ?></option>
                                <option value="inativo"><?= __('admin.products.status.inactive', 'Inativo') ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('admin.products.form.main_image', 'Imagem Principal') ?></label>
                            <input type="file" name="imagem_principal" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp" id="imagem_principal">
                            <small class="text-muted"><?= __('admin.products.form.main_image_hint', 'Formatos aceitos: JPEG, JPG, PNG, WebP (Máx: 5MB)') ?></small>
                            
                            <!-- Preview da imagem -->
                            <div id="imagem_preview" class="mt-2" style="display: none;">
                                <img id="preview_img" src="" alt="<?= htmlspecialchars(__('admin.products.form.preview_alt', 'Preview'), ENT_QUOTES, 'UTF-8') ?>" style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removerImagem()">
                                    <i class="fas fa-trash"></i> <?= __('common.remove', 'Remover') ?>
                                </button>
                                <div class="mt-2">
                                    <small class="text-success">
                                        <i class="fas fa-check-circle"></i> 
                                        <span id="imagem_status"><?= __('admin.products.js.image_uploaded_success', 'Imagem carregada com sucesso!') ?></span>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('admin.products.form.additional_images', 'Imagens Adicionais') ?></label>
                            <input type="file" name="imagens[]" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp" multiple>
                            <small class="text-muted"><?= __('admin.products.form.additional_images_hint', 'Formatos aceitos: JPEG, JPG, PNG, WebP (Máx: 5MB por imagem)') ?></small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" onclick="salvarProduto()"><?= __('common.save', 'Salvar') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.ADMIN_PRODUCTS_I18N = {
    uploading_processing: <?= json_encode(__('admin.products.js.uploading_processing', 'Processando upload...'), JSON_UNESCAPED_UNICODE) ?>,
    image_uploaded_success_prefix: <?= json_encode(__('admin.products.js.image_uploaded_success_prefix', 'Imagem carregada com sucesso! URL:'), JSON_UNESCAPED_UNICODE) ?>,
    upload_error_prefix: <?= json_encode(__('admin.products.js.upload_error_prefix', 'Erro no upload:'), JSON_UNESCAPED_UNICODE) ?>,
    upload_try_again: <?= json_encode(__('admin.products.js.upload_try_again', 'Erro no upload. Tente novamente.'), JSON_UNESCAPED_UNICODE) ?>,
    loading: <?= json_encode(__('common.loading', 'Carregando...'), JSON_UNESCAPED_UNICODE) ?>,
    edit_title: <?= json_encode(__('admin.products.edit', 'Editar Produto'), JSON_UNESCAPED_UNICODE) ?>,
    error_load_prefix: <?= json_encode(__('admin.products.js.error_load_prefix', 'Erro ao carregar produto:'), JSON_UNESCAPED_UNICODE) ?>,
    data_not_found: <?= json_encode(__('admin.products.js.data_not_found', 'Dados não encontrados'), JSON_UNESCAPED_UNICODE) ?>,
    error_load: <?= json_encode(__('admin.products.js.error_load', 'Erro ao carregar produto'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_change_status: <?= json_encode(__('admin.products.js.confirm_change_status', 'Deseja realmente alterar o status deste produto?'), JSON_UNESCAPED_UNICODE) ?>,
    status_changed_success: <?= json_encode(__('admin.products.js.status_changed_success', 'Status alterado com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_change_status_prefix: <?= json_encode(__('admin.products.js.error_change_status_prefix', 'Erro ao alterar status:'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_delete: <?= json_encode(__('admin.products.js.confirm_delete', 'Deseja realmente excluir este produto? Esta ação não pode ser desfeita!'), JSON_UNESCAPED_UNICODE) ?>,
    deleted_success: <?= json_encode(__('admin.products.js.deleted_success', 'Produto excluído com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_delete_prefix: <?= json_encode(__('admin.products.js.error_delete_prefix', 'Erro ao excluir produto:'), JSON_UNESCAPED_UNICODE) ?>,
    name_required: <?= json_encode(__('admin.products.js.name_required', 'Por favor, informe o nome do produto!'), JSON_UNESCAPED_UNICODE) ?>,
    sku_required: <?= json_encode(__('admin.products.js.sku_required', 'Por favor, informe o SKU do produto!'), JSON_UNESCAPED_UNICODE) ?>,
    sku_min_length: <?= json_encode(__('admin.products.js.sku_min_length', 'O SKU deve ter pelo menos 3 caracteres!'), JSON_UNESCAPED_UNICODE) ?>,
    desc_required: <?= json_encode(__('admin.products.js.desc_required', 'Por favor, informe a descrição do produto!'), JSON_UNESCAPED_UNICODE) ?>,
    category_required: <?= json_encode(__('admin.products.js.category_required', 'Por favor, selecione uma categoria!'), JSON_UNESCAPED_UNICODE) ?>,
    invalid_usd_value: <?= json_encode(__('admin.products.js.invalid_usd_value', 'Por favor, informe um valor válido em USD!'), JSON_UNESCAPED_UNICODE) ?>,
    invalid_weight: <?= json_encode(__('admin.products.js.invalid_weight', 'Por favor, informe um peso válido!'), JSON_UNESCAPED_UNICODE) ?>,
    invalid_stock: <?= json_encode(__('admin.products.js.invalid_stock', 'Por favor, informe um estoque válido!'), JSON_UNESCAPED_UNICODE) ?>,
    updated_success_usd: <?= json_encode(__('admin.products.js.updated_success_usd', 'Produto atualizado com sucesso em USD!'), JSON_UNESCAPED_UNICODE) ?>,
    created_success_usd: <?= json_encode(__('admin.products.js.created_success_usd', 'Produto criado com sucesso em USD!'), JSON_UNESCAPED_UNICODE) ?>,
    error_save_prefix: <?= json_encode(__('admin.products.js.error_save_prefix', 'Erro ao salvar produto:'), JSON_UNESCAPED_UNICODE) ?>,
    error_save_check_console: <?= json_encode(__('admin.products.js.error_save_check_console', 'Erro ao salvar produto. Verifique o console para mais detalhes.'), JSON_UNESCAPED_UNICODE) ?>,
    new_title: <?= json_encode(__('admin.products.new', 'Novo Produto'), JSON_UNESCAPED_UNICODE) ?>
};

// Upload instantâneo com preview
document.getElementById('imagem_principal').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagem_preview');
    const previewImg = document.getElementById('preview_img');
    const statusSpan = document.getElementById('imagem_status');
    
    if (file) {
        // Mostrar preview imediato
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
            statusSpan.textContent = (window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.uploading_processing) ? window.ADMIN_PRODUCTS_I18N.uploading_processing : 'Processando upload...';
        }
        reader.readAsDataURL(file);
        
        // Fazer upload via AJAX
        const formData = new FormData();
        formData.append('imagem', file);
        
        fetch('/admin/upload-imagem', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar preview com a URL do servidor
                previewImg.src = data.imagem.src;
                statusSpan.textContent = ((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.image_uploaded_success_prefix) ? window.ADMIN_PRODUCTS_I18N.image_uploaded_success_prefix : 'Imagem carregada com sucesso! URL:') + ' ' + data.imagem.href;
                statusSpan.className = 'text-success';
                
                // Adicionar campo hidden com a URL
                let urlField = document.getElementById('imagem_url_field');
                if (!urlField) {
                    urlField = document.createElement('input');
                    urlField.type = 'hidden';
                    urlField.name = 'imagem_url';
                    urlField.id = 'imagem_url_field';
                    document.getElementById('formProduto').appendChild(urlField);
                }
                urlField.value = data.imagem.href;
                
                console.log('✅ Upload instantâneo:', data.imagem);
            } else {
                statusSpan.textContent = ((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.upload_error_prefix) ? window.ADMIN_PRODUCTS_I18N.upload_error_prefix : 'Erro no upload:') + ' ' + data.error;
                statusSpan.className = 'text-danger';
            }
        })
        .catch(error => {
            console.error('❌ Erro no upload:', error);
            statusSpan.textContent = (window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.upload_try_again) ? window.ADMIN_PRODUCTS_I18N.upload_try_again : 'Erro no upload. Tente novamente.';
            statusSpan.className = 'text-danger';
        });
    } else {
        preview.style.display = 'none';
        // Remover campo hidden se existir
        const urlField = document.getElementById('imagem_url_field');
        if (urlField) {
            urlField.remove();
        }
    }
});

function removerImagem() {
    document.getElementById('imagem_principal').value = '';
    document.getElementById('imagem_preview').style.display = 'none';
    document.getElementById('preview_img').src = '';
    
    // Remover campo hidden se existir
    const urlField = document.getElementById('imagem_url_field');
    if (urlField) {
        urlField.remove();
    }
}

function editarProduto(id) {
    console.log('🔍 [PRODUTOS] editarProduto() chamada com ID:', id);
    
    // Limpar formulário
    document.getElementById('formProduto').reset();
    document.getElementById('produto_id').value = '';
    document.getElementById('modalProdutoTitle').textContent = (window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.loading) ? window.ADMIN_PRODUCTS_I18N.loading : 'Carregando...';
    
    fetch(`/admin/produto/${id}`)
        .then(response => response.json())
        .then(data => {
            console.log('🔍 [PRODUTOS] Dados recebidos:', data);
            
            if (data.success && data.produto) {
                // Preencher o modal
                document.getElementById('modalProdutoTitle').textContent = (window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.edit_title) ? window.ADMIN_PRODUCTS_I18N.edit_title : 'Editar Produto';
                document.getElementById('produto_id').value = data.produto.id || '';
                
                // Preencher campos com validação robusta
                const campos = {
                    'nome': data.produto.nome || '',
                    'sku': data.produto.sku || '',
                    'descricao_curta': data.produto.descricao_curta || '',
                    'categoria_id': data.produto.categoria_id || '',
                    'valor': data.produto.valor || '',
                    'moeda': data.produto.moeda || 'USD',
                    'peso': data.produto.peso || '',
                    'estoque': data.produto.estoque || '',
                    'status': data.produto.status || 'ativo'
                };
                
                console.log('🔍 [PRODUTOS] Campos a preencher:', campos);
                
                // Preencher cada campo
                Object.keys(campos).forEach(campo => {
                    const elemento = document.querySelector(`[name="${campo}"]`);
                    if (elemento) {
                        elemento.value = campos[campo];
                        console.log(`🔍 [PRODUTOS] Campo ${campo} preenchido com:`, campos[campo]);
                        
                        // Forçar atualização visual
                        elemento.dispatchEvent(new Event('input', { bubbles: true }));
                        elemento.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                        console.warn(`🔍 [PRODUTOS] Campo ${campo} não encontrado`);
                    }
                });
                
                // Forçar moeda USD
                const moedaSelect = document.querySelector('select[name="moeda"]');
                if (moedaSelect) {
                    moedaSelect.value = 'USD';
                    moedaSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                
                // Pequeno delay para garantir que os campos foram preenchidos antes de abrir o modal
                setTimeout(() => {
                    // Abrir modal
                    const modalElement = document.getElementById('modalProduto');
                    const modal = new bootstrap.Modal(modalElement);
                    
                    // Remover aria-hidden temporariamente
                    modalElement.removeAttribute('aria-hidden');
                    
                    modal.show();
                    
                    console.log('🔍 [PRODUTOS] Modal aberto com sucesso');
                }, 100);
                
            } else {
                alert(((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.error_load_prefix) ? window.ADMIN_PRODUCTS_I18N.error_load_prefix : 'Erro ao carregar produto:') + ' ' + (data.error || ((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.data_not_found) ? window.ADMIN_PRODUCTS_I18N.data_not_found : 'Dados não encontrados')));
            }
        })
        .catch(error => {
            console.error('❌ [PRODUTOS] Erro:', error);
            alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.error_load) ? window.ADMIN_PRODUCTS_I18N.error_load : 'Erro ao carregar produto');
        });
}

function alterarStatus(id) {
    if (confirm((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.confirm_change_status) ? window.ADMIN_PRODUCTS_I18N.confirm_change_status : 'Deseja realmente alterar o status deste produto?')) {
        fetch(`/admin/alterar-status-produto/${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.status_changed_success) ? window.ADMIN_PRODUCTS_I18N.status_changed_success : 'Status alterado com sucesso!');
                location.reload();
            } else {
                alert(((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.error_change_status_prefix) ? window.ADMIN_PRODUCTS_I18N.error_change_status_prefix : 'Erro ao alterar status:') + ' ' + data.error);
            }
        });
    }
}

function excluirProduto(id) {
    if (confirm((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.confirm_delete) ? window.ADMIN_PRODUCTS_I18N.confirm_delete : 'Deseja realmente excluir este produto? Esta ação não pode ser desfeita!')) {
        // Criar form dinâmico para POST
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/produtos/excluir/' + id;
        form.style.display = 'none';
        document.body.appendChild(form);
        form.submit();
    }
}

function gerenciarImagens(id) {
    window.open(`/admin/gerenciar-imagens/${id}`, '_blank');
}

// Função para atualizar preços com base na moeda - CORRIGIDO PARA USD ORIGINAL
function updateProductPrices(currency) {
    console.log('🔍 [PRODUTOS] updateProductPrices() chamada com currency:', currency);
    
    const currencySymbol = currency === 'BRL'
        ? ((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_brl) ? window.ADMIN_ORDERS_I18N.currency_brl : 'R$')
        : ((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_usd) ? window.ADMIN_ORDERS_I18N.currency_usd : 'US$');
    const rate = window.exchangeRates ? window.exchangeRates[currency] : 1;
    
    console.log('🔍 [PRODUTOS] currencySymbol:', currencySymbol);
    console.log('🔍 [PRODUTOS] rate:', rate);
    console.log('🔍 [PRODUTOS] window.exchangeRates:', window.exchangeRates);
    
    // DEBUG: Mostrar que o valor original está em USD
    console.log('🔍 [PRODUTOS] VALOR ORIGINAL EM USD');
    console.log('🔍 [PRODUTOS] - Se currency = BRL: USD × 5.5 = BRL');
    console.log('🔍 [PRODUTOS] - Se currency = USD: USD × 1 = USD (sem conversão)');
    
    if (!rate) {
        console.error('❌ [PRODUTOS] Taxa de conversão não encontrada para:', currency);
        console.error('❌ [PRODUTOS] Taxas disponíveis:', window.exchangeRates);
        return;
    }
    
    // Verificar se a tabela existe
    const table = document.querySelector('table');
    console.log('🔍 [PRODUTOS] Tabela encontrada:', !!table);
    
    if (table) {
        const rows = table.querySelectorAll('tbody tr');
        console.log('🔍 [PRODUTOS] Linhas na tabela:', rows.length);
    }
    
    // Atualizar todos os preços de produtos - VALOR ORIGINAL EM USD
    const productPrices = document.querySelectorAll('.product-price');
    console.log('🔍 [PRODUTOS] Preços de produtos encontrados:', productPrices.length);
    
    // Se não encontrar com a classe, tentar encontrar spans com preço
    if (productPrices.length === 0) {
        console.log('🔍 [PRODUTOS] Tentando encontrar spans com preço...');
        const allSpans = document.querySelectorAll('span');
        console.log('🔍 [PRODUTOS] Total de spans na página:', allSpans.length);
        
        allSpans.forEach((span, index) => {
            const text = span.textContent.trim();
            const brlFallback = ((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_brl) ? window.ADMIN_ORDERS_I18N.currency_brl : 'R$');
            const usdFallback = ((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_usd) ? window.ADMIN_ORDERS_I18N.currency_usd : 'US$');
            if (text.includes(brlFallback) || text.includes(usdFallback) || text.includes('R$') || text.includes('$') || text.includes('US$')) {
                const originalValue = parseFloat(span.getAttribute('data-original-value'));
                console.log(`🔍 [PRODUTOS] Span ${index} com preço:`, text, 'data-original-value:', span.getAttribute('data-original-value'));
                
                // LÓGICA CORRETA: valor original em USD
                if (!isNaN(originalValue)) {
                    let convertedPrice;
                    
                    if (currency === 'BRL') {
                        // Converter USD para BRL: multiplicar pela taxa
                        convertedPrice = originalValue * rate;
                        console.log(`🔍 [PRODUTOS] Convertendo USD para BRL: ${originalValue} × ${rate} = ${convertedPrice}`);
                    } else {
                        // Manter em USD: sem conversão
                        convertedPrice = originalValue;
                        console.log(`🔍 [PRODUTOS] Mantendo USD: ${originalValue} (sem conversão)`);
                    }
                    
                    const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
                    span.textContent = formattedPrice;
                    console.log(`🔍 [PRODUTOS] Span ${index} convertido:`, formattedPrice);
                }
            }
        });
    } else {
        productPrices.forEach((element, index) => {
            if (element) {
                const originalValue = parseFloat(element.getAttribute('data-original-value'));
                console.log(`🔍 [PRODUTOS] Produto ${index} - Valor original (USD):`, originalValue);
                
                // LÓGICA CORRETA: valor original em USD
                if (!isNaN(originalValue)) {
                    let convertedPrice;
                    
                    if (currency === 'BRL') {
                        // Converter USD para BRL: multiplicar pela taxa
                        convertedPrice = originalValue * rate;
                        console.log(`🔍 [PRODUTOS] Convertendo USD para BRL: ${originalValue} × ${rate} = ${convertedPrice}`);
                    } else {
                        // Manter em USD: sem conversão
                        convertedPrice = originalValue;
                        console.log(`🔍 [PRODUTOS] Mantendo USD: ${originalValue} (sem conversão)`);
                    }
                    
                    const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
                    element.textContent = formattedPrice;
                    console.log(`🔍 [PRODUTOS] Produto ${index} - Valor convertido:`, formattedPrice);
                } else {
                    console.error(`❌ [PRODUTOS] Produto ${index} - Valor original inválido:`, element.getAttribute('data-original-value'));
                }
            }
        });
    }
    
    console.log('🔍 [PRODUTOS] updateProductPrices() concluída');
    console.log('🔍 [PRODUTOS] LÓGICA: valor_original_USD × rate (para BRL) ou valor_original_USD (para USD)');
}

// Função global para atualizar preços de produtos
window.updateProductPrices = updateProductPrices;

function salvarProduto() {
    const form = document.getElementById('formProduto');
    const formData = new FormData(form);
    
    // FORÇAR MOEDA USD SEMPRE
    formData.set('moeda', 'USD');
    console.log('🔍 [PRODUTOS] Moeda forçada para USD no salvamento');
    
    // Verificar se é edição ou criação
    const produtoId = formData.get('id');
    const isEdicao = produtoId && produtoId !== '';
    
    const url = isEdicao ? `/admin/atualizar-produto/${produtoId}` : '/admin/salvar-produto';
    
    // Validar campos obrigatórios
    const nome = formData.get('nome');
    const sku = formData.get('sku');
    const descricao_curta = formData.get('descricao_curta');
    const valor = parseFloat(formData.get('valor'));
    const categoriaId = formData.get('categoria_id');
    const peso = parseFloat(formData.get('peso'));
    const estoque = parseInt(formData.get('estoque'));
    
    // Validação robusta
    if (!nome || nome.trim() === '') {
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.name_required) ? window.ADMIN_PRODUCTS_I18N.name_required : 'Por favor, informe o nome do produto!');
        return;
    }
    
    if (!sku || sku.trim() === '') {
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.sku_required) ? window.ADMIN_PRODUCTS_I18N.sku_required : 'Por favor, informe o SKU do produto!');
        return;
    }
    
    if (sku.trim().length < 3) {
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.sku_min_length) ? window.ADMIN_PRODUCTS_I18N.sku_min_length : 'O SKU deve ter pelo menos 3 caracteres!');
        return;
    }
    
    if (!descricao_curta || descricao_curta.trim() === '') {
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.desc_required) ? window.ADMIN_PRODUCTS_I18N.desc_required : 'Por favor, informe a descrição do produto!');
        return;
    }
    
    if (!categoriaId || categoriaId === '') {
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.category_required) ? window.ADMIN_PRODUCTS_I18N.category_required : 'Por favor, selecione uma categoria!');
        return;
    }
    
    if (isNaN(valor) || valor <= 0) {
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.invalid_usd_value) ? window.ADMIN_PRODUCTS_I18N.invalid_usd_value : 'Por favor, informe um valor válido em USD!');
        return;
    }
    
    if (isNaN(peso) || peso < 0) {
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.invalid_weight) ? window.ADMIN_PRODUCTS_I18N.invalid_weight : 'Por favor, informe um peso válido!');
        return;
    }
    
    if (isNaN(estoque) || estoque < 0) {
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.invalid_stock) ? window.ADMIN_PRODUCTS_I18N.invalid_stock : 'Por favor, informe um estoque válido!');
        return;
    }
    
    // Limpar e formatar dados
    formData.set('nome', nome.trim());
    formData.set('sku', sku.trim().toUpperCase());
    formData.set('descricao_curta', descricao_curta.trim());
    formData.set('valor', valor.toFixed(2));
    formData.set('peso', peso.toFixed(3));
    formData.set('estoque', estoque);
    
    console.log('🔍 [PRODUTOS] Dados finais antes de enviar:');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    console.log('🔍 [PRODUTOS] Salvando produto - Edição:', isEdicao, 'ID:', produtoId, 'Valor USD:', valor);
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('🔍 [PRODUTOS] Status da resposta:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('🔍 [PRODUTOS] Resposta do servidor:', data);
        
        if (data.success) {
            const mensagem = isEdicao ?
                ((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.updated_success_usd) ? window.ADMIN_PRODUCTS_I18N.updated_success_usd : 'Produto atualizado com sucesso em USD!') :
                ((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.created_success_usd) ? window.ADMIN_PRODUCTS_I18N.created_success_usd : 'Produto criado com sucesso em USD!');
            alert(mensagem);
            
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalProduto'));
            if (modal) {
                modal.hide();
            }
            
            // Recarregar página para mostrar alterações
            location.reload();
        } else {
            console.error('❌ [PRODUTOS] Erro do servidor:', data.error);
            alert(((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.error_save_prefix) ? window.ADMIN_PRODUCTS_I18N.error_save_prefix : 'Erro ao salvar produto:') + ' ' + data.error);
        }
    })
    .catch(error => {
        console.error('🔍 [PRODUTOS] Erro ao salvar produto:', error);
        alert((window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.error_save_check_console) ? window.ADMIN_PRODUCTS_I18N.error_save_check_console : 'Erro ao salvar produto. Verifique o console para mais detalhes.');
    });
}

// Função para resetar formulário e garantir USD
function resetarFormularioProduto() {
    document.getElementById('formProduto').reset();
    document.getElementById('modalProdutoTitle').textContent = (window.ADMIN_PRODUCTS_I18N && window.ADMIN_PRODUCTS_I18N.new_title) ? window.ADMIN_PRODUCTS_I18N.new_title : 'Novo Produto';
    document.getElementById('produto_id').value = '';
    
    // Forçar moeda USD para novos produtos
    const moedaSelect = document.querySelector('select[name="moeda"]');
    if (moedaSelect) {
        moedaSelect.value = 'USD';
    }
    
    console.log('🔍 [PRODUTOS] Formulário resetado com moeda USD');
}

// Event listener para quando o modal de produto for aberto
document.addEventListener('DOMContentLoaded', function() {
    const modalProduto = document.getElementById('modalProduto');
    if (modalProduto) {
        modalProduto.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            
            // Se não for um botão de edição, é um novo produto
            if (!button || !button.getAttribute('onclick') || !button.getAttribute('onclick').includes('editarProduto')) {
                resetarFormularioProduto();
            }
        });
    }
});

</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
