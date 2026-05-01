-- Migration 141: Corrige coluna 'categoria' em estoque_interno para aceitar NULL por padrão.
-- Erro original: SQLSTATE[HY000]: General error: 1364 Field 'categoria' doesn't have a default value
-- A coluna foi adicionada sem DEFAULT NULL, causando falha nos INSERTs que não informam categoria.

SET @db = DATABASE();

-- Verifica se a coluna existe e, se sim, altera para NULL DEFAULT
SET @exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'categoria'
);

SET @sql := IF(
    @exists > 0,
    'ALTER TABLE estoque_interno MODIFY COLUMN categoria VARCHAR(100) NULL DEFAULT NULL',
    'SELECT 1 -- coluna categoria nao existe, nada a fazer'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
