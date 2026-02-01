CREATE TABLE IF NOT EXISTS remessa_janelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'aberta',
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_remessa_janelas_periodo (data_inicio, data_fim),
    INDEX idx_remessa_janelas_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remessa_janela_pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    janela_id INT NOT NULL,
    pedido_id INT NOT NULL,
    etiqueta_gerada TINYINT(1) NOT NULL DEFAULT 0,
    etiqueta_gerada_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_janela_pedido (janela_id, pedido_id),
    INDEX idx_rjp_janela (janela_id),
    INDEX idx_rjp_pedido (pedido_id),
    CONSTRAINT fk_rjp_janela FOREIGN KEY (janela_id) REFERENCES remessa_janelas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
