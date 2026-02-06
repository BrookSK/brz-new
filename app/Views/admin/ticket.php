<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Ticket #<?= (int) ($ticket['id'] ?? 0) ?></h1>
        <div class="text-muted small"><?= htmlspecialchars((string) ($ticket['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="text-muted small">Cliente: <?= htmlspecialchars((string) ($ticket['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($ticket['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
        <?php $st = (string) ($ticket['status'] ?? 'open'); ?>
        <?php if ($st === 'open'): ?>
            <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/fechar">
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-lock me-1"></i>Fechar</button>
            </form>
        <?php else: ?>
            <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/reabrir">
                <button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-unlock me-1"></i>Reabrir</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="badge <?= ($st === 'open' ? 'bg-success' : 'bg-secondary') ?>"><?= $st === 'open' ? 'Aberto' : 'Fechado' ?></span>
            <?php if (!empty($ticket['pedido_id'])): ?>
                <div class="text-muted small">Pedido #<?= (int) ($ticket['pedido_id'] ?? 0) ?></div>
            <?php endif; ?>
        </div>

        <div class="border rounded p-3" style="background:#fff; max-height: 520px; overflow:auto;">
            <?php if (empty($messages)): ?>
                <div class="text-muted">Sem mensagens ainda.</div>
            <?php else: ?>
                <?php foreach ($messages as $m): ?>
                    <?php $isAdmin = ((string) ($m['autor_tipo'] ?? '')) === 'admin'; ?>
                    <div class="d-flex mb-3 <?= $isAdmin ? 'justify-content-end' : 'justify-content-start' ?>">
                        <div class="p-3 rounded" style="max-width: 85%; <?= $isAdmin ? 'background:#0b1f3a; color:#fff;' : 'background:#f3f4f6; color:#111;' ?>">
                            <div class="small" style="opacity: .8;">
                                <?= $isAdmin ? 'Admin/Suporte' : 'Cliente' ?> • <?= htmlspecialchars((string) ($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div style="white-space: pre-wrap;">
                                <?= htmlspecialchars((string) ($m['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($st === 'open'): ?>
            <form class="mt-3" method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/mensagem">
                <div class="row g-2 align-items-end">
                    <div class="col-md-10">
                        <label class="form-label mb-1">Mensagem</label>
                        <textarea class="form-control" name="mensagem" rows="3" required></textarea>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Enviar</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-secondary mt-3 mb-0">Ticket fechado.</div>
        <?php endif; ?>
    </div>
</div>
