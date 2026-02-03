-- Adiciona colunas auxiliares de pagamento (PIX/boleto/link) em pedidos caso não existam

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
      AND column_name = 'payment_invoice_url'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_invoice_url TEXT NULL AFTER payment_status',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_bank_slip_url'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_bank_slip_url TEXT NULL AFTER payment_invoice_url',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_digitable_line'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_digitable_line TEXT NULL AFTER payment_bank_slip_url',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_pix_encoded_image'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_pix_encoded_image LONGTEXT NULL AFTER payment_digitable_line',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_pix_payload'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_pix_payload LONGTEXT NULL AFTER payment_pix_encoded_image',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
