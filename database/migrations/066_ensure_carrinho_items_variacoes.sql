-- Garantir colunas e índices necessários para carrinho com variações (idempotente)

-- carrinhos: campos básicos
SET @has_carrinhos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'carrinhos');
SET @sql := IF(@has_carrinhos = 0,
  'CREATE TABLE carrinhos (\
    id INT AUTO_INCREMENT PRIMARY KEY,\
    usuario_id INT NULL,\
    session_id VARCHAR(255) NULL,\
    moeda VARCHAR(3) NOT NULL DEFAULT ''USD'',\
    taxa_conversao DECIMAL(10,6) NULL,\
    frete_manual DECIMAL(10,2) DEFAULT 0,\
    taxa_servico DECIMAL(10,2) DEFAULT 0,\
    subtotal_produtos DECIMAL(12,2) DEFAULT 0,\
    valor_impostos DECIMAL(12,2) DEFAULT 0,\
    valor_total DECIMAL(12,2) DEFAULT 0,\
    peso_total DECIMAL(8,3) DEFAULT 0,\
    expira_em TIMESTAMP NULL,\
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\
    KEY idx_carrinho_usuario (usuario_id),\
    KEY idx_carrinho_session (session_id)\
  )',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- carrinho_items: criação se não existir
SET @has_items := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'carrinho_items');
SET @sql := IF(@has_items = 0,
  'CREATE TABLE carrinho_items (\
    id INT AUTO_INCREMENT PRIMARY KEY,\
    carrinho_id INT NOT NULL,\
    produto_id INT NOT NULL,\
    produto_variacao_id INT NULL,\
    variacao_descricao VARCHAR(255) NULL,\
    quantidade INT NOT NULL DEFAULT 1,\
    valor_unitario DECIMAL(12,2) NOT NULL,\
    subtotal DECIMAL(12,2) NOT NULL,\
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\
    UNIQUE KEY uq_carrinho_item (carrinho_id, produto_id, produto_variacao_id),\
    KEY idx_carrinho_items_carrinho (carrinho_id),\
    KEY idx_carrinho_items_produto (produto_id)\
  )',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- carrinho_items: colunas de variação (se tabela já existia sem elas)
SET @has_col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='carrinho_items' AND column_name='produto_variacao_id');
SET @sql := IF(@has_col = 0, 'ALTER TABLE carrinho_items ADD COLUMN produto_variacao_id INT NULL AFTER produto_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='carrinho_items' AND column_name='variacao_descricao');
SET @sql := IF(@has_col = 0, 'ALTER TABLE carrinho_items ADD COLUMN variacao_descricao VARCHAR(255) NULL AFTER produto_variacao_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='carrinho_items' AND column_name='updated_at');
SET @sql := IF(@has_col = 0, 'ALTER TABLE carrinho_items ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- índice único para variações
SET @has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name='carrinho_items' AND index_name='uq_carrinho_item');
SET @sql := IF(@has_idx = 0, 'ALTER TABLE carrinho_items ADD UNIQUE KEY uq_carrinho_item (carrinho_id, produto_id, produto_variacao_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
