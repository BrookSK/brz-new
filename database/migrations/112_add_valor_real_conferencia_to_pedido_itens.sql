-- Valor real conferido pelo admin na tela de conferência
ALTER TABLE pedido_itens
    ADD COLUMN IF NOT EXISTS valor_real_conferencia DECIMAL(10,2) NULL AFTER observacao_cliente,
    ADD COLUMN IF NOT EXISTS conferido_em DATETIME NULL AFTER valor_real_conferencia,
    ADD COLUMN IF NOT EXISTS conferido_por INT NULL AFTER conferido_em;

ALTER TABLE pedido_items
    ADD COLUMN IF NOT EXISTS valor_real_conferencia DECIMAL(10,2) NULL,
    ADD COLUMN IF NOT EXISTS conferido_em DATETIME NULL,
    ADD COLUMN IF NOT EXISTS conferido_por INT NULL;
