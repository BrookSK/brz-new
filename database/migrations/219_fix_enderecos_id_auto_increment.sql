-- Corrige a coluna enderecos.id que perdeu o atributo AUTO_INCREMENT.
--
-- Sintoma: ao criar um novo endereço (pedido manual, rascunho com cliente, etc.) o INSERT
-- gravava id = 0 e colidia com um registro existente, gerando:
--   SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '0' for key 'PRIMARY'
--
-- Causa: a coluna `id` (INT, PRIMARY KEY) estava sem AUTO_INCREMENT.
--
-- Observações de segurança:
--  - Se existir 1 linha com id = 0 (endereço criado pelo bug), o MySQL a renumera
--    automaticamente para MAX(id)+1 ao aplicar o AUTO_INCREMENT, preservando os dados.
--  - Antes de aplicar esta migração em produção, garanta que NENHUM registro
--    referencia enderecos.id = 0 (ex.: pedidos.endereco_entrega_id = 0). Caso exista,
--    atualize essas referências para o novo id após o ALTER.
--  - Reexecutar este comando é seguro: apenas reafirma o atributo AUTO_INCREMENT.

ALTER TABLE enderecos MODIFY id INT(11) NOT NULL AUTO_INCREMENT;
