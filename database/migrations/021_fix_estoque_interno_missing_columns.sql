-- Migration 021: Garantir todas as colunas necessárias em estoque_interno (corrige erro Unknown column 'galpao')
-- Rode este SQL no mesmo banco do site (ex: novobr) no servidor.

-- 1) Se a tabela não existir, cria com o schema completo
CREATE TABLE IF NOT EXISTS estoque_interno (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    data_compra DATE NULL,
    data_validade DATE NULL,
    is_alimenticio TINYINT(1) NOT NULL DEFAULT 0,
    galpao VARCHAR(80) NULL,
    prateleira VARCHAR(80) NULL,
    observacao VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estoque_interno_produto (produto_id),
    INDEX idx_estoque_interno_validade (data_validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Se a tabela já existe mas faltam colunas, adiciona de forma segura
-- (compatível com MySQL que não suporta ADD COLUMN IF NOT EXISTS)

SET @db := DATABASE();

-- galpao
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'galpao'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE estoque_interno ADD COLUMN galpao VARCHAR(80) NULL AFTER is_alimenticio', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- prateleira
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'prateleira'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE estoque_interno ADD COLUMN prateleira VARCHAR(80) NULL AFTER galpao', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- observacao
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'observacao'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE estoque_interno ADD COLUMN observacao VARCHAR(255) NULL AFTER prateleira', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- data_compra
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'data_compra'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE estoque_interno ADD COLUMN data_compra DATE NULL AFTER quantidade', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- data_validade
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'data_validade'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE estoque_interno ADD COLUMN data_validade DATE NULL AFTER data_compra', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_alimenticio
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'is_alimenticio'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE estoque_interno ADD COLUMN is_alimenticio TINYINT(1) NOT NULL DEFAULT 0 AFTER data_validade', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- created_at
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE estoque_interno ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- updated_at
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_interno'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE estoque_interno ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
