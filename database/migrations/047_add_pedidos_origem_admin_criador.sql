-- Migration: add order origin and admin creator id for manual orders

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos'
);

-- Add column `origem_pedido`
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'origem_pedido'
);

SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE pedidos ADD COLUMN origem_pedido VARCHAR(20) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add column `admin_criador_id`
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'admin_criador_id'
);

SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE pedidos ADD COLUMN admin_criador_id INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index for `origem_pedido`
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND INDEX_NAME = 'idx_pedidos_origem_pedido'
);

SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE pedidos ADD INDEX idx_pedidos_origem_pedido (origem_pedido)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index for `admin_criador_id`
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND INDEX_NAME = 'idx_pedidos_admin_criador_id'
);

SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE pedidos ADD INDEX idx_pedidos_admin_criador_id (admin_criador_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill origin for existing orders where origin is null.
-- Este UPDATE lê codigo_pedido e numero_pedido; alguns schemas não têm essas
-- colunas (erro 1054). Só executa se AMBAS existirem — caso contrário vira
-- no-op e a migration 049 (schema-variant safe) faz o backfill corretamente.
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'origem_pedido'
);
SET @has_codigo_pedido := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'codigo_pedido'
);
SET @has_numero_pedido := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'numero_pedido'
);

SET @sql := IF(@table_exists = 1 AND @col_exists = 1 AND @has_codigo_pedido = 1 AND @has_numero_pedido = 1,
  "UPDATE pedidos SET origem_pedido = CASE WHEN (codigo_pedido LIKE 'MAN-%' OR numero_pedido LIKE 'MAN-%') THEN 'manual' ELSE 'organico' END WHERE origem_pedido IS NULL OR origem_pedido = ''",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
