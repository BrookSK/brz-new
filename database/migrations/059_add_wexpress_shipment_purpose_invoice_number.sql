-- Migration: adicionar campos auxiliares para W-Express (finalidade e invoice) na remessa_janela_pedidos

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos'
);

-- wexpress_shipment_purpose
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_shipment_purpose'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_shipment_purpose VARCHAR(20) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- wexpress_invoice_number
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_invoice_number'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_invoice_number VARCHAR(120) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
