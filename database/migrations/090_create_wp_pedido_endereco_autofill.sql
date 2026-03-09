-- Tabela local para armazenar campos auto-preenchidos (somente interno)

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'wp_pedido_endereco_autofill'
);

SET @sql := IF(
  @table_exists = 0,
  'CREATE TABLE wp_pedido_endereco_autofill (
      id INT AUTO_INCREMENT PRIMARY KEY,
      source VARCHAR(10) NOT NULL,
      wp_order_id BIGINT NOT NULL,
      field_name VARCHAR(32) NOT NULL,
      old_value VARCHAR(255) NULL,
      new_value VARCHAR(255) NULL,
      cep VARCHAR(20) NULL,
      wp_created_at DATETIME NULL,
      wp_status VARCHAR(50) NULL,
      request_json LONGTEXT NULL,
      response_json LONGTEXT NULL,
      error VARCHAR(800) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_source_order_field (source, wp_order_id, field_name),
      KEY idx_source_field (source, field_name),
      KEY idx_wp_created_at (wp_created_at),
      KEY idx_wp_status (wp_status)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
