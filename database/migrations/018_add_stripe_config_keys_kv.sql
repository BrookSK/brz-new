-- Migration: ensure Stripe config keys exist in key/value schema (configuracoes_sistema)
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
  ('pagamentos_stripe_enabled', '0', 'Stripe ativo', 'boolean'),
  ('pagamentos_stripe_ambiente', 'test', 'Ambiente Stripe (test/live)', 'string'),
  ('pagamentos_stripe_publishable_key', '', 'Stripe Publishable Key (pk_...)', 'string'),
  ('pagamentos_stripe_secret_key', '', 'Stripe Secret Key (sk_...)', 'string'),
  ('pagamentos_stripe_webhook_secret', '', 'Stripe Webhook Signing Secret (whsec_...)', 'string');
