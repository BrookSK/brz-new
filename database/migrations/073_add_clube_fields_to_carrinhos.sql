-- Adiciona campos de transparência do Clube Brasiliana na tabela carrinhos

SET @col_peso_clube_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'carrinhos' AND COLUMN_NAME = 'peso_clube_total'
);
SET @sql_add_peso_clube := IF(
    @col_peso_clube_exists = 0,
    'ALTER TABLE carrinhos ADD COLUMN peso_clube_total DECIMAL(10,3) NOT NULL DEFAULT 0.000',
    'SELECT 1'
);
PREPARE stmt_add_peso_clube FROM @sql_add_peso_clube; EXECUTE stmt_add_peso_clube; DEALLOCATE PREPARE stmt_add_peso_clube;

SET @col_subtotal_clube_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'carrinhos' AND COLUMN_NAME = 'subtotal_clube'
);
SET @sql_add_subtotal_clube := IF(
    @col_subtotal_clube_exists = 0,
    'ALTER TABLE carrinhos ADD COLUMN subtotal_clube DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt_add_subtotal_clube FROM @sql_add_subtotal_clube; EXECUTE stmt_add_subtotal_clube; DEALLOCATE PREPARE stmt_add_subtotal_clube;

SET @col_desconto_clube_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'carrinhos' AND COLUMN_NAME = 'desconto_clube'
);
SET @sql_add_desconto_clube := IF(
    @col_desconto_clube_exists = 0,
    'ALTER TABLE carrinhos ADD COLUMN desconto_clube DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt_add_desconto_clube FROM @sql_add_desconto_clube; EXECUTE stmt_add_desconto_clube; DEALLOCATE PREPARE stmt_add_desconto_clube;

SET @col_cashback_clube_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'carrinhos' AND COLUMN_NAME = 'cashback_clube_estimado'
);
SET @sql_add_cashback_clube := IF(
    @col_cashback_clube_exists = 0,
    'ALTER TABLE carrinhos ADD COLUMN cashback_clube_estimado DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt_add_cashback_clube FROM @sql_add_cashback_clube; EXECUTE stmt_add_cashback_clube; DEALLOCATE PREPARE stmt_add_cashback_clube;

SET @idx_carrinhos_clube_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'carrinhos' AND INDEX_NAME = 'idx_carrinhos_clube'
);
SET @sql_add_idx_clube := IF(
    @idx_carrinhos_clube_exists = 0,
    'CREATE INDEX idx_carrinhos_clube ON carrinhos (peso_clube_total, subtotal_clube)',
    'SELECT 1'
);
PREPARE stmt_add_idx_clube FROM @sql_add_idx_clube; EXECUTE stmt_add_idx_clube; DEALLOCATE PREPARE stmt_add_idx_clube;
