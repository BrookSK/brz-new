<?php $title = __('admin.installment.detail_title', 'Detalhe Carnê #{id} - Admin', ['id' => $carne['id']]); ?>
<?php ob_start(); ?>

<div class="container-fluid">
    <a href="/admin/carnes" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> <?= __('admin.installment.back', 'Voltar') ?></a>
    <a href="/admin/pedidos/detalhes/<?= (int) $carne['pedido_id'] ?>" class="btn btn-sm btn-outline-primary mb-3 ms-2" target="_blank"><i class="fas fa-external-link-alt me-1"></i> <?= __('admin.installment.open_order', 'Abrir Pedido #{id}', ['id' => (int) $carne['pedido_id']]) ?></a>

    <div class="row">
        <!-- Info Principal -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= __('admin.installment.plan_order_heading', 'Carnê #{cid} — Pedido #{pid}', ['cid' => $carne['id'], 'pid' => $carne['pedido_id']]) ?></h5>
                    <span class="badge bg-<?= $carne['status'] === 'quitado' ? 'success' : ($carne['status'] === 'com_atraso' ? 'danger' : 'primary') ?> fs-6">
                        <?= ucfirst(str_replace('_', ' ', $carne['status'])) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p><strong><?= __('admin.installment.customer', 'Cliente:') ?></strong> <?= htmlspecialchars($carne['cliente_nome']) ?></p>
                            <p><strong><?= __('admin.installment.email', 'Email:') ?></strong> <?= htmlspecialchars($carne['cliente_email']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong><?= __('admin.installment.total_label', 'Total:') ?></strong> R$ <?= number_format($carne['total_geral'], 2, ',', '.') ?></p>
                            <p><strong><?= __('admin.installment.products_label', 'Produtos:') ?></strong> R$ <?= number_format($carne['total_produtos'], 2, ',', '.') ?></p>
                            <p><strong><?= __('admin.installment.fees_label', 'Taxas:') ?></strong> R$ <?= number_format($carne['total_taxas'], 2, ',', '.') ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong><?= __('admin.installment.installments_label', 'Parcelas:') ?></strong> <?= $carne['quantidade_parcelas'] ?>x</p>
                            <p><strong><?= __('admin.installment.internal_purchase', 'Compra Interna:') ?></strong> <?= $carne['compra_interna_liberada'] ? '<span class="badge bg-success">' . __('admin.installment.released_female', 'Liberada') . '</span>' : '<span class="badge bg-secondary">' . __('admin.installment.awaiting', 'Aguardando') . '</span>' ?></p>
                            <p><strong><?= __('admin.installment.shipping_label', 'Envio:') ?></strong> <?= $carne['envio_liberado'] ? '<span class="badge bg-success">' . __('admin.installment.released', 'Liberado') . '</span>' : '<span class="badge bg-secondary">' . __('admin.installment.blocked', 'Bloqueado') . '</span>' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parcelas -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><?= __('admin.installment.installments', 'Parcelas') ?></h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>#</th><th><?= __('admin.installment.col_due_date', 'Vencimento') ?></th><th><?= __('admin.installment.col_value', 'Valor') ?></th><th class="d-none d-lg-table-cell"><?= __('admin.installment.col_products_exchange', 'Prod. (Câmbio)') ?></th><th class="d-none d-lg-table-cell"><?= __('admin.installment.col_fee_cr', 'Taxa (CR Taxas)') ?></th><th><?= __('admin.installment.col_status', 'Status') ?></th><th><?= __('admin.installment.col_actions', 'Ações') ?></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($carne['parcelas'] as $p): ?>
                                <tr>
                                    <td><?= $p['numero_parcela'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
                                    <td>R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                                    <td class="d-none d-lg-table-cell"><span class="badge bg-<?= $p['boleto_produtos_pago'] ? 'success' : 'warning' ?>">R$ <?= number_format($p['valor_produtos'], 2, ',', '.') ?> <?= $p['boleto_produtos_pago'] ? '✓' : '⏳' ?></span></td>
                                    <td class="d-none d-lg-table-cell"><span class="badge bg-<?= $p['boleto_taxas_pago'] ? 'success' : 'warning' ?>">R$ <?= number_format($p['valor_taxas'], 2, ',', '.') ?> <?= $p['boleto_taxas_pago'] ? '✓' : '⏳' ?></span></td>
                                    <td><span class="badge bg-<?= $p['status'] === 'paga' ? 'success' : ($p['status'] === 'em_atraso' ? 'danger' : 'secondary') ?>"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span></td>
                                    <td>
                                        <form method="POST" action="/admin/carnes/reemitir-boleto/<?= $p['id'] ?>" class="d-inline"><button type="submit" class="btn btn-sm btn-outline-warning" title="<?= htmlspecialchars(__('admin.installment.reissue', 'Reemitir'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-redo"></i></button></form>
                                        <?php if (in_array($p['status'], ['pendente', 'aguardando_pagamento', 'vencida', 'em_atraso'])): ?>
                                        <form method="POST" action="/admin/carnes/enviar-cobranca/<?= $p['id'] ?>" class="d-inline ms-1" onsubmit="return confirm('<?= htmlspecialchars(__('admin.installment.confirm_send_charge_installment', 'Enviar email de cobrança para esta parcela?'), ENT_QUOTES, 'UTF-8') ?>')"><button type="submit" class="btn btn-sm btn-outline-info" title="<?= htmlspecialchars(__('admin.installment.send_charge_email', 'Enviar cobrança por email'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-paper-plane"></i></button></form>
                                        <?php endif; ?>
                                        <?php if ($p['status'] !== 'paga'): ?>
                                        <div class="btn-group btn-group-sm ms-1">
                                            <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="<?= htmlspecialchars(__('admin.installment.mark_as_paid', 'Marcar como pago'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-check"></i></button>
                                            <ul class="dropdown-menu">
                                                <?php if (!$p['boleto_produtos_pago']): ?>
                                                <li><form method="POST" action="/admin/carnes/marcar-parcela-paga/<?= $p['id'] ?>"><input type="hidden" name="tipo" value="produtos"><button type="submit" class="dropdown-item" onclick="return confirm('<?= htmlspecialchars(__('admin.installment.confirm_mark_products_paid', 'Marcar PRODUTOS como pago?'), ENT_QUOTES, 'UTF-8') ?>')"><i class="fas fa-box me-1"></i> <?= __('admin.installment.products', 'Produtos') ?></button></form></li>
                                                <?php endif; ?>
                                                <?php if (!$p['boleto_taxas_pago']): ?>
                                                <li><form method="POST" action="/admin/carnes/marcar-parcela-paga/<?= $p['id'] ?>"><input type="hidden" name="tipo" value="taxas"><button type="submit" class="dropdown-item" onclick="return confirm('<?= htmlspecialchars(__('admin.installment.confirm_mark_fees_paid', 'Marcar TAXAS como pago?'), ENT_QUOTES, 'UTF-8') ?>')"><i class="fas fa-receipt me-1"></i> <?= __('admin.installment.fees', 'Taxas') ?></button></form></li>
                                                <?php endif; ?>
                                                <li><form method="POST" action="/admin/carnes/marcar-parcela-paga/<?= $p['id'] ?>"><input type="hidden" name="tipo" value="ambos"><button type="submit" class="dropdown-item" onclick="return confirm('<?= htmlspecialchars(__('admin.installment.confirm_mark_both_paid', 'Marcar AMBOS como pago?'), ENT_QUOTES, 'UTF-8') ?>')"><i class="fas fa-check-double me-1"></i> <?= __('admin.installment.both', 'Ambos') ?></button></form></li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Produtos do Pedido -->
            <?php if (!empty($itensPedido)): ?>
            <?php
            // Carnê é sempre em BRL — os itens no banco estão em USD, precisam converter
            $taxaConvCarne = (float) ($pedido['taxa_conversao'] ?? 0);
            if ($taxaConvCarne <= 1.01) {
                try {
                    $db2 = \Config\Database::getConnection();
                    $stTx = $db2->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                    $stTx->execute();
                    $txV = (float) ($stTx->fetchColumn() ?: 0);
                    if ($txV > 1.01) $taxaConvCarne = $txV;
                } catch (\Exception $e) {}
            }
            if ($taxaConvCarne <= 1.01) $taxaConvCarne = \App\Core\ExchangeRate::getUsdToBrl();

            // Verificar se este carnê usou preço original (flag salva na criação)
            $carneUsouPrecoOriginal = false;
            $pedidoIdMeta = (int) ($carne['pedido_id'] ?? 0);
            if ($pedidoIdMeta > 0) {
                try {
                    $db = \Config\Database::getConnection();
                    $stMeta = $db->prepare("SELECT meta_value FROM pedido_meta WHERE pedido_id = ? AND meta_key = 'carne_usou_preco_original' LIMIT 1");
                    $stMeta->execute([$pedidoIdMeta]);
                    $carneUsouPrecoOriginal = ((string) $stMeta->fetchColumn() === '1');
                } catch (\Exception $e) {}
            }
            ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-box-open me-2"></i><?= __('admin.installment.order_products', 'Produtos do Pedido') ?></h6></div>
                <div class="card-body p-0">
                    <?php if ($carneUsouPrecoOriginal): ?>
                    <div class="alert alert-info small m-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Carnê Braziliana:</strong> <?= __('admin.installment.original_price_notice', 'Os produtos foram cobrados pelo valor original (sem promoção), pois promoções podem não estar vigentes durante todo o período de parcelamento.') ?>
                    </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th><?= __('admin.installment.col_product', 'Produto') ?></th><th class="text-center"><?= __('admin.installment.col_qty', 'Qtd') ?></th><th class="text-end"><?= __('admin.installment.col_unit_price', 'Preço Unit.') ?></th><th class="text-end"><?= __('admin.installment.col_subtotal', 'Subtotal') ?></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($itensPedido as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($item['produto_imagem'])): ?>
                                                <img src="<?= htmlspecialchars($item['produto_imagem']) ?>" class="rounded border" style="width:40px;height:40px;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                                <div class="rounded bg-light align-items-center justify-content-center" style="width:40px;height:40px;display:none;"><i class="fas fa-image text-muted"></i></div>
                                            <?php else: ?>
                                                <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fas fa-image text-muted"></i></div>
                                            <?php endif; ?>
                                            <span class="fw-semibold small"><?= htmlspecialchars($item['nome_display'] ?? __('admin.installment.product', 'Produto')) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= (int)($item['quantidade'] ?? 1) ?></td>
                                    <?php
                                    $puCarne = (float) ($item['preco_unitario'] ?? 0);
                                    $stCarne = (float) ($item['subtotal'] ?? 0);
                                    // Itens estão em USD no banco — converter para BRL
                                    if ($taxaConvCarne > 1.01 && $puCarne > 0 && $puCarne < 500) {
                                        $puCarne = round($puCarne * $taxaConvCarne, 2);
                                        $stCarne = round($stCarne * $taxaConvCarne, 2);
                                    }
                                    ?>
                                    <td class="text-end"><?= $puCarne > 0 ? 'R$ ' . number_format($puCarne, 2, ',', '.') : '-' ?></td>
                                    <td class="text-end"><?= $stCarne > 0 ? 'R$ ' . number_format($stCarne, 2, ',', '.') : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Ações -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><?= __('admin.installment.actions', 'Ações') ?></h6></div>
                <div class="card-body">
                    <?php if ($carne['status'] === 'quitado' && !$carne['envio_liberado']): ?>
                        <form method="POST" action="/admin/carnes/liberar-envio/<?= $carne['id'] ?>" class="mb-2">
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-truck"></i> <?= __('admin.installment.release_shipping', 'Liberar Envio') ?></button>
                        </form>
                    <?php endif; ?>

                    <?php if ($compraInterna): ?>
                        <?php if ($compraInterna['status'] === 'aguardando_compra'): ?>
                            <form method="POST" action="/admin/carnes/marcar-comprado/<?= $compraInterna['id'] ?>" class="mb-2">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-shopping-cart"></i> <?= __('admin.installment.mark_purchased', 'Marcar Comprado') ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($compraInterna['status'] === 'comprado'): ?>
                            <form method="POST" action="/admin/carnes/marcar-recebido/<?= $compraInterna['id'] ?>" class="mb-2">
                                <button type="submit" class="btn btn-info w-100"><i class="fas fa-box"></i> <?= __('admin.installment.mark_received', 'Marcar Recebido') ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <button class="btn btn-outline-danger w-100 mb-2" data-bs-toggle="collapse" data-bs-target="#prodIndisponivel">
                        <i class="fas fa-exclamation-triangle"></i> <?= __('admin.installment.product_unavailable', 'Produto Indisponível') ?>
                    </button>
                    <div class="collapse" id="prodIndisponivel">
                        <div class="border rounded p-2 mt-2">
                            <!-- Tabs -->
                            <ul class="nav nav-tabs nav-fill mb-2" role="tablist">
                                <li class="nav-item"><a class="nav-link active small" data-bs-toggle="tab" href="#tab-credito"><?= __('admin.installment.wallet_credit', 'Crédito Carteira') ?></a></li>
                                <li class="nav-item"><a class="nav-link small" data-bs-toggle="tab" href="#tab-diferenca"><?= __('admin.installment.charge_difference', 'Cobrar Diferença') ?></a></li>
                            </ul>
                            <div class="tab-content">
                                <!-- Tab Crédito -->
                                <div class="tab-pane active" id="tab-credito">
                                    <form method="POST" action="/admin/carnes/produto-indisponivel/<?= $carne['id'] ?>">
                                        <input type="hidden" name="acao" value="credito_carteira">
                                        <div class="mb-2">
                                            <label class="form-label small"><?= __('admin.installment.value_brl', 'Valor (R$)') ?></label>
                                            <input type="number" name="valor" step="0.01" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small"><?= __('admin.installment.notes', 'Observações') ?></label>
                                            <textarea name="observacoes" class="form-control form-control-sm" rows="2"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-warning w-100"><i class="fas fa-wallet me-1"></i><?= __('admin.installment.generate_credit', 'Gerar Crédito') ?></button>
                                    </form>
                                </div>
                                <!-- Tab Diferença -->
                                <div class="tab-pane" id="tab-diferenca">
                                    <div class="mb-2">
                                        <label class="form-label small"><?= __('admin.installment.difference_value_brl', 'Valor da diferença (R$)') ?></label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" id="diferenca-valor" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small"><?= __('admin.installment.notes', 'Observações') ?></label>
                                        <textarea class="form-control form-control-sm" rows="2" id="diferenca-obs"></textarea>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary w-100" id="btn-gerar-diferenca" onclick="gerarLinkDiferenca()">
                                        <i class="fas fa-link me-1"></i><?= __('admin.installment.generate_payment_link', 'Gerar Link de Pagamento') ?>
                                    </button>
                                    <div id="diferenca-resultado" class="mt-2" style="display:none;">
                                        <div class="alert alert-success small py-2 mb-1">
                                            <i class="fas fa-check-circle me-1"></i><?= __('admin.installment.link_generated_success', 'Link gerado com sucesso!') ?>
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control bg-light" readonly id="diferenca-link-url">
                                            <button class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('diferenca-link-url').value);this.innerHTML='<?= htmlspecialchars(__('admin.installment.copied', '✓ Copiado'), ENT_QUOTES, 'UTF-8') ?>'">
                                                <i class="fas fa-copy"></i> <?= __('admin.installment.copy', 'Copiar') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    function gerarLinkDiferenca() {
                        const btn = document.getElementById('btn-gerar-diferenca');
                        const valor = parseFloat(document.getElementById('diferenca-valor').value || 0);
                        const obs = document.getElementById('diferenca-obs').value || '';
                        if (valor <= 0) { alert('<?= htmlspecialchars(__('admin.installment.js_inform_difference_value', 'Informe o valor da diferença'), ENT_QUOTES, 'UTF-8') ?>'); return; }
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i><?= htmlspecialchars(__('admin.installment.js_generating', 'Gerando...'), ENT_QUOTES, 'UTF-8') ?>';
                        fetch('/admin/carnes/gerar-link-diferenca/<?= $carne['id'] ?>', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({valor: valor, observacoes: obs})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('diferenca-resultado').style.display = 'block';
                                var linkUrl = data.link_url || '';
                                if (linkUrl && !linkUrl.startsWith('http')) {
                                    linkUrl = window.location.origin + linkUrl;
                                }
                                document.getElementById('diferenca-link-url').value = linkUrl;
                                btn.innerHTML = '<i class="fas fa-check me-1"></i><?= htmlspecialchars(__('admin.installment.js_link_generated', 'Link Gerado'), ENT_QUOTES, 'UTF-8') ?>';
                            } else {
                                alert(data.error || '<?= htmlspecialchars(__('admin.installment.js_error_generating_link', 'Erro ao gerar link'), ENT_QUOTES, 'UTF-8') ?>');
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-link me-1"></i><?= htmlspecialchars(__('admin.installment.generate_payment_link', 'Gerar Link de Pagamento'), ENT_QUOTES, 'UTF-8') ?>';
                            }
                        })
                        .catch(() => {
                            alert('<?= htmlspecialchars(__('admin.installment.js_connection_error', 'Erro de conexão'), ENT_QUOTES, 'UTF-8') ?>');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-link me-1"></i><?= htmlspecialchars(__('admin.installment.generate_payment_link', 'Gerar Link de Pagamento'), ENT_QUOTES, 'UTF-8') ?>';
                        });
                    }
                    </script>

                    <form method="POST" action="/admin/carnes/reenviar-notificacao/<?= $carne['id'] ?>" class="mt-2">
                        <div class="input-group input-group-sm">
                            <select name="evento" class="form-select form-select-sm">
                                <option value="carne_criado"><?= __('admin.installment.event_plan_created', 'Carnê Criado') ?></option>
                                <option value="parcela_paga"><?= __('admin.installment.event_installment_paid', 'Parcela Paga') ?></option>
                                <option value="carne_quitado"><?= __('admin.installment.event_plan_paid_off', 'Carnê Quitado') ?></option>
                                <option value="envio_liberado"><?= __('admin.installment.event_shipping_released', 'Envio Liberado') ?></option>
                            </select>
                            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($compraInterna): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><?= __('admin.installment.internal_purchase_title', 'Compra Interna') ?></h6></div>
                <div class="card-body">
                    <p><strong><?= __('admin.installment.status_label', 'Status:') ?></strong> <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $compraInterna['status'])) ?></span></p>
                    <?php if ($compraInterna['comprado_em']): ?><p><strong><?= __('admin.installment.purchased_at', 'Comprado em:') ?></strong> <?= date('d/m/Y H:i', strtotime($compraInterna['comprado_em'])) ?></p><?php endif; ?>
                    <?php if ($compraInterna['recebido_em']): ?><p><strong><?= __('admin.installment.received_at', 'Recebido em:') ?></strong> <?= date('d/m/Y H:i', strtotime($compraInterna['recebido_em'])) ?></p><?php endif; ?>
                    <?php if ($compraInterna['produto_indisponivel']): ?><div class="alert alert-danger small"><?= __('admin.installment.product_unavailable_action', 'Produto indisponível — Ação: {a}', ['a' => $compraInterna['acao_indisponibilidade']]) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><?= __('admin.installment.history', 'Histórico') ?></h6></div>
                <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($historico as $h): ?>
                        <li class="list-group-item small">
                            <strong><?= date('d/m H:i', strtotime($h['created_at'])) ?></strong> — <?= htmlspecialchars($h['descricao']) ?>
                            <?php if (!empty($h['usuario_nome'])): ?><br><span class="text-muted"><?= __('admin.installment.by', 'por') ?> <?= htmlspecialchars($h['usuario_nome']) ?></span><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header"><h6 class="mb-0"><?= __('admin.installment.notifications_sent', 'Notificações Enviadas') ?></h6></div>
                <div class="card-body p-0" style="max-height: 250px; overflow-y: auto;">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($notificacoes as $n): ?>
                        <li class="list-group-item small">
                            <span class="badge bg-<?= $n['status'] === 'enviado' ? 'success' : ($n['status'] === 'erro' ? 'danger' : 'warning') ?>"><?= $n['canal'] ?></span>
                            <?= htmlspecialchars($n['evento']) ?> — <?= date('d/m H:i', strtotime($n['created_at'])) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
