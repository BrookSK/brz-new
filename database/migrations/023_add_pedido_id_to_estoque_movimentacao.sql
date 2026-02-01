-- Migration 023: Relacionar movimentacao de estoque ao pedido
-- Rode este SQL no banco do servidor.

SET @db := DATABASE();

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_movimentacao'
      AND COLUMN_NAME = 'pedido_id'
);

SET @sql := IF(
    @exists = 0,
    'ALTER TABLE estoque_movimentacao ADD COLUMN pedido_id INT NULL AFTER produto_id',
    'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_movimentacao'
      AND INDEX_NAME = 'idx_estoque_mov_pedido'
);

SET @sql := IF(
    @exists = 0,
    'CREATE INDEX idx_estoque_mov_pedido ON estoque_movimentacao (pedido_id)',
    'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
