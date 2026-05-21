<?php ob_start(); ?>
<?php use App\Core\Url; ?>
<?php
// === DATA PREPARATION ===
$produtoNome = htmlspecialchars($produto['nome'] ?? '');
$produtoMoeda = $produto['moeda'] ?? 'USD';
$precoBase = (float)($produto['preco'] ?? $produto['valor'] ?? 0);
$precoPromo = (float)($produto['preco_promocao'] ?? $produto['preco_promo'] ?? 0);
$precoFinal = ($precoPromo > 0 && $precoPromo < $precoBase) ? $precoPromo : $precoBase;
$temDesconto = ($precoPromo > 0 && $precoPromo < $precoBase);
$estoque = (int)($produto['estoque'] ?? 0);
$marca = trim((string)($produto['marca'] ?? $produto['brand'] ?? ''));
$peso = (float)($produto['peso'] ?? $produto['weight'] ?? 0);
$sku = trim((string)($produto['sku'] ?? ''));
$categoriaNome = htmlspecialchars($produto['categoria_nome'] ?? $produto['categoria'] ?? '');

// Photos
if (empty($fotoPrincipal) && !empty($fotos)) {
    foreach ($fotos as $f) { if (!empty($f['principal'])) { $fotoPrincipal = $f; break; } }
    if (empty($fotoPrincipal)) $fotoPrincipal = $fotos[0] ?? null;
}
$mainImgUrl = '';
if (!empty($fotoPrincipal['nome_arquivo'])) {
    $mainImgUrl = $fotoPrincipal['url_completa'] ?? Url::absolute($fotoPrincipal['nome_arquivo']);
} elseif (!empty($produto['foto_principal'])) {
    $mainImgUrl = $produto['foto_principal'];
}

// AI Description & Benefits
$descCompleta = trim((string)($produto['descricao'] ?? $produto['description'] ?? ''));
$descCurta = trim((string)($produto['descricao_curta'] ?? ''));
$benefits = [];
$currentLocale = class_exists('\\App\\Core\\I18n') ? \App\Core\I18n::getLocale() : 'pt-BR';
try {
    $pdoDesc = \Config\Database::getConnection();
    $stDesc = $pdoDesc->prepare("SELECT descricao_gerada, descricao_gerada_en, descricao_editada, descricao_editada_en, benefits_gerados, benefits_gerados_en FROM produto_descricoes_ia WHERE produto_id = ? AND status_revisao = 'aprovado' LIMIT 1");
    $stDesc->execute([(int)$produto['id']]);
    $rowDesc = $stDesc->fetch(\PDO::FETCH_ASSOC);
    if ($rowDesc) {
        if ($currentLocale === 'en') {
            $descIA = trim((string)($rowDesc['descricao_editada_en'] ?: $rowDesc['descricao_gerada_en']));
            $benefitsJson = $rowDesc['benefits_gerados_en'] ?? $rowDesc['benefits_gerados'];
        } else {
            $descIA = trim((string)($rowDesc['descricao_editada'] ?: $rowDesc['descricao_gerada']));
            $benefitsJson = $rowDesc['benefits_gerados'] ?? $rowDesc['benefits_gerados_en'];
        }
        if ($descIA !== '') $descCompleta = $descIA;
        if ($benefitsJson) {
            $decoded = is_string($benefitsJson) ? json_decode($benefitsJson, true) : $benefitsJson;
            if (is_array($decoded) && !empty($decoded)) $benefits = $decoded;
        }
    }
} catch (\Exception $e) {}

// Default benefits if none from IA
if (empty($benefits)) {
    $benefits = [
        ['icon' => 'fa-solid fa-shield', 'title' => __('product_details.benefit_original', 'Produto Original'), 'description' => __('product_details.benefit_original_desc', 'Importado diretamente dos EUA com garantia de autenticidade.')],
        ['icon' => 'fa-solid fa-truck-fast', 'title' => __('product_details.benefit_shipping', 'Envio Internacional'), 'description' => __('product_details.benefit_shipping_desc', 'Entrega rastreada para o Brasil e mundo todo.')],
        ['icon' => 'fa-solid fa-box', 'title' => __('product_details.benefit_packaging', 'Embalagem Segura'), 'description' => __('product_details.benefit_packaging_desc', 'Produto embalado com cuidado para transporte internacional.')],
        ['icon' => 'fa-solid fa-headset', 'title' => __('product_details.benefit_support', 'Suporte Dedicado'), 'description' => __('product_details.benefit_support_desc', 'Atendimento especializado em português.')],
    ];
}

