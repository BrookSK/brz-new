-- Adiciona colunas de destinatário em pedidos caso não existam

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
      AND column_name = 'destinatario_nome'
) = 0,
"ALTER TABLE pedidos ADD COLUMN destinatario_nome VARCHAR(255) NULL",
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'destinatario_documento'
) = 0,
"ALTER TABLE pedidos ADD COLUMN destinatario_documento VARCHAR(50) NULL",
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'destinatario_telefone'
) = 0,
"ALTER TABLE pedidos ADD COLUMN destinatario_telefone VARCHAR(50) NULL",
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
