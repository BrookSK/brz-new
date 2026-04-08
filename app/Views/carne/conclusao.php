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
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Cada parcela gera <strong>dois boletos</strong>: um de produtos (Câmbio Real) e um de taxas (Appmax)</li>
                        <li class="mb-2"><i class="fas fa-exclamation-triangle text-warning"></i> A parcela só é considerada paga quando <strong>ambos os boletos</strong> forem pagos</li>
                        <li class="mb-2"><i class="fas fa-truck text-info"></i> O envio do pedido ocorrerá <strong>somente após a quitação total</strong></li>
                        <li class="mb-2"><i class="fas fa-barcode text-primary"></i> O primeiro boleto já está disponível abaixo</li>
                        <li class="mb-2"><i class="fas fa-user text-secondary"></i> Acompanhe tudo em <a href="/meus-carnes">Minha Conta > Meus Carnês</a></li>
                    </ul>
                </div>
            </div>

            <?php if ($primeiraParcela): ?>
            <div class="card shadow-sm mt-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-barcode"></i> Primeira Parcela</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3 text-center">
                                <h6>Boleto Produtos (Câmbio Real)</h6>
                                <p class="fs-4 fw-bold text-primary">R$ <?= number_format($primeiraParcela['valor_produtos'], 2, ',', '.') ?></p>
                                <p class="text-muted small">Vencimento: <?= date('d/m/Y', strtotime($primeiraParcela['vencimento'])) ?></p>
                                <?php if (!empty($primeiraParcela['boleto_produtos_url'])): ?>
                                    <a href="<?= htmlspecialchars($primeiraParcela['boleto_produtos_url']) ?>" target="_blank" class="btn btn-primary"><i class="fas fa-external-link-alt"></i> Abrir Boleto</a>
                                <?php else: ?>
                                    <span class="badge bg-info">Boleto será gerado em breve</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3 text-center">
                                <h6>Boleto Taxas (Appmax)</h6>
                                <p class="fs-4 fw-bold text-primary">R$ <?= number_format($primeiraParcela['valor_taxas'], 2, ',', '.') ?></p>
                                <p class="text-muted small">Vencimento: <?= date('d/m/Y', strtotime($primeiraParcela['vencimento'])) ?></p>
                                <?php if (!empty($primeiraParcela['boleto_taxas_url'])): ?>
                                    <a href="<?= htmlspecialchars($primeiraParcela['boleto_taxas_url']) ?>" target="_blank" class="btn btn-primary"><i class="fas fa-external-link-alt"></i> Abrir Boleto</a>
                                <?php else: ?>
                                    <span class="badge bg-info">Boleto será gerado em breve</span>
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
