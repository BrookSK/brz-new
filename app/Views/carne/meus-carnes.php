<?php
$pageTitle = 'Meus Carnês - Carnê Braziliana';
require __DIR__ . '/../partials/header.php';
?>

<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-file-invoice-dollar"></i> Meus Carnês</h2>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if (empty($carnes)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Você ainda não possui carnês.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($carnes as $c): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0">Pedido #<?= $c['pedido_id'] ?></h6>
                                <span class="badge bg-<?= $c['status'] === 'quitado' ? 'success' : ($c['status'] === 'com_atraso' ? 'danger' : 'primary') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-2"><?= date('d/m/Y', strtotime($c['created_at'])) ?></p>
                            <div class="mb-2">
                                <span class="fw-bold">Total:</span> R$ <?= number_format($c['total_geral'], 2, ',', '.') ?><br>
                                <span class="fw-bold">Parcelas:</span> <?= $c['quantidade_parcelas'] ?>x de R$ <?= number_format($c['total_geral'] / $c['quantidade_parcelas'], 2, ',', '.') ?><br>
                                <span class="fw-bold">Pagas:</span> <?= $c['parcelas_pagas'] ?? 0 ?> / <?= $c['quantidade_parcelas'] ?>
                            </div>
                            <?php if (!empty($c['proximo_vencimento'])): ?>
                                <p class="small text-muted mb-2">
                                    <i class="fas fa-calendar"></i> Próximo vencimento: <?= date('d/m/Y', strtotime($c['proximo_vencimento'])) ?>
                                </p>
                            <?php endif; ?>
                            <a href="/meu-carne/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fas fa-eye"></i> Ver Detalhes
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
