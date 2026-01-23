<?php ob_start(); ?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-search-location"></i> Rastrear Pedido</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Digite o número do seu pedido para acompanhar o status de entrega.</p>
                
                <form method="GET" action="/rastreamento">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="id" placeholder="Número do pedido" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Rastrear
                        </button>
                    </div>
                </form>
                
                <hr>
                
                <div class="text-center">
                    <h6 class="mb-3">Fluxo do Processo</h6>
                    <div class="row text-center">
                        <div class="col-6 col-md-4 mb-3">
                            <i class="fas fa-shopping-cart fa-2x text-secondary mb-2"></i>
                            <p class="small mb-0">Seleção</p>
                        </div>
                        <div class="col-6 col-md-4 mb-3">
                            <i class="fas fa-calculator fa-2x text-info mb-2"></i>
                            <p class="small mb-0">Cobrança</p>
                        </div>
                        <div class="col-6 col-md-4 mb-3">
                            <i class="fas fa-plane-departure fa-2x text-warning mb-2"></i>
                            <p class="small mb-0">Despacho</p>
                        </div>
                        <div class="col-6 col-md-4 mb-3">
                            <i class="fas fa-plane fa-2x text-primary mb-2"></i>
                            <p class="small mb-0">Trânsito</p>
                        </div>
                        <div class="col-6 col-md-4 mb-3">
                            <i class="fas fa-gavel fa-2x text-warning mb-2"></i>
                            <p class="small mb-0">Alfândega</p>
                        </div>
                        <div class="col-6 col-md-4 mb-3">
                            <i class="fas fa-truck fa-2x text-success mb-2"></i>
                            <p class="small mb-0">Entrega</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
