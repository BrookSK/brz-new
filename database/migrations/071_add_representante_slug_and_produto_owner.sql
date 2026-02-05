-- 071_add_representante_slug_and_produto_owner

-- usuarios.representante_slug
SET @tbl_usuarios_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
);

SET @col_rep_slug_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'representante_slug'
);

SET @sql_add_rep_slug := IF(
    @tbl_usuarios_exists > 0 AND @col_rep_slug_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN representante_slug VARCHAR(120) NULL',
    'SELECT 1'
);

PREPARE stmt_add_rep_slug FROM @sql_add_rep_slug;
EXECUTE stmt_add_rep_slug;
DEALLOCATE PREPARE stmt_add_rep_slug;

-- produtos.representante_id / produtos.representante_email
SET @tbl_produtos_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
);

SET @col_rep_id_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'representante_id'
);

SET @sql_add_rep_id := IF(
    @tbl_produtos_exists > 0 AND @col_rep_id_exists = 0,
    'ALTER TABLE produtos ADD COLUMN representante_id INT NULL',
    'SELECT 1'
);

PREPARE stmt_add_rep_id FROM @sql_add_rep_id;
EXECUTE stmt_add_rep_id;
DEALLOCATE PREPARE stmt_add_rep_id;

SET @col_rep_email_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'representante_email'
);

SET @sql_add_rep_email := IF(
    @tbl_produtos_exists > 0 AND @col_rep_email_exists = 0,
    'ALTER TABLE produtos ADD COLUMN representante_email VARCHAR(255) NULL',
    'SELECT 1'
);

PREPARE stmt_add_rep_email FROM @sql_add_rep_email;
EXECUTE stmt_add_rep_email;
DEALLOCATE PREPARE stmt_add_rep_email;

-- indices
SET @idx_produtos_rep_id_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND INDEX_NAME = 'idx_produtos_representante_id'
);

SET @sql_add_idx_rep_id := IF(
    @tbl_produtos_exists > 0 AND @idx_produtos_rep_id_exists = 0,
    'CREATE INDEX idx_produtos_representante_id ON produtos (representante_id)',
    'SELECT 1'
);

PREPARE stmt_add_idx_rep_id FROM @sql_add_idx_rep_id;
EXECUTE stmt_add_idx_rep_id;
DEALLOCATE PREPARE stmt_add_idx_rep_id;

SET @idx_usuarios_rep_slug_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND INDEX_NAME = 'idx_usuarios_representante_slug'
);

SET @sql_add_idx_rep_slug := IF(
    @tbl_usuarios_exists > 0 AND @idx_usuarios_rep_slug_exists = 0,
    'CREATE INDEX idx_usuarios_representante_slug ON usuarios (representante_slug)',
    'SELECT 1'
);

PREPARE stmt_add_idx_rep_slug FROM @sql_add_idx_rep_slug;
EXECUTE stmt_add_idx_rep_slug;
DEALLOCATE PREPARE stmt_add_idx_rep_slug;
