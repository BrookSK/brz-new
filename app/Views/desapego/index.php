<?php ob_start(); ?>
<div class="container py-4">

    <!-- Header -->
    <div class="text-center mb-4">
        <h1 class="fw-bold" style="color: var(--primary-color);">
            <i class="fas fa-hand-holding-heart me-2"></i>Desapego Brasiliano
        </h1>
        <p class="text-muted fs-5">Produtos disponíveis para venda direta nos Estados Unidos</p>
    </div>

    <!-- Aviso: somente para EUA -->
    <div class="alert alert-info d-flex align-items-center mb-4" style="border-radius: 14px; border-left: 4px solid #0dcaf0;">
        <i class="fas fa-flag-usa fa-2x me-3 text-info"></i>
        <div>
            <strong>Atenção:</strong> Os produtos desta seção estão disponíveis <strong>exclusivamente para entrega nos Estados Unidos</strong>. 
            Para finalizar a compra com itens de desapego, o endereço de entrega deve ser nos EUA.
        </div>
    </div>

    <!-- Products Grid -->
    <div class="row">
        <?php if (empty($produtos)): ?>
            <div class="col-lg-12">
                <div class="text-center py-5">
                    <i class="fas fa-hand-holding-heart fa-4x text-muted mb-3"></i>
                    <h3 class="text-muted">Nenhum produto de desapego disponível no momento</h3>
                    <p class="text-muted">Fique de olho, novos produtos podem aparecer a qualquer momento!</p>
                    <a href="/produtos" class="btn btn-primary mt-3">
                        <i class="fas fa-arrow-left me-2"></i>Ver todos os produtos
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($produtos as $produto): ?>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card h-100 product-card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                    <div class="position-relative overflow-hidden" style="height: 220px; background: #f8f9fa;">
                        <?php if ($produto['foto_principal']): ?>
                            <img src="<?= htmlspecialchars($produto['foto_principal']) ?>" 
                                 alt="<?= htmlspecialchars($produto['name']) ?>"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <span class="position-absolute top-0 start-0 m-2 badge" style="background: #0891b2; font-size: 0.8rem;">
                            <i class="fas fa-hand-holding-heart me-1"></i>Desapego
                        </span>
                        <?php if (!empty($produto['sale_price']) && (float) $produto['sale_price'] > 0 && (float) $produto['sale_price'] < (float) $produto['price']): ?>
                            <?php 
                                $desconto = round((1 - (float) $produto['sale_price'] / (float) $produto['price']) * 100);
                            ?>
                            <span class="position-absolute top-0 end-0 m-2 badge bg-success" style="font-size: 0.8rem;">
                                -<?= $desconto ?>%
                            </span>
                        <?php endif; ?>
                        <span class="position-absolute bottom-0 end-0 m-2 badge bg-dark bg-opacity-75" style="font-size: 0.7rem;">
                            <i class="fas fa-flag-usa me-1"></i>Somente EUA
                        </span>
                    </div>
                    <div class="card-body d-flex flex-column p-3">
                        <h6 class="card-title fw-semibold mb-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.8em;">
                            <?= htmlspecialchars($produto['name']) ?>
                        </h6>
                        <div class="mt-auto">
                            <?php if (!empty($produto['sale_price']) && (float) $produto['sale_price'] > 0 && (float) $produto['sale_price'] < (float) $produto['price']): ?>
                                <div>
                                    <small class="text-muted text-decoration-line-through">US$ <?= number_format((float) $produto['price'], 2) ?></small>
                                </div>
                                <div class="fw-bold fs-5" style="color: #0891b2;">
                                    US$ <?= number_format((float) $produto['sale_price'], 2) ?>
                                </div>
                            <?php else: ?>
                                <div class="fw-bold fs-5" style="color: var(--primary-color);">
                                    US$ <?= number_format((float) $produto['price'], 2) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="/produto/detalhes/<?= (int) $produto['id'] ?>" class="btn btn-primary btn-sm w-100 mt-3" style="border-radius: 10px;">
                            <i class="fas fa-eye me-1"></i>Ver detalhes
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Desapego Brasiliano';
require __DIR__ . '/../layouts/main.php';
?>
