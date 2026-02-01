-- Adiciona FKs e índices adicionais para compras_documentos

-- FK loja_id -> lojas(id)
SET @fk1 := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras_documentos' AND COLUMN_NAME = 'loja_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @sql1 := IF(@fk1 IS NULL, 'ALTER TABLE compras_documentos ADD CONSTRAINT fk_compras_documentos_loja FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- FK usuario_id -> usuarios(id)
SET @fk2 := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras_documentos' AND COLUMN_NAME = 'usuario_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @sql2 := IF(@fk2 IS NULL, 'ALTER TABLE compras_documentos ADD CONSTRAINT fk_compras_documentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
