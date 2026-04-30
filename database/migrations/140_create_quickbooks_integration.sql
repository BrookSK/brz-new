-- Migration 140: QuickBooks Online Integration
-- Cria tabela de tokens OAuth2 e adiciona chaves de configuração

-- Tabela para armazenar tokens OAuth2 do QuickBooks
CREATE TABLE IF NOT EXISTS quickbooks_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    realm_id VARCHAR(50) NOT NULL COMMENT 'Company ID do QuickBooks (realmId)',
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    access_token_expires_at DATETIME NOT NULL,
    refresh_token_expires_at DATETIME NOT NULL,
    ambiente ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_realm_id (realm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de log de sincronizações com QuickBooks
CREATE TABLE IF NOT EXISTS quickbooks_sync_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entidade VARCHAR(50) NOT NULL COMMENT 'invoice, payment, customer, etc.',
    entidade_id INT UNSIGNED NULL COMMENT 'ID local (pedido_id, etc.)',
    qb_id VARCHAR(50) NULL COMMENT 'ID no QuickBooks',
    acao VARCHAR(20) NOT NULL COMMENT 'create, update, read',
    status ENUM('success','error') NOT NULL DEFAULT 'success',
    payload JSON NULL COMMENT 'Dados enviados/recebidos',
    erro TEXT NULL,
    usuario_id INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entidade (entidade, entidade_id),
    INDEX idx_qb_id (qb_id),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de mapeamento pedido <-> QuickBooks Invoice
CREATE TABLE IF NOT EXISTS quickbooks_pedido_map (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT UNSIGNED NOT NULL,
    qb_invoice_id VARCHAR(50) NULL COMMENT 'ID da Invoice no QuickBooks',
    qb_invoice_doc_number VARCHAR(50) NULL,
    qb_customer_id VARCHAR(50) NULL COMMENT 'ID do Customer no QuickBooks',
    qb_payment_id VARCHAR(50) NULL COMMENT 'ID do Payment no QuickBooks',
    sincronizado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pedido_id (pedido_id),
    INDEX idx_qb_invoice_id (qb_invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir chaves de configuração do QuickBooks (se não existirem)
INSERT IGNORE INTO configuracoes_sistema (chave, valor, descricao, tipo) VALUES
('qb_client_id',      '',         'QuickBooks Client ID',                    'string'),
('qb_client_secret',  '',         'QuickBooks Client Secret',                'string'),
('qb_redirect_uri',   '',         'QuickBooks OAuth2 Redirect URI',          'string'),
('qb_ambiente',       'sandbox',  'QuickBooks ambiente (sandbox/production)', 'string'),
('qb_ativo',          '0',        'QuickBooks integração ativa',             'boolean'),
('qb_verifier_token', '',         'QuickBooks Webhook Verifier Token',       'string');
