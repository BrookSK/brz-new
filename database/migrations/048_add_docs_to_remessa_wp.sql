-- Remessa WP: flag medicamento e documentos obrigatórios pós-etiqueta

-- Adiciona coluna medicamento (se não existir)
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'remessa_wp_janela_pedidos'
);

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'remessa_wp_janela_pedidos'
      AND column_name = 'medicamento'
);

SET @sql := IF(@tbl_exists > 0 AND @col_exists = 0,
    "ALTER TABLE remessa_wp_janela_pedidos ADD COLUMN medicamento TINYINT(1) NOT NULL DEFAULT 0 AFTER recebido_confirmado_em",
    ''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tabela de documentos por pedido na janela
CREATE TABLE IF NOT EXISTS remessa_wp_pedido_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    janela_id INT NOT NULL,
    source VARCHAR(10) NOT NULL DEFAULT 'br',
    order_id BIGINT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ok',
    file_path TEXT NULL,
    original_name VARCHAR(255) NULL,
    mime VARCHAR(120) NULL,
    file_size INT NULL,
    uploaded_by_user_id INT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rwp_docs (janela_id, source, order_id, tipo),
    INDEX idx_rwp_docs_order (source, order_id),
    INDEX idx_rwp_docs_janela (janela_id),
    CONSTRAINT fk_rwp_docs_janela FOREIGN KEY (janela_id) REFERENCES remessa_wp_janelas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
