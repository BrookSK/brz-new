<?php
$status = strtolower(trim((string) ($_GET['status'] ?? 'open')));
if (!in_array($status, ['open', 'closed', 'all'], true)) {
    $status = 'open';
}
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$clienteTipo = strtolower(trim((string) ($_GET['cliente_tipo'] ?? '')));
if (!in_array($clienteTipo, ['', 'admin', 'suporte', 'vendedor'], true)) {
    $clienteTipo = '';
}
$atendenteId = (int) ($_GET['atendente_id'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="page-title"><?= __('admin.tickets.title', 'Tickets') ?></h1>
        <p class="page-subtitle"><?= __('admin.tickets.subtitle', 'Atendimento e comunicação pelo site') ?></p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="/admin/tickets" id="ticketsFilterForm">
            <div class="row g-2">
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>><?= __('admin.tickets.filter.open', 'Abertos') ?></option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>><?= __('admin.tickets.filter.closed', 'Fechados') ?></option>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>><?= __('admin.tickets.filter.all', 'Todos') ?></option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" name="cliente_tipo" onchange="this.form.submit()">
                        <option value="" <?= $clienteTipo === '' ? 'selected' : '' ?>><?= __('admin.tickets.filter.type_all', 'Tipo: Todos') ?></option>
                        <option value="admin" <?= $clienteTipo === 'admin' ? 'selected' : '' ?>><?= __('admin.tickets.filter.type_admin', 'Admin') ?></option>
                        <option value="suporte" <?= $clienteTipo === 'suporte' ? 'selected' : '' ?>><?= __('admin.tickets.filter.type_support', 'Suporte') ?></option>
                        <option value="vendedor" <?= $clienteTipo === 'vendedor' ? 'selected' : '' ?>><?= __('admin.tickets.filter.type_seller', 'Vendedor') ?></option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" name="atendente_id" onchange="this.form.submit()">
                        <option value="0" <?= $atendenteId === 0 ? 'selected' : '' ?>><?= __('admin.tickets.filter.agent_all', 'Atendente: Todos') ?></option>
                        <?php foreach (($atendentes ?? []) as $a): ?>
                            <option value="<?= (int) ($a['id'] ?? 0) ?>" <?= (int) ($a['id'] ?? 0) === $atendenteId ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) ($a['nome'] ?? $a['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars(__('admin.tickets.filter.date_from', 'De'), ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars(__('admin.tickets.filter.date_to', 'Até'), ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                </div>
                <div class="col-6 col-md-2">
                    <a class="btn btn-outline-secondary btn-sm w-100" href="/admin/tickets"><?= __('admin.tickets.filter.clear', 'Limpar') ?></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($tickets)): ?>
            <div class="text-muted"><?= __('admin.tickets.empty', 'Nenhum ticket encontrado.') ?></div>
        <?php else: ?>
            <!-- Desktop: Table -->
            <div class="d-none d-md-block table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= __('admin.tickets.table.customer', 'Cliente') ?></th>
                            <th><?= __('admin.tickets.table.agent', 'Atendente') ?></th>
                            <th><?= __('admin.tickets.table.reason', 'Motivo') ?></th>
                            <th><?= __('admin.tickets.table.subject', 'Assunto') ?></th>
                            <th><?= __('common.status', 'Status') ?></th>
                            <th><?= __('admin.tickets.table.updated', 'Atualizado') ?></th>
                            <th class="text-end"><?= __('common.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                            <?php
                                $st = (string) ($t['status'] ?? 'open');
                                $badge = ($st === 'open') ? 'bg-success' : 'bg-secondary';
                            ?>
                            <tr>
                                <td class="fw-semibold">#<?= (int) ($t['id'] ?? 0) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($t['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars((string) ($t['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($t['atendente_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars((string) ($t['motivo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><?= htmlspecialchars((string) ($t['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $badge ?>"><?= $st === 'open' ? __('ticket.status.open', 'Aberto') : __('ticket.status.closed', 'Fechado') ?></span></td>
                                <td class="text-muted small"><?= htmlspecialchars((string) ($t['updated_at'] ?? $t['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary position-relative" href="/admin/tickets/<?= (int) ($t['id'] ?? 0) ?>">
                                        <i class="fas fa-comments"></i>
                                        <?php if (!empty($t['has_unread'])): ?>
                                            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 12px; height: 12px;">
                                                <span class="visually-hidden"><?= __('admin.tickets.unread_messages', 'mensagens não lidas') ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile: Cards -->
            <div class="d-md-none">
                <?php foreach ($tickets as $t): ?>
                    <?php
                        $st = (string) ($t['status'] ?? 'open');
                        $badge = ($st === 'open') ? 'bg-success' : 'bg-secondary';
                    ?>
                    <a href="/admin/tickets/<?= (int) ($t['id'] ?? 0) ?>" class="text-decoration-none d-block mb-2">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="fw-bold text-dark">#<?= (int) ($t['id'] ?? 0) ?></span>
                                    <span class="badge <?= $badge ?>"><?= $st === 'open' ? __('ticket.status.open', 'Aberto') : __('ticket.status.closed', 'Fechado') ?></span>
                                </div>
                                <div class="fw-semibold text-dark" style="word-break:break-word;"><?= htmlspecialchars((string) ($t['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small" style="word-break:break-all;"><?= htmlspecialchars((string) ($t['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($t['atendente_nome'])): ?>
                                    <div class="small mt-1"><span class="text-muted"><?= __('admin.tickets.table.agent', 'Atendente') ?>:</span> <?= htmlspecialchars((string) ($t['atendente_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                <?php if (!empty($t['motivo'])): ?>
                                    <div class="small mt-1"><span class="text-muted"><?= __('admin.tickets.table.reason', 'Motivo') ?>:</span> <?= htmlspecialchars((string) ($t['motivo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                <?php if (!empty($t['assunto'])): ?>
                                    <div class="small mt-1" style="word-break:break-word;"><span class="text-muted"><?= __('admin.tickets.table.subject', 'Assunto') ?>:</span> <?= htmlspecialchars((string) ($t['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                <div class="text-muted small mt-1"><?= htmlspecialchars((string) ($t['updated_at'] ?? $t['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($t['has_unread'])): ?>
                                    <span class="badge bg-danger mt-1"><?= __('admin.tickets.new_message', 'Nova mensagem') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
