-- Adiciona observacao_vendedor em pedidos caso não exista (para uso interno do vendedor)

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
);

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'observacao_vendedor'
) = 0,
"ALTER TABLE pedidos ADD COLUMN observacao_vendedor LONGTEXT NULL",
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
