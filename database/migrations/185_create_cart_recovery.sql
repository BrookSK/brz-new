-- ============================================================
-- RECUPERAÇÃO DE CARRINHO
-- ============================================================

CREATE TABLE IF NOT EXISTS cart_recovery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    carrinho_id INT DEFAULT NULL,
    status ENUM('abandonado','em_atendimento','recuperado','perdido','nao_retornou') DEFAULT 'abandonado',
    atendido_por INT DEFAULT NULL,
    pedido_recuperado_id INT DEFAULT NULL,
    valor_carrinho DECIMAL(10,2) DEFAULT 0,
    itens_carrinho INT DEFAULT 0,
    pagina_abandono VARCHAR(255) DEFAULT NULL,
    detectado_em DATETIME NOT NULL,
    atendido_em DATETIME DEFAULT NULL,
    recuperado_em DATETIME DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_status (status),
    INDEX idx_atendido_por (atendido_por),
    INDEX idx_detectado (detectado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
