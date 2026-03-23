<?php ob_start(); ?>
<div class="container py-5">
    <!-- Header de Sucesso -->
    <div class="text-center mb-5">
        <div class="success-icon mb-4">
            <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--primary-color);"></i>
        </div>
        <?php
        $statusPagamentoHeader = $pedido['payment_status'] ?? ((is_array($paymentDetails) ? ($paymentDetails['status'] ?? null) : null));
        if (is_string($statusPagamentoHeader)) {
            $statusPagamentoHeader = strtoupper($statusPagamentoHeader);
        }
        $isPagoHeader = !empty($statusPagamentoHeader) && in_array($statusPagamentoHeader, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true);
        ?>
        <h1 class="display-4 fw-bold" style="color: var(--primary-color);"><?= $isPagoHeader ? __('checkout_done.order_confirmed', 'Pedido Confirmado!') : __('checkout_done.order_placed', 'Pedido realizado!') ?></h1>
        <p class="lead text-muted"><?= $isPagoHeader ? __('checkout_done.confirmed_subtitle', 'Seu pedido foi processado com sucesso e está sendo preparado.') : __('checkout_done.placed_subtitle', 'Seu pedido foi criado. Finalize o pagamento para confirmar.') ?></p>
    </div>

    <!-- Resumo do Pedido -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-receipt"></i> <?= __('checkout_done.order_summary', 'Resumo do Pedido') ?></h5>
                </div>
                <div class="card-body">
                    <?php $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? 'BRL'))); ?>
                    <?php $simboloMoeda = ($moedaPedido === 'USD') ? 'US$' : 'R$'; ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= __('checkout_done.order_number', 'Número do Pedido') ?>:</strong>
                            <span class="text-primary"><?= $pedido['numero_pedido'] ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong><?= __('checkout_done.date', 'Data') ?>:</strong>
                            <span><?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= __('checkout_done.status', 'Status') ?>:</strong>
                            <?php
                            $statusPagamentoResumo = $pedido['payment_status'] ?? ((is_array($paymentDetails) ? ($paymentDetails['status'] ?? null) : null));
                            if (is_string($statusPagamentoResumo)) {
                                $statusPagamentoResumo = strtoupper($statusPagamentoResumo);
                            }
                            $statusPedidoBadgeText = __('checkout_done.awaiting_payment', 'Aguardando pagamento');
                            $statusPedidoBadgeStyle = 'background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.35); color: rgba(124, 45, 18, 1);';
                            if (!empty($statusPagamentoResumo)) {
                                if (in_array($statusPagamentoResumo, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                                    $statusPedidoBadgeText = __('checkout_done.confirmed', 'Confirmado');
                                    $statusPedidoBadgeStyle = 'background: rgba(34, 197, 94, 0.14); border: 1px solid rgba(34, 197, 94, 0.35); color: rgba(20, 83, 45, 1);';
                                } elseif (in_array($statusPagamentoResumo, ['REJECTED', 'CANCELED', 'CANCELLED', 'DELETED'], true)) {
                                    $statusPedidoBadgeText = __('checkout_done.cancelled', 'Cancelado');
                                    $statusPedidoBadgeStyle = 'background: rgba(239, 68, 68, 0.14); border: 1px solid rgba(239, 68, 68, 0.35); color: rgba(127, 29, 29, 1);';
                                } elseif (in_array($statusPagamentoResumo, ['REFUNDED'], true)) {
                                    $statusPedidoBadgeText = __('checkout_done.refunded', 'Estornado');
                                    $statusPedidoBadgeStyle = 'background: rgba(148, 163, 184, 0.18); border: 1px solid rgba(148, 163, 184, 0.35); color: rgba(15, 23, 42, 0.82);';
                                }
                            }
                            ?>
                            <span class="badge" style="<?= $statusPedidoBadgeStyle ?>"><?= htmlspecialchars($statusPedidoBadgeText) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong><?= __('checkout_done.currency', 'Moeda') ?>:</strong>
                            <span><?= htmlspecialchars($moedaPedido) ?></span>
                        </div>
                    </div>

                    <!-- Itens do Pedido -->
                    <h6 class="mt-4 mb-3"><i class="fas fa-box"></i> <?= __('checkout_done.order_items', 'Itens do Pedido') ?></h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= __('checkout_done.product', 'Produto') ?></th>
                                    <th class="text-center"><?= __('checkout_done.qty', 'Qtd') ?></th>
                                    <th class="text-end"><?= __('checkout_done.unit_price', 'Valor Unit.') ?></th>
                                    <th class="text-end"><?= __('checkout_done.subtotal', 'Subtotal') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($item['nome']) ?>
                                        <?php if (!empty($item['variacao_descricao']) || !empty($item['variacao_label'])): ?>
                                            <div class="small text-muted"><?= htmlspecialchars((string) ($item['variacao_descricao'] ?? $item['variacao_label']), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $item['quantidade'] ?></td>
                                    <td class="text-end"><?= $simboloMoeda ?> <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                                    <td class="text-end"><?= $simboloMoeda ?> <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Valores -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user"></i> <?= __('checkout_done.customer_data', 'Dados do Cliente') ?></h6>
                            <p class="mb-1">
                                <strong><?= __('checkout_done.name', 'Nome') ?>:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?><br>
                                <strong><?= __('checkout_done.email', 'Email') ?>:</strong> <?= htmlspecialchars($pedido['cliente_email']) ?><br>
                                <strong><?= __('checkout_done.phone', 'Telefone') ?>:</strong> <?= htmlspecialchars($pedido['cliente_telefone']) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-truck"></i> <?= __('checkout_done.shipping_address', 'Endereço de Entrega') ?></h6>
                            <p class="mb-1">
                                <?= htmlspecialchars($pedido['endereco']) ?>, <?= htmlspecialchars($pedido['numero']) ?><br>
                                <?= htmlspecialchars($pedido['bairro']) ?><br>
                                <?= htmlspecialchars($pedido['cidade']) ?> - <?= htmlspecialchars($pedido['estado']) ?><br>
                                <?= __('auth.zip', 'CEP') ?>: <?= htmlspecialchars($pedido['cep']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Valores Totais -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> <?= __('checkout_done.values', 'Valores') ?></h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= __('checkout_done.subtotal', 'Subtotal') ?>:</span>
                        <span><?= $simboloMoeda ?> <?= number_format($pedido['subtotal'], 2, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= __('checkout_done.shipping', 'Frete') ?>:</span>
                        <?php $freteVal = (float) ($pedido['frete'] ?? 0); ?>
                        <span><?= ($freteVal <= 0 ? __('cart.free_shipping', 'Frete grátis') : ($simboloMoeda . ' ' . number_format($freteVal, 2, ',', '.'))) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= __('checkout_done.service_fee', 'Taxa de Serviço') ?>:</span>
                        <span><?= $simboloMoeda ?> <?= number_format($pedido['taxa_servico'], 2, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= __('checkout_done.taxes_brazil', 'Impostos do Brasil') ?>:</span>
                        <span><?= $simboloMoeda ?> <?= number_format($pedido['impostos'], 2, ',', '.') ?></span>
                    </div>
                    <?php if (((float) ($pedido['imposto_local'] ?? 0)) > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= __('checkout_done.local_tax', 'Imposto local') ?>:</span>
                        <span><?= $simboloMoeda ?> <?= number_format((float) $pedido['imposto_local'], 2, ',', '.') ?></span>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total:</strong>
                        <strong style="color: var(--primary-color);"><?= $simboloMoeda ?> <?= number_format($pedido['total'], 2, ',', '.') ?></strong>
                    </div>
                </div>
            </div>

            <!-- Forma de Pagamento -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-credit-card"></i> <?= __('checkout_done.payment', 'Pagamento') ?></h5>
                </div>
                <div class="card-body">
                    <p class="mb-1">
                        <strong><?= __('checkout_done.method', 'Forma') ?>:</strong>
                        <?php
                        $formas = [
                            'carteira' => __('checkout.payment.wallet_credit', 'Carteira'),
                            'cartao_credito' => __('checkout.payment.credit_card', 'Cartão de Crédito'),
                            'cartao_debito' => __('checkout.payment.debit_card', 'Cartão de Débito'),
                            'boleto' => __('checkout.payment.boleto', 'Boleto Bancário'),
                            'pix' => 'PIX',
                        ];
                        $fp = (string) ($pedido['forma_pagamento'] ?? '');
                        echo $formas[$fp] ?? $fp;
                        ?>
                    </p>

                    <?php
                    $statusPagamento = $pedido['payment_status'] ?? ((is_array($paymentDetails) ? ($paymentDetails['status'] ?? null) : null));
                    if (is_string($statusPagamento)) {
                        $statusPagamento = strtoupper($statusPagamento);
                    }

                    $badgeClass = 'bg-warning text-dark';
                    $statusLabel = __('checkout_done.awaiting_payment', 'Aguardando');
                    $isPago = false;
                    if (!empty($statusPagamento)) {
                        if (in_array($statusPagamento, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                            $badgeClass = 'bg-success';
                            $statusLabel = __('checkout_done.paid', 'Pago');
                            $isPago = true;
                        } elseif (in_array($statusPagamento, ['REJECTED', 'CANCELED', 'CANCELLED', 'DELETED'], true)) {
                            $badgeClass = 'bg-danger';
                            $statusLabel = __('checkout_done.cancelled', 'Cancelado');
                        } elseif (in_array($statusPagamento, ['REFUNDED'], true)) {
                            $badgeClass = 'bg-secondary';
                            $statusLabel = __('checkout_done.refunded', 'Estornado');
                        }
                    }
                    ?>

                    <p class="mb-2 text-muted">
                        <small><?= __('checkout_done.payment_status', 'Status do pagamento') ?>: <span class="badge" style="background: rgba(148, 163, 184, 0.18); border: 1px solid rgba(148, 163, 184, 0.35); color: rgba(15, 23, 42, 0.82);"><?= htmlspecialchars($statusLabel) ?></span></small>
                    </p>

                    <?php
                    $splitPagamentos = (isset($splitPagamentos) && is_array($splitPagamentos)) ? $splitPagamentos : [];
                    $hasSplit = !empty($splitPagamentos);
                    $billingType = strtoupper((string) ((is_array($paymentDetails) ? ($paymentDetails['billingType'] ?? '') : '') ?: ($pedido['forma_pagamento'] ?? '')));
                    $invoiceUrl = (is_array($paymentDetails) ? ($paymentDetails['invoiceUrl'] ?? null) : null);
                    $bankSlipUrl = (is_array($paymentDetails) ? ($paymentDetails['bankSlipUrl'] ?? null) : null);
                    $digitableLine = (is_array($paymentDetails) ? ($paymentDetails['digitableLine'] ?? null) : null);

                    $splitProdutoStatus = '';
                    $splitTaxaStatus = '';
                    $splitImpostoStatus = '';
                    $splitPagoParcial = false;
                    if ($hasSplit) {
                        $pProduto = (isset($splitPagamentos['produto']) && is_array($splitPagamentos['produto'])) ? $splitPagamentos['produto'] : null;
                        $pTaxa = (isset($splitPagamentos['taxa_servico']) && is_array($splitPagamentos['taxa_servico'])) ? $splitPagamentos['taxa_servico'] : ((isset($splitPagamentos['taxa']) && is_array($splitPagamentos['taxa'])) ? $splitPagamentos['taxa'] : null);
                        $pImposto = (isset($splitPagamentos['imposto']) && is_array($splitPagamentos['imposto'])) ? $splitPagamentos['imposto'] : null;
                        $splitProdutoStatus = strtoupper(trim((string) (is_array($pProduto) ? ($pProduto['status'] ?? '') : '')));
                        $splitTaxaStatus = strtoupper(trim((string) (is_array($pTaxa) ? ($pTaxa['status'] ?? '') : '')));
                        $splitImpostoStatus = strtoupper(trim((string) (is_array($pImposto) ? ($pImposto['status'] ?? '') : '')));
                        $produtoOk = ($splitProdutoStatus !== '' && in_array($splitProdutoStatus, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true));
                        $taxaOk = ($splitTaxaStatus !== '' && in_array($splitTaxaStatus, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true));
                        $hasImposto = (!empty($pImposto) || $splitImpostoStatus !== '');
                        $impostoOk = (!$hasImposto) ? true : ($splitImpostoStatus !== '' && in_array($splitImpostoStatus, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true));

                        $totalOk = ($produtoOk ? 1 : 0) + ($taxaOk ? 1 : 0) + ($impostoOk ? 1 : 0);
                        $totalNeed = 2 + ($hasImposto ? 1 : 0);
                        $splitPagoParcial = ($totalOk > 0 && $totalOk < $totalNeed);
                    }
                    ?>

                    <?php if (!$isPago && $hasSplit && $splitPagoParcial): ?>
                        <div class="alert alert-warning" style="background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.35); color: rgba(124, 45, 18, 1);">
                            <strong><?= __('checkout_done.partial_payment_title', 'Pagamento parcial detectado') ?>.</strong>
                            <?= __('checkout_done.partial_payment_desc', 'Seu pedido continuará como pendente até que todos os pagamentos sejam concluídos (produtos, taxa de serviço e, se houver, impostos). Se você pagar apenas parte deles, o pedido não será confirmado.', []) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isPago && $hasSplit): ?>
                        <?php
                        $pProduto = $splitPagamentos['produto'] ?? null;
                        $pTaxa = $splitPagamentos['taxa_servico'] ?? ($splitPagamentos['taxa'] ?? null);
                        $pImposto = $splitPagamentos['imposto'] ?? null;

                        $renderSplitBox = function (string $titulo, ?array $row) {
                            if (empty($row)) {
                                return;
                            }

                            $url = (string) ($row['invoice_url'] ?? '');
                            $bank = (string) ($row['bank_slip_url'] ?? '');
                            $dig = (string) ($row['digitable_line'] ?? '');
                            $pixImg = (string) ($row['pix_encoded_image'] ?? '');
                            $pixPayload = (string) ($row['pix_payload'] ?? '');

                            $display = $url !== '' ? $url : ($pixPayload !== '' ? $pixPayload : ($bank !== '' ? $bank : $dig));
                            $openUrl = $url !== '' ? $url : ($bank !== '' ? $bank : '');

                            $boxId = 'split_' . md5($titulo . $display);
                            ?>
                            <div class="border rounded p-3 mb-3" style="background: rgba(148, 163, 184, 0.08);">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div><strong><?= htmlspecialchars($titulo) ?></strong></div>
                                    <div class="d-flex gap-2">
                                        <?php if ($openUrl !== ''): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($openUrl) ?>" target="_blank" rel="noopener"><?= __('checkout_done.open', 'Abrir') ?></a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="copiarPixPayload('<?= $boxId ?>','<?= $boxId ?>_copied', this)"><?= __('checkout_done.copy', 'Copiar') ?></button>
                                    </div>
                                </div>
                                <input id="<?= $boxId ?>" type="text" class="form-control mt-2" value="<?= htmlspecialchars($display) ?>" readonly onclick="this.select();" />
                                <div id="<?= $boxId ?>_copied" class="small text-success mt-1" style="display:none;"><?= __('checkout_done.copied', 'Copiado!') ?></div>

                                <?php if ($pixImg !== ''): ?>
                                    <?php
                                    $mime = 'image/png';
                                    try {
                                        $decoded = base64_decode($pixImg, true);
                                        if ($decoded !== false) {
                                            $head = ltrim(substr((string) $decoded, 0, 200));
                                            if ($head !== '' && (stripos($head, '<svg') !== false || stripos($head, '<?xml') !== false)) {
                                                $mime = 'image/svg+xml';
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        $mime = 'image/png';
                                    }
                                    ?>
                                    <div class="text-center my-3">
                                        <img src="data:<?= htmlspecialchars($mime) ?>;base64,<?= htmlspecialchars($pixImg) ?>" alt="QR Code PIX" style="max-width: 220px; width: 100%; height: auto;" />
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                        };
                        ?>

                        <?php $renderSplitBox('Pagamento 1: Produtos (Câmbio Real)', is_array($pProduto) ? $pProduto : null); ?>
                        <?php $renderSplitBox('Pagamento 2: Taxas e impostos (AppMax)', is_array($pTaxa) ? $pTaxa : null); ?>

                    <?php elseif (!$isPago && $billingType === 'PIX' && !empty($pixQrCode)): ?>
                        <?php $pixImage = $pixQrCode['encodedImage'] ?? null; ?>
                        <?php $pixPayload = $pixQrCode['payload'] ?? null; ?>
                        <?php if (!empty($pixImage)): ?>
                            <?php
                            $mime = 'image/png';
                            try {
                                $decoded = base64_decode((string) $pixImage, true);
                                if ($decoded !== false) {
                                    $head = ltrim(substr((string) $decoded, 0, 200));
                                    if ($head !== '' && (stripos($head, '<svg') !== false || stripos($head, '<?xml') !== false)) {
                                        $mime = 'image/svg+xml';
                                    }
                                }
                            } catch (\Exception $e) {
                                $mime = 'image/png';
                            }
                            ?>
                            <div class="text-center my-3">
                                <img src="data:<?= htmlspecialchars($mime) ?>;base64,<?= htmlspecialchars((string) $pixImage) ?>" alt="QR Code PIX" style="max-width: 220px; width: 100%; height: auto;" />
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($pixPayload)): ?>
                            <div class="mb-2">
                                <strong><?= __('checkout_done.copy_paste', 'Copia e cola') ?>:</strong>
                                <div class="input-group">
                                    <input id="pix-payload" type="text" class="form-control" value="<?= htmlspecialchars($pixPayload) ?>" readonly onclick="this.select();" />
                                    <button type="button" class="btn btn-outline-dark" onclick="copiarPixPayload('pix-payload','pix-copied', this)"><?= __('checkout_done.copy', 'Copiar') ?></button>
                                </div>
                                <div id="pix-copied" class="small text-success mt-1" style="display:none;"><?= __('checkout_done.copied', 'Copiado!') ?></div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($billingType === 'BOLETO'): ?>
                        <?php if (!empty($bankSlipUrl) || !empty($invoiceUrl)): ?>
                            <p class="mb-2">
                                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($bankSlipUrl ?: $invoiceUrl) ?>" target="_blank" rel="noopener"><?= __('checkout_done.open_boleto', 'Abrir boleto') ?></a>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($digitableLine)): ?>
                            <div class="mb-2">
                                <strong><?= __('checkout_done.digitable_line', 'Linha digitável') ?>:</strong>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($digitableLine) ?>" readonly onclick="this.select();" />
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline de Próximas Etapas -->
    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-clock"></i> <?= __('checkout_done.next_steps', 'Próximas Etapas') ?></h5>
        </div>
        <div class="card-body">
            <?php
            $billingTypeEtapas = strtoupper((string) ((is_array($paymentDetails) ? ($paymentDetails['billingType'] ?? '') : '') ?: ($pedido['forma_pagamento'] ?? '')));
            if ($billingTypeEtapas === 'CARTAO_CREDITO') {
                $billingTypeEtapas = 'CREDIT_CARD';
            }

            $hasInvoiceLink = (is_array($paymentDetails) && (!empty($paymentDetails['invoiceUrl']) || !empty($paymentDetails['bankSlipUrl'])));
            $rotuloCobranca = __('checkout_done.billing.default', 'cobrança');
            if ($billingTypeEtapas === 'PIX') {
                $rotuloCobranca = __('checkout_done.billing.pix', 'código PIX');
            } elseif ($billingTypeEtapas === 'BOLETO') {
                $rotuloCobranca = __('checkout_done.billing.boleto', 'boleto');
            } elseif ($billingTypeEtapas === 'CREDIT_CARD') {
                $rotuloCobranca = __('checkout_done.billing.payment_link', 'link de pagamento');
            } elseif ($billingTypeEtapas === 'CARTEIRA' || $billingTypeEtapas === 'WALLET') {
                $rotuloCobranca = __('checkout_done.billing.wallet', 'carteira');
            }

            $stepDoneClass = 'text-success';
            $stepPendingClass = 'text-warning';
            $stepCurrentClass = 'text-primary';

            $steps = [];
            if ($isPago) {
                $steps[] = [
                    'icon' => 'fa-check-circle',
                    'iconClass' => $stepDoneClass,
                    'title' => __('checkout_done.step_payment_confirmed', 'Pagamento confirmado'),
                    'desc' => __('checkout_done.step_payment_confirmed_desc', 'Recebemos a confirmação do pagamento e seu pedido entrou em fila de processamento.'),
                ];
                $steps[] = [
                    'icon' => 'fa-box',
                    'iconClass' => $stepCurrentClass,
                    'title' => __('checkout_done.step_preparing', 'Preparação'),
                    'desc' => __('checkout_done.step_preparing_desc', 'Sua compra está sendo separada e preparada para envio.'),
                ];
                $steps[] = [
                    'icon' => 'fa-shipping-fast',
                    'iconClass' => $stepPendingClass,
                    'title' => __('checkout_done.step_shipping', 'Envio'),
                    'desc' => __('checkout_done.step_shipping_desc', 'Assim que o pedido for postado, você receberá atualizações.'),
                ];
                $steps[] = [
                    'icon' => 'fa-home',
                    'iconClass' => $stepPendingClass,
                    'title' => __('checkout_done.step_delivery', 'Entrega'),
                    'desc' => __('checkout_done.step_delivery_desc', 'Entrega no endereço informado no checkout.'),
                ];
            } else {
                $steps[] = [
                    'icon' => 'fa-check-circle',
                    'iconClass' => $stepDoneClass,
                    'title' => __('checkout_done.step_order_created', 'Pedido criado'),
                    'desc' => __('checkout_done.step_order_created_desc', 'Seu pedido foi registrado. O próximo passo é finalizar o pagamento para confirmar.'),
                ];

                $descPagamento = __('checkout_done.step_awaiting_payment_desc', 'Finalize o pagamento para confirmar o pedido.');
                if ($hasInvoiceLink) {
                    $descPagamento = __('checkout_done.wait_for_billing', 'Aguarde a geração da {billing} e finalize o pagamento para confirmar o pedido.', ['billing' => $rotuloCobranca]);
                } elseif ($billingTypeEtapas !== '') {
                    $descPagamento = __('checkout_done.pay_via_billing', 'Finalize o pagamento via {billing} para confirmar o pedido.', ['billing' => $rotuloCobranca]);
                }

                $steps[] = [
                    'icon' => 'fa-file-invoice-dollar',
                    'iconClass' => $stepCurrentClass,
                    'title' => __('checkout_done.step_awaiting_payment', 'Aguardando pagamento'),
                    'desc' => $descPagamento,
                ];
                $steps[] = [
                    'icon' => 'fa-box',
                    'iconClass' => $stepPendingClass,
                    'title' => __('checkout_done.step_preparing', 'Preparação'),
                    'desc' => __('checkout_done.step_preparing_after_pay_desc', 'Após a confirmação do pagamento, iniciamos a preparação do seu pedido.'),
                ];
                $steps[] = [
                    'icon' => 'fa-shipping-fast',
                    'iconClass' => $stepPendingClass,
                    'title' => __('checkout_done.step_shipping', 'Envio'),
                    'desc' => __('checkout_done.step_shipping_after_prep_desc', 'Após a preparação, o pedido é enviado e você recebe atualizações.'),
                ];
                $steps[] = [
                    'icon' => 'fa-home',
                    'iconClass' => $stepPendingClass,
                    'title' => __('checkout_done.step_delivery', 'Entrega'),
                    'desc' => __('checkout_done.step_delivery_desc', 'Entrega no endereço informado no checkout.'),
                ];
            }

            $stepsChunks = array_chunk($steps, 4);
            ?>

            <?php foreach ($stepsChunks as $chunk): ?>
            <div class="row">
                <?php foreach ($chunk as $step): ?>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-icon mb-2">
                            <i class="fas <?= htmlspecialchars($step['icon']) ?> <?= htmlspecialchars($step['iconClass']) ?>"></i>
                        </div>
                        <h6><?= htmlspecialchars($step['title']) ?></h6>
                        <p class="small text-muted"><?= htmlspecialchars($step['desc']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Ações -->
    <div class="text-center mt-5">
        <a href="/produtos" class="btn btn-primary btn-lg me-3">
            <i class="fas fa-shopping-bag"></i> <?= __('checkout_done.actions_continue_shopping', 'Continuar Comprando') ?>
        </a>
        <a href="/meus-pedidos" class="btn btn-outline-primary btn-lg">
            <i class="fas fa-list"></i> <?= __('checkout_done.actions_my_orders', 'Meus Pedidos') ?>
        </a>
    </div>
</div>

<style>
.success-icon {
    animation: none;
}

.step-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.card {
    border: none;
    border-radius: 10px;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}
</style>

<script>
function copiarPixPayload(inputId, msgId, btn) {
    const el = document.getElementById(inputId);
    const msg = document.getElementById(msgId);
    if (!el) return;
    const txt = el.value || el.textContent || "";
    if (!txt) return;
    const old = btn ? btn.innerText : "";

    const ok = () => {
        if (msg) {
            msg.style.display = "block";
            setTimeout(() => { msg.style.display = "none"; }, 1800);
        }
        if (btn) {
            btn.innerText = <?= json_encode(__('checkout_done.copied', 'Copiado!'), JSON_UNESCAPED_UNICODE) ?>;
            setTimeout(() => { btn.innerText = old || <?= json_encode(__('checkout_done.copy', 'Copiar'), JSON_UNESCAPED_UNICODE) ?>; }, 1800);
        }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(ok).catch(() => {
            el.focus();
            el.select();
            try { document.execCommand("copy"); ok(); } catch (e) {}
        });
        return;
    }
    el.focus();
    el.select();
    try { document.execCommand("copy"); ok(); } catch (e) {}
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
