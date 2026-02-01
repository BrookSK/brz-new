-- Migration: rename `switch` to `suite` (keep compatibility)

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios'
);

-- Add column `suite`
SET @col_suite_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'suite'
);

SET @sql := IF(@table_exists = 1 AND @col_suite_exists = 0,
  'ALTER TABLE usuarios ADD COLUMN `suite` INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add unique index for `suite`
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'uk_usuarios_suite'
);

SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE usuarios ADD UNIQUE KEY uk_usuarios_suite (`suite`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill suite
-- Prefer copying from `switch` when it exists, otherwise use id
SET @col_switch_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'switch'
);

SET @sql := IF(@table_exists = 1 AND @col_suite_exists = 1 AND @col_switch_exists = 1,
  'UPDATE usuarios SET `suite` = `switch` WHERE (`suite` IS NULL OR `suite` = 0) AND (`switch` IS NOT NULL AND `switch` <> 0)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@table_exists = 1 AND @col_suite_exists = 1,
  'UPDATE usuarios SET `suite` = id WHERE (`suite` IS NULL OR `suite` = 0)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
