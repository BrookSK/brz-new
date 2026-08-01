-- Correção para pedidos existentes que foram criados antes do fix de redirecionamento
-- 1. Remover itens de pacote da lista_compras (não precisam ser comprados)
DELETE FROM lista_compras WHERE produto_id >= 999990;

-- 2. Atualizar pedido_itens: preencher nome, ncm, foto_url, declaration_value a partir de pacotes_recebidos
-- Para itens com produto_id >= 999990 que estão com dados vazios
UPDATE pedido_itens pi
INNER JOIN pacotes_recebidos pr ON pr.id = pi.pacote_id
SET 
    pi.nome = COALESCE(NULLIF(pi.nome, ''), pr.nome),
    pi.ncm = COALESCE(NULLIF(pi.ncm, ''), pr.ncm),
    pi.foto_url = COALESCE(NULLIF(pi.foto_url, ''), pr.foto_url),
    pi.peso_kg = COALESCE(NULLIF(pi.peso_kg, 0), pr.peso_kg),
    pi.nome_item = COALESCE(NULLIF(pi.nome_item, ''), pr.nome),
    pi.tipo_item = 'pacote_redirecionamento'
WHERE pi.produto_id >= 999990 
  AND pi.pacote_id IS NOT NULL 
  AND pi.pacote_id > 0;

-- 3. Preencher declaration_value a partir do carrinho_items (se ainda existir)
UPDATE pedido_itens pi
INNER JOIN carrinho_items ci ON ci.pacote_id = pi.pacote_id AND ci.tipo_item = 'pacote_redirecionamento'
SET pi.declaration_value = ci.declaration_value
WHERE pi.produto_id >= 999990 
  AND (pi.declaration_value IS NULL OR pi.declaration_value = 0)
  AND ci.declaration_value > 0;

-- 4. Preencher comprovante_url a partir do carrinho_items
UPDATE pedido_itens pi
INNER JOIN carrinho_items ci ON ci.pacote_id = pi.pacote_id AND ci.tipo_item = 'pacote_redirecionamento'
SET pi.comprovante_url = ci.comprovante_url
WHERE pi.produto_id >= 999990 
  AND (pi.comprovante_url IS NULL OR pi.comprovante_url = '')
  AND ci.comprovante_url IS NOT NULL AND ci.comprovante_url != '';
