-- Adiciona coluna 'tipo' para diferenciar comprovante de produtos vs taxas/impostos

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedidos_pagamento_documentos'
      AND COLUMN_NAME = 'tipo'
);

SET @sql := IF(@col_exists = 0,
    "ALTER TABLE pedidos_pagamento_documentos ADD COLUMN tipo ENUM('produtos','taxas') NOT NULL DEFAULT 'produtos' AFTER metodo",
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para facilitar busca por tipo
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedidos_pagamento_documentos'
      AND INDEX_NAME = 'idx_ppd_tipo'
);

SET @sql2 := IF(@idx_exists = 0,
    'ALTER TABLE pedidos_pagamento_documentos ADD INDEX idx_ppd_tipo (pedido_id, tipo)',
    'SELECT 1'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
