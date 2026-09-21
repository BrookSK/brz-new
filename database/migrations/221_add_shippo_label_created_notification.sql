-- Configurações de notificação para o evento de geração de etiqueta via Shippo.
-- Evento: shippo_label_created
-- Disparado quando uma etiqueta Shippo é gerada (individual ou em massa) em AdminShippoController.
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
   SELECT ''notificacoes'', ''email_to_override_shippo_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_override_shippo_label_created'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_shippo_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_shippo_label_created'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_extra_shippo_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_extra_shippo_label_created'')
   UNION ALL
   SELECT ''notificacoes'', ''whatsapp_template_shippo_label_created'', ''Olá {{nome}}! Seu pedido #{{codigo_pedido}} foi despachado. Rastreio: {{tracking_number}}.'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''whatsapp_template_shippo_label_created'')
   UNION ALL
   SELECT ''email_templates'', ''shippo_label_created_assunto'', ''Seu pedido #{{codigo_pedido}} foi despachado - Rastreio {{tracking_number}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''email_templates'' AND chave = ''shippo_label_created_assunto'')
   UNION ALL
   SELECT ''email_templates'', ''shippo_label_created_html'', ''Olá {{nome}},<br><br>Boas notícias! A etiqueta de envio do seu pedido <strong>#{{codigo_pedido}}</strong> foi gerada e ele já está a caminho.<br><br><strong>Transportadora:</strong> {{carrier}}<br><strong>Código de rastreio:</strong> {{tracking_number}}<br><br>Acompanhe a entrega pelo link:<br><a href="{{tracking_url}}">Acompanhar rastreio</a><br><br>Você também pode ver o status atualizado na sua conta, em Meus Pedidos.<br><br>Atenciosamente,<br>Braziliana'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''email_templates'' AND chave = ''shippo_label_created_html'')',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Inserção para schema chave/valor (sem categoria)
SET @sql := IF(
  @can_kv = 1 AND @can_cat = 0,
  'INSERT INTO configuracoes_sistema (chave, valor)
   SELECT ''notificacoes_email_to_override_shippo_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_override_shippo_label_created'')
   UNION ALL
   SELECT ''notificacoes_email_to_shippo_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_shippo_label_created'')
   UNION ALL
   SELECT ''notificacoes_email_to_extra_shippo_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_extra_shippo_label_created'')
   UNION ALL
   SELECT ''notificacoes_whatsapp_template_shippo_label_created'', ''Olá {{nome}}! Seu pedido #{{codigo_pedido}} foi despachado. Rastreio: {{tracking_number}}.'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_whatsapp_template_shippo_label_created'')
   UNION ALL
   SELECT ''email_templates_shippo_label_created_assunto'', ''Seu pedido #{{codigo_pedido}} foi despachado - Rastreio {{tracking_number}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''email_templates_shippo_label_created_assunto'')
   UNION ALL
   SELECT ''email_templates_shippo_label_created_html'', ''Olá {{nome}},<br><br>Boas notícias! A etiqueta de envio do seu pedido <strong>#{{codigo_pedido}}</strong> foi gerada e ele já está a caminho.<br><br><strong>Transportadora:</strong> {{carrier}}<br><strong>Código de rastreio:</strong> {{tracking_number}}<br><br>Acompanhe a entrega pelo link:<br><a href="{{tracking_url}}">Acompanhar rastreio</a><br><br>Você também pode ver o status atualizado na sua conta, em Meus Pedidos.<br><br>Atenciosamente,<br>Braziliana'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''email_templates_shippo_label_created_html'')',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Registrar o evento em eventos_sistema para permitir configurar um webhook de WhatsApp. Idempotente.
INSERT INTO eventos_sistema (nome, descricao, ativo, created_at)
SELECT 'shippo_label_created', 'Etiqueta Shippo gerada (notifica cliente com rastreio)', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'shippo_label_created');
