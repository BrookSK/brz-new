<?php
$grupos = is_array($grupos ?? null) ? $grupos : [];
?>

<div class="container py-5">
    <div class="mb-5 text-center">
        <h1 class="h2 fw-bold mb-2"><?= __('purchase_groups_page.title', 'Grupos de Compras') ?></h1>
        <p class="text-muted"><?= __('purchase_groups_page.subtitle', 'Escolha um grupo para ver os produtos disponíveis') ?></p>
    </div>

    <!-- Busca de produtos nos grupos -->
    <div class="row justify-content-center mb-4">
        <div class="col-lg-6 col-md-8">
            <div class="input-group shadow-sm" style="border-radius:50px;overflow:hidden;">
                <span class="input-group-text bg-white border-0 ps-4"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="buscaGruposInput" class="form-control border-0 py-2" placeholder="<?= htmlspecialchars(__('purchase_groups_page.search_ph', 'Buscar produtos nos grupos de compras...'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" style="box-shadow:none;">
                <span class="input-group-text bg-white border-0 pe-4 d-none" id="buscaGruposClear" style="cursor:pointer"><i class="fas fa-times text-muted"></i></span>
            </div>
        </div>
    </div>

    <!-- Conteúdo original: lista de grupos -->
    <div id="gruposConteudoOriginal">
        <?php if (empty($grupos)): ?>
        <div class="text-center py-5">
            <i class="fas fa-store fa-4x text-muted mb-3 d-block opacity-50"></i>
            <h3 class="text-muted"><?= __('purchase_groups_page.empty', 'Nenhum grupo disponível no momento') ?></h3>
            <a href="/produtos" class="btn btn-primary mt-3"><?= __('purchase_groups_page.see_all', 'Ver todos os produtos') ?></a>
        </div>
        <?php else: ?>
        <div class="row g-4 justify-content-center">
            <?php foreach ($grupos as $g): ?>
            <?php
                $slug = htmlspecialchars($g['slug'] ?? '', ENT_QUOTES, 'UTF-8');
                $nome = htmlspecialchars($g['nome'] ?? '', ENT_QUOTES, 'UTF-8');
                $descricao = htmlspecialchars($g['descricao'] ?? '', ENT_QUOTES, 'UTF-8');
                $qtd = (int)($g['qtd_produtos'] ?? 0);
                $cobraImposto = (int)($g['cobra_imposto_eua'] ?? 0);
                $impostoLocal = (float)($g['imposto_local_percent'] ?? 0);
                $banner = trim((string)($g['banner'] ?? ''));
                $clubeOnly = (int)($g['clube_only'] ?? 0);
            ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="/grupo/<?= $slug ?>" class="text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 grupo-card-public">
                        <div class="grupo-card-img d-flex align-items-center justify-content-center" <?php if ($banner !== ''): ?>style="background:none;padding:0;overflow:hidden"<?php endif; ?>>
                            <?php if ($banner !== ''): ?>
                                <img src="<?= htmlspecialchars($banner, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $nome ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-store fa-3x text-white opacity-75"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="fw-bold mb-1 text-dark"><?= $nome ?></h5>
                            <?php if ($descricao !== ''): ?>
                            <p class="text-muted small mb-2" style="display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                                <?= $descricao ?>
                            </p>
                            <?php endif; ?>
                            <div class="d-flex justify-content-center align-items-center gap-2 mt-2">
                                <span class="badge bg-light text-secondary border">
                                    <i class="fas fa-box me-1"></i><?= $qtd ?> <?= $qtd !== 1 ? __('purchase_groups_page.products', 'produtos') : __('purchase_groups_page.product', 'produto') ?>
                                </span>
                                <?php if ($impostoLocal > 0): ?>
                                <span class="badge bg-warning text-dark">
                                    <?= __('purchase_groups_page.local_tax', 'Imposto local') ?> <?= number_format($impostoLocal, 0) ?>%
                                </span>
                                <?php endif; ?>
                                <?php if ($clubeOnly): ?>
                                <span class="badge" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
                                    <i class="fas fa-crown me-1"></i><?= __('purchase_groups_page.club', 'Clube') ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top text-center py-3">
                            <span class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-eye me-2"></i><?= __('purchase_groups_page.see_products', 'Ver produtos') ?>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Resultados da busca (catálogo inline, escondido por padrão) -->
    <div id="gruposBuscaResultados" style="display:none;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <p class="text-muted mb-0" id="gruposBuscaInfo"></p>
        </div>
        <div class="row g-4" id="gruposBuscaGrid"></div>
        <div class="text-center mt-4" id="gruposBuscaLoadMore" style="display:none;">
            <button class="btn btn-outline-primary" id="gruposBtnLoadMore"><?= __('purchase_groups_page.js.load_more', 'Carregar mais') ?></button>
        </div>
    </div>
