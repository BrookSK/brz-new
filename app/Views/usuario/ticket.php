<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <?php $activePage = 'tickets'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h2 class="mb-1">Ticket #<?= (int) ($ticket['id'] ?? 0) ?></h2>
                    <div class="text-muted small"><?= htmlspecialchars((string) ($ticket['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div>
                    <a class="btn btn-outline-secondary" href="/meus-pedidos"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <?php $st = (string) ($ticket['status'] ?? 'open'); ?>
                        <span class="badge <?= ($st === 'open' ? 'bg-success' : 'bg-secondary') ?>">
                            <?= $st === 'open' ? 'Aberto' : 'Fechado' ?>
                        </span>
                        <?php if (!empty($ticket['pedido_id'])): ?>
                            <div class="text-muted small">Pedido #<?= (int) ($ticket['pedido_id'] ?? 0) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="border rounded p-3" style="background:#fff; max-height: 420px; overflow:auto;">
                        <?php if (empty($messages)): ?>
                            <div class="text-muted">Escreva a primeira mensagem para iniciar o atendimento.</div>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <?php $isMe = ((string) ($m['autor_tipo'] ?? '')) === 'cliente'; ?>
                                <div class="d-flex mb-3 <?= $isMe ? 'justify-content-end' : 'justify-content-start' ?>">
                                    <div class="p-3 rounded" style="max-width: 85%; <?= $isMe ? 'background:#0b1f3a; color:#fff;' : 'background:#f3f4f6; color:#111;' ?>">
                                        <div class="small" style="opacity: .8;">
                                            <?= $isMe ? 'Você' : 'Suporte' ?> • <?= htmlspecialchars((string) ($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
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
                        <form class="mt-3" method="POST" action="/meu-ticket/<?= (int) ($ticket['id'] ?? 0) ?>/mensagem">
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
                        <div class="alert alert-secondary mt-3 mb-0">Ticket fechado. Se precisar, abra um novo ticket a partir do pedido.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
