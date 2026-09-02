<?php ob_start(); ?>
<?php
include_once __DIR__ . '/../../partials/admin_sidebar.php';
$modoAtual = $modo_atual ?? 'subtotal_taxa';
$historico = $historico ?? [];
$successMsg = $success_msg ?? '';
?>
<!DOCTYPE html>
<html lang="<?= \App\Core\I18n::getLocaleHtml() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('admin.wallet_config.page_title', 'Configuração da Carteira - Braziliana Admin'), ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php renderAdminSidebarStyles(); ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php renderAdminSidebar('configuracoes'); ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                    <h1 class="h3"><i class="fas fa-wallet me-2"></i> <?= __('admin.wallet_config.title', 'Configuração da Carteira') ?></h1>
                    <a href="/admin/configuracoes" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> <?= __('admin.wallet_config.back', 'Voltar') ?></a>
                </div>

                <?php if ($successMsg): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($successMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Configuração Atual -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i> <?= __('admin.wallet_config.coverage_mode', 'Modo de Cobertura da Carteira') ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            <?= __('admin.wallet_config.coverage_intro', 'Define quais componentes do pedido a carteira pode cobrir quando o cliente seleciona "Crédito da Carteira" no checkout.') ?>
                        </p>

                        <form method="POST" action="/admin/carteira-config/salvar" id="form-config">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card h-100 <?= $modoAtual === 'subtotal_taxa' ? 'border-primary' : 'border-light' ?>" style="cursor:pointer;" onclick="document.getElementById('modo_subtotal_taxa').checked=true; highlightCard(this);">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="modo" id="modo_subtotal_taxa" value="subtotal_taxa" <?= $modoAtual === 'subtotal_taxa' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold" for="modo_subtotal_taxa">
                                                    <?= __('admin.wallet_config.mode_subtotal_fee', 'Subtotal + Taxa de Serviço') ?>
                                                </label>
                                            </div>
                                            <hr>
                                            <p class="small text-muted mb-2"><?= __('admin.wallet_config.wallet_covers', 'A carteira cobre:') ?></p>
                                            <ul class="small mb-0">
                                                <li><i class="fas fa-check text-success me-1"></i> <?= __('admin.wallet_config.products_subtotal', 'Subtotal de produtos') ?></li>
                                                <li><i class="fas fa-check text-success me-1"></i> <?= __('admin.wallet_config.service_fee', 'Taxa de serviço') ?></li>
                                                <li><i class="fas fa-times text-danger me-1"></i> <?= __('admin.wallet_config.taxes_via_gateway', 'Impostos (cobrados via gateway)') ?></li>
                                                <li><i class="fas fa-times text-danger me-1"></i> <?= __('admin.wallet_config.local_tax_via_gateway', 'Imposto local (cobrado via gateway)') ?></li>
                                            </ul>
                                            <div class="mt-2">
                                                <span class="badge bg-info"><?= __('admin.wallet_config.current_default_mode', 'Modo atual padrão') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 <?= $modoAtual === 'subtotal_taxa_impostos' ? 'border-primary' : 'border-light' ?>" style="cursor:pointer;" onclick="document.getElementById('modo_subtotal_taxa_impostos').checked=true; highlightCard(this);">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="modo" id="modo_subtotal_taxa_impostos" value="subtotal_taxa_impostos" <?= $modoAtual === 'subtotal_taxa_impostos' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold" for="modo_subtotal_taxa_impostos">
                                                    <?= __('admin.wallet_config.mode_subtotal_fee_taxes', 'Subtotal + Taxa + Impostos') ?>
                                                </label>
                                            </div>
                                            <hr>
                                            <p class="small text-muted mb-2"><?= __('admin.wallet_config.wallet_covers', 'A carteira cobre:') ?></p>
                                            <ul class="small mb-0">
                                                <li><i class="fas fa-check text-success me-1"></i> <?= __('admin.wallet_config.products_subtotal', 'Subtotal de produtos') ?></li>
                                                <li><i class="fas fa-check text-success me-1"></i> <?= __('admin.wallet_config.service_fee', 'Taxa de serviço') ?></li>
                                                <li><i class="fas fa-check text-success me-1"></i> <?= __('admin.wallet_config.taxes', 'Impostos') ?></li>
                                                <li><i class="fas fa-check text-success me-1"></i> <?= __('admin.wallet_config.local_tax', 'Imposto local') ?></li>
                                            </ul>
                                            <div class="mt-2">
                                                <span class="badge bg-warning text-dark"><?= __('admin.wallet_config.wallet_covers_all', 'Carteira cobre tudo') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="motivo" class="form-label"><?= __('admin.wallet_config.change_reason', 'Motivo da alteração') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="motivo" name="motivo" placeholder="<?= htmlspecialchars(__('admin.wallet_config.change_reason_placeholder', 'Ex: Decisão comercial para facilitar checkout'), ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> <?= __('admin.wallet_config.save', 'Salvar Configuração') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Histórico de Alterações -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> <?= __('admin.wallet_config.changes_history', 'Histórico de Alterações') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historico)): ?>
                            <p class="text-muted mb-0"><?= __('admin.wallet_config.no_changes', 'Nenhuma alteração registrada ainda.') ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th><?= __('admin.wallet_config.col_date', 'Data') ?></th>
                                            <th><?= __('admin.wallet_config.col_from', 'De') ?></th>
                                            <th><?= __('admin.wallet_config.col_to', 'Para') ?></th>
                                            <th><?= __('admin.wallet_config.col_changed_by', 'Alterado por') ?></th>
                                            <th><?= __('admin.wallet_config.col_reason', 'Motivo') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historico as $h): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                                            <td>
                                                <?php
                                                $labels = ['subtotal_taxa' => __('admin.wallet_config.label_subtotal_fee', 'Subtotal + Taxa'), 'subtotal_taxa_impostos' => __('admin.wallet_config.label_subtotal_fee_taxes', 'Subtotal + Taxa + Impostos')];
                                                echo '<span class="badge bg-secondary">' . htmlspecialchars($labels[$h['modo_anterior']] ?? $h['modo_anterior']) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?= '<span class="badge bg-primary">' . htmlspecialchars($labels[$h['modo_novo']] ?? $h['modo_novo']) . '</span>' ?>
                                            </td>
                                            <td><?= htmlspecialchars($h['alterado_por_nome'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($h['motivo'] ?? '-') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function highlightCard(el) {
        document.querySelectorAll('#form-config .card').forEach(c => {
            c.classList.remove('border-primary');
            c.classList.add('border-light');
        });
        el.classList.remove('border-light');
        el.classList.add('border-primary');
    }
    </script>
</body>
</html>
<?php $content = ob_get_clean(); echo $content; ?>
