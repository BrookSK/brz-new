<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-9 mx-auto">
            <div class="text-center mb-4">
                <h1 class="display-5 mb-2">Status do Pedido: Entenda o Fluxo</h1>
                <p class="lead text-muted mb-0">Veja como seu pedido evolui do pagamento até a entrega.</p>
            </div>

            <div class="alert alert-info">
                <strong>Importante:</strong> alguns status podem depender de atualizações manuais e/ou eventos de transporte.
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="mb-3">Fluxo de Status</h4>

                    <div class="mb-3">
                        <div class="fw-bold">1) Pendente</div>
                        <div class="text-muted">Pedido criado e aguardando confirmação do pagamento.</div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold">2) Processando</div>
                        <div class="text-muted">Pagamento em processamento/validação e preparação inicial do pedido.</div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold">3) Pago</div>
                        <div class="text-muted">Pagamento confirmado. A partir daqui o pedido segue para preparação logística.</div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold">4) Caixa Fechada</div>
                        <div class="text-muted">Pedido embalado e pronto para geração de etiqueta.</div>
                        <div class="small mt-2">
                            <strong>Obrigatório antes de gerar etiqueta:</strong>
                            <div class="text-muted">preencher altura, largura, comprimento e peso real do pedido.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold">5) Etiqueta gerada</div>
                        <div class="text-muted">Etiqueta de envio gerada (ex.: Correios Mundial / Correios). O pedido já possui rastreio.</div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold">6) Em transporte</div>
                        <div class="text-muted">Pedido enviado/despachado e em rota de transporte.</div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold">7) Aguardando Liberação Aduaneira</div>
                        <div class="text-muted">Chegou ao Brasil e aguarda liberação/tributação (quando aplicável).</div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold">8) Enviado ao destinatário</div>
                        <div class="text-muted">Após liberação e/ou pagamento de tributos, foi encaminhado para entrega final.</div>
                    </div>

                    <div class="mb-0">
                        <div class="fw-bold">9) Entregue</div>
                        <div class="text-muted">Pedido entregue ao destinatário.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h4 class="mb-2">Dúvidas frequentes</h4>
                    <div class="text-muted">Se você tiver dúvidas sobre o seu pedido, acesse nossa página de <a href="/faq">FAQ</a> ou entre em contato.</div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
