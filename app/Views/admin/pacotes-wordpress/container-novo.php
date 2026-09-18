<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><?= __('admin.wp_packages.new_container_title', 'Novo Container (Pacotes WP)') ?></h1>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/pacotes-wordpress?action=containers"><i class="fas fa-arrow-left me-1"></i><?= __('admin.wp_packages.back', 'Voltar') ?></a>
    </div>

    <div class="alert alert-danger" id="containerError" style="display:none;"></div>
    <div class="alert alert-success" id="containerSuccess" style="display:none;"></div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><strong><?= __('admin.wp_packages.create_container', 'Criar Container') ?></strong></div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label"><?= __('admin.wp_packages.container_name_optional', 'Nome do Container (opcional)') ?></label>
                    <input type="text" class="form-control" id="containerNome" placeholder="<?= htmlspecialchars(__('admin.wp_packages.container_name_placeholder', 'Ex: Container BR - Maio 2026'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <h6 class="mb-2"><?= __('admin.wp_packages.select_tracking_numbers', 'Selecione os tracking numbers:') ?></h6>
            <div class="mb-2">
                <input type="text" class="form-control form-control-sm" id="filtroTracking" placeholder="<?= htmlspecialchars(__('admin.wp_packages.filter_tracking_order', 'Filtrar por tracking ou pedido...'), ENT_QUOTES, 'UTF-8') ?>" oninput="filtrarDisponiveis()">
            </div>

            <div class="mb-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="selecionarTodos()"><?= __('admin.wp_packages.select_all', 'Selecionar todos') ?></button>
                <button class="btn btn-sm btn-outline-secondary" onclick="deselecionarTodos()"><?= __('admin.wp_packages.deselect_all', 'Desmarcar todos') ?></button>
                <span class="ms-2 small text-muted" id="countSelecionados">0 <?= __('admin.wp_packages.selected', 'selecionados') ?></span>
            </div>

            <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                <table class="table table-sm align-middle" id="tabelaDisponiveis">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
                            <th><?= __('admin.wp_packages.origin', 'Origem') ?></th>
                            <th><?= __('admin.wp_packages.order', 'Pedido') ?></th>
                            <th><?= __('admin.wp_packages.customer', 'Cliente') ?></th>
                            <th><?= __('admin.wp_packages.tracking', 'Tracking') ?></th>
                            <th><?= __('admin.wp_packages.date', 'Data') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $disponiveis = isset($disponiveis) && is_array($disponiveis) ? $disponiveis : []; ?>
                        <?php if (empty($disponiveis)): ?>
                            <tr><td colspan="6" class="text-muted"><?= __('admin.wp_packages.no_available_labels', 'Nenhuma etiqueta disponível (sem container). Sincronize primeiro.') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($disponiveis as $e): ?>
                                <?php
                                    $trk = (string) ($e['tracking_number'] ?? '');
                                    $origem = strtoupper((string) ($e['origem'] ?? ''));
                                    $pedidoId = (int) ($e['pedido_id'] ?? 0);
                                ?>
                                <tr class="row-disponivel" data-tracking="<?= htmlspecialchars($trk) ?>" data-pedido="<?= $pedidoId ?>" data-nome="<?= htmlspecialchars((string) ($e['cliente_nome'] ?? '')) ?>">
                                    <td><input type="checkbox" class="chk-tracking" value="<?= htmlspecialchars($trk) ?>" onchange="atualizarCount()"></td>
                                    <td><span class="badge bg-<?= $origem === 'BR' ? 'success' : ($origem === 'RED' ? 'warning' : 'info') ?>"><?= $origem ?></span></td>
                                    <td><?= $pedidoId > 0 ? '#' . $pedidoId : '-' ?></td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></td>
                                    <td><code class="small"><?= htmlspecialchars($trk) ?></code></td>
                                    <td><?= !empty($e['created_at']) ? date('d/m/Y', strtotime((string) $e['created_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <button class="btn btn-primary" onclick="criarContainer()" id="btnCriar">
                    <i class="fas fa-box me-1"></i><?= __('admin.wp_packages.create_container', 'Criar Container') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function filtrarDisponiveis() {
    const filtro = document.getElementById('filtroTracking').value.toLowerCase();
    document.querySelectorAll('.row-disponivel').forEach(row => {
        const trk = (row.dataset.tracking || '').toLowerCase();
        const pedido = (row.dataset.pedido || '').toLowerCase();
        const nome = (row.dataset.nome || '').toLowerCase();
        const match = trk.includes(filtro) || pedido.includes(filtro) || nome.includes(filtro);
        row.style.display = match ? '' : 'none';
    });
}

function toggleAll(el) {
    document.querySelectorAll('.chk-tracking').forEach(chk => {
        if (chk.closest('tr').style.display !== 'none') {
            chk.checked = el.checked;
        }
    });
    atualizarCount();
}

function selecionarTodos() {
    document.querySelectorAll('.chk-tracking').forEach(chk => {
        if (chk.closest('tr').style.display !== 'none') chk.checked = true;
    });
    atualizarCount();
}

function deselecionarTodos() {
    document.querySelectorAll('.chk-tracking').forEach(chk => chk.checked = false);
    atualizarCount();
}

function atualizarCount() {
    const count = document.querySelectorAll('.chk-tracking:checked').length;
    document.getElementById('countSelecionados').textContent = count + ' <?= htmlspecialchars(__('admin.wp_packages.selected', 'selecionados'), ENT_QUOTES, 'UTF-8') ?>';
}

async function criarContainer() {
    const errorEl = document.getElementById('containerError');
    const successEl = document.getElementById('containerSuccess');
    errorEl.style.display = 'none';
    successEl.style.display = 'none';

    const nome = document.getElementById('containerNome').value.trim();
    const trackings = [];
    document.querySelectorAll('.chk-tracking:checked').forEach(chk => trackings.push(chk.value));

    if (trackings.length === 0) {
        errorEl.textContent = '<?= htmlspecialchars(__('admin.wp_packages.select_one_tracking', 'Selecione ao menos um tracking number.'), ENT_QUOTES, 'UTF-8') ?>';
        errorEl.style.display = '';
        return;
    }

    const btn = document.getElementById('btnCriar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i><?= htmlspecialchars(__('admin.wp_packages.creating', 'Criando...'), ENT_QUOTES, 'UTF-8') ?>';

    try {
        const r = await fetch('/admin/pacotes-wordpress?action=container-criar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ nome, trackings })
        });
        const data = await r.json();
        if (data.success) {
            successEl.textContent = '<?= htmlspecialchars(__('admin.wp_packages.container', 'Container'), ENT_QUOTES, 'UTF-8') ?> #' + data.container_id + ' <?= htmlspecialchars(__('admin.wp_packages.created_with', 'criado com'), ENT_QUOTES, 'UTF-8') ?> ' + data.tracking_count + ' <?= htmlspecialchars(__('admin.wp_packages.trackings_excl', 'trackings!'), ENT_QUOTES, 'UTF-8') ?>';
            successEl.style.display = '';
            setTimeout(() => window.location.href = '/admin/pacotes-wordpress?action=containers', 1500);
        } else {
            errorEl.textContent = data.error || '<?= htmlspecialchars(__('admin.wp_packages.error_create_container', 'Erro ao criar container.'), ENT_QUOTES, 'UTF-8') ?>';
            errorEl.style.display = '';
        }
    } catch (e) {
        errorEl.textContent = '<?= htmlspecialchars(__('admin.wp_packages.error', 'Erro:'), ENT_QUOTES, 'UTF-8') ?> ' + e.message;
        errorEl.style.display = '';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-box me-1"></i><?= htmlspecialchars(__('admin.wp_packages.create_container', 'Criar Container'), ENT_QUOTES, 'UTF-8') ?>';
    }
}
</script>

<?php
$content = ob_get_clean();
$title = __('admin.wp_packages.new_container_page_title', 'Novo Container - Pacotes WordPress');
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
