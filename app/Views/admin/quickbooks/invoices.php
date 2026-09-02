<?php require_once __DIR__.'/../../partials/admin_sidebar.php'; renderAdminSidebarStyles(); ?>
<!DOCTYPE html>
<html lang="<?= \App\Core\I18n::getLocaleHtml() ?>">
<head><meta charset="UTF-8"><title><?= __('admin.quickbooks.invoices_title_short','Invoices QB') ?></title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body>
<div class="container-fluid"><div class="row">
<?php renderAdminSidebar('quickbooks'); ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title"><?= __('admin.quickbooks.invoices_page_title','Invoices QuickBooks') ?></h1>
        <p class="page-subtitle"><?= __('admin.quickbooks.invoices_page_subtitle','Faturas sincronizadas com QuickBooks Online') ?></p>
    </div>
    <a href="/admin/quickbooks" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><?= __('admin.quickbooks.back','Voltar') ?></a>
</div>

<?php if (!empty($erro)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?= __('admin.quickbooks.date_start','Data Início') ?></label>
                <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($filtros['data_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= __('admin.quickbooks.date_end','Data Fim') ?></label>
                <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($filtros['data_fim'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= __('admin.quickbooks.status_label','Status') ?></label>
                <select name="status" class="form-select">
                    <option value=""><?= __('admin.quickbooks.all_m','Todos') ?></option>
                    <option value="pago" <?= ($filtros['status'] ?? '') === 'pago' ? 'selected' : '' ?>><?= __('admin.quickbooks.paid_plural','Pagos') ?></option>
                    <option value="aberto" <?= ($filtros['status'] ?? '') === 'aberto' ? 'selected' : '' ?>><?= __('admin.quickbooks.outstanding','Em Aberto') ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i><?= __('admin.quickbooks.filter','Filtrar') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Invoices -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= __('admin.quickbooks.invoices_count','Invoices ({n})', ['n' => count($invoices)]) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>QB ID</th>
                    <th><?= __('admin.quickbooks.th_doc_number','Doc Nº') ?></th>
                    <th><?= __('admin.quickbooks.th_customer','Cliente') ?></th>
                    <th><?= __('admin.quickbooks.th_date','Data') ?></th>
                    <th class="text-end"><?= __('admin.quickbooks.th_total','Total') ?></th>
                    <th class="text-end"><?= __('admin.quickbooks.th_balance','Saldo') ?></th>
                    <th><?= __('admin.quickbooks.th_status','Status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= __('admin.quickbooks.no_invoices','Nenhuma invoice encontrada.') ?></td></tr>
                <?php else: ?>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td class="font-monospace small"><?= htmlspecialchars($inv['Id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($inv['DocNumber'] ?? '') ?></td>
                    <td><?= htmlspecialchars($inv['CustomerRef']['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($inv['TxnDate'] ?? '') ?></td>
                    <td class="text-end"><?= number_format((float)($inv['TotalAmt'] ?? 0), 2, ',', '.') ?></td>
                    <td class="text-end"><?= number_format((float)($inv['Balance'] ?? 0), 2, ',', '.') ?></td>
                    <td>
                        <?php if ((float)($inv['Balance'] ?? 0) == 0): ?>
                            <span class="badge bg-success"><?= __('admin.quickbooks.paid','Pago') ?></span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><?= __('admin.quickbooks.outstanding','Em Aberto') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
