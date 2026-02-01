-- Migracao: tornar pedidos.status configuravel (ENUM -> VARCHAR)
-- Motivo: ENUM pode gravar '' (vazio) quando recebe valor nao permitido, impedindo novos status.

ALTER TABLE pedidos
  MODIFY COLUMN `status` VARCHAR(50) NULL;
