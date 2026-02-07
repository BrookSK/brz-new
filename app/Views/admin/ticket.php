<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Ticket #<?= (int) ($ticket['id'] ?? 0) ?></h1>
        <div class="text-muted small"><?= htmlspecialchars((string) ($ticket['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="text-muted small">Cliente: <?= htmlspecialchars((string) ($ticket['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($ticket['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/admin/tickets"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
        <?php $st = (string) ($ticket['status'] ?? 'open'); ?>
        <?php if (!empty($pedidoManual) && !empty($vendedores) && is_array($vendedores) && $st === 'open'): ?>
            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#contatarVendedorBox" aria-expanded="false" aria-controls="contatarVendedorBox">
                <i class="fas fa-user-tie me-1"></i>Contatar vendedor
            </button>
        <?php endif; ?>
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

<?php if (!empty($pedidoManual) && !empty($vendedores) && is_array($vendedores) && $st === 'open'): ?>
    <div class="collapse mb-3" id="contatarVendedorBox">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/contatar-vendedor">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label mb-1">Vendedor</label>
                            <select class="form-select" name="vendedor_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($vendedores as $v): ?>
                                    <?php $vid = (int) ($v['id'] ?? 0); ?>
                                    <option value="<?= $vid ?>" <?= (!empty($pedidoDetalhes) && (int) ($pedidoDetalhes['admin_criador_id'] ?? 0) === $vid) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) ($v['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($v['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label mb-1">Mensagem</label>
                            <textarea class="form-control" name="mensagem" rows="2" required placeholder="Digite a mensagem para o vendedor..."></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-paper-plane me-1"></i>Enviar
                            </button>
                        </div>
                    </div>
                </form>
                <div class="form-text mt-2">Esta ação fica registrada em <strong>Anotações internas</strong>.</div>
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
                <span class="badge <?= ($st === 'open' ? 'bg-success' : 'bg-secondary') ?>"><?= $st === 'open' ? 'Aberto' : 'Fechado' ?></span>
                <?php if (!empty($ticket['pedido_id'])): ?>
                    <div class="brz-sub">Pedido #<?= (int) ($ticket['pedido_id'] ?? 0) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="brz-chat-body">
            <?php if (empty($messages)): ?>
                <div class="text-muted">Sem mensagens ainda.</div>
            <?php else: ?>
                <?php foreach ($messages as $m): ?>
                    <?php $isAdmin = ((string) ($m['autor_tipo'] ?? '')) === 'admin'; ?>
                    <div class="brz-row <?= $isAdmin ? 'me' : 'other' ?>">
                        <div class="brz-bubble <?= $isAdmin ? 'me' : 'other' ?>">
                            <div class="brz-meta">
                                <?php if ($isAdmin): ?>
                                    <?= htmlspecialchars((string) (($m['autor_nome'] ?? '') !== '' ? $m['autor_nome'] : 'Admin/Suporte'), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    Cliente
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
                <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/mensagem" enctype="multipart/form-data">
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
                            <button type="submit" class="btn btn-primary w-100" title="Enviar">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-secondary mb-0">Ticket fechado.</div>
            <?php endif; ?>
        </div>
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
                                    <td class="k">Código</td>
                                    <td class="v muted"><?= htmlspecialchars($codigoPedido, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php if ($moedaProc !== ''): ?>
                                    <tr>
                                        <td class="k">Moeda processada</td>
                                        <td class="v muted"><?= htmlspecialchars($moedaProc, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($moedaOrig !== ''): ?>
                                    <tr>
                                        <td class="k">Moeda original</td>
                                        <td class="v muted"><?= htmlspecialchars($moedaOrig, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($pd['status'])): ?>
                                    <tr>
                                        <td class="k">Status</td>
                                        <td class="v muted"><?= htmlspecialchars((string) ($pd['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($taxaConv !== null && $taxaConv !== '' && (float) $taxaConv != 0.0): ?>
                                    <tr>
                                        <td class="k">Taxa conversão</td>
                                        <td class="v muted"><?= htmlspecialchars((string) $taxaConv, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pedido-totais">
                        <?php if ($subtotal !== null && $subtotal !== '' && (float) $subtotal != 0.0): ?>
                            <div class="rowline"><div class="l">Subtotal</div><div class="r"><?= htmlspecialchars((string) $subtotal, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                        <?php if ($frete !== null && $frete !== '' && (float) $frete != 0.0): ?>
                            <div class="rowline"><div class="l">Frete</div><div class="r"><?= htmlspecialchars((string) $frete, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                        <?php if ($servico !== null && $servico !== '' && (float) $servico != 0.0): ?>
                            <div class="rowline"><div class="l">Taxas</div><div class="r"><?= htmlspecialchars((string) $servico, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                        <?php if ($impostos !== null && $impostos !== '' && (float) $impostos != 0.0): ?>
                            <div class="rowline"><div class="l">Impostos</div><div class="r"><?= htmlspecialchars((string) $impostos, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                        <?php if ($total !== null && $total !== '' && (float) $total != 0.0): ?>
                            <div class="rowline total"><div class="l">Total</div><div class="r"><?= htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') ?></div></div>
                        <?php endif; ?>
                    </div>
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
            <div class="fw-semibold">Arquivos do ticket</div>
        </div>
        <div class="card-body">
            <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/arquivos" enctype="multipart/form-data" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label mb-1">Enviar arquivo</label>
                        <input class="form-control" type="file" name="arquivo" required>
                        <div class="form-text">Até 20MB. Alguns tipos perigosos são bloqueados.</div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" title="Enviar arquivo">
                            <i class="fas fa-upload"></i>
                        </button>
                    </div>
                </div>
            </form>

            <?php if (empty($ticketFiles)): ?>
                <div class="text-muted">Nenhum arquivo anexado ao ticket.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Arquivo</th>
                                <th>Tipo</th>
                                <th>Tamanho</th>
                                <th>Data</th>
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
            <div class="fw-semibold">Anotações internas (somente admin)</div>
        </div>
        <div class="card-body">
            <form method="POST" action="/admin/tickets/<?= (int) ($ticket['id'] ?? 0) ?>/notas-internas">
                <div class="mb-2">
                    <textarea class="form-control" name="internal_notes" rows="4" placeholder="Detalhes internos sobre o pedido / tratativa / observações..." style="white-space: pre-wrap;" ><?= htmlspecialchars((string) ($ticket['internal_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i>Salvar
                    </button>
                </div>
            </form>
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
