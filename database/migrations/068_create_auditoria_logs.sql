-- Cria tabela de auditoria de ações de usuários (logs)
-- Não altera migrations existentes

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'auditoria_logs'
);

SET @sql_create := IF(
    @tbl_exists = 0,
    'CREATE TABLE auditoria_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NULL,
        acao VARCHAR(255) NOT NULL,
        tabela VARCHAR(100) NULL,
        registro_id INT NULL,
        valores_antigos LONGTEXT NULL,
        valores_novos LONGTEXT NULL,
        ip VARCHAR(64) NULL,
        user_agent VARCHAR(500) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_created_at (created_at),
        INDEX idx_acao (acao),
        INDEX idx_tabela_registro (tabela, registro_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);

PREPARE stmt_create_auditoria FROM @sql_create;
EXECUTE stmt_create_auditoria;
DEALLOCATE PREPARE stmt_create_auditoria;
