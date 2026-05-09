<?php
/** @var array|null $live */
/** @var array $products */
/** @var array $eligibleProducts */
$isEdit = !empty($live);
?>
<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= $isEdit ? 'Editar Live' : 'Nova Live' ?></h1>
        <a href="/admin/lives" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <form method="POST" action="<?= $isEdit ? '/admin/lives/' . $live['id'] . '/atualizar' : '/admin/lives' ?>" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">Informações da Live</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input type="text" name="title" class="form-control" required
                                   value="<?= htmlspecialchars($live['title'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($live['description'] ?? '') ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data/Hora prevista</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control"
                                       value="<?= !empty($live['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($live['scheduled_at'])) : '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Método de transmissão</label>
                                <select name="ingest_method" class="form-select">
                                    <option value="webrtc" <?= ($live['ingest_method'] ?? 'webrtc') === 'webrtc' ? 'selected' : '' ?>>
                                        WebRTC (Navegador/Celular)
                                    </option>
                                    <option value="obs" <?= ($live['ingest_method'] ?? '') === 'obs' ? 'selected' : '' ?>>
                                        OBS (RTMPS)
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capa</label>
                            <input type="file" name="cover" class="form-control" accept="image/*">
                            <?php if (!empty($live['cover_url'])): ?>
                                <img src="<?= htmlspecialchars($live['cover_url']) ?>" class="mt-2" style="max-height:100px;border-radius:8px" alt="">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Produtos da Live -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Produtos da Live</span>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="productsList" class="list-group list-group-flush">
                            <?php foreach ($products as $p): ?>
                                <div class="list-group-item d-flex align-items-center" data-product-id="<?= $p['product_id'] ?>">
                                    <i class="fas fa-grip-vertical text-muted me-2" style="cursor:grab"></i>
                                    <div class="flex-grow-1">
                                        <strong><?= htmlspecialchars($p['display_name'] ?? '') ?></strong>
                                        <br><small class="text-muted">R$ <?= number_format((float)($p['display_price'] ?? 0), 2, ',', '.') ?></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.list-group-item').remove()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <input type="hidden" name="products[]" value="<?= $p['product_id'] ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($products)): ?>
                            <p class="text-center text-muted py-3 mb-0" id="noProductsMsg">
                                Nenhum produto adicionado. Selecione os produtos que serão vendidos nesta live.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Freemium -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="freemiumToggle"
                                   <?= (($live['free_seconds'] ?? 0) > 0) ? 'checked' : '' ?>
                                   onchange="document.getElementById('freemiumFields').classList.toggle('d-none')">
                            <label class="form-check-label" for="freemiumToggle">Freemium (paywall)</label>
                        </div>
                    </div>
                    <div class="card-body <?= (($live['free_seconds'] ?? 0) > 0) ? '' : 'd-none' ?>" id="freemiumFields">
                        <div class="mb-3">
                            <label class="form-label">Segundos grátis</label>
                            <input type="number" name="free_seconds" class="form-control" min="0"
                                   value="<?= (int)($live['free_seconds'] ?? 0) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço para desbloquear (R$)</label>
                            <input type="number" name="unlock_price" class="form-control" min="0" step="0.01"
                                   value="<?= number_format((float)($live['unlock_price'] ?? 0), 2, '.', '') ?>">
                        </div>
                        <small class="text-muted">Se ativado, o cliente assiste X segundos grátis e depois precisa pagar para continuar.</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-save me-2"></i> <?= $isEdit ? 'Salvar Alterações' : 'Criar Live' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Modal Adicionar Produto -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Produtos da Live</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control mb-3" placeholder="Buscar produto..." id="searchProduct"
                       oninput="filterProducts(this.value)">
                <div id="eligibleList" style="max-height:300px;overflow-y:auto">
                    <?php foreach ($eligibleProducts as $ep): ?>
                        <div class="eligible-item d-flex align-items-center p-2 border-bottom" 
                             data-id="<?= $ep['id'] ?>" data-name="<?= htmlspecialchars($ep['nome'] ?? '') ?>"
                             data-price="<?= (float)($ep['preco'] ?? 0) ?>">
                            <div class="flex-grow-1">
                                <strong><?= htmlspecialchars($ep['nome'] ?? 'Produto #' . $ep['id']) ?></strong>
                                <br><small>R$ <?= number_format((float)($ep['preco'] ?? 0), 2, ',', '.') ?></small>
                            </div>
                            <button type="button" class="btn btn-sm btn-success" onclick="addProductToLive(this)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function filterProducts(term) {
    term = term.toLowerCase();
    document.querySelectorAll('.eligible-item').forEach(el => {
        const name = el.dataset.name.toLowerCase();
        el.style.display = name.includes(term) ? '' : 'none';
    });
}

function addProductToLive(btn) {
    const item = btn.closest('.eligible-item');
    const id = item.dataset.id;
    const name = item.dataset.name;
    const price = parseFloat(item.dataset.price);

    if (document.querySelector(`#productsList [data-product-id="${id}"]`)) {
        alert('Produto já adicionado');
        return;
    }

    const noMsg = document.getElementById('noProductsMsg');
    if (noMsg) noMsg.remove();

    const html = `
        <div class="list-group-item d-flex align-items-center" data-product-id="${id}">
            <i class="fas fa-grip-vertical text-muted me-2" style="cursor:grab"></i>
            <div class="flex-grow-1">
                <strong>${name}</strong>
                <br><small class="text-muted">R$ ${price.toFixed(2).replace('.', ',')}</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.list-group-item').remove()">
                <i class="fas fa-times"></i>
            </button>
            <input type="hidden" name="products[]" value="${id}">
        </div>
    `;
    document.getElementById('productsList').insertAdjacentHTML('beforeend', html);
    
    bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
}
</script>
