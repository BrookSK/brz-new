-- VERIFICAR REFERÊNCIAS AO PLACEHOLDER.JPG

-- Verificar na tabela produtos
SELECT id, nome, sku, foto_principal 
FROM produtos 
WHERE foto_principal LIKE '%placeholder.jpg%' 
   OR foto_principal LIKE '%placeholder.png%' 
   OR foto_principal LIKE '%placeholder.svg%';

-- Verificar na tabela produto_fotos
SELECT id, produto_id, nome_arquivo, arquivo_original 
FROM produto_fotos 
WHERE nome_arquivo LIKE '%placeholder.jpg%' 
   OR arquivo_original LIKE '%placeholder.jpg%'
   OR nome_arquivo LIKE '%placeholder.png%'
   OR arquivo_original LIKE '%placeholder.png%'
   OR nome_arquivo LIKE '%placeholder.svg%'
   OR arquivo_original LIKE '%placeholder.svg%';

-- Verificar se há algum registro com via.placeholder.com
SELECT id, nome, sku, foto_principal 
FROM produtos 
WHERE foto_principal LIKE '%via.placeholder.com%';

-- Limpar se encontrar (descomentar as linhas abaixo)
-- UPDATE produtos SET foto_principal = NULL WHERE foto_principal LIKE '%placeholder.jpg%';
-- UPDATE produtos SET foto_principal = NULL WHERE foto_principal LIKE '%placeholder.png%';
-- UPDATE produtos SET foto_principal = NULL WHERE foto_principal LIKE '%placeholder.svg%';
-- DELETE FROM produto_fotos WHERE nome_arquivo LIKE '%placeholder%';
