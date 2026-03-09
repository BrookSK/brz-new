-- Tabela para suportar split payment (produto vs taxa_servico) com múltiplas cobranças por pedido

CREATE TABLE IF NOT EXISTS pedido_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    componente VARCHAR(30) NOT NULL,
    gateway VARCHAR(30) NOT NULL,
    metodo VARCHAR(30) DEFAULT NULL,
    moeda VARCHAR(3) NOT NULL DEFAULT 'BRL',
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_id VARCHAR(255) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    gateway_status VARCHAR(80) DEFAULT NULL,
    invoice_url TEXT NULL,
    bank_slip_url TEXT NULL,
    digitable_line TEXT NULL,
    pix_encoded_image LONGTEXT NULL,
    pix_payload LONGTEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pp_pedido (pedido_id),
    INDEX idx_pp_pedido_comp (pedido_id, componente),
    UNIQUE KEY uk_pp_gateway_payment (gateway, payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
