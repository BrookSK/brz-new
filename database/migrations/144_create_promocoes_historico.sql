-- Histórico/auditoria de promoções de produtos
CREATE TABLE IF NOT EXISTS `promocoes_historico` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `produto_id` INT NOT NULL,
    `produto_nome` VARCHAR(255) DEFAULT NULL,
    `acao` ENUM('criada','alterada','removida','expirada') NOT NULL DEFAULT 'criada',
    `sale_price` DECIMAL(12,2) DEFAULT NULL COMMENT 'Preço promocional definido',
    `price` DECIMAL(12,2) DEFAULT NULL COMMENT 'Preço regular no momento',
    `sale_price_expires` DATETIME DEFAULT NULL COMMENT 'Data limite da promoção',
    `sale_price_anterior` DECIMAL(12,2) DEFAULT NULL COMMENT 'Preço promo anterior (em alterações)',
    `usuario_id` INT DEFAULT NULL COMMENT 'Quem fez a alteração',
    `usuario_nome` VARCHAR(191) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_produto` (`produto_id`),
    INDEX `idx_acao` (`acao`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
