-- Migration: change produtos.loja from ENUM to VARCHAR to support dynamic store slugs

SET @db := DATABASE();
SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'produtos'
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'produtos' AND COLUMN_NAME = 'loja'
);

SET @is_enum := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'produtos' AND COLUMN_NAME = 'loja'
    AND DATA_TYPE = 'enum'
);

SET @sql := NULL;

SET @sql := IF(@table_exists = 1 AND @col_exists = 1 AND @is_enum = 1,
  'ALTER TABLE produtos MODIFY COLUMN loja VARCHAR(100) NULL DEFAULT ''outro''',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Normalize invalid enum writes (stored as empty string) to NULL
SET @sql2 := IF(@table_exists = 1 AND @col_exists = 1,
  'UPDATE produtos SET loja = NULL WHERE loja = ''''',
  'SELECT 1'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
