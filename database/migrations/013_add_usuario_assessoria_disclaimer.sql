-- Add flag/timestamp to persist Assessoria disclaimer acceptance per user

SET @db := DATABASE();

SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'assessoria_disclaimer_aceito_em'
);

SET @sql := IF(
    @exists = 0,
    'ALTER TABLE usuarios ADD COLUMN assessoria_disclaimer_aceito_em DATETIME NULL;',
    'SELECT "Column assessoria_disclaimer_aceito_em already exists";'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
