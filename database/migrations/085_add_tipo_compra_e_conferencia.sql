-- Adiciona suporte a:
-- - tipo_compra (online/offline)
-- - status_conferencia (pendente/confirmado/cancelado)
--
-- Observação: migrations antigas não devem ser alteradas. Esta migration é idempotente.

-- pedidos.tipo_compra
SET @has_pedidos := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos');
SET @has_col_pedidos_tipo_compra := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'tipo_compra');
SET @sql_pedidos_tipo_compra := IF(@has_pedidos > 0 AND @has_col_pedidos_tipo_compra = 0,
    'ALTER TABLE pedidos ADD COLUMN tipo_compra VARCHAR(16) NULL',
    'SELECT 1'
);
PREPARE stmt_pedidos_tipo_compra FROM @sql_pedidos_tipo_compra; EXECUTE stmt_pedidos_tipo_compra; DEALLOCATE PREPARE stmt_pedidos_tipo_compra;

-- pedidos.status_conferencia
SET @has_col_pedidos_status_conferencia := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'status_conferencia');
SET @sql_pedidos_status_conferencia := IF(@has_pedidos > 0 AND @has_col_pedidos_status_conferencia = 0,
    'ALTER TABLE pedidos ADD COLUMN status_conferencia VARCHAR(20) NULL',
    'SELECT 1'
);
PREPARE stmt_pedidos_status_conferencia FROM @sql_pedidos_status_conferencia; EXECUTE stmt_pedidos_status_conferencia; DEALLOCATE PREPARE stmt_pedidos_status_conferencia;

-- lista_compras.tipo_compra
SET @has_lista := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras');
SET @has_col_lista_tipo_compra := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras' AND COLUMN_NAME = 'tipo_compra');
SET @sql_lista_tipo_compra := IF(@has_lista > 0 AND @has_col_lista_tipo_compra = 0,
    'ALTER TABLE lista_compras ADD COLUMN tipo_compra VARCHAR(16) NULL',
    'SELECT 1'
);
PREPARE stmt_lista_tipo_compra FROM @sql_lista_tipo_compra; EXECUTE stmt_lista_tipo_compra; DEALLOCATE PREPARE stmt_lista_tipo_compra;
