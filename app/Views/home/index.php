<?php ob_start(); ?>
<!-- Hero Section -->
<section class="hero-section">
    <?php
    $layoutBanners = [];
    try {
        $locale = class_exists('\\App\\Core\\I18n') ? (string) \App\Core\I18n::getLocaleHtml() : 'pt-BR';
        $isEn = (stripos($locale, 'en') === 0);
        $layoutKey = $isEn ? 'banners_en' : 'banners';
        $layoutKeyLegacy = 'banners';
        $layoutColKey = $isEn ? 'layout_banners_en' : 'layout_banners';
        $layoutColKeyLegacy = 'layout_banners';

        $pdo = \Config\Database::getConnection();
        $raw = '';

        $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        foreach ($tablesToTry as $t) {
            if ($raw !== '') break;
            try {
                $stmtT = $pdo->prepare('SHOW TABLES LIKE ?');
                $stmtT->execute([$t]);
                if (!$stmtT->fetchColumn()) {
                    continue;
                }

                $stmtCols = $pdo->query('DESCRIBE ' . $t);
                $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
                if (!is_array($cols)) {
                    $cols = [];
                }

                // schema categoria+chave+valor
                if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                    $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                    if ($valCol !== '') {
                        $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                        $stmt->execute(['layout', $layoutKey]);
                        $raw = (string) ($stmt->fetchColumn() ?: '');
                        if ($raw === '' && $layoutKey !== $layoutKeyLegacy) {
                            $stmt->execute(['layout', $layoutKeyLegacy]);
                            $raw = (string) ($stmt->fetchColumn() ?: '');
                        }
                        if ($raw !== '') break;
                    }
                }

                // schema key/value
                $keyCol = '';
                if (in_array('chave', $cols, true)) $keyCol = 'chave';
                elseif (in_array('key', $cols, true)) $keyCol = 'key';
                elseif (in_array('nome', $cols, true)) $keyCol = 'nome';
                elseif (in_array('config_key', $cols, true)) $keyCol = 'config_key';
                $valCol = '';
                if (in_array('valor', $cols, true)) $valCol = 'valor';
                elseif (in_array('value', $cols, true)) $valCol = 'value';
                elseif (in_array('conteudo', $cols, true)) $valCol = 'conteudo';
                if ($keyCol !== '' && $valCol !== '') {
                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                    $stmt->execute([$layoutColKey]);
                    $raw = (string) ($stmt->fetchColumn() ?: '');
                    if ($raw === '' && $layoutColKey !== $layoutColKeyLegacy) {
                        $stmt->execute([$layoutColKeyLegacy]);
                        $raw = (string) ($stmt->fetchColumn() ?: '');
                    }
                    if ($raw !== '') break;
                }

                // schema single_row (coluna direta)
                if (in_array($layoutColKey, $cols, true) || in_array($layoutColKeyLegacy, $cols, true)) {
                    $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                    $col = in_array($layoutColKey, $cols, true) ? $layoutColKey : $layoutColKeyLegacy;
                    $stmt2 = $pdo->query('SELECT ' . $col . ' AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                    $raw = (string) ($stmt2->fetchColumn() ?: '');
                    if ($raw !== '') break;
                }
            } catch (\Exception $e) {
            }
        }

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item)) {
                        $src = trim($item);
                        if ($src === '') continue;
                        $layoutBanners[] = ['desktop' => $src, 'mobile' => '', 'link' => ''];
                        continue;
                    }
                    if (is_array($item)) {
                        $d = isset($item['desktop']) && is_string($item['desktop']) ? trim((string) $item['desktop']) : '';
                        $m = isset($item['mobile']) && is_string($item['mobile']) ? trim((string) $item['mobile']) : '';
                        $l = isset($item['link']) && is_string($item['link']) ? trim((string) $item['link']) : '';
                        if ($d === '' && $m === '') continue;
                        $layoutBanners[] = ['desktop' => $d, 'mobile' => $m, 'link' => $l];
                        continue;
                    }
                }
            }
        }
    } catch (\Exception $e) {
        $layoutBanners = [];
    }
    ?>

    <?php if (!empty($layoutBanners)): ?>
        <div class="container">
            <div class="hero-image" data-aos="fade-left">
                <div id="homeHeroBanners" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
                    <div class="carousel-inner" style="overflow: hidden;">
                        <?php foreach ($layoutBanners as $i => $banner): ?>
                            <?php
                                $desktopSrc = is_array($banner) ? (string) ($banner['desktop'] ?? '') : '';
                                $mobileSrc = is_array($banner) ? (string) ($banner['mobile'] ?? '') : '';
                                $linkHref = is_array($banner) ? (string) ($banner['link'] ?? '') : '';
                                if ($desktopSrc === '' && $mobileSrc !== '') $desktopSrc = $mobileSrc;
                                if ($mobileSrc === '' && $desktopSrc !== '') $mobileSrc = $desktopSrc;
                            ?>
                            <div class="carousel-item <?= ($i === 0 ? 'active' : '') ?>">
                                <?php if (!empty($linkHref)): ?>
                                    <a href="<?= htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') ?>" style="display:block;">
                                        <div class="home-hero-banner">
                                            <picture>
                                                <source media="(max-width: 767px)" srcset="<?= htmlspecialchars($mobileSrc, ENT_QUOTES, 'UTF-8') ?>">
                                                <img src="<?= htmlspecialchars($desktopSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Banner" class="w-100 h-100">
                                            </picture>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="home-hero-banner">
                                        <picture>
                                            <source media="(max-width: 767px)" srcset="<?= htmlspecialchars($mobileSrc, ENT_QUOTES, 'UTF-8') ?>">
                                            <img src="<?= htmlspecialchars($desktopSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Banner" class="w-100 h-100">
                                        </picture>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($layoutBanners) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#homeHeroBanners" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden"><?= __('common.previous', 'Anterior') ?></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#homeHeroBanners" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden"><?= __('common.next', 'Próximo') ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- Features Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title" data-aos="fade-up"><?= __('home.why_title', 'Por que escolher a Braziliana?') ?></h2>
            <p class="section-subtitle" data-aos="fade-up"><?= __('home.why_subtitle', 'Oferecemos a melhor experiência de importação com tecnologia e confiança') ?></p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card card h-100 p-4">
                    <div class="text-center mb-3">
                        <div class="feature-icon rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14);">
                            <i class="fas fa-shield-alt fa-2x" style="color: rgba(11, 31, 58, 1);"></i>
                        </div>
                        <h5><?= __('home.feature.secure_buy', 'Compra Segura') ?></h5>
                    </div>
                    <p class="text-muted"><?= __('home.feature.secure_buy_desc', 'Pagamento 100% seguro com proteção anti-fraude e criptografia de dados.') ?></p>
                </div>
            </div>
            
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card card h-100 p-4">
                    <div class="text-center mb-3">
                        <div class="feature-icon rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14);">
                            <i class="fas fa-plane fa-2x" style="color: rgba(11, 31, 58, 1);"></i>
                        </div>
                        <h5><?= __('home.feature.full_logistics', 'Logística Completa') ?></h5>
                    </div>
                    <p class="text-muted"><?= __('home.feature.full_logistics_desc', 'Do despacho nos EUA até a entrega na sua porta, cuidamos de tudo.') ?></p>
                </div>
            </div>
            
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card card h-100 p-4">
                    <div class="text-center mb-3">
                        <div class="feature-icon rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14);">
                            <i class="fas fa-calculator fa-2x" style="color: rgba(11, 31, 58, 1);"></i>
                        </div>
                        <h5><?= __('home.feature.transparent_prices', 'Preços Transparentes') ?></h5>
                    </div>
                    <p class="text-muted"><?= __('home.feature.transparent_prices_desc', 'Sem taxas escondidas. Você vê o valor final antes de comprar.') ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Products Preview Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title" data-aos="fade-up"><?= __('home.featured_title', 'Produtos em Destaque') ?></h2>
            <p class="section-subtitle" data-aos="fade-up"><?= __('home.featured_subtitle', 'Conheça nossos produtos mais populares') ?></p>
        </div>
        
        <div class="row g-4" id="produtos-destaque">
            <!-- Produtos serão carregados via AJAX -->
        </div>
        
        <div class="text-center mt-4">
            <a href="/produtos" class="btn btn-outline-primary btn-lg" data-aos="fade-up">
                <i class="fas fa-th me-2"></i> <?= __('home.view_all_products', 'Ver Todos os Produtos') ?>
            </a>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title" data-aos="fade-up"><?= __('home.how_title', 'Como Funciona') ?></h2>
            <p class="section-subtitle" data-aos="fade-up"><?= __('home.how_subtitle', 'Importar nunca foi tão simples') ?></p>
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
                            <h5><?= __('home.step1_title', 'Escolha seus Produtos') ?></h5>
                            <p class="text-muted"><?= __('home.step1_desc', 'Navegue pelo nosso catálogo e selecione os produtos desejados.') ?></p>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="timeline-item d-flex mb-4" data-aos="fade-left">
                        <div class="timeline-number rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                            <span class="fw-bold">2</span>
                        </div>
                        <div class="timeline-content">
                            <h5><?= __('home.step2_title', 'Pague de Forma Segura') ?></h5>
                            <p class="text-muted"><?= __('home.step2_desc', 'Checkout seguro com múltiplas opções de pagamento.') ?></p>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="timeline-item d-flex mb-4" data-aos="fade-right">
                        <div class="timeline-number rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                            <span class="fw-bold">3</span>
                        </div>
                        <div class="timeline-content">
                            <h5><?= __('home.step3_title', 'Acompanhe o Processo') ?></h5>
                            <p class="text-muted"><?= __('home.step3_desc', 'Acompanhe cada etapa desde o despacho até a entrega.') ?></p>
                        </div>
                    </div>
                    
                    <!-- Step 4 -->
                    <div class="timeline-item d-flex mb-4" data-aos="fade-left">
                        <div class="timeline-number rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 50px; height: 50px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                            <span class="fw-bold">4</span>
                        </div>
                        <div class="timeline-content">
                            <h5><?= __('home.step4_title', 'Receba em Casa') ?></h5>
                            <p class="text-muted"><?= __('home.step4_desc', 'Receba seus produtos diretamente na sua porta.') ?></p>
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
            <h2 class="section-title" data-aos="fade-up"><?= __('home.testimonials_title', 'O que nossos clientes dizem') ?></h2>
            <p class="section-subtitle" data-aos="fade-up"><?= __('home.testimonials_subtitle', 'Milhares de clientes satisfeitos') ?></p>
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
                <h2 class="mb-4"><?= __('home.cta_title', 'Pronto para começar a importar?') ?></h2>
                <p class="lead mb-4"><?= __('home.cta_subtitle', 'Junte-se a milhares de clientes que economizam comprando diretamente dos EUA') ?></p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="/register" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i> <?= __('home.cta_create_account', 'Criar Conta Gratuita') ?>
                    </a>
                    <a href="/produtos" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-eye me-2"></i> <?= __('home.cta_view_products', 'Ver Produtos') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    const UI = {
        badge_last_units: <?= json_encode(__('home.badge_last_units', 'Últimas unidades'), JSON_UNESCAPED_UNICODE) ?>,
        details: <?= json_encode(__('home.details', 'Ver Detalhes'), JSON_UNESCAPED_UNICODE) ?>,
        no_featured: <?= json_encode(__('home.no_featured', 'Nenhum produto em destaque no momento.'), JSON_UNESCAPED_UNICODE) ?>,
        cant_load: <?= json_encode(__('home.cant_load_featured', 'Não foi possível carregar produtos em destaque.'), JSON_UNESCAPED_UNICODE) ?>,
        units_short: <?= json_encode(__('home.units_short', 'unid.'), JSON_UNESCAPED_UNICODE) ?>
    };
    const LOCALE = <?= json_encode((class_exists('\\App\\Core\\I18n') ? \App\Core\I18n::getLocale() : 'pt-BR'), JSON_UNESCAPED_UNICODE) ?>;

    function formatMoney(value) {
        const num = Number(value || 0);
        try {
            const locale = (String(LOCALE).toLowerCase() === 'en') ? 'en-US' : 'pt-BR';
            return new Intl.NumberFormat(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
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
                var clubeAcesso = response.clube_acesso || false;
                let html = '';
                response.produtos.forEach(function(produto) {
                    var isClubeBlocked = produto.clube_only && !clubeAcesso;
                    var precoHtml = isClubeBlocked
                        ? '<span class="badge" style="background:#0b1f3a;"><i class="fas fa-crown me-1"></i>Exclusivo Clube</span>'
                        : '<span class="h5 mb-0 text-primary">' + produto.moeda + ' ' + formatMoney(produto.valor) + '</span>';
                    var btnHtml = isClubeBlocked
                        ? '<a href="/grupo-compras" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-crown me-2"></i>Saiba mais sobre o Clube</a>'
                        : '<a href="/produto/detalhes/' + produto.id + '" class="btn btn-outline-primary btn-sm w-100">' + UI.details + '</a>';
                    html += `
                        <div class="col-lg-3 col-md-6">
                            <div class="product-card card h-100">
                                <div class="position-relative overflow-hidden">
                                    <img src="${produto.foto_principal || '/uploads/produtos/placeholder.jpg'}" 
                                         alt="${produto.nome}" 
                                         class="product-image card-img-top">
                                    ${produto.estoque <= 5 && !isClubeBlocked ? '<span class="position-absolute top-0 end-0 m-2 badge" style="background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.35); color: rgba(124, 45, 18, 1);">' + UI.badge_last_units + '</span>' : ''}
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title">${produto.nome}</h6>
                                    <p class="text-muted small">${produto.categoria}</p>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        ${precoHtml}
                                        ${!isClubeBlocked ? '<small class="text-muted">' + produto.estoque + ' ' + UI.units_short + '</small>' : ''}
                                    </div>
                                    ${btnHtml}
                                </div>
                            </div>
                        </div>
                    `;
                });
                $('#produtos-destaque').html(html);
            } else {
                $('#produtos-destaque').html('<div class="col-12 text-center"><p class="text-muted">' + UI.no_featured + '</p></div>');
            }
        },
        error: function() {
            $('#produtos-destaque').html('<div class="col-12 text-center"><p class="text-muted">' + UI.cant_load + '</p></div>');
        }
    });
});
</script>

<style>
.hero-section {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
    background: transparent !important;
}

.hero-section .container {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

.hero-section .hero-image {
    position: relative;
    padding: 0;
    border-radius: 0;
    background: transparent;
    border: none;
    box-shadow: none;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
    overflow: hidden;
}

.hero-section .hero-image::before {
    display: none;
}

.hero-section .hero-image img {
    position: relative;
    display: block;
    width: 100%;
    border-radius: 0;
    box-shadow: none;
    filter: none;
    transform: none;
}

.hero-section .home-hero-banner {
    width: 100%;
    aspect-ratio: 1149 / 436;
    background: transparent;
    margin-left: 0;
    margin-right: 0;
    margin-top: 50px;
    border-radius: 18px;
    overflow: hidden;
}

.hero-section .home-hero-banner img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
}

@media (max-width: 767px) {
    .hero-section .home-hero-banner {
        aspect-ratio: 391 / 333;
    }
}

.hero-section .carousel-control-prev,
.hero-section .carousel-control-next {
    width: 8%;
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
