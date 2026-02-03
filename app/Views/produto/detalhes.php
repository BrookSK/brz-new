<?php ob_start(); ?>
<?php use App\Core\Url; ?>
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
                    if (empty($fotoPrincipal) && !empty($fotos)) {
                        foreach ($fotos as $foto) {
                            if (!empty($foto['principal'])) {
                                $fotoPrincipal = $foto;
                                break;
                            }
                        }
                        if (empty($fotoPrincipal) && !empty($fotos)) {
                            $fotoPrincipal = $fotos[0];
                        }
                    }

                    if (!empty($fotoPrincipal) && !empty($fotoPrincipal['nome_arquivo'])) {
                        $fotoUrl = $fotoPrincipal['nome_arquivo'];
                        $fotoAbsUrl = $fotoPrincipal['url_completa'] ?? Url::absolute($fotoUrl);
                        if (array_key_exists('arquivo_existe', $fotoPrincipal)) {
                            $fotoExists = (bool) $fotoPrincipal['arquivo_existe'];
                        } else {
                            $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
                            $rel = '/' . ltrim((string) $fotoUrl, '/');
                            $fotoExists = (
                                ($docRoot !== '' && file_exists($docRoot . $rel)) ||
                                ($docRoot !== '' && file_exists($docRoot . '/public' . $rel))
                            );
                        }
                    } else {
                        $fotoUrl = null;
                        $fotoAbsUrl = null;
                        $fotoExists = false;
                    }
                    ?>
                    <?php if ($fotoUrl && $fotoExists): ?>
                        <a href="<?= $fotoAbsUrl ?>" target="_blank">
                            <img id="main-image" 
                                 src="<?= $fotoAbsUrl ?>?v=<?= time() ?>" 
                                 alt="<?= htmlspecialchars($produto['nome']) ?>"
                                 class="img-fluid rounded shadow-sm main-product-image"
                                 style="cursor: pointer;"
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
                                $miniaturaAbsUrl = $foto['url_completa'] ?? Url::absolute($miniaturaUrl);
                                if (array_key_exists('arquivo_existe', $foto)) {
                                    $miniaturaExists = (bool) $foto['arquivo_existe'];
                                } else {
                                    $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
                                    $rel = '/' . ltrim((string) $miniaturaUrl, '/');
                                    $miniaturaExists = (
                                        ($docRoot !== '' && file_exists($docRoot . $rel)) ||
                                        ($docRoot !== '' && file_exists($docRoot . '/public' . $rel))
                                    );
                                }
                            } else {
                                $miniaturaUrl = null;
                                $miniaturaAbsUrl = null;
                                $miniaturaExists = false;
                            }
                            ?>
                            <?php if ($miniaturaUrl && $miniaturaExists): ?>
                                <img src="<?= $miniaturaAbsUrl ?>?v=<?= time() ?>" 
                                     alt="<?= htmlspecialchars($foto['legenda'] ?? 'Miniatura ' . ($index + 1)) ?>"
                                     class="img-thumbnail thumbnail-image cursor-pointer"
                                     style="height: 80px; width: 100%; object-fit: cover; cursor: pointer;"
                                     data-main-image="<?= $miniaturaAbsUrl ?>"
                                     title="<?= $foto['principal'] ? 'Imagem Principal' : 'Clique para ver esta imagem' ?>">
                                <?php if ($foto['principal']): ?>
                                    <span class="position-absolute top-0 start-0 badge" style="font-size: 0.6em; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">Principal</span>
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
                    <small>Categoria: <?= htmlspecialchars($produto['categoria'] ?? $produto['categoria_nome'] ?? 'Sem categoria') ?></small>
                </p>

                <!-- Preço -->
                <div class="price-section mb-4">
                    <div class="current-price">
                        <?php
                        $currencySymbols = [
                            'BRL' => 'R$',
                            'USD' => '$',
                            'EUR' => '€',
                            'GBP' => '£',
                            'JPY' => '¥',
                        ];
                        $currencyCode = strtoupper((string) ($produto['moeda'] ?? ''));
                        $currencyLabel = $currencySymbols[$currencyCode] ?? $currencyCode;
                        ?>
                        <span class="currency"><?= htmlspecialchars($currencyLabel) ?></span>
                        <span class="amount" data-original-price="<?= $produto['preco'] ?>"><?= number_format($produto['preco'], 2, ',', '.') ?></span>
                    </div>
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
                    <div class="table-responsive">
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
                                        <span id="stock-badge" class="badge" style="background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.18); color: rgba(6, 78, 59, 1);">
                                            <?= $produto['estoque'] ?> unidades
                                        </span>
                                    <?php else: ?>
                                        <span id="stock-badge" class="badge" style="background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.18); color: rgba(185, 28, 28, 1);">
                                            Fora de estoque
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php $variacoesUi = $variacoesUi ?? ['enabled' => false]; ?>
                <?php if (!empty($variacoesUi['enabled'])): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Variações</h5>
                            <div class="row g-3" id="variacoes-selectors">
                                <?php foreach (($variacoesUi['atributos'] ?? []) as $attr): ?>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold"><?= htmlspecialchars((string) ($attr['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></label>
                                        <select class="form-select variacao-select" data-tipo-id="<?= (int) ($attr['tipo_id'] ?? 0) ?>">
                                            <option value="">Selecione...</option>
                                            <?php foreach (($attr['opcoes'] ?? []) as $op): ?>
                                                <option value="<?= (int) ($op['opcao_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($op['valor'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3 small text-muted" id="variacao-status"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Adicionar ao Carrinho -->
                <div class="add-to-cart-section">
                    <form id="add-to-cart-form" class="row g-3">
                        <input type="hidden" name="id" value="<?= $produto['id'] ?>">
                        <input type="hidden" name="produto_variacao_id" id="produto_variacao_id" value="">
                        
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
                            <button id="btn-add-to-cart" type="submit" class="btn btn-primary btn-lg w-100" 
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
                        </div>
                        <div class="card-body">
                            <h6 class="card-title text-dark"><?= htmlspecialchars($relacionado['nome']) ?></h6>
                            <p class="card-text">
                                <span class="fw-bold" style="color: var(--primary-color);">
                                    <?php
                                    $relCurrencyCode = strtoupper((string) ($relacionado['moeda'] ?? ''));
                                    $relCurrencyLabel = $currencySymbols[$relCurrencyCode] ?? $relCurrencyCode;
                                    ?>
                                    <?= htmlspecialchars($relCurrencyLabel) ?> <?= number_format($relacionado['preco'], 2, ',', '.') ?>
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
    cursor: zoom-in;
}

