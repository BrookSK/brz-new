<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><?= __('ticket.title', 'Ticket #{id}', ['id' => (int) ($ticket['id'] ?? 0)]) ?></h1>
        <div class="text-muted small"><?= htmlspecialchars((string) ($ticket['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="text-muted small"><?= __('admin.orders.table.customer', 'Cliente') ?>: <?= htmlspecialchars((string) ($ticket['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($ticket['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets"><i class="fas fa-arrow-left me-1"></i><?= __('common.back', 'Voltar') ?></a>
        <?php $st = (string) ($ticket['status'] ?? 'open'); ?>
        <?php if (!empty($pedidoManual) && $st === 'open'): ?>
            <button class="btn btn-outline-primary btn-sm position-relative" id="btnContatarVendedor" type="button" data-bs-toggle="collapse" data-bs-target="#contatarVendedorBox" aria-expanded="false" aria-controls="contatarVendedorBox">
                <i class="fas fa-user-tie me-1"></i><?= __('admin.ticket.vendor_chat.contact_seller', 'Contatar vendedor') ?>
                <?php if (!empty($vendorChatHasUnread)): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="badgeVendorUnread">!</span>
                <?php endif; ?>
            </button>
        <?php endif; ?>
        <?php if ($st === 'open'): ?>
            <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#fecharTicketBox" aria-expanded="false" aria-controls="fecharTicketBox">
                <i class="fas fa-lock me-1"></i><?= __('ticket.close', 'Fechar') ?>
            </button>
        <?php else: ?>
            <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/reabrir">
                <button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-unlock me-1"></i><?= __('admin.ticket.reopen', 'Reabrir') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_GET['closure_error'])): ?>
    <div class="alert alert-warning"><?= __('ticket.closure_error', 'Para fechar o ticket, informe o que ficou decidido entre a empresa e o cliente.') ?></div>
<?php endif; ?>

<?php if ($st === 'open'): ?>
    <div class="collapse mb-3" id="fecharTicketBox">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/fechar" onsubmit="return confirm(<?= json_encode(__('ticket.confirm_close', 'Tem certeza que deseja fechar este ticket?'), JSON_UNESCAPED_UNICODE) ?>);">
                    <div class="mb-2">
                        <label class="form-label mb-1"><?= __('ticket.closure_decision_label', 'O que ficou decidido (obrigatório)') ?></label>
                        <textarea class="form-control" name="closure_decision" rows="3" required></textarea>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-danger btn-sm"><?= __('ticket.closure_confirm', 'Confirmar fechamento') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($ticket['closure_decision']) || !empty($ticket['closed_by_type']) || !empty($ticket['closed_by_user_id'])): ?>
    <div class="card border-0 shadow-sm mt-3 mb-3">
        <div class="card-header bg-white"><div class="fw-semibold"><?= __('ticket.closure_record', 'Registro de encerramento') ?></div></div>
        <div class="card-body">
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
    </div>
<?php endif; ?>

<?php if (!empty($pedidoManual) && $st === 'open'): ?>
    <div class="collapse mb-3" id="contatarVendedorBox">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <style>
                    .vendor-chat-box { border: 1px solid rgba(148,163,184,.25); border-radius: 12px; padding: 12px; background: #fff; max-height: 320px; overflow: auto; display: flex; flex-direction: column; gap: 10px; }
                    .vendor-row { width: 100%; display: flex; }
                    .vendor-row.me { justify-content: flex-end; }
                    .vendor-row.other { justify-content: flex-start; }
                    .vendor-bubble { width: fit-content; max-width: min(82%, 760px); padding: 10px 12px; border-radius: 12px; display: flex; flex-direction: column; gap: 6px; }
                    .vendor-bubble.me { background: #0b1f3a; color: #fff; border-top-right-radius: 8px; }
                    .vendor-bubble.other { background: #f1f5f9; color: #0f172a; border-top-left-radius: 8px; }
                    .vendor-meta { font-size: 12px; opacity: .85; display: flex; gap: 8px; }
                    .vendor-text { white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere; line-height: 1.35; }
                </style>

                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label mb-1"><?= __('admin.ticket.vendor_chat.seller', 'Vendedor') ?></label>
                        <select class="form-select" onchange="location.href='/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>?vendedor_id=' + this.value + '#contatarVendedorBox';">
                            <option value=""><?= __('common.select', 'Selecione...') ?></option>
                            <?php $selVid = (int) ($selectedVendedorId ?? 0); ?>
                            <?php if (!empty($vendedores) && is_array($vendedores)): ?>
                                <?php foreach ($vendedores as $v): ?>
                                    <?php $vid = (int) ($v['id'] ?? 0); ?>
                                    <option value="<?= $vid ?>" <?= ($selVid === $vid) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) ($v['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($vendedores)): ?>
                            <div class="text-muted small mt-2"><?= __('admin.ticket.vendor_chat.no_seller_found', 'Nenhum vendedor encontrado (role/perfil = vendedor). Verifique o cadastro de usuários.') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label mb-1"><?= __('admin.ticket.vendor_chat.internal_conversation', 'Conversa interna (suporte x vendedor)') ?></label>
                        <div class="vendor-chat-box">
                            <?php if (empty($selectedVendedorId)): ?>
                                <div class="text-muted"><?= __('admin.ticket.vendor_chat.select_seller_to_view_history', 'Selecione um vendedor para ver o histórico.') ?></div>
                            <?php elseif (empty($vendorChatMessages)): ?>
                                <div class="text-muted"><?= __('admin.ticket.vendor_chat.no_messages_yet', 'Sem mensagens ainda com este vendedor.') ?></div>
                            <?php else: ?>
                                <?php foreach ($vendorChatMessages as $vm): ?>
                                    <?php $isMe = ((string) ($vm['sender_type'] ?? '')) === 'suporte'; ?>
                                    <div class="vendor-row <?= $isMe ? 'me' : 'other' ?>">
                                        <div class="vendor-bubble <?= $isMe ? 'me' : 'other' ?>">
                                            <div class="vendor-meta">
                                                <?= $isMe ? __('admin.ticket.vendor_chat.support', 'Suporte') : __('admin.ticket.vendor_chat.seller', 'Vendedor') ?>
                                                <span class="opacity-75">•</span>
                                                <span class="opacity-75"><?= htmlspecialchars((string) ($vm['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="vendor-text"><?= htmlspecialchars((string) ($vm['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>

                                            <?php if (!empty($vm['attachments']) && is_array($vm['attachments'])): ?>
                                                <div class="mt-2">
                                                    <?php foreach ($vm['attachments'] as $va): ?>
                                                        <?php $fp = (string) ($va['file_path'] ?? ''); ?>
                                                        <?php $on = (string) ($va['original_name'] ?? ''); ?>
                                                        <?php if ($fp !== ''): ?>
                                                            <div>
                                                                <a href="<?= htmlspecialchars($fp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                                                    <i class="fas fa-paperclip me-1"></i><?= htmlspecialchars($on !== '' ? $on : $fp, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/contatar-vendedor" enctype="multipart/form-data" class="d-flex gap-2 align-items-start">
                            <input type="hidden" name="vendedor_id" value="<?= (int) ($selectedVendedorId ?? 0) ?>">
                            <div class="flex-grow-1">
                                <label class="form-label mb-1"><?= __('ticket.message', 'Mensagem') ?></label>
                                <textarea class="form-control" name="mensagem" rows="2" required <?= empty($selectedVendedorId) ? 'disabled' : '' ?> placeholder="<?= htmlspecialchars(__('admin.ticket.vendor_chat.message_placeholder', 'Digite a mensagem...'), ENT_QUOTES, 'UTF-8') ?>" ></textarea>
                                <div class="mt-2">
                                    <input class="form-control" type="file" name="documentos[]" multiple <?= empty($selectedVendedorId) ? 'disabled' : '' ?> >
                                    <div class="form-text"><?= __('admin.ticket.vendor_chat.attach_hint', 'Anexe documentos/arquivos (até 20MB cada).') ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 26px;">
                                <button type="submit" class="btn btn-primary" title="<?= htmlspecialchars(__('common.send', 'Enviar'), ENT_QUOTES, 'UTF-8') ?>" <?= empty($selectedVendedorId) ? 'disabled' : '' ?> style="height: 42px; width: 56px;">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                        <div class="form-text mt-2"><?= __('admin.ticket.vendor_chat.internal_notice', 'Conversa interna: não aparece para o cliente. Não há opção de apagar mensagens.') ?></div>
                    </div>
                </div>

                <script>
                    (function () {
                        try {
                            var qs = new URLSearchParams(window.location.search || '');
                            var hasVendor = (qs.get('vendedor_id') || '') !== '';
                            if (!hasVendor) return;

                            var el = document.getElementById('contatarVendedorBox');
                            if (!el) return;

                            if (window.bootstrap && typeof window.bootstrap.Collapse === 'function') {
                                var inst = window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                                inst.show();
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                                var inst2 = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                                inst2.show();
                            } else {
                                el.classList.add('show');
                            }
                        } catch (e) {
                        }
                    })();
                </script>

                <script>
                    (function () {
                        try {
                            var el = document.getElementById('contatarVendedorBox');
                            if (!el) return;

                            var markSeen = function () {
                                try {
                                    var vidEl = document.querySelector('input[name="vendedor_id"]');
                                    var vid = vidEl ? (vidEl.value || '') : '';
                                    if (!vid) return;

                                    var badge = document.getElementById('badgeVendorUnread');
                                    if (badge) badge.style.display = 'none';

                                    var fd = new FormData();
                                    fd.append('vendedor_id', vid);

                                    var url = '/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/vendor-chat/seen';
                                    if (navigator.sendBeacon) {
                                        navigator.sendBeacon(url, fd);
                                    } else {
                                        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
                                    }
                                } catch (e) {
                                }
                            };

                            if (window.bootstrap && window.bootstrap.Collapse) {
                                el.addEventListener('shown.bs.collapse', markSeen);
                            } else {
                                el.addEventListener('click', function () {
                                    if (el.classList.contains('show')) {
                                        markSeen();
                                    }
                                });
                            }
                        } catch (e) {
                        }
                    })();
                </script>
            </div>
        </div>
    </div>
<?php endif; ?>

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
            .brz-chat .brz-actions { margin-top: 22px; }
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
        </div>

        <div class="brz-chat-body">
            <?php if (empty($messages)): ?>
                <div class="text-muted"><?= __('ticket.empty', 'Sem mensagens ainda.') ?></div>
            <?php else: ?>
                <?php foreach ($messages as $m): ?>
                    <?php $isAdmin = ((string) ($m['autor_tipo'] ?? '')) === 'admin'; ?>
                    <div class="brz-row <?= $isAdmin ? 'me' : 'other' ?>">
                        <div class="brz-bubble <?= $isAdmin ? 'me' : 'other' ?>">
                            <div class="brz-meta">
                                <?php if ($isAdmin): ?>
                                    <?= htmlspecialchars((string) (($m['autor_nome'] ?? '') !== '' ? $m['autor_nome'] : __('ticket.admin_support', 'Admin/Suporte')), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <?= __('admin.orders.table.customer', 'Cliente') ?>
                                <?php endif; ?>
                                <span class="opacity-75">•</span>
                                <span class="opacity-75"><?= htmlspecialchars((string) ($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($isAdmin): ?>
                                    <?php $readAt = trim((string) ($m['read_at'] ?? '')); ?>
                                    <?php if ($readAt !== ''): ?>
                                        <span title="Lida pelo cliente em <?= htmlspecialchars($readAt, ENT_QUOTES, 'UTF-8') ?>" style="margin-left:4px;color:#53bdeb;">✓✓</span>
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
                <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/mensagem" enctype="multipart/form-data">
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
                <div class="alert alert-secondary mb-0"><?= __('admin.ticket.closed_notice', 'Ticket fechado.') ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($pedidoDetalhes) && is_array($pedidoDetalhes) && !empty($ticket['pedido_id'])): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fw-semibold"><?= __('admin.ticket.order_details.title', 'Detalhes do pedido do ticket') ?></div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="text-muted small"><?= __('ticket.order', 'Pedido #{id}', ['id' => (int) ($ticket['pedido_id'] ?? 0)]) ?></div>
                    <a class="btn btn-outline-primary btn-sm" href="/admin/pedidos/detalhes/<?= (int) ($ticket['pedido_id'] ?? 0) ?>" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt me-1"></i><?= __('admin.ticket.order_details.open_order', 'Abrir pedido') ?>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php $pd = $pedidoDetalhes; ?>

            <div class="row g-3">
                <div class="col-lg-4">
                    <style>
                        .pedido-kv td { vertical-align: middle; }
                        .pedido-kv .k { color: #64748b; font-size: 12px; }
                        .pedido-kv .v { text-align: right; font-weight: 600; color: #0f172a; }
                        .pedido-kv .v.muted { font-weight: 500; color: #334155; }
                        .pedido-totais { background: rgba(148,163,184,.10); border: 1px solid rgba(148,163,184,.22); border-radius: 12px; padding: 12px; }
                        .pedido-totais .rowline { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; }
                        .pedido-totais .rowline .l { color: #475569; font-size: 12px; }
                        .pedido-totais .rowline .r { font-weight: 700; }
                        .pedido-totais .rowline.total { border-top: 1px solid rgba(148,163,184,.28); margin-top: 6px; padding-top: 10px; }
                    </style>

                    <?php
                        $codigoPedido = (string) ($pd['codigo_pedido'] ?? ($pd['numero_pedido'] ?? ($pd['id'] ?? '')));
                        $moedaProc = (string) ($pd['moeda'] ?? '');
                        $moedaOrig = '';
                        foreach (['moeda_original', 'currency_original', 'original_currency'] as $k) {
                            if (!empty($pd[$k])) { $moedaOrig = (string) $pd[$k]; break; }
                        }

                        // Priorizar campos normalizados pelo PedidoEcommerce
                        $subtotal = $pd['subtotal_produtos'] ?? ($pd['subtotal'] ?? null);
                        $frete = $pd['valor_frete'] ?? ($pd['frete'] ?? null);
                        $servico = $pd['taxa_servico'] ?? ($pd['servicos'] ?? null);
                        $impostos = $pd['valor_impostos'] ?? ($pd['impostos'] ?? null);
                        $total = $pd['total'] ?? ($pd['valor_total'] ?? null);
                        $taxaConv = $pd['taxa_conversao'] ?? null;
                    ?>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-3 pedido-kv">
                            <tbody>
                                <tr>
                                    <td class="k"><?= __('admin.ticket.order_details.code', 'Código') ?></td>
                                    <td class="v muted"><?= htmlspecialchars($codigoPedido, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php if ($moedaProc !== ''): ?>
                                    <tr>
                                        <td class="k"><?= __('admin.ticket.order_details.processed_currency', 'Moeda processada') ?></td>
                                        <td class="v muted"><?= htmlspecialchars($moedaProc, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($moedaOrig !== ''): ?>
                                    <tr>
                                        <td class="k"><?= __('admin.ticket.order_details.original_currency', 'Moeda original') ?></td>
                                        <td class="v muted"><?= htmlspecialchars($moedaOrig, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($pd['status'])): ?>
                                    <tr>
                                        <td class="k"><?= __('common.status', 'Status') ?></td>
                                        <td class="v muted"><?= htmlspecialchars((string) ($pd['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($taxaConv !== null && $taxaConv !== '' && (float) $taxaConv != 0.0): ?>
                                    <tr>
                                        <td class="k"><?= __('admin.ticket.order_details.exchange_rate', 'Taxa conversão') ?></td>
                                        <td class="v muted"><?= htmlspecialchars((string) $taxaConv, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pedido-totais">
                        <?php if ($subtotal !== null && $subtotal !== '' && (float) $subtotal != 0.0): ?>
                            <div class="rowline"><div class="l"><?= __('checkout.subtotal', 'Subtotal') ?></div><div class="r"><?= htmlspecialchars((string) $subtotal, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                        <?php if ($frete !== null && $frete !== '' && (float) $frete != 0.0): ?>
                            <div class="rowline"><div class="l"><?= __('admin.ticket.order_details.shipping', 'Frete') ?></div><div class="r"><?= htmlspecialchars((string) $frete, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                        <?php if ($servico !== null && $servico !== '' && (float) $servico != 0.0): ?>
                            <div class="rowline"><div class="l"><?= __('admin.ticket.order_details.fees', 'Taxas') ?></div><div class="r"><?= htmlspecialchars((string) $servico, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                        <?php if ($impostos !== null && $impostos !== '' && (float) $impostos != 0.0): ?>
                            <div class="rowline"><div class="l"><?= __('admin.ticket.order_details.taxes', 'Impostos') ?></div><div class="r"><?= htmlspecialchars((string) $impostos, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                        <?php if ($total !== null && $total !== '' && (float) $total != 0.0): ?>
                            <div class="rowline total"><div class="l"><?= __('common.total', 'Total') ?></div><div class="r"><?= htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="small text-muted"><?= __('admin.ticket.order_details.items', 'Itens') ?></div>
                    <?php $itens = $pd['itens'] ?? ($pd['items'] ?? []); ?>
                    <?php if (empty($itens) || !is_array($itens)): ?>
                        <div class="text-muted"><?= __('admin.ticket.order_details.items_load_failed', 'Não foi possível carregar os itens.') ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('admin.ticket.order_details.table.product', 'Produto') ?></th>
                                        <th class="text-end"><?= __('admin.ticket.order_details.table.qty', 'Qtd') ?></th>
                                        <th class="text-end"><?= __('admin.ticket.order_details.table.unit', 'Unit') ?></th>
                                        <th class="text-end"><?= __('checkout.subtotal', 'Subtotal') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $it): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars((string) ($it['nome_produto'] ?? ($it['nome'] ?? __('admin.ticket.order_details.product_fallback', 'Produto'))), ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($it['variacao_descricao'])): ?>
                                                    <div class="text-muted small"><?= htmlspecialchars((string) $it['variacao_descricao'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?= (int) ($it['quantidade'] ?? 0) ?></td>
                                            <td class="text-end"><?= htmlspecialchars((string) ($it['preco_unitario'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><?= htmlspecialchars((string) ($it['subtotal'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
    $perfil = '';
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_role'] ?? ''))));
    } catch (\Exception $e) {
        $perfil = '';
    }
?>

<?php if (in_array($perfil, ['admin', 'suporte'], true)): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <div class="fw-semibold"><?= __('admin.ticket.files.title', 'Arquivos do ticket') ?></div>
        </div>
        <div class="card-body">
            <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/arquivos" enctype="multipart/form-data" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label mb-1"><?= __('admin.ticket.files.upload', 'Enviar arquivo') ?></label>
                        <input class="form-control" type="file" name="arquivo" required>
                        <div class="form-text"><?= __('admin.ticket.files.upload_hint', 'Até 20MB. Alguns tipos perigosos são bloqueados.') ?></div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 mt-1" title="<?= htmlspecialchars(__('admin.ticket.files.upload', 'Enviar arquivo'), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-upload"></i>
                        </button>
                    </div>
                </div>
            </form>

            <?php if (empty($ticketFiles)): ?>
                <div class="text-muted"><?= __('admin.ticket.files.empty', 'Nenhum arquivo anexado ao ticket.') ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= __('admin.ticket.files.table.file', 'Arquivo') ?></th>
                                <th><?= __('admin.ticket.files.table.type', 'Tipo') ?></th>
                                <th><?= __('admin.ticket.files.table.size', 'Tamanho') ?></th>
                                <th><?= __('admin.ticket.files.table.date', 'Data') ?></th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ticketFiles as $f): ?>
                                <?php
                                    $path = (string) ($f['file_path'] ?? '');
                                    $name = (string) ($f['original_name'] ?? '');
                                    $mime = (string) ($f['mime_type'] ?? '');
                                    $size = (int) ($f['file_size'] ?? 0);
                                    $created = (string) ($f['created_at'] ?? '');
                                    $sizeKb = $size > 0 ? round($size / 1024, 1) . ' KB' : '';
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($name !== '' ? $name : $path, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($sizeKb, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <?php if ($path !== ''): ?>
                                            <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <div class="fw-semibold"><?= __('admin.ticket.internal_notes.title', 'Anotações internas (somente admin)') ?></div>
        </div>
        <div class="card-body">
            <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/notas-internas">
                <div class="mb-2">
                    <textarea class="form-control" name="internal_notes" rows="4" placeholder="<?= htmlspecialchars(__('admin.ticket.internal_notes.placeholder', 'Detalhes internos sobre o pedido / tratativa / observações...'), ENT_QUOTES, 'UTF-8') ?>" style="white-space: pre-wrap;" ><?= htmlspecialchars((string) ($ticket['internal_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i><?= __('common.save', 'Salvar') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="fw-semibold"><?= __('admin.ticket.customer_summary.title', 'Resumo do Cliente e Compras') ?></div>
            <?php if (!empty($ticket['pedido_id'])): ?>
                <div class="text-muted small"><?= __('admin.ticket.customer_summary.related_order', 'Relacionado ao pedido #{id}', ['id' => (int) ($ticket['pedido_id'] ?? 0)]) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-2"><?= __('admin.ticket.customer_summary.customer_data', 'Dados do cliente') ?></div>
                    <?php $c = $clienteResumo ?? []; ?>
                    <div class="small text-muted">ID</div>
                    <div class="mb-2"><?= (int) ($c['id'] ?? ($ticket['usuario_id'] ?? 0)) ?></div>

                    <div class="small text-muted"><?= __('common.name', 'Nome') ?></div>
                    <div class="mb-2"><?= htmlspecialchars((string) ($c['nome'] ?? ($ticket['usuario_nome'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="small text-muted"><?= __('common.email', 'E-mail') ?></div>
                    <div class="mb-2"><?= htmlspecialchars((string) ($c['email'] ?? ($ticket['usuario_email'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>

                    <?php
                        $tel = '';
                        foreach (['telefone', 'celular', 'whatsapp', 'phone'] as $k) {
                            if (!empty($c[$k])) { $tel = (string) $c[$k]; break; }
                        }
                        $doc = '';
                        foreach (['cpf', 'documento', 'cpf_cnpj', 'cnpj', 'document'] as $k) {
                            if (!empty($c[$k])) { $doc = (string) $c[$k]; break; }
                        }
                        $cad = '';
                        foreach (['created_at', 'data_cadastro', 'cadastrado_em'] as $k) {
                            if (!empty($c[$k])) { $cad = (string) $c[$k]; break; }
                        }
                    ?>

                    <?php if ($tel !== ''): ?>
                        <div class="small text-muted"><?= __('common.phone', 'Telefone') ?></div>
                        <div class="mb-2"><?= htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <?php if ($doc !== ''): ?>
                        <div class="small text-muted"><?= __('admin.ticket.customer_summary.document', 'Documento') ?></div>
                        <div class="mb-2"><?= htmlspecialchars($doc, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <?php if ($cad !== ''): ?>
                        <div class="small text-muted"><?= __('admin.ticket.customer_summary.registered_at', 'Cadastro') ?></div>
                        <div><?= htmlspecialchars($cad, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="border rounded p-3 mb-3">
                    <div class="fw-semibold mb-2"><?= __('admin.ticket.customer_summary.previous_purchases', 'Compras anteriores') ?></div>
                    <?php if (empty($comprasAnteriores)): ?>
                        <div class="text-muted"><?= __('admin.ticket.customer_summary.no_previous_purchases', 'Nenhuma compra anterior encontrada.') ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('admin.ticket.customer_summary.table.code', 'Código') ?></th>
                                        <th><?= __('common.status', 'Status') ?></th>
                                        <th><?= __('common.total', 'Total') ?></th>
                                        <th><?= __('admin.ticket.customer_summary.table.date', 'Data') ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comprasAnteriores as $p): ?>
                                        <tr>
                                            <td><?= (int) ($p['id'] ?? 0) ?></td>
                                            <td><?= htmlspecialchars((string) ($p['codigo_pedido'] ?? ($p['id'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($p['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($p['total'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($p['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-outline-primary btn-sm" href="/admin/pedidos/detalhes/<?= (int) ($p['id'] ?? 0) ?>" target="_blank" rel="noopener"><?= __('common.view', 'Ver') ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="border rounded p-3 mb-3">
                    <div class="fw-semibold mb-2"><?= __('admin.ticket.customer_summary.order_tickets', 'Tickets do pedido') ?></div>
                    <?php if (empty($ticketsDoPedido)): ?>
                        <div class="text-muted"><?= __('admin.ticket.customer_summary.no_other_order_tickets', 'Nenhum outro ticket relacionado a este pedido.') ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('admin.tickets.table.subject', 'Assunto') ?></th>
                                        <th><?= __('common.status', 'Status') ?></th>
                                        <th><?= __('admin.tickets.table.updated', 'Atualizado') ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ticketsDoPedido as $tp): ?>
                                        <tr>
                                            <td class="text-muted small">#<?= (int) ($tp['id'] ?? 0) ?></td>
                                            <td><?= htmlspecialchars((string) ($tp['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($tp['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars((string) ($tp['atualizado_em'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="/admin/tickets/<?= (int) ($tp['id'] ?? 0) ?>" target="_blank" rel="noopener"><?= __('common.view', 'Ver') ?></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="border rounded p-3">
                    <div class="fw-semibold mb-2"><?= __('admin.ticket.customer_summary.customer_tickets', 'Tickets do cliente (geral)') ?></div>
                    <?php if (empty($ticketsDoCliente)): ?>
                        <div class="text-muted"><?= __('admin.ticket.customer_summary.no_other_customer_tickets', 'Nenhum outro ticket do cliente.') ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('admin.orders.table.order', 'Pedido') ?></th>
                                        <th><?= __('admin.tickets.table.subject', 'Assunto') ?></th>
                                        <th><?= __('common.status', 'Status') ?></th>
                                        <th><?= __('admin.tickets.table.updated', 'Atualizado') ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ticketsDoCliente as $tc): ?>
                                        <tr>
                                            <td class="fw-semibold">#<?= (int) ($tc['id'] ?? 0) ?></td>
                                            <td class="text-muted small"><?= !empty($tc['pedido_id']) ? ('#' . (int) $tc['pedido_id']) : '-' ?></td>
                                            <td><?= htmlspecialchars((string) ($tc['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($tc['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars((string) ($tc['atualizado_em'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="/admin/tickets/<?= (int) ($tc['id'] ?? 0) ?>" target="_blank" rel="noopener"><?= __('common.view', 'Ver') ?></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
