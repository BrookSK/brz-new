<?php
$sidebarActive = 'redirecionamento-tabela-pesos';
$title = __('admin.redirect.weight_price_table', 'Tabela de Pesos e Preços');
$tabela = is_array($tabela ?? null) ? $tabela : [];
$_perfilAtual = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
$_isAdmin = in_array($_perfilAtual, ['admin', 'suporte'], true);
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><?= __('admin.redirect.weight_price_table', 'Tabela de Pesos e Preços') ?></h1>
            <div class="text-muted small"><?= __('admin.redirect.controls_all_redirect_charging', 'Controla toda a cobrança do redirecionamento') ?></div>
        </div>
    </div>

    <!-- Simulador -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="fas fa-calculator me-2 text-primary"></i><?= __('admin.redirect.simulator', 'Simulador') ?></h5>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.redirect.total_weight_kg', 'Peso total (kg)') ?></label>
                    <input class="form-control" type="number" step="0.001" min="0.001" id="simPeso" placeholder="<?= htmlspecialchars(__('admin.redirect.eg_2_5', 'Ex: 2.5'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.redirect.range_up_to_kg', 'Faixa (até kg)') ?></label>
                    <input class="form-control" type="text" id="simFaixa" readonly placeholder="—">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.redirect.value_usd', 'Valor (USD)') ?></label>
                    <div class="input-group">
                        <span class="input-group-text">US$</span>
                        <input class="form-control fw-bold" type="text" id="simValor" readonly placeholder="—">
                    </div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="button" id="btnSimular"><i class="fas fa-calculator me-2"></i><?= __('admin.redirect.calculate', 'Calcular') ?></button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($_isAdmin): ?>
    <!-- CRUD tabela — somente admin/suporte -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3"><?= __('admin.redirect.add_edit_range', 'Adicionar / editar faixa') ?></h5>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label"><?= __('admin.redirect.weight_up_to_kg', 'Peso até (kg)') ?> <span class="text-danger">*</span></label>
                    <input class="form-control" type="number" step="0.5" min="0.5" id="novoPeso" placeholder="<?= htmlspecialchars(__('admin.redirect.eg_1_5', 'Ex: 1.5'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= __('admin.redirect.value_usd', 'Valor (USD)') ?> <span class="text-danger">*</span></label>
                    <input class="form-control" type="number" step="0.01" min="0.01" id="novoValor" placeholder="<?= htmlspecialchars(__('admin.redirect.eg_19_94', 'Ex: 19.94'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-success w-100" type="button" id="btnSalvarFaixa"><i class="fas fa-save me-2"></i><?= __('admin.redirect.save_range', 'Salvar faixa') ?></button>
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
                            <th class="ps-3"><?= __('admin.redirect.weight_up_to_kg', 'Peso até (kg)') ?></th>
                            <th><?= __('admin.redirect.value_usd', 'Valor (USD)') ?></th>
                            <?php if ($_isAdmin): ?><th class="pe-3 text-end"><?= __('admin.redirect.actions', 'Ações') ?></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tabela)): ?>
                        <tr><td colspan="<?= $_isAdmin ? 3 : 2 ?>" class="text-center text-muted py-4"><?= __('admin.redirect.empty_table', 'Tabela vazia.') ?></td></tr>
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

    <?php if ($_isAdmin): ?>
    <!-- Configuração do provedor de etiqueta -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="fas fa-cog me-2 text-primary"></i><?= __('admin.redirect.redirect_settings', 'Configurações do Redirecionamento') ?></h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= __('admin.redirect.label_provider', 'Provedor de etiqueta') ?></label>
                    <select class="form-select" id="cfgProvedorEtiqueta">
                        <option value="wexpress" <?= ($provedorEtiqueta ?? 'wexpress') === 'wexpress' ? 'selected' : '' ?>>W Express</option>
                        <option value="correios" <?= ($provedorEtiqueta ?? 'wexpress') === 'correios' ? 'selected' : '' ?>>Correios (<?= __('admin.redirect.pre_posting', 'Pré-Postagem') ?>)</option>
                        <option value="correios_wordpress" <?= ($provedorEtiqueta ?? 'wexpress') === 'correios_wordpress' ? 'selected' : '' ?>>Correios (WordPress/PACKET)</option>
                    </select>
                    <div class="form-text"><?= __('admin.redirect.api_used_when_redirector_generates_label', 'API usada quando o redirecionador gera a etiqueta.') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= __('admin.redirect.collection_notification_emails', 'Emails de notificação de coletas') ?></label>
                    <input type="text" class="form-control" id="cfgEmailsColeta" value="<?= htmlspecialchars($emailsColeta ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="email1@exemplo.com, email2@exemplo.com">
                    <div class="form-text"><?= __('admin.redirect.comma_separated_collection_alert', 'Separados por vírgula. Recebem aviso quando um redirecionador agenda coleta.') ?></div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" id="btnSalvarProvedor"><?= __('admin.redirect.save', 'Salvar') ?></button>
                </div>
                <div class="col-12" id="msgProvedor"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const TABELA_JS = <?= json_encode(array_map(fn($r)=>['id'=>(int)$r['id'],'peso'=>(float)$r['peso_ate_kg'],'valor'=>(float)$r['valor_usd']],$tabela)) ?>;

