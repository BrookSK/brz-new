-- Adiciona coluna webhook_link_pagamento_pedido_manual_url em configuracoes_sistema (schema single-row) caso não exista

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema'
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'webhook_link_pagamento_pedido_manual_url'
);

SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN webhook_link_pagamento_pedido_manual_url LONGTEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
