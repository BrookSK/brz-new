-- Adiciona colunas de pagamento em pedidos caso não existam

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
      AND column_name = 'forma_pagamento'
) = 0,
'ALTER TABLE pedidos ADD COLUMN forma_pagamento VARCHAR(50) NULL AFTER observacoes',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_gateway'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_gateway VARCHAR(50) NULL AFTER forma_pagamento',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_id'
) = 0,
'ALTER TABLE pedidos ADD COLUMN payment_id VARCHAR(255) NULL AFTER payment_gateway',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'payment_status'
) = 0,
"ALTER TABLE pedidos ADD COLUMN payment_status VARCHAR(50) NULL AFTER payment_id",
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl_exists > 0 AND (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pedidos'
      AND column_name = 'pago_em'
) = 0,
'ALTER TABLE pedidos ADD COLUMN pago_em TIMESTAMP NULL AFTER payment_status',
'');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
