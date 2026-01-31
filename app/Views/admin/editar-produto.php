<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-edit me-2"></i>
            Editar Produto
        </h1>
        <div>
            <a href="/admin/produtos" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar para Produtos
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
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($produto['nome'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SKU *</label>
                        <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($produto['sku'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrição Curta *</label>
                        <textarea name="descricao_curta" class="form-control" rows="3" required><?= htmlspecialchars($produto['descricao_curta'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrição Completa</label>
                        <textarea name="descricao_completa" class="form-control" rows="5"><?= htmlspecialchars($produto['descricao_completa'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Categoria *</label>
                        <select name="categoria_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= $categoria['id'] ?>" <?= ($produto['categoria_id'] ?? '') == $categoria['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor (USD) *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="valor" class="form-control" step="0.01" value="<?= number_format($produto['valor'] ?? 0, 2, '.', '') ?>" placeholder="0.00" required>
                            <span class="input-group-text">USD</span>
                        </div>
                        <small class="text-muted">Todos os produtos devem ser cadastrados em Dólar Americano (USD)</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Moeda</label>
                        <select name="moeda" class="form-select" required>
                            <option value="USD" <?= ($produto['moeda'] ?? 'USD') == 'USD' ? 'selected' : '' ?>>Dólar Americano (USD) - Padrão</option>
                            <option value="BRL" disabled>Real Brasileiro (BRL) - Desativado</option>
                        </select>
                        <small class="text-muted">Moeda padrão fixada em USD para todos os produtos</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Peso (kg)</label>
                        <input type="number" name="peso" class="form-control" step="0.001" value="<?= number_format($produto['peso'] ?? 0, 3, '.', '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estoque</label>
                        <input type="number" name="estoque" class="form-control" value="<?= $produto['estoque'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="ativo" <?= ($produto['status'] ?? 'ativo') == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= ($produto['status'] ?? 'ativo') == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    
                    <!-- Imagem Atual -->
                    <?php 
                    $fotoUrl = !empty($produto['foto_principal']) ? $produto['foto_principal'] : null;
                    if ($fotoUrl): 
                    ?>
                        <div class="col-12">
                            <label class="form-label">Imagem Atual</label>
                            <div class="mb-3">
                                <a href="<?= $fotoUrl ?>" target="_blank" class="text-decoration-none">
                                    <img src="<?= $fotoUrl ?>?v=<?= time() ?>" 
                                         alt="Imagem atual" 
                                         style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;"
                                         title="Clique para ver imagem em tamanho real">
                                </a>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-external-link-alt"></i> 
                                        <a href="<?= $fotoUrl ?>" target="_blank">Abrir imagem em nova aba</a>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-12">
                        <label class="form-label">Nova Imagem Principal</label>
                        <input type="file" name="imagem_principal" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp">
                        <small class="text-muted">Formatos aceitos: JPEG, JPG, PNG, WebP (Máx: 5MB). Deixe em branco para manter a imagem atual.</small>
                    </div>
                    
                    <!-- Galería de Imagens -->
                    <?php if (!empty($galeria)): ?>
                    <div class="col-12">
                        <label class="form-label">Galeria de Imagens</label>
                        <div class="row g-3">
                            <?php foreach ($galeria as $foto): ?>
                                <div class="col-md-3 col-sm-4 col-6">
                                    <div class="card border-0 shadow-sm">
                                        <?php if ($foto['arquivo_existe']): ?>
                                            <a href="<?= $foto['url_completa'] ?>" target="_blank" class="text-decoration-none">
                                                <img src="<?= $foto['url_completa'] ?>?v=<?= time() ?>" 
                                                     alt="<?= htmlspecialchars($foto['legenda'] ?? 'Imagem') ?>" 
                                                     class="card-img-top img-fluid"
                                                     style="height: 150px; object-fit: cover; cursor: pointer;"
                                                     title="<?= $foto['principal'] ? 'Imagem Principal' : 'Imagem da Galeria' ?>">
                                                <?php if ($foto['principal']): ?>
                                                    <span class="position-absolute top-0 start-0 badge" style="margin: 10px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">Principal</span>
                                                <?php endif; ?>
                                            </a>
                                            <div class="card-body p-2">
                                                <small class="text-muted d-block text-truncate" title="<?= htmlspecialchars($foto['arquivo_original'] ?? '') ?>">
                                                    <?= htmlspecialchars($foto['arquivo_original'] ?? 'Imagem') ?>
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
                                                <small class="text-muted">Arquivo não encontrado</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-12">
                        <label class="form-label">Adicionar Imagens à Galeria</label>
                        <input type="file" name="imagens[]" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp" multiple>
                        <small class="text-muted">Formatos aceitos: JPEG, JPG, PNG, WebP (Máx: 5MB por imagem). Selecione múltiplas imagens para adicionar à galeria.</small>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Atualizar Produto
                    </button>
                    <a href="/admin/produtos" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function marcarComoPrincipal(fotoId) {
    if (confirm('Deseja marcar esta imagem como principal?')) {
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
                alert('Erro ao marcar imagem como principal: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao marcar imagem como principal');
        });
    }
}

function excluirImagem(fotoId) {
    if (confirm('Deseja excluir esta imagem? Esta ação não pode ser desfeita.')) {
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
                alert('Erro ao excluir imagem: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir imagem');
        });
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';
?>
