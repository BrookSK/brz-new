<?php ob_start(); ?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1" style="color:#0b1f3a; font-weight: 800;">Produtos do Clube Brasiliana</h1>
            <div class="text-muted">Produtos com benefícios do Clube (desconto progressivo e cashback em créditos, quando aplicável).</div>
        </div>
        <div class="d-flex gap-2">
            <a href="/clube-brasiliana" class="btn btn-outline-primary">
                <i class="fas fa-circle-info me-2"></i>Como funciona
            </a>
            <a href="/carrinho" class="btn btn-success">
                <i class="fas fa-shopping-cart me-2"></i>Ver carrinho
            </a>
        </div>
    </div>

    <?php if (empty($produtos)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="text-muted">Nenhum produto do Clube disponível no momento.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($produtos as $produto): ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="position-relative overflow-hidden" style="aspect-ratio: 1 / 1;">
                            <?php if (!empty($produto['foto_principal'])): ?>
                                <img src="<?= htmlspecialchars($produto['foto_principal']) ?>" alt="<?= htmlspecialchars($produto['name'] ?? '') ?>" class="card-img-top" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" style="width:100%;height:100%;">
                                    <i class="fas fa-image text-muted fa-3x"></i>
                                </div>
                            <?php endif; ?>

                            <span class="position-absolute top-0 start-0 m-2 badge" style="background:#0b1f3a;">
                                <i class="fas fa-crown me-1"></i>Clube Ativo
                            </span>
                            <?php if (!empty($produto['featured'])): ?>
                                <span class="position-absolute top-0 end-0 m-2 badge bg-danger">
                                    <i class="fas fa-star me-1"></i>Destaque
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="card-body">
                            <div class="mb-2">
                                <small class="text-muted"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($produto['categoria'] ?? 'Sem categoria') ?></small>
                            </div>
                            <div class="fw-bold mb-2" style="line-height:1.2;">
                                <?= htmlspecialchars($produto['name'] ?? '') ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-bold text-primary">
                                    <?= number_format((float) ($produto['price'] ?? 0), 2, ',', '.') ?>
                                </div>
                                <div>
                                    <?php if (((int) ($produto['stock'] ?? 0)) > 0): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Disponível</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Esgotado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-transparent border-top-0">
                            <div class="d-grid gap-2">
                                <a href="/produto/detalhes/<?= (int) ($produto['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-2"></i>Ver detalhes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php $content = ob_get_clean(); ?>
<?php $title = 'Produtos do Clube Brasiliana'; ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
