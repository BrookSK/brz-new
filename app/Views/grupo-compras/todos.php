<?php
$grupos = is_array($grupos ?? null) ? $grupos : [];
?>

<div class="container py-5">
    <div class="mb-5 text-center">
        <h1 class="h2 fw-bold mb-2">Grupos de Compras</h1>
        <p class="text-muted">Escolha um grupo para ver os produtos disponíveis</p>
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
        ?>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <a href="/grupo/<?= $slug ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 grupo-card-public">
                    <!-- Imagem / ícone do grupo -->
                    <div class="grupo-card-img d-flex align-items-center justify-content-center">
                        <i class="fas fa-store fa-3x text-white opacity-75"></i>
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
                                <i class="fas fa-percent me-1"></i>Imposto local <?= number_format($impostoLocal, 0) ?>%
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
</style>
