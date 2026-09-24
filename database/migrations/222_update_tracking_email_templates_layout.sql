-- Atualiza o LAYOUT (corpo_html e assunto) dos templates de e-mail de rastreio para um
-- visual padronizado da Braziliana (cabeçalho escuro, corpo branco, botão de rastreio, rodapé).
-- Cobre os eventos: correios_packet_label_created, correios_packet_shipment_departed, shippo_label_created.
-- Idempotente: usa UPDATE (só altera se a linha existir) na tabela email_templates.
--
-- Placeholders usados: {{codigo_pedido}} {{tracking_number}} {{tracking_url}}

-- ==========================================================================
-- Bloco 1: tabela dedicada email_templates (nome, assunto, corpo_html)
-- ==========================================================================
SET @has_email_templates := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'email_templates'
);

-- Corpo HTML compartilhado (texto oficial da Braziliana sobre rastreio/impostos).
-- {URL_BTN} é substituído por CONCAT antes de aplicar; placeholders {{...}} são resolvidos no envio.
SET @corpo_correios := CONCAT(
  '<div style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">',
  '<div style="max-width:600px;margin:0 auto;padding:24px 12px;">',
  '<div style="background:#0b1f3a;border-radius:10px 10px 0 0;padding:22px 28px;text-align:center;">',
  '<span style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:.5px;">BRAZILIANA</span>',
  '</div>',
  '<div style="background:#ffffff;padding:28px;border:1px solid #e6e8eb;border-top:0;">',
  '<p style="margin:0 0 16px;color:#0b1f3a;font-size:16px;">Hello! Tudo bem?</p>',
  '<p style="margin:0 0 16px;color:#444;font-size:14px;line-height:1.6;">Boas notícias, sua caixa foi enviada! Você tem acesso ao número de rastreio diretamente no seu pedido, na sua conta do site.</p>',
  '<p style="margin:0 0 8px;color:#0b1f3a;font-size:15px;font-weight:bold;">Importante sobre o pagamento dos impostos:</p>',
  '<ul style="margin:0 0 16px;padding-left:20px;color:#444;font-size:14px;line-height:1.6;">',
  '<li style="margin-bottom:10px;"><strong>Se você comprou por conta própria e mandou entregar na nossa sede:</strong> é de sua responsabilidade acompanhar o trajeto do seu pacote e realizar o pagamento dos impostos no seu portal dos Correios (www.correios.com.br) em &ldquo;MINHAS IMPORTAÇÕES&rdquo;.</li>',
  '<li><strong>Se você utilizou nossos serviços de compra e pagou os impostos antecipadamente:</strong> a Braziliana acompanha o processo completo e somente entraremos em contato para que você nos envie o boleto dos impostos para que possamos pagar.</li>',
  '</ul>',
  '<div style="background:#f4f7ff;border:1px solid #dbe4ff;border-radius:8px;padding:16px 20px;margin:20px 0;">',
  '<div style="color:#8a94a6;font-size:12px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Seu código de rastreio é</div>',
  '<div style="color:#0b1f3a;font-size:20px;font-weight:bold;letter-spacing:1px;">{{tracking_number}}</div>',
  '</div>',
  '<div style="text-align:center;margin:22px 0;">',
  '<a href="{URL_BTN}" style="display:inline-block;background:#0b1f3a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:13px 30px;border-radius:6px;">Acompanhar rastreio</a>',
  '</div>',
  '<p style="margin:0 0 8px;color:#0b1f3a;font-size:15px;font-weight:bold;">Como acessar o boleto dos impostos nos Correios:</p>',
  '<p style="margin:0 0 10px;color:#444;font-size:14px;line-height:1.6;">Acesse o portal Minhas Importações: <a href="https://portalimportador.correios.com.br" style="color:#0b1f3a;">portalimportador.correios.com.br</a></p>',
  '<ol style="margin:0 0 16px;padding-left:20px;color:#444;font-size:14px;line-height:1.6;">',
  '<li>Faça login com sua conta.</li>',
  '<li>Localize sua encomenda utilizando o código de rastreio.</li>',
  '<li>Quando os impostos estiverem disponíveis, aparecerá uma pendência de pagamento.</li>',
  '<li>Clique sobre a encomenda e acesse &ldquo;Visualizar Detalhes&rdquo; &rarr; &ldquo;Pagamento&rdquo;.</li>',
  '<li>Nessa tela, gere o boleto para pagamento dos impostos e nos envie por WhatsApp.</li>',
  '</ol>',
  '<div style="background:#fff8e1;border:1px solid #ffe08a;border-radius:8px;padding:14px 18px;margin:0 0 18px;color:#6b5300;font-size:13px;line-height:1.6;">',
  '<strong>Fique atento!</strong>',
  '<ul style="margin:8px 0 0;padding-left:18px;">',
  '<li style="margin-bottom:8px;">Os Correios normalmente não enviam notificações sobre a liberação dos impostos. Por isso, recomendamos acompanhar o portal Minhas Importações, onde o prazo de pagamento é de somente 20 dias corridos.</li>',
  '<li>Os Correios não enviam boletos de pagamento por SMS e não entram em contato por telefone para solicitar esse tipo de pagamento.</li>',
  '</ul>',
  '</div>',
  '<p style="margin:0 0 16px;color:#444;font-size:14px;line-height:1.6;">Caso tenha qualquer dificuldade para acessar o sistema, localizar sua encomenda ou identificar a cobrança, nossa equipe estará à disposição para ajudar!</p>',
  '<p style="margin:0 0 4px;color:#444;font-size:14px;line-height:1.6;">Muito obrigada pela confiança no nosso trabalho!</p>',
  '<p style="margin:16px 0 0;color:#0b1f3a;font-size:14px;line-height:1.6;">Love,<br><strong>Fabi</strong></p>',
  '</div>',
  '<div style="background:#f4f5f7;padding:18px;text-align:center;color:#9aa2ad;font-size:12px;border:1px solid #e6e8eb;border-top:0;border-radius:0 0 10px 10px;">',
  'Braziliana • Este é um e-mail automático, não responda.',
  '</div>',
  '</div></div>'
);

-- Variação por botão: Correios (URL de rastreamento do site) e Shippo ({{tracking_url}}).
SET @corpo_btn_correios := REPLACE(@corpo_correios, '{URL_BTN}', 'https://brazilianashop.com.br/rastreamento?codigo={{tracking_number}}');
SET @corpo_btn_shippo := REPLACE(@corpo_correios, '{URL_BTN}', '{{tracking_url}}');

SET @sql := IF(@has_email_templates > 0,
  'UPDATE email_templates SET assunto = ''Sua caixa foi enviada! Pedido #{{codigo_pedido}}'', corpo_html = @corpo_btn_correios WHERE nome = ''correios_packet_label_created''',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Embarque (shipment departed)
SET @sql := IF(@has_email_templates > 0,
  'UPDATE email_templates SET assunto = ''Sua caixa foi enviada! Pedido #{{codigo_pedido}} - Rastreio {{tracking_number}}'', corpo_html = @corpo_btn_correios WHERE nome = ''correios_packet_shipment_departed''',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Shippo
SET @sql := IF(@has_email_templates > 0,
  'UPDATE email_templates SET assunto = ''Sua caixa foi enviada! Pedido #{{codigo_pedido}} - Rastreio {{tracking_number}}'', corpo_html = @corpo_btn_shippo WHERE nome = ''shippo_label_created''',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
