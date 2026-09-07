<?php
ob_start();
$edicao = !empty($cupom);
$val = function($k, $default = '') use ($cupom) {
    return htmlspecialchars((string) ($cupom[$k] ?? $default), ENT_QUOTES, 'UTF-8');
};
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-ticket-alt me-2"></i><?= $edicao ? 'Editar Cupom' : 'Novo Cupom' ?>
        </h1>
        <a href="/admin/cupons" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Voltar</a>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="/admin/cupons/salvar">
                <?php if ($edicao): ?>
                    <input type="hidden" name="id" value="<?= (int) $cupom['id'] ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Código do cupom *</label>
                        <input type="text" name="codigo" class="form-control text-uppercase" value="<?= $val('codigo') ?>" placeholder="EX: BEMVINDO10" required>
                        <div class="form-text">Será usado pelo cliente no checkout.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Descrição (interna)</label>
                        <input type="text" name="descricao" class="form-control" value="<?= $val('descricao') ?>" placeholder="Ex: Cupom de boas-vindas">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo de desconto *</label>
                        <select name="tipo" id="tipoCupom" class="form-select">
                            <option value="percentual" <?= (($cupom['tipo'] ?? 'percentual') === 'percentual') ? 'selected' : '' ?>>Percentual (%)</option>
                            <option value="fixo" <?= (($cupom['tipo'] ?? '') === 'fixo') ? 'selected' : '' ?>>Valor fixo (US$)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor do desconto *</label>
                        <div class="input-group">
                            <span class="input-group-text" id="prefixoValor"><?= (($cupom['tipo'] ?? 'percentual') === 'fixo') ? 'US$' : '%' ?></span>
                            <input type="number" step="0.01" min="0" name="valor" class="form-control" value="<?= $val('valor') ?>" required>
                        </div>
                        <div class="form-text">O desconto incide somente sobre o valor dos produtos.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor mínimo de produtos (US$)</label>
                        <input type="number" step="0.01" min="0" name="valor_minimo_pedido" class="form-control" value="<?= $val('valor_minimo_pedido') ?>" placeholder="Opcional">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Válido a partir de</label>
                        <input type="date" name="data_inicio" class="form-control" value="<?= $val('data_inicio') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expira em</label>
                        <input type="date" name="data_expiracao" class="form-control" value="<?= $val('data_expiracao') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Limite total de usos</label>
                        <input type="number" min="0" name="limite_uso_total" class="form-control" value="<?= $val('limite_uso_total') ?>" placeholder="Ilimitado">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Limite por usuário</label>
                        <input type="number" min="0" name="limite_uso_por_usuario" class="form-control" value="<?= $val('limite_uso_por_usuario') ?>" placeholder="Ilimitado">
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" <?= (!$edicao || (int)($cupom['ativo'] ?? 0) === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Cupom ativo</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar</button>
                    <a href="/admin/cupons" class="btn btn-outline-secondary ms-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var tipo = document.getElementById('tipoCupom');
    var prefixo = document.getElementById('prefixoValor');
    if (tipo && prefixo) {
        tipo.addEventListener('change', function() {
            prefixo.textContent = (this.value === 'fixo') ? 'US$' : '%';
        });
    }
})();
</script>
<?php
$content = ob_get_clean();
$title = ($edicao ? 'Editar' : 'Novo') . ' Cupom - Admin';
include __DIR__ . '/../../layouts/admin.php';
?>
