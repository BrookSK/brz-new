-- Criar tabela para orçamentos persistidos da Assessoria

SET @db := DATABASE();

SET @table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'assessoria_orcamentos'
);

SET @sql := IF(
    @table_exists = 0,
    'CREATE TABLE assessoria_orcamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT ''rascunho'',
        public_token VARCHAR(64) NOT NULL,
        job_id VARCHAR(64) NULL,
        links_json LONGTEXT NULL,
        produtos_json LONGTEXT NULL,
        erros_json LONGTEXT NULL,
        totais_json LONGTEXT NULL,
        last_processed_at DATETIME NULL,
        pedido_id INT NULL,
        paid_at DATETIME NULL,
        webhook_inicio_disparado_em DATETIME NULL,
        webhook_conclusao_disparado_em DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX idx_assessoria_orcamentos_usuario (usuario_id),
        INDEX idx_assessoria_orcamentos_status (status),
        UNIQUE KEY uq_assessoria_orcamentos_token (public_token),
        INDEX idx_assessoria_orcamentos_job (job_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
    'SELECT "Table assessoria_orcamentos already exists" AS info;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
