<?php
// Variáveis esperadas: $grupo (array), $produtos (array), $page (int), $totalPages (int), $total (int)
$grupo     = $grupo ?? [];
$produtos  = is_array($produtos) ? $produtos : [];
$page      = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$total     = (int) ($total ?? 0);
$slug      = htmlspecialchars($grupo['slug'] ?? '', ENT_QUOTES, 'UTF-8');

$buildUrl = static function (int $p) use ($slug): string {
    return '/grupo/' . $slug . ($p > 1 ? '?page=' . $p : '');
};
?>

<div class="container py-4">

    <!-- Cabeçalho do grupo -->
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">
            <?= htmlspecialchars($grupo['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($clubeOnly)): ?>
            <span class="badge align-middle ms-2" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:.55em;vertical-align:middle;">
                <i class="fas fa-crown me-1"></i>Clube Braziliana
            </span>
            <?php endif; ?>
        </h1>
        <?php if (!empty($grupo['descricao'])): ?>
        <p class="text-muted mb-2"><?= htmlspecialchars($grupo['descricao'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted small"><i class="fas fa-box me-1"></i><?= $total ?> produto(s)</span>
            <?php
            $impostoLocalPercent = (float)($grupo['imposto_local_percent'] ?? 0);
            if ($impostoLocalPercent > 0): ?>
            <span class="badge" style="background:rgba(245,158,11,.15);color:#92400e;border:1px solid rgba(245,158,11,.3);">
                Inclui imposto local (<?= number_format($impostoLocalPercent, 0) ?>%)
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Busca por nome -->
    <div class="mb-4">
        <div class="input-group" style="max-width:400px">
            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="filtroProduto" class="form-control border-start-0 ps-0" placeholder="Buscar produto pelo nome...">
        </div>
        <div id="semResultados" class="text-muted small mt-2" style="display:none">Nenhum produto encontrado.</div>
    </div>

    <!-- Bloqueio Clube Braziliana -->
    <?php if (!empty($clubeOnly) && empty($clubeAcessoLiberado)): ?>
    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">
        <div class="card-body text-center py-5" style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);">
            <i class="fas fa-crown fa-3x mb-3" style="color:#d97706;"></i>
            <h3 class="fw-bold mb-2" style="color:#92400e;">Grupo exclusivo do Clube Braziliana</h3>
            <p class="text-muted mb-3">
                Para acessar os produtos deste grupo, você precisa ser membro do Clube Braziliana
                com saldo mínimo de <strong>US$ <?= number_format($clubeMinimo, 2, ',', '.') ?></strong> na carteira.
            </p>
            <?php if (empty($clubeLogado)): ?>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="/login" class="btn btn-primary"><i class="fas fa-sign-in-alt me-1"></i>Fazer login</a>
                    <a href="/registro" class="btn btn-outline-primary"><i class="fas fa-user-plus me-1"></i>Criar conta</a>
                    <a href="/como-funciona-clube" class="btn btn-outline-secondary"><i class="fas fa-info-circle me-1"></i>Saiba mais</a>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-2">Seu saldo atual: <strong>US$ <?= number_format($clubeSaldoUsd, 2, ',', '.') ?></strong></p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="/minha-conta" class="btn btn-primary"><i class="fas fa-wallet me-1"></i>Recarregar carteira</a>
                    <a href="/como-funciona-clube" class="btn btn-outline-secondary"><i class="fas fa-info-circle me-1"></i>Saiba mais</a>
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
                <h3 class="text-muted">Nenhum produto disponível</h3>
                <p class="text-muted">Este grupo ainda não possui produtos publicados.</p>
                <a href="/produtos" class="btn btn-primary mt-2">Ver todos os produtos</a>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($produtos as $produto): ?>
        <?php
            $precoBase  = (float) ($produto['price'] ?? 0);
            $precoPromo = (float) ($produto['sale_price'] ?? 0);
            $temPromo   = ($precoPromo > 0 && $precoPromo < $precoBase);
            $precoExibir = $temPromo ? $precoPromo : $precoBase;
            // Aplicar imposto local do grupo no preço exibido
            $impostoLocalGrupo = (float)($grupo['imposto_local_percent'] ?? 0);
            $precoComImposto = $impostoLocalGrupo > 0 ? $precoExibir * (1 + $impostoLocalGrupo / 100) : $precoExibir;
            $precoBaseComImposto = $impostoLocalGrupo > 0 ? $precoBase * (1 + $impostoLocalGrupo / 100) : $precoBase;
        ?>
        <div class="col-lg-3 col-md-6 mb-4 produto-item" data-nome="<?= strtolower(htmlspecialchars($produto['name'] ?? '', ENT_QUOTES, 'UTF-8')) ?>">
            <div class="card h-100 product-card-modern border-0 shadow-sm">
                <div class="position-relative overflow-hidden product-image-frame">
                    <?php if (!empty($produto['foto_principal'])): ?>
                        <img src="<?= htmlspecialchars($produto['foto_principal'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($produto['name'], ENT_QUOTES, 'UTF-8') ?>"
                             class="card-img-top product-image-modern">
                        <?php if ((int)($produto['stock'] ?? 0) <= 5 && (int)($produto['stock'] ?? 0) > 0): ?>
                        <span class="position-absolute top-0 end-0 m-2 badge bg-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i><?= (int)$produto['stock'] ?> unidades
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($produto['featured'])): ?>
                        <span class="position-absolute top-0 start-0 m-2 badge bg-danger">
                            <i class="fas fa-star me-1"></i>Destaque
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
                            <span class="h4 text-primary fw-bold mb-0 product-price"
                                  data-original-price="<?= $precoComImposto ?>">
                                <?= number_format($precoComImposto, 2, ',', '.') ?>
                            </span>
                            <?php if ($temPromo): ?>
                            <small class="text-decoration-line-through text-muted product-original-price"
                                   data-original-original-price="<?= $precoBaseComImposto ?>">
                                <?= number_format($precoBaseComImposto, 2, ',', '.') ?>
                            </small>
                            <?php endif; ?>
                            <?php if ($impostoLocalGrupo > 0): ?>
                            <div class="small text-warning mt-1">Incl. imposto local <?= number_format($impostoLocalGrupo, 0) ?>%</div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ((int)($produto['stock'] ?? 0) > 0): ?>
                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Disponível</span>
                            <?php else: ?>
                            <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Esgotado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-transparent border-top-0">
                    <div class="d-grid gap-2">
                        <a href="/produto/detalhes/<?= (int)$produto['id'] ?>"
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-2"></i>Ver Detalhes
                        </a>
                        <button class="btn btn-primary btn-sm btn-adicionar-modern"
                                data-produto-id="<?= (int)$produto['id'] ?>"
                                data-produto-nome="<?= htmlspecialchars($produto['name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-produto-preco="<?= $precoExibir ?>"
                                data-is-variavel="<?= !empty($produto['is_variavel']) ? '1' : '0' ?>"
                                <?= (int)($produto['stock'] ?? 0) > 0 ? '' : 'disabled' ?>>
                            <i class="fas fa-cart-plus me-2"></i>
                            <?= (int)($produto['stock'] ?? 0) > 0 ? 'Adicionar ao Carrinho' : 'Indisponível' ?>
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
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Paginação" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= htmlspecialchars($buildUrl(max(1, $page - 1)), ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
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
                <a class="page-link" href="<?= htmlspecialchars($buildUrl(min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8') ?>">Próxima</a>
            </li>
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
    add_to_cart: 'Adicionar ao Carrinho',
    adding: 'Adicionando...',
    error_add: 'Erro ao adicionar produto'
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
    botao.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adicionando...';
    fetch('/carrinho/adicionar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `id=${encodeURIComponent(produtoId)}&quantidade=1`
    })
    .then(r => r.json())
    .then(data => {
        botao.disabled = false;
        botao.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Adicionar ao Carrinho';
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
        botao.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Adicionar ao Carrinho';
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

    // Filtro por nome
    const filtro = document.getElementById('filtroProduto');
    const semResultados = document.getElementById('semResultados');
    if (filtro) {
        filtro.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.produto-item');
            let visiveis = 0;
            items.forEach(item => {
                const nome = item.dataset.nome || '';
                const visivel = q === '' || nome.includes(q);
                item.style.display = visivel ? '' : 'none';
                if (visivel) visiveis++;
            });
            semResultados.style.display = (q !== '' && visiveis === 0) ? '' : 'none';
        });
    }
});
</script>
