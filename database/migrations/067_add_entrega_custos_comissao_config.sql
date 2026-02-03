-- Adiciona colunas de configuração de entrega para custo interno por item (USD) e comissão (%)
-- Não altera migrations existentes

-- Caso o projeto use schema legacy "configuracoes_sistema" (single row), garantir colunas
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'configuracoes_sistema'
);

-- custo_envio_por_item_usd
SET @col_custo_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'configuracoes_sistema'
      AND COLUMN_NAME = 'custo_envio_por_item_usd'
);

SET @sql_add_custo := IF(
    @tbl_exists > 0 AND @col_custo_exists = 0,
    'ALTER TABLE configuracoes_sistema ADD COLUMN custo_envio_por_item_usd DECIMAL(10,2) NULL DEFAULT 0',
    'SELECT 1'
);

PREPARE stmt_add_custo FROM @sql_add_custo;
EXECUTE stmt_add_custo;
DEALLOCATE PREPARE stmt_add_custo;

-- comissao_percentual
SET @col_comissao_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'configuracoes_sistema'
      AND COLUMN_NAME = 'comissao_percentual'
);

SET @sql_add_comissao := IF(
    @tbl_exists > 0 AND @col_comissao_exists = 0,
    'ALTER TABLE configuracoes_sistema ADD COLUMN comissao_percentual DECIMAL(10,2) NULL DEFAULT 0',
    'SELECT 1'
);

PREPARE stmt_add_comissao FROM @sql_add_comissao;
EXECUTE stmt_add_comissao;
DEALLOCATE PREPARE stmt_add_comissao;
