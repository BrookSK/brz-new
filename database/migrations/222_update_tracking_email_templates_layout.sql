-- Atualiza o LAYOUT (corpo_html e assunto) dos templates de e-mail de rastreio para um
-- visual padronizado da Braziliana (cabeçalho escuro, corpo branco, botão de rastreio, rodapé).
-- Cobre os eventos: correios_packet_label_created, correios_packet_shipment_departed, shippo_label_created.
-- Idempotente: usa UPDATE (só altera se a linha existir) e cobre os schemas categoria+chave,
-- chave/valor e a tabela email_templates.
--
-- Placeholders usados: {{nome}} {{codigo_pedido}} {{tracking_number}} {{tracking_url}} {{customer_control_code}}

-- ==========================================================================
-- Bloco 1: tabela dedicada email_templates (nome, assunto, corpo_html)
-- ==========================================================================
SET @has_email_templates := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'email_templates'
);

SET @sql := IF(@has_email_templates > 0,
  'UPDATE email_templates SET
     assunto = ''Etiqueta gerada - Pedido #{{codigo_pedido}}'',
     corpo_html = CONCAT(
       ''<div style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">'',
       ''<div style="max-width:600px;margin:0 auto;padding:24px 12px;">'',
       ''<div style="background:#0b1f3a;border-radius:10px 10px 0 0;padding:22px 28px;text-align:center;">'',
       ''<span style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:.5px;">BRAZILIANA</span>'',
       ''</div>'',
       ''<div style="background:#ffffff;padding:28px;border:1px solid #e6e8eb;border-top:0;">'',
       ''<p style="margin:0 0 16px;color:#0b1f3a;font-size:16px;">Olá <strong>{{nome}}</strong>,</p>'',
       ''<p style="margin:0 0 16px;color:#444;font-size:14px;line-height:1.6;">A etiqueta de envio do seu pedido <strong>#{{codigo_pedido}}</strong> foi gerada com sucesso. Em breve ele estará a caminho!</p>'',
       ''<div style="background:#f4f7ff;border:1px solid #dbe4ff;border-radius:8px;padding:16px 20px;margin:20px 0;">'',
       ''<div style="color:#8a94a6;font-size:12px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Código de rastreio</div>'',
       ''<div style="color:#0b1f3a;font-size:20px;font-weight:bold;letter-spacing:1px;">{{tracking_number}}</div>'',
       ''</div>'',
       ''<div style="text-align:center;margin:26px 0 10px;">'',
       ''<a href="https://brazilianashop.com.br/rastreamento?codigo={{tracking_number}}" style="display:inline-block;background:#0b1f3a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:13px 30px;border-radius:6px;">Acompanhar rastreio</a>'',
       ''</div>'',
       ''<p style="margin:18px 0 0;color:#8a94a6;font-size:13px;line-height:1.6;">Você também pode acompanhar o status na sua conta, em <strong>Meus Pedidos</strong>.</p>'',
       ''</div>'',
       ''<div style="background:#f4f5f7;padding:18px;text-align:center;color:#9aa2ad;font-size:12px;border:1px solid #e6e8eb;border-top:0;border-radius:0 0 10px 10px;">'',
       ''Braziliana • Este é um e-mail automático, não responda.'',
       ''</div>'',
       ''</div></div>''
     )
   WHERE nome = ''correios_packet_label_created''',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Embarque (shipment departed)
SET @sql := IF(@has_email_templates > 0,
  'UPDATE email_templates SET
     assunto = ''Seu pedido #{{codigo_pedido}} foi embarcado - Rastreio {{tracking_number}}'',
     corpo_html = CONCAT(
       ''<div style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">'',
       ''<div style="max-width:600px;margin:0 auto;padding:24px 12px;">'',
       ''<div style="background:#0b1f3a;border-radius:10px 10px 0 0;padding:22px 28px;text-align:center;">'',
       ''<span style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:.5px;">BRAZILIANA</span>'',
       ''</div>'',
       ''<div style="background:#ffffff;padding:28px;border:1px solid #e6e8eb;border-top:0;">'',
       ''<p style="margin:0 0 16px;color:#0b1f3a;font-size:16px;">Olá <strong>{{nome}}</strong>,</p>'',
       ''<p style="margin:0 0 16px;color:#444;font-size:14px;line-height:1.6;">Boas notícias! Seu pedido <strong>#{{codigo_pedido}}</strong> foi embarcado e já está a caminho do destino.</p>'',
       ''<div style="background:#f4f7ff;border:1px solid #dbe4ff;border-radius:8px;padding:16px 20px;margin:20px 0;">'',
       ''<div style="color:#8a94a6;font-size:12px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Código de rastreio</div>'',
       ''<div style="color:#0b1f3a;font-size:20px;font-weight:bold;letter-spacing:1px;">{{tracking_number}}</div>'',
       ''</div>'',
       ''<div style="text-align:center;margin:26px 0 10px;">'',
       ''<a href="https://brazilianashop.com.br/rastreamento?codigo={{tracking_number}}" style="display:inline-block;background:#0b1f3a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:13px 30px;border-radius:6px;">Acompanhar rastreio</a>'',
       ''</div>'',
       ''<p style="margin:18px 0 0;color:#8a94a6;font-size:13px;line-height:1.6;">Você também pode acompanhar o status na sua conta, em <strong>Meus Pedidos</strong>.</p>'',
       ''</div>'',
       ''<div style="background:#f4f5f7;padding:18px;text-align:center;color:#9aa2ad;font-size:12px;border:1px solid #e6e8eb;border-top:0;border-radius:0 0 10px 10px;">'',
       ''Braziliana • Este é um e-mail automático, não responda.'',
       ''</div>'',
       ''</div></div>''
     )
   WHERE nome = ''correios_packet_shipment_departed''',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Shippo
SET @sql := IF(@has_email_templates > 0,
  'UPDATE email_templates SET
     assunto = ''Seu pedido #{{codigo_pedido}} foi despachado - Rastreio {{tracking_number}}'',
     corpo_html = CONCAT(
       ''<div style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">'',
       ''<div style="max-width:600px;margin:0 auto;padding:24px 12px;">'',
       ''<div style="background:#0b1f3a;border-radius:10px 10px 0 0;padding:22px 28px;text-align:center;">'',
       ''<span style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:.5px;">BRAZILIANA</span>'',
       ''</div>'',
       ''<div style="background:#ffffff;padding:28px;border:1px solid #e6e8eb;border-top:0;">'',
       ''<p style="margin:0 0 16px;color:#0b1f3a;font-size:16px;">Olá <strong>{{nome}}</strong>,</p>'',
       ''<p style="margin:0 0 16px;color:#444;font-size:14px;line-height:1.6;">A etiqueta de envio do seu pedido <strong>#{{codigo_pedido}}</strong> foi gerada e ele já está a caminho.</p>'',
       ''<div style="background:#f4f7ff;border:1px solid #dbe4ff;border-radius:8px;padding:16px 20px;margin:20px 0;">'',
       ''<div style="color:#8a94a6;font-size:12px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Código de rastreio</div>'',
       ''<div style="color:#0b1f3a;font-size:20px;font-weight:bold;letter-spacing:1px;">{{tracking_number}}</div>'',
       ''</div>'',
       ''<div style="text-align:center;margin:26px 0 10px;">'',
       ''<a href="{{tracking_url}}" style="display:inline-block;background:#0b1f3a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:13px 30px;border-radius:6px;">Acompanhar rastreio</a>'',
       ''</div>'',
       ''<p style="margin:18px 0 0;color:#8a94a6;font-size:13px;line-height:1.6;">Você também pode acompanhar o status na sua conta, em <strong>Meus Pedidos</strong>.</p>'',
       ''</div>'',
       ''<div style="background:#f4f5f7;padding:18px;text-align:center;color:#9aa2ad;font-size:12px;border:1px solid #e6e8eb;border-top:0;border-radius:0 0 10px 10px;">'',
       ''Braziliana • Este é um e-mail automático, não responda.'',
       ''</div>'',
       ''</div></div>''
     )
   WHERE nome = ''shippo_label_created''',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
