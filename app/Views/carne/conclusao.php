<?php $title = 'Pedido Confirmado - Carnê Braziliana'; ?>
<?php ob_start(); ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-success">
                <div class="card-body text-center py-4">
                    <div class="mb-3"><i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i></div>
                    <h3 class="text-success mb-3">Compra registrada com sucesso!</h3>
                    <p class="lead">Seu pedido #<?= $carne['pedido_id'] ?> foi registrado com pagamento via Carnê Braziliana.</p>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Como funciona o Carnê Braziliana</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Sua compra foi parcelada em <strong><?= $carne['quantidade_parcelas'] ?>x</strong></li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Cada parcela gera <strong>dois pagamentos</strong>: um de produtos (Câmbio Real) e um de taxas (Câmbio Real Taxas)</li>
                        <li class="mb-2"><i class="fas fa-qrcode text-primary"></i> A <strong>primeira parcela é via PIX</strong> para pagamento imediato</li>
                        <li class="mb-2"><i class="fas fa-exclamation-triangle text-warning"></i> A parcela só é considerada paga quando <strong>ambos os pagamentos</strong> forem confirmados</li>
                        <li class="mb-2"><i class="fas fa-truck text-info"></i> O envio do pedido ocorrerá <strong>somente após a quitação total</strong></li>
                        <li class="mb-2"><i class="fas fa-user text-secondary"></i> Acompanhe tudo em <a href="/meus-carnes">Minha Conta > Meus Carnês</a></li>
                    </ul>
                </div>
            </div>

            <?php if ($primeiraParcela): ?>
            <?php $isPix = (($primeiraParcela['metodo_pagamento'] ?? '') === 'pix'); ?>
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-<?= $isPix ? 'success' : 'dark' ?> text-white">
                    <h5 class="mb-0"><i class="fas fa-<?= $isPix ? 'qrcode' : 'barcode' ?>"></i> Primeira Parcela <?= $isPix ? '— Pague via PIX' : '' ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($isPix): ?>
                        <div class="alert alert-success small mb-3">
                            <i class="fas fa-bolt"></i> A primeira parcela é via <strong>PIX</strong> para pagamento imediato. Escaneie o QR Code ou copie o código.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Produtos (Câmbio Real) -->
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3 text-center">
                                <h6>Produtos (Câmbio Real)</h6>
                                <p class="fs-4 fw-bold text-primary">R$ <?= number_format($primeiraParcela['valor_produtos'], 2, ',', '.') ?></p>

                                <?php if ($isPix && !empty($primeiraParcela['pix_produtos_qrcode'])): ?>
                                    <?php $qrSrc = (strpos(base64_decode(substr($primeiraParcela['pix_produtos_qrcode'],0,100)),'<svg')!==false) ? 'data:image/svg+xml;base64,' : 'data:image/png;base64,'; ?>
                                    <div class="mb-2"><img src="<?= $qrSrc . htmlspecialchars($primeiraParcela['pix_produtos_qrcode']) ?>" alt="QR Code PIX" style="max-width: 250px; width: 250px; height: 250px; image-rendering: pixelated;" class="img-fluid"></div>
                                <?php endif; ?>

                                <?php if ($isPix && !empty($primeiraParcela['pix_produtos_payload'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted d-block mb-1">Copia e Cola:</small>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($primeiraParcela['pix_produtos_payload']) ?>" id="pix-prod">
                                            <button class="btn btn-outline-success" onclick="navigator.clipboard.writeText(document.getElementById('pix-prod').value);this.innerHTML='<i class=\'fas fa-check\'></i> Copiado'"><i class="fas fa-copy"></i></button>
                                        </div>
                                    </div>
                                <?php elseif (!empty($primeiraParcela['boleto_produtos_url'])): ?>
                                    <a href="<?= htmlspecialchars($primeiraParcela['boleto_produtos_url']) ?>" target="_blank" class="btn btn-primary mb-2"><i class="fas fa-external-link-alt"></i> Abrir Boleto</a>
                                <?php elseif (!empty($primeiraParcela['boleto_produtos_codigo']) && $primeiraParcela['boleto_produtos_codigo'] !== 'Valor abaixo do mínimo para geração de boleto'): ?>
                                    <div class="input-group input-group-sm mt-2">
                                        <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($primeiraParcela['boleto_produtos_codigo']) ?>" id="linha-prod">
                                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('linha-prod').value)"><i class="fas fa-copy"></i></button>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-info">Pagamento será gerado em breve</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Taxas (Câmbio Real Taxas) -->
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3 text-center">
                                <h6>Taxas (Câmbio Real Taxas)</h6>
                                <p class="fs-4 fw-bold text-primary">R$ <?= number_format($primeiraParcela['valor_taxas'], 2, ',', '.') ?></p>

                                <?php if ($isPix && !empty($primeiraParcela['pix_taxas_qrcode'])): ?>
                                    <?php $qrSrcTaxas = (strpos(base64_decode(substr($primeiraParcela['pix_taxas_qrcode'],0,100)),'<svg')!==false) ? 'data:image/svg+xml;base64,' : 'data:image/png;base64,'; ?>
                                    <div class="mb-2">
                                        <img src="<?= $qrSrcTaxas . htmlspecialchars($primeiraParcela['pix_taxas_qrcode']) ?>" alt="QR Code PIX" style="max-width: 250px; width: 250px; height: 250px; image-rendering: pixelated;" class="img-fluid">
                                    </div>
                                <?php elseif ($isPix && !empty($primeiraParcela['pix_taxas_payload'])): ?>
                                    <div class="mb-2" id="qr-taxas-container" style="max-width:250px;margin:0 auto;"></div>
                                    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                                    <script>
                                    (function(){
                                        var payload = <?= json_encode($primeiraParcela['pix_taxas_payload']) ?>;
                                        var container = document.getElementById('qr-taxas-container');
                                        if (container && payload && typeof QRCode !== 'undefined') {
                                            QRCode.toCanvas(payload, {width:250,margin:1}, function(err, canvas){
                                                if (!err && canvas) container.appendChild(canvas);
                                            });
                                        }
                                    })();
                                    </script>
                                <?php endif; ?>

                                <?php if ($isPix && !empty($primeiraParcela['pix_taxas_payload'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted d-block mb-1">Copia e Cola:</small>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($primeiraParcela['pix_taxas_payload']) ?>" id="pix-taxa">
                                            <button class="btn btn-outline-success" onclick="navigator.clipboard.writeText(document.getElementById('pix-taxa').value);this.innerHTML='<i class=\'fas fa-check\'></i> Copiado'"><i class="fas fa-copy"></i></button>
                                        </div>
                                    </div>
                                <?php elseif (!empty($primeiraParcela['boleto_taxas_url'])): ?>
                                    <a href="<?= htmlspecialchars($primeiraParcela['boleto_taxas_url']) ?>" target="_blank" class="btn btn-primary mb-2"><i class="fas fa-external-link-alt"></i> Abrir Boleto</a>
                                <?php elseif ($primeiraParcela['valor_taxas'] > 0): ?>
                                    <span class="badge bg-info">Pagamento será gerado em breve</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sem taxas nesta parcela</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="/meus-carnes" class="btn btn-primary btn-lg"><i class="fas fa-file-invoice-dollar"></i> Ver Meus Carnês</a>
                <a href="/" class="btn btn-outline-secondary btn-lg ms-2"><i class="fas fa-home"></i> Voltar à Loja</a>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
