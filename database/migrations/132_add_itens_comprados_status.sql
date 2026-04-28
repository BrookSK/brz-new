-- Migration 132: Adiciona status 'itens_comprados' à coluna status da tabela pedidos
-- Se a coluna for ENUM, adiciona o novo valor. Se for VARCHAR, não precisa de alteração.

SET @col_type := (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedidos'
      AND COLUMN_NAME = 'status'
    LIMIT 1
);

-- Só altera se for ENUM e o valor ainda não existir
SET @is_enum := IF(@col_type LIKE 'enum(%', 1, 0);
SET @has_value := IF(@col_type LIKE '%itens_comprados%', 1, 0);

SET @sql := IF(
    @is_enum = 1 AND @has_value = 0,
    CONCAT(
        'ALTER TABLE pedidos MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT ''pendente'''
    ),
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
