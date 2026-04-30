<?php $title = 'Termos do Carnê Braziliana'; ?>
<?php ob_start(); ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="mb-4">Termos e Condições do Carnê Braziliana</h2>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>1. Como funciona o Carnê</h5>
                    <p>O Carnê Braziliana é um método de pagamento parcelado via boletos bancários. Ao escolher este método, o valor da compra será dividido em parcelas mensais (de 1 a 12 vezes), cada uma composta por dois boletos separados.</p>

                    <h5>2. Estrutura das Parcelas</h5>
                    <p>Cada parcela mensal gera dois boletos:</p>
                    <ul>
                        <li>Boleto 1 (Câmbio Real): referente ao valor dos produtos</li>
                        <li>Boleto 2 (Câmbio Real Taxas): referente às taxas de serviço, impostos e demais valores</li>
                    </ul>
                    <p>A parcela só será considerada quitada quando ambos os boletos forem pagos.</p>

                    <h5>3. Prazo de Vencimento</h5>
                    <p>Cada parcela possui prazo de 7 dias para pagamento a partir da data de geração. Parcelas não pagas dentro do prazo serão marcadas como vencidas e posteriormente em atraso.</p>

                    <h5>4. Política de Envio</h5>
                    <p>O envio do pedido será realizado somente após a quitação total de todas as parcelas. Enquanto houver parcelas pendentes, o pedido permanecerá aguardando.</p>

                    <h5>5. Inadimplência</h5>
                    <p>Parcelas em atraso podem resultar em bloqueio do carnê. O sistema manterá o registro de todas as parcelas e seus respectivos status.</p>

                    <h5>6. Segunda Via</h5>
                    <p>O cliente pode solicitar segunda via de boletos a qualquer momento através da área "Meus Carnês" na sua conta.</p>

                    <h5>7. Produto Indisponível</h5>
                    <p>Caso o produto ou variação não esteja mais disponível no momento da compra interna, a equipe entrará em contato para oferecer alternativas: crédito em carteira ou pedido complementar com a diferença de valor.</p>

                    <h5>8. Diferença de Valor</h5>
                    <p>Se houver necessidade de ajuste de valor (produto substituto mais caro), será gerado um pedido complementar separado contendo apenas a diferença.</p>

                    <h5>9. Adiantamento de Parcelas</h5>
                    <p>O cliente pode pagar parcelas futuras antecipadamente, se desejar.</p>

                    <h5>10. Cancelamento por Inadimplência</h5>
                    <p>Caso o cliente permaneça com parcelas em atraso por 2 (dois) meses consecutivos sem efetuar o pagamento, o sistema enviará um aviso de cancelamento por e-mail. A partir do recebimento desse aviso, o cliente terá 7 (sete) dias para regularizar todas as parcelas em atraso (quitando ambos os boletos de cada parcela pendente).</p>
                    <p>Se a regularização não for realizada dentro do prazo, o carnê será automaticamente cancelado. Nesse caso, o cliente precisará realizar uma nova compra com um novo carnê para dar continuidade ao pedido.</p>
                    <p>O cancelamento do carnê não gera reembolso automático das parcelas já pagas. Eventuais créditos serão analisados caso a caso pela equipe.</p>

                    <h5>11. Regras Gerais</h5>
                    <ul>
                        <li>O Carnê Braziliana está disponível apenas para pagamentos em Reais (BRL) e envios para o Brasil</li>
                        <li>Não se trata de assinatura ou recorrência automática</li>
                        <li>O parcelamento é controlado internamente pelo sistema</li>
                        <li>Todas as parcelas e boletos podem ser acompanhados na área do cliente</li>
                    </ul>
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
