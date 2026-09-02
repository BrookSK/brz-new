<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-box me-2"></i>
            <?= $pacote ? __('admin.received_packages.edit_package_num', 'Editar Pacote #{id}', ['id' => $pacote['id']]) : __('admin.received_packages.new_received_package', 'Novo Pacote Recebido') ?>
        </h1>
        <a href="/admin/pacotes-recebidos" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i><?= __('admin.received_packages.back', 'Voltar') ?>
        </a>
    </div>

    <!-- Mensagem Flash -->
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if ($pacote && !$editavel): ?>
        <div class="alert alert-warning">
            <i class="fas fa-lock me-2"></i><?= __('admin.received_packages.cannot_edit_left_pending', 'Este pacote não pode mais ser editado pois já saiu do status "Pendente".') ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="/admin/pacotes-recebidos/salvar" enctype="multipart/form-data">
                <?php if ($pacote): ?>
                    <input type="hidden" name="id" value="<?= $pacote['id'] ?>">
                    <input type="hidden" name="foto_url_existente" value="<?= htmlspecialchars($pacote['foto_url'] ?? '') ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <!-- Suite / Nome -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.suite_or_client_name', 'Suite ou Nome do Cliente') ?> *</label>
                        <div class="input-group">
                            <input type="text" name="numero_suite" id="numero_suite" class="form-control" 
                                   value="<?= htmlspecialchars($pacote['numero_suite'] ?? '') ?>" 
                                   placeholder="<?= htmlspecialchars(__('admin.received_packages.suite_or_name_placeholder', 'Suite ou nome...'), ENT_QUOTES, 'UTF-8') ?>"
                                   required <?= !$editavel ? 'readonly' : '' ?>>
                            <button type="button" class="btn btn-outline-primary" id="btnBuscarSuite" <?= !$editavel ? 'disabled' : '' ?>>
                                <i class="fas fa-search"></i> <?= __('admin.received_packages.search', 'Buscar') ?>
                            </button>
                        </div>
                        <div id="suiteInfo" class="form-text text-success" style="display:none;"></div>
                        <div id="suiteErro" class="form-text text-danger" style="display:none;"></div>
                        <small class="form-text text-muted"><?= __('admin.received_packages.enter_suite_or_client_name', 'Digite o número da suite ou o nome do cliente.') ?></small>
                    </div>

                    <!-- Nome do Produto -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.product_name', 'Nome do Produto') ?> *</label>
                        <input type="text" name="nome" class="form-control" 
                               value="<?= htmlspecialchars($pacote['nome'] ?? '') ?>" 
                               required <?= !$editavel ? 'readonly' : '' ?>>
                    </div>

                    <!-- Fornecedor -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.supplier_store', 'Fornecedor/Loja') ?> *</label>
                        <input type="text" name="fornecedor" class="form-control" 
                               value="<?= htmlspecialchars($pacote['fornecedor'] ?? '') ?>" 
                               required <?= !$editavel ? 'readonly' : '' ?>>
                    </div>

                    <!-- NCM -->
                    <div class="col-md-5">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.ncm_fiscal_code', 'NCM (Código Fiscal)') ?> *</label>
                        <input type="text" id="ncm_search" class="form-control" placeholder="<?= htmlspecialchars(__('admin.received_packages.ncm_filter_placeholder', 'Digite para filtrar (ex: celular, bolsa, 8517...)'), ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="off" <?= !$editavel ? 'readonly' : '' ?>
                               value="<?= isset($pacote['ncm']) && $pacote['ncm'] ? $pacote['ncm'] . ' - ' . ($ncmOptions[$pacote['ncm']] ?? '') : '' ?>">
                        <input type="hidden" name="ncm" id="ncm_value" value="<?= htmlspecialchars($pacote['ncm'] ?? '') ?>" required>
                        <div id="ncm_dropdown" class="list-group position-absolute shadow-sm" style="z-index:1050;max-height:250px;overflow-y:auto;display:none;width:calc(100% - 24px);"></div>
                        <small class="form-text text-muted"><?= __('admin.received_packages.ncm_start_typing', 'Comece a digitar o nome ou código NCM.') ?></small>
                    </div>

                    <!-- Data Recebimento -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.receipt_date', 'Data de Recebimento') ?> *</label>
                        <input type="date" name="data_recebimento" class="form-control" 
                               value="<?= htmlspecialchars($pacote['data_recebimento'] ?? date('Y-m-d')) ?>" 
                               required <?= !$editavel ? 'readonly' : '' ?>>
                    </div>

                    <!-- Peso -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.weight_kg', 'Peso (kg)') ?> *</label>
                        <input type="number" name="peso_kg" class="form-control" step="0.001" min="0.001"
                               value="<?= htmlspecialchars($pacote['peso_kg'] ?? '') ?>" 
                               required <?= !$editavel ? 'readonly' : '' ?>>
                    </div>

                    <!-- Quantidade -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.quantity', 'Quantidade') ?> *</label>
                        <input type="number" name="quantidade" class="form-control" min="1"
                               value="<?= htmlspecialchars($pacote['quantidade'] ?? '1') ?>" 
                               required <?= !$editavel ? 'readonly' : '' ?>>
                    </div>

                    <!-- Descrição -->
                    <div class="col-12">
                        <label class="form-label"><?= __('admin.received_packages.description_notes', 'Descrição / Observações') ?></label>
                        <textarea name="descricao" class="form-control" rows="3" <?= !$editavel ? 'readonly' : '' ?>><?= htmlspecialchars($pacote['descricao'] ?? '') ?></textarea>
                    </div>

                    <!-- Foto -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.product_photo', 'Foto do Produto') ?> *</label>
                        <input type="file" name="foto" class="form-control" accept="image/*" <?= !$editavel ? 'disabled' : '' ?> <?= !$pacote ? 'required' : '' ?>>
                        <small class="form-text text-muted"><?= __('admin.received_packages.photo_formats_max', 'JPG, PNG, WebP ou GIF. Max 5MB.') ?></small>
                    </div>

                    <!-- Foto existente -->
                    <?php if (!empty($pacote['foto_url'])): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= __('admin.received_packages.current_photo', 'Foto Atual') ?></label><br>
                        <img src="<?= htmlspecialchars($pacote['foto_url']) ?>" alt="<?= htmlspecialchars(__('admin.received_packages.package_photo_alt', 'Foto do pacote'), ENT_QUOTES, 'UTF-8') ?>" 
                             style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                    </div>
                    <?php endif; ?>

                    <!-- Status (somente leitura quando editando) -->
                    <?php if ($pacote): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('admin.received_packages.current_status', 'Status Atual') ?></label>
                        <input type="text" class="form-control" value="<?= \App\Controllers\AdminPacotesRecebidosController::getStatusList()[$pacote['status']] ?? $pacote['status'] ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('admin.received_packages.storage_days', 'Dias de Armazenamento') ?></label>
                        <input type="text" class="form-control" value="<?= (int)$pacote['dias_armazenamento'] ?>" readonly>
                    </div>
                    <?php if (!empty($pacote['pedido_id'])): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('admin.received_packages.linked_order', 'Pedido Vinculado') ?></label>
                        <a href="/admin/pedidos/<?= $pacote['pedido_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-external-link-alt me-1"></i><?= __('admin.received_packages.order_num', 'Pedido #{id}', ['id' => $pacote['pedido_id']]) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($editavel): ?>
                <hr class="my-4">
                <div class="d-flex justify-content-end gap-2">
                    <a href="/admin/pacotes-recebidos" class="btn btn-secondary"><?= __('admin.received_packages.cancel', 'Cancelar') ?></a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?= $pacote ? __('admin.received_packages.save_changes', 'Salvar Alterações') : __('admin.received_packages.register_package', 'Cadastrar Pacote') ?>
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
// === NCM - Campo com busca/filtro ===
(function() {
    const ncmOptions = <?= json_encode($ncmOptions, JSON_UNESCAPED_UNICODE) ?>;
    const searchInput = document.getElementById('ncm_search');
    const hiddenInput = document.getElementById('ncm_value');
    const dropdown = document.getElementById('ncm_dropdown');

    if (!searchInput || !dropdown) return;

    function renderOptions(filter) {
        dropdown.innerHTML = '';
        const term = (filter || '').toLowerCase();
        let count = 0;

        for (const [code, label] of Object.entries(ncmOptions)) {
            const text = code + ' - ' + label;
            if (term && !text.toLowerCase().includes(term)) continue;
            if (count >= 30) break; // limitar resultados visíveis

            const item = document.createElement('a');
            item.href = '#';
            item.className = 'list-group-item list-group-item-action py-1 px-2 small';
            item.textContent = text;
            item.dataset.code = code;
            item.addEventListener('mousedown', function(e) {
                e.preventDefault();
                searchInput.value = text;
                hiddenInput.value = code;
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(item);
            count++;
        }

        if (count === 0) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-muted small py-1';
            empty.textContent = '<?= htmlspecialchars(__('admin.received_packages.no_ncm_found', 'Nenhum NCM encontrado.'), ENT_QUOTES, 'UTF-8') ?>';
            dropdown.appendChild(empty);
        }

        dropdown.style.display = 'block';
    }

    searchInput.addEventListener('focus', function() {
        renderOptions(this.value);
    });

    searchInput.addEventListener('input', function() {
        hiddenInput.value = ''; // limpar seleção até selecionar novamente
        renderOptions(this.value);
    });

    searchInput.addEventListener('blur', function() {
        setTimeout(() => { dropdown.style.display = 'none'; }, 200);
    });

    // Se limpar o campo, limpar o hidden
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdown.style.display = 'none';
        }
    });
})();

