-- Adicionar coluna venda_sob_demanda na tabela produtos
-- Permite deixar o produto comprável no site sem consumir/afetar o inventário físico (estoque_interno).
-- Quando ativo (1):
--   - O produto fica comprável na loja mesmo com stock = 0 (não precisa inflar o campo Estoque).
--   - Ao pagar, a quantidade NÃO baixa do estoque_interno: vai integral para lista_compras (pendência de compra).

ALTER TABLE produtos ADD COLUMN IF NOT EXISTS venda_sob_demanda TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se 1, produto é vendido sob demanda: comprável no site sem estoque físico; compra vira pendência em lista_compras';
ALTER TABLE produtos ADD INDEX IF NOT EXISTS idx_produtos_venda_sob_demanda (venda_sob_demanda);
