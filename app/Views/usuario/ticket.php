<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <?php $activePage = 'tickets'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>

        <div class="col-lg-9">
            <script>
                window.TICKET_I18N = {
                    confirm_close: <?= json_encode(__('ticket.confirm_close', 'Tem certeza que deseja encerrar este ticket?'), JSON_UNESCAPED_UNICODE) ?>,
                    copied: <?= json_encode(__('common.copied', 'Copiado'), JSON_UNESCAPED_UNICODE) ?>,
                    copy: <?= json_encode(__('common.copy', 'Copiar'), JSON_UNESCAPED_UNICODE) ?>
                };
            </script>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h2 class="mb-1"><?= __('ticket.title', 'Ticket #{id}', ['id' => (int) ($ticket['id'] ?? 0)]) ?></h2>
                    <div class="text-muted small"><?= htmlspecialchars((string) ($ticket['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div>
                    <a class="btn btn-outline-secondary" href="/meus-pedidos"><i class="fas fa-arrow-left me-1"></i><?= __('common.back', 'Voltar') ?></a>
                </div>
            </div>

            <?php $st = (string) ($ticket['status'] ?? 'open'); ?>
            <div class="card border-0 shadow-sm brz-chat">
                <div class="card-body p-0">
                    <style>
                        .brz-chat { overflow: hidden; }
                        .brz-chat, .brz-chat * { box-sizing: border-box; }
                        .brz-chat .brz-chat-header {
                            padding: 14px 16px;
                            border-bottom: 1px solid rgba(148,163,184,.25);
                            background: #fff;
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 12px;
                        }
                        .brz-chat .brz-chat-header .brz-title {
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            min-width: 0;
                        }
                        .brz-chat .brz-chat-header .brz-sub {
                            color: #64748b;
                            font-size: 12px;
                            white-space: nowrap;
                        }
                        .brz-chat .brz-chat-body {
                            background: #fff;
                            height: min(62vh, 560px);
                            overflow: auto;
                            padding: 16px;
                            display: flex;
                            flex-direction: column;
                            gap: 12px;
                        }
                        .brz-chat .brz-row { width: 100%; display: flex; }
                        .brz-chat .brz-row.me { justify-content: flex-end; }
                        .brz-chat .brz-row.other { justify-content: flex-start; }
                        .brz-chat .brz-bubble {
                            width: fit-content;
                            max-width: min(82%, 760px);
                            padding: 12px 14px;
                            border-radius: 14px;
                            display: flex;
                            flex-direction: column;
                            gap: 8px;
                            text-align: left !important;
                        }
                        .brz-chat .brz-bubble * { text-align: left !important; }
                        .brz-chat .brz-bubble.me { background: #0b1f3a; color: #fff; border-top-right-radius: 8px; }
                        .brz-chat .brz-bubble.other { background: #f1f5f9; color: #0f172a; border-top-left-radius: 8px; }
                        .brz-chat .brz-meta {
                            width: 100%;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            font-size: 12px;
                            opacity: .85;
                        }
                        .brz-chat .brz-text {
                            width: 100%;
                            white-space: pre-wrap;
                            word-break: break-word;
                            overflow-wrap: anywhere;
                            line-height: 1.35;
                        }
                        .brz-chat .brz-attachments {
                            width: 100%;
                            display: grid;
                            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                            gap: 10px;
                        }
                        .brz-chat .brz-attachments a {
                            display: block;
                            border-radius: 12px;
                            overflow: hidden;
                            border: 1px solid rgba(148,163,184,.35);
                            background: #fff;
                        }
                        .brz-chat .brz-attachments img {
                            width: 100%;
                            height: 110px;
                            object-fit: cover;
                            display: block;
                        }
                        .brz-chat .brz-chat-footer {
                            background: #fff;
                            border-top: 1px solid rgba(148,163,184,.25);
                            padding: 14px 16px;
                        }
                        .brz-chat .brz-composer { display: grid; grid-template-columns: 1fr 56px; gap: 10px; }
                        .brz-chat .brz-composer textarea { min-height: 90px; resize: vertical; }
                        .brz-chat .brz-actions { display: flex; flex-direction: column; gap: 10px; }
                        .brz-chat .brz-actions .btn { height: 44px; }
                        .brz-chat .brz-actions { margin-top: 24px; }
                        @media (max-width: 768px) {
                            .brz-chat .brz-chat-body { height: min(66vh, 560px); }
                            .brz-chat .brz-composer { grid-template-columns: 1fr; }
                            .brz-chat .brz-actions { flex-direction: row; }
                            .brz-chat .brz-actions .btn { width: 100%; }
                        }
                    </style>

                    <div class="brz-chat-header">
                        <div class="brz-title">
                            <span class="badge <?= ($st === 'open' ? 'bg-success' : 'bg-secondary') ?>"><?= $st === 'open' ? __('ticket.status.open', 'Aberto') : __('ticket.status.closed', 'Fechado') ?></span>
                            <?php if (!empty($ticket['pedido_id'])): ?>
                                <div class="brz-sub"><?= __('ticket.order', 'Pedido #{id}', ['id' => (int) ($ticket['pedido_id'] ?? 0)]) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($st === 'open'): ?>
                            <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#fecharTicketBox" aria-expanded="false" aria-controls="fecharTicketBox">
                                <i class="fas fa-lock me-1"></i><?= __('ticket.close', 'Encerrar') ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($_GET['closure_error'])): ?>
                        <div class="alert alert-warning mt-3 mb-0"><?= __('ticket.closure_error', 'Para encerrar o ticket, informe o que ficou decidido entre a empresa e você.') ?></div>
                    <?php endif; ?>

                    <?php if ($st === 'open'): ?>
                        <div class="collapse mt-3" id="fecharTicketBox">
                            <div class="border rounded p-3" style="background: #fff;">
                                <form method="POST" action="/meu-ticket/<?= (int) ($ticket['id'] ?? 0) ?>/fechar" onsubmit="return confirm((window.TICKET_I18N && window.TICKET_I18N.confirm_close) ? window.TICKET_I18N.confirm_close : 'Tem certeza que deseja encerrar este ticket?');">
                                    <div class="mb-2">
                                        <label class="form-label mb-1"><?= __('ticket.closure_decision_label', 'O que ficou decidido (obrigatório)') ?></label>
                                        <textarea class="form-control" name="closure_decision" rows="3" required></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-danger btn-sm"><?= __('ticket.closure_confirm', 'Confirmar encerramento') ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($ticket['closure_decision']) || !empty($ticket['closed_by_type']) || !empty($ticket['closed_by_user_id'])): ?>
                        <div class="border rounded p-3 mt-3" style="background: #fff;">
                            <div class="fw-semibold mb-2"><?= __('ticket.closure_record', 'Registro de encerramento') ?></div>
                            <?php if (!empty($ticket['closure_decision'])): ?>
                                <div style="white-space: pre-wrap;"><?= htmlspecialchars((string) $ticket['closure_decision'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                            <div class="text-muted small mt-2">
                                <?= htmlspecialchars((string) ($ticket['closed_by_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($ticket['closed_by_user_id'])): ?>
                                    #<?= (int) $ticket['closed_by_user_id'] ?>
                                <?php endif; ?>
                                <?php if (!empty($ticket['closed_at'])): ?>
                                    • <?= htmlspecialchars((string) $ticket['closed_at'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="brz-chat-body">
                        <?php if (empty($messages)): ?>
                            <div class="text-muted"><?= __('ticket.empty', 'Escreva a primeira mensagem para iniciar o atendimento.') ?></div>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <?php $isMe = ((string) ($m['autor_tipo'] ?? '')) === 'cliente'; ?>
                                <div class="brz-row <?= $isMe ? 'me' : 'other' ?>">
                                    <div class="brz-bubble <?= $isMe ? 'me' : 'other' ?>">
                                        <div class="brz-meta">
                                            <?php if ($isMe): ?>
                                                <?= __('ticket.you', 'Você') ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars((string) (($m['autor_nome'] ?? '') !== '' ? $m['autor_nome'] : __('ticket.admin_support', 'Admin/Suporte')), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                            <span class="opacity-75">•</span>
                                            <span class="opacity-75"><?= htmlspecialchars((string) ($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($isMe): ?>
                                                <?php $readAt = trim((string) ($m['read_at'] ?? '')); ?>
                                                <?php if ($readAt !== ''): ?>
                                                    <span title="Lida em <?= htmlspecialchars($readAt, ENT_QUOTES, 'UTF-8') ?>" style="margin-left:4px;color:#53bdeb;">✓✓</span>
                                                <?php else: ?>
                                                    <span title="Enviada" style="margin-left:4px;opacity:.5;">✓✓</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="brz-text"><?= htmlspecialchars((string) ($m['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>

                                        <?php if (!empty($m['attachments']) && is_array($m['attachments'])): ?>
                                            <div class="brz-attachments">
                                                <?php foreach ($m['attachments'] as $a): ?>
                                                    <?php $p = (string) ($a['file_path'] ?? ''); ?>
                                                    <?php if ($p !== ''): ?>
                                                        <a href="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                            <img src="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(__('ticket.attachment', 'Anexo'), ENT_QUOTES, 'UTF-8') ?>">
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

                    <div class="brz-chat-footer">
                        <?php if ($st === 'open'): ?>
                            <form method="POST" action="/meu-ticket/<?= (int) ($ticket['id'] ?? 0) ?>/mensagem" enctype="multipart/form-data">
                                <div class="brz-composer">
                                    <div>
                                        <label class="form-label mb-1"><?= __('ticket.message', 'Mensagem') ?></label>
                                        <textarea class="form-control" name="mensagem" required></textarea>
                                        <div class="mt-2">
                                            <input class="form-control" type="file" name="imagens[]" accept="image/jpeg,image/png,image/webp" multiple>
                                            <div class="form-text"><?= __('ticket.attach_hint', 'Anexe imagens (JPG/PNG/WebP até 5MB).') ?></div>
                                        </div>
                                    </div>
                                    <div class="brz-actions">
                                        <button type="submit" class="btn btn-primary w-100" title="<?= htmlspecialchars(__('common.send', 'Enviar'), ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-secondary mb-0"><?= __('ticket.closed_notice', 'Ticket fechado. Se precisar, abra um novo ticket a partir do pedido.') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
