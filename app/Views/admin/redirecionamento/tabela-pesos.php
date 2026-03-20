<?php
$sidebarActive = 'redirecionamento-tabela-pesos';
$title = 'Tabela de Pesos e Preços';
$tabela = is_array($tabela ?? null) ? $tabela : [];
$_perfilAtual = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
$_isAdmin = in_array($_perfilAtual, ['admin', 'suporte'], true);
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Tabela de Pesos e Preços</h1>
            <div class="text-muted small">Controla toda a cobrança do redirecionamento</div>
        </div>
    </div>

    <!-- Simulador -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="fas fa-calculator me-2 text-primary"></i>Simulador</h5>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Peso total (kg)</label>
                    <input class="form-control" type="number" step="0.001" min="0.001" id="simPeso" placeholder="Ex: 2.5">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Faixa (até kg)</label>
                    <input class="form-control" type="text" id="simFaixa" readonly placeholder="—">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valor (USD)</label>
                    <div class="input-group">
                        <span class="input-group-text">US$</span>
                        <input class="form-control fw-bold" type="text" id="simValor" readonly placeholder="—">
                    </div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="button" id="btnSimular"><i class="fas fa-calculator me-2"></i>Calcular</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($_isAdmin): ?>
    <!-- CRUD tabela — somente admin/suporte -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3">Adicionar / editar faixa</h5>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Peso até (kg) <span class="text-danger">*</span></label>
                    <input class="form-control" type="number" step="0.5" min="0.5" id="novoPeso" placeholder="Ex: 1.5">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Valor (USD) <span class="text-danger">*</span></label>
                    <input class="form-control" type="number" step="0.01" min="0.01" id="novoValor" placeholder="Ex: 19.94">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-success w-100" type="button" id="btnSalvarFaixa"><i class="fas fa-save me-2"></i>Salvar faixa</button>
                </div>
            </div>
            <div id="msgFaixa" class="mt-2"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="tabelaPesos">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Peso até (kg)</th>
                            <th>Valor (USD)</th>
                            <?php if ($_isAdmin): ?><th class="pe-3 text-end">Ações</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tabela)): ?>
                        <tr><td colspan="<?= $_isAdmin ? 3 : 2 ?>" class="text-center text-muted py-4">Tabela vazia.</td></tr>
                        <?php else: foreach ($tabela as $row): ?>
                        <tr data-id="<?= (int)$row['id'] ?>">
                            <td class="ps-3"><?= number_format((float)$row['peso_ate_kg'],3,',','.') ?> kg</td>
                            <td>US$ <?= number_format((float)$row['valor_usd'],2,',','.') ?></td>
                            <?php if ($_isAdmin): ?>
                            <td class="pe-3 text-end">
                                <button type="button" class="btn btn-xs btn-outline-danger btn-excluir" data-id="<?= (int)$row['id'] ?>" style="font-size:.75rem;padding:2px 8px">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const TABELA_JS = <?= json_encode(array_map(fn($r)=>['id'=>(int)$r['id'],'peso'=>(float)$r['peso_ate_kg'],'valor'=>(float)$r['valor_usd']],$tabela)) ?>;

document.getElementById('btnSimular').addEventListener('click', async () => {
    const peso = parseFloat(document.getElementById('simPeso').value) || 0;
    if (peso <= 0) return;
    const r = await fetch('/admin/redirecionamento/tabela-pesos/calcular?peso='+peso);
    const j = await r.json();
    if (j.ok) {
        document.getElementById('simFaixa').value = 'até ' + j.faixa.toFixed(1) + ' kg';
        document.getElementById('simValor').value = j.valor_usd.toFixed(2);
    } else {
        document.getElementById('simFaixa').value = 'Fora da tabela';
        document.getElementById('simValor').value = '';
    }
});

<?php if ($_isAdmin): ?>
document.getElementById('btnSalvarFaixa').addEventListener('click', async () => {
    const peso = document.getElementById('novoPeso').value;
    const valor = document.getElementById('novoValor').value;
    if (!peso || !valor) { document.getElementById('msgFaixa').innerHTML='<div class="alert alert-danger py-1 small">Preencha peso e valor.</div>'; return; }
    const fd = new FormData(); fd.append('peso_ate_kg',peso); fd.append('valor_usd',valor);
    const r = await fetch('/admin/redirecionamento/tabela-pesos/salvar',{method:'POST',body:fd});
    const j = await r.json();
    document.getElementById('msgFaixa').innerHTML = j.ok ? '<div class="alert alert-success py-1 small">Salvo! Recarregue para ver.</div>' : '<div class="alert alert-danger py-1 small">'+(j.msg||'Erro')+'</div>';
    if (j.ok) setTimeout(()=>location.reload(),1000);
});

document.querySelectorAll('.btn-excluir').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Excluir esta faixa?')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/redirecionamento/tabela-pesos/excluir',{method:'POST',body:fd});
        const j = await r.json();
        if (j.ok) btn.closest('tr').remove();
    });
});
<?php endif; ?>
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
