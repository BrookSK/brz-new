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
   SELECT ''notificacoes'', ''email_to_override_correios_packet_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_override_correios_packet_label_created'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_correios_packet_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_correios_packet_label_created'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_extra_correios_packet_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_extra_correios_packet_label_created'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_override_correios_packet_label_failed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_override_correios_packet_label_failed'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_correios_packet_label_failed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_correios_packet_label_failed'')
   UNION ALL
   SELECT ''notificacoes'', ''email_to_extra_correios_packet_label_failed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''notificacoes'' AND chave = ''email_to_extra_correios_packet_label_failed'')
   UNION ALL
   SELECT ''email_templates'', ''correios_packet_label_created_assunto'', ''Etiqueta PACKET gerada - Pedido #{{codigo_pedido}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''email_templates'' AND chave = ''correios_packet_label_created_assunto'')
   UNION ALL
   SELECT ''email_templates'', ''correios_packet_label_created_html'', ''Olá {{nome}},<br><br>Sua etiqueta Correios PACKET foi gerada com sucesso para o pedido <strong>#{{codigo_pedido}}</strong>.<br><br><strong>Tracking:</strong> {{tracking_number}}<br><strong>Código de controle:</strong> {{customer_control_code}}<br><br>Atenciosamente,<br>Braziliana'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''email_templates'' AND chave = ''correios_packet_label_created_html'')
   UNION ALL
   SELECT ''email_templates'', ''correios_packet_label_failed_assunto'', ''Falha ao gerar etiqueta PACKET - Pedido #{{codigo_pedido}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''email_templates'' AND chave = ''correios_packet_label_failed_assunto'')
   UNION ALL
   SELECT ''email_templates'', ''correios_packet_label_failed_html'', ''Olá,<br><br>Falha ao gerar etiqueta Correios PACKET automaticamente para o pedido <strong>#{{codigo_pedido}}</strong>.<br><br><strong>Etapa:</strong> {{stage}}<br><strong>Erro:</strong> {{error}}<br><br>Atenciosamente,<br>Braziliana'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE categoria = ''email_templates'' AND chave = ''correios_packet_label_failed_html'')',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Inserção para schema chave/valor (sem categoria)
SET @sql := IF(
  @can_kv = 1 AND @can_cat = 0,
  'INSERT INTO configuracoes_sistema (chave, valor)
   SELECT ''notificacoes_email_to_override_correios_packet_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_override_correios_packet_label_created'')
   UNION ALL
   SELECT ''notificacoes_email_to_correios_packet_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_correios_packet_label_created'')
   UNION ALL
   SELECT ''notificacoes_email_to_extra_correios_packet_label_created'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_extra_correios_packet_label_created'')
   UNION ALL
   SELECT ''notificacoes_email_to_override_correios_packet_label_failed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_override_correios_packet_label_failed'')
   UNION ALL
   SELECT ''notificacoes_email_to_correios_packet_label_failed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_correios_packet_label_failed'')
   UNION ALL
   SELECT ''notificacoes_email_to_extra_correios_packet_label_failed'', '''' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''notificacoes_email_to_extra_correios_packet_label_failed'')
   UNION ALL
   SELECT ''email_templates_correios_packet_label_created_assunto'', ''Etiqueta PACKET gerada - Pedido #{{codigo_pedido}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''email_templates_correios_packet_label_created_assunto'')
   UNION ALL
   SELECT ''email_templates_correios_packet_label_created_html'', ''Olá {{nome}},<br><br>Sua etiqueta Correios PACKET foi gerada com sucesso para o pedido <strong>#{{codigo_pedido}}</strong>.<br><br><strong>Tracking:</strong> {{tracking_number}}<br><strong>Código de controle:</strong> {{customer_control_code}}<br><br>Atenciosamente,<br>Braziliana'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''email_templates_correios_packet_label_created_html'')
   UNION ALL
   SELECT ''email_templates_correios_packet_label_failed_assunto'', ''Falha ao gerar etiqueta PACKET - Pedido #{{codigo_pedido}}'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''email_templates_correios_packet_label_failed_assunto'')
   UNION ALL
   SELECT ''email_templates_correios_packet_label_failed_html'', ''Olá,<br><br>Falha ao gerar etiqueta Correios PACKET automaticamente para o pedido <strong>#{{codigo_pedido}}</strong>.<br><br><strong>Etapa:</strong> {{stage}}<br><strong>Erro:</strong> {{error}}<br><br>Atenciosamente,<br>Braziliana'' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM configuracoes_sistema WHERE chave = ''email_templates_correios_packet_label_failed_html'')',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
