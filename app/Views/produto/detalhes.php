<?php ob_start(); ?>
<?php use App\Core\Url; ?>
<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><?= __('nav.home', 'Início') ?></a></li>
            <li class="breadcrumb-item"><a href="/produtos"><?= __('nav.products', 'Produtos') ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($produto['nome']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Galeria de Fotos -->
        <div class="col-lg-6">
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
                                 style="object-fit: cover; cursor: pointer;"
                                 title="<?= htmlspecialchars(__('product_details.click_full_size', 'Clique para ver imagem em tamanho real'), ENT_QUOTES, 'UTF-8') ?>">
                        </a>
                    <?php else: ?>
                        <div class="img-fluid rounded shadow-sm main-product-image bg-light d-flex align-items-center justify-content-center" style="height: 100%;">
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
                                     alt="<?= htmlspecialchars($foto['legenda'] ?? (__('product_details.thumbnail', 'Miniatura') . ' ' . ($index + 1))) ?>"
                                     class="img-thumbnail thumbnail-image cursor-pointer"
                                     style="height: 80px; width: 100%; object-fit: cover; cursor: pointer;"
                                     data-main-image="<?= $miniaturaAbsUrl ?>"
                                     title="<?= htmlspecialchars($foto['principal'] ? __('product_details.main_image', 'Imagem Principal') : __('product_details.click_to_view', 'Clique para ver esta imagem'), ENT_QUOTES, 'UTF-8') ?>">
                                <?php if ($foto['principal']): ?>
                                    <span class="position-absolute top-0 start-0 badge" style="font-size: 0.6em; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);"><?= __('product_details.primary', 'Principal') ?></span>
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

                <?php $variacoesUi = $variacoesUi ?? ['enabled' => false]; ?>
                <?php $variacoesEnabled = (!empty($variacoesUi['enabled']) || (!empty($variacoesUi['atributos']) && !empty($variacoesUi['variacoes']))); ?>
                <div class="buybox card mb-3" id="buybox">
                    <div class="card-body">
                        <div class="mb-2">
                            <h1 class="h2 mb-1"><?= htmlspecialchars($produto['nome']) ?></h1>
                            <div class="text-muted"><small><?= __('product_details.category', 'Categoria') ?>: <?= htmlspecialchars($produto['categoria'] ?? $produto['categoria_nome'] ?? __('product_details.no_category', 'Sem categoria')) ?></small></div>
                        </div>

                        <?php
                        $precoBase = (float) ($produto['preco'] ?? 0);
                        $precoPromo = (float) ($produto['preco_promocao'] ?? 0);
                        $temPromo = ($precoPromo > 0 && $precoPromo < $precoBase);
                        ?>

                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <?php if ($temPromo): ?>
                                <div class="text-muted text-decoration-line-through" style="font-size: 1.1rem;">
                                    <span class="original-amount" data-original-price="<?= $precoBase ?>"><?= htmlspecialchars($currencyLabel) ?> <?= number_format($precoBase, 2, ',', '.') ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="fs-4 fw-bold">
                                <span class="amount" data-original-price="<?= $temPromo ? $precoPromo : $precoBase ?>"><?= htmlspecialchars($currencyLabel) ?> <?= number_format($temPromo ? $precoPromo : $precoBase, 2, ',', '.') ?></span>
                            </div>
                        </div>

                        <div id="variacoes-card" class="mb-3" style="<?= $variacoesEnabled ? '' : 'display:none;' ?>">
                            <div class="fw-semibold mb-2"><?= __('product_details.variations', 'Variações') ?></div>
                            <div id="variacoes-selectors"></div>
                            <div class="mt-2 small text-muted" id="variacao-status"></div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="text-muted small"><?= __('product_details.availability', 'Disponibilidade') ?></div>
                            <div>
                                <?php if ($produto['estoque'] > 0): ?>
                                    <span id="stock-badge" class="badge" style="background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.18); color: rgba(6, 78, 59, 1);">
                                        <?= $produto['estoque'] ?> <?= __('product_details.units', 'unidades') ?>
                                    </span>
                                <?php else: ?>
                                    <span id="stock-badge" class="badge" style="background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.18); color: rgba(185, 28, 28, 1);">
                                        <?= __('product_details.out_of_stock', 'Fora de estoque') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form id="add-to-cart-form" class="row g-3">
                            <input type="hidden" name="id" value="<?= $produto['id'] ?>">
                            <input type="hidden" name="produto_variacao_id" id="produto_variacao_id" value="">

                            <div class="col-12">
                                <label for="quantity" class="form-label"><?= __('product_details.quantity', 'Quantidade') ?></label>
                                <div class="input-group" style="max-width: 220px;">
                                    <button type="button" class="btn btn-outline-secondary" id="decrease-qty">-</button>
                                    <input type="number" class="form-control text-center" name="quantidade" id="quantity" value="1" min="1" max="<?= $produto['estoque'] ?>">
                                    <button type="button" class="btn btn-outline-secondary" id="increase-qty">+</button>
                                </div>
                            </div>

                            <div class="col-12">
                                <button id="btn-add-to-cart" type="submit" class="btn btn-primary btn-lg w-100" <?= $produto['estoque'] > 0 ? '' : 'disabled' ?>>
                                    <?php if ($produto['estoque'] > 0): ?>
                                        <i class="fas fa-shopping-cart"></i> <?= __('product_details.add_to_cart', 'Adicionar ao Carrinho') ?>
                                    <?php else: ?>
                                        <i class="fas fa-times"></i> <?= __('product_details.unavailable', 'Produto Indisponível') ?>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="accordion" id="produtoAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingDesc">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDesc" aria-expanded="true" aria-controls="collapseDesc"><?= __('product_details.description', 'Descrição') ?></button>
                        </h2>
                        <div id="collapseDesc" class="accordion-collapse collapse show" aria-labelledby="headingDesc" data-bs-parent="#produtoAccordion">
                            <div class="accordion-body">
                                <?php
                                $descCurta = trim((string) ($produto['descricao_curta'] ?? ''));
                                $descCompleta = trim((string) ($produto['descricao'] ?? ''));
                                $descExtra = trim((string) ($produto['descricao_completa'] ?? ''));
                                ?>

                                <?php if ($descCurta !== ''): ?>
                                    <div class="text-muted"><?= nl2br(htmlspecialchars($descCurta)) ?></div>
                                <?php endif; ?>

                                <?php if ($descCompleta !== '' && $descCompleta !== $descCurta): ?>
                                    <div class="mt-3 text-muted"><?= nl2br(htmlspecialchars($descCompleta)) ?></div>
                                <?php endif; ?>

                                <?php if ($descExtra !== '' && $descExtra !== $descCurta && $descExtra !== $descCompleta): ?>
                                    <div class="mt-3 text-muted"><?= nl2br(htmlspecialchars($descExtra)) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingSpecs">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSpecs" aria-expanded="false" aria-controls="collapseSpecs"><?= __('product_details.specs', 'Especificações') ?></button>
                        </h2>
                        <div id="collapseSpecs" class="accordion-collapse collapse" aria-labelledby="headingSpecs" data-bs-parent="#produtoAccordion">
                            <div class="accordion-body">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <td><strong>SKU:</strong></td>
                                            <td><?= htmlspecialchars($produto['sku']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?= __('product_details.weight', 'Peso') ?>:</strong></td>
                                            <td><?= number_format($produto['peso'], 3, ',', '.') ?> kg</td>
                                        </tr>
                                        <?php if ($produto['comprimento'] && $produto['largura'] && $produto['altura']): ?>
                                        <tr>
                                            <td><strong><?= __('product_details.dimensions', 'Dimensões') ?>:</strong></td>
                                            <td><?= $produto['comprimento'] ?> × <?= $produto['largura'] ?> × <?= $produto['altura'] ?> cm</td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingImport">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseImport" aria-expanded="false" aria-controls="collapseImport"><?= __('product_details.import_info', 'Informações de Importação') ?></button>
                        </h2>
                        <div id="collapseImport" class="accordion-collapse collapse" aria-labelledby="headingImport" data-bs-parent="#produtoAccordion">
                            <div class="accordion-body">
                                <ul class="mb-0 small text-muted">
                                    <li><?= __('product_details.import.from_us', 'Produto importado dos EUA') ?></li>
                                    <li><?= __('product_details.import.delivery_time', 'Tempo estimado de entrega: 15-30 dias') ?></li>
                                    <li><?= __('product_details.import.taxes_included', 'Impostos inclusos no preço final') ?></li>
                                    <li><?= __('product_details.import.service_fee', 'Taxa de serviço: US$ 39/kg') ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Produtos Relacionados -->
    <?php if (!empty($produtosRelacionados)): ?>
    <div class="related-products mt-5">
        <h3 class="mb-4"><?= __('product_details.related', 'Produtos Relacionados') ?></h3>
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

.buybox {
    position: sticky;
    top: 20px;
}

.variacao-attr + .variacao-attr {
    margin-top: 14px;
}

.variacao-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.variacao-option {
    border: 1px solid rgba(15, 23, 42, 0.14);
    background: #fff;
    border-radius: 14px;
    padding: 10px 12px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
}

.variacao-option[aria-disabled="true"] {
    opacity: 0.45;
    cursor: not-allowed;
}

.variacao-option.is-selected {
    border-color: rgba(29, 78, 216, 0.55);
    box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.10);
}

.variacao-thumb {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    object-fit: cover;
    border: 1px solid rgba(15, 23, 42, 0.10);
}

.variacao-thumb-wrap {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.10);
    background: rgba(15, 23, 42, 0.04);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    overflow: hidden;
}

