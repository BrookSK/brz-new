<?php
$grupos = is_array($grupos ?? null) ? $grupos : [];
?>

<div class="container py-5">
    <div class="mb-5 text-center">
        <h1 class="h2 fw-bold mb-2">Grupos de Compras</h1>
        <p class="text-muted">Escolha um grupo para ver os produtos disponíveis</p>
    </div>

    <!-- Busca de produtos nos grupos -->
    <div class="row justify-content-center mb-4">
        <div class="col-lg-6 col-md-8">
            <div class="input-group shadow-sm" style="border-radius:50px;overflow:hidden;">
                <span class="input-group-text bg-white border-0 ps-4"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="buscaGruposInput" class="form-control border-0 py-2" placeholder="Buscar produtos nos grupos de compras..." autocomplete="off" style="box-shadow:none;">
                <span class="input-group-text bg-white border-0 pe-4 d-none" id="buscaGruposClear" style="cursor:pointer"><i class="fas fa-times text-muted"></i></span>
            </div>
        </div>
    </div>

    <!-- Conteúdo original: lista de grupos -->
    <div id="gruposConteudoOriginal">
        <?php if (empty($grupos)): ?>
        <div class="text-center py-5">
            <i class="fas fa-store fa-4x text-muted mb-3 d-block opacity-50"></i>
            <h3 class="text-muted">Nenhum grupo disponível no momento</h3>
            <a href="/produtos" class="btn btn-primary mt-3">Ver todos os produtos</a>
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
                                    <i class="fas fa-box me-1"></i><?= $qtd ?> produto<?= $qtd !== 1 ? 's' : '' ?>
                                </span>
                                <?php if ($impostoLocal > 0): ?>
                                <span class="badge bg-warning text-dark">
                                    Imposto local <?= number_format($impostoLocal, 0) ?>%
                                </span>
                                <?php endif; ?>
                                <?php if ($clubeOnly): ?>
                                <span class="badge" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
                                    <i class="fas fa-crown me-1"></i>Clube
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top text-center py-3">
                            <span class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-eye me-2"></i>Ver produtos
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
            <button class="btn btn-outline-primary" id="gruposBtnLoadMore">Carregar mais</button>
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
        try { return sym+' '+n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
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
            grupoBadge += '<span class="badge grupo-tag" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff"><i class="fas fa-crown me-1"></i>Exclusivo Clube</span>';
        }

        const priceHtml = isClubeBlocked
            ? '<span class="badge" style="background:#0b1f3a;font-size:.75rem"><i class="fas fa-lock me-1"></i>Clube</span>'
            : '<span class="h6 mb-0 text-primary">'+formatMoney(p.valor, p.moeda)+'</span>';

        let btns = '';
        if (isClubeBlocked) {
            btns = '<a href="/como-funciona-clube" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-crown me-2"></i>Saiba mais</a>';
        } else {
            btns += '<a href="'+detalhesLink+'" class="btn btn-outline-primary btn-sm w-100 mb-2"><i class="fas fa-eye me-1"></i> Ver detalhes</a>';
            if (grupoLink) {
                btns += '<a href="'+grupoLink+'" class="btn btn-outline-secondary btn-sm w-100 mb-2"><i class="fas fa-store me-1"></i> Ver no grupo</a>';
            }
            btns += '<button type="button" class="btn btn-primary btn-sm w-100 btn-add-cart" data-id="'+p.id+'"><i class="fas fa-cart-plus me-1"></i> Adicionar ao carrinho</button>';
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
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Adicionando...';
        fetch('/api/carrinho/adicionar', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'produto_id='+pid+'&quantidade=1'
        })
        .then(r=>r.json())
        .then(function(resp){
            if (resp.success) {
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Adicionado!';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
                setTimeout(function(){ btn.innerHTML=origHtml; btn.disabled=false; btn.classList.remove('btn-success'); btn.classList.add('btn-primary'); }, 2000);
                const cartBadge = document.querySelector('.cart-count, #cart-count, [data-cart-count]');
                if (cartBadge && resp.total_itens !== undefined) cartBadge.textContent = resp.total_itens;
            } else {
                btn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> '+(resp.error||'Erro');
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
            grid.innerHTML = '<div class="col-12 text-center py-5"><i class="fas fa-search fa-3x text-muted mb-3 d-block opacity-50"></i><h5 class="text-muted">Nenhum produto encontrado</h5><p class="text-muted small">Tente buscar com outros termos</p></div>';
            info.textContent = '';
            loadMoreWrap.style.display = 'none';
        } else {
            info.innerHTML = '<i class="fas fa-search me-1"></i> <strong>'+allProducts.length+'</strong> produto'+(allProducts.length!==1?'s':'')+' encontrado'+(allProducts.length!==1?'s':'');
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
        info.textContent = 'Buscando...';
        loadMoreWrap.style.display = 'none';

        fetch('/api/produtos/buscar-todos?q='+encodeURIComponent(q)+'&context=grupos&limit=60')
            .then(r=>r.json())
            .then(showResults)
            .catch(function(){ grid.innerHTML='<div class="col-12 text-center py-4 text-muted">Erro ao buscar.</div>'; });
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
