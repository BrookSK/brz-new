<?php ob_start(); ?>
<div class="row">
    <div class="col-lg-8 mx-auto text-center">
        <h1 class="display-4 mb-4">
            <i class="fas fa-globe-americas text-primary"></i><br>
            Sistema de Logística Internacional
        </h1>
        <p class="lead mb-5">
            Importe produtos dos EUA com todo o suporte logístico e aduaneiro
        </p>
    </div>
</div>

<div class="row mb-5">
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="fas fa-search fa-3x text-primary mb-3"></i>
                <h5 class="card-title">1. Seleção</h5>
                <p class="card-text">Escolha os produtos desejados em nosso catálogo</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="fas fa-calculator fa-3x text-success mb-3"></i>
                <h5 class="card-title">2. Cobrança</h5>
                <p class="card-text">Cálculo automático de produtos, serviços e impostos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="fas fa-plane fa-3x text-info mb-3"></i>
                <h5 class="card-title">3. Transporte</h5>
                <p class="card-text">Despacho para Miami e voo para o Brasil</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="fas fa-truck fa-3x text-warning mb-3"></i>
                <h5 class="card-title">4. Entrega</h5>
                <p class="card-text">Processamento aduaneiro e entrega final via Correios</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-rocket"></i> Comece Agora</h5>
            </div>
            <div class="card-body text-center">
                <p class="mb-4">Explore nossos produtos e inicie sua importação</p>
                <a href="/produtos" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-shopping-bag"></i> Ver Produtos
                </a>
                <a href="/rastreamento" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-search-location"></i> Rastrear Pedido
                </a>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
