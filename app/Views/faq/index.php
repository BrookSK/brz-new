<?php ob_start(); ?>
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">Perguntas Frequentes</h1>
                <p class="lead text-muted">Tire suas dúvidas sobre importação e nossos serviços</p>
            </div>
            
            <!-- Busca de FAQ -->
            <div class="mb-5">
                <div class="input-group">
                    <input type="text" class="form-control" id="faq-search" placeholder="Buscar perguntas...">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
            </div>
            
            <!-- Categorias de FAQ -->
            <div class="row mb-4">
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-primary w-100" onclick="filtrarCategoria('todos')">Todos</button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-secondary w-100" onclick="filtrarCategoria('importacao')">Importação</button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-secondary w-100" onclick="filtrarCategoria('pagamento')">Pagamento</button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-secondary w-100" onclick="filtrarCategoria('entrega')">Entrega</button>
                </div>
            </div>
            
            <!-- Perguntas e Respostas -->
            <div class="accordion" id="faqAccordion">
                <!-- Importação -->
                <div class="accordion-item mb-3" data-categoria="importacao">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            <i class="fas fa-globe-americas me-2"></i>
                            Como funciona a importação de produtos dos EUA?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            A importação é simples: você escolhe os produtos em nosso catálogo, faz o pagamento e nós cuidamos de todo o processo logístico. Isso inclui despacho nos EUA, transporte aéreo, processamento aduaneiro e entrega na sua porta. Todo o processo leva de 15 a 30 dias.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item mb-3" data-categoria="importacao">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            <i class="fas fa-shield-alt me-2"></i>
                            Quais produtos posso importar?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Você pode importar eletrônicos, vestuário, acessórios e muitos outros produtos. Alguns itens como armas, medicamentos controlados e produtos piratas não são permitidos. Verifique nossa lista completa de produtos permitidos antes de comprar.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item mb-3" data-categoria="importacao">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            <i class="fas fa-calculator me-2"></i>
                            Como são calculados os impostos?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Os impostos são calculados automaticamente e já estão inclusos no preço final. Aplicamos ICMS (60%) e IPI (20%) sobre o valor do produto. Não há taxas escondidas - você vê o valor final antes de confirmar a compra.
                        </div>
                    </div>
                </div>
                
                <!-- Pagamento -->
                <div class="accordion-item mb-3" data-categoria="pagamento">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            <i class="fas fa-credit-card me-2"></i>
                            Quais formas de pagamento aceitam?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Aceitamos cartão de crédito, débito, PIX e boleto. Para compras em USD, processamos via Stripe. Para compras em BRL, usamos AppMax. Todas as transações são 100% seguras com criptografia SSL.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item mb-3" data-categoria="pagamento">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            <i class="fas fa-dollar-sign me-2"></i>
                            Posso pagar em reais ou dólares?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Sim! Você pode escolher entre pagar em BRL ou USD. A conversão é feita automaticamente com a taxa do dia. O valor final já inclui todas as taxas e impostos, sem surpresas.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item mb-3" data-categoria="pagamento">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            <i class="fas fa-lock me-2"></i>
                            Meus dados de pagamento são seguros?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Sim! Usamos os mesmos padrões de segurança dos maiores e-commerces do mundo. Seus dados são criptografados e nunca armazenados em nossos servidores. Processamos pagamentos através de gateways certificados.
                        </div>
                    </div>
                </div>
                
                <!-- Entrega -->
                <div class="accordion-item mb-3" data-categoria="entrega">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                            <i class="fas fa-truck me-2"></i>
                            Quanto tempo demora a entrega?
                        </button>
                    </h2>
                    <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            O prazo médio é de 15 a 30 dias corridos. Isso inclui o tempo de processamento nos EUA (3-5 dias), transporte aéreo (5-7 dias) e liberação aduaneira no Brasil (7-15 dias). Você pode acompanhar cada etapa pelo nosso sistema de rastreamento.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item mb-3" data-categoria="entrega">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                            <i class="fas fa-box me-2"></i>
                            Como faço para rastrear meu pedido?
                        </button>
                    </h2>
                    <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Após a compra, você receberá um código de rastreamento. Acesse nossa área de "Rastreamento" no site e informe seu código ou número do pedido. Você verá todas as etapas desde a saída dos EUA até a entrega final.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item mb-3" data-categoria="entrega">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                            <i class="fas fa-home me-2"></i>
                            Entregam em todo o Brasil?
                        </button>
                    </h2>
                    <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Sim! Fazemos entregas para todo o território nacional. O prazo pode variar ligeiramente dependendo da sua localização, mas cobrimos desde cidades grandes até localidades mais remotas.
                        </div>
                    </div>
                </div>
                
                <!-- Geral -->
                <div class="accordion-item mb-3" data-categoria="geral">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq10">
                            <i class="fas fa-headset me-2"></i>
                            Como entro em contato com o suporte?
                        </button>
                    </h2>
                    <div id="faq10" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Nosso suporte está disponível de segunda a sexta, das 9h às 18h. Você pode nos contatar por e-mail (suporte@brzlogistics.com), WhatsApp (11 99999-9999) ou através do chat em nosso site. Responderemos em até 24 horas úteis.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item mb-3" data-categoria="geral">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq11">
                            <i class="fas fa-undo me-2"></i>
                            Posso cancelar meu pedido?
                        </button>
                    </h2>
                    <div id="faq11" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Você pode cancelar seu pedido antes do envio dos EUA. Após o despacho, o cancelamento não é mais possível. Em casos especiais, entre em contato com nosso suporte para analisarmos sua situação.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item mb-3" data-categoria="geral">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq12">
                            <i class="fas fa-shield-alt me-2"></i>
                            Possuem seguro para os produtos?
                        </button>
                    </h2>
                    <div id="faq12" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Sim! Todos os pedidos possuem seguro contra perda, roubo ou danos durante o transporte. Caso ocorra qualquer problema, acionaremos o seguro e reembolsaremos 100% do valor pago ou enviaremos um novo produto.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seção de Contato -->
            <div class="text-center mt-5 p-4 bg-light rounded">
                <h4 class="mb-3">Ainda tem dúvidas?</h4>
                <p class="text-muted mb-4">Nossa equipe está pronta para ajudar</p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="/contato" class="btn btn-primary">
                        <i class="fas fa-envelope me-2"></i> Fale Conosco
                    </a>
                    <a href="/rastreamento" class="btn btn-outline-primary">
                        <i class="fas fa-search-location me-2"></i> Rastrear Pedido
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Busca em tempo real
    $('#faq-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.accordion-item').each(function() {
            const text = $(this).text().toLowerCase();
            
            if (text.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});

function filtrarCategoria(categoria) {
    if (categoria === 'todos') {
        $('.accordion-item').show();
    } else {
        $('.accordion-item').hide();
        $(`.accordion-item[data-categoria="${categoria}"]`).show();
    }
    
    // Atualizar botões
    $('.btn').removeClass('btn-primary').addClass('btn-outline-secondary');
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('btn-primary');
}
</script>

<style>
.accordion-item {
    border: 0;
    border-radius: 14px;
    box-shadow: var(--shadow-sm);
}

.accordion-button {
    border-radius: 14px;
}

.accordion-button:not(.collapsed) {
    color: rgba(11, 31, 58, 1);
    background: rgba(11, 31, 58, 0.06);
    box-shadow: none;
}

.accordion-button:focus {
    border-color: rgba(11, 31, 58, 0.20);
    box-shadow: 0 0 0 0.25rem rgba(11, 31, 58, 0.10);
}

.accordion-body {
    color: rgba(15, 23, 42, 0.88);
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
