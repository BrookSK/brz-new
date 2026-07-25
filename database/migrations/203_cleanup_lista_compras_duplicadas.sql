-- Limpar pendências indevidas na lista_compras:
-- 1. Remove registros com status 'pendente' onde já existe um registro 'comprado' para o mesmo produto/pedido

DELETE lc FROM lista_compras lc
INNER JOIN lista_compras lc2 
    ON lc2.pedido_id = lc.pedido_id 
    AND lc2.produto_id = lc.produto_id 
    AND lc2.status = 'comprado'
WHERE lc.status = 'pendente';

-- 2. Marcar como comprado itens pendentes de pedidos que já avançaram além do status de compra

UPDATE lista_compras lc
INNER JOIN pedidos p ON p.id = lc.pedido_id
SET lc.status = 'comprado', lc.quantidade_faltante = 0
WHERE lc.status = 'pendente'
AND LOWER(p.status) IN ('itens_comprados', 'produto_consolidado', 'etiqueta_gerada', 'em_transporte', 'aguardando_liberacao_aduaneira', 'enviado_ao_destinatario', 'enviado', 'entregue');
