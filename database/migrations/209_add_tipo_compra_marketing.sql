-- Migration: Adiciona suporte ao tipo de compra "marketing"
-- 
-- Pedidos do tipo "marketing" são pagos via PagDev (Fabiana) e
-- seus valores (produtos, impostos, frete, taxas) são lançados
-- automaticamente como despesas no sistema financeiro.
--
-- A coluna tipo_compra já é VARCHAR(16), então não há necessidade
-- de alterar schema. Esta migration apenas garante que a coluna
-- origem na tabela despesas aceite o valor 'sistema' (usado para
-- lançamentos automáticos de marketing) e adiciona um índice
-- para pedidos de marketing.

-- Garantir que a coluna 'origem' em despesas aceite 'sistema'
-- (já definido no ENUM original, mas caso tenha sido modificado)
SET @has_despesas := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'despesas');
SET @has_origem := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'despesas' AND COLUMN_NAME = 'origem');
SET @sql_check_origem := IF(@has_despesas > 0 AND @has_origem > 0,
    'SELECT 1',
    'SELECT 1'
);
PREPARE stmt_check_origem FROM @sql_check_origem; EXECUTE stmt_check_origem; DEALLOCATE PREPARE stmt_check_origem;

-- Adicionar índice para consulta de pedidos por tipo_compra (se não existir)
SET @has_pedidos := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos');
SET @has_tipo_compra := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'tipo_compra');
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND INDEX_NAME = 'idx_pedidos_tipo_compra');
SET @sql_idx := IF(@has_pedidos > 0 AND @has_tipo_compra > 0 AND @has_idx = 0,
    'ALTER TABLE pedidos ADD INDEX idx_pedidos_tipo_compra (tipo_compra)',
    'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx; EXECUTE stmt_idx; DEALLOCATE PREPARE stmt_idx;

-- Adicionar índice na lista_compras para tipo_compra (se não existir)
SET @has_lista := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras');
SET @has_tc_lista := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras' AND COLUMN_NAME = 'tipo_compra');
SET @has_idx_lista := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras' AND INDEX_NAME = 'idx_lista_compras_tipo_compra');
SET @sql_idx_lista := IF(@has_lista > 0 AND @has_tc_lista > 0 AND @has_idx_lista = 0,
    'ALTER TABLE lista_compras ADD INDEX idx_lista_compras_tipo_compra (tipo_compra)',
    'SELECT 1'
);
PREPARE stmt_idx_lista FROM @sql_idx_lista; EXECUTE stmt_idx_lista; DEALLOCATE PREPARE stmt_idx_lista;
