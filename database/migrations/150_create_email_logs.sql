-- Migration: Criar tabela email_logs para registrar todos os emails enviados pelo sistema
CREATE TABLE IF NOT EXISTS email_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(100) NOT NULL COMMENT 'Tipo do email (cobranca, notificacao, carne_criado, etc)',
    destinatario_email VARCHAR(255) NOT NULL,
    destinatario_nome VARCHAR(255) NULL,
    assunto VARCHAR(500) NOT NULL,
    corpo_resumo TEXT NULL COMMENT 'Resumo ou preview do corpo do email',
    status ENUM('enviado','erro') NOT NULL DEFAULT 'enviado',
    erro_mensagem TEXT NULL,
    carne_id INT UNSIGNED NULL,
    parcela_id INT UNSIGNED NULL,
    pedido_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_logs_tipo (tipo),
    INDEX idx_email_logs_status (status),
    INDEX idx_email_logs_destinatario (destinatario_email),
    INDEX idx_email_logs_carne (carne_id),
    INDEX idx_email_logs_pedido (pedido_id),
    INDEX idx_email_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
