<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Nova Fatura CN38 (Pacotes WP)</h1>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/pacotes-wordpress/faturas"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="alert alert-danger" id="faturaError" style="display:none;"></div>
    <div class="alert alert-success" id="faturaSuccess" style="display:none;"></div>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong>Selecione os containers para a fatura</strong></div>
        <div class="card-body">
            <?php $containers = isset($containers) && is_array($containers) ? $containers : []; ?>
            <?php if (empty($containers)): ?>
                <p class="text-muted">Nenhum container disponível (sem fatura). Crie containers primeiro.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="checkAllContainers" onchange="toggleAllContainers(this)"></th>
                                <th>#</th>
                                <th>Nome</th>
                                <th>Dispatch</th>
                                <th>Trackings</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($containers as $c): ?>
                                <?php
                                    $cid = (int) ($c['id'] ?? 0);
                                    $trackings = json_decode((string) ($c['tracking_numbers_json'] ?? '[]'), true) ?: [];
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="chk-container" value="<?= $cid ?>"></td>
                                    <td><?= $cid ?></td>
                                    <td><?= htmlspecialchars((string) ($c['nome'] ?? '-')) ?></td>
                                    <td><?= (int) ($c['dispatch_number'] ?? 0) ?></td>
                                    <td><span class="badge bg-secondary"><?= count($trackings) ?></span></td>
                                    <td><?= !empty($c['created_at']) ? date('d/m/Y H:i', strtotime((string) $c['created_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary" onclick="criarFatura()" id="btnCriarFatura">
                        <i class="fas fa-file-invoice me-1"></i>Gerar Fatura CN38
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleAllContainers(el) {
    document.querySelectorAll('.chk-container').forEach(chk => chk.checked = el.checked);
}

async function criarFatura() {
    const errorEl = document.getElementById('faturaError');
    const successEl = document.getElementById('faturaSuccess');
    errorEl.style.display = 'none';
    successEl.style.display = 'none';

    const containerIds = [];
    document.querySelectorAll('.chk-container:checked').forEach(chk => containerIds.push(parseInt(chk.value)));

    if (containerIds.length === 0) {
        errorEl.textContent = 'Selecione ao menos um container.';
        errorEl.style.display = '';
        return;
    }

    const btn = document.getElementById('btnCriarFatura');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';

    try {
        const r = await fetch('/admin/pacotes-wordpress/faturas/criar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ container_ids: containerIds })
        });
        const data = await r.json();
        if (data.success) {
            successEl.textContent = 'Fatura ' + data.cn38_code + ' criada com ' + data.tracking_count + ' trackings!';
            successEl.style.display = '';
            setTimeout(() => window.location.href = '/admin/pacotes-wordpress/faturas', 1500);
        } else {
            errorEl.textContent = data.error || 'Erro ao criar fatura.';
            errorEl.style.display = '';
        }
    } catch (e) {
        errorEl.textContent = 'Erro: ' + e.message;
        errorEl.style.display = '';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-invoice me-1"></i>Gerar Fatura CN38';
    }
}
</script>

<?php
$content = ob_get_clean();
$title = 'Nova Fatura CN38 - Pacotes WordPress';
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
