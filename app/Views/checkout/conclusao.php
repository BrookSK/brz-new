<?php ob_start(); ?>
<div class="container py-5">
    <!-- Header de Sucesso -->
    <div class="text-center mb-5">
        <div class="success-icon mb-4">
            <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--primary-color);"></i>
        </div>
        <?php
        $statusPagamentoHeader = $pedido['payment_status'] ?? ($paymentDetails['status'] ?? null);
        if (is_string($statusPagamentoHeader)) {
            $statusPagamentoHeader = strtoupper($statusPagamentoHeader);
        }
        $isPagoHeader = !empty($statusPagamentoHeader) && in_array($statusPagamentoHeader, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true);
        ?>
        <h1 class="display-4 fw-bold" style="color: var(--primary-color);"><?= $isPagoHeader ? 'Pedido Confirmado!' : 'Pedido realizado!' ?></h1>
        <p class="lead text-muted"><?= $isPagoHeader ? 'Seu pedido foi processado com sucesso e está sendo preparado.' : 'Seu pedido foi criado. Finalize o pagamento para confirmar.' ?></p>
    </div>

    <!-- Resumo do Pedido -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-receipt"></i> Resumo do Pedido</h5>
                </div>
                <div class="card-body">
                    <?php $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? 'BRL'))); ?>
                    <?php $simboloMoeda = ($moedaPedido === 'USD') ? 'US$' : 'R$'; ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Número do Pedido:</strong>
                            <span class="text-primary"><?= $pedido['numero_pedido'] ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Data:</strong>
                            <span><?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Status:</strong>
                            <span class="badge" style="background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.35); color: rgba(124, 45, 18, 1);">Pendente</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Moeda:</strong>
                            <span><?= htmlspecialchars($moedaPedido) ?></span>
                        </div>
                    </div>

                    <!-- Itens do Pedido -->
                    <h6 class="mt-4 mb-3"><i class="fas fa-box"></i> Itens do Pedido</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th class="text-center">Qtd</th>
                                    <th class="text-end">Valor Unit.</th>
                                    <th class="text-end">Subtotal</th>
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
                            <h6><i class="fas fa-user"></i> Dados do Cliente</h6>
                            <p class="mb-1">
                                <strong>Nome:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?><br>
                                <strong>Email:</strong> <?= htmlspecialchars($pedido['cliente_email']) ?><br>
                                <strong>Telefone:</strong> <?= htmlspecialchars($pedido['cliente_telefone']) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-truck"></i> Endereço de Entrega</h6>
                            <p class="mb-1">
                                <?= htmlspecialchars($pedido['endereco']) ?>, <?= htmlspecialchars($pedido['numero']) ?><br>
                                <?= htmlspecialchars($pedido['bairro']) ?><br>
                                <?= htmlspecialchars($pedido['cidade']) ?> - <?= htmlspecialchars($pedido['estado']) ?><br>
                                CEP: <?= htmlspecialchars($pedido['cep']) ?>
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
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Valores</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span><?= $simboloMoeda ?> <?= number_format($pedido['subtotal'], 2, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Frete:</span>
                        <?php $freteVal = (float) ($pedido['frete'] ?? 0); ?>
                        <span><?= ($freteVal <= 0 ? 'Frete grátis' : ($simboloMoeda . ' ' . number_format($freteVal, 2, ',', '.'))) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Taxa de Serviço:</span>
                        <span><?= $simboloMoeda ?> <?= number_format($pedido['taxa_servico'], 2, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Impostos:</span>
                        <span><?= $simboloMoeda ?> <?= number_format($pedido['impostos'], 2, ',', '.') ?></span>
                    </div>
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
                    <h5 class="mb-0"><i class="fas fa-credit-card"></i> Pagamento</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1">
                        <strong>Forma:</strong> 
                        <?php
                        $formas = [
                            'cartao_credito' => 'Cartão de Crédito',
                            'boleto' => 'Boleto Bancário',
                            'pix' => 'PIX',
                        ];
                        $fp = (string) ($pedido['forma_pagamento'] ?? '');
                        echo $formas[$fp] ?? $fp;
                        ?>
                    </p>

                    <?php
                    $statusPagamento = $pedido['payment_status'] ?? ($paymentDetails['status'] ?? null);
                    if (is_string($statusPagamento)) {
                        $statusPagamento = strtoupper($statusPagamento);
                    }

                    $badgeClass = 'bg-warning text-dark';
                    $statusLabel = 'Aguardando';
                    $isPago = false;
                    if (!empty($statusPagamento)) {
                        if (in_array($statusPagamento, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                            $badgeClass = 'bg-success';
                            $statusLabel = 'Pago';
                            $isPago = true;
                        } elseif (in_array($statusPagamento, ['REJECTED', 'CANCELED', 'CANCELLED', 'DELETED'], true)) {
                            $badgeClass = 'bg-danger';
                            $statusLabel = 'Cancelado';
                        } elseif (in_array($statusPagamento, ['REFUNDED'], true)) {
                            $badgeClass = 'bg-secondary';
                            $statusLabel = 'Estornado';
                        }
                    }
                    ?>

                    <p class="mb-2 text-muted">
                        <small>Status do pagamento: <span class="badge" style="background: rgba(148, 163, 184, 0.18); border: 1px solid rgba(148, 163, 184, 0.35); color: rgba(15, 23, 42, 0.82);"><?= htmlspecialchars($statusLabel) ?></span></small>
                    </p>

                    <?php
                    $billingType = strtoupper((string) ($paymentDetails['billingType'] ?? ''));
                    $invoiceUrl = $paymentDetails['invoiceUrl'] ?? null;
                    $bankSlipUrl = $paymentDetails['bankSlipUrl'] ?? null;
                    $digitableLine = $paymentDetails['digitableLine'] ?? null;
                    ?>

                    <?php if (!$isPago && $billingType === 'PIX' && !empty($pixQrCode)): ?>
                        <?php $pixImage = $pixQrCode['encodedImage'] ?? null; ?>
                        <?php $pixPayload = $pixQrCode['payload'] ?? null; ?>
                        <?php if (!empty($pixImage)): ?>
                            <div class="text-center my-3">
                                <img src="data:image/png;base64,<?= $pixImage ?>" alt="QR Code PIX" style="max-width: 220px; width: 100%; height: auto;" />
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($pixPayload)): ?>
                            <div class="mb-2">
                                <strong>Copia e cola:</strong>
                                <div class="input-group">
                                    <input id="pix-payload" type="text" class="form-control" value="<?= htmlspecialchars($pixPayload) ?>" readonly onclick="this.select();" />
                                    <button type="button" class="btn btn-outline-dark" onclick="copiarPixPayload('pix-payload','pix-copied', this)">Copiar</button>
                                </div>
                                <div id="pix-copied" class="small text-success mt-1" style="display:none;">Copiado!</div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($billingType === 'BOLETO'): ?>
                        <?php if (!empty($bankSlipUrl) || !empty($invoiceUrl)): ?>
                            <p class="mb-2">
                                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($bankSlipUrl ?: $invoiceUrl) ?>" target="_blank" rel="noopener">Abrir boleto</a>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($digitableLine)): ?>
                            <div class="mb-2">
                                <strong>Linha digitável:</strong>
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
            <h5 class="mb-0"><i class="fas fa-clock"></i> Próximas Etapas</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-icon completed mb-2">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                        <h6>Pedido Confirmado</h6>
                        <p class="small text-muted">Seu pedido foi recebido e está sendo processado.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-icon pending mb-2">
                            <i class="fas fa-box text-warning"></i>
                        </div>
                        <h6>Preparação</h6>
                        <p class="small text-muted">Seus produtos estão sendo preparados para envio.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-icon pending mb-2">
                            <i class="fas fa-shipping-fast text-warning"></i>
                        </div>
                        <h6>Envio</h6>
                        <p class="small text-muted">Seu pedido será enviado em até 30 dias.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-icon pending mb-2">
                            <i class="fas fa-home text-warning"></i>
                        </div>
                        <h6>Entrega</h6>
                        <p class="small text-muted">Receba seu produto no endereço informado.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações -->
    <div class="text-center mt-5">
        <a href="/produtos" class="btn btn-primary btn-lg me-3">
            <i class="fas fa-shopping-bag"></i> Continuar Comprando
        </a>
        <a href="/meus-pedidos" class="btn btn-outline-primary btn-lg">
            <i class="fas fa-list"></i> Meus Pedidos
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

.step-icon.completed {
    color: #28a745;
}

.step-icon.pending {
    color: #ffc107;
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
            btn.innerText = "Copiado";
            setTimeout(() => { btn.innerText = old || "Copiar"; }, 1800);
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
