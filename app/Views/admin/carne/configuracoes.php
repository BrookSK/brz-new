<?php $title = __('admin.installment.settings_title', 'Configurações Carnê - Admin'); ?>
<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title"><?= __('admin.installment.settings_heading', 'Configurações — Carnê Braziliana') ?></h1>
        <a href="/admin/carnes" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> <?= __('admin.installment.back', 'Voltar') ?></a>
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
                    <div class="card-header"><h6 class="mb-0"><?= __('admin.installment.general', 'Geral') ?></h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="carne_ativo" value="0">
                                <input type="checkbox" name="carne_ativo" value="1" class="form-check-input" <?= ($config['carne_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= __('admin.installment.publish_all_customers', 'Publicar para todos os clientes') ?></label>
                            </div>
                            <small class="text-muted"><?= __('admin.installment.publish_all_hint', 'Quando ativado, todos os clientes veem o Carnê no checkout. Desativar não afeta carnês já existentes.') ?></small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="carne_somente_admin" value="0">
                                <input type="checkbox" name="carne_somente_admin" value="1" class="form-check-input" <?= ($config['carne_somente_admin'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= __('admin.installment.test_mode', 'Modo Teste (somente admin)') ?></label>
                            </div>
                            <small class="text-muted"><?= __('admin.installment.test_mode_hint', 'Quando ativado, só admins veem o Carnê no checkout (para testar). Tem prioridade sobre o toggle acima.') ?></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('admin.installment.max_installments', 'Máximo de Parcelas') ?></label>
                            <input type="number" name="carne_max_parcelas" class="form-control" value="<?= htmlspecialchars($config['carne_max_parcelas'] ?? '12') ?>" min="2" max="24">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('admin.installment.min_order_value', 'Valor Mínimo do Pedido (R$)') ?></label>
                            <input type="number" name="carne_valor_minimo" class="form-control" value="<?= htmlspecialchars($config['carne_valor_minimo'] ?? '0') ?>" min="0" step="0.01" placeholder="<?= htmlspecialchars(__('admin.installment.min_value_placeholder', '0 = sem mínimo'), ENT_QUOTES, 'UTF-8') ?>">
                            <small class="text-muted"><?= __('admin.installment.min_order_hint', 'Pedidos abaixo deste valor não podem usar o Carnê. Use 0 para não ter mínimo.') ?></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('admin.installment.days_to_due', 'Dias para Vencimento') ?></label>
                            <input type="number" name="carne_dias_vencimento" class="form-control" value="<?= htmlspecialchars($config['carne_dias_vencimento'] ?? '7') ?>" min="1" max="30">
                        </div>
                        <hr>
                        <h6 class="text-muted small mb-2"><?= __('admin.installment.cancellation_abandonment', 'Cancelamento por Abandono') ?></h6>
                        <div class="mb-3">
                            <label class="form-label"><?= __('admin.installment.months_overdue_notice', 'Meses de atraso para aviso') ?></label>
                            <input type="number" name="carne_meses_atraso_cancelamento" class="form-control" value="<?= htmlspecialchars($config['carne_meses_atraso_cancelamento'] ?? '2') ?>" min="1" max="12">
                            <small class="text-muted"><?= __('admin.installment.months_overdue_hint', 'Após X meses sem pagar, envia aviso de cancelamento.') ?></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('admin.installment.days_after_notice', 'Dias de prazo após aviso') ?></label>
                            <input type="number" name="carne_dias_aviso_cancelamento" class="form-control" value="<?= htmlspecialchars($config['carne_dias_aviso_cancelamento'] ?? '7') ?>" min="1" max="30">
                            <small class="text-muted"><?= __('admin.installment.days_after_notice_hint', 'Dias que o cliente tem para regularizar após o aviso, antes do cancelamento.') ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0"><?= __('admin.installment.notifications', 'Notificações') ?></h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="carne_email_ativo" value="0">
                                <input type="checkbox" name="carne_email_ativo" value="1" class="form-check-input" <?= ($config['carne_email_ativo'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= __('admin.installment.email_notifications', 'Notificações por E-mail') ?></label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('admin.installment.email_events', 'Eventos de E-mail') ?></label>
                            <input type="text" name="carne_eventos_email" class="form-control" value="<?= htmlspecialchars($config['carne_eventos_email'] ?? '') ?>" placeholder="carne_criado,parcela_paga,...">
                            <small class="text-muted"><?= __('admin.installment.comma_separated', 'Separados por vírgula') ?></small>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="carne_webhook_ativo" value="0">
                                <input type="checkbox" name="carne_webhook_ativo" value="1" class="form-check-input" <?= ($config['carne_webhook_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= __('admin.installment.webhook_active', 'Webhook Ativo') ?></label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('admin.installment.webhook_url', 'Webhook URL') ?></label>
                            <input type="url" name="carne_webhook_url" class="form-control" value="<?= htmlspecialchars($config['carne_webhook_url'] ?? '') ?>" placeholder="https://...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('admin.installment.webhook_events', 'Eventos de Webhook') ?></label>
                            <input type="text" name="carne_eventos_webhook" class="form-control" value="<?= htmlspecialchars($config['carne_eventos_webhook'] ?? '') ?>" placeholder="carne_criado,parcela_paga,...">
                            <small class="text-muted"><?= __('admin.installment.comma_separated', 'Separados por vírgula') ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cron -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0"><i class="fas fa-clock me-1"></i> <?= __('admin.installment.cron_automation', 'Cron / Automação') ?></h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?= __('admin.installment.cron_security_token', 'Token de Segurança do Cron') ?></label>
                                    <div class="input-group">
                                        <input type="text" name="cron_secret" id="cron_secret" class="form-control" value="<?= htmlspecialchars($config['cron_secret'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('admin.installment.cron_token_placeholder', 'Deixe vazio para desabilitar proteção'), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('cron_secret').value = Array.from(crypto.getRandomValues(new Uint8Array(32))).map(b=>b.toString(16).padStart(2,'0')).join('')">
                                            <i class="fas fa-random"></i> <?= __('admin.installment.generate', 'Gerar') ?>
                                        </button>
                                    </div>
                                    <small class="text-muted"><?= __('admin.installment.cron_token_hint', 'Token usado para proteger o endpoint do cron contra acesso não autorizado.') ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?= __('admin.installment.cron_url', 'URL do Cron') ?></label>
                                    <?php
                                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'seudominio.com.br');
                                    $cronToken = htmlspecialchars($config['cron_secret'] ?? 'SEU_TOKEN');
                                    ?>
                                    <input type="text" class="form-control bg-light" readonly value="<?= $baseUrl ?>/cron/carne/processar?token=<?= $cronToken ?>" onclick="this.select()">
                                    <small class="text-muted"><?= __('admin.installment.cron_url_hint', 'Cole esta URL no painel de cron da hospedagem. Intervalo recomendado: 5 minutos.') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> <?= __('admin.installment.save_settings', 'Salvar Configurações') ?></button>
        </div>
    </form>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
