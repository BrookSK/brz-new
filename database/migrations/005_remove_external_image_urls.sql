-- Migration: remove external image URLs from database fields
-- Purpose: stop relying on external hosts like via.placeholder.com
-- Safe/idempotent: running multiple times yields the same result

-- 1) Products cover image
SET @has_produtos_foto_principal := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'foto_principal'
);

SET @sql_produtos_foto_principal := IF(
    @has_produtos_foto_principal > 0,
    "UPDATE produtos SET foto_principal = NULL WHERE foto_principal LIKE 'http%';",
    "SELECT 1;"
);

PREPARE stmt_produtos_foto_principal FROM @sql_produtos_foto_principal;
EXECUTE stmt_produtos_foto_principal;
DEALLOCATE PREPARE stmt_produtos_foto_principal;

-- 2) Product gallery images (if any external URLs were stored)
-- Prefer clearing the url instead of deleting rows to preserve ordering/history.
SET @has_produto_fotos_nome_arquivo := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produto_fotos'
      AND COLUMN_NAME = 'nome_arquivo'
);

SET @sql_produto_fotos_nome_arquivo := IF(
    @has_produto_fotos_nome_arquivo > 0,
    "UPDATE produto_fotos SET nome_arquivo = NULL WHERE nome_arquivo LIKE 'http%';",
    "SELECT 1;"
);

PREPARE stmt_produto_fotos_nome_arquivo FROM @sql_produto_fotos_nome_arquivo;
EXECUTE stmt_produto_fotos_nome_arquivo;
DEALLOCATE PREPARE stmt_produto_fotos_nome_arquivo;

-- 3) Optional cleanup: if you prefer removing broken rows, uncomment.
-- DELETE FROM produto_fotos WHERE nome_arquivo IS NULL;
