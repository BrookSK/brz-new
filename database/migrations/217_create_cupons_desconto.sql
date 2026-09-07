-- Sistema de cupons de desconto (aplicados sobre o valor do produto no checkout)
-- Tabela principal de cupons + tabela de usos para controle de limite por usuário.

CREATE TABLE IF NOT EXISTS cupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    descricao VARCHAR(255) NULL,
    tipo ENUM('percentual', 'fixo') NOT NULL DEFAULT 'percentual',
    valor DECIMAL(10,2) NOT NULL DEFAULT 0,
    -- Regras de uso
    data_inicio DATE NULL,
    data_expiracao DATE NULL,
    limite_uso_total INT NULL,            -- NULL = ilimitado
    limite_uso_por_usuario INT NULL,      -- NULL = ilimitado por usuário
    usos_realizados INT NOT NULL DEFAULT 0,
    valor_minimo_pedido DECIMAL(10,2) NULL, -- valor mínimo de produtos (USD) para aplicar
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cupons_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupom_usos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cupom_id INT NOT NULL,
    usuario_id INT NULL,
    pedido_id INT NULL,
    valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0,
    moeda VARCHAR(3) NOT NULL DEFAULT 'USD',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cupom_usos_cupom (cupom_id),
    KEY idx_cupom_usos_usuario (usuario_id),
    KEY idx_cupom_usos_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
