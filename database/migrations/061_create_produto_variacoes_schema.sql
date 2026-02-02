-- Cria estrutura para variações (atributos/opções/variações/fotos) e integra com itens de pedido
-- Idempotente: pode ser executado múltiplas vezes sem erro

-- 1) Tipos de variação (atributos)
CREATE TABLE IF NOT EXISTS variacao_tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_variacao_tipos_slug (slug)
);

-- 2) Opções do tipo (termos)
CREATE TABLE IF NOT EXISTS variacao_opcoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_id INT NOT NULL,
    valor VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_variacao_opcoes_tipo (tipo_id),
    UNIQUE KEY uq_variacao_opcoes_tipo_slug (tipo_id, slug)
);

-- 3) Atributos habilitados por produto
CREATE TABLE IF NOT EXISTS produto_atributos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    tipo_id INT NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_produto_atributos (produto_id, tipo_id),
    KEY idx_produto_atributos_tipo (tipo_id)
);

-- 4) Variações (simples ou compostas) vendáveis
CREATE TABLE IF NOT EXISTS produto_variacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    sku VARCHAR(120) NULL,
    price_override DECIMAL(12,2) NULL,
    stock INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_produto_variacoes_produto (produto_id),
    KEY idx_produto_variacoes_ativo (ativo)
);

-- Índice único por produto/sku quando sku existir (permite NULL repetido)
SET @idx_sku_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produto_variacoes'
      AND INDEX_NAME = 'uq_produto_variacoes_produto_sku'
);

SET @sql_add_uq_sku := IF(
    @idx_sku_exists = 0,
    'CREATE UNIQUE INDEX uq_produto_variacoes_produto_sku ON produto_variacoes (produto_id, sku)',
    'SELECT 1'
);

PREPARE stmt_add_uq_sku FROM @sql_add_uq_sku;
EXECUTE stmt_add_uq_sku;
DEALLOCATE PREPARE stmt_add_uq_sku;

-- 5) Itens (atributo -> opção) de cada variação (combinação)
CREATE TABLE IF NOT EXISTS produto_variacao_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_variacao_id INT NOT NULL,
    tipo_id INT NOT NULL,
    opcao_id INT NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_variacao_itens (produto_variacao_id, tipo_id),
    KEY idx_variacao_itens_opcao (opcao_id)
);

-- 6) Fotos por variação
CREATE TABLE IF NOT EXISTS produto_variacao_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_variacao_id INT NOT NULL,
    nome_arquivo VARCHAR(500) NOT NULL,
    arquivo_original VARCHAR(500) NULL,
    principal TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    KEY idx_pvf_variacao (produto_variacao_id),
    KEY idx_pvf_principal (principal)
);

-- 7) Integrar com itens do pedido (pedido_itens / pedido_items): produto_variacao_id
-- pedido_itens
SET @pedido_itens_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedido_itens'
);

SET @col_pedido_itens_pvi_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedido_itens'
      AND COLUMN_NAME = 'produto_variacao_id'
);

SET @sql_add_pedido_itens_pvi := IF(
    @pedido_itens_exists > 0 AND @col_pedido_itens_pvi_exists = 0,
    'ALTER TABLE pedido_itens ADD COLUMN produto_variacao_id INT NULL',
    'SELECT 1'
);

PREPARE stmt_add_pedido_itens_pvi FROM @sql_add_pedido_itens_pvi;
EXECUTE stmt_add_pedido_itens_pvi;
DEALLOCATE PREPARE stmt_add_pedido_itens_pvi;

-- pedido_items
SET @pedido_items_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedido_items'
);

SET @col_pedido_items_pvi_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedido_items'
      AND COLUMN_NAME = 'produto_variacao_id'
);

SET @sql_add_pedido_items_pvi := IF(
    @pedido_items_exists > 0 AND @col_pedido_items_pvi_exists = 0,
    'ALTER TABLE pedido_items ADD COLUMN produto_variacao_id INT NULL',
    'SELECT 1'
);

PREPARE stmt_add_pedido_items_pvi FROM @sql_add_pedido_items_pvi;
EXECUTE stmt_add_pedido_items_pvi;
DEALLOCATE PREPARE stmt_add_pedido_items_pvi;

-- Índices opcionais
SET @idx_pedido_itens_pvi_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedido_itens'
      AND INDEX_NAME = 'idx_pedido_itens_produto_variacao_id'
);

SET @sql_add_idx_pedido_itens_pvi := IF(
    @pedido_itens_exists > 0 AND @col_pedido_itens_pvi_exists = 0 AND @idx_pedido_itens_pvi_exists = 0,
    'CREATE INDEX idx_pedido_itens_produto_variacao_id ON pedido_itens (produto_variacao_id)',
    'SELECT 1'
);

PREPARE stmt_add_idx_pedido_itens_pvi FROM @sql_add_idx_pedido_itens_pvi;
EXECUTE stmt_add_idx_pedido_itens_pvi;
DEALLOCATE PREPARE stmt_add_idx_pedido_itens_pvi;

SET @idx_pedido_items_pvi_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedido_items'
      AND INDEX_NAME = 'idx_pedido_items_produto_variacao_id'
);

SET @sql_add_idx_pedido_items_pvi := IF(
    @pedido_items_exists > 0 AND @col_pedido_items_pvi_exists = 0 AND @idx_pedido_items_pvi_exists = 0,
    'CREATE INDEX idx_pedido_items_produto_variacao_id ON pedido_items (produto_variacao_id)',
    'SELECT 1'
);

PREPARE stmt_add_idx_pedido_items_pvi FROM @sql_add_idx_pedido_items_pvi;
EXECUTE stmt_add_idx_pedido_items_pvi;
DEALLOCATE PREPARE stmt_add_idx_pedido_items_pvi;
