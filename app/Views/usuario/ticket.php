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
                    <style>
                        .ticket-chat-box {
                            background: #fff;
                            max-height: 460px;
                            overflow: auto;
                        }
                        .ticket-msg-row {
                            display: flex;
                            margin-bottom: 12px;
                        }
                        .ticket-msg-row.is-me {
                            justify-content: flex-end;
                        }
                        .ticket-msg-row.is-other {
                            justify-content: flex-start;
                        }
                        .ticket-bubble {
                            max-width: 85%;
                            padding: 12px 14px;
                            border-radius: 12px;
                            display: block !important;
                            text-align: left !important;
                            white-space: pre-wrap;
                            word-break: break-word;
                            overflow-wrap: anywhere;
                        }
                        .ticket-bubble * {
                            text-align: left !important;
                        }
                        .ticket-bubble.is-me {
                            background: #0b1f3a;
                            color: #fff;
                            border-top-right-radius: 6px;
                        }
                        .ticket-bubble.is-other {
                            background: #f3f4f6;
                            color: #111;
                            border-top-left-radius: 6px;
                        }
                        .ticket-meta {
                            font-size: 12px;
                            opacity: .85;
                            margin-bottom: 6px;
                            display: block !important;
                            text-align: left !important;
                        }
                        .ticket-text {
                            display: block !important;
                            text-align: left !important;
                        }
                        .ticket-attachments {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 10px;
                            margin-top: 10px;
                        }
                        .ticket-attachments a {
                            display: inline-block;
                            border: 1px solid rgba(0,0,0,.08);
                            border-radius: 10px;
                            overflow: hidden;
                            background: #fff;
                        }
                        .ticket-attachments img {
                            width: 140px;
                            height: 100px;
                            object-fit: cover;
                            display: block;
                        }
                    </style>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <?php $st = (string) ($ticket['status'] ?? 'open'); ?>
                        <span class="badge <?= ($st === 'open' ? 'bg-success' : 'bg-secondary') ?>">
                            <?= $st === 'open' ? 'Aberto' : 'Fechado' ?>
                        </span>
                        <?php if (!empty($ticket['pedido_id'])): ?>
                            <div class="text-muted small">Pedido #<?= (int) ($ticket['pedido_id'] ?? 0) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="border rounded p-3 ticket-chat-box">
                        <?php if (empty($messages)): ?>
                            <div class="text-muted">Escreva a primeira mensagem para iniciar o atendimento.</div>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <?php $isMe = ((string) ($m['autor_tipo'] ?? '')) === 'cliente'; ?>
                                <div class="ticket-msg-row <?= $isMe ? 'is-me' : 'is-other' ?>">
                                    <div class="ticket-bubble <?= $isMe ? 'is-me' : 'is-other' ?>">
                                        <div class="ticket-meta">
                                            <?php if ($isMe): ?>
                                                Você
                                            <?php else: ?>
                                                <?= htmlspecialchars((string) (($m['autor_nome'] ?? '') !== '' ? $m['autor_nome'] : 'Admin/Suporte'), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                            • <?= htmlspecialchars((string) ($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </div>

                                        <div class="ticket-text">
                                            <?= htmlspecialchars((string) ($m['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </div>

                                        <?php if (!empty($m['attachments']) && is_array($m['attachments'])): ?>
                                            <div class="ticket-attachments">
                                                <?php foreach ($m['attachments'] as $a): ?>
                                                    <?php $p = (string) ($a['file_path'] ?? ''); ?>
                                                    <?php if ($p !== ''): ?>
                                                        <a href="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                            <img src="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>" alt="Anexo">
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($st === 'open'): ?>
                        <div class="d-flex justify-content-end mt-3">
                            <form method="POST" action="/meu-ticket/<?= (int) ($ticket['id'] ?? 0) ?>/fechar">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-lock me-1"></i> Encerrar ticket
                                </button>
                            </form>
                        </div>

                        <form class="mt-3" method="POST" action="/meu-ticket/<?= (int) ($ticket['id'] ?? 0) ?>/mensagem" enctype="multipart/form-data">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-10">
                                    <label class="form-label mb-1">Mensagem</label>
                                    <textarea class="form-control" name="mensagem" rows="3" required></textarea>
                                    <div class="mt-2">
                                        <input class="form-control" type="file" name="imagens[]" accept="image/jpeg,image/png,image/webp" multiple>
                                        <div class="form-text">Anexe imagens (JPG/PNG/WebP até 5MB).</div>
                                    </div>
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
