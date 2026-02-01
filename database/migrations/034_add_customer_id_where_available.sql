-- Compatibilidade: alguns bancos não possuem a tabela `pagamentos`.
-- Esta migration adiciona `customer_id` onde for possível:
-- 1) Se existir `pagamentos`, adiciona a coluna lá.
-- 2) Se NÃO existir `pagamentos`, adiciona a coluna em `pedidos`.

-- pagamentos.customer_id
SET @has_pagamentos := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos');
SET @has_col_pagamentos := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos' AND COLUMN_NAME = 'customer_id');
SET @sql_pagamentos := IF(@has_pagamentos > 0 AND @has_col_pagamentos = 0,
    'ALTER TABLE pagamentos ADD COLUMN customer_id VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE stmt_pagamentos FROM @sql_pagamentos; EXECUTE stmt_pagamentos; DEALLOCATE PREPARE stmt_pagamentos;

-- pedidos.customer_id (fallback)
SET @has_pedidos := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos');
SET @has_col_pedidos := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'customer_id');
SET @sql_pedidos := IF(@has_pagamentos = 0 AND @has_pedidos > 0 AND @has_col_pedidos = 0,
    'ALTER TABLE pedidos ADD COLUMN customer_id VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE stmt_pedidos FROM @sql_pedidos; EXECUTE stmt_pedidos; DEALLOCATE PREPARE stmt_pedidos;
