-- Adiciona colunas de snapshot em carrinho_items para suporte/auditoria (não exibido ao cliente)
-- Não altera migrations existentes

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'carrinho_items'
);

-- preco_unit_snapshot
SET @col_price := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'carrinho_items'
      AND COLUMN_NAME = 'preco_unit_snapshot'
);
SET @sql_add_price := IF(
    @tbl_exists > 0 AND @col_price = 0,
    'ALTER TABLE carrinho_items ADD COLUMN preco_unit_snapshot DECIMAL(12,2) NULL AFTER subtotal',
    'SELECT 1'
);
PREPARE stmt_add_price FROM @sql_add_price;
EXECUTE stmt_add_price;
DEALLOCATE PREPARE stmt_add_price;

-- peso_unit_snapshot
SET @col_weight := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'carrinho_items'
      AND COLUMN_NAME = 'peso_unit_snapshot'
);
SET @sql_add_weight := IF(
    @tbl_exists > 0 AND @col_weight = 0,
    'ALTER TABLE carrinho_items ADD COLUMN peso_unit_snapshot DECIMAL(10,3) NULL AFTER preco_unit_snapshot',
    'SELECT 1'
);
PREPARE stmt_add_weight FROM @sql_add_weight;
EXECUTE stmt_add_weight;
DEALLOCATE PREPARE stmt_add_weight;
