<?php

function send_redirecionamento_email($user, $redirecionamento_id) {
    $numero_suite = get_post_meta($redirecionamento_id, '_numero_suite', true);
    $nome_produto = get_post_meta($redirecionamento_id, '_nome', true);
    $descricao = get_post_meta($redirecionamento_id, '_descricao', true);
    $fornecedor = get_post_meta($redirecionamento_id, '_fornecedor', true);
    $recebimento = get_post_meta($redirecionamento_id, '_recebimento', true);
    $peso = get_post_meta($redirecionamento_id, '_peso', true);
    $quantidade = get_post_meta($redirecionamento_id, '_quantidade', true);

    $to = $user->user_email;
    $subject = 'Redirecionamento Disponível';
    $headers = array('Content-Type: text/html; charset=UTF-8');

    $message = '
    <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 0;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 8px;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                }
                .email-header {
                    background-color: #222d5d;
                    padding: 10px;
                    text-align: center;
                    color: white;
                }
                .email-body {
                    padding: 20px;
                    color: #333;
                    text-align: left;
                }
                .email-body p {
                    line-height: 1.6;
                    margin: 10px 0;
                }
                .email-footer {
                    background-color: #222d5d;
                    color: #ffffff;
                    text-align: center;
                    padding: 10px;
                    font-size: 12px;
                }
                .email-footer a {
                    color: #ffffff;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <h1 style="color: white;">Seu produto foi cadastrado!</h1>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <img src="https://brazilianashop.com.br/wp-content/uploads/2024/10/White-Green-Modern-Minimalist-Pop-Up-Notification-Voucher-Discount-Nature-Instagram-Post-5.png" alt="Logo Braziliana" style="max-width: 180px; height: auto;">
                </div>
                <div class="email-body">
                    <p>Olá, ' . $user->display_name . '.</p>
                    <p>Temos ótimas notícias! O seu produto <strong>' . $nome_produto . '</strong> foi cadastrado com sucesso no nosso sistema e já está disponível em sua suíte.</p>
                    <p><strong>Dados da compra:</strong></p>
                    <p><strong>Observação:</strong> ' . $descricao . '</p>
                    <p><strong>Número da Suíte:</strong> ' . $numero_suite . '</p>
                    <p><strong>Nome do Produto:</strong> ' . $nome_produto . '</p>
                    <p><strong>Fornecedor:</strong> ' . $fornecedor . '</p>
                    <p><strong>Data de Recebimento:</strong> ' . $recebimento . '</p>
                    <p><strong>Peso:</strong> ' . $peso . ' kg</p>
                    <p><strong>Quantidade:</strong> ' . $quantidade . '</p>
                    <p>Você tem até 30 dias para concluir todas as suas compras e solicitar o envio. Para acessar sua suíte e verificar seus produtos, basta <a href="https://brazilianashop.com.br/carrinho/">clicar aqui</a>.</p>
                    <p>Caso tenha alguma dúvida ou precise de assistência, não hesite em <a href="https://brazilianashop.com.br/suporte/">entrar em contato com o nosso suporte aqui</a>.</p>
                    <p>Lembramos que, após os 30 dias, uma taxa diária de armazenamento será aplicada a partir do 31º dia até o 40º dia. Caso o envio não seja solicitado até o 42º dia, sua mercadoria será considerada abandonada.</p>
                    <p>Obrigado por escolher a Braziliana. Estamos sempre à disposição para ajudar!</p>
                    <p>Atenciosamente,<br>Equipe Braziliana</p>
                </div>
            </div>
        </body>
    </html>';

    // Enviando o e-mail
    wp_mail($to, $subject, $message, $headers);
}
