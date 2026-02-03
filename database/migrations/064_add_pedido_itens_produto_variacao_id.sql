-- Migration: adicionar produto_variacao_id em pedido_itens/pedido_items para registrar variações nos itens do pedido
-- Observação: rode manualmente no banco. Não altera migrations antigas.

SET @db_name := DATABASE();

-- pedido_itens
SET @pedido_itens_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedido_itens'
);

SET @col_pedido_itens_pvi_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedido_itens'
      AND COLUMN_NAME = 'produto_variacao_id'
);

SET @sql_add_pedido_itens_pvi := IF(
    @pedido_itens_exists > 0 AND @col_pedido_itens_pvi_exists = 0,
    'ALTER TABLE pedido_itens ADD COLUMN produto_variacao_id INT NULL',
    'SELECT 1'
);

PREPARE stmt_add_pedido_itens_pvi FROM @sql_add_pedido_itens_pvi;
EXECUTE stmt_add_pedido_itens_pvi;
DEALLOCATE PREPARE stmt_add_pedido_itens_pvi;

SET @idx_pedido_itens_pvi_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedido_itens'
      AND INDEX_NAME = 'idx_pedido_itens_produto_variacao_id'
);

SET @sql_add_idx_pedido_itens_pvi := IF(
    @pedido_itens_exists > 0 AND @idx_pedido_itens_pvi_exists = 0,
    'CREATE INDEX idx_pedido_itens_produto_variacao_id ON pedido_itens (produto_variacao_id)',
    'SELECT 1'
);

PREPARE stmt_add_idx_pedido_itens_pvi FROM @sql_add_idx_pedido_itens_pvi;
EXECUTE stmt_add_idx_pedido_itens_pvi;
DEALLOCATE PREPARE stmt_add_idx_pedido_itens_pvi;

-- pedido_items (algumas instalações usam essa tabela)
SET @pedido_items_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedido_items'
);

SET @col_pedido_items_pvi_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedido_items'
      AND COLUMN_NAME = 'produto_variacao_id'
);

SET @sql_add_pedido_items_pvi := IF(
    @pedido_items_exists > 0 AND @col_pedido_items_pvi_exists = 0,
    'ALTER TABLE pedido_items ADD COLUMN produto_variacao_id INT NULL',
    'SELECT 1'
);

PREPARE stmt_add_pedido_items_pvi FROM @sql_add_pedido_items_pvi;
EXECUTE stmt_add_pedido_items_pvi;
DEALLOCATE PREPARE stmt_add_pedido_items_pvi;

SET @idx_pedido_items_pvi_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedido_items'
      AND INDEX_NAME = 'idx_pedido_items_produto_variacao_id'
);

SET @sql_add_idx_pedido_items_pvi := IF(
    @pedido_items_exists > 0 AND @idx_pedido_items_pvi_exists = 0,
    'CREATE INDEX idx_pedido_items_produto_variacao_id ON pedido_items (produto_variacao_id)',
    'SELECT 1'
);

PREPARE stmt_add_idx_pedido_items_pvi FROM @sql_add_idx_pedido_items_pvi;
EXECUTE stmt_add_idx_pedido_items_pvi;
DEALLOCATE PREPARE stmt_add_idx_pedido_items_pvi;