.main-image-container {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    aspect-ratio: 1 / 1;
    width: 100%;
    min-height: 320px;
    max-height: 560px;
}

.main-image-container > a {
    display: block;
    width: 100%;
    height: 100%;
}

.main-product-image {
    width: 100%;
    height: 100%;
    cursor: zoom-in;
    object-fit: cover;
    display: block;
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
const I18N = {
    select_variation_title: <?= json_encode(__('product_details.select_variation_title', 'Selecione a variação'), JSON_UNESCAPED_UNICODE) ?>,
    select_variation_text: <?= json_encode(__('product_details.select_variation_text', 'Este produto possui variações. Selecione as opções antes de adicionar ao carrinho.'), JSON_UNESCAPED_UNICODE) ?>,
    select_variation_before_add: <?= json_encode(__('product_details.select_variation_before_add', 'Selecione a variação antes de adicionar ao carrinho.'), JSON_UNESCAPED_UNICODE) ?>,
    choose_options_before_add: <?= json_encode(__('product_details.choose_options_before_add', 'Escolha as opções do produto antes de adicionar ao carrinho.'), JSON_UNESCAPED_UNICODE) ?>,
    variations_select_to_check: <?= json_encode(__('product_details.variations_select_to_check', 'Selecione as opções para ver disponibilidade.'), JSON_UNESCAPED_UNICODE) ?>,
    variation_unavailable: <?= json_encode(__('product_details.variation_unavailable', 'Combinação indisponível.'), JSON_UNESCAPED_UNICODE) ?>,
    variation_selected: <?= json_encode(__('product_details.variation_selected', 'Variação selecionada'), JSON_UNESCAPED_UNICODE) ?>,
    out_of_stock: <?= json_encode(__('product_details.out_of_stock', 'Fora de estoque'), JSON_UNESCAPED_UNICODE) ?>,
    units: <?= json_encode(__('product_details.units', 'unidades'), JSON_UNESCAPED_UNICODE) ?>,
    adding: <?= json_encode(__('products.adding', 'Adicionando...'), JSON_UNESCAPED_UNICODE) ?>,
    success_title: <?= json_encode(__('common.success', 'Sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    added_to_cart: <?= json_encode(__('product_details.added_to_cart', 'Produto adicionado ao carrinho'), JSON_UNESCAPED_UNICODE) ?>,
    added_to_cart_alert: <?= json_encode(__('product_details.added_to_cart_alert', 'Produto adicionado ao carrinho!'), JSON_UNESCAPED_UNICODE) ?>,
    error_title: <?= json_encode(__('common.error', 'Erro'), JSON_UNESCAPED_UNICODE) ?>,
    cant_add: <?= json_encode(__('product_details.cant_add', 'Não foi possível adicionar o produto ao carrinho'), JSON_UNESCAPED_UNICODE) ?>,
    error_add_try_again: <?= json_encode(__('product_details.error_add_try_again', 'Erro ao adicionar produto ao carrinho. Tente novamente.'), JSON_UNESCAPED_UNICODE) ?>
};

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

    try {
        const params = new URLSearchParams(window.location.search || '');
        const pedirSelecao = params.get('selecionar_variacao');
        if (pedirSelecao === '1' || (pedirSelecao || '').toLowerCase() === 'true') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: I18N.select_variation_title,
                    text: I18N.select_variation_text
                });
            } else {
                alert(I18N.select_variation_text);
            }

            params.delete('selecionar_variacao');
            const newQs = params.toString();
            const newUrl = window.location.pathname + (newQs ? ('?' + newQs) : '') + (window.location.hash || '');
            window.history.replaceState({}, document.title, newUrl);

            const target = document.getElementById('variacoes-card') || document.getElementById('buybox');
            if (target && typeof target.scrollIntoView === 'function') {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    } catch (e) {
        // noop
    }

    const variacoesUi = <?= json_encode($variacoesUi ?? ['enabled' => false]) ?>;
    const variacoesEnabled = !!(variacoesUi && (variacoesUi.enabled || ((variacoesUi.atributos || []).length > 0 && (variacoesUi.variacoes || []).length > 0)));
    const fotosProdutoBase = <?= json_encode(array_values(array_map(function($f) {
        return [
            'url' => ($f['url_completa'] ?? (isset($f['nome_arquivo']) ? Url::absolute((string) $f['nome_arquivo']) : null)),
            'legenda' => ($f['legenda'] ?? null),
        ];
    }, $fotos ?? []))) ?>;

    const basePrice = Number($('.amount').data('original-price') || 0);

    const produtoId = <?= (int) ($produto['id'] ?? 0) ?>;

    function normalizeVariacoesUi(raw) {
        const r = raw && typeof raw === 'object' ? raw : {};
        const attrs = Array.isArray(r.atributos) ? r.atributos : [];
        const vars = Array.isArray(r.variacoes) ? r.variacoes : [];
        const fotos = r.fotos_por_variacao && typeof r.fotos_por_variacao === 'object' ? r.fotos_por_variacao : {};
        const enabled = !!(r.enabled || (attrs.length > 0 && vars.length > 0));
        return { enabled, atributos: attrs, variacoes: vars, fotos_por_variacao: fotos };
    }

    function renderVariacaoSelectors(ui) {
        const container = $('#variacoes-selectors');
        if (!container.length) return;
        container.empty();

        (ui.atributos || []).forEach((attr) => {
            const tipoId = Number(attr.tipo_id || 0);
            const nome = String(attr.nome || '');
            const block = $('<div class="variacao-attr"></div>');
            const label = $('<div class="form-label fw-semibold mb-2"></div>').text(nome);
            const options = $('<div class="variacao-options" role="list"></div>');
            options.attr('data-tipo-id', String(tipoId));

            (attr.opcoes || []).forEach((op) => {
                const oid = Number(op.opcao_id || 0);
                const display = String(op.nome_exibicao || op.valor || '');
                const img = String(op.imagem || '');

                const btn = $('<button type="button" class="variacao-option" role="listitem"></button>');
                btn.attr('data-tipo-id', String(tipoId));
                btn.attr('data-opcao-id', String(oid));
                btn.attr('aria-disabled', 'false');

                const thumbWrap = $('<span class="variacao-thumb-wrap" aria-hidden="true"></span>');
                if (img) {
                    const thumb = $('<img class="variacao-thumb" alt="" />');
                    thumb.attr('src', img);
                    thumbWrap.append(thumb);
                }
                btn.append(thumbWrap);
                if (display) {
                    const txt = $('<span class="variacao-label"></span>').text(display);
                    btn.append(txt);
                }

                options.append(btn);
            });

            block.append(label);
            block.append(options);
            container.append(block);
        });
    }

    let variacoesState = normalizeVariacoesUi(variacoesUi);

    function getCandidateVariations(selectionMap) {
        if (!variacoesState.enabled) return [];
        const vars = Array.isArray(variacoesState.variacoes) ? variacoesState.variacoes : [];
        const keys = Object.keys(selectionMap || {});
        if (keys.length === 0) return vars;
        return vars.filter((v) => {
            const map = v && v.map ? v.map : {};
            for (let i = 0; i < keys.length; i++) {
                const k = keys[i];
                if (String(map[k] || '') !== String(selectionMap[k] || '')) return false;
            }
            return true;
        });
    }

    function refreshOptionAvailability() {
        if (!variacoesState.enabled) return;

        const currentSel = readSelection();
        const allVars = Array.isArray(variacoesState.variacoes) ? variacoesState.variacoes : [];

        const $groups = $('.variacao-options[data-tipo-id]');
        $groups.each(function() {
            const $group = $(this);
            const tipoId = String($group.data('tipo-id') || '');
            if (!tipoId) return;

            const selMinusThis = { ...currentSel };
            delete selMinusThis[tipoId];
            const candidates = getCandidateVariations(selMinusThis);

            const allowed = new Set();
            candidates.forEach((v) => {
                const map = v && v.map ? v.map : {};
                if (map[tipoId] !== null && map[tipoId] !== undefined) {
                    allowed.add(String(map[tipoId]));
                }
            });

            $group.find('.variacao-option').each(function() {
                const $btn = $(this);
                const oid = String($btn.data('opcao-id') || '');
                const ok = allowed.has(oid);
                $btn.attr('aria-disabled', ok ? 'false' : 'true');
                $btn.prop('disabled', !ok);
                if (!ok) {
                    $btn.removeClass('is-selected');
                }
            });
        });

        const stillSel = readSelection();
        const status = $('#variacao-status');
        if (Object.keys(stillSel).length === 0 && allVars.length > 0) {
            if (status.length) status.text(I18N.variations_select_to_check);
        }
    }

    function findMatchingVariationDynamic(selectionMap) {
        if (!variacoesState.enabled) return null;
        const vars = variacoesState.variacoes || [];
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
            badge.text(s + ' ' + I18N.units);
            badge.attr('style', 'background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.18); color: rgba(6, 78, 59, 1);');
            btn.prop('disabled', false);
        } else {
            badge.text(I18N.out_of_stock);
            badge.attr('style', 'background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.18); color: rgba(185, 28, 28, 1);');
            btn.prop('disabled', true);
        }
        qty.attr('max', String(Math.max(1, s)));
        const current = Number(qty.val() || 1);
        if (s > 0 && current > s) qty.val(String(s));
        if (s <= 0) qty.val('1');
    }

    function setPriceUi(priceUsd) {
        const p = Number(priceUsd || 0);
        const amount = $('.amount');
        if (!amount.length) return;

        // Manter o valor original em USD para o conversor global
        amount.attr('data-original-price', String(p));

        // Renderizar o valor conforme a moeda selecionada
        const cc = (window.CurrencyConverter && typeof window.CurrencyConverter === 'object') ? window.CurrencyConverter : null;
        const cur = (cc && cc.currentCurrency) ? String(cc.currentCurrency) : (localStorage.getItem('selected_currency') || 'USD');
        const rates = (cc && cc.exchangeRates) ? cc.exchangeRates : (window.exchangeRates || { BRL: 5.50, USD: 1.00 });
        const rate = Number(rates[cur] || 1);
        const symbol = (cur === 'BRL') ? 'R$' : '$';
        const converted = p * rate;
        const formatted = converted.toFixed(2).replace('.', ',');
        amount.text(symbol + ' ' + formatted);
    }

    function findMatchingVariation(selectionMap) {
        return findMatchingVariationDynamic(selectionMap);
    }

    function readSelection() {
        const sel = {};
        if ($('.variacao-option').length) {
            $('.variacao-option.is-selected').each(function() {
                const tipoId = $(this).data('tipo-id');
                const oid = $(this).data('opcao-id');
                if (tipoId && oid) sel[String(tipoId)] = String(oid);
            });
        } else {
            $('.variacao-select').each(function() {
                const tipoId = $(this).data('tipo-id');
                const v = $(this).val();
                if (v && tipoId) sel[String(tipoId)] = String(v);
            });
        }
        return sel;
    }

    function allSelected() {
        const attrs = Array.isArray(variacoesState.atributos) ? variacoesState.atributos : [];
        if (attrs.length === 0) return true;
        const sel = readSelection();
        return attrs.every((a) => {
            const tid = String(a.tipo_id || '');
            return tid && !!sel[tid];
        });
    }

    function onVariationChange() {
        if (!variacoesState.enabled) return;
        refreshOptionAvailability();
        const selection = readSelection();
        const status = $('#variacao-status');
        const hidden = $('#produto_variacao_id');

        if (!allSelected()) {
            hidden.val('');
            status.text(I18N.variations_select_to_check);
            setPriceUi(basePrice);
            setStockUi(<?= (int) ($produto['estoque'] ?? 0) ?>);
            renderGallery(fotosProdutoBase);
            return;
        }

        const v = findMatchingVariation(selection);
        if (!v) {
            hidden.val('');
            status.text(I18N.variation_unavailable);
            setStockUi(0);
            return;
        }

        hidden.val(String(v.id));
        status.text(v.descricao || I18N.variation_selected);

        const priceUsd = (v.price_override !== null && v.price_override !== undefined) ? Number(v.price_override) : basePrice;
        setPriceUi(priceUsd);
        setStockUi(v.stock);

        const fotosMap = variacoesState.fotos_por_variacao || {};
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

        if (variacoesUi && variacoesEnabled) {
            const pv = $('#produto_variacao_id').val();
            if (!pv) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: I18N.select_variation_title, text: I18N.choose_options_before_add });
                } else {
                    alert(I18N.select_variation_before_add);
                }
                return;
            }
        }
        
        console.log('Formulário submetido');
        
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + I18N.adding);
        
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
                            title: I18N.success_title,
                            text: I18N.added_to_cart,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        alert(I18N.added_to_cart_alert);
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
                            title: I18N.error_title,
                            text: response.error || I18N.cant_add
                        });
                    } else {
                        alert(I18N.error_title + ': ' + (response.error || I18N.cant_add));
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
                        title: I18N.error_title,
                        text: I18N.error_add_try_again
                    });
                } else {
                    alert(I18N.error_add_try_again);
                }
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    function updateCartBadge(totalItens) {
        const n = Number(totalItens || 0);

        // Navbar badge
        let $badges = $('.cart-badge');
        if ($badges.length === 0) {
            // Criar badge se não existir (quando estava 0 na renderização inicial)
            const $cartLink = $('a.nav-link.position-relative[href="/carrinho"]').first();
            if ($cartLink && $cartLink.length) {
                $cartLink.append('<span class="cart-badge"></span>');
            }
            const $floatBtn = $('.floating-cart button');
            if ($floatBtn && $floatBtn.length) {
                $floatBtn.append('<span class="cart-badge"></span>');
            }
            $badges = $('.cart-badge');
        }

        $badges.each(function() {
            const $b = $(this);
            if (n > 0) {
                $b.text(String(n));
                $b.css('display', 'flex');
            } else {
                $b.css('display', 'none');
            }
        });
    }
    
    if (variacoesState.enabled) {
        $('#variacoes-card').show();
        renderVariacaoSelectors(variacoesState);
        $('#variacoes-selectors').on('change', '.variacao-select', onVariationChange);
        $('#variacoes-selectors').on('click', '.variacao-option', function() {
            const $btn = $(this);
            if ($btn.attr('aria-disabled') === 'true' || $btn.prop('disabled')) return;
            const tipoId = String($btn.data('tipo-id') || '');
            if (!tipoId) return;

            $('.variacao-option[data-tipo-id="' + tipoId.replace(/"/g, '') + '"]').removeClass('is-selected');
            $btn.addClass('is-selected');
            onVariationChange();
        });
        // Inicial
        $('#variacao-status').text(I18N.variations_select_to_check);
        refreshOptionAvailability();
    }

    if (!variacoesState.enabled && produtoId > 0) {
        fetch('/produto/variacoes/' + produtoId, { credentials: 'same-origin' })
            .then(async (r) => {
                const contentType = (r.headers.get('content-type') || '').toLowerCase();
                const text = await r.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    data = null;
                }

                if (!data || typeof data !== 'object') {
                    $('#variacoes-card').hide();
                    return;
                }

                variacoesState = normalizeVariacoesUi(data);
                if (!variacoesState.enabled) {
                    $('#variacoes-card').hide();
                    return;
                }

                $('#variacoes-card').show();
                const status = $('#variacao-status');
                if (status.length) {
                    status.text(I18N.variations_select_to_check);
                }

                renderVariacaoSelectors(variacoesState);
                $('#variacoes-selectors').off('change.variacoesDyn').on('change.variacoesDyn', '.variacao-select', onVariationChange);
                $('#variacoes-selectors').off('click.variacoesDyn').on('click.variacoesDyn', '.variacao-option', function() {
                    const $btn = $(this);
                    if ($btn.attr('aria-disabled') === 'true' || $btn.prop('disabled')) return;
                    const tipoId = String($btn.data('tipo-id') || '');
                    if (!tipoId) return;
                    $('.variacao-option[data-tipo-id="' + tipoId.replace(/"/g, '') + '"]').removeClass('is-selected');
                    $btn.addClass('is-selected');
                    onVariationChange();
                });
                refreshOptionAvailability();
            })
            .catch(() => {
                $('#variacoes-card').hide();
            });
    }
}
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
