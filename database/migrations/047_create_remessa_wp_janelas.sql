CREATE TABLE IF NOT EXISTS remessa_wp_janelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(10) NOT NULL DEFAULT 'br',
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'aberta',
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_remessa_wp_janelas_periodo (source, data_inicio, data_fim),
    INDEX idx_remessa_wp_janelas_periodo (source, data_inicio, data_fim),
    INDEX idx_remessa_wp_janelas_status (source, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remessa_wp_janela_pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    janela_id INT NOT NULL,
    source VARCHAR(10) NOT NULL DEFAULT 'br',
    order_id BIGINT NOT NULL,
    etiqueta_gerada TINYINT(1) NOT NULL DEFAULT 0,
    etiqueta_gerada_em DATETIME NULL,
    wexpress_shipping_id VARCHAR(120) NULL,
    wexpress_tracking_number VARCHAR(120) NULL,
    courier_tracking_number VARCHAR(120) NULL,
    wexpress_status VARCHAR(50) NULL,
    wexpress_label_url TEXT NULL,
    wexpress_last_request_json LONGTEXT NULL,
    wexpress_last_response_json LONGTEXT NULL,
    wexpress_last_http_code INT NULL,
    wexpress_updated_at DATETIME NULL,
    recebido_confirmado TINYINT(1) NOT NULL DEFAULT 0,
    recebido_confirmado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remessa_wp_janela_order (janela_id, order_id),
    INDEX idx_rwp_janela (janela_id),
    INDEX idx_rwp_order (order_id),
    INDEX idx_rwp_source (source),
    CONSTRAINT fk_rwp_janela FOREIGN KEY (janela_id) REFERENCES remessa_wp_janelas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
