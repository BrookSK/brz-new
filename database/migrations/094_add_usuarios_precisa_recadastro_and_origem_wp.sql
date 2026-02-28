-- Adiciona colunas para migração de usuários vindos do WordPress e obrigatoriedade de recadastro

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'precisa_recadastro'
);
SET @sql := IF(@col_exists = 0, "ALTER TABLE usuarios ADD COLUMN precisa_recadastro TINYINT(1) NOT NULL DEFAULT 0;", 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'wp_origem'
);
SET @sql := IF(@col_exists = 0, "ALTER TABLE usuarios ADD COLUMN wp_origem VARCHAR(10) NULL;", 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'wp_user_id'
);
SET @sql := IF(@col_exists = 0, "ALTER TABLE usuarios ADD COLUMN wp_user_id INT NULL;", 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND INDEX_NAME = 'idx_usuarios_wp_user_id'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE usuarios ADD INDEX idx_usuarios_wp_user_id (wp_user_id);', 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
