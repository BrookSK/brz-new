-- Adicionar coluna is_brinde na tabela de itens do pedido
-- Marca itens que são brindes vinculados (preço $0, impostos devolvidos na carteira)

SET @db := DATABASE();

-- Tentar em pedido_itens
SET @has_col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_itens' AND COLUMN_NAME = 'is_brinde'
);
SET @sql := IF(@has_col = 0,
    'ALTER TABLE pedido_itens ADD COLUMN is_brinde TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "Column is_brinde already exists in pedido_itens" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tentar em pedido_items (nome alternativo)
SET @has_table2 := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_items'
);
SET @has_col2 := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_items' AND COLUMN_NAME = 'is_brinde'
);
SET @sql2 := IF(@has_table2 > 0 AND @has_col2 = 0,
    'ALTER TABLE pedido_items ADD COLUMN is_brinde TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "Skipped pedido_items is_brinde" AS info'
);
PREPARE stmt FROM @sql2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
