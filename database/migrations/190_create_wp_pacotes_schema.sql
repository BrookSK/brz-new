-- Módulo Pacotes WordPress: cache local dos pacotes puxados do WordPress + containers + faturas CN38
-- Criado em 2026-05-19

CREATE TABLE IF NOT EXISTS wp_packet_etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origem VARCHAR(5) NOT NULL DEFAULT 'br' COMMENT 'br, red ou us',
    wp_post_id INT NOT NULL COMMENT 'ID do post (package) no WordPress',
    pedido_id INT NULL COMMENT 'ID do pedido no WooCommerce (order_id)',
    cliente_nome VARCHAR(200) NULL,
    tracking_number VARCHAR(120) NULL,
    container_id INT NULL COMMENT 'FK para wp_packet_containers.id (local)',
    wp_container_post_id INT NULL COMMENT 'ID do container no WordPress',
    label_pdf_url TEXT NULL COMMENT 'URL do PDF da etiqueta no WP (se disponível)',
    meta_json LONGTEXT NULL COMMENT 'JSON com todos os postmeta relevantes',
    synced_at TIMESTAMP NULL COMMENT 'Última sincronização com WP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_wp_packet_etiquetas_origem_wp (origem, wp_post_id),
    KEY idx_wp_packet_etiquetas_pedido (pedido_id),
    KEY idx_wp_packet_etiquetas_tracking (tracking_number),
    KEY idx_wp_packet_etiquetas_container (container_id),
    KEY idx_wp_packet_etiquetas_origem (origem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wp_packet_containers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NULL COMMENT 'Nome/descrição do container',
    dispatch_number INT NULL,
    unit_code VARCHAR(120) NULL,
    tracking_numbers_json LONGTEXT NULL COMMENT 'JSON array dos tracking numbers',
    status VARCHAR(30) DEFAULT 'created',
    bill_id INT NULL COMMENT 'FK para wp_packet_bills.id',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_wp_packet_containers_status (status),
    KEY idx_wp_packet_containers_bill (bill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wp_packet_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cn38_code VARCHAR(120) NULL,
    status VARCHAR(30) DEFAULT 'pending',
    containers_json LONGTEXT NULL COMMENT 'JSON array dos container IDs',
    tracking_count INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_wp_packet_bills_status (status),
    KEY idx_wp_packet_bills_cn38 (cn38_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
