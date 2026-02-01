-- Adiciona FK lista_compras.documento_compra_id -> compras_documentos(id)

SET @col1 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras' AND COLUMN_NAME = 'documento_compra_id');
SET @col2 := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras_documentos');

SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras' AND COLUMN_NAME = 'documento_compra_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);

SET @sql := IF(@col1 > 0 AND @col2 > 0 AND @fk IS NULL,
    'ALTER TABLE lista_compras ADD CONSTRAINT fk_lista_compras_documento FOREIGN KEY (documento_compra_id) REFERENCES compras_documentos(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
