-- Remessa WP: adicionar campos para janelas manuais (ex.: janela de testes)

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'remessa_wp_janelas'
);

-- tipo (auto/manual)
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'remessa_wp_janelas'
      AND column_name = 'tipo'
);

SET @sql := IF(@tbl_exists > 0 AND @col_exists = 0,
    "ALTER TABLE remessa_wp_janelas ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'auto' AFTER status",
    ''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- titulo
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'remessa_wp_janelas'
      AND column_name = 'titulo'
);

SET @sql := IF(@tbl_exists > 0 AND @col_exists = 0,
    "ALTER TABLE remessa_wp_janelas ADD COLUMN titulo VARCHAR(120) NULL AFTER tipo",
    ''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- índice para achar janela de testes
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'remessa_wp_janelas'
      AND index_name = 'idx_remessa_wp_janelas_tipo'
);

SET @sql := IF(@tbl_exists > 0 AND @idx_exists = 0,
    "ALTER TABLE remessa_wp_janelas ADD INDEX idx_remessa_wp_janelas_tipo (source, tipo, status)",
    ''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
