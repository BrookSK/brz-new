<?php

function send_order_tracking_code_email($order, $tracking_code) {
    $to = $order->get_billing_email();
    $subject = 'Sua caixa foi enviada!';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $message = '
        <p>Hello!</p>
<p>Temos uma ótima notícia: a etiqueta de envio da sua caixa foi gerada com sucesso!</p>
<p>Seu código de rastreio é: <b>' . esc_html($tracking_code) . '</b></p>
<p>A partir de agora, você já pode acompanhar sua encomenda pelo site dos Correios:</p>
<p>👉 <a href="https://rastreamento.correios.com.br/app/index.php">Correios</a></p>
<p><b>Atenção:</b> Pode levar de 7 a 10 dias para que o status comece a ser atualizado, pois a carga está sendo transportada da Carolina do Norte até Miami, onde aguardará o próximo voo com destino ao Brasil. Após a chegada no Brasil, a caixa precisa ser oficialmente recebida pelos Correios para que o rastreio mostre o status “objeto recebido pelos Correios”.</p>
<p>📦 <b>Rastreamento e Pagamento de Impostos:</b></p>
<p>A partir de agora, o rastreamento e o pagamento dos impostos são de responsabilidade sua, o cliente, diretamente no portal Minhas Importações dos Correios:</p>
<p>👉 <a href="https://cas.correios.com.br/login?service=https%3A%2F%2Fportalimportador.correios.com.br%2Fpages%2FpesquisarRemessaImportador%2FpesquisarRemessaImportador.jsf">Minhas Importações</a></p>
<p>• O prazo médio de entrega é de 15 a 20 dias úteis, mas esse prazo é apenas uma estimativa.<br>
• A Receita Federal não garante prazos para liberação da fiscalização.<br>
• Se sua encomenda contiver produtos sujeitos à fiscalização da ANVISA, o prazo de análise pode ser mais longo — a ANVISA costuma levar pelo menos 30 dias para finalizar a vistoria.</p>
<p>Para mais informações e dúvidas frequentes, acesse:</p>
<p>👉 <a href="https://www.correios.com.br/acesso-a-informacao/perguntas-frequentes">Perguntas Frequentes dos Correios</a></p>
<p>⚠️ <b>Informações Importantes:</b></p>
<p>• Os Correios não enviam carta nem notificação sobre os impostos.<br>
• É fundamental que você acesse regularmente o portal Minhas Importações no site dos Correios para acompanhar sua encomenda e verificar se há tributos a pagar.<br>
• O prazo para pagamento dos impostos é de 20 dias corridos após a cobrança.<br>
• Caso você perca o prazo, sua caixa será devolvida aos Estados Unidos. Esse processo pode demorar até 9 meses, e todos os custos de retorno e reenvio ao Brasil serão de sua responsabilidade.</p>
<p>⚠️ Infelizmente, não há como reverter esse processo no Brasil.</p>
<p>💰 <b>Revisão de Impostos</b></p>
<p>Se você considerar que o valor dos impostos cobrados não condiz com o valor real da sua compra + frete, é possível solicitar uma revisão de tributos no próprio portal Minhas Importações para que a Receita Federal reavalie a sua encomenda.</p>
<p>Se tiver qualquer dúvida, estamos à disposição para te ajudar!</p>
<p>Muito obrigada por me dar a oportunidade de redirecionar seus produtos importados!</p>
<p>Love,<br>Fabi</p>
    ';

    wp_mail($to, $subject, $message, $headers);
}

function send_order_tracking_code_whatsapp($order, $tracking_code) {
    $url = 'https://backend.botconversa.com.br/api/v1/webhooks-automation/catch/105646/Bws8SVPJChZC/';
    $data = array(
        'nome' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'celular' => preg_replace('/\D/', '', $order->get_billing_phone()),
        'codigo_rastreio' => $tracking_code,
        'numero_pedido' => $order->get_order_number()
    );
    
    $response = wp_remote_post($url, array(
        'method' => 'POST',
        'body' => json_encode($data),
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        error_log('Erro ao enviar código de rastreamento: ' . $response->get_error_message());
    } else {
        error_log('Request enviado com sucesso. Resposta: ' . wp_remote_retrieve_body($response));
    }
}