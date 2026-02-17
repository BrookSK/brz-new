<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-edit me-2"></i>
            <?= __('admin.edit_product.title', 'Editar Produto') ?>
        </h1>
        <div>
            <a href="/admin/produtos" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= __('admin.edit_product.back_to_products', 'Voltar para Produtos') ?>
            </a>
        </div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_GET['success']) ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="/admin/atualizar-produto/<?= $produto['id'] ?>" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $produto['id'] ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><?= __('common.name', 'Nome') ?> *</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($produto['nome'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= __('admin.products.sku', 'SKU') ?> *</label>
                        <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($produto['sku'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= __('admin.edit_product.short_description', 'Descrição Curta') ?> *</label>
                        <textarea name="descricao_curta" class="form-control" rows="3" required><?= htmlspecialchars($produto['descricao_curta'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= __('admin.edit_product.full_description', 'Descrição Completa') ?></label>
                        <textarea name="descricao_completa" class="form-control" rows="5"><?= htmlspecialchars($produto['descricao_completa'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('admin.products.filter.category', 'Categoria') ?> *</label>
                        <select name="categoria_id" class="form-select" required>
                            <option value=""><?= __('common.select', 'Selecione...') ?></option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= $categoria['id'] ?>" <?= ($produto['categoria_id'] ?? '') == $categoria['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('admin.edit_product.value_usd', 'Valor (USD)') ?> *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="valor" class="form-control" step="0.01" value="<?= number_format($produto['valor'] ?? 0, 2, '.', '') ?>" placeholder="0.00" required>
                            <span class="input-group-text">USD</span>
                        </div>
                        <small class="text-muted"><?= __('admin.edit_product.usd_only_hint', 'Todos os produtos devem ser cadastrados em Dólar Americano (USD)') ?></small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('admin.edit_product.currency', 'Moeda') ?></label>
                        <select name="moeda" class="form-select" required>
                            <option value="USD" <?= ($produto['moeda'] ?? 'USD') == 'USD' ? 'selected' : '' ?>><?= __('admin.edit_product.currency_usd_default', 'Dólar Americano (USD) - Padrão') ?></option>
                            <option value="BRL" disabled><?= __('admin.edit_product.currency_brl_disabled', 'Real Brasileiro (BRL) - Desativado') ?></option>
                        </select>
                        <small class="text-muted"><?= __('admin.edit_product.currency_usd_fixed_hint', 'Moeda padrão fixada em USD para todos os produtos') ?></small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?= __('admin.edit_product.weight_kg', 'Peso (kg)') ?></label>
                        <input type="number" name="peso" class="form-control" step="0.001" value="<?= number_format($produto['peso'] ?? 0, 3, '.', '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?= __('admin.products.table.stock', 'Estoque') ?></label>
                        <input type="number" name="estoque" class="form-control" value="<?= $produto['estoque'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= __('common.status', 'Status') ?></label>
                        <select name="status" class="form-select" required>
                            <option value="ativo" <?= ($produto['status'] ?? 'ativo') == 'ativo' ? 'selected' : '' ?>><?= __('admin.products.status.active', 'Ativo') ?></option>
                            <option value="inativo" <?= ($produto['status'] ?? 'ativo') == 'inativo' ? 'selected' : '' ?>><?= __('admin.products.status.inactive', 'Inativo') ?></option>
                        </select>
                    </div>
                    
                    <!-- Imagem Atual -->
                    <?php 
                    $fotoUrl = !empty($produto['foto_principal']) ? $produto['foto_principal'] : null;
                    if ($fotoUrl): 
                    ?>
                        <div class="col-12">
                            <label class="form-label"><?= __('admin.edit_product.current_image', 'Imagem Atual') ?></label>
                            <div class="mb-3">
                                <a href="<?= $fotoUrl ?>" target="_blank" class="text-decoration-none">
                                    <img src="<?= $fotoUrl ?>?v=<?= time() ?>" 
                                         alt="<?= htmlspecialchars(__('admin.edit_product.current_image_alt', 'Imagem atual'), ENT_QUOTES, 'UTF-8') ?>" 
                                         style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;"
                                         title="<?= htmlspecialchars(__('product_details.click_full_size', 'Clique para ver imagem em tamanho real'), ENT_QUOTES, 'UTF-8') ?>">
                                </a>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-external-link-alt"></i> 
                                        <a href="<?= $fotoUrl ?>" target="_blank"><?= __('admin.edit_product.open_image_new_tab', 'Abrir imagem em nova aba') ?></a>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-12">
                        <label class="form-label"><?= __('admin.edit_product.new_main_image', 'Nova Imagem Principal') ?></label>
                        <input type="file" name="imagem_principal" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp">
                        <small class="text-muted"><?= __('admin.edit_product.main_image_hint', 'Formatos aceitos: JPEG, JPG, PNG, WebP (Máx: 5MB). Deixe em branco para manter a imagem atual.') ?></small>
                    </div>
                    
                    <!-- Galería de Imagens -->
                    <?php if (!empty($galeria)): ?>
                    <div class="col-12">
                        <label class="form-label"><?= __('admin.edit_product.gallery', 'Galeria de Imagens') ?></label>
                        <div class="row g-3">
                            <?php foreach ($galeria as $foto): ?>
                                <div class="col-md-3 col-sm-4 col-6">
                                    <div class="card border-0 shadow-sm">
                                        <?php if ($foto['arquivo_existe']): ?>
                                            <a href="<?= $foto['url_completa'] ?>" target="_blank" class="text-decoration-none">
                                                <img src="<?= $foto['url_completa'] ?>?v=<?= time() ?>" 
                                                     alt="<?= htmlspecialchars($foto['legenda'] ?? __('admin.edit_product.image', 'Imagem')) ?>" 
                                                     class="card-img-top img-fluid"
                                                     style="height: 150px; object-fit: cover; cursor: pointer;"
                                                     title="<?= htmlspecialchars($foto['principal'] ? __('admin.edit_product.main_image', 'Imagem Principal') : __('admin.edit_product.gallery_image', 'Imagem da Galeria'), ENT_QUOTES, 'UTF-8') ?>">
                                                <?php if ($foto['principal']): ?>
                                                    <span class="position-absolute top-0 start-0 badge" style="margin: 10px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);"><?= __('admin.edit_product.main_badge', 'Principal') ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <div class="card-body p-2">
                                                <small class="text-muted d-block text-truncate" title="<?= htmlspecialchars($foto['arquivo_original'] ?? '') ?>">
                                                    <?= htmlspecialchars($foto['arquivo_original'] ?? __('admin.edit_product.image', 'Imagem')) ?>
                                                </small>
                                                <div class="btn-group btn-group-sm w-100" role="group">
                                                    <?php if (!$foto['principal']): ?>
                                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="marcarComoPrincipal(<?= $foto['id'] ?>)">
                                                            <i class="fas fa-star"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="excluirImagem(<?= $foto['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="card-body text-center">
                                                <i class="fas fa-image fa-3x text-muted mb-2"></i>
                                                <small class="text-muted"><?= __('admin.edit_product.file_not_found', 'Arquivo não encontrado') ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-12">
                        <label class="form-label"><?= __('admin.edit_product.add_images_to_gallery', 'Adicionar Imagens à Galeria') ?></label>
                        <input type="file" name="imagens[]" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp" multiple>
                        <small class="text-muted"><?= __('admin.edit_product.gallery_images_hint', 'Formatos aceitos: JPEG, JPG, PNG, WebP (Máx: 5MB por imagem). Selecione múltiplas imagens para adicionar à galeria.') ?></small>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?= __('admin.edit_product.update', 'Atualizar Produto') ?>
                    </button>
                    <a href="/admin/produtos" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i><?= __('common.cancel', 'Cancelar') ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.ADMIN_EDIT_PRODUCT_I18N = {
    confirm_set_main_image: <?= json_encode(__('admin.edit_product.js.confirm_set_main_image', 'Deseja marcar esta imagem como principal?'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_delete_image: <?= json_encode(__('admin.edit_product.js.confirm_delete_image', 'Deseja excluir esta imagem? Esta ação não pode ser desfeita.'), JSON_UNESCAPED_UNICODE) ?>,
    error_set_main_image_prefix: <?= json_encode(__('admin.edit_product.js.error_set_main_image_prefix', 'Erro ao marcar imagem como principal:'), JSON_UNESCAPED_UNICODE) ?>,
    error_set_main_image: <?= json_encode(__('admin.edit_product.js.error_set_main_image', 'Erro ao marcar imagem como principal'), JSON_UNESCAPED_UNICODE) ?>,
    error_delete_image_prefix: <?= json_encode(__('admin.edit_product.js.error_delete_image_prefix', 'Erro ao excluir imagem:'), JSON_UNESCAPED_UNICODE) ?>,
    error_delete_image: <?= json_encode(__('admin.edit_product.js.error_delete_image', 'Erro ao excluir imagem'), JSON_UNESCAPED_UNICODE) ?>,
    error_console_prefix: <?= json_encode(__('admin.edit_product.js.error_console_prefix', 'Erro:'), JSON_UNESCAPED_UNICODE) ?>
};

function marcarComoPrincipal(fotoId) {
    if (confirm((window.ADMIN_EDIT_PRODUCT_I18N && window.ADMIN_EDIT_PRODUCT_I18N.confirm_set_main_image) ? window.ADMIN_EDIT_PRODUCT_I18N.confirm_set_main_image : 'Deseja marcar esta imagem como principal?')) {
        fetch('/admin/marcar-foto-principal/' + fotoId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(((window.ADMIN_EDIT_PRODUCT_I18N && window.ADMIN_EDIT_PRODUCT_I18N.error_set_main_image_prefix) ? window.ADMIN_EDIT_PRODUCT_I18N.error_set_main_image_prefix : 'Erro ao marcar imagem como principal:') + ' ' + data.error);
            }
        })
        .catch(error => {
            console.error(((window.ADMIN_EDIT_PRODUCT_I18N && window.ADMIN_EDIT_PRODUCT_I18N.error_console_prefix) ? window.ADMIN_EDIT_PRODUCT_I18N.error_console_prefix : 'Erro:'), error);
            alert((window.ADMIN_EDIT_PRODUCT_I18N && window.ADMIN_EDIT_PRODUCT_I18N.error_set_main_image) ? window.ADMIN_EDIT_PRODUCT_I18N.error_set_main_image : 'Erro ao marcar imagem como principal');
        });
    }
}

function excluirImagem(fotoId) {
    if (confirm((window.ADMIN_EDIT_PRODUCT_I18N && window.ADMIN_EDIT_PRODUCT_I18N.confirm_delete_image) ? window.ADMIN_EDIT_PRODUCT_I18N.confirm_delete_image : 'Deseja excluir esta imagem? Esta ação não pode ser desfeita.')) {
        fetch('/admin/excluir-foto/' + fotoId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(((window.ADMIN_EDIT_PRODUCT_I18N && window.ADMIN_EDIT_PRODUCT_I18N.error_delete_image_prefix) ? window.ADMIN_EDIT_PRODUCT_I18N.error_delete_image_prefix : 'Erro ao excluir imagem:') + ' ' + data.error);
            }
        })
        .catch(error => {
            console.error(((window.ADMIN_EDIT_PRODUCT_I18N && window.ADMIN_EDIT_PRODUCT_I18N.error_console_prefix) ? window.ADMIN_EDIT_PRODUCT_I18N.error_console_prefix : 'Erro:'), error);
            alert((window.ADMIN_EDIT_PRODUCT_I18N && window.ADMIN_EDIT_PRODUCT_I18N.error_delete_image) ? window.ADMIN_EDIT_PRODUCT_I18N.error_delete_image : 'Erro ao excluir imagem');
        });
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';
?>
