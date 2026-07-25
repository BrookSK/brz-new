-- Coluna para comprovante de compra (imagem/PDF) do item de redirecionamento
ALTER TABLE carrinho_items ADD COLUMN comprovante_url TEXT NULL;
