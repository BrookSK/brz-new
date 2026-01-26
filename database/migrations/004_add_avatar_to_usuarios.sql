-- Migration: add avatar column to usuarios
-- Idempotent for MySQL/MariaDB using INFORMATION_SCHEMA

SET @db := DATABASE();
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'avatar'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN avatar VARCHAR(255) NULL',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
