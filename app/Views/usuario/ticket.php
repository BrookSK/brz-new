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
                        .brz-chat .brz-composer { display: grid; grid-template-columns: 1fr 160px; gap: 10px; }
                        .brz-chat .brz-composer textarea { min-height: 90px; resize: vertical; }
                        .brz-chat .brz-actions { display: flex; flex-direction: column; gap: 10px; }
                        .brz-chat .brz-actions .btn { height: 44px; }
                        @media (max-width: 768px) {
                            .brz-chat .brz-chat-body { height: min(66vh, 560px); }
                            .brz-chat .brz-composer { grid-template-columns: 1fr; }
                            .brz-chat .brz-actions { flex-direction: row; }
                            .brz-chat .brz-actions .btn { width: 100%; }
                        }
                    </style>

                    <div class="brz-chat-header">
                        <div class="brz-title">
                            <span class="badge <?= ($st === 'open' ? 'bg-success' : 'bg-secondary') ?>"><?= $st === 'open' ? 'Aberto' : 'Fechado' ?></span>
                            <?php if (!empty($ticket['pedido_id'])): ?>
                                <div class="brz-sub">Pedido #<?= (int) ($ticket['pedido_id'] ?? 0) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($st === 'open'): ?>
                            <form method="POST" action="/meu-ticket/<?= (int) ($ticket['id'] ?? 0) ?>/fechar">
                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-lock me-1"></i>Encerrar</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="brz-chat-body">
                        <?php if (empty($messages)): ?>
                            <div class="text-muted">Escreva a primeira mensagem para iniciar o atendimento.</div>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <?php $isMe = ((string) ($m['autor_tipo'] ?? '')) === 'cliente'; ?>
                                <div class="brz-row <?= $isMe ? 'me' : 'other' ?>">
                                    <div class="brz-bubble <?= $isMe ? 'me' : 'other' ?>">
                                        <div class="brz-meta">
                                            <?php if ($isMe): ?>
                                                Você
                                            <?php else: ?>
                                                <?= htmlspecialchars((string) (($m['autor_nome'] ?? '') !== '' ? $m['autor_nome'] : 'Admin/Suporte'), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                            <span class="opacity-75">•</span>
                                            <span class="opacity-75"><?= htmlspecialchars((string) ($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="brz-text"><?= htmlspecialchars((string) ($m['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>

                                        <?php if (!empty($m['attachments']) && is_array($m['attachments'])): ?>
                                            <div class="brz-attachments">
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

                    <div class="brz-chat-footer">
                        <?php if ($st === 'open'): ?>
                            <form method="POST" action="/meu-ticket/<?= (int) ($ticket['id'] ?? 0) ?>/mensagem" enctype="multipart/form-data">
                                <div class="brz-composer">
                                    <div>
                                        <label class="form-label mb-1">Mensagem</label>
                                        <textarea class="form-control" name="mensagem" required></textarea>
                                        <div class="mt-2">
                                            <input class="form-control" type="file" name="imagens[]" accept="image/jpeg,image/png,image/webp" multiple>
                                            <div class="form-text">Anexe imagens (JPG/PNG/WebP até 5MB).</div>
                                        </div>
                                    </div>
                                    <div class="brz-actions">
                                        <button type="submit" class="btn btn-primary w-100">Enviar</button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-secondary mb-0">Ticket fechado. Se precisar, abra um novo ticket a partir do pedido.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
