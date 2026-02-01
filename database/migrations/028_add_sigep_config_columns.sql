-- Migration: add SIGEP Web (Correios) config columns to configuracoes_sistema (legacy single-row schema)

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema'
);

-- sigep_enabled
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_enabled'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_enabled TINYINT(1) NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sigep_ambiente
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_ambiente'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_ambiente VARCHAR(20) NULL DEFAULT \'homologacao\'',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sigep_usuario
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_usuario'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_usuario VARCHAR(100) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sigep_senha
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_senha'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_senha LONGTEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sigep_numero_contrato
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_numero_contrato'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_numero_contrato VARCHAR(50) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sigep_cartao_postagem
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_cartao_postagem'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_cartao_postagem VARCHAR(50) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sigep_cnpj
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_cnpj'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_cnpj VARCHAR(30) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sigep_servico
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_servico'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_servico VARCHAR(50) NULL DEFAULT \'PAC\'',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sigep_servico_codigo (código do serviço no contrato)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'configuracoes_sistema' AND COLUMN_NAME = 'sigep_servico_codigo'
);
SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN sigep_servico_codigo VARCHAR(50) NULL DEFAULT \'\'',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
