<?php $title = 'Configurações Carnê - Admin'; ?>
<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-cog me-2"></i> Configurações — Carnê Braziliana</h1>
        <a href="/admin/carnes" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Voltar</a>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <form method="POST" action="/admin/carnes/configuracoes">
        <div class="row">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0">Geral</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="carne_ativo" value="0">
                                <input type="checkbox" name="carne_ativo" value="1" class="form-check-input" <?= ($config['carne_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Exibir Carnê Braziliana no Checkout</label>
                            </div>
                            <small class="text-muted">Desativar não afeta carnês já existentes.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Máximo de Parcelas</label>
                            <input type="number" name="carne_max_parcelas" class="form-control" value="<?= htmlspecialchars($config['carne_max_parcelas'] ?? '12') ?>" min="1" max="24">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dias para Vencimento</label>
                            <input type="number" name="carne_dias_vencimento" class="form-control" value="<?= htmlspecialchars($config['carne_dias_vencimento'] ?? '7') ?>" min="1" max="30">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0">Notificações</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="carne_email_ativo" value="0">
                                <input type="checkbox" name="carne_email_ativo" value="1" class="form-check-input" <?= ($config['carne_email_ativo'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Notificações por E-mail</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Eventos de E-mail</label>
                            <input type="text" name="carne_eventos_email" class="form-control" value="<?= htmlspecialchars($config['carne_eventos_email'] ?? '') ?>" placeholder="carne_criado,parcela_paga,...">
                            <small class="text-muted">Separados por vírgula</small>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="carne_webhook_ativo" value="0">
                                <input type="checkbox" name="carne_webhook_ativo" value="1" class="form-check-input" <?= ($config['carne_webhook_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Webhook Ativo</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" name="carne_webhook_url" class="form-control" value="<?= htmlspecialchars($config['carne_webhook_url'] ?? '') ?>" placeholder="https://...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Eventos de Webhook</label>
                            <input type="text" name="carne_eventos_webhook" class="form-control" value="<?= htmlspecialchars($config['carne_eventos_webhook'] ?? '') ?>" placeholder="carne_criado,parcela_paga,...">
                            <small class="text-muted">Separados por vírgula</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar Configurações</button>
        </div>
    </form>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
