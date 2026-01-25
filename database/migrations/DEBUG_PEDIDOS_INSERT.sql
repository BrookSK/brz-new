-- Debug para INSERT em pedidos
-- Verificar exatamente quantas colunas a tabela tem

SELECT COUNT(*) as total_colunas FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pedidos';

-- Listar colunas em ordem
SELECT COLUMN_NAME, ORDINAL_POSITION FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pedidos' ORDER BY ORDINAL_POSITION;

-- Testar INSERT manual com valores NULL
INSERT INTO pedidos (
    usuario_id, nome, numero_pedido, cliente_id, status, 
    subtotal, servicos, impostos, frete, desconto, total, 
    moeda, taxa_conversao, endereco_entrega_id, endereco_cobranca_id, 
    observacoes, rastreamento, data_aprovacao, data_envio, data_entrega, 
    created_at, updated_at
) VALUES (
    1, 'Teste', 'TEST123', 1, 'pendente',
    100.00, 10.00, 15.00, 20.00, 0.00, 145.00,
    'USD', 1.0, NULL, NULL,
    'Teste observacao', NULL, NULL, NULL, NULL,
    NOW(), NOW()
);
