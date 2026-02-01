-- Migration 022: Adicionar usuario_login em estoque_movimentacao para auditoria (quem executou)
-- Rode este SQL no banco do servidor.

SET @db := DATABASE();

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'estoque_movimentacao'
      AND COLUMN_NAME = 'usuario_login'
);

SET @sql := IF(
    @exists = 0,
    'ALTER TABLE estoque_movimentacao ADD COLUMN usuario_login VARCHAR(120) NULL AFTER usuario_id',
    'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
