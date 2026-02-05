<?php ob_start(); ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="h3 mb-3" style="color:#0b1f3a; font-weight: 800;">Clube Brasiliana: como funciona</h1>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <div class="text-muted mb-2">Resumo</div>
                    <div class="fw-semibold">O Clube Brasiliana é um programa de benefícios com créditos internos, com desconto progressivo e cashback em créditos, conforme regras do sistema.</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">Ativação</h2>
                    <div class="text-muted">Para ativar e manter o acesso ao Clube, você precisa manter um saldo mínimo de <strong>$ 39,00</strong> em créditos internos na carteira (considerando o equivalente em USD).</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">Produtos elegíveis</h2>
                    <div class="text-muted">Somente produtos marcados como <strong>Clube Ativo</strong> participam dos benefícios.</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">Desconto progressivo e cashback</h2>
                    <div class="text-muted">Os benefícios são aplicados somente sobre os produtos com Clube Ativo, conforme peso total e regras configuradas. O cashback retorna para sua carteira como créditos internos, na mesma moeda do pedido.</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">Rendimento do Clube (créditos internos)</h2>
                    <div class="text-muted">O Clube pode gerar créditos adicionais periodicamente, somente enquanto o saldo mínimo for mantido. Esse rendimento é configurado pelo sistema e não é sacável.</div>
                </div>
            </div>

            <div class="alert alert-info" style="border-radius:14px;">
                <div class="fw-bold">Importante</div>
                <div class="small">O Clube Brasiliana é um programa de benefícios. <strong>Não é instituição financeira</strong> e não oferece investimento. Todos os valores são <strong>créditos internos</strong>, não sacáveis e auditáveis.</div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="/produtos-clube" class="btn btn-primary"><i class="fas fa-crown me-2"></i>Ir para produtos do Clube</a>
                <a href="/minha-conta" class="btn btn-outline-primary"><i class="fas fa-wallet me-2"></i>Minha conta</a>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php $title = 'Clube Brasiliana - Como funciona'; ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
