-- Migration: adicionar colunas para rastrear link original e variação selecionada nos itens do pedido

-- Observação:
-- - Rode manualmente no banco.
-- - Não altera migrations antigas.
-- - Compatível com MySQL sem `ADD COLUMN IF NOT EXISTS`

SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedido_itens' AND COLUMN_NAME='url_original') = 0,
    'ALTER TABLE pedido_itens ADD COLUMN url_original VARCHAR(1024) NULL AFTER produto_id',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedido_itens' AND COLUMN_NAME='variacao_id') = 0,
    'ALTER TABLE pedido_itens ADD COLUMN variacao_id VARCHAR(255) NULL AFTER url_original',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedido_itens' AND COLUMN_NAME='variacao_label') = 0,
    'ALTER TABLE pedido_itens ADD COLUMN variacao_label VARCHAR(512) NULL AFTER variacao_id',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedido_itens' AND COLUMN_NAME='variacao_atributos') = 0,
    'ALTER TABLE pedido_itens ADD COLUMN variacao_atributos JSON NULL AFTER variacao_label',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
