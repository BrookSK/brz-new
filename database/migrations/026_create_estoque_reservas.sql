CREATE TABLE IF NOT EXISTS estoque_reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    pedido_id INT NOT NULL,
    quantidade_reservada INT NOT NULL DEFAULT 0,
    status ENUM('ativa','liberada','consumida') NOT NULL DEFAULT 'ativa',
    origem VARCHAR(50) NULL,
    observacao VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_er_produto (produto_id),
    INDEX idx_er_pedido (pedido_id),
    INDEX idx_er_status (status),
    UNIQUE KEY uniq_er_pedido_produto_status (pedido_id, produto_id, status)
);
