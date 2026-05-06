-- Adicionar coluna peso_manual na tabela de itens do pedido
-- Quando o cliente altera o peso manualmente na assessoria, esse valor é salvo aqui

SET @db := DATABASE();

-- Tentar em pedido_itens
SET @has_col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_itens' AND COLUMN_NAME = 'peso_manual'
);
SET @sql := IF(@has_col = 0,
    'ALTER TABLE pedido_itens ADD COLUMN peso_manual DECIMAL(10,3) NULL DEFAULT NULL AFTER subtotal',
    'SELECT "Column peso_manual already exists in pedido_itens" AS info'
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
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_items' AND COLUMN_NAME = 'peso_manual'
);
SET @sql2 := IF(@has_table2 > 0 AND @has_col2 = 0,
    'ALTER TABLE pedido_items ADD COLUMN peso_manual DECIMAL(10,3) NULL DEFAULT NULL AFTER subtotal',
    'SELECT "Skipped pedido_items" AS info'
);
PREPARE stmt FROM @sql2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
