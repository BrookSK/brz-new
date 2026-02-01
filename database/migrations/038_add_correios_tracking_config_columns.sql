-- Migration: add Correios Tracking API config columns to configuracoes_sistema

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema'
);

-- entrega_correios_tracking_enabled
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'entrega_correios_tracking_enabled'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN entrega_correios_tracking_enabled TINYINT(1) NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- entrega_correios_tracking_base_url
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'entrega_correios_tracking_base_url'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN entrega_correios_tracking_base_url VARCHAR(255) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- entrega_correios_tracking_token
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'entrega_correios_tracking_token'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN entrega_correios_tracking_token VARCHAR(255) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- entrega_correios_tracking_header
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'entrega_correios_tracking_header'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN entrega_correios_tracking_header VARCHAR(80) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
