-- Adicionar coluna outlet na tabela produtos
-- Permite marcar produtos para exibição na página Braziliana Outlet

ALTER TABLE produtos ADD COLUMN IF NOT EXISTS outlet TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se 1, produto aparece na página Braziliana Outlet';
ALTER TABLE produtos ADD INDEX IF NOT EXISTS idx_produtos_outlet (outlet);
