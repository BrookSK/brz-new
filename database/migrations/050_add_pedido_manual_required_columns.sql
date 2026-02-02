-- Garante colunas necessárias para pedidos manuais + pagamento + resumo e itens
-- Não altera migrations existentes; execute manualmente no banco.

SET @db := DATABASE();

-- =========================
-- Tabela: pedidos
-- =========================
SET @pedidos_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos'
);

-- Helper para adicionar coluna se não existir

-- moeda
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='moeda'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN moeda VARCHAR(3) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- total (se não existir e também não existir valor_total/valor_total_brl)
SET @col_total := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='total'
);
SET @col_valor_total := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='valor_total'
);
SET @col_valor_total_brl := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='valor_total_brl'
);
SET @sql := IF(@pedidos_exists=1 AND @col_total=0 AND @col_valor_total=0 AND @col_valor_total_brl=0,
  'ALTER TABLE pedidos ADD COLUMN total DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- codigo_pedido (se não existir e também não existir numero_pedido/codigo)
SET @col_codigo_pedido := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='codigo_pedido'
);
SET @col_numero_pedido := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='numero_pedido'
);
SET @col_codigo := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='codigo'
);
SET @sql := IF(@pedidos_exists=1 AND @col_codigo_pedido=0 AND @col_numero_pedido=0 AND @col_codigo=0,
  'ALTER TABLE pedidos ADD COLUMN codigo_pedido VARCHAR(50) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- status (garantir um campo de status simples)
SET @col_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='status'
);
SET @col_status_pedido := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='status_pedido'
);
SET @col_pedido_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='pedido_status'
);
SET @sql := IF(@pedidos_exists=1 AND @col_status=0 AND @col_status_pedido=0 AND @col_pedido_status=0,
  'ALTER TABLE pedidos ADD COLUMN status VARCHAR(30) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- observacoes
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='observacoes'
);
SET @col_observacao := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='observacao'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0 AND @col_observacao=0,
  'ALTER TABLE pedidos ADD COLUMN observacoes TEXT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- origem_pedido
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='origem_pedido'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN origem_pedido VARCHAR(20) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- admin_criador_id
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='admin_criador_id'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN admin_criador_id INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payment_gateway
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='payment_gateway'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN payment_gateway VARCHAR(30) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payment_id
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='payment_id'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN payment_id VARCHAR(120) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payment_status
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='payment_status'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN payment_status VARCHAR(30) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- subtotal_produtos
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='subtotal_produtos'
);
SET @col_subtotal := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='subtotal'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0 AND @col_subtotal=0,
  'ALTER TABLE pedidos ADD COLUMN subtotal_produtos DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- peso_total
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='peso_total'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN peso_total DECIMAL(12,3) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- taxa_servico
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='taxa_servico'
);
SET @col_servicos := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='servicos'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0 AND @col_servicos=0,
  'ALTER TABLE pedidos ADD COLUMN taxa_servico DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- valor_impostos
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='valor_impostos'
);
SET @col_impostos := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='impostos'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0 AND @col_impostos=0,
  'ALTER TABLE pedidos ADD COLUMN valor_impostos DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- valor_frete
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='valor_frete'
);
SET @col_frete := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='frete'
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0 AND @col_frete=0,
  'ALTER TABLE pedidos ADD COLUMN valor_frete DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- valor_total (se não existir e também não existir total)
SET @col_valor_total := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='valor_total'
);
SET @col_total := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND COLUMN_NAME='total'
);
SET @sql := IF(@pedidos_exists=1 AND @col_valor_total=0 AND @col_total=0,
  'ALTER TABLE pedidos ADD COLUMN valor_total DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices úteis
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND INDEX_NAME='idx_pedidos_origem_pedido'
);
SET @sql := IF(@pedidos_exists=1 AND @idx_exists=0,
  'ALTER TABLE pedidos ADD INDEX idx_pedidos_origem_pedido (origem_pedido)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedidos' AND INDEX_NAME='idx_pedidos_admin_criador_id'
);
SET @sql := IF(@pedidos_exists=1 AND @idx_exists=0,
  'ALTER TABLE pedidos ADD INDEX idx_pedidos_admin_criador_id (admin_criador_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================
-- Tabela: itens do pedido (pedido_itens / pedido_items)
-- =========================
SET @pedido_itens_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedido_itens'
);
SET @pedido_items_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pedido_items'
);

SET @itens_table := IF(@pedido_itens_exists>0, 'pedido_itens', IF(@pedido_items_exists>0, 'pedido_items', NULL));

-- nome_produto / produto_nome / nome (vamos garantir nome_produto)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@itens_table AND COLUMN_NAME='nome_produto'
);
SET @sql := IF(@itens_table IS NOT NULL AND @col_exists=0,
  CONCAT('ALTER TABLE ', @itens_table, ' ADD COLUMN nome_produto VARCHAR(255) NULL'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sku
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@itens_table AND COLUMN_NAME='sku'
);
SET @sql := IF(@itens_table IS NOT NULL AND @col_exists=0,
  CONCAT('ALTER TABLE ', @itens_table, ' ADD COLUMN sku VARCHAR(80) NULL'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- quantidade (garantir quantidade)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@itens_table AND COLUMN_NAME='quantidade'
);
SET @col_qty := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@itens_table AND COLUMN_NAME='qty'
);
SET @sql := IF(@itens_table IS NOT NULL AND @col_exists=0 AND @col_qty=0,
  CONCAT('ALTER TABLE ', @itens_table, ' ADD COLUMN quantidade INT NULL'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- valor_unitario (garantir valor_unitario)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@itens_table AND COLUMN_NAME='valor_unitario'
);
SET @col_price := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@itens_table AND COLUMN_NAME='price'
);
SET @col_preco := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@itens_table AND COLUMN_NAME='preco'
);
SET @sql := IF(@itens_table IS NOT NULL AND @col_exists=0 AND @col_price=0 AND @col_preco=0,
  CONCAT('ALTER TABLE ', @itens_table, ' ADD COLUMN valor_unitario DECIMAL(12,2) NULL'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- subtotal
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@itens_table AND COLUMN_NAME='subtotal'
);
SET @sql := IF(@itens_table IS NOT NULL AND @col_exists=0,
  CONCAT('ALTER TABLE ', @itens_table, ' ADD COLUMN subtotal DECIMAL(12,2) NULL'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
