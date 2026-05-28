-- Adicionar status 'itens_parcialmente_comprados' ao fluxo de pedidos
-- Este status indica que pelo menos um item do pedido já foi comprado, mas ainda faltam outros

-- Se a coluna status for ENUM, precisamos expandir os valores permitidos
-- Se for VARCHAR, não precisa de alteração no schema

SET @db := DATABASE();

-- Verificar se a coluna status é ENUM
SET @col_type := (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'status'
);

-- Se for ENUM e não contiver 'itens_parcialmente_comprados', alterar
SET @has_partial := (
    SELECT CASE WHEN @col_type LIKE '%itens_parcialmente_comprados%' THEN 1 ELSE 0 END
);

SET @is_enum := (
    SELECT CASE WHEN @col_type LIKE 'enum%' THEN 1 ELSE 0 END
);

-- Se for ENUM, converter para VARCHAR para flexibilidade (evita problemas com novos status)
SET @sql := IF(@is_enum = 1 AND @has_partial = 0,
    'ALTER TABLE pedidos MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT \'pendente\'',
    'SELECT "Status column already supports partial purchase status" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
