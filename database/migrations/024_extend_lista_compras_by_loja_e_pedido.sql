-- Migration 024: Lista de compras por loja e vinculada a pedido
-- Rode este SQL no banco do servidor.

SET @db := DATABASE();

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'lista_compras'
      AND COLUMN_NAME = 'loja'
);

SET @sql := IF(
    @exists = 0,
    'ALTER TABLE lista_compras ADD COLUMN loja VARCHAR(120) NULL AFTER produto_id',
    'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'lista_compras'
      AND COLUMN_NAME = 'pedido_id'
);

SET @sql := IF(
    @exists = 0,
    'ALTER TABLE lista_compras ADD COLUMN pedido_id INT NULL AFTER loja',
    'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'lista_compras'
      AND INDEX_NAME = 'idx_lista_compras_loja'
);

SET @sql := IF(
    @exists = 0,
    'CREATE INDEX idx_lista_compras_loja ON lista_compras (loja)',
    'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'lista_compras'
      AND INDEX_NAME = 'idx_lista_compras_pedido'
);

SET @sql := IF(
    @exists = 0,
    'CREATE INDEX idx_lista_compras_pedido ON lista_compras (pedido_id)',
    'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