// === Busca de usuario por suite ou nome (AJAX) ===
document.getElementById('btnBuscarSuite')?.addEventListener('click', function() {
    const termo = document.getElementById('numero_suite').value.trim();
    const infoEl = document.getElementById('suiteInfo');
    const erroEl = document.getElementById('suiteErro');
    
    infoEl.style.display = 'none';
    erroEl.style.display = 'none';

    if (!termo) {
        erroEl.textContent = '<?= htmlspecialchars(__('admin.received_packages.enter_suite_or_name', 'Informe um número de suite ou nome.'), ENT_QUOTES, 'UTF-8') ?>';
        erroEl.style.display = 'block';
        return;
    }

    fetch('/api/buscar-usuario-suite?busca=' + encodeURIComponent(termo))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Preencher o campo com a suite real encontrada
                document.getElementById('numero_suite').value = data.usuario.suite;
                infoEl.innerHTML = '<i class="fas fa-check-circle me-1"></i>' + data.usuario.nome + ' (' + data.usuario.email + ') - <?= htmlspecialchars(__('admin.received_packages.suite_label', 'Suite:'), ENT_QUOTES, 'UTF-8') ?> ' + data.usuario.suite;
                infoEl.style.display = 'block';
            } else {
                erroEl.textContent = data.message || '<?= htmlspecialchars(__('admin.received_packages.no_client_found_dot', 'Nenhum cliente encontrado.'), ENT_QUOTES, 'UTF-8') ?>';
                erroEl.style.display = 'block';
            }
        })
        .catch(() => {
            erroEl.textContent = '<?= htmlspecialchars(__('admin.received_packages.search_error_try_again', 'Erro ao buscar. Tente novamente.'), ENT_QUOTES, 'UTF-8') ?>';
            erroEl.style.display = 'block';
        });
});

// Auto-buscar ao sair do campo suite
document.getElementById('numero_suite')?.addEventListener('blur', function() {
    if (this.value && parseInt(this.value) > 0) {
        document.getElementById('btnBuscarSuite')?.click();
    }
});
</script>

<?php
$content = ob_get_clean();
$title = ($pacote ? __('admin.received_packages.edit_package_num', 'Editar Pacote #{id}', ['id' => $pacote['id']]) : __('admin.received_packages.new_package', 'Novo Pacote')) . ' - ' . __('admin.received_packages.admin_suffix', 'Admin');
include __DIR__ . '/../../layouts/admin.php';
?>
