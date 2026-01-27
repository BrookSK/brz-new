-- Migration: add produtos.foto_principal (cover image) column
-- Safe/idempotent: only adds the column if it does not exist

SET @has_foto_principal := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'foto_principal'
);

SET @sql_add_foto_principal := IF(
    @has_foto_principal = 0,
    "ALTER TABLE produtos ADD COLUMN foto_principal VARCHAR(255) NULL;",
    "SELECT 1;"
);

PREPARE stmt_add_foto_principal FROM @sql_add_foto_principal;
EXECUTE stmt_add_foto_principal;
DEALLOCATE PREPARE stmt_add_foto_principal;
