-- Adiciona campos para valor informado manualmente pelo cliente (assessoria)
-- valor_informado_cliente: flag indicando que o preço foi digitado pelo cliente
-- observacao_cliente: observação livre do cliente sobre o produto

ALTER TABLE pedido_itens
    ADD COLUMN IF NOT EXISTS valor_informado_cliente TINYINT(1) NOT NULL DEFAULT 0 AFTER free_offer_exempt_tax,
    ADD COLUMN IF NOT EXISTS observacao_cliente TEXT NULL AFTER valor_informado_cliente;

-- Tentar também na tabela alternativa (pedido_items)
ALTER TABLE pedido_items
    ADD COLUMN IF NOT EXISTS valor_informado_cliente TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS observacao_cliente TEXT NULL;