// Format price
function fmtPrice($val, $moeda) {
    if ($moeda === 'USD') return '$ ' . number_format($val, 2, '.', ',');
    return 'R$ ' . number_format($val, 2, ',', '.');
}
?>

<?php if (!empty($clube_bloqueado)): ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm text-center p-5">
                <div class="mb-3"><i class="fas fa-crown fa-3x" style="color:#0b1f3a;"></i></div>
                <h4><?= __('product_details.clube_exclusive', 'Produto exclusivo do Clube Braziliana') ?></h4>
                <p class="text-muted"><?= __('product_details.clube_desc', 'Este produto está disponível apenas para membros do Clube Braziliana.') ?></p>
                <div class="d-grid gap-2 mt-3" style="max-width:300px;margin:0 auto;">
                    <a href="/como-funciona-clube" class="btn btn-primary"><i class="fas fa-crown me-2"></i><?= __('product_details.clube_learn', 'Conhecer o Clube') ?></a>
                    <a href="/produtos" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i><?= __('nav.back', 'Voltar') ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>

<div class="pdp-container">
    <div class="pdp-layout">
        <!-- LEFT: Gallery -->
        <div class="pdp-gallery">
            <div class="pdp-main-image">
                <?php if ($mainImgUrl): ?>
                    <img id="pdp-main-img" src="<?= htmlspecialchars($mainImgUrl) ?>" alt="<?= $produtoNome ?>">
                <?php else: ?>
                    <div class="pdp-no-image"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($fotos) && count($fotos) > 1): ?>
            <div class="pdp-thumbs">
                <?php foreach (array_slice($fotos, 0, 5) as $i => $foto): 
                    $thumbUrl = $foto['url_completa'] ?? Url::absolute($foto['nome_arquivo'] ?? '');
                    if (!$thumbUrl) continue;
                ?>
                <div class="pdp-thumb <?= $i === 0 ? 'active' : '' ?>" onclick="document.getElementById('pdp-main-img').src='<?= htmlspecialchars($thumbUrl) ?>';document.querySelectorAll('.pdp-thumb').forEach(t=>t.classList.remove('active'));this.classList.add('active');">
                    <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Product Info -->
        <div class="pdp-info">
            <!-- Badges -->
            <div class="pdp-badges">
                <?php if ($estoque > 0): ?>
                <div class="pdp-badge"><i class="fa-solid fa-circle-check"></i><?= __('product_details.in_stock', 'Em estoque nos EUA') ?></div>
                <?php endif; ?>
                <div class="pdp-badge"><i class="fa-solid fa-shield"></i><?= __('product_details.original', 'Produto Original') ?></div>
                <div class="pdp-badge"><i class="fa-solid fa-truck-fast"></i><?= __('product_details.intl_shipping', 'Envio Internacional') ?></div>
            </div>

            <!-- Title -->
            <h1 class="pdp-title"><?= $produtoNome ?></h1>

            <?php if ($marca): ?>
            <div class="pdp-brand"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($marca) ?></div>
            <?php endif; ?>

            <!-- Price -->
            <div class="pdp-price-box">
                <?php if ($temDesconto): ?>
                <div class="pdp-old-price"><?= fmtPrice($precoBase, $produtoMoeda) ?></div>
                <?php endif; ?>
                <div class="pdp-price"><?= fmtPrice($precoFinal, $produtoMoeda) ?></div>
            </div>

            <!-- Stock -->
            <?php if ($estoque > 0 && $estoque <= 10): ?>
            <div class="pdp-stock-low"><i class="fa-solid fa-exclamation-triangle"></i> <?= sprintf(__('product_details.low_stock', 'Apenas %d unidades disponíveis'), $estoque) ?></div>
            <?php endif; ?>

            <!-- Benefits -->
            <div class="pdp-benefits">
                <?php foreach ($benefits as $b): ?>
                <div class="pdp-benefit">
                    <i class="<?= htmlspecialchars($b['icon'] ?? 'fa-solid fa-check') ?>"></i>
                    <div>
                        <h4><?= htmlspecialchars($b['title'] ?? '') ?></h4>
                        <p><?= htmlspecialchars($b['description'] ?? '') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Add to Cart -->
            <form id="pdp-cart-form" method="POST" action="/produto/adicionar-carrinho">
                <input type="hidden" name="produto_id" value="<?= (int)$produto['id'] ?>">
                <div class="pdp-purchase">
                    <div class="pdp-quantity">
                        <button type="button" onclick="pdpQty(-1)"><i class="fa-solid fa-minus"></i></button>
                        <input type="number" name="quantidade" id="pdp-qty" value="1" min="1" max="<?= $estoque ?>">
                        <button type="button" onclick="pdpQty(1)"><i class="fa-solid fa-plus"></i></button>
                    </div>
                </div>
                <div class="pdp-actions">
                    <button type="submit" class="pdp-btn-primary" <?= $estoque <= 0 ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-cart-shopping"></i>
                        <?= $estoque > 0 ? __('product_details.add_to_cart', 'Adicionar ao Carrinho') : __('product_details.unavailable', 'Indisponível') ?>
                    </button>
                </div>
            </form>

            <!-- Trust -->
            <div class="pdp-trust">
                <div class="pdp-trust-item"><i class="fa-solid fa-lock"></i> <?= __('product_details.trust_secure', 'Compra segura e protegida') ?></div>
                <div class="pdp-trust-item"><i class="fa-solid fa-box"></i> <?= __('product_details.trust_authentic', 'Produto importado autêntico') ?></div>
                <div class="pdp-trust-item"><i class="fa-solid fa-truck"></i> <?= __('product_details.trust_tracked', 'Envio rastreado de ponta a ponta') ?></div>
                <div class="pdp-trust-item"><i class="fa-solid fa-headset"></i> <?= __('product_details.trust_support', 'Suporte especializado em português') ?></div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <?php if ($descCompleta || $descCurta): ?>
    <div class="pdp-description">
        <h2><?= __('product_details.description', 'Sobre o Produto') ?></h2>
        <p><?= nl2br(htmlspecialchars($descCompleta ?: $descCurta)) ?></p>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<style>
