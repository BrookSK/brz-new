-- Migration 087: soft delete (lixeira) para pedidos
-- Importante: esta migration é idempotente.

SET @has_pedidos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pedidos');

SET @has_deleted_at := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'deleted_at');
SET @sql1 := IF(@has_pedidos > 0 AND @has_deleted_at = 0,
  'ALTER TABLE `pedidos` ADD COLUMN `deleted_at` DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @has_deleted_by := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'deleted_by');
SET @sql2 := IF(@has_pedidos > 0 AND @has_deleted_by = 0,
  'ALTER TABLE `pedidos` ADD COLUMN `deleted_by` INT NULL',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND index_name = 'idx_pedidos_deleted_at');
SET @sql3 := IF(@has_pedidos > 0 AND @has_idx = 0,
  'ALTER TABLE `pedidos` ADD INDEX `idx_pedidos_deleted_at` (`deleted_at`)',
  'SELECT 1'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;
