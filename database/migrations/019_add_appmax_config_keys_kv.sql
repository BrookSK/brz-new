-- Migration: ensure AppMax config keys exist in key/value schema (configuracoes_sistema)
-- Safe/idempotent: creates table if needed and INSERT IGNORE default keys.

CREATE TABLE IF NOT EXISTS configuracoes_sistema (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(255) NOT NULL UNIQUE,
  valor TEXT NULL,
  descricao TEXT NULL,
  tipo ENUM('string','number','boolean','json') DEFAULT 'string',
  atualizado_por INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO configuracoes_sistema (chave, valor, descricao, tipo) VALUES
  ('pagamentos_appmax_enabled', '0', 'AppMax ativo', 'boolean'),
  ('pagamentos_appmax_ambiente', 'production', 'Ambiente AppMax (production/homolog)', 'string'),
  ('pagamentos_appmax_base_url', '', 'Base URL AppMax API v3 (opcional)', 'string'),
  ('pagamentos_appmax_access_token', '', 'AppMax access-token (API v3)', 'string'),
  ('pagamentos_appmax_client_id', '', 'AppMax OAuth Client ID (legado)', 'string'),
  ('pagamentos_appmax_client_secret', '', 'AppMax OAuth Client Secret (legado)', 'string'),
  ('pagamentos_appmax_app_id', '', 'AppMax App ID (legado)', 'string');
