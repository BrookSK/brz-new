-- Migration 086: suporte a comprovante de compra online e comissões de processamento
-- Importante: esta migration é idempotente.

-- Tabela para armazenar comprovantes de compra (ex: compra online feita pelo vendedor)
CREATE TABLE IF NOT EXISTS `pedidos_compra_documentos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NOT NULL,
  `tipo_compra` VARCHAR(20) NOT NULL DEFAULT 'online',
  `status` VARCHAR(20) NOT NULL DEFAULT 'ok',
  `arquivo_path` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(120) NULL,
  `usuario_id` INT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pedido_id` (`pedido_id`),
  KEY `idx_usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK (tolerante): só cria se a tabela pedidos existir e se a constraint ainda não existir
SET @has_pedidos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pedidos');
SET @has_fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'pedidos_compra_documentos' AND constraint_type = 'FOREIGN KEY' AND constraint_name = 'fk_pedidos_compra_documentos_pedido');
SET @sql_fk := IF(@has_pedidos > 0 AND @has_fk = 0,
  'ALTER TABLE `pedidos_compra_documentos` ADD CONSTRAINT `fk_pedidos_compra_documentos_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- Tabela para registrar comissões por processamento (ex: finalizar compra online)
CREATE TABLE IF NOT EXISTS `comissoes_processamento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NOT NULL,
  `usuario_id` INT NOT NULL,
  `moeda` VARCHAR(10) NOT NULL DEFAULT 'BRL',
  `percentual` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `base_liquida` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `valor_comissao` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pedido_usuario` (`pedido_id`, `usuario_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_pedido_id` (`pedido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_fk2 := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'comissoes_processamento' AND constraint_type = 'FOREIGN KEY' AND constraint_name = 'fk_comissoes_processamento_pedido');
SET @sql_fk2 := IF(@has_pedidos > 0 AND @has_fk2 = 0,
  'ALTER TABLE `comissoes_processamento` ADD CONSTRAINT `fk_comissoes_processamento_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk2 FROM @sql_fk2;
EXECUTE stmt_fk2;
DEALLOCATE PREPARE stmt_fk2;

-- Campos no pedido para guardar quem finalizou a compra e quando (opcional)
SET @has_col_finalizado_por := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'compra_finalizada_por');
SET @sql_col1 := IF(@has_pedidos > 0 AND @has_col_finalizado_por = 0,
  'ALTER TABLE `pedidos` ADD COLUMN `compra_finalizada_por` INT NULL',
  'SELECT 1'
);
PREPARE stmt_col1 FROM @sql_col1;
EXECUTE stmt_col1;
DEALLOCATE PREPARE stmt_col1;

SET @has_col_finalizado_em := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'compra_finalizada_em');
SET @sql_col2 := IF(@has_pedidos > 0 AND @has_col_finalizado_em = 0,
  'ALTER TABLE `pedidos` ADD COLUMN `compra_finalizada_em` DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt_col2 FROM @sql_col2;
EXECUTE stmt_col2;
DEALLOCATE PREPARE stmt_col2;
