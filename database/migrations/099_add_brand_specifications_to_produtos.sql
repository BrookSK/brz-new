-- Adiciona colunas brand (marca) e specifications (especificações técnicas) à tabela produtos
-- Idempotente: só adiciona se não existir

SET @db := DATABASE();

SET @has_produtos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'produtos');

-- brand
SET @col_brand := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'produtos' AND column_name = 'brand');
SET @col_marca := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'produtos' AND column_name = 'marca');
SET @sql_brand := IF(@has_produtos > 0 AND @col_brand = 0 AND @col_marca = 0,
  'ALTER TABLE produtos ADD COLUMN brand VARCHAR(255) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_brand; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- specifications
SET @col_specs := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'produtos' AND column_name = 'specifications');
SET @col_especificacoes := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'produtos' AND column_name = 'especificacoes');
SET @sql_specs := IF(@has_produtos > 0 AND @col_specs = 0 AND @col_especificacoes = 0,
  'ALTER TABLE produtos ADD COLUMN specifications TEXT NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_specs; EXECUTE stmt; DEALLOCATE PREPARE stmt;
