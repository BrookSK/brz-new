-- 072_create_representante_comissoes

CREATE TABLE IF NOT EXISTS representante_comissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    representante_id INT NOT NULL,
    percentual DECIMAL(10,2) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_rep_comissoes_rep (representante_id),
    INDEX idx_rep_comissoes_ativo (ativo),
    UNIQUE KEY uq_rep_comissoes_rep (representante_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK (cria só se existir tabela usuarios)
SET @tbl_usuarios_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
);

SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'representante_comissoes'
      AND CONSTRAINT_NAME = 'fk_rep_comissoes_usuario'
);

SET @sql_add_fk := IF(
    @tbl_usuarios_exists > 0 AND @fk_exists = 0,
    'ALTER TABLE representante_comissoes ADD CONSTRAINT fk_rep_comissoes_usuario FOREIGN KEY (representante_id) REFERENCES usuarios(id) ON DELETE CASCADE',
    'SELECT 1'
);

PREPARE stmt_add_fk FROM @sql_add_fk;
EXECUTE stmt_add_fk;
DEALLOCATE PREPARE stmt_add_fk;
