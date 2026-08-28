-- Adiciona valor_pago em `pagamentos` SE a tabela existir.
-- Compatibilidade: alguns bancos não possuem a tabela `pagamentos` (o sistema
-- tem fallback em `pedidos`). A migration irmã 035 já usa este guard; a versão
-- original desta fazia um ALTER cru e quebrava com 1146 em bancos sem a tabela.

SET @has_pagamentos := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos'
);
SET @has_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos' AND COLUMN_NAME = 'valor_pago'
);
SET @sql := IF(@has_pagamentos > 0 AND @has_col = 0,
    'ALTER TABLE pagamentos ADD COLUMN valor_pago DECIMAL(12,2) NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
