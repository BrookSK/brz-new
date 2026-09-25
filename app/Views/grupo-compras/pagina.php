<?php
// Variáveis esperadas: $grupo (array), $produtos (array), $page (int), $totalPages (int), $total (int)
$grupo     = $grupo ?? [];
$produtos  = is_array($produtos) ? $produtos : [];
$page      = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$total     = (int) ($total ?? 0);
$slug      = htmlspecialchars($grupo['slug'] ?? '', ENT_QUOTES, 'UTF-8');
$categoriasDoGrupo = $categoriasDoGrupo ?? [];
$categoriaFiltro = (int) ($categoriaFiltro ?? 0);

$buildUrl = static function (int $p) use ($slug, $busca, $categoriaFiltro): string {
    $url = '/grupo/' . $slug;
    $qs = [];
    if ($p > 1) $qs[] = 'page=' . $p;
    if (($busca ?? '') !== '') $qs[] = 'q=' . urlencode($busca);
    if ($categoriaFiltro > 0) $qs[] = 'categoria=' . $categoriaFiltro;
    return $url . (!empty($qs) ? '?' . implode('&', $qs) : '');
};
$busca = $busca ?? '';
?>

<div class="container py-4">

<?php if (!empty($grupoInativo)): ?>
<div class="alert alert-warning mb-4">
    <i class="fas fa-archive me-2"></i>
    <strong><?= htmlspecialchars(__('group_page.archived_title', 'Grupo arquivado.'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars(__('group_page.archived_text', 'Este grupo de compras foi encerrado. Os produtos e valores exibidos são históricos.'), ENT_QUOTES, 'UTF-8') ?>
    <?php if (!empty($snapshotInfo)): ?>
    <div class="mt-1 small"><?= htmlspecialchars(__('group_page.period', 'Período'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($snapshotInfo['inicio']) ?> — <?= htmlspecialchars($snapshotInfo['fim']) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

    <!-- Cabeçalho do grupo -->
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">
            <?= htmlspecialchars($grupo['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($clubeOnly)): ?>
            <span class="badge align-middle ms-2" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:.55em;vertical-align:middle;">
                <i class="fas fa-crown me-1"></i><?= htmlspecialchars(__('group_page.club_badge', 'Clube Braziliana'), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php endif; ?>
        </h1>
        <?php if (!empty($grupo['descricao'])): ?>
        <p class="text-muted mb-2"><?= htmlspecialchars($grupo['descricao'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted small"><i class="fas fa-box me-1"></i><?= $total ?> <?= htmlspecialchars(__('group_page.products_count', 'produto(s)'), ENT_QUOTES, 'UTF-8') ?></span>
            <?php
            $impostoLocalPercent = (float)($grupo['imposto_local_percent'] ?? 0);
            if ($impostoLocalPercent > 0): ?>
            <span class="badge" style="background:rgba(245,158,11,.15);color:#92400e;border:1px solid rgba(245,158,11,.3);">
                <?= htmlspecialchars(__('group_page.includes_local_tax', 'Inclui imposto local'), ENT_QUOTES, 'UTF-8') ?> (<?= number_format($impostoLocalPercent, 0) ?>%)
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Busca por nome e filtro de categoria -->
    <div class="mb-4">
        <form method="GET" action="/grupo/<?= $slug ?>" id="formBuscaGrupo" class="d-flex gap-2 flex-wrap" style="max-width:700px">
            <div class="input-group" style="flex:1;min-width:200px;">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="q" id="filtroProduto" class="form-control border-start-0 ps-0" placeholder="<?= htmlspecialchars(__('group_page.search_placeholder', 'Buscar produto pelo nome...'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
            </div>
            <?php if (!empty($categoriasDoGrupo)): ?>
            <select name="categoria" class="form-select" style="max-width:220px;" onchange="this.form.submit()">
                <option value=""><?= htmlspecialchars(__('group_page.all_categories', 'Todas as categorias'), ENT_QUOTES, 'UTF-8') ?></option>
                <?php foreach ($categoriasDoGrupo as $cat): ?>
                <option value="<?= (int) $cat['cat_id'] ?>" <?= $categoriaFiltro === (int) $cat['cat_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['cat_nome'] ?? __('group_page.no_name', 'Sem nome'), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($busca !== '' || $categoriaFiltro > 0): ?>
            <a href="/grupo/<?= $slug ?>" class="btn btn-outline-secondary" title="<?= htmlspecialchars(__('group_page.clear_filters', 'Limpar filtros'), ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
        <?php if ($busca !== '' && empty($produtos)): ?>
        <div class="text-muted small mt-2"><?= htmlspecialchars(__('group_page.no_results_for', 'Nenhum produto encontrado para'), ENT_QUOTES, 'UTF-8') ?> "<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>".</div>
        <?php endif; ?>
    </div>

    <!-- Bloqueio Clube Braziliana -->
    <?php if (!empty($clubeOnly) && empty($clubeAcessoLiberado)): ?>
    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">
        <div class="card-body text-center py-5" style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);">
            <i class="fas fa-crown fa-3x mb-3" style="color:#d97706;"></i>
            <h3 class="fw-bold mb-2" style="color:#92400e;"><?= htmlspecialchars(__('group_page.club_exclusive_title', 'Grupo exclusivo do Clube Braziliana'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="text-muted mb-3">
                <?= htmlspecialchars(__('group_page.club_exclusive_text', 'Para acessar os produtos deste grupo, você precisa ser membro do Clube Braziliana com saldo mínimo de'), ENT_QUOTES, 'UTF-8') ?> <strong>US$ <?= number_format($clubeMinimo, 2, ',', '.') ?></strong> <?= htmlspecialchars(__('group_page.in_wallet', 'na carteira.'), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <?php if (empty($clubeLogado)): ?>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="/login" class="btn btn-primary"><i class="fas fa-sign-in-alt me-1"></i><?= htmlspecialchars(__('group_page.login', 'Fazer login'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="/registro" class="btn btn-outline-primary"><i class="fas fa-user-plus me-1"></i><?= htmlspecialchars(__('group_page.create_account', 'Criar conta'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="/como-funciona-clube" class="btn btn-outline-secondary"><i class="fas fa-info-circle me-1"></i><?= htmlspecialchars(__('group_page.learn_more', 'Saiba mais'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-2"><?= htmlspecialchars(__('group_page.current_balance', 'Seu saldo atual:'), ENT_QUOTES, 'UTF-8') ?> <strong>US$ <?= number_format($clubeSaldoUsd, 2, ',', '.') ?></strong></p>
                <?php $__clubeEnabledGrupo = \App\Controllers\ClubeController::isClubeEnabled(); ?>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <?php if ($__clubeEnabledGrupo): ?>
                        <a href="/minha-conta" class="btn btn-primary"><i class="fas fa-wallet me-1"></i><?= htmlspecialchars(__('group_page.recharge_wallet', 'Recarregar carteira'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else: ?>
                        <a href="https://wa.me/<?= htmlspecialchars(\App\Controllers\ClubeController::CLUBE_WHATSAPP) ?>?text=<?= rawurlencode('Olá, gostaria de saber mais sobre o meu Clube Braziliana.') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success"><i class="fab fa-whatsapp me-1"></i><?= htmlspecialchars(__('group_page.talk_whatsapp', 'Falar no WhatsApp'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endif; ?>
                    <a href="/como-funciona-clube" class="btn btn-outline-secondary"><i class="fas fa-info-circle me-1"></i><?= htmlspecialchars(__('group_page.learn_more', 'Saiba mais'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grid de produtos -->
    <?php if (!empty($clubeOnly) && empty($clubeAcessoLiberado)): ?>
    <div style="position:relative;">
        <div style="filter:blur(6px);pointer-events:none;user-select:none;opacity:.4;">
    <?php endif; ?>
    <div class="row">
        <?php if (empty($produtos)): ?>
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                <h3 class="text-muted"><?= htmlspecialchars(__('group_page.empty_title', 'Nenhum produto disponível'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="text-muted"><?= htmlspecialchars(__('group_page.empty_text', 'Este grupo ainda não possui produtos publicados.'), ENT_QUOTES, 'UTF-8') ?></p>
                <a href="/produtos" class="btn btn-primary mt-2"><?= htmlspecialchars(__('group_page.see_all_products', 'Ver todos os produtos'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($produtos as $produto): ?>
        <?php
            $precoBase  = (float) ($produto['price'] ?? 0);
            $precoPromo = (float) ($produto['sale_price'] ?? 0);
            $temPromo   = ($precoPromo > 0 && $precoPromo < $precoBase);
            $precoExibir = $temPromo ? $precoPromo : $precoBase;
            $impostoLocalGrupo = (float)($grupo['imposto_local_percent'] ?? 0);
        ?>
        <div class="col-lg-3 col-md-6 mb-4 produto-item" data-nome="<?= strtolower(htmlspecialchars($produto['name'] ?? '', ENT_QUOTES, 'UTF-8')) ?>">
            <div class="card h-100 product-card-modern border-0 shadow-sm">
                <div class="position-relative overflow-hidden product-image-frame">
                    <?php if (!empty($produto['foto_principal'])): ?>
                        <img src="<?= htmlspecialchars($produto['foto_principal'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($produto['name'], ENT_QUOTES, 'UTF-8') ?>"
                             class="card-img-top product-image-modern">
                        <?php if (!empty($produto['featured'])): ?>
                        <span class="position-absolute top-0 start-0 m-2 badge bg-danger">
                            <i class="fas fa-star me-1"></i><?= htmlspecialchars(__('group_page.featured', 'Destaque'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="card-img-top product-image-modern bg-light d-flex align-items-center justify-content-center">
                            <i class="fas fa-image text-muted fa-3x"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <h5 class="card-title fw-bold text-truncate">
                        <?= htmlspecialchars($produto['name'], ENT_QUOTES, 'UTF-8') ?>
                    </h5>
                    <?php if (!empty($produto['short_description'])): ?>
                    <p class="card-text text-muted small mb-3">
                        <?= htmlspecialchars(substr($produto['short_description'], 0, 80), ENT_QUOTES, 'UTF-8') ?>...
                    </p>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="price-section">
                            <span class="h4 fw-bold mb-0 product-price <?= $temPromo ? 'text-danger' : 'text-primary' ?>"
                                  data-original-price="<?= $precoExibir ?>">
                                <?= number_format($precoExibir, 2, ',', '.') ?>
                            </span>
                            <?php if ($temPromo): ?>
                            <small class="text-decoration-line-through text-muted ms-1 product-original-price"
                                   data-original-original-price="<?= $precoBase ?>">
                                <?= number_format($precoBase, 2, ',', '.') ?>
                            </small>
                            <?php endif; ?>
                            <?php if ($impostoLocalGrupo > 0): ?>
                            <div class="small text-warning mt-1">+ <?= htmlspecialchars(__('group_page.local_tax', 'imposto local'), ENT_QUOTES, 'UTF-8') ?> <?= number_format($impostoLocalGrupo, 0) ?>%</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-transparent border-top-0">
                    <div class="d-grid gap-2">
                        <a href="/produto/detalhes/<?= (int)$produto['id'] ?>"
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-2"></i><?= htmlspecialchars(__('group_page.view_details', 'Ver Detalhes'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php $podeComprar = !empty($produto['venda_sob_demanda']) || (int)($produto['stock'] ?? 0) > 0; ?>
                        <button class="btn btn-primary btn-sm btn-adicionar-modern"
                                data-produto-id="<?= (int)$produto['id'] ?>"
                                data-produto-nome="<?= htmlspecialchars($produto['name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-produto-preco="<?= $precoExibir ?>"
                                data-is-variavel="<?= !empty($produto['is_variavel']) ? '1' : '0' ?>"
                                <?= $podeComprar ? '' : 'disabled' ?>>
                            <i class="fas fa-cart-plus me-2"></i>
                            <?= $podeComprar ? htmlspecialchars(__('group_page.add_to_cart', 'Adicionar ao Carrinho'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(__('group_page.unavailable', 'Indisponível'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($clubeOnly) && empty($clubeAcessoLiberado)): ?>
        </div><!-- /blur -->
    </div><!-- /position-relative -->
    <?php endif; ?>

    <!-- Paginação -->
    <?php if ($totalPages > 1 || !empty($verTodos)): ?>
    <nav aria-label="Paginação" class="mt-4">
        <ul class="pagination justify-content-center flex-wrap gap-1">
            <?php if (!empty($verTodos)): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= htmlspecialchars($buildUrl(1), ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fas fa-list me-1"></i><?= htmlspecialchars(__('group_page.paginated', 'Paginado'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php else: ?>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= htmlspecialchars($buildUrl(max(1, $page - 1)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.previous', 'Anterior'), ENT_QUOTES, 'UTF-8') ?></a>
            </li>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($buildUrl(1), ENT_QUOTES, 'UTF-8') . '">1</a></li>';
                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            for ($i = $start; $i <= $end; $i++) {
                $active = ($i === $page);
                echo '<li class="page-item' . ($active ? ' active' : '') . '"><a class="page-link" href="' . htmlspecialchars($buildUrl($i), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a></li>';
            }
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($buildUrl($totalPages), ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a></li>';
            }
            ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= htmlspecialchars($buildUrl(min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.next', 'Próxima'), ENT_QUOTES, 'UTF-8') ?></a>
            </li>
            <?php
            $verTodosUrl = '/grupo/' . $slug . '?ver_todos=1';
            if ($busca !== '') $verTodosUrl .= '&q=' . urlencode($busca);
            ?>
            <li class="page-item ms-2">
                <a class="page-link text-primary fw-semibold" href="<?= htmlspecialchars($verTodosUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fas fa-th me-1"></i><?= htmlspecialchars(__('group_page.see_all', 'Ver Todos'), ENT_QUOTES, 'UTF-8') ?> (<?= $total ?>)
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<style>
.product-card-modern { transition: none; border-radius: 15px; }
.product-image-frame { aspect-ratio: 3 / 4; }
.product-image-modern { width: 100%; height: 100%; object-fit: cover; }
</style>

<script>
window.PRODUCTS_MODERNO_I18N = {
    add_to_cart: <?= json_encode(__('group_page.add_to_cart', 'Adicionar ao Carrinho'), JSON_UNESCAPED_UNICODE) ?>,
    adding: <?= json_encode(__('group_page.adding', 'Adicionando...'), JSON_UNESCAPED_UNICODE) ?>,
    error_add: <?= json_encode(__('group_page.error_add', 'Erro ao adicionar produto'), JSON_UNESCAPED_UNICODE) ?>
};

function adicionarAoCarrinhoModerno(botao) {
    const produtoId = botao.getAttribute('data-produto-id');
    const isVariavel = String(botao.getAttribute('data-is-variavel') || '0') === '1';
    if (isVariavel) {
        window.location.href = `/produto/detalhes/${encodeURIComponent(produtoId)}?selecionar_variacao=1`;
        return;
    }
    if (botao.disabled) return;
    botao.disabled = true;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + (window.PRODUCTS_MODERNO_I18N ? window.PRODUCTS_MODERNO_I18N.adding : 'Adicionando...');
    fetch('/carrinho/adicionar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `id=${encodeURIComponent(produtoId)}&quantidade=1`
    })
    .then(r => r.json())
    .then(data => {
        botao.disabled = false;
        botao.innerHTML = '<i class="fas fa-cart-plus me-2"></i>' + (window.PRODUCTS_MODERNO_I18N ? window.PRODUCTS_MODERNO_I18N.add_to_cart : 'Adicionar ao Carrinho');
        if (data.success) {
            if (window.updateCartBadge) window.updateCartBadge(data.total_itens);
            const a = document.createElement('div');
            a.className = 'alert-container';
            a.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:400px;';
            a.innerHTML = `<div class="alert alert-success alert-dismissible fade show">${data.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
            document.body.appendChild(a);
            setTimeout(() => a.remove(), 4000);
        }
    })
    .catch(() => {
        botao.disabled = false;
        botao.innerHTML = '<i class="fas fa-cart-plus me-2"></i>' + (window.PRODUCTS_MODERNO_I18N ? window.PRODUCTS_MODERNO_I18N.add_to_cart : 'Adicionar ao Carrinho');
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-adicionar-modern').forEach(btn => {
        btn.addEventListener('click', function (e) { e.preventDefault(); adicionarAoCarrinhoModerno(this); });
    });

    // Converter preços se houver conversor de moeda ativo
    if (window.updateProductPrices) {
        const cur = (window.CurrencyConverter && window.CurrencyConverter.currentCurrency)
            ? window.CurrencyConverter.currentCurrency
            : (localStorage.getItem('selected_currency') || 'BRL');
        window.updateProductPrices(cur);
    }

    // Submeter busca automaticamente ao digitar (debounce 500ms)
    const filtroProduto = document.getElementById('filtroProduto');
    if (filtroProduto) {
        let debounceTimer;
        filtroProduto.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                document.getElementById('formBuscaGrupo').submit();
            }, 500);
        });
    }
});
</script>
