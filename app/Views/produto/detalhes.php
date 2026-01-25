<?php ob_start(); ?>
<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Início</a></li>
            <li class="breadcrumb-item"><a href="/produtos">Produtos</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($produto['nome']) ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Galeria de Fotos -->
        <div class="col-lg-6 mb-4">
            <div class="product-gallery">
                <!-- Foto Principal -->
                <div class="main-image-container mb-3">
                    <?php 
                    $fotoPrincipal = null;
                    if (!empty($fotos)) {
                        foreach ($fotos as $foto) {
                            if ($foto['principal']) {
                                $fotoPrincipal = $foto;
                                break;
                            }
                        }
                        // Se não tiver principal, usa a primeira
                        if (!$fotoPrincipal && !empty($fotos)) {
                            $fotoPrincipal = $fotos[0];
                        }
                    }
                    
                    if ($fotoPrincipal && !empty($fotoPrincipal['nome_arquivo'])) {
                        $fotoUrl = $fotoPrincipal['nome_arquivo'];
                        $caminhoFisico = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($fotoUrl, '/');
                        $fotoExists = file_exists($caminhoFisico);
                    } else {
                        $fotoUrl = null;
                        $fotoExists = false;
                    }
                    ?>
                    <?php if ($fotoUrl && $fotoExists): ?>
                        <a href="<?= 'https://novobr.brazilianashop.com.br' . $fotoUrl ?>" target="_blank">
                            <img id="main-image" 
                                 src="<?= 'https://novobr.brazilianashop.com.br' . $fotoUrl ?>?v=<?= time() ?>" 
                                 alt="<?= htmlspecialchars($produto['nome']) ?>"
                                 class="img-fluid rounded shadow-sm main-product-image"
                                 style="cursor: pointer; transition: transform 0.2s;"
                                 onmouseover="this.style.transform='scale(1.02)'"
                                 onmouseout="this.style.transform='scale(1)'"
                                 title="Clique para ver imagem em tamanho real">
                        </a>
                    <?php else: ?>
                        <div class="img-fluid rounded shadow-sm main-product-image bg-light d-flex align-items-center justify-content-center" style="height: 400px;">
                            <i class="fas fa-image text-muted fa-3x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Miniaturas -->
                <?php if (!empty($fotos)): ?>
                    <div class="row g-2">
                        <?php foreach ($fotos as $index => $foto): ?>
                        <div class="col-3">
                            <?php 
                            if (!empty($foto['nome_arquivo'])) {
                                $miniaturaUrl = $foto['nome_arquivo'];
                                $caminhoMiniatura = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($miniaturaUrl, '/');
                                $miniaturaExists = file_exists($caminhoMiniatura);
                            } else {
                                $miniaturaUrl = null;
                                $miniaturaExists = false;
                            }
                            ?>
                            <?php if ($miniaturaUrl && $miniaturaExists): ?>
                                <img src="<?= 'https://novobr.brazilianashop.com.br' . $miniaturaUrl ?>?v=<?= time() ?>" 
                                     alt="<?= htmlspecialchars($foto['legenda'] ?? 'Miniatura ' . ($index + 1)) ?>"
                                     class="img-thumbnail thumbnail-image cursor-pointer"
                                     style="height: 80px; width: 100%; object-fit: cover; cursor: pointer;"
                                     onclick="changeMainImage('<?= 'https://novobr.brazilianashop.com.br' . $miniaturaUrl ?>')"
                                     title="<?= $foto['principal'] ? 'Imagem Principal' : 'Clique para ver esta imagem' ?>">
                                <?php if ($foto['principal']): ?>
                                    <span class="position-absolute top-0 start-0 badge bg-primary" style="font-size: 0.6em;">Principal</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="img-thumbnail bg-light d-flex align-items-center justify-content-center" style="height: 80px;">
                                    <i class="fas fa-image text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informações do Produto -->
        <div class="col-lg-6">
            <div class="product-info">
                <!-- Nome e Categoria -->
                <h1 class="h2 mb-2"><?= htmlspecialchars($produto['nome']) ?></h1>
                <p class="text-muted mb-3">
                    <small>Categoria: <?= htmlspecialchars($produto['categoria']) ?></small>
                </p>

                <!-- Preço -->
                <div class="price-section mb-4">
                    <div class="current-price">
                        <span class="currency"><?= $produto['moeda'] ?></span>
                        <span class="amount" data-original-price="<?= $produto['preco'] ?>"><?= number_format($produto['preco'], 2, ',', '.') ?></span>
                    </div>
                    <?php if ($produto['moeda'] === 'USD'): ?>
                    <div class="price-conversion text-muted">
                        <small>≈ R$ <?= number_format($produto['preco'] * 5.5, 2, ',', '.') ?></small>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Descrição -->
                <div class="description mb-4">
                    <h5>Descrição</h5>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($produto['descricao_curta'] ?? $produto['descricao'] ?? '')) ?></p>
                    
                    <?php if (!empty($produto['descricao_completa'])): ?>
                        <div class="mt-3">
                            <h6>Descrição Completa</h6>
                            <div class="text-muted">
                                <?= nl2br(htmlspecialchars($produto['descricao_completa'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Especificações -->
                <div class="specifications mb-4">
                    <h5>Especificações</h5>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>SKU:</strong></td>
                            <td><?= htmlspecialchars($produto['sku']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Peso:</strong></td>
                            <td><?= number_format($produto['peso'], 3, ',', '.') ?> kg</td>
                        </tr>
                        <?php if ($produto['comprimento'] && $produto['largura'] && $produto['altura']): ?>
                        <tr>
                            <td><strong>Dimensões:</strong></td>
                            <td><?= $produto['comprimento'] ?> × <?= $produto['largura'] ?> × <?= $produto['altura'] ?> cm</td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Estoque:</strong></td>
                            <td>
                                <?php if ($produto['estoque'] > 0): ?>
                                    <span class="badge bg-success"><?= $produto['estoque'] ?> unidades</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Fora de estoque</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Adicionar ao Carrinho -->
                <div class="add-to-cart-section">
                    <form id="add-to-cart-form" class="row g-3">
                        <input type="hidden" name="id" value="<?= $produto['id'] ?>">
                        
                        <div class="col-12">
                            <label for="quantity" class="form-label">Quantidade:</label>
                            <div class="input-group" style="max-width: 200px;">
                                <button type="button" class="btn btn-outline-secondary" id="decrease-qty">-</button>
                                <input type="number" class="form-control text-center" name="quantidade" id="quantity" 
                                       value="1" min="1" max="<?= $produto['estoque'] ?>">
                                <button type="button" class="btn btn-outline-secondary" id="increase-qty">+</button>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg w-100" 
                                    <?= $produto['estoque'] > 0 ? '' : 'disabled' ?>>
                                <?php if ($produto['estoque'] > 0): ?>
                                    <i class="fas fa-shopping-cart"></i> Adicionar ao Carrinho
                                <?php else: ?>
                                    <i class="fas fa-times"></i> Produto Indisponível
                                <?php endif; ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Informações de Importação -->
                <div class="import-info mt-4">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Informações de Importação</h6>
                        <ul class="mb-0 small">
                            <li>Produto importado dos EUA</li>
                            <li>Tempo estimado de entrega: 15-30 dias</li>
                            <li>Impostos inclusos no preço final</li>
                            <li>Taxa de serviço: US$ 39/kg</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Produtos Relacionados -->
    <?php if (!empty($produtosRelacionados)): ?>
    <div class="related-products mt-5">
        <h3 class="mb-4">Produtos Relacionados</h3>
        <div class="row">
            <?php foreach ($produtosRelacionados as $relacionado): ?>
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card h-100 product-card">
                    <a href="/produto/detalhes/<?= $relacionado['id'] ?>" class="text-decoration-none">
                        <div class="product-image-container">
                            <?php 
                            $relacionadoPath = '/uploads/produtos/' . ($relacionado['foto_principal'] ?? '');
                            $relacionadoExists = !empty($relacionado['foto_principal']) && file_exists(__DIR__ . '/../../public' . $relacionadoPath);
                            ?>
                            <?php if ($relacionadoExists): ?>
                                <img src="<?= 'https://novobr.brazilianashop.com.br' . $relacionadoPath ?>?v=<?= time() ?>" 
                                     alt="<?= htmlspecialchars($relacionado['nome']) ?>"
                                     class="card-img-top"
                                     style="height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                                    <i class="fas fa-image text-muted fa-2x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title text-dark"><?= htmlspecialchars($relacionado['nome']) ?></h6>
                            <p class="card-text">
                                <span class="text-primary fw-bold">
                                    <?= $relacionado['moeda'] ?> <?= number_format($relacionado['preco'], 2, ',', '.') ?>
                                </span>
                            </p>
                        </div>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.product-gallery {
    position: sticky;
    top: 20px;
}

.main-image-container {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
}

.main-product-image {
    width: 100%;
    height: auto;
    transition: transform 0.3s ease;
    cursor: zoom-in;
}

.main-product-image:hover {
    transform: scale(1.05);
}

.thumbnail-image {
    cursor: pointer;
    transition: all 0.3s ease;
    opacity: 0.7;
}

.thumbnail-image:hover,
.thumbnail-image.active {
    opacity: 1;
    border-color: #007bff !important;
    transform: scale(1.05);
}

.current-price {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
}

.currency {
    font-size: 1.5rem;
    vertical-align: super;
}

.product-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.product-image-container {
    height: 200px;
    overflow: hidden;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-image {
    transform: scale(1.1);
}

/* Zoom no clique */
.zoom-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    cursor: zoom-out;
}

.zoom-overlay img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

@media (max-width: 768px) {
    .product-gallery {
        position: relative;
        top: 0;
    }
    
    .current-price {
        font-size: 1.5rem;
    }
    
    .currency {
        font-size: 1.2rem;
    }
}
</style>

<!-- Overlay para zoom -->
<div class="zoom-overlay" id="zoom-overlay">
    <img id="zoom-image" src="" alt="Zoom">
</div>

<script>
// Verificar se jQuery está disponível
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ === 'undefined') {
        console.error('jQuery não está carregado na página de detalhes!');
        // Tentar carregar jQuery manualmente
        const script = document.createElement('script');
        script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
        script.onload = function() {
            console.log('jQuery carregado manualmente');
            inicializarDetalhesProduto();
        };
        document.head.appendChild(script);
    } else {
        console.log('jQuery já está carregado na página de detalhes');
        inicializarDetalhesProduto();
    }
});

function inicializarDetalhesProduto() {
    console.log('Inicializando detalhes do produto...');
    
    // Verificar se SweetAlert está disponível
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert não está carregado!');
        // Fallback para alert nativo
        window.alertFallback = function(message, type = 'info') {
            alert(message);
        };
    }
    
    // Trocar imagem principal ao clicar na miniatura
    $('.thumbnail-image').on('click', function() {
        const newImageSrc = $(this).data('main-image');
        $('#main-image').attr('src', newImageSrc);
        
        // Atualizar classe active
        $('.thumbnail-image').removeClass('active');
        $(this).addClass('active');
    });
    
    // Zoom na imagem principal
    $('#main-image').on('click', function() {
        const imageSrc = $(this).attr('src');
        $('#zoom-image').attr('src', imageSrc);
        $('#zoom-overlay').fadeIn(300);
    });
    
    // Fechar zoom
    $('#zoom-overlay').on('click', function() {
        $(this).fadeOut(300);
    });
    
    // Controles de quantidade
    $('#decrease-qty').on('click', function() {
        const qtyInput = $('#quantity');
        const currentValue = parseInt(qtyInput.val());
        const minValue = parseInt(qtyInput.attr('min'));
        
        if (currentValue > minValue) {
            qtyInput.val(currentValue - 1);
        }
    });
    
    $('#increase-qty').on('click', function() {
        const qtyInput = $('#quantity');
        const currentValue = parseInt(qtyInput.val());
        const maxValue = parseInt(qtyInput.attr('max'));
        
        if (currentValue < maxValue) {
            qtyInput.val(currentValue + 1);
        }
    });
    
    // Adicionar ao carrinho
    $('#add-to-cart-form').on('submit', function(e) {
        e.preventDefault();
        
        console.log('Formulário submetido');
        
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adicionando...');
        
        $.ajax({
            url: '/produtos/carrinho',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                console.log('Resposta do servidor:', response);
                
                if (response.success) {
                    // Mostrar mensagem de sucesso
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Produto adicionado ao carrinho',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        alert('Produto adicionado ao carrinho!');
                    }
                    
                    // Atualizar badge do carrinho
                    updateCartBadge(response.total_itens || 1);
                    
                    // Resetar formulário
                    $('#quantity').val(1);
                    
                    // Redirecionar para o carrinho após 1.5 segundos
                    setTimeout(function() {
                        window.location.href = '/carrinho';
                    }, 1500);
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: response.error || 'Não foi possível adicionar o produto ao carrinho'
                        });
                    } else {
                        alert('Erro: ' + (response.error || 'Não foi possível adicionar o produto ao carrinho'));
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', error);
                console.error('Status:', status);
                console.error('XHR:', xhr);
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao adicionar produto ao carrinho. Tente novamente.'
                    });
                } else {
                    alert('Erro ao adicionar produto ao carrinho. Tente novamente.');
                }
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    function updateCartBadge(totalItens) {
        const badge = $('.navbar-nav .badge');
        if (totalItens > 0) {
            badge.text(totalItens).show();
        } else {
            badge.hide();
        }
    }
}
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
