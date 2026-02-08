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
        <h1 class="h4 mb-0">Tickets</h1>
        <div class="text-muted small">Atendimento e comunicação pelo site</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets?status=open">Abertos</a>
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets?status=closed">Fechados</a>
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets?status=all">Todos</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET" action="/admin/tickets">
            <div class="col-md-3">
                <label class="form-label mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Abertos</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Fechados</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">De</label>
                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Até</label>
                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Usuário (tipo)</label>
                <select class="form-select" name="cliente_tipo">
                    <option value="" <?= $clienteTipo === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="admin" <?= $clienteTipo === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="suporte" <?= $clienteTipo === 'suporte' ? 'selected' : '' ?>>Suporte</option>
                    <option value="vendedor" <?= $clienteTipo === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Atendente</label>
                <select class="form-select" name="atendente_id">
                    <option value="0" <?= $atendenteId === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach (($atendentes ?? []) as $a): ?>
                        <option value="<?= (int) ($a['id'] ?? 0) ?>" <?= (int) ($a['id'] ?? 0) === $atendenteId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($a['nome'] ?? $a['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a class="btn btn-outline-secondary w-100" href="/admin/tickets"><i class="fas fa-eraser me-1"></i>Limpar</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($tickets)): ?>
            <div class="text-muted">Nenhum ticket encontrado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Atendente</th>
                            <th>Motivo</th>
                            <th>Assunto</th>
                            <th>Status</th>
                            <th>Atualizado</th>
                            <th class="text-end">Ações</th>
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
                                <td><span class="badge <?= $badge ?>"><?= $st === 'open' ? 'Aberto' : 'Fechado' ?></span></td>
                                <td class="text-muted small"><?= htmlspecialchars((string) ($t['updated_at'] ?? $t['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/tickets/<?= (int) ($t['id'] ?? 0) ?>">
                                        <i class="fas fa-comments"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
