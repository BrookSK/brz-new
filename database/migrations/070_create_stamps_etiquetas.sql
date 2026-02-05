CREATE TABLE IF NOT EXISTS stamps_etiquetas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  stamps_label_id VARCHAR(80) NULL,
  tracking_number VARCHAR(120) NULL,
  carrier VARCHAR(40) NULL,
  service_type VARCHAR(80) NULL,
  packaging_type VARCHAR(80) NULL,
  label_url TEXT NULL,
  status VARCHAR(30) DEFAULT 'gerada',
  last_request_json LONGTEXT NULL,
  last_response_json LONGTEXT NULL,
  last_http_code INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uniq_stamps_etiquetas_pedido_id (pedido_id),
  KEY idx_stamps_etiquetas_tracking_number (tracking_number)
);
