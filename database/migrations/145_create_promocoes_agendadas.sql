-- Agendamento de promoções (campanhas com início/fim)
CREATE TABLE IF NOT EXISTS `promocoes_agendadas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(255) NOT NULL COMMENT 'Nome da campanha/promoção',
    `desconto_tipo` ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
    `desconto_valor` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `inicio` DATETIME NOT NULL,
    `fim` DATETIME NOT NULL,
    `status` ENUM('agendada','ativa','finalizada','cancelada') NOT NULL DEFAULT 'agendada',
    `criado_por` INT DEFAULT NULL,
    `criado_por_nome` VARCHAR(191) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_inicio_fim` (`inicio`, `fim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produtos vinculados a cada promoção agendada
CREATE TABLE IF NOT EXISTS `promocoes_agendadas_produtos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `promocao_id` INT NOT NULL,
    `produto_id` INT NOT NULL,
    `preco_original` DECIMAL(12,2) DEFAULT NULL COMMENT 'Preço regular no momento do agendamento',
    `preco_promocional` DECIMAL(12,2) DEFAULT NULL COMMENT 'Preço calculado com desconto',
    INDEX `idx_promocao` (`promocao_id`),
    INDEX `idx_produto` (`produto_id`),
    UNIQUE KEY `uk_promo_produto` (`promocao_id`, `produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
