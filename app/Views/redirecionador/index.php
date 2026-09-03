<?php
$title = 'Seja um Redirecionador — Braziliana';
$enderecoSede = $enderecoSede ?? '1227 W Broad St, Saint Pauls, NC 28384';
?>
<?php ob_start(); ?>

<!-- Hero -->
<section class="redir-hero text-white text-center">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <span class="badge bg-light text-dark mb-3 px-3 py-2"><i class="fas fa-truck-fast me-2"></i>Programa de Redirecionadores</span>
                <h1 class="display-4 fw-bold mb-3">Envie os pacotes dos seus clientes pela Braziliana</h1>
                <p class="lead mb-4">Você cuida da venda, a gente cuida da logística internacional. Painel próprio, tabela de preços transparente, etiqueta com rastreio e entrega no Brasil.</p>
                <a href="/contato" class="btn btn-light btn-lg px-4"><i class="fas fa-paper-plane me-2"></i>Quero ser redirecionador</a>
            </div>
        </div>
    </div>
</section>

<!-- O que é -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-3">O que é o redirecionamento?</h2>
                <p class="text-muted">Se você vende ou revende produtos para clientes no Brasil, o programa de redirecionadores da Braziliana coloca toda a nossa estrutura de envio internacional à sua disposição.</p>
                <p class="text-muted">Você tem acesso a um <strong>painel exclusivo</strong> onde cadastra seus clientes, cria os envios, acompanha os pagamentos e gera as etiquetas. Nós recebemos o pacote, conferimos, despachamos ao Brasil e entregamos ao destinatário final, tudo com rastreio.</p>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-6"><div class="redir-card h-100"><i class="fas fa-desktop text-primary fa-2x mb-2"></i><h6 class="fw-bold">Painel próprio</h6><p class="small text-muted mb-0">Gerencie tudo em um só lugar.</p></div></div>
                    <div class="col-6"><div class="redir-card h-100"><i class="fas fa-table text-primary fa-2x mb-2"></i><h6 class="fw-bold">Preços transparentes</h6><p class="small text-muted mb-0">Tabela de pesos e valores clara.</p></div></div>
                    <div class="col-6"><div class="redir-card h-100"><i class="fas fa-tag text-primary fa-2x mb-2"></i><h6 class="fw-bold">Etiqueta com rastreio</h6><p class="small text-muted mb-0">Gerada automaticamente após o pagamento.</p></div></div>
                    <div class="col-6"><div class="redir-card h-100"><i class="fas fa-plane-departure text-primary fa-2x mb-2"></i><h6 class="fw-bold">Entrega no Brasil</h6><p class="small text-muted mb-0">Acompanhamento ponta a ponta.</p></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Como funciona (passo a passo) -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Como funciona o processo</h2>
            <p class="text-muted">Do cadastro do cliente até a entrega no destinatário</p>
        </div>
        <div class="row g-4">
            <?php
            $passos = [
                ['icon' => 'fa-user-plus',       'titulo' => '1. Cadastre seus clientes',   'desc' => 'No painel, cadastre o destinatário final com CPF, endereço no Brasil e contato. É rápido e o CEP preenche o endereço automaticamente.'],
                ['icon' => 'fa-box',             'titulo' => '2. Crie o envio',              'desc' => 'Informe peso, dimensões e os produtos (com NCM). O sistema calcula o valor pela tabela de pesos e preços.'],
                ['icon' => 'fa-credit-card',     'titulo' => '3. Pague pelo sistema',        'desc' => 'Pague o envio direto no painel via cartão. Se preferir, anexe o comprovante de pagamento para conferência.'],
                ['icon' => 'fa-tag',             'titulo' => '4. Gere a etiqueta',           'desc' => 'Após o pagamento confirmado, a etiqueta é gerada com o código de rastreio. Imprima e cole na caixa.'],
                ['icon' => 'fa-truck-ramp-box',  'titulo' => '5. Coleta ou envio à sede',    'desc' => 'Agende a coleta na sua porta ou, se preferir, envie o pacote você mesmo para o nosso endereço de recebimento.'],
                ['icon' => 'fa-weight-hanging',  'titulo' => '6. Conferência',               'desc' => 'Conferimos peso e dimensões reais. Se houver diferença em relação ao informado, cobramos ou reembolsamos.'],
                ['icon' => 'fa-plane',           'titulo' => '7. Envio internacional',       'desc' => 'Despachamos o pacote ao Brasil com todo o processo aduaneiro cuidado pela nossa equipe.'],
                ['icon' => 'fa-house-circle-check','titulo' => '8. Entrega e rastreio',      'desc' => 'O destinatário acompanha tudo pelo código de rastreio até receber em casa.'],
            ];
            foreach ($passos as $p): ?>
            <div class="col-md-6 col-lg-3">
                <div class="redir-step h-100">
                    <div class="redir-step-icon"><i class="fas <?= $p['icon'] ?>"></i></div>
                    <h6 class="fw-bold"><?= htmlspecialchars($p['titulo']) ?></h6>
                    <p class="small text-muted mb-0"><?= htmlspecialchars($p['desc']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Coleta vs Envio à sede -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Duas formas de entregar o pacote</h2>
            <p class="text-muted">Escolha o que for mais prático para você</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="redir-card h-100 text-center">
                    <i class="fas fa-hand-holding-box text-primary fa-3x mb-3"></i>
                    <h5 class="fw-bold">Agende uma coleta</h5>
                    <p class="text-muted">Marque data e horário no painel e passamos para buscar o pacote no endereço combinado.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-5">
                <div class="redir-card h-100 text-center">
                    <i class="fas fa-dolly text-primary fa-3x mb-3"></i>
                    <h5 class="fw-bold">Envie para a nossa sede</h5>
                    <p class="text-muted mb-3">Prefere enviar você mesmo? Basta despachar o pacote para o nosso endereço de recebimento e marcar como "enviado" no painel. Você recebe a confirmação e nós seguimos com o processo.</p>
                    <div class="redir-endereco">
                        <div class="small text-uppercase text-muted mb-1"><i class="fas fa-map-marker-alt me-1"></i>Endereço de recebimento</div>
                        <div class="fw-bold"><?= htmlspecialchars($enderecoSede) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA final -->
<section class="redir-cta text-white text-center py-5">
    <div class="container">
        <h2 class="fw-bold mb-3">Pronto para começar?</h2>
        <p class="lead mb-4">Fale com a gente e ative seu acesso ao painel de redirecionador.</p>
        <a href="/contato" class="btn btn-light btn-lg px-4"><i class="fas fa-envelope me-2"></i>Entrar em contato</a>
    </div>
</section>

<style>
    .redir-hero {
        background: linear-gradient(135deg, #0b1f3a 0%, #12407a 100%);
    }
    .redir-cta {
        background: linear-gradient(135deg, #12407a 0%, #0b1f3a 100%);
    }
    .redir-card {
        background: #fff;
        border: 1px solid #eef0f4;
        border-radius: 14px;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(0,0,0,.04);
    }
    .redir-step {
        background: #fff;
        border-radius: 14px;
        padding: 24px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,.05);
        text-align: center;
    }
    .redir-step-icon {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: rgba(18,64,122,.1);
        color: #12407a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin: 0 auto 14px;
    }
    .redir-endereco {
        background: #f0f9ff;
        border: 1px dashed #7dd3fc;
        border-radius: 10px;
        padding: 14px;
    }
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
