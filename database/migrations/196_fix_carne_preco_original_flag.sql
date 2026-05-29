-- Inserir flag carne_usou_preco_original para o carnê #66 (pedido #897)
-- que foi criado com a regra de preço cheio mas antes do código salvar a flag automaticamente

INSERT IGNORE INTO pedido_meta (pedido_id, meta_key, meta_value)
VALUES (897, 'carne_usou_preco_original', '1');

-- Corrigir o subtotal do pedido #897 que foi alterado incorretamente para USD
-- O valor atual é ~50.88 (USD), precisa ser multiplicado pela taxa (~5.85)
-- Usar a taxa_conversao do próprio pedido se existir, senão 5.85
UPDATE pedidos 
SET subtotal = ROUND(subtotal * COALESCE(NULLIF(taxa_conversao, 0), 5.85), 2)
WHERE id = 897 
AND moeda = 'BRL' 
AND subtotal > 0 
AND subtotal < 100;
