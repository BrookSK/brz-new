-- Configurações de demandas
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES
('demandas_senha_painel', ''),
('demandas_emails_notificacao', ''),
('demandas_webhook_url', ''),
('demandas_usuarios_notificacao', '');

-- Tabela de notificações push para o admin
CREATE TABLE IF NOT EXISTS admin_notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'demanda',
    titulo VARCHAR(500) NOT NULL,
    mensagem TEXT NULL,
    link VARCHAR(1000) NULL,
    lida TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_lida (usuario_id, lida),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
