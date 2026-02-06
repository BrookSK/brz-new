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
        <style>
            .ticket-chat-box {
                background: #fff;
                max-height: 520px;
                overflow: auto;
            }
            .ticket-msg-row {
                display: flex;
                margin-bottom: 12px;
            }
            .ticket-msg-row.is-admin {
                justify-content: flex-end;
            }
            .ticket-msg-row.is-client {
                justify-content: flex-start;
            }
            .ticket-bubble {
                max-width: 85%;
                padding: 12px 14px;
                border-radius: 12px;
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                text-align: left !important;
                white-space: pre-wrap;
                word-break: break-word;
                overflow-wrap: anywhere;
            }
            .ticket-bubble * {
                text-align: left !important;
            }
            .ticket-bubble.is-admin {
                background: #0b1f3a;
                color: #fff;
                border-top-right-radius: 6px;
            }
            .ticket-bubble.is-client {
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
                width: 100% !important;
            }
            .ticket-text {
                display: block !important;
                text-align: left !important;
                width: 100% !important;
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
            <span class="badge <?= ($st === 'open' ? 'bg-success' : 'bg-secondary') ?>"><?= $st === 'open' ? 'Aberto' : 'Fechado' ?></span>
            <?php if (!empty($ticket['pedido_id'])): ?>
                <div class="text-muted small">Pedido #<?= (int) ($ticket['pedido_id'] ?? 0) ?></div>
            <?php endif; ?>
        </div>

        <div class="border rounded p-3 ticket-chat-box">
            <?php if (empty($messages)): ?>
                <div class="text-muted">Sem mensagens ainda.</div>
            <?php else: ?>
                <?php foreach ($messages as $m): ?>
                    <?php $isAdmin = ((string) ($m['autor_tipo'] ?? '')) === 'admin'; ?>
                    <div class="ticket-msg-row <?= $isAdmin ? 'is-admin' : 'is-client' ?>">
                        <div class="ticket-bubble <?= $isAdmin ? 'is-admin' : 'is-client' ?>">
                            <div class="ticket-meta">
                                <?php if ($isAdmin): ?>
                                    <?= htmlspecialchars((string) (($m['autor_nome'] ?? '') !== '' ? $m['autor_nome'] : 'Admin/Suporte'), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    Cliente
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
            <form class="mt-3" method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/mensagem" enctype="multipart/form-data">
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
            <div class="alert alert-secondary mt-3 mb-0">Ticket fechado.</div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($pedidoDetalhes) && is_array($pedidoDetalhes) && !empty($ticket['pedido_id'])): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fw-semibold">Detalhes do pedido do ticket</div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="text-muted small">Pedido #<?= (int) ($ticket['pedido_id'] ?? 0) ?></div>
                    <a class="btn btn-outline-primary btn-sm" href="/admin/pedidos/detalhes/<?= (int) ($ticket['pedido_id'] ?? 0) ?>" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt me-1"></i>Abrir pedido
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php $pd = $pedidoDetalhes; ?>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="small text-muted">Código do pedido</div>
                    <div class="mb-2"><?= htmlspecialchars((string) ($pd['codigo_pedido'] ?? ($pd['numero_pedido'] ?? $pd['id'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="small text-muted">Status</div>
                    <div class="mb-2"><?= htmlspecialchars((string) ($pd['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="small text-muted">Total</div>
                    <div><?= htmlspecialchars((string) ($pd['total'] ?? ($pd['valor_total'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>

                    <?php
                        $valList = [
                            'subtotal_produtos' => 'Subtotal produtos',
                            'valor_frete' => 'Frete',
                            'taxa_servico' => 'Taxa serviço',
                            'valor_impostos' => 'Impostos',
                            'taxa_conversao' => 'Taxa conversão',
                            'subtotal_produtos_brl' => 'Subtotal (BRL)',
                            'valor_frete_brl' => 'Frete (BRL)',
                            'taxa_servico_brl' => 'Taxa serviço (BRL)',
                            'valor_impostos_brl' => 'Impostos (BRL)',
                            'valor_total_brl' => 'Total (BRL)',
                            'total_brl' => 'Total (BRL)',
                        ];
                        foreach ($valList as $k => $label) {
                            if (array_key_exists($k, $pd) && $pd[$k] !== null && $pd[$k] !== '' && (float) $pd[$k] != 0.0) {
                                echo '<div class="small text-muted mt-2">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
                                echo '<div>' . htmlspecialchars((string) $pd[$k], ENT_QUOTES, 'UTF-8') . '</div>';
                            }
                        }
                    ?>
                </div>

                <div class="col-lg-8">
                    <div class="small text-muted">Itens</div>
                    <?php $itens = $pd['itens'] ?? ($pd['items'] ?? []); ?>
                    <?php if (empty($itens) || !is_array($itens)): ?>
                        <div class="text-muted">Não foi possível carregar os itens.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-end">Qtd</th>
                                        <th class="text-end">Unit</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $it): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars((string) ($it['nome_produto'] ?? ($it['nome'] ?? 'Produto')), ENT_QUOTES, 'UTF-8') ?>
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

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="fw-semibold">Resumo do Cliente e Compras</div>
            <?php if (!empty($ticket['pedido_id'])): ?>
                <div class="text-muted small">Relacionado ao pedido #<?= (int) ($ticket['pedido_id'] ?? 0) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-2">Dados do cliente</div>
                    <?php $c = $clienteResumo ?? []; ?>
                    <div class="small text-muted">ID</div>
                    <div class="mb-2"><?= (int) ($c['id'] ?? ($ticket['usuario_id'] ?? 0)) ?></div>

                    <div class="small text-muted">Nome</div>
                    <div class="mb-2"><?= htmlspecialchars((string) ($c['nome'] ?? ($ticket['usuario_nome'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="small text-muted">E-mail</div>
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
                        <div class="small text-muted">Telefone</div>
                        <div class="mb-2"><?= htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <?php if ($doc !== ''): ?>
                        <div class="small text-muted">Documento</div>
                        <div class="mb-2"><?= htmlspecialchars($doc, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <?php if ($cad !== ''): ?>
                        <div class="small text-muted">Cadastro</div>
                        <div><?= htmlspecialchars($cad, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-2">Compras anteriores</div>
                    <?php if (empty($comprasAnteriores)): ?>
                        <div class="text-muted">Nenhuma compra anterior encontrada.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Código</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th>Data</th>
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
                                                <a class="btn btn-outline-primary btn-sm" href="/admin/pedidos/detalhes/<?= (int) ($p['id'] ?? 0) ?>" target="_blank" rel="noopener">Ver</a>
                                            </td>
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
