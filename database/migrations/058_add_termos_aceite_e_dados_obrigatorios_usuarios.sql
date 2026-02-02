-- Adiciona colunas para aceite de termos e dados obrigatórios do usuário

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'data_nascimento'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE usuarios ADD COLUMN data_nascimento DATE NULL;', 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'pais_residencia'
);
SET @sql := IF(@col_exists = 0, "ALTER TABLE usuarios ADD COLUMN pais_residencia VARCHAR(2) NULL DEFAULT 'BR';", 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'termos_aceitos_em'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE usuarios ADD COLUMN termos_aceitos_em DATETIME NULL;', 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'termos_aceitos_ip'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE usuarios ADD COLUMN termos_aceitos_ip VARCHAR(45) NULL;', 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'termos_versao'
);
SET @sql := IF(@col_exists = 0, "ALTER TABLE usuarios ADD COLUMN termos_versao VARCHAR(20) NULL DEFAULT '1.0';", 'SELECT 1;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
