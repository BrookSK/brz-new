<?php
$title = 'Seja um Redirecionador — Braziliana';
$tabelaPesos = is_array($tabelaPesos ?? null) ? $tabelaPesos : [];
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
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="/contato" class="btn btn-light btn-lg px-4"><i class="fas fa-paper-plane me-2"></i>Quero ser redirecionador</a>
                    <a href="#tabela-precos" class="btn btn-outline-light btn-lg px-4"><i class="fas fa-table me-2"></i>Ver tabela de preços</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- O que é -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-3">Redirecione seus envios para o Brasil com a Braziliana Global</h2>
                <p class="text-muted">Se você já trabalha com redirecionamento de produtos importados e busca uma solução eficiente para enviar seus pacotes ao Brasil, a Braziliana Global coloca nossa estrutura logística à sua disposição.</p>
                <p class="text-muted">Você tem acesso a um <strong>painel exclusivo</strong>, onde cadastra seus clientes, cria os envios, acompanha os pagamentos e gera as etiquetas de cada pacote.</p>
                <p class="text-muted">A partir da nossa sede, nós cuidamos do processo de envio internacional até o Brasil. Para a etapa final da entrega (last mile), contamos com uma operação integrada aos Correios do Brasil, que realizam a entrega diretamente no endereço do destinatário, com rastreamento.</p>
                <p class="text-muted">Nosso preço é calculado por quilo, e a cubagem não interfere no valor do envio, proporcionando mais previsibilidade na hora de calcular seus custos.</p>
                <p class="text-muted">A tabela de preços da Braziliana Global contempla o transporte da nossa sede até o endereço final no Brasil. O envio do pacote até a nossa sede possui custo local separado, quando não houver acordo específico.</p>
                <a href="/contato" class="btn btn-primary mt-2"><i class="fas fa-user-plus me-2"></i>Falar com a equipe</a>
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
                ['icon' => 'fa-user-plus',       'titulo' => '1. Cadastre seus clientes',   'desc' => 'No painel, cadastre o destinatário final com nome, CPF, data de nascimento (18+), endereço no Brasil e contato. O CEP preenche o endereço automaticamente.'],
                ['icon' => 'fa-box',             'titulo' => '2. Crie o envio',              'desc' => 'Informe peso, dimensões e os produtos (com NCM). O sistema calcula o valor pela tabela de pesos e preços.'],
                ['icon' => 'fa-credit-card',     'titulo' => '3. Pague pelo sistema',        'desc' => 'Pague o envio direto no painel via cartão. Se preferir, anexe o comprovante de pagamento para conferência.'],
                ['icon' => 'fa-tag',             'titulo' => '4. Gere a etiqueta',           'desc' => 'Depois de pagar, o sistema libera a geração. Você mesmo gera a etiqueta com o código de rastreio, imprime e cola na caixa.'],
                ['icon' => 'fa-truck-ramp-box',  'titulo' => '5. Coleta ou envio',           'desc' => 'Agende uma coleta na sua porta ou, se preferir, envie o pacote você mesmo para o nosso ponto de recebimento.'],
                ['icon' => 'fa-weight-hanging',  'titulo' => '6. Conferência',               'desc' => 'Conferimos peso e dimensões reais. Se houver diferença em relação ao informado, cobramos ou reembolsamos.'],
                ['icon' => 'fa-plane',           'titulo' => '7. Envio internacional',       'desc' => 'Despachamos o pacote ao Brasil com todo o processo aduaneiro cuidado pela nossa equipe.'],
                ['icon' => 'fa-house-user',      'titulo' => '8. Entrega e rastreio',      'desc' => 'O destinatário acompanha tudo pelo código de rastreio até receber em casa.'],
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

