-- Migration: add `switch` sequential identifier to usuarios

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios'
);

-- Add column `switch`
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'switch'
);

SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE usuarios ADD COLUMN `switch` INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add unique index for `switch`
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'uk_usuarios_switch'
);

SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE usuarios ADD UNIQUE KEY uk_usuarios_switch (`switch`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill for existing users (default: switch = id)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'switch'
);

SET @sql := IF(@table_exists = 1 AND @col_exists = 1,
  'UPDATE usuarios SET `switch` = id WHERE (`switch` IS NULL OR `switch` = 0)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
