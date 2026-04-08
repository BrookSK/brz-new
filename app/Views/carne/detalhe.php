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
                    <p><strong>Total da Compra:</strong> R$ <?= number_format($carne['total_geral'], 2, ',', '.') ?></p>
                    <p><strong>Produtos:</strong> R$ <?= number_format($carne['total_produtos'], 2, ',', '.') ?></p>
                    <p><strong>Taxas/Impostos:</strong> R$ <?= number_format($carne['total_taxas'], 2, ',', '.') ?></p>
                </div>
                <div class="col-md-4">
                    <p><strong>Parcelas:</strong> <?= $carne['quantidade_parcelas'] ?>x</p>
                    <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($carne['created_at'])) ?></p>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        O envio do pedido será realizado somente após a quitação total de todas as parcelas.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h6 class="mb-0"><i class="fas fa-list"></i> Parcelas</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Vencimento</th><th>Valor Total</th>
                            <th>Boleto Produtos (Câmbio Real)</th><th>Boleto Taxas (Appmax)</th>
                            <th>Status</th><th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carne['parcelas'] as $p): ?>
                        <tr>
                            <td><?= $p['numero_parcela'] ?></td>
                            <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
                            <td>R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                            <td>
                                <span class="badge bg-<?= $p['boleto_produtos_pago'] ? 'success' : 'secondary' ?>">
                                    R$ <?= number_format($p['valor_produtos'], 2, ',', '.') ?>
                                    <?= $p['boleto_produtos_pago'] ? '✓ Pago' : '⏳ Pendente' ?>
                                </span>
                                <?php if (!empty($p['boleto_produtos_url'])): ?>
                                    <a href="<?= htmlspecialchars($p['boleto_produtos_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1"><i class="fas fa-barcode"></i> Ver Boleto</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $p['boleto_taxas_pago'] ? 'success' : 'secondary' ?>">
                                    R$ <?= number_format($p['valor_taxas'], 2, ',', '.') ?>
                                    <?= $p['boleto_taxas_pago'] ? '✓ Pago' : '⏳ Pendente' ?>
                                </span>
                                <?php if (!empty($p['boleto_taxas_url'])): ?>
                                    <a href="<?= htmlspecialchars($p['boleto_taxas_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1"><i class="fas fa-barcode"></i> Ver Boleto</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusColors = ['paga'=>'success','parcialmente_paga'=>'warning','aguardando_pagamento'=>'info','pendente'=>'secondary','vencida'=>'danger','em_atraso'=>'danger','reemitida'=>'primary'];
                                ?>
                                <span class="badge bg-<?= $statusColors[$p['status']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span>
                            </td>
                            <td>
                                <?php if (in_array($p['status'], ['aguardando_pagamento','vencida','em_atraso','reemitida','pendente'])): ?>
                                    <form method="POST" action="/carne/segunda-via/<?= $p['id'] ?>" class="d-inline">
                                        <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-redo"></i> 2ª Via</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
