<?php $title = 'Carnê Braziliana'; ?>
<?php ob_start(); ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="mb-2">Carnê Braziliana</h2>
            <p class="text-muted mb-4">Como funciona o parcelamento sem juros via boleto bancário</p>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="alert alert-light border mb-4">
                        <p class="mb-0">O Carnê Braziliana permite parcelar sua compra em até <strong>12 parcelas mensais sem juros</strong>, pagas por boleto bancário. Simples, transparente e sem cartão de crédito.</p>
                    </div>

                    <h5>1. O que é o Carnê</h5>
                    <p>As compras via Carnê são realizadas sempre pelo <strong>valor integral</strong> dos produtos. Promoções, cupons e descontos não se aplicam a este método de pagamento. O cliente escolhe a quantidade de parcelas no checkout, de <strong>1 a 12</strong>.</p>
                    <p>O Carnê está disponível apenas para <strong>produtos oferecidos pela Braziliana no site e com estoque disponível nas lojas</strong>. Produtos de close out (pontas de estoque e liquidações especiais) não se qualificam para o Carnê. Nesses casos, entre em contato com o nosso Atendimento para avaliarmos uma possível exceção.</p>

                    <h5>2. Estrutura das Parcelas</h5>
                    <p>O carnê é uma cobrança única: o valor total da compra, já com tudo incluso (produtos, taxa de serviço e impostos), é dividido na quantidade de parcelas escolhida pelo cliente. Cada parcela corresponde a <strong>um único boleto mensal</strong> (Câmbio Real).</p>

                    <h5>3. Compra dos Produtos</h5>
                    <p>A compra dos seus produtos nos EUA é realizada após a confirmação do pagamento da primeira parcela. Quanto antes a primeira parcela for paga, maior a chance de garantirmos todos os itens do seu pedido antes que esgotem nas lojas.</p>

                    <h5>4. Vencimento e Pagamento em Atraso</h5>
                    <p>Os boletos são emitidos mensalmente, com prazo de <strong>7 dias</strong> para pagamento a partir da emissão.</p>
                    <div class="alert alert-warning border-start border-warning border-3 bg-light">
                        <p class="mb-0">Parcelas pagas após o vencimento sofrem <strong>multa de 2%</strong> sobre o valor da parcela, acrescida de <strong>juros de mora de 1% ao mês</strong>, calculados proporcionalmente aos dias de atraso, conforme o Código de Defesa do Consumidor (art. 52, §1º, Lei nº 8.078/90).</p>
                    </div>

                    <h5>5. Envio</h5>
                    <p>O envio é realizado somente após a quitação total do carnê. Após a compensação da última parcela, seu pedido embarca no próximo envio programado para o Brasil.</p>

                    <h5>6. Atraso e Cancelamento do Carnê</h5>
                    <p>Havendo parcela em atraso, o cliente será notificado por e-mail e terá <strong>7 dias corridos</strong> para regularizar o pagamento, com os acréscimos previstos no item 4.</p>
                    <div class="alert alert-light border">
                        <p>Não havendo regularização dentro do prazo, o carnê será <strong>cancelado</strong>: os produtos retornam ao estoque da Braziliana e ficam disponíveis para revenda. Dos valores já pagos:</p>
                        <ul class="mb-2">
                            <li>A parte referente a <strong>impostos de importação</strong> é convertida <strong>integralmente</strong> em crédito em carteira, pois o imposto só é recolhido no envio;</li>
                            <li>A parte referente a <strong>produtos e serviço</strong> é convertida em crédito com retenção de <strong>20%</strong> a título de custos operacionais de compra, armazenagem e revenda.</li>
                        </ul>
                        <p class="mb-0">O crédito em carteira pode ser utilizado em qualquer compra e tem <strong>validade de 60 dias</strong> a contar da data do cancelamento. Não há reembolso em dinheiro.</p>
                    </div>

                    <h5>7. Cancelamento pelo Cliente</h5>
                    <p>O cliente pode solicitar o cancelamento do carnê a qualquer momento. Nesse caso, aplicam-se as mesmas condições do item 6.</p>

                    <h5>8. Produto Indisponível</h5>
                    <p>Se algum produto não estiver mais disponível no momento da compra, nossa equipe entrará em contato para oferecer, à escolha do cliente:</p>
                    <ul>
                        <li>produto substituto equivalente;</li>
                        <li>crédito integral em carteira referente ao item;</li>
                        <li>no caso de substituto de valor maior, pedido complementar contendo apenas a diferença.</li>
                    </ul>

                    <h5>9. Antecipação de Parcelas</h5>
                    <p>O cliente pode antecipar o pagamento de parcelas futuras a qualquer momento, sem custo adicional, pela área "Meus Carnês".</p>

                    <h5>10. Segunda Via</h5>
                    <p>A segunda via de qualquer boleto pode ser emitida a qualquer momento na área "Meus Carnês" da sua conta.</p>

                    <h5>11. Análise e Limites</h5>
                    <p>O Carnê Braziliana está sujeito a análise de aprovação. A Braziliana pode, a seu critério, definir limite de valor e de quantidade de parcelas por cliente.</p>

                    <h5>12. Regras Gerais</h5>
                    <ul>
                        <li>Disponível apenas para pagamentos em Reais (BRL) e envios para o Brasil;</li>
                        <li>Não se trata de assinatura nem de cobrança automática ou recorrente;</li>
                        <li>Todas as parcelas e boletos podem ser acompanhados na área do cliente;</li>
                        <li>Clientes brasileiros devem possuir CPF cadastrado.</li>
                    </ul>

                    <hr class="my-4">
                    <p class="small text-muted mb-0">Este regulamento observa o Código de Defesa do Consumidor (Lei nº 8.078/90), em especial os artigos 52 e 53. Em caso de cancelamento, a retenção prevista no item 6 corresponde aos custos operacionais efetivos da Braziliana, sendo o saldo restante integralmente disponibilizado ao cliente na forma de crédito. Dúvidas: fale com nossa equipe pelo WhatsApp ou pela área de Atendimento do site.</p>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="/" class="btn btn-outline-secondary"><i class="fas fa-home"></i> Voltar à Loja</a>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
