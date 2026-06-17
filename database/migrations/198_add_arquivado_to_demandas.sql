-- Adicionar coluna 'arquivado' para permitir arquivar demandas no painel
ALTER TABLE demandas ADD COLUMN IF NOT EXISTS arquivado TINYINT(1) NOT NULL DEFAULT 0 AFTER teste_expirado;

-- Index para filtro de arquivados
CREATE INDEX IF NOT EXISTS idx_arquivado ON demandas (arquivado);
