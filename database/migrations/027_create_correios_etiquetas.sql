CREATE TABLE IF NOT EXISTS correios_etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    codigo_etiqueta VARCHAR(100) NOT NULL,
    dados_remetente JSON NULL,
    dados_destinatario JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'gerada',
    data_impressao DATETIME NULL,
    data_postagem DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_correios_codigo (codigo_etiqueta),
    INDEX idx_correios_pedido (pedido_id),
    INDEX idx_correios_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
