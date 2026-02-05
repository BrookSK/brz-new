-- Adiciona campo Clube Ativo aos produtos (para benefícios do Clube Brasiliana)

SET @col_clube_ativo_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'clube_ativo'
);

SET @sql_add_clube_ativo := IF(
    @col_clube_ativo_exists = 0,
    'ALTER TABLE produtos ADD COLUMN clube_ativo TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);

PREPARE stmt_add_clube_ativo FROM @sql_add_clube_ativo;
EXECUTE stmt_add_clube_ativo;
DEALLOCATE PREPARE stmt_add_clube_ativo;

SET @idx_clube_ativo_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND INDEX_NAME = 'idx_produtos_clube_ativo'
);

SET @sql_add_idx_clube_ativo := IF(
    @idx_clube_ativo_exists = 0,
    'CREATE INDEX idx_produtos_clube_ativo ON produtos (clube_ativo)',
    'SELECT 1'
);

PREPARE stmt_add_idx_clube_ativo FROM @sql_add_idx_clube_ativo;
EXECUTE stmt_add_idx_clube_ativo;
DEALLOCATE PREPARE stmt_add_idx_clube_ativo;
