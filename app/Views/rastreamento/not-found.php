<?php ob_start(); ?>
<div class="container py-4">
    <div class="row">
        <div class="col-lg-6 mx-auto text-center">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
                    <h3>Pedido Não Encontrado</h3>
                    <p class="text-muted mb-4">O número do pedido informado não foi encontrado em nosso sistema.</p>
                    <a href="/rastreamento" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Tentar Novamente
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
