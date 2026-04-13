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
            <div class="position-relative" id="buscaGruposWrap">
                <div class="input-group shadow-sm" style="border-radius:50px;overflow:hidden;">
                    <span class="input-group-text bg-white border-0 ps-4"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="buscaGruposInput" class="form-control border-0 py-2" placeholder="Buscar produtos nos grupos de compras..." autocomplete="off" style="box-shadow:none;">
                    <span class="input-group-text bg-white border-0 pe-4 d-none" id="buscaGruposClear" style="cursor:pointer"><i class="fas fa-times text-muted"></i></span>
                </div>
                <div id="buscaGruposResults" class="position-absolute w-100 bg-white shadow rounded-3 mt-1" style="z-index:1050;display:none;max-height:450px;overflow-y:auto;"></div>
            </div>
        </div>
    </div>

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
                    <!-- Imagem / ícone do grupo -->
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

<style>
.grupo-card-public {
    border-radius: 16px;
    transition: transform .18s ease, box-shadow .18s ease;
    overflow: hidden;
}
.grupo-card-public:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(15,23,42,.13) !important;
}
.grupo-card-img {
    height: 140px;
    background: linear-gradient(135deg, #0b1f3a 0%, #1d4ed8 100%);
}
.busca-grupo-header { background:#f8fafc; padding:8px 14px; font-size:.8rem; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0; position:sticky; top:0; z-index:1; }
.busca-grupo-item:hover { background:#f8fafc; }
.busca-grupo-item:last-child { border-bottom:none !important; }
</style>

<script>
(function(){
    const inp = document.getElementById('buscaGruposInput');
    const wrap = document.getElementById('buscaGruposResults');
    const clearBtn = document.getElementById('buscaGruposClear');
    if (!inp || !wrap) return;

    let timer = null;
    let lastQ = '';

    function esc(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

    function formatMoney(v, moeda){
        const n = Number(v||0);
        const sym = (moeda||'USD')==='BRL' ? 'R$' : '$';
        try { return sym + ' ' + n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
        catch(e){ return sym + ' ' + n.toFixed(2); }
    }

    function renderResults(data){
        if (!data.produtos || data.produtos.length === 0){
            wrap.innerHTML = '<div class="p-3 text-center text-muted small">Nenhum produto encontrado nos grupos.</div>';
            wrap.style.display = 'block';
            return;
        }
        const clubeAcesso = data.clube_acesso || false;

        // Agrupar por grupo de compras
        const groups = {};
        data.produtos.forEach(function(p){
            const gName = p.grupo_nome || 'Outros';
            const gSlug = p.grupo_slug || '';
            const key = gSlug || gName;
            if (!groups[key]) groups[key] = { nome: gName, slug: gSlug, clube_only: Number(p.clube_only||0), banner: p.grupo_banner||'', items: [] };
            groups[key].items.push(p);
        });

        let html = '';
        Object.keys(groups).forEach(function(key){
            const g = groups[key];
            const isClubeGroup = (g.clube_only === 1) && !clubeAcesso;
            let headerBadge = '';
            if (isClubeGroup) {
                headerBadge = ' <span class="badge" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:.65rem;vertical-align:middle"><i class="fas fa-crown me-1"></i>Clube</span>';
            }
            html += '<div class="busca-grupo-header"><i class="fas fa-users me-1"></i>' + esc(g.nome) + headerBadge + '</div>';

            g.items.forEach(function(p){
                const isClubeBlocked = isClubeGroup;
                const foto = p.foto_principal || '/uploads/produtos/placeholder.jpg';
                const link = g.slug ? '/grupo/' + esc(g.slug) : '#';
                const blurStyle = isClubeBlocked ? 'filter:blur(3px);pointer-events:none;user-select:none;' : '';
                const priceHtml = isClubeBlocked
                    ? '<span class="text-muted small">Exclusivo Clube</span>'
                    : '<span class="fw-bold text-primary small">' + formatMoney(p.valor, p.moeda) + '</span>';

                html += '<a href="' + (isClubeBlocked ? '/como-funciona-clube' : link) + '" class="d-flex align-items-center gap-3 px-3 py-2 text-decoration-none border-bottom busca-grupo-item" style="color:inherit;">';
                html += '<img src="' + esc(foto) + '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0;' + blurStyle + '">';
                html += '<div class="flex-grow-1 overflow-hidden">';
                html += '<div class="fw-semibold text-truncate" style="font-size:.85rem;' + blurStyle + '">' + esc(p.nome) + '</div>';
                html += '<div>' + priceHtml + '</div>';
                html += '</div>';
                html += '</a>';
            });
        });
        wrap.innerHTML = html;
        wrap.style.display = 'block';
    }

    function doSearch(){
        const q = inp.value.trim();
        if (q.length < 2){ wrap.style.display='none'; lastQ=''; return; }
        if (q === lastQ) return;
        lastQ = q;
        wrap.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin text-muted"></i></div>';
        wrap.style.display = 'block';
        fetch('/api/produtos/buscar-todos?q=' + encodeURIComponent(q) + '&context=grupos&limit=20')
            .then(r => r.json())
            .then(renderResults)
            .catch(function(){ wrap.innerHTML='<div class="p-3 text-center text-muted small">Erro ao buscar.</div>'; });
    }

    inp.addEventListener('input', function(){
        clearBtn.classList.toggle('d-none', inp.value.trim() === '');
        clearTimeout(timer);
        timer = setTimeout(doSearch, 350);
    });

    clearBtn.addEventListener('click', function(){
        inp.value = '';
        wrap.style.display = 'none';
        clearBtn.classList.add('d-none');
        lastQ = '';
        inp.focus();
    });

    document.addEventListener('click', function(e){
        if (!document.getElementById('buscaGruposWrap').contains(e.target)){
            wrap.style.display = 'none';
        }
    });

    inp.addEventListener('focus', function(){
        if (inp.value.trim().length >= 2 && wrap.innerHTML.trim() !== '') wrap.style.display = 'block';
    });
})();
</script>
