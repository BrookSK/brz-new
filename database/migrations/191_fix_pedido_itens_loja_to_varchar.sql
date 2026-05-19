-- Migration: converter coluna loja em pedido_itens/pedido_items de ENUM para VARCHAR(120)
-- Corrige erro "Data truncated for column 'loja'" ao adicionar itens com lojas como 'amazoncom'

SET @db := DATABASE();

-- ========== pedido_itens ==========
SET @table_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_itens'
);

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_itens' AND COLUMN_NAME = 'loja'
);

SET @is_enum := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_itens' AND COLUMN_NAME = 'loja'
    AND DATA_TYPE = 'enum'
);

SET @is_short_varchar := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_itens' AND COLUMN_NAME = 'loja'
    AND DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH < 120
);

-- Se a coluna existe e é ENUM ou VARCHAR curto, converter para VARCHAR(120)
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 1 AND (@is_enum = 1 OR @is_short_varchar = 1),
  'ALTER TABLE pedido_itens MODIFY COLUMN loja VARCHAR(120) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Se a tabela existe mas a coluna não, adicionar
SET @sql2 := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE pedido_itens ADD COLUMN loja VARCHAR(120) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ========== pedido_items (variante de nome) ==========
SET @table2_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_items'
);

SET @col2_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_items' AND COLUMN_NAME = 'loja'
);

SET @is_enum2 := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_items' AND COLUMN_NAME = 'loja'
    AND DATA_TYPE = 'enum'
);

SET @is_short_varchar2 := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_items' AND COLUMN_NAME = 'loja'
    AND DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH < 120
);

SET @sql3 := IF(
  @table2_exists = 1 AND @col2_exists = 1 AND (@is_enum2 = 1 OR @is_short_varchar2 = 1),
  'ALTER TABLE pedido_items MODIFY COLUMN loja VARCHAR(120) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @sql4 := IF(
  @table2_exists = 1 AND @col2_exists = 0,
  'ALTER TABLE pedido_items ADD COLUMN loja VARCHAR(120) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;
