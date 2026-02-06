-- Cria tabela de meta-dados de produtos (importação / compatibilidade)
-- Não altera migrations existentes; execute manualmente no banco.

SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'produto_meta'
);

SET @sql := IF(@exists = 0,
  'CREATE TABLE produto_meta (
      id INT AUTO_INCREMENT PRIMARY KEY,
      produto_id INT NOT NULL,
      meta_key VARCHAR(191) NOT NULL,
      meta_value LONGTEXT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uk_produto_meta (produto_id, meta_key),
      KEY idx_meta_key (meta_key),
      KEY idx_produto_id (produto_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
  'SELECT 1'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
