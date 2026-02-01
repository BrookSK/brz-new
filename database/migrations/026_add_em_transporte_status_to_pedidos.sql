-- Migration: add 'em_transporte' to pedidos.status ENUM (safe/dynamic)

SET @db := DATABASE();

-- Build a dynamic statement:
-- - if column doesn't exist or value already present => run harmless SELECT 1
-- - else => ALTER TABLE to extend the ENUM
SET @sql := (
  SELECT
    CASE
      WHEN COLUMN_TYPE IS NULL THEN 'SELECT 1'
      WHEN LOCATE("'em_transporte'", COLUMN_TYPE) > 0 THEN 'SELECT 1'
      ELSE CONCAT(
        'ALTER TABLE pedidos MODIFY COLUMN status ENUM(',
        SUBSTRING(COLUMN_TYPE, 6, CHAR_LENGTH(COLUMN_TYPE) - 6),
        ",'em_transporte'",
        ')'
      )
    END
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'pedidos'
    AND COLUMN_NAME = 'status'
  LIMIT 1
);

-- In case information_schema returns no row at all
SET @sql := COALESCE(@sql, 'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cleanup
SET @db := NULL;
SET @sql := NULL;
