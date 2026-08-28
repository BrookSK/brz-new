-- Adiciona customer_id em `pagamentos` SE a tabela existir.
-- Compatibilidade: alguns bancos não possuem a tabela `pagamentos` (o sistema
-- tem fallback em `pedidos`). Migrations irmãs (034/035) já usam este mesmo
-- guard idempotente; a versão original desta migration fazia um ALTER cru e
-- quebrava com 1146 em bancos sem a tabela.

SET @has_pagamentos := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos'
);
SET @has_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos' AND COLUMN_NAME = 'customer_id'
);
SET @sql := IF(@has_pagamentos > 0 AND @has_col = 0,
    'ALTER TABLE pagamentos ADD COLUMN customer_id VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
