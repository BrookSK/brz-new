-- Faturas adicionais - cobranças extras vinculadas a pedidos existentes
-- Ex: taxa adicional, produto faltante, ajuste de peso, etc.

CREATE TABLE IF NOT EXISTS pedido_faturas_adicionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao TEXT NULL,
    status ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
    pago_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
