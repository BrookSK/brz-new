-- Migration: store W-Express label/shipping metadata per pedido dentro da janela

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos'
);

-- wexpress_shipping_id
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_shipping_id'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_shipping_id VARCHAR(120) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- wexpress_tracking_number
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_tracking_number'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_tracking_number VARCHAR(120) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- courier_tracking_number
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'courier_tracking_number'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN courier_tracking_number VARCHAR(120) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- wexpress_status
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_status'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_status VARCHAR(50) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- wexpress_last_request_json
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_last_request_json'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_last_request_json LONGTEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- wexpress_last_response_json
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_last_response_json'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_last_response_json LONGTEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- wexpress_last_http_code
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_last_http_code'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_last_http_code INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- wexpress_updated_at
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos' AND COLUMN_NAME = 'wexpress_updated_at'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE remessa_janela_pedidos ADD COLUMN wexpress_updated_at DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
