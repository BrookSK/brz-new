-- Migration: add Correios PrePostagem (v3) audit columns to correios_etiquetas

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas'
);

-- correios_provider
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'correios_provider'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN correios_provider VARCHAR(30) NULL DEFAULT \'sigep\'',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- prepostagem_id
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'prepostagem_id'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN prepostagem_id VARCHAR(80) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- prepostagem_last_request_json
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'prepostagem_last_request_json'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN prepostagem_last_request_json LONGTEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- prepostagem_last_response_json
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'prepostagem_last_response_json'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN prepostagem_last_response_json LONGTEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- prepostagem_error
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'prepostagem_error'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN prepostagem_error TEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- prepostagem_recibo_rotulo
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'prepostagem_recibo_rotulo'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN prepostagem_recibo_rotulo VARCHAR(120) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
