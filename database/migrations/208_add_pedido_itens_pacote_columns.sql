-- Colunas extras na tabela de itens do pedido para suportar pacotes de redirecionamento
ALTER TABLE pedido_itens ADD COLUMN declaration_value DECIMAL(10,2) NULL;
ALTER TABLE pedido_itens ADD COLUMN tipo_item VARCHAR(30) DEFAULT 'produto';
ALTER TABLE pedido_itens ADD COLUMN pacote_id INT NULL;
ALTER TABLE pedido_itens ADD COLUMN foto_url TEXT NULL;
ALTER TABLE pedido_itens ADD COLUMN comprovante_url TEXT NULL;
ALTER TABLE pedido_itens ADD COLUMN ncm VARCHAR(20) NULL;
ALTER TABLE pedido_itens ADD COLUMN nome_item VARCHAR(255) NULL;
