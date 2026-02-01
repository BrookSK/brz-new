-- Adiciona colunas para cobrança de diferença (débito adicional) em pedidos caso não existam

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
      AND column_name = 'payment_diferenca_id'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_diferenca_id VARCHAR(255) NULL AFTER payment_status',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_diferenca_status'
) = 0,
"ALTER TABLE pedidos ADD COLUMN payment_diferenca_status VARCHAR(50) NULL AFTER payment_diferenca_id",
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_diferenca_valor'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_diferenca_valor DECIMAL(10,2) NULL AFTER payment_diferenca_status',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_diferenca_invoice_url'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_diferenca_invoice_url TEXT NULL AFTER payment_diferenca_valor',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_diferenca_bank_slip_url'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_diferenca_bank_slip_url TEXT NULL AFTER payment_diferenca_invoice_url',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_diferenca_created_at'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_diferenca_created_at TIMESTAMP NULL AFTER payment_diferenca_bank_slip_url',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_diferenca_paid_at'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_diferenca_paid_at TIMESTAMP NULL AFTER payment_diferenca_created_at',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
