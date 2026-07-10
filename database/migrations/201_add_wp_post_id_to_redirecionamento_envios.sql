-- Adicionar coluna wp_post_id_etiqueta na tabela redirecionamento_envios
-- Para vincular o envio ao pacote criado no WordPress Etiquetas (Correios PACKET)

ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS wp_post_id_etiqueta INT NULL DEFAULT NULL COMMENT 'ID do post (package) no WordPress Etiquetas';
ALTER TABLE redirecionamento_envios ADD INDEX IF NOT EXISTS idx_redir_envios_wp_post_id (wp_post_id_etiqueta);
