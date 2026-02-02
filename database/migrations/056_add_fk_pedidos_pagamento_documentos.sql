-- Adiciona FK pedidos_pagamento_documentos.pedido_id -> pedidos(id) quando possível

SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_pagamento_documentos');
SET @ped := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos');

SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedidos_pagamento_documentos'
      AND COLUMN_NAME = 'pedido_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1);

SET @sql := IF(@tbl > 0 AND @ped > 0 AND @fk IS NULL,
    'ALTER TABLE pedidos_pagamento_documentos ADD CONSTRAINT fk_ppd_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
