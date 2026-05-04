CREATE TABLE IF NOT EXISTS carne_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carne_id INT NULL,
    pedido_id INT NULL,
    parcela_id INT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'info',
    mensagem TEXT NOT NULL,
    detalhes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_carne_logs_carne (carne_id),
    INDEX idx_carne_logs_pedido (pedido_id),
    INDEX idx_carne_logs_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