<!-- Tabela de pesos e preços -->
<section class="py-5" id="tabela-precos">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Tabela de pesos e preços</h2>
            <p class="text-muted">Valores do envio internacional em dólar (USD), por faixa de peso. Peso máximo de 30 kg por pacote.</p>
        </div>

        <?php if (empty($tabelaPesos)): ?>
        <div class="alert alert-light border text-center">
            Nossa tabela de preços está sendo atualizada. <a href="/contato">Fale com a gente</a> para receber os valores.
        </div>
        <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 redir-tabela">
                            <thead>
                                <tr>
                                    <th class="ps-4">Faixa de peso</th>
                                    <th class="pe-4 text-end">Valor (USD)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tabelaPesos as $row): ?>
                                <tr>
                                    <td class="ps-4">até <?= number_format((float)$row['peso_ate_kg'], 3, ',', '.') ?> kg</td>
                                    <td class="pe-4 text-end fw-bold text-primary">US$ <?= number_format((float)$row['valor_usd'], 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-muted small mt-3 text-center">
                    <i class="fas fa-circle-info me-1"></i>O valor é calculado pela faixa de peso do pacote. Se o peso real conferido for diferente do informado, a diferença é cobrada ou reembolsada.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Formas de entrega -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Duas formas de entregar o pacote</h2>
            <p class="text-muted">Escolha o que for mais prático para você</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="redir-card h-100 text-center">
                    <i class="fas fa-people-carry-box text-primary fa-3x mb-3"></i>
                    <h5 class="fw-bold">Agende uma coleta</h5>
                    <p class="text-muted mb-0">Marque data e horário no painel e passamos para buscar o pacote no endereço combinado.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-5">
                <div class="redir-card h-100 text-center">
                    <i class="fas fa-dolly text-primary fa-3x mb-3"></i>
                    <h5 class="fw-bold">Envie você mesmo</h5>
                    <p class="text-muted mb-0">Prefere despachar você mesmo? Após ativar seu acesso, o endereço do nosso ponto de recebimento fica disponível no painel. Basta enviar e marcar como "enviado".</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Perguntas frequentes</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqRedir">
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">Quem pode ser redirecionador?</button></h2>
                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">Qualquer pessoa ou empresa que venda ou revenda produtos para clientes no Brasil e queira usar nossa estrutura de envio internacional. Entre em contato para ativar seu acesso ao painel.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">Como o preço é calculado?</button></h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">Pela faixa de peso do pacote, conforme a tabela acima (em dólar). O peso máximo é de 30 kg por pacote. Depois da conferência do peso real, se houver diferença, cobramos ou reembolsamos automaticamente.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">Como pago pelos envios?</button></h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">Direto pelo painel, via cartão. Você também pode anexar o comprovante de pagamento para conferência da nossa equipe.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">O pacote tem rastreio?</button></h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">Sim. Depois de pagar, o sistema libera a geração da etiqueta e você mesmo a gera pelo painel, já com o código de rastreio. O destinatário acompanha a entrega até receber em casa.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">O que é o NCM?</button></h2>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">NCM (Nomenclatura Comum do Mercosul) é o código que classifica o produto para a alfândega. É obrigatório para gerar a etiqueta. No painel, basta começar a digitar o nome ou o código do produto e o sistema sugere o NCM automaticamente.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">Como funcionam as divergências de peso?</button></h2>
                        <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">Depois que o pacote chega até nós, conferimos o peso e as dimensões reais. Se o peso real for maior que o informado, você recebe um aviso com a diferença a pagar; se for menor, o excedente é reembolsado. Dica: use uma balança precisa para evitar cobranças extras.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">Posso enviar qualquer produto?</button></h2>
                        <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">Não. Produtos proibidos pela alfândega brasileira (armas, drogas, medicamentos controlados, entre outros) não podem ser enviados.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">Quanto tempo demora a entrega?</button></h2>
                        <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">O prazo varia de 15 a 45 dias úteis, dependendo da liberação aduaneira.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">O destinatário paga impostos?</button></h2>
                        <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">Sim, a Receita Federal pode cobrar impostos de importação. O valor depende do tipo de produto e do valor declarado.</div></div>
                    </div>
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded overflow-hidden">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq10">Posso cancelar um envio?</button></h2>
                        <div id="faq10" class="accordion-collapse collapse" data-bs-parent="#faqRedir"><div class="accordion-body text-muted">Antes da coleta ou do envio à nossa sede, fale com o suporte. Depois que o pacote já está com a gente, não é possível cancelar.</div></div>
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
    .redir-tabela thead th {
        background: #0b1f3a;
        color: #fff;
        border: none;
    }
    .redir-tabela tbody tr:nth-child(even) {
        background: #f8fafc;
    }
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
