-- Adiciona FKs e constraints para estoque_reservas (sem alterar migrations anteriores)
-- Ajuste manual: caso seu banco não tenha tabelas/ids compatíveis, rode os blocos necessários.

-- FK produto_id -> produtos(id)
SET @fk1 := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_reservas' AND COLUMN_NAME = 'produto_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @sql1 := IF(@fk1 IS NULL, 'ALTER TABLE estoque_reservas ADD CONSTRAINT fk_estoque_reservas_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- FK pedido_id -> pedidos(id)
SET @fk2 := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_reservas' AND COLUMN_NAME = 'pedido_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @sql2 := IF(@fk2 IS NULL, 'ALTER TABLE estoque_reservas ADD CONSTRAINT fk_estoque_reservas_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
