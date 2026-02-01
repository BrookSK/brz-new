-- Migration: add missing closed_at/updated_at to remessa_janelas for existing installs

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janelas'
);

-- closed_at
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janelas' AND COLUMN_NAME = 'closed_at'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janelas ADD COLUMN closed_at DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- updated_at
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janelas' AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janelas ADD COLUMN updated_at DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
