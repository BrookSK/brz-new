-- Adiciona campos extras em auditoria_logs para antifraude: método, rota, handler, status HTTP, duração, session_id e referer
-- Não altera migrations existentes

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
);

-- http_method
SET @col_http_method := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
      AND COLUMN_NAME = 'http_method'
);
SET @sql_add_http_method := IF(
    @tbl_exists > 0 AND @col_http_method = 0,
    'ALTER TABLE auditoria_logs ADD COLUMN http_method VARCHAR(10) NULL AFTER usuario_id',
    'SELECT 1'
);
PREPARE stmt_add_http_method FROM @sql_add_http_method;
EXECUTE stmt_add_http_method;
DEALLOCATE PREPARE stmt_add_http_method;

-- route
SET @col_route := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
      AND COLUMN_NAME = 'route'
);
SET @sql_add_route := IF(
    @tbl_exists > 0 AND @col_route = 0,
    'ALTER TABLE auditoria_logs ADD COLUMN route VARCHAR(255) NULL AFTER http_method',
    'SELECT 1'
);
PREPARE stmt_add_route FROM @sql_add_route;
EXECUTE stmt_add_route;
DEALLOCATE PREPARE stmt_add_route;

-- handler
SET @col_handler := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
      AND COLUMN_NAME = 'handler'
);
SET @sql_add_handler := IF(
    @tbl_exists > 0 AND @col_handler = 0,
    'ALTER TABLE auditoria_logs ADD COLUMN handler VARCHAR(255) NULL AFTER route',
    'SELECT 1'
);
PREPARE stmt_add_handler FROM @sql_add_handler;
EXECUTE stmt_add_handler;
DEALLOCATE PREPARE stmt_add_handler;

-- status_code
SET @col_status_code := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
      AND COLUMN_NAME = 'status_code'
);
SET @sql_add_status_code := IF(
    @tbl_exists > 0 AND @col_status_code = 0,
    'ALTER TABLE auditoria_logs ADD COLUMN status_code INT NULL AFTER user_agent',
    'SELECT 1'
);
PREPARE stmt_add_status_code FROM @sql_add_status_code;
EXECUTE stmt_add_status_code;
DEALLOCATE PREPARE stmt_add_status_code;

-- duration_ms
SET @col_duration_ms := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
      AND COLUMN_NAME = 'duration_ms'
);
SET @sql_add_duration_ms := IF(
    @tbl_exists > 0 AND @col_duration_ms = 0,
    'ALTER TABLE auditoria_logs ADD COLUMN duration_ms INT NULL AFTER status_code',
    'SELECT 1'
);
PREPARE stmt_add_duration_ms FROM @sql_add_duration_ms;
EXECUTE stmt_add_duration_ms;
DEALLOCATE PREPARE stmt_add_duration_ms;

-- session_id
SET @col_session_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
      AND COLUMN_NAME = 'session_id'
);
SET @sql_add_session_id := IF(
    @tbl_exists > 0 AND @col_session_id = 0,
    'ALTER TABLE auditoria_logs ADD COLUMN session_id VARCHAR(128) NULL AFTER duration_ms',
    'SELECT 1'
);
PREPARE stmt_add_session_id FROM @sql_add_session_id;
EXECUTE stmt_add_session_id;
DEALLOCATE PREPARE stmt_add_session_id;

-- referer
SET @col_referer := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
      AND COLUMN_NAME = 'referer'
);
SET @sql_add_referer := IF(
    @tbl_exists > 0 AND @col_referer = 0,
    'ALTER TABLE auditoria_logs ADD COLUMN referer VARCHAR(500) NULL AFTER session_id',
    'SELECT 1'
);
PREPARE stmt_add_referer FROM @sql_add_referer;
EXECUTE stmt_add_referer;
DEALLOCATE PREPARE stmt_add_referer;
