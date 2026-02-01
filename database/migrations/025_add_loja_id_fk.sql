-- Migration 025: Migrar vinculo de loja para FK (loja_id) em produtos e lista_compras
-- Rode este SQL no banco do servidor.
-- Não remove colunas antigas (ex.: produtos.loja), apenas adiciona loja_id e tenta popular a partir de lojas.slug.

SET @db := DATABASE();

-- Garantir tabela lojas
CREATE TABLE IF NOT EXISTS lojas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- produtos.loja_id
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'loja_id'
);

SET @sql := IF(
    @exists = 0,
    'ALTER TABLE produtos ADD COLUMN loja_id INT NULL AFTER loja',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- lista_compras.loja_id
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'lista_compras'
      AND COLUMN_NAME = 'loja_id'
);

SET @sql := IF(
    @exists = 0,
    'ALTER TABLE lista_compras ADD COLUMN loja_id INT NULL AFTER loja',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'produtos'
      AND INDEX_NAME = 'idx_produtos_loja_id'
);

SET @sql := IF(
    @exists = 0,
    'CREATE INDEX idx_produtos_loja_id ON produtos (loja_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'lista_compras'
      AND INDEX_NAME = 'idx_lista_compras_loja_id'
);

SET @sql := IF(
    @exists = 0,
    'CREATE INDEX idx_lista_compras_loja_id ON lista_compras (loja_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill: produtos.loja (slug) -> produtos.loja_id
SET @has_produtos_loja := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'loja'
);

SET @has_lojas_slug := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'lojas'
      AND COLUMN_NAME = 'slug'
);

SET @sql := IF(
    @has_produtos_loja > 0 AND @has_lojas_slug > 0,
    'UPDATE produtos p JOIN lojas l ON LOWER(TRIM(p.loja)) = LOWER(TRIM(l.slug)) SET p.loja_id = l.id WHERE (p.loja_id IS NULL OR p.loja_id = 0) AND p.loja IS NOT NULL AND p.loja <> ""',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill: lista_compras.loja (slug) -> lista_compras.loja_id
SET @has_lista_loja := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'lista_compras'
      AND COLUMN_NAME = 'loja'
);

SET @sql := IF(
    @has_lista_loja > 0 AND @has_lojas_slug > 0,
    'UPDATE lista_compras lc JOIN lojas l ON LOWER(TRIM(lc.loja)) = LOWER(TRIM(l.slug)) SET lc.loja_id = l.id WHERE (lc.loja_id IS NULL OR lc.loja_id = 0) AND lc.loja IS NOT NULL AND lc.loja <> ""',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
