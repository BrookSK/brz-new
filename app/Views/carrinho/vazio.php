<?php ob_start(); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="text-center">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                <h3 class="mb-4">Seu carrinho está vazio</h3>
                <p class="text-muted mb-4">Parece um momento para adicionar produtos ao seu carrinho.</p>
                <div class="d-grid gap-3 d-flex justify-content-center">
                    <a href="/produtos" class="btn btn-primary">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Ver Produtos
                    </a>
                    <a href="/" class="btn btn-outline-primary">
                        <i class="fas fa-home me-2"></i>
                        Voltar à Home
                    </a>
                </div>
                
                <!-- Produtos Recentes -->
                <div class="mt-5">
                    <h5 class="text-center mb-4">Produtos em Destaque</h5>
                    <div class="row">
                        <?php
                        // Obter produtos em destaque para exibir
                        $produtosDestaque = [];
                        try {
                            // Aqui você pode buscar produtos em destaque do banco
                            $db = \Config\Database::getConnection();
                            $stmt = $db->prepare("
                                SELECT p.*, 
                                       c.nome AS categoria_nome,
                                       pf.nome_arquivo AS foto_principal
                                FROM produtos p
                                LEFT JOIN categorias c ON p.category_id = c.id
                                LEFT JOIN produto_fotos pf ON p.id = pf.produto_id AND pf.principal = 1
                                WHERE p.active = 1 AND p.featured = 1
                                ORDER BY RAND()
                                LIMIT 4
                            ");
                            $stmt->execute();
                            $produtosDestaque = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                        } catch (\Exception $e) {
                            $produtosDestaque = [];
                        }
                        
                        foreach ($produtosDestaque as $produto):
                        ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 product-card">
                                <a href="/produto/detalhes/<?= $produto['id'] ?>" class="text-decoration-none text-dark">
                                    <div class="product-image-container">
                                        <?php if ($produto['foto_principal']): ?>
                                            <img src="/uploads/produtos/<?= $produto['foto_principal'] ?>" 
                                                 alt="<?= htmlspecialchars($produto['nome']) ?>" 
                                                 class="card-img-top">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/300x200/667eea/ffffff?text=<?= urlencode($produto['nome']) ?>" 
                                                 alt="<?= htmlspecialchars($produto['nome']) ?>" 
                                                 class="card-img-top">
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body p-3">
                                        <h6 class="card-title"><?= htmlspecialchars($produto['nome']) ?></h6>
                                        <p class="card-text text-muted small">
                                            <?= htmlspecialchars(substr($produto['descricao'] ?? '', 0, 80)) ?>...
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-primary rounded-pill"><?= $produto['moeda'] ?? 'USD' ?> <?= number_format($produto['preco'] ?? 0, 2, ',', '.') ?></span>
                                            <span class="badge bg-success rounded-pill"><?= $produto['categoria_nome'] ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.empty-cart {
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.empty-cart i {
    color: #6c757d;
    margin-bottom: 1rem;
}

.empty-cart h3 {
    color: #495057;
    font-weight: 600;
    margin-bottom: 1rem;
}

.empty-cart p {
    color: #6c757d;
    margin-bottom: 2rem;
}

.feature-card {
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.product-card {
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.product-image-container {
    height: 200px;
    overflow: hidden;
}

.product-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-image-container img {
    transform: scale(1.05);
}

.card-img-top {
    height: 200px;
    object-fit: cover;
}

.card-body {
    padding: 1rem;
}

.card-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    line-height: 1.2;
    color: #212529;
}

.card-text {
    font-size: 0.875rem;
    line-height: 1.4;
    color: #6c757d;
}

.badge {
    font-size: 0.75em;
    font-weight: 700;
}

.badge.bg-primary {
    background-color: #0d6efd !important;
}

.badge.bg-success {
    background-color: #198754 !important;
}

@media (max-width: 768px) {
    .empty-cart {
        min-height: 50vh;
        padding: 2rem 1rem;
    }
    
    .feature-card {
        margin-bottom: 1rem;
    }
}
</style>

<?php $content = ob_get_clean(); ?>
