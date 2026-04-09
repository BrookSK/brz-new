<?php $title = 'Detalhe do Carnê - Carnê Braziliana'; ?>
<?php ob_start(); ?>

<div class="container py-4">
    <a href="/meus-carnes" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left"></i> Voltar</a>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Carnê #<?= $carne['id'] ?> — Pedido #<?= $carne['pedido_id'] ?></h5>
            <span class="badge bg-light text-dark"><?= ucfirst(str_replace('_', ' ', $carne['status'])) ?></span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <p><strong>Total:</strong> R$ <?= number_format($carne['total_geral'], 2, ',', '.') ?></p>
                    <p><strong>Produtos:</strong> R$ <?= number_format($carne['total_produtos'], 2, ',', '.') ?></p>
                    <p><strong>Taxas:</strong> R$ <?= number_format($carne['total_taxas'], 2, ',', '.') ?></p>
                </div>
                <div class="col-md-4">
                    <p><strong>Parcelas:</strong> <?= $carne['quantidade_parcelas'] ?>x</p>
                    <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($carne['created_at'])) ?></p>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-exclamation-triangle"></i> O envio ocorre somente após a quitação total.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parcelas -->
    <?php foreach ($carne['parcelas'] as $p): ?>
    <?php
        $isPix = (($p['metodo_pagamento'] ?? '') === 'pix');
        $statusColors = ['paga'=>'success','parcialmente_paga'=>'warning','aguardando_pagamento'=>'info','pendente'=>'secondary','vencida'=>'danger','em_atraso'=>'danger','reemitida'=>'primary'];
        $cor = $statusColors[$p['status']] ?? 'secondary';
        $paga = ($p['status'] === 'paga');
    ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                Parcela <?= $p['numero_parcela'] ?> — <?= $isPix ? '<i class="fas fa-qrcode text-success"></i> PIX' : '<i class="fas fa-barcode"></i> Boleto' ?>
            </h6>
            <div>
                <span class="badge bg-<?= $cor ?>"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span>
                <span class="text-muted small ms-2">Venc: <?= date('d/m/Y', strtotime($p['vencimento'])) ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Produtos -->
                <div class="col-md-6 mb-3">
                    <div class="border rounded p-3 text-center">
                        <h6 class="small text-muted">Produtos (Câmbio Real)</h6>
                        <p class="fs-5 fw-bold mb-1">R$ <?= number_format($p['valor_produtos'], 2, ',', '.') ?></p>
                        <span class="badge bg-<?= $p['boleto_produtos_pago'] ? 'success' : 'warning' ?> mb-2"><?= $p['boleto_produtos_pago'] ? '✓ Pago' : '⏳ Pendente' ?></span>

                        <?php if (!$paga): ?>
                            <?php if ($isPix && !empty($p['pix_produtos_payload'])): ?>
                                <?php if (!empty($p['pix_produtos_qrcode'])): ?>
                                    <div class="mb-2"><img src="data:image/png;base64,<?= htmlspecialchars($p['pix_produtos_qrcode']) ?>" alt="QR" style="max-width:160px;" class="img-fluid"></div>
                                <?php endif; ?>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($p['pix_produtos_payload']) ?>" id="pix-prod-<?= $p['id'] ?>">
                                    <button class="btn btn-outline-success btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('pix-prod-<?= $p['id'] ?>').value);this.innerHTML='✓'"><i class="fas fa-copy"></i></button>
                                </div>
                            <?php elseif (!empty($p['boleto_produtos_url'])): ?>
                                <a href="<?= htmlspecialchars($p['boleto_produtos_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-barcode"></i> Ver Boleto</a>
                            <?php elseif (!empty($p['boleto_produtos_codigo']) && strpos($p['boleto_produtos_codigo'], 'Valor abaixo') === false): ?>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($p['boleto_produtos_codigo']) ?>">
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Taxas -->
                <div class="col-md-6 mb-3">
                    <div class="border rounded p-3 text-center">
                        <h6 class="small text-muted">Taxas (Appmax)</h6>
                        <p class="fs-5 fw-bold mb-1">R$ <?= number_format($p['valor_taxas'], 2, ',', '.') ?></p>
                        <span class="badge bg-<?= $p['boleto_taxas_pago'] ? 'success' : 'warning' ?> mb-2"><?= $p['boleto_taxas_pago'] ? '✓ Pago' : '⏳ Pendente' ?></span>

                        <?php if (!$paga && $p['valor_taxas'] > 0): ?>
                            <?php if ($isPix && !empty($p['pix_taxas_payload'])): ?>
                                <?php if (!empty($p['pix_taxas_qrcode'])): ?>
                                    <div class="mb-2"><img src="data:image/png;base64,<?= htmlspecialchars($p['pix_taxas_qrcode']) ?>" alt="QR" style="max-width:160px;" class="img-fluid"></div>
                                <?php endif; ?>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($p['pix_taxas_payload']) ?>" id="pix-taxa-<?= $p['id'] ?>">
                                    <button class="btn btn-outline-success btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('pix-taxa-<?= $p['id'] ?>').value);this.innerHTML='✓'"><i class="fas fa-copy"></i></button>
                                </div>
                            <?php elseif (!empty($p['boleto_taxas_url'])): ?>
                                <a href="<?= htmlspecialchars($p['boleto_taxas_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-barcode"></i> Ver Boleto</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!$paga && $isPix): ?>
            <div class="text-center mt-2">
                <button class="btn btn-sm btn-outline-success" onclick="regerarPix(<?= $p['id'] ?>, this)">
                    <i class="fas fa-redo"></i> Regerar QR Code PIX
                </button>
            </div>
            <?php elseif (!$paga && !$isPix && in_array($p['status'], ['aguardando_pagamento','vencida','em_atraso','reemitida'])): ?>
            <div class="text-center mt-2">
                <form method="POST" action="/carne/segunda-via/<?= $p['id'] ?>" class="d-inline">
                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-redo"></i> 2ª Via Boleto</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function regerarPix(parcelaId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
    fetch('/carne/regerar-pix/' + parcelaId, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Erro ao regerar PIX');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Regerar QR Code PIX';
            }
        })
        .catch(() => {
            alert('Erro de conexão');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-redo"></i> Regerar QR Code PIX';
        });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