.thumbnail-image {
    cursor: pointer;
    transition: all 0.3s ease;
    opacity: 0.7;
}

.thumbnail-image:hover,
.thumbnail-image.active {
    opacity: 1;
    border-color: rgba(11, 31, 58, 0.28) !important;
    transform: none;
}

.current-price {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
}

.currency {
    font-size: 1.5rem;
    vertical-align: super;
}

.product-card {
    transition: none;
}

.product-card:hover {
    transform: none;
    box-shadow: none;
}

.thumbnail-image {
    border: 2px solid transparent;
    transition: border-color 0.3s ease;
}

.thumbnail-image:hover {
    transform: none;
    border-color: rgba(11, 31, 58, 0.28);
}

.thumbnail-image.border-primary {
    border-color: rgba(11, 31, 58, 0.28) !important;
}

.product-image-container {
    height: 200px;
    overflow: hidden;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
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

@media (max-width: 575.98px) {
    .add-to-cart-section .input-group {
        max-width: none !important;
        width: 100%;
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

    const variacoesUi = <?= json_encode($variacoesUi ?? ['enabled' => false]) ?>;
    const fotosProdutoBase = <?= json_encode(array_values(array_map(function($f) {
        return [
            'url' => ($f['url_completa'] ?? (isset($f['nome_arquivo']) ? Url::absolute((string) $f['nome_arquivo']) : null)),
            'legenda' => ($f['legenda'] ?? null),
        ];
    }, $fotos ?? []))) ?>;

    const currencyLabel = <?= json_encode($currencyLabel ?? '') ?>;
    const basePrice = Number($('.amount').data('original-price') || 0);

    function renderGallery(photos) {
        const safe = Array.isArray(photos) ? photos.filter(p => p && p.url) : [];
        if (safe.length === 0) return;

        const mainUrl = safe[0].url;
        const mainLink = $('#main-image').parent('a');
        $('#main-image').attr('src', mainUrl + '?v=' + Date.now());
        if (mainLink && mainLink.length) {
            mainLink.attr('href', mainUrl);
        }

        const thumbsContainer = $('.product-gallery .row.g-2');
        if (!thumbsContainer || thumbsContainer.length === 0) return;
        thumbsContainer.empty();
        safe.forEach((p, index) => {
            const col = $('<div class="col-3"></div>');
            const img = $('<img />');
            img.attr('src', p.url + '?v=' + Date.now());
            img.attr('alt', p.legenda || ('Miniatura ' + (index + 1)));
            img.addClass('img-thumbnail thumbnail-image cursor-pointer');
            img.attr('style', 'height: 80px; width: 100%; object-fit: cover; cursor: pointer;');
            img.attr('data-main-image', p.url);
            col.append(img);
            thumbsContainer.append(col);
        });

        // Rebind do clique nas miniaturas
        $('.thumbnail-image').off('click').on('click', function() {
            const newImageSrc = $(this).data('main-image');
            $('#main-image').attr('src', newImageSrc + '?v=' + Date.now());
            $('#main-image').parent('a').attr('href', newImageSrc);
            $('.thumbnail-image').removeClass('border-primary');
            $(this).addClass('border-primary');
        });

        $('.thumbnail-image').first().addClass('border-primary');
    }

    function setStockUi(stock) {
        const s = Number(stock || 0);
        const badge = $('#stock-badge');
        const btn = $('#btn-add-to-cart');
        const qty = $('#quantity');
        if (!badge.length || !btn.length || !qty.length) return;

        if (s > 0) {
            badge.text(s + ' unidades');
            badge.attr('style', 'background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.18); color: rgba(6, 78, 59, 1);');
            btn.prop('disabled', false);
        } else {
            badge.text('Fora de estoque');
            badge.attr('style', 'background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.18); color: rgba(185, 28, 28, 1);');
            btn.prop('disabled', true);
        }
        qty.attr('max', String(Math.max(1, s)));
        const current = Number(qty.val() || 1);
        if (s > 0 && current > s) qty.val(String(s));
        if (s <= 0) qty.val('1');
    }

    function setPriceUi(price) {
        const p = Number(price || 0);
        const amount = $('.amount');
        if (!amount.length) return;
        const formatted = p.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        amount.text(formatted);
        $('.currency').text(currencyLabel);
    }

    function findMatchingVariation(selectionMap) {
        if (!variacoesUi || !variacoesUi.enabled) return null;
        const vars = variacoesUi.variacoes || [];
        for (let i = 0; i < vars.length; i++) {
            const v = vars[i];
            const map = v.map || {};
            let ok = true;
            for (const k in selectionMap) {
                if (!Object.prototype.hasOwnProperty.call(selectionMap, k)) continue;
                if (String(map[k] || '') !== String(selectionMap[k] || '')) {
                    ok = false;
                    break;
                }
            }
            if (ok) return v;
        }
        return null;
    }

    function readSelection() {
        const sel = {};
        $('.variacao-select').each(function() {
            const tipoId = $(this).data('tipo-id');
            const v = $(this).val();
            if (v && tipoId) sel[String(tipoId)] = String(v);
        });
        return sel;
    }

    function allSelected() {
        let ok = true;
        $('.variacao-select').each(function() {
            if (!$(this).val()) ok = false;
        });
        return ok;
    }

    function onVariationChange() {
        if (!variacoesUi || !variacoesUi.enabled) return;
        const selection = readSelection();
        const status = $('#variacao-status');
        const hidden = $('#produto_variacao_id');

        if (!allSelected()) {
            hidden.val('');
            status.text('Selecione as opções para ver disponibilidade.');
            setPriceUi(basePrice);
            setStockUi(<?= (int) ($produto['estoque'] ?? 0) ?>);
            renderGallery(fotosProdutoBase);
            return;
        }

        const v = findMatchingVariation(selection);
        if (!v) {
            hidden.val('');
            status.text('Combinação indisponível.');
            setStockUi(0);
            return;
        }

        hidden.val(String(v.id));
        status.text(v.descricao || 'Variação selecionada');

        const price = (v.price_override !== null && v.price_override !== undefined) ? Number(v.price_override) : basePrice;
        setPriceUi(price);
        setStockUi(v.stock);

        const fotosMap = variacoesUi.fotos_por_variacao || {};
        const fotosVar = fotosMap[String(v.id)] || fotosMap[v.id] || [];
        if (Array.isArray(fotosVar) && fotosVar.length > 0) {
            renderGallery(fotosVar.map(x => ({ url: x.url_completa || x.url, legenda: x.legenda || null })));
        } else {
            renderGallery(fotosProdutoBase);
        }
    }
    
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
        $('#main-image').attr('src', newImageSrc + '?v=' + Date.now());
        
        // Atualizar link da imagem principal
        $('#main-image').parent('a').attr('href', newImageSrc);
        
        // Atualizar classe active
        $('.thumbnail-image').removeClass('border-primary');
        $(this).addClass('border-primary');
        
        console.log('Imagem trocada para:', newImageSrc);
    });
    
    // Adicionar borda na imagem principal inicial
    $('.thumbnail-image').each(function() {
        if ($(this).data('main-image') === $('#main-image').attr('src').split('?')[0]) {
            $(this).addClass('border-primary');
        }
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

        if (variacoesUi && variacoesUi.enabled) {
            const pv = $('#produto_variacao_id').val();
            if (!pv) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Selecione a variação', text: 'Escolha as opções do produto antes de adicionar ao carrinho.' });
                } else {
                    alert('Selecione a variação antes de adicionar ao carrinho.');
                }
                return;
            }
        }
        
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
    
    if (variacoesUi && variacoesUi.enabled) {
        $('#variacoes-selectors').on('change', '.variacao-select', onVariationChange);
        // Inicial
        $('#variacao-status').text('Selecione as opções para ver disponibilidade.');
    }
}
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
