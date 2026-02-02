-- Ajuste de schema: garante coluna usuarios.nome (usada em ORDER BY e telas/admin)
-- Não altera migrations existentes; execute manualmente no banco.

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios'
);

SET @col_nome_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'nome'
);

SET @col_name_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'name'
);

-- 1) Adiciona coluna nome se não existir
SET @sql_add := IF(
  @table_exists = 1 AND @col_nome_exists = 0,
  'ALTER TABLE usuarios ADD COLUMN nome VARCHAR(255) NULL',
  'SELECT 1'
);
PREPARE stmt_add FROM @sql_add;
EXECUTE stmt_add;
DEALLOCATE PREPARE stmt_add;

-- Recalcular após alteração
SET @col_nome_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'nome'
);

-- 2) Se existir coluna name, copia valores para nome quando nome estiver vazio
SET @sql_fill := IF(
  @table_exists = 1 AND @col_nome_exists = 1 AND @col_name_exists = 1,
  'UPDATE usuarios SET nome = name WHERE (nome IS NULL OR nome = "") AND (name IS NOT NULL AND name <> "")',
  'SELECT 1'
);
PREPARE stmt_fill FROM @sql_fill;
EXECUTE stmt_fill;
DEALLOCATE PREPARE stmt_fill;

-- 3) Cria índice em nome (opcional, apenas se a coluna existir)
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_nome'
);

SET @sql_idx := IF(
  @table_exists = 1 AND @col_nome_exists = 1 AND @idx_exists = 0,
  'CREATE INDEX idx_usuarios_nome ON usuarios (nome)',
  'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
