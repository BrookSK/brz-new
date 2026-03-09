-- Token específico para Correios (API Busca CEP)

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema'
);

-- entrega_correios_cep_token
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'entrega_correios_cep_token'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN entrega_correios_cep_token TEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
