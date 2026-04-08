-- Adiciona campo 'oculto' aos produtos
-- Quando oculto=1, o produto não aparece para clientes (orgânico, grupo de compras, etc.)
-- Só aparece para admin/vendedor no pedido manual.

SET @col_oculto_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'oculto'
);

SET @sql_add_oculto := IF(
    @col_oculto_exists = 0,
    'ALTER TABLE produtos ADD COLUMN oculto TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);

PREPARE stmt_add_oculto FROM @sql_add_oculto;
EXECUTE stmt_add_oculto;
DEALLOCATE PREPARE stmt_add_oculto;

SET @idx_oculto_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND INDEX_NAME = 'idx_produtos_oculto'
);

SET @sql_add_idx_oculto := IF(
    @idx_oculto_exists = 0,
    'CREATE INDEX idx_produtos_oculto ON produtos (oculto)',
    'SELECT 1'
);

PREPARE stmt_add_idx_oculto FROM @sql_add_idx_oculto;
EXECUTE stmt_add_idx_oculto;
DEALLOCATE PREPARE stmt_add_idx_oculto;
