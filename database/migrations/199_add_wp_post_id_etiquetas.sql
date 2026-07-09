-- Adicionar coluna wp_post_id na tabela correios_packet_etiquetas
-- Usada para vincular ao post do WordPress

ALTER TABLE correios_packet_etiquetas ADD COLUMN IF NOT EXISTS wp_post_id INT NULL DEFAULT NULL;
