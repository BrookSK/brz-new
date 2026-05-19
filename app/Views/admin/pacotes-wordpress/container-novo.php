<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Novo Container (Pacotes WP)</h1>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/pacotes-wordpress/containers"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="alert alert-danger" id="containerError" style="display:none;"></div>
    <div class="alert alert-success" id="containerSuccess" style="display:none;"></div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><strong>Criar Container</strong></div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome do Container (opcional)</label>
                    <input type="text" class="form-control" id="containerNome" placeholder="Ex: Container BR - Maio 2026">
                </div>
            </div>

            <h6 class="mb-2">Selecione os tracking numbers:</h6>
            <div class="mb-2">
                <input type="text" class="form-control form-control-sm" id="filtroTracking" placeholder="Filtrar por tracking ou pedido..." oninput="filtrarDisponiveis()">
            </div>

            <div class="mb-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="selecionarTodos()">Selecionar todos</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="deselecionarTodos()">Desmarcar todos</button>
                <span class="ms-2 small text-muted" id="countSelecionados">0 selecionados</span>
            </div>

            <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                <table class="table table-sm align-middle" id="tabelaDisponiveis">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
                            <th>Origem</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Tracking</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $disponiveis = isset($disponiveis) && is_array($disponiveis) ? $disponiveis : []; ?>
                        <?php if (empty($disponiveis)): ?>
                            <tr><td colspan="6" class="text-muted">Nenhuma etiqueta disponível (sem container). Sincronize primeiro.</td></tr>
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
                    <i class="fas fa-box me-1"></i>Criar Container
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
    document.getElementById('countSelecionados').textContent = count + ' selecionados';
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
        errorEl.textContent = 'Selecione ao menos um tracking number.';
        errorEl.style.display = '';
        return;
    }

    const btn = document.getElementById('btnCriar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Criando...';

    try {
        const r = await fetch('/admin/pacotes-wordpress/containers/criar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ nome, trackings })
        });
        const data = await r.json();
        if (data.success) {
            successEl.textContent = 'Container #' + data.container_id + ' criado com ' + data.tracking_count + ' trackings!';
            successEl.style.display = '';
            setTimeout(() => window.location.href = '/admin/pacotes-wordpress/containers', 1500);
        } else {
            errorEl.textContent = data.error || 'Erro ao criar container.';
            errorEl.style.display = '';
        }
    } catch (e) {
        errorEl.textContent = 'Erro: ' + e.message;
        errorEl.style.display = '';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-box me-1"></i>Criar Container';
    }
}
</script>

<?php
$content = ob_get_clean();
$title = 'Novo Container - Pacotes WordPress';
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
