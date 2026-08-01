-- Correção para pedidos existentes que foram criados antes do fix de redirecionamento
-- NOTA: Este SQL já foi executado manualmente. Mantido aqui apenas como referência/documentação.
-- Não precisa rodar em produção se já rodou.

-- 1. Remover itens de pacote da lista_compras (não precisam ser comprados)
-- DELETE FROM lista_compras WHERE produto_id >= 999990;

-- 2. Atualizar pedido_itens: preencher dados a partir de pacotes_recebidos
-- UPDATE pedido_itens pi
-- INNER JOIN pacotes_recebidos pr ON pr.id = pi.pacote_id
-- SET 
--     pi.nome_produto = COALESCE(NULLIF(pi.nome_produto, ''), pr.nome),
--     pi.ncm = COALESCE(NULLIF(pi.ncm, ''), pr.ncm),
--     pi.produto_ncm = COALESCE(NULLIF(pi.produto_ncm, ''), pr.ncm),
--     pi.foto_url = COALESCE(NULLIF(pi.foto_url, ''), pr.foto_url),
--     pi.peso_manual = COALESCE(NULLIF(pi.peso_manual, 0), pr.peso_kg),
--     pi.nome_item = COALESCE(NULLIF(pi.nome_item, ''), pr.nome),
--     pi.tipo_item = 'pacote_redirecionamento'
-- WHERE pi.produto_id >= 999990 
--   AND pi.pacote_id IS NOT NULL 
--   AND pi.pacote_id > 0;