</div>

<style>
.grupo-card-public { border-radius:16px; transition:transform .18s ease,box-shadow .18s ease; overflow:hidden; }
.grupo-card-public:hover { transform:translateY(-4px); box-shadow:0 16px 40px rgba(15,23,42,.13)!important; }
.grupo-card-img { height:140px; background:linear-gradient(135deg,#0b1f3a 0%,#1d4ed8 100%); }
.busca-produto-card { border-radius:14px; transition:transform .15s ease,box-shadow .15s ease; overflow:hidden; }
.busca-produto-card:hover { transform:translateY(-3px); box-shadow:0 12px 32px rgba(15,23,42,.1)!important; }
.busca-produto-card .card-img-top { height:180px; object-fit:cover; }
.busca-produto-card.clube-blocked .card-img-top,
.busca-produto-card.clube-blocked .card-body { filter:blur(4px); pointer-events:none; user-select:none; }
.busca-produto-card .grupo-tag { font-size:.72rem; }
.grupo-badge-link:hover { opacity:.85; text-decoration:none!important; }
</style>

<script>
window.PG_SEARCH_I18N = {
    club_exclusive: <?= json_encode(__('purchase_groups_page.js.club_exclusive', 'Exclusivo Clube'), JSON_UNESCAPED_UNICODE) ?>,
    club: <?= json_encode(__('purchase_groups_page.js.club', 'Clube'), JSON_UNESCAPED_UNICODE) ?>,
    learn_more: <?= json_encode(__('purchase_groups_page.js.learn_more', 'Saiba mais'), JSON_UNESCAPED_UNICODE) ?>,
    view_details: <?= json_encode(__('purchase_groups_page.js.view_details', 'Ver detalhes'), JSON_UNESCAPED_UNICODE) ?>,
    view_in_group: <?= json_encode(__('purchase_groups_page.js.view_in_group', 'Ver no grupo'), JSON_UNESCAPED_UNICODE) ?>,
    add_to_cart: <?= json_encode(__('purchase_groups_page.js.add_to_cart', 'Adicionar ao carrinho'), JSON_UNESCAPED_UNICODE) ?>,
    adding: <?= json_encode(__('purchase_groups_page.js.adding', 'Adicionando...'), JSON_UNESCAPED_UNICODE) ?>,
    added: <?= json_encode(__('purchase_groups_page.js.added', 'Adicionado!'), JSON_UNESCAPED_UNICODE) ?>,
    error: <?= json_encode(__('purchase_groups_page.js.error', 'Erro'), JSON_UNESCAPED_UNICODE) ?>,
    no_products: <?= json_encode(__('purchase_groups_page.js.no_products', 'Nenhum produto encontrado'), JSON_UNESCAPED_UNICODE) ?>,
    try_other_terms: <?= json_encode(__('purchase_groups_page.js.try_other_terms', 'Tente buscar com outros termos'), JSON_UNESCAPED_UNICODE) ?>,
    found_singular: <?= json_encode(__('purchase_groups_page.js.found_singular', 'produto encontrado'), JSON_UNESCAPED_UNICODE) ?>,
    found_plural: <?= json_encode(__('purchase_groups_page.js.found_plural', 'produtos encontrados'), JSON_UNESCAPED_UNICODE) ?>,
    searching: <?= json_encode(__('purchase_groups_page.js.searching', 'Buscando...'), JSON_UNESCAPED_UNICODE) ?>,
    search_error: <?= json_encode(__('purchase_groups_page.js.search_error', 'Erro ao buscar.'), JSON_UNESCAPED_UNICODE) ?>,
    load_more: <?= json_encode(__('purchase_groups_page.js.load_more', 'Carregar mais'), JSON_UNESCAPED_UNICODE) ?>,
    money_locale: <?= json_encode(\App\Core\I18n::getLocale() === 'en' ? 'en-US' : 'pt-BR') ?>
};
(function(){
    const inp = document.getElementById('buscaGruposInput');
    const clearBtn = document.getElementById('buscaGruposClear');
    const original = document.getElementById('gruposConteudoOriginal');
    const resultados = document.getElementById('gruposBuscaResultados');
    const grid = document.getElementById('gruposBuscaGrid');
    const info = document.getElementById('gruposBuscaInfo');
    const loadMoreWrap = document.getElementById('gruposBuscaLoadMore');
    const btnLoadMore = document.getElementById('gruposBtnLoadMore');
    if (!inp || !original || !resultados || !grid) return;

    let timer = null, lastQ = '', currentPage = 0, allProducts = [], clubeAcesso = false;

    function esc(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    function formatMoney(v, moeda){
        const n = Number(v||0);
        const sym = (moeda||'USD')==='BRL' ? 'R$' : '$';
        try { return sym+' '+n.toLocaleString(window.PG_SEARCH_I18N.money_locale,{minimumFractionDigits:2,maximumFractionDigits:2}); }
        catch(e){ return sym+' '+n.toFixed(2); }
    }

    function buildCard(p){
        const isGrupo = p.is_grupo || (p.grupo_compras_id && Number(p.grupo_compras_id)>0);
        const isClubeBlocked = (Number(p.clube_only||0)===1) && !clubeAcesso;
        const foto = p.foto_principal || '/uploads/produtos/placeholder.jpg';
        const detalhesLink = '/produto/detalhes/'+p.id;
        const grupoLink = isGrupo && p.grupo_slug ? '/grupo/'+esc(p.grupo_slug)+'?q='+encodeURIComponent(p.nome) : '';

        // Badge do grupo clicável
        let grupoBadge = '';
        if (isGrupo && p.grupo_nome && p.grupo_slug) {
            grupoBadge = '<a href="/grupo/'+esc(p.grupo_slug)+'" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none grupo-badge-link grupo-tag"><i class="fas fa-users me-1"></i>'+esc(p.grupo_nome)+'</a>';
        }
        if (isClubeBlocked) {
            grupoBadge += '<span class="badge grupo-tag" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff"><i class="fas fa-crown me-1"></i>'+window.PG_SEARCH_I18N.club_exclusive+'</span>';
        }

        const priceHtml = isClubeBlocked
            ? '<span class="badge" style="background:#0b1f3a;font-size:.75rem"><i class="fas fa-lock me-1"></i>'+window.PG_SEARCH_I18N.club+'</span>'
            : '<span class="h6 mb-0 text-primary">'+formatMoney(p.valor, p.moeda)+'</span>';

        let btns = '';
        if (isClubeBlocked) {
            btns = '<a href="/como-funciona-clube" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-crown me-2"></i>'+window.PG_SEARCH_I18N.learn_more+'</a>';
        } else {
            btns += '<a href="'+detalhesLink+'" class="btn btn-outline-primary btn-sm w-100 mb-2"><i class="fas fa-eye me-1"></i> '+window.PG_SEARCH_I18N.view_details+'</a>';
            if (grupoLink) {
                btns += '<a href="'+grupoLink+'" class="btn btn-outline-secondary btn-sm w-100 mb-2"><i class="fas fa-store me-1"></i> '+window.PG_SEARCH_I18N.view_in_group+'</a>';
            }
            btns += '<button type="button" class="btn btn-primary btn-sm w-100 btn-add-cart" data-id="'+p.id+'"><i class="fas fa-cart-plus me-1"></i> '+window.PG_SEARCH_I18N.add_to_cart+'</button>';
        }

        return '<div class="col-lg-3 col-md-4 col-sm-6">'
            + '<div class="card border-0 shadow-sm h-100 busca-produto-card'+(isClubeBlocked?' clube-blocked':'')+'">'
            + '<a href="'+(isClubeBlocked?'/como-funciona-clube':detalhesLink)+'" class="text-decoration-none">'
            + '<img src="'+esc(foto)+'" alt="'+esc(p.nome)+'" class="card-img-top">'
            + '</a>'
            + '<div class="card-body d-flex flex-column">'
            + '<div class="mb-2 d-flex flex-wrap gap-1">'+grupoBadge+'</div>'
            + '<h6 class="card-title mb-1" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">'+esc(p.nome)+'</h6>'
            + '<div class="mb-3">'+priceHtml+'</div>'
            + '<div class="mt-auto">'+btns+'</div>'
            + '</div></div></div>';
    }

    function handleAddToCart(e){
        const btn = e.target.closest('.btn-add-cart');
        if (!btn) return;
        const pid = btn.dataset.id;
        if (!pid) return;
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> '+window.PG_SEARCH_I18N.adding;
        fetch('/api/carrinho/adicionar', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'produto_id='+pid+'&quantidade=1'
        })
        .then(r=>r.json())
        .then(function(resp){
            if (resp.success) {
                btn.innerHTML = '<i class="fas fa-check me-1"></i> '+window.PG_SEARCH_I18N.added;
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
                setTimeout(function(){ btn.innerHTML=origHtml; btn.disabled=false; btn.classList.remove('btn-success'); btn.classList.add('btn-primary'); }, 2000);
                const cartBadge = document.querySelector('.cart-count, #cart-count, [data-cart-count]');
                if (cartBadge && resp.total_itens !== undefined) cartBadge.textContent = resp.total_itens;
            } else {
                btn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> '+(resp.error||window.PG_SEARCH_I18N.error);
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-danger');
                setTimeout(function(){ btn.innerHTML=origHtml; btn.disabled=false; btn.classList.remove('btn-danger'); btn.classList.add('btn-primary'); }, 2500);
            }
        })
        .catch(function(){ btn.innerHTML=origHtml; btn.disabled=false; });
    }

    grid.addEventListener('click', handleAddToCart);

    function showResults(data){
        allProducts = data.produtos || [];
        clubeAcesso = data.clube_acesso || false;
        currentPage = 0;

        if (allProducts.length === 0){
            grid.innerHTML = '<div class="col-12 text-center py-5"><i class="fas fa-search fa-3x text-muted mb-3 d-block opacity-50"></i><h5 class="text-muted">'+window.PG_SEARCH_I18N.no_products+'</h5><p class="text-muted small">'+window.PG_SEARCH_I18N.try_other_terms+'</p></div>';
            info.textContent = '';
            loadMoreWrap.style.display = 'none';
        } else {
            info.innerHTML = '<i class="fas fa-search me-1"></i> <strong>'+allProducts.length+'</strong> '+(allProducts.length!==1?window.PG_SEARCH_I18N.found_plural:window.PG_SEARCH_I18N.found_singular);
            grid.innerHTML = '';
            loadPage();
        }

        original.style.display = 'none';
        resultados.style.display = 'block';
    }

    function loadPage(){
        const perPage = 12;
        const start = currentPage * perPage;
        const slice = allProducts.slice(start, start + perPage);
        let html = '';
        slice.forEach(function(p){ html += buildCard(p); });
        grid.insertAdjacentHTML('beforeend', html);
        currentPage++;
        const hasMore = (currentPage * perPage) < allProducts.length;
        loadMoreWrap.style.display = hasMore ? 'block' : 'none';
    }

    function resetSearch(){
        original.style.display = '';
        resultados.style.display = 'none';
        grid.innerHTML = '';
        lastQ = '';
        allProducts = [];
    }

    function doSearch(){
        const q = inp.value.trim();
        if (q.length < 2){ resetSearch(); return; }
        if (q === lastQ) return;
        lastQ = q;
        original.style.display = 'none';
        resultados.style.display = 'block';
        grid.innerHTML = '<div class="col-12 text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        info.textContent = window.PG_SEARCH_I18N.searching;
        loadMoreWrap.style.display = 'none';

        fetch('/api/produtos/buscar-todos?q='+encodeURIComponent(q)+'&context=grupos&limit=60')
            .then(r=>r.json())
            .then(showResults)
            .catch(function(){ grid.innerHTML='<div class="col-12 text-center py-4 text-muted">'+window.PG_SEARCH_I18N.search_error+'</div>'; });
    }

    inp.addEventListener('input', function(){
        clearBtn.classList.toggle('d-none', inp.value.trim()==='');
        clearTimeout(timer);
        timer = setTimeout(doSearch, 400);
    });

    clearBtn.addEventListener('click', function(){
        inp.value = '';
        clearBtn.classList.add('d-none');
        resetSearch();
        inp.focus();
    });

    if (btnLoadMore) btnLoadMore.addEventListener('click', loadPage);
})();
</script>
