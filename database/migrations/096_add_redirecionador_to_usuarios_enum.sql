SET @tbl_usuarios_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
);

SET @role_col_type := (
    SELECT COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'role'
    LIMIT 1
);

SET @role_has_redir := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'role'
      AND COLUMN_TYPE LIKE '%\'redirecionador\'%'
);

SET @sql_role := IF(
    @tbl_usuarios_exists > 0 AND @role_col_type IS NOT NULL AND @role_has_redir = 0 AND @role_col_type LIKE 'enum(%',
    CONCAT(
        'ALTER TABLE usuarios MODIFY COLUMN role ',
        REPLACE(@role_col_type, ')', ',\'redirecionador\')'),
        ' NULL DEFAULT \'cliente\''
    ),
    'SELECT 1'
);

PREPARE stmt_role FROM @sql_role;
EXECUTE stmt_role;
DEALLOCATE PREPARE stmt_role;

SET @perfil_col_type := (
    SELECT COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'perfil'
    LIMIT 1
);

SET @perfil_has_redir := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'perfil'
      AND COLUMN_TYPE LIKE '%\'redirecionador\'%'
);

SET @sql_perfil := IF(
    @tbl_usuarios_exists > 0 AND @perfil_col_type IS NOT NULL AND @perfil_has_redir = 0 AND @perfil_col_type LIKE 'enum(%',
    CONCAT(
        'ALTER TABLE usuarios MODIFY COLUMN perfil ',
        REPLACE(@perfil_col_type, ')', ',\'redirecionador\')'),
        ' NULL DEFAULT \'cliente\''
    ),
    'SELECT 1'
);

PREPARE stmt_perfil FROM @sql_perfil;
EXECUTE stmt_perfil;
DEALLOCATE PREPARE stmt_perfil;
