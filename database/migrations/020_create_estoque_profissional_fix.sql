-- Migration 020: Fix da Migration 019 (SQL válido e sem acentos). Use este arquivo no lugar da 019.

CREATE TABLE IF NOT EXISTS estoque_interno (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    data_compra DATE NULL,
    data_validade DATE NULL,
    is_alimenticio TINYINT(1) NOT NULL DEFAULT 0,
    galpao VARCHAR(80) NULL,
    prateleira VARCHAR(80) NULL,
    observacao VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estoque_interno_produto (produto_id),
    INDEX idx_estoque_interno_validade (data_validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_movimentacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    tipo_movimentacao ENUM('entrada','saida','ajuste') NOT NULL,
    quantidade INT NOT NULL,
    quantidade_anterior INT NULL,
    quantidade_nova INT NULL,
    motivo VARCHAR(255) NULL,
    usuario_id INT NULL,
    data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estoque_mov_produto (produto_id),
    INDEX idx_estoque_mov_data (data_movimentacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    estoque_minimo INT NOT NULL DEFAULT 5,
    estoque_ideal INT NOT NULL DEFAULT 20,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_estoque_cfg_produto (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lista_compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    quantidade_necessaria INT NOT NULL DEFAULT 1,
    quantidade_faltante INT NOT NULL DEFAULT 0,
    prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
    status ENUM('pendente','comprado','cancelado') NOT NULL DEFAULT 'pendente',
    data_solicitacao DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lista_compras_produto (produto_id),
    INDEX idx_lista_compras_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP VIEW IF EXISTS vw_status_geral_estoque;

CREATE VIEW vw_status_geral_estoque AS
SELECT
    p.id AS produto_id,
    p.name AS produto_nome,
    p.sku AS sku,
    p.loja AS loja,
    COALESCE(SUM(e.quantidade), 0) AS quantidade_estoque,
    CASE
        WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_minimo, 5) THEN 'critico'
        WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_ideal, 20) THEN 'baixo'
        ELSE 'normal'
    END AS status_estoque
FROM produtos p
LEFT JOIN estoque_interno e ON p.id = e.produto_id
LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
WHERE p.active = 1
GROUP BY p.id, p.name, p.sku, p.loja, ec.estoque_minimo, ec.estoque_ideal;
