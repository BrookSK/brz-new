-- garanta que está no banco certo
-- USE sua_base;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM transacoes_carteira WHERE usuario_id NOT IN (4,5,6,7,8,9);
DELETE FROM carteiras WHERE usuario_id NOT IN (4,5,6,7,8,9);
DELETE FROM enderecos WHERE usuario_id NOT IN (4,5,6,7,8,9);
DELETE FROM carrinhos WHERE usuario_id NOT IN (4,5,6,7,8,9);

-- pedidos (e itens) podem existir, então apaga itens primeiro
DELETE pi FROM pedido_items pi
JOIN pedidos p ON p.id = pi.pedido_id
WHERE p.usuario_id NOT IN (4,5,6,7,8,9);

DELETE FROM pedidos WHERE usuario_id NOT IN (4,5,6,7,8,9);

-- por último, usuários
DELETE FROM usuarios WHERE id NOT IN (4,5,6,7,8,9);

SET FOREIGN_KEY_CHECKS = 1;