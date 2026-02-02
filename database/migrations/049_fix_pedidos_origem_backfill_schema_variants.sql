-- Fix: backfill origem_pedido without assuming codigo_pedido exists
-- Some schemas don't have pedidos.codigo_pedido. This migration updates origem_pedido safely
-- using whichever identifier columns exist, or admin_criador_id when available.

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos'
);

SET @has_origem := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'origem_pedido'
);

SET @has_admin := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'admin_criador_id'
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

SET @has_codigo := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'codigo'
);

-- 1) Marcar como manual se admin_criador_id estiver preenchido (quando a coluna existir)
SET @sql1 := IF(
  @table_exists = 1 AND @has_origem = 1 AND @has_admin = 1,
  "UPDATE pedidos SET origem_pedido = 'manual' WHERE (origem_pedido IS NULL OR origem_pedido = '') AND admin_criador_id IS NOT NULL",
  'SELECT 1'
);
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

-- 2) Se existir codigo_pedido e/ou numero_pedido e/ou codigo, tentar detectar prefixo MAN-
SET @where_man := '';
SET @where_man := IF(@has_codigo_pedido = 1, "(codigo_pedido LIKE 'MAN-%')", @where_man);
SET @where_man := IF(@has_numero_pedido = 1,
  IF(@where_man = '', "(numero_pedido LIKE 'MAN-%')", CONCAT(@where_man, " OR (numero_pedido LIKE 'MAN-%')")),
  @where_man
);
SET @where_man := IF(@has_codigo = 1,
  IF(@where_man = '', "(codigo LIKE 'MAN-%')", CONCAT(@where_man, " OR (codigo LIKE 'MAN-%')")),
  @where_man
);

SET @sql2 := IF(
  @table_exists = 1 AND @has_origem = 1 AND @where_man <> '',
  CONCAT("UPDATE pedidos SET origem_pedido = 'manual' WHERE (origem_pedido IS NULL OR origem_pedido = '') AND (", @where_man, ")"),
  'SELECT 1'
);
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

-- 3) Qualquer pedido restante sem origem vira organico
SET @sql3 := IF(
  @table_exists = 1 AND @has_origem = 1,
  "UPDATE pedidos SET origem_pedido = 'organico' WHERE origem_pedido IS NULL OR origem_pedido = ''",
  'SELECT 1'
);
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;
