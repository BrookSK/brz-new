-- Cria tabela de controle para importações idempotentes (usuários/pedidos/produtos)
-- Não altera migrations existentes; execute manualmente no banco.

SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'import_row_status'
);

SET @sql := IF(@exists = 0,
  'CREATE TABLE import_row_status (
      id INT AUTO_INCREMENT PRIMARY KEY,
      import_type VARCHAR(40) NOT NULL,
      row_key VARCHAR(191) NOT NULL,
      status VARCHAR(10) NOT NULL,
      attempts INT NOT NULL DEFAULT 0,
      last_error TEXT NULL,
      ok_at DATETIME NULL,
      fail_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uk_import_row (import_type, row_key),
      KEY idx_import_type_status (import_type, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
  'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
