-- Migration: add layout_logo column to configuracoes_sistema for single-row schema compatibility
-- This migration is safe to run only if configuracoes_sistema exists and does NOT already have the layout_logo column.

SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'configuracoes_sistema'
);

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'configuracoes_sistema'
      AND column_name = 'layout_logo'
);

SET @sql := IF(
    @table_exists > 0 AND @col_exists = 0,
    'ALTER TABLE configuracoes_sistema ADD COLUMN layout_logo LONGTEXT NULL',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
