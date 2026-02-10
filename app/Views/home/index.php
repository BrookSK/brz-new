<?php ob_start(); ?>
<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="hero-content" data-aos="fade-right">
                    <h1 class="display-3 fw-bold mb-4">Importe Produtos dos EUA com Logística Completa</h1>
                    <p class="lead mb-4">Sua plataforma confiável para importar eletrônicos, vestuário e muito mais com processo 100% transparente e seguro.</p>
                    <div class="d-flex gap-3 mb-4">
                        <a href="/produtos" class="cta-button">
                            <i class="fas fa-shopping-cart me-2"></i> Ver Produtos
                        </a>
                        <a href="/como-funciona" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-play-circle me-2"></i> Como Funciona
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-image" data-aos="fade-left">
                    <img src="/import.png" 
                         alt="Importação Internacional" 
                         class="img-fluid rounded-3 shadow-lg">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title" data-aos="fade-up">Por que escolher a Braziliana?</h2>
            <p class="section-subtitle" data-aos="fade-up">Oferecemos a melhor experiência de importação com tecnologia e confiança</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card card h-100 p-4">
                    <div class="text-center mb-3">
                        <div class="feature-icon rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14);">
                            <i class="fas fa-shield-alt fa-2x" style="color: rgba(11, 31, 58, 1);"></i>
                        </div>
                        <h5>Compra Segura</h5>
                    </div>
                    <p class="text-muted">Pagamento 100% seguro com proteção anti-fraude e criptografia de dados.</p>
                </div>
            </div>
            
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card card h-100 p-4">
                    <div class="text-center mb-3">
                        <div class="feature-icon rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14);">
                            <i class="fas fa-plane fa-2x" style="color: rgba(11, 31, 58, 1);"></i>
                        </div>
                        <h5>Logística Completa</h5>
                    </div>
                    <p class="text-muted">Do despacho nos EUA até a entrega na sua porta, cuidamos de tudo.</p>
                </div>
            </div>
            
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card card h-100 p-4">
                    <div class="text-center mb-3">
                        <div class="feature-icon rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14);">
                            <i class="fas fa-calculator fa-2x" style="color: rgba(11, 31, 58, 1);"></i>
                        </div>
                        <h5>Preços Transparentes</h5>
                    </div>
                    <p class="text-muted">Sem taxas escondidas. Você vê o valor final antes de comprar.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Products Preview Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title" data-aos="fade-up">Produtos em Destaque</h2>
            <p class="section-subtitle" data-aos="fade-up">Conheça nossos produtos mais populares</p>
        </div>
        
        <div class="row g-4" id="produtos-destaque">
            <!-- Produtos serão carregados via AJAX -->
        </div>
        
        <div class="text-center mt-4">
            <a href="/produtos" class="btn btn-outline-primary btn-lg" data-aos="fade-up">
                <i class="fas fa-th me-2"></i> Ver Todos os Produtos
            </a>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title" data-aos="fade-up">Como Funciona</h2>
            <p class="section-subtitle" data-aos="fade-up">Importar nunca foi tão simples</p>
        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="timeline">
                    <!-- Step 1 -->
                    <div class="timeline-item d-flex mb-4" data-aos="fade-right">
                        <div class="timeline-number rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                            <span class="fw-bold">1</span>
                        </div>
                        <div class="timeline-content">
                            <h5>Escolha seus Produtos</h5>
                            <p class="text-muted">Navegue pelo nosso catálogo e selecione os produtos desejados.</p>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="timeline-item d-flex mb-4" data-aos="fade-left">
                        <div class="timeline-number rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                            <span class="fw-bold">2</span>
                        </div>
                        <div class="timeline-content">
                            <h5>Pague de Forma Segura</h5>
                            <p class="text-muted">Checkout seguro com múltiplas opções de pagamento.</p>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="timeline-item d-flex mb-4" data-aos="fade-right">
                        <div class="timeline-number rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                            <span class="fw-bold">3</span>
                        </div>
                        <div class="timeline-content">
                            <h5>Acompanhe o Processo</h5>
                            <p class="text-muted">Acompanhe cada etapa desde o despacho até a entrega.</p>
                        </div>
                    </div>
                    
                    <!-- Step 4 -->
                    <div class="timeline-item d-flex mb-4" data-aos="fade-left">
                        <div class="timeline-number rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                            <span class="fw-bold">4</span>
                        </div>
                        <div class="timeline-content">
                            <h5>Receba em Casa</h5>
                            <p class="text-muted">Receba seus produtos diretamente na sua porta.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title" data-aos="fade-up">O que nossos clientes dizem</h2>
            <p class="section-subtitle" data-aos="fade-up">Milhares de clientes satisfeitos</p>
        </div>
        
        <div class="row">
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="testimonial-card">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                <span class="fw-bold">JD</span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0">João Silva</h6>
                            <small class="text-muted">São Paulo, SP</small>
                        </div>
                    </div>
                    <p class="mb-0">"Excelente serviço! Consegui importar meu iPhone com um preço muito melhor que no Brasil. Todo o processo foi transparente e recebi exatamente no prazo combinado."</p>
                    <div class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="testimonial-card">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                <span class="fw-bold">MS</span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0">Maria Santos</h6>
                            <small class="text-muted">Rio de Janeiro, RJ</small>
                        </div>
                    </div>
                    <p class="mb-0">"Recomendo a todos! O suporte é incrível e a plataforma muito fácil de usar. Já importei vários produtos e nunca tive problemas."</p>
                    <div class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                <div class="testimonial-card">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                <span class="fw-bold">PC</span>
                            </div>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0">Pedro Costa</h6>
                            <small class="text-muted">Belo Horizonte, MG</small>
                        </div>
                    </div>
                    <p class="mb-0">"Melhor que comprar diretamente! Os preços são competitivos e a qualidade do serviço é impecável. Super recomendo!"</p>
                    <div class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5">
    <div class="container text-center">
        <div class="row">
            <div class="col-lg-8 mx-auto" data-aos="zoom-in">
                <h2 class="mb-4">Pronto para começar a importar?</h2>
                <p class="lead mb-4">Junte-se a milhares de clientes que economizam comprando diretamente dos EUA</p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="/register" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i> Criar Conta Gratuita
                    </a>
                    <a href="/produtos" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-eye me-2"></i> Ver Produtos
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    function formatMoney(value) {
        const num = Number(value || 0);
        try {
            return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        } catch (e) {
            return num.toFixed(2);
        }
    }

    // Carregar produtos em destaque via AJAX
    $.ajax({
        url: '/api/produtos/destaque',
        method: 'GET',
        success: function(response) {
            if (response.produtos && response.produtos.length > 0) {
                let html = '';
                response.produtos.forEach(function(produto) {
                    html += `
                        <div class="col-lg-3 col-md-6">
                            <div class="product-card card h-100">
                                <div class="position-relative overflow-hidden">
                                    <img src="${produto.foto_principal || '/uploads/produtos/placeholder.jpg'}" 
                                         alt="${produto.nome}" 
                                         class="product-image card-img-top">
                                    ${produto.estoque <= 5 ? '<span class="position-absolute top-0 end-0 m-2 badge" style="background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.35); color: rgba(124, 45, 18, 1);">Últimas unidades</span>' : ''}
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title">${produto.nome}</h6>
                                    <p class="text-muted small">${produto.categoria}</p>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="h5 mb-0 text-primary">${produto.moeda} ${formatMoney(produto.valor)}</span>
                                        <small class="text-muted">${produto.estoque} unid.</small>
                                    </div>
                                    <a href="/produto/detalhes/${produto.id}" class="btn btn-outline-primary btn-sm w-100">
                                        Ver Detalhes
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                });
                $('#produtos-destaque').html(html);
            } else {
                $('#produtos-destaque').html('<div class="col-12 text-center"><p class="text-muted">Nenhum produto em destaque no momento.</p></div>');
            }
        },
        error: function() {
            $('#produtos-destaque').html('<div class="col-12 text-center"><p class="text-muted">Não foi possível carregar produtos em destaque.</p></div>');
        }
    });
});
</script>

<style>
.hero-section .hero-image {
    position: relative;
    padding: 14px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.10);
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 18px 50px rgba(11, 31, 58, 0.28);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    overflow: hidden;
}

.hero-section .hero-image::before {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
}

.hero-section .hero-image img {
    position: relative;
    display: block;
    width: 100%;
    border-radius: 18px;
    box-shadow: 0 14px 38px rgba(11, 31, 58, 0.30);
    filter: saturate(1.08) contrast(1.02);
    transform: none;
}

.timeline {
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 25px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgba(148, 163, 184, 0.35);
}

.timeline-item {
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -2px;
    top: 25px;
    width: 4px;
    height: 4px;
    background: white;
    border: 2px solid rgba(148, 163, 184, 0.65);
    border-radius: 50%;
}

@media (max-width: 768px) {
    .timeline::before {
        left: 15px;
    }
    
    .timeline-number {
        width: 40px !important;
        height: 40px !important;
        font-size: 0.9rem !important;
    }
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