document.getElementById('btnSimular').addEventListener('click', async () => {
    const peso = parseFloat(document.getElementById('simPeso').value) || 0;
    if (peso <= 0) return;
    const r = await fetch('/admin/redirecionamento/tabela-pesos/calcular?peso='+peso);
    const j = await r.json();
    if (j.ok) {
        document.getElementById('simFaixa').value = '<?= htmlspecialchars(__('admin.redirect.up_to', 'até'), ENT_QUOTES, 'UTF-8') ?> ' + j.faixa.toFixed(1) + ' kg';
        document.getElementById('simValor').value = j.valor_usd.toFixed(2);
    } else {
        document.getElementById('simFaixa').value = '<?= htmlspecialchars(__('admin.redirect.out_of_table', 'Fora da tabela'), ENT_QUOTES, 'UTF-8') ?>';
        document.getElementById('simValor').value = '';
    }
});

<?php if ($_isAdmin): ?>
document.getElementById('btnSalvarProvedor')?.addEventListener('click', async () => {
    const provedor = document.getElementById('cfgProvedorEtiqueta').value;
    const emails = document.getElementById('cfgEmailsColeta').value;

    // Salvar provedor
    const fd1 = new FormData();
    fd1.append('chave', 'redirecionamento_provedor_etiqueta');
    fd1.append('valor', provedor);
    await fetch('/admin/redirecionamento/configuracao/salvar', {method:'POST', body:fd1});

    // Salvar emails
    const fd2 = new FormData();
    fd2.append('chave', 'redirecionamento_emails_coleta');
    fd2.append('valor', emails);
    const r = await fetch('/admin/redirecionamento/configuracao/salvar', {method:'POST', body:fd2});
    const j = await r.json();

    document.getElementById('msgProvedor').innerHTML = j.ok
        ? '<div class="alert alert-success py-1 small"><?= htmlspecialchars(__('admin.redirect.settings_saved_excl', 'Configurações salvas!'), ENT_QUOTES, 'UTF-8') ?></div>'
        : '<div class="alert alert-danger py-1 small">'+(j.msg||'<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>')+'</div>';
});

document.getElementById('btnSalvarFaixa').addEventListener('click', async () => {
    const peso = document.getElementById('novoPeso').value;
    const valor = document.getElementById('novoValor').value;
    if (!peso || !valor) { document.getElementById('msgFaixa').innerHTML='<div class="alert alert-danger py-1 small"><?= htmlspecialchars(__('admin.redirect.fill_weight_and_value', 'Preencha peso e valor.'), ENT_QUOTES, 'UTF-8') ?></div>'; return; }
    const fd = new FormData(); fd.append('peso_ate_kg',peso); fd.append('valor_usd',valor);
    const r = await fetch('/admin/redirecionamento/tabela-pesos/salvar',{method:'POST',body:fd});
    const j = await r.json();
    document.getElementById('msgFaixa').innerHTML = j.ok ? '<div class="alert alert-success py-1 small"><?= htmlspecialchars(__('admin.redirect.saved_reload_to_see', 'Salvo! Recarregue para ver.'), ENT_QUOTES, 'UTF-8') ?></div>' : '<div class="alert alert-danger py-1 small">'+(j.msg||'<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>')+'</div>';
    if (j.ok) setTimeout(()=>location.reload(),1000);
});

document.querySelectorAll('.btn-excluir').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('<?= htmlspecialchars(__('admin.redirect.confirm_delete_range', 'Excluir esta faixa?'), ENT_QUOTES, 'UTF-8') ?>')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/redirecionamento/tabela-pesos/excluir',{method:'POST',body:fd});
        const j = await r.json();
        if (j.ok) btn.closest('tr').remove();
    });
});
<?php endif; ?>
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
