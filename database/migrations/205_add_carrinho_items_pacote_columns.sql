-- Novas colunas em carrinho_items para suportar pacotes de redirecionamento e faturas adicionais
-- tipo_item: 'produto' (padrão), 'pacote_redirecionamento', 'fatura_adicional'
-- declaration_value: valor declarado em USD (obrigatório para pacotes antes do checkout)
-- pacote_id: referência ao pacote recebido
-- fatura_adicional_id: referência à fatura adicional

ALTER TABLE carrinho_items ADD COLUMN declaration_value DECIMAL(10,2) NULL;
ALTER TABLE carrinho_items ADD COLUMN tipo_item VARCHAR(30) NOT NULL DEFAULT 'produto';
ALTER TABLE carrinho_items ADD COLUMN pacote_id INT NULL;
ALTER TABLE carrinho_items ADD COLUMN fatura_adicional_id INT NULL;
ALTER TABLE carrinho_items ADD COLUMN nome_item VARCHAR(255) NULL;
ALTER TABLE carrinho_items ADD COLUMN peso_kg DECIMAL(6,3) NULL;
ALTER TABLE carrinho_items ADD COLUMN foto_url TEXT NULL;