:root{--pdp-navy:#071739;--pdp-navy-light:#0d224f;--pdp-text:#0f172a;--pdp-text-soft:#64748b;--pdp-border:#e2e8f0;--pdp-bg:#f8fafc;--pdp-white:#ffffff;--pdp-success:#0f766e;}
.pdp-container{width:100%;max-width:1400px;margin:auto;padding:40px 24px;}
.pdp-layout{display:grid;grid-template-columns:1.1fr .9fr;gap:48px;align-items:start;}
.pdp-gallery{position:sticky;top:20px;}
.pdp-main-image{background:var(--pdp-white);border-radius:24px;padding:40px;box-shadow:0 8px 30px rgba(2,6,23,.04);overflow:hidden;aspect-ratio:1;display:flex;align-items:center;justify-content:center;}
.pdp-main-image img{width:100%;height:100%;object-fit:contain;transition:.3s ease;}
.pdp-main-image:hover img{transform:scale(1.03);}
.pdp-no-image{color:#cbd5e1;font-size:4rem;}
.pdp-thumbs{display:flex;gap:12px;margin-top:16px;}
.pdp-thumb{width:72px;height:72px;background:var(--pdp-white);border-radius:14px;border:2px solid transparent;padding:6px;cursor:pointer;transition:.2s;box-shadow:0 2px 10px rgba(2,6,23,.04);}
.pdp-thumb.active,.pdp-thumb:hover{border-color:var(--pdp-navy);}
.pdp-thumb img{width:100%;height:100%;object-fit:contain;}
.pdp-info{background:var(--pdp-white);border-radius:24px;padding:36px;box-shadow:0 8px 30px rgba(2,6,23,.04);}
.pdp-badges{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;}
.pdp-badge{height:32px;padding:0 14px;border-radius:999px;background:#eef2ff;color:var(--pdp-navy);display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;}
.pdp-title{font-size:32px;line-height:1.15;font-weight:800;letter-spacing:-.5px;margin-bottom:12px;color:var(--pdp-text);}
.pdp-brand{font-size:13px;color:var(--pdp-text-soft);margin-bottom:20px;}
.pdp-price-box{margin-bottom:24px;}
.pdp-old-price{color:#94a3b8;text-decoration:line-through;font-size:16px;margin-bottom:4px;}
.pdp-price{font-size:42px;font-weight:800;letter-spacing:-1.5px;color:var(--pdp-navy);}
.pdp-stock-low{display:inline-flex;align-items:center;gap:8px;background:#fef3c7;color:#92400e;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:20px;}
.pdp-benefits{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:28px;}
.pdp-benefit{border:1px solid var(--pdp-border);background:#fbfdff;border-radius:16px;padding:14px;display:flex;align-items:flex-start;gap:12px;}
.pdp-benefit i{color:var(--pdp-navy);margin-top:2px;font-size:14px;}
.pdp-benefit h4{font-size:13px;font-weight:700;margin:0 0 2px;color:var(--pdp-text);}
.pdp-benefit p{font-size:12px;line-height:1.4;color:var(--pdp-text-soft);margin:0;}
.pdp-purchase{margin-bottom:16px;}
.pdp-quantity{display:inline-flex;align-items:center;height:48px;border-radius:14px;overflow:hidden;border:1px solid var(--pdp-border);background:var(--pdp-bg);}
.pdp-quantity button{width:44px;height:100%;border:none;background:none;font-size:14px;cursor:pointer;color:var(--pdp-navy);}
.pdp-quantity button:hover{background:#e2e8f0;}
.pdp-quantity input{width:50px;border:none;background:none;text-align:center;font-size:16px;font-weight:700;color:var(--pdp-navy);}
.pdp-actions{display:flex;flex-direction:column;gap:12px;}
.pdp-btn-primary{height:56px;border:none;border-radius:16px;background:linear-gradient(135deg,var(--pdp-navy),var(--pdp-navy-light));color:white;font-size:16px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:10px;cursor:pointer;transition:.25s;box-shadow:0 10px 24px rgba(7,23,57,.15);width:100%;}
.pdp-btn-primary:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(7,23,57,.2);}
.pdp-btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
.pdp-trust{margin-top:28px;padding-top:24px;border-top:1px solid var(--pdp-border);display:grid;gap:12px;}
.pdp-trust-item{display:flex;align-items:center;gap:12px;color:var(--pdp-text-soft);font-size:13px;font-weight:500;}
.pdp-trust-item i{width:16px;color:var(--pdp-navy);font-size:13px;}
.pdp-description{margin-top:40px;background:var(--pdp-white);border-radius:24px;padding:40px;box-shadow:0 8px 30px rgba(2,6,23,.04);}
.pdp-description h2{font-size:26px;font-weight:800;margin-bottom:16px;letter-spacing:-.5px;color:var(--pdp-text);}
.pdp-description p{font-size:15px;line-height:1.8;color:var(--pdp-text-soft);}
@media(max-width:980px){
    .pdp-layout{grid-template-columns:1fr;gap:24px;}
    .pdp-gallery{position:static;}
    .pdp-title{font-size:24px;}
    .pdp-price{font-size:34px;}
    .pdp-benefits{grid-template-columns:1fr;}
    .pdp-container{padding:16px 12px;}
    .pdp-info{padding:24px 18px;}
    .pdp-main-image{padding:20px;border-radius:16px;}
    .pdp-description{padding:24px 18px;}
}
</style>

<script>
function pdpQty(delta) {
    var input = document.getElementById('pdp-qty');
    var val = parseInt(input.value) || 1;
    var max = parseInt(input.max) || 99;
    val += delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}

// AJAX add to cart
document.getElementById('pdp-cart-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = this.querySelector('.pdp-btn-primary');
    var produtoId = this.querySelector('[name=produto_id]').value;
    var quantidade = document.getElementById('pdp-qty').value;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Adicionando...';
    btn.disabled = true;
    fetch('/api/copiloto/addcart', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({produto_id: produtoId, quantidade: parseInt(quantidade)})
    }).then(r => r.json()).then(d => {
        if (d.success) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Adicionado!';
            btn.style.background = '#0f766e';
            setTimeout(() => {
                btn.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Adicionar ao Carrinho';
                btn.style.background = '';
                btn.disabled = false;
            }, 2000);
        } else {
            btn.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Adicionar ao Carrinho';
            btn.disabled = false;
            alert(d.error || 'Erro ao adicionar');
        }
    }).catch(() => {
        btn.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Adicionar ao Carrinho';
        btn.disabled = false;
    });
});
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
