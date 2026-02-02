-- Cria tabela de documentos de pagamento (comprovante) para pedidos manuais/offline

CREATE TABLE IF NOT EXISTS pedidos_pagamento_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    metodo VARCHAR(60) NOT NULL,
    status ENUM('pendente_upload','ok') NOT NULL DEFAULT 'pendente_upload',
    arquivo_path VARCHAR(500) NULL,
    mime VARCHAR(120) NULL,
    uploaded_at TIMESTAMP NULL,
    usuario_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ppd_pedido (pedido_id),
    INDEX idx_ppd_status (status),
    INDEX idx_ppd_metodo (metodo)
);
