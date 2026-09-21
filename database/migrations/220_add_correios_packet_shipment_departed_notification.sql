-- Configurações de notificação para o evento de EMBARQUE do Correios Internacional (PACKET).
-- Evento: correios_packet_shipment_departed
-- Disparado quando um embarque (departure) é confirmado em /admin/etiquetas-wp/criar-embarque.
-- Notifica o cliente por e-mail e WhatsApp com o código de rastreio.
-- Idempotente e tolerante ao schema (categoria+chave OU chave/valor).

CREATE TABLE IF NOT EXISTS configuracoes_sistema (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(255) NOT NULL UNIQUE,
  valor TEXT NULL,
  descricao TEXT NULL,
  tipo ENUM('string','number','boolean','json') DEFAULT 'string',
  atualizado_por INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'configuracoes_sistema'
);

SET @has_categoria := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'configuracoes_sistema'
      AND column_name = 'categoria'
);

SET @has_chave := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'configuracoes_sistema'
      AND column_name = 'chave'
);

SET @has_valor := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'configuracoes_sistema'
      AND column_name = 'valor'
);

SET @can_kv := IF(@table_exists > 0 AND @has_chave > 0 AND @has_valor > 0, 1, 0);
SET @can_cat := IF(@can_kv = 1 AND @has_categoria > 0, 1, 0);

-- Inserção para schema categoria+chave
SET @sql := IF(
  @can_cat = 1,
  'INSERT INTO configuracoes_sistema (categoria, chave, valor)
   SELECT ''notificacoes'', ''email_to_override_correios_packet_shipment_departed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_override_correios_packet_shipment_departed'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_correios_packet_shipment_departed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_correios_packet_shipment_departed'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_extra_correios_packet_shipment_departed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_extra_correios_packet_shipment_departed'')
   UNION ALL
   SELECT ''notificacoes'', ''whatsapp_template_correios_packet_shipment_departed'', ''Olá {{nome}}! Seu pedido #{{codigo_pedido}} foi embarcado e está a caminho. Rastreio: {{tracking_number}}. Acompanhe em https://brazilianashop.com.br/rastreamento?codigo={{tracking_number}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''whatsapp_template_correios_packet_shipment_departed'')
   UNION ALL
   SELECT ''email_templates'', ''correios_packet_shipment_departed_assunto'', ''Seu pedido #{{codigo_pedido}} foi embarcado - Rastreio {{tracking_number}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''email_templates'' AND chave = ''correios_packet_shipment_departed_assunto'')
   UNION ALL
   SELECT ''email_templates'', ''correios_packet_shipment_departed_html'', ''Olá {{nome}},<br><br>Boas notícias! Seu pedido <strong>#{{codigo_pedido}}</strong> foi embarcado e já está a caminho do destino.<br><br><strong>Código de rastreio:</strong> {{tracking_number}}<br><br>Você pode acompanhar a entrega pelo link:<br><a href="https://brazilianashop.com.br/rastreamento?codigo={{tracking_number}}">Acompanhar rastreio</a><br><br>Você também pode ver o status atualizado na sua conta, em Meus Pedidos.<br><br>Atenciosamente,<br>Braziliana'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''email_templates'' AND chave = ''correios_packet_shipment_departed_html'')',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Inserção para schema chave/valor (sem categoria)
SET @sql := IF(
  @can_kv = 1 AND @can_cat = 0,
  'INSERT INTO configuracoes_sistema (chave, valor)
   SELECT ''notificacoes_email_to_override_correios_packet_shipment_departed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_override_correios_packet_shipment_departed'')
   UNION ALL
   SELECT ''notificacoes_email_to_correios_packet_shipment_departed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_correios_packet_shipment_departed'')
   UNION ALL
   SELECT ''notificacoes_email_to_extra_correios_packet_shipment_departed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_extra_correios_packet_shipment_departed'')
   UNION ALL
   SELECT ''notificacoes_whatsapp_template_correios_packet_shipment_departed'', ''Olá {{nome}}! Seu pedido #{{codigo_pedido}} foi embarcado e está a caminho. Rastreio: {{tracking_number}}. Acompanhe em https://brazilianashop.com.br/rastreamento?codigo={{tracking_number}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_whatsapp_template_correios_packet_shipment_departed'')
   UNION ALL
   SELECT ''email_templates_correios_packet_shipment_departed_assunto'', ''Seu pedido #{{codigo_pedido}} foi embarcado - Rastreio {{tracking_number}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''email_templates_correios_packet_shipment_departed_assunto'')
   UNION ALL
   SELECT ''email_templates_correios_packet_shipment_departed_html'', ''Olá {{nome}},<br><br>Boas notícias! Seu pedido <strong>#{{codigo_pedido}}</strong> foi embarcado e já está a caminho do destino.<br><br><strong>Código de rastreio:</strong> {{tracking_number}}<br><br>Você pode acompanhar a entrega pelo link:<br><a href="https://brazilianashop.com.br/rastreamento?codigo={{tracking_number}}">Acompanhar rastreio</a><br><br>Você também pode ver o status atualizado na sua conta, em Meus Pedidos.<br><br>Atenciosamente,<br>Braziliana'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''email_templates_correios_packet_shipment_departed_html'')',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Registrar o evento em eventos_sistema para permitir configurar um webhook de WhatsApp
-- (a tabela webhooks liga-se a eventos_sistema.id). Idempotente.
INSERT INTO eventos_sistema (nome, descricao, ativo, created_at)
SELECT 'correios_packet_shipment_departed', 'Embarque do Correios Internacional confirmado (notifica cliente com rastreio)', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'correios_packet_shipment_departed');
