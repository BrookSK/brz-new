<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-cog me-2"></i><?= __('admin.received_packages.settings_title', 'Configurações - Pacotes Recebidos') ?>
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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><?= __('admin.received_packages.storage_insurance_fees', 'Taxas de Armazenamento e Seguro') ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="/admin/pacotes-recebidos/configuracoes/salvar">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.days_until_storage_fee', 'Dias até início da multa de armazenamento') ?></label>
                        <input type="number" name="pacote_dias_multa_inicio" class="form-control" min="1"
                               value="<?= htmlspecialchars($configs['pacote_dias_multa_inicio'] ?? '15') ?>">
                        <small class="form-text text-muted"><?= __('admin.received_packages.days_until_storage_fee_help', 'Após este número de dias, começa a cobrança por dia.') ?></small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.fee_per_day_usd', 'Valor da multa por dia (USD)') ?></label>
                        <div class="input-group">
                            <span class="input-group-text">US$</span>
                            <input type="number" name="pacote_multa_valor_dia_usd" class="form-control" step="0.01" min="0"
                                   value="<?= htmlspecialchars($configs['pacote_multa_valor_dia_usd'] ?? '2.00') ?>">
                        </div>
                        <small class="form-text text-muted"><?= __('admin.received_packages.fee_per_day_help', 'Cobrado por dia de atraso após o prazo inicial.') ?></small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.days_auto_discard', 'Dias para descarte automático') ?></label>
                        <input type="number" name="pacote_dias_descarte" class="form-control" min="1"
                               value="<?= htmlspecialchars($configs['pacote_dias_descarte'] ?? '42') ?>">
                        <small class="form-text text-muted"><?= __('admin.received_packages.days_auto_discard_help', 'Após este período, o pacote é descartado automaticamente.') ?></small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.email_reminder_interval', 'Intervalo de lembrete por e-mail (dias)') ?></label>
                        <input type="number" name="pacote_lembrete_intervalo_dias" class="form-control" min="1"
                               value="<?= htmlspecialchars($configs['pacote_lembrete_intervalo_dias'] ?? '5') ?>">
                        <small class="form-text text-muted"><?= __('admin.received_packages.email_reminder_interval_help', 'A cada X dias após a multa, enviar e-mail de lembrete.') ?></small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold"><?= __('admin.received_packages.insurance_fee_pct', 'Taxa de Seguro (%)') ?></label>
                        <div class="input-group">
                            <input type="number" name="pacote_taxa_seguro_percentual" class="form-control" step="0.01" min="0"
                                   value="<?= htmlspecialchars($configs['pacote_taxa_seguro_percentual'] ?? '3.00') ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="form-text text-muted"><?= __('admin.received_packages.insurance_fee_help', 'Percentual sobre o valor declarado (declaration_value) cobrado como seguro.') ?></small>
                    </div>
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?= __('admin.received_packages.save_settings', 'Salvar Configurações') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Informações -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><?= __('admin.received_packages.how_it_works', 'Como funciona') ?></h5>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li><strong><?= __('admin.received_packages.info_storage_fee_label', 'Multa de armazenamento:') ?></strong> <?= __('admin.received_packages.info_storage_fee_desc', 'Após o prazo configurado, é cobrado o valor por dia no checkout.') ?></li>
                <li><strong><?= __('admin.received_packages.info_discard_label', 'Descarte:') ?></strong> <?= __('admin.received_packages.info_discard_desc', 'Pacotes que excederem o prazo máximo são marcados automaticamente como "descartado" pelo cron diário.') ?></li>
                <li><strong><?= __('admin.received_packages.info_reminder_label', 'Lembrete:') ?></strong> <?= __('admin.received_packages.info_reminder_desc', 'E-mails são enviados periodicamente informando o cliente sobre a taxa acumulada.') ?></li>
                <li><strong><?= __('admin.received_packages.info_insurance_label', 'Taxa de Seguro:') ?></strong> <?= __('admin.received_packages.info_insurance_desc', 'Calculada como percentual do valor declarado pelo cliente no carrinho.') ?></li>
            </ul>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = __('admin.received_packages.settings_short_title', 'Configurações Pacotes') . ' - ' . __('admin.received_packages.admin_suffix', 'Admin');
include __DIR__ . '/../../layouts/admin.php';
?>
