-- Adicionar coluna is_test na tabela shippo_etiquetas
-- Marca etiquetas geradas com token de teste da Shippo (data.test = true).
-- Necessária porque o AdminShippoController grava is_test no INSERT, mas a
-- tabela criada em runtime por versões anteriores não possui a coluna.

SET @db := DATABASE();

-- A tabela shippo_etiquetas é criada em runtime pelo controller; só aplicar
-- o ALTER quando a tabela existir e a coluna is_test ainda não existir.
SET @has_table := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shippo_etiquetas'
);
SET @has_col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shippo_etiquetas' AND COLUMN_NAME = 'is_test'
);
SET @sql := IF(@has_table > 0 AND @has_col = 0,
    'ALTER TABLE shippo_etiquetas ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT "Column is_test already exists or table shippo_etiquetas missing" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
