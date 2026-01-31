<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
        <h3 class="mb-2">Carrinho vazio</h3>
        <p class="text-muted mb-4">Adicione produtos ao carrinho para ver o resumo e finalizar a compra.</p>

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
    transition: none;
    border: none;
    overflow: hidden;
}

.feature-card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.product-card {
    transition: none;
    border: none;
    overflow: hidden;
}

.product-card:hover {
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
    transition: none;
}

.product-card:hover .product-image-container img {
    transform: none;
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
