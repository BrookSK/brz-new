-- Inserir flag carne_usou_preco_original para o carnê #66 (pedido #897)
-- que foi criado com a regra de preço cheio mas antes do código salvar a flag automaticamente

INSERT IGNORE INTO pedido_meta (pedido_id, meta_key, meta_value)
VALUES (897, 'carne_usou_preco_original', '1');
