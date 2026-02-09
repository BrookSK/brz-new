-- Cria tabela de etiquetas ShipStation (UPS) - remessa exterior
-- Não altera migrations existentes; execute manualmente no banco.

SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipstation_etiquetas'
);

SET @sql := IF(@exists = 0,
  'CREATE TABLE shipstation_etiquetas (\n      id INT AUTO_INCREMENT PRIMARY KEY,\n      pedido_id INT NOT NULL,\n      shipstation_shipment_id VARCHAR(80) NULL,\n      shipstation_label_id VARCHAR(80) NULL,\n      tracking_number VARCHAR(120) NULL,\n      carrier_code VARCHAR(40) NULL,\n      service_code VARCHAR(80) NULL,\n      package_code VARCHAR(80) NULL,\n      label_url TEXT NULL,\n      status VARCHAR(30) DEFAULT \'gerada\',\n      last_request_json LONGTEXT NULL,\n      last_response_json LONGTEXT NULL,\n      last_http_code INT NULL,\n      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n      updated_at TIMESTAMP NULL DEFAULT NULL,\n      UNIQUE KEY uniq_shipstation_etiquetas_pedido_id (pedido_id),\n      KEY idx_shipstation_etiquetas_tracking_number (tracking_number)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
  'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
