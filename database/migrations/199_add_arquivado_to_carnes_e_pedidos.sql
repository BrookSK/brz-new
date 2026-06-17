-- Adicionar coluna 'arquivado' em carnes para ocultar cancelados automaticamente
ALTER TABLE carnes ADD COLUMN IF NOT EXISTS arquivado TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_carnes_arquivado ON carnes (arquivado);

-- Adicionar coluna 'arquivado' em pedidos para ocultar pedidos de carnês cancelados
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS arquivado TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_pedidos_arquivado ON pedidos (arquivado);
