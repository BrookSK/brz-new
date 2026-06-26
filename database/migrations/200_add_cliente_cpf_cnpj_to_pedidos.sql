-- Adiciona coluna cliente_cpf_cnpj na tabela pedidos para permitir edição de CPF por pedido
-- sem afetar o cadastro global do cliente (tabela usuarios).

SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pedidos');
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'cliente_cpf_cnpj');

SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE pedidos ADD COLUMN cliente_cpf_cnpj VARCHAR(20) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Preencher com o CPF do usuario vinculado (backfill dos pedidos existentes)
SET @col_usuario_id := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'usuario_id');
SET @col_cpf_usuarios := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'cpf_cnpj');

SET @sql2 := IF(@table_exists = 1 AND @col_exists = 0 AND @col_usuario_id = 1 AND @col_cpf_usuarios = 1,
  'UPDATE pedidos p INNER JOIN usuarios u ON u.id = p.usuario_id SET p.cliente_cpf_cnpj = u.cpf_cnpj WHERE p.cliente_cpf_cnpj IS NULL AND u.cpf_cnpj IS NOT NULL AND u.cpf_cnpj != \'\'',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
