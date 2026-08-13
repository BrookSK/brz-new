-- Migration 213: Desapego Brasiliano
-- Adiciona coluna desapego e desapeguista_id na tabela produtos
-- Adiciona colunas is_desapeguista e desapeguista_comissao na tabela usuarios
-- Cria tabela desapego_comissoes para controle de comissões

-- 1. Coluna desapego (flag) na tabela produtos
ALTER TABLE produtos ADD COLUMN desapego TINYINT(1) NOT NULL DEFAULT 0 AFTER outlet;

-- 2. Coluna desapeguista_id (FK opcional para usuario dono do produto) na tabela produtos
ALTER TABLE produtos ADD COLUMN desapeguista_id INT NULL DEFAULT NULL AFTER desapego;

-- 3. Coluna is_desapeguista na tabela usuarios
ALTER TABLE usuarios ADD COLUMN is_desapeguista TINYINT(1) NOT NULL DEFAULT 0;

-- 4. Coluna desapeguista_comissao (percentual de comissão, padrão 30%)
ALTER TABLE usuarios ADD COLUMN desapeguista_comissao DECIMAL(5,2) NOT NULL DEFAULT 30.00;

-- 5. Tabela de comissões do desapego (registro por pedido/item vendido)
CREATE TABLE IF NOT EXISTS desapego_comissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    desapeguista_id INT NOT NULL COMMENT 'ID do usuário desapeguista',
    produto_id INT NOT NULL COMMENT 'ID do produto vendido',
    pedido_id INT NOT NULL COMMENT 'ID do pedido onde foi vendido',
    pedido_item_id INT NULL COMMENT 'ID do item no pedido',
    valor_venda DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de venda do produto (USD)',
    percentual_comissao DECIMAL(5,2) NOT NULL DEFAULT 30.00 COMMENT 'Percentual de comissão aplicado',
    valor_comissao DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor da comissão calculada (USD)',
    status ENUM('pendente', 'aprovado', 'pago', 'cancelado') NOT NULL DEFAULT 'pendente',
    data_pagamento DATETIME NULL DEFAULT NULL COMMENT 'Data em que a comissão foi paga',
    observacao TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_desapego_comissoes_desapeguista (desapeguista_id),
    INDEX idx_desapego_comissoes_produto (produto_id),
    INDEX idx_desapego_comissoes_pedido (pedido_id),
    INDEX idx_desapego_comissoes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Índice para filtrar produtos de desapego rapidamente
ALTER TABLE produtos ADD INDEX idx_produtos_desapego (desapego);

-- 7. Índice para filtrar desapeguistas
ALTER TABLE usuarios ADD INDEX idx_usuarios_is_desapeguista (is_desapeguista);
