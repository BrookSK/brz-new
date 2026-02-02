-- Garante que a tabela produtos exista e que o campo featured (destaque) exista sem erro de duplicidade

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NULL,
    nome VARCHAR(255) NULL,
    slug VARCHAR(255) NULL,
    sku VARCHAR(100) NULL,
    description TEXT NULL,
    short_description TEXT NULL,
    category_id INT NULL,
    categoria_id INT NULL,
    price DECIMAL(12,2) NULL,
    cost_price DECIMAL(12,2) NULL,
    sale_price DECIMAL(12,2) NULL,
    weight DECIMAL(8,3) NULL,
    stock INT NULL,
    min_stock INT NULL,
    max_stock INT NULL,
    length DECIMAL(8,2) NULL,
    width DECIMAL(8,2) NULL,
    height DECIMAL(8,2) NULL,
    type VARCHAR(50) NULL,
    status VARCHAR(50) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    foto_principal VARCHAR(500) NULL,
    loja VARCHAR(255) NULL,
    loja_id INT NULL,
    ncm VARCHAR(20) NULL,
    tags JSON NULL,
    images JSON NULL,
    variations JSON NULL,
    attributes JSON NULL,
    currency VARCHAR(3) NULL,
    digital TINYINT(1) NULL,
    digital_file VARCHAR(500) NULL,
    digital_downloads INT NULL,
    views INT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

-- Adiciona coluna featured apenas se não existir (compatível com MySQL 5.7+ via INFORMATION_SCHEMA)
SET @col_featured_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'featured'
);

SET @sql_add_featured := IF(
    @col_featured_exists = 0,
    'ALTER TABLE produtos ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);

PREPARE stmt_add_featured FROM @sql_add_featured;
EXECUTE stmt_add_featured;
DEALLOCATE PREPARE stmt_add_featured;

-- Cria índice apenas se não existir
SET @idx_featured_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND INDEX_NAME = 'idx_produtos_featured'
);

SET @sql_add_idx := IF(
    @idx_featured_exists = 0,
    'CREATE INDEX idx_produtos_featured ON produtos (featured)',
    'SELECT 1'
);

PREPARE stmt_add_idx FROM @sql_add_idx;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;
