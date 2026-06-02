<?php

function send_storage_email($redirecionamento_id) {
    $numero_suite = get_post_meta($redirecionamento_id, '_numero_suite', true);
    $user = get_user_by_suite($numero_suite);

    if ($user) {
        $to = $user->user_email;
        $user_name = $user->display_name;
        $storage_time = get_post_meta($redirecionamento_id, '_dias_atraso', true);

        $subject = 'Armazenamento do Redirecionamento';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $message = '
        <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Mercadoria Parada no Armazém</title>
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
                        padding: 20px;
                        text-align: center;
                        color: #ffffff;
                    }
                    .email-body {
                        padding: 30px;
                        color: #333;
                        text-align: left;
                    }
                    .email-body img {
                        margin-bottom: 20px;
                        max-width: 180px;
                        height: auto;
                        display: block;
                        margin-left: auto;
                        margin-right: auto;
                    }
                    .email-body h1 {
                        color: #222d5d;
                        font-size: 24px;
                        margin-top: 0;
                        text-align: center;
                    }
                    .email-body p {
                        line-height: 1.6;
                        margin: 10px 0;
                    }
                    .email-footer {
                        background-color: #222d5d;
                        color: #ffffff;
                        text-align: center;
                        padding: 20px;
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
                    <h1>Notificação de Mercadoria</h1>
                </div>
                <div class="email-body">
                    <img src="https://www.brazilianashop.com.br/wp-content/uploads/2024/10/White-Green-Modern-Minimalist-Pop-Up-Notification-Voucher-Discount-Nature-Instagram-Post-5.png" alt="Logo Braziliana" />
                    <h1>Olá, ' . esc_html($user_name) . '.</h1>
                    <p>Estamos entrando em contato para informar que a sua mercadoria de redirecionamento encontra-se parada em nosso <strong>armazém</strong> há <strong>' . $storage_time . ' dias</strong>, e gostaríamos de ajudá-lo(a) a regularizar sua situação o mais rápido possível.</p>
                    <p>Entendemos que imprevistos podem acontecer, mas é importante que realize o envio da sua mercadoria dentro do prazo de 30 dias, pois de acordo com nossos procedimentos, após o prazo determinado, somos obrigados a tomar medidas adicionais, o que pode resultar na perda da mercadoria.</p>
                    <p>Para mais informações, entre em contato conosco pelo <a href="https://wa.me/13053638204" target="_blank">WhatsApp</a>. Estamos prontos para ajudá-lo(a) a resolver essa questão o quanto antes.</p>
                </div>
                <div class="email-footer">
                    <p>E-mail automático. Não responda.</p>
                    <p>Braziliana - Todos os direitos reservados</p>
                </div>
            </div>
            </body>
        </html>
        ';

        wp_mail($to, $subject, $message, $headers);
    } else {
        error_log('Nenhum usuário encontrado para a suíte: ' . $numero_suite);
    }
}
