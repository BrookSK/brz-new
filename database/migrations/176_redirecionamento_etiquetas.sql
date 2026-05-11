-- Migration: Adicionar suporte a etiquetas no redirecionamento

-- Configuração do provedor de etiqueta para redirecionamento
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES 
('redirecionamento_provedor_etiqueta', 'wexpress');

-- Campos na tabela redirecionamento_envios para etiqueta
-- (também adicionados via migrarColunas no controller, mas aqui para referência)
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS etiqueta_provedor VARCHAR(30) NULL COMMENT 'wexpress ou correios';
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS wexpress_shipping_id VARCHAR(100) NULL;
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS wexpress_tracking_number VARCHAR(100) NULL;
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS courier_tracking_number VARCHAR(100) NULL;
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS wexpress_status VARCHAR(50) NULL;
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS wexpress_label_url TEXT NULL;
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS etiqueta_request_json LONGTEXT NULL;
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS etiqueta_response_json LONGTEXT NULL;
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS etiqueta_gerada_em DATETIME NULL;
ALTER TABLE redirecionamento_envios ADD COLUMN IF NOT EXISTS etiqueta_gerada_por INT NULL COMMENT 'usuario_id que gerou';
