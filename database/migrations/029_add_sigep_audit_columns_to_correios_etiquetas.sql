-- Migration: add SIGEP audit columns to correios_etiquetas

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas'
);

-- sigep_enabled_used
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'sigep_enabled_used'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN sigep_enabled_used TINYINT(1) NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigep_ambiente
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'sigep_ambiente'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN sigep_ambiente VARCHAR(20) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigep_etiqueta_sem_dv
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'sigep_etiqueta_sem_dv'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN sigep_etiqueta_sem_dv VARCHAR(100) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigep_dv
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'sigep_dv'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN sigep_dv VARCHAR(10) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigep_last_request_json
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'sigep_last_request_json'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN sigep_last_request_json LONGTEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigep_last_response_json
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'sigep_last_response_json'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN sigep_last_response_json LONGTEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigep_error
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'correios_etiquetas' AND COLUMN_NAME = 'sigep_error'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE correios_etiquetas ADD COLUMN sigep_error TEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
