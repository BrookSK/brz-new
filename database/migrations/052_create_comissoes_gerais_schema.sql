-- Schema para gestão de "Comissões gerais" (janelas, pagamentos parciais, ajustes e histórico)
-- Observação: compatível com bancos que já possuem `pedidos` e `usuarios`.
-- Não altera migrations antigas.

-- 1) Janelas globais de comissão
CREATE TABLE IF NOT EXISTS comissao_janelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'aberta',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_comissao_janelas_periodo (data_inicio, data_fim),
    INDEX idx_comissao_janelas_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Ajustes manuais (crédito/débito) por vendedor e janela
CREATE TABLE IF NOT EXISTS comissao_ajustes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    janela_id INT NOT NULL,
    vendedor_id INT NOT NULL,
    tipo VARCHAR(20) NOT NULL, -- credito | debito
    valor_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    motivo VARCHAR(255) NULL,
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comissao_ajustes_janela (janela_id),
    INDEX idx_comissao_ajustes_vendedor (vendedor_id),
    INDEX idx_comissao_ajustes_janela_vendedor (janela_id, vendedor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Pagamentos de comissão (permite parcial) por vendedor e janela
CREATE TABLE IF NOT EXISTS comissao_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    janela_id INT NOT NULL,
    vendedor_id INT NOT NULL,

    valor_calculado_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    valor_ajustes_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    valor_total_usd DECIMAL(12,2) NOT NULL DEFAULT 0,

    valor_pago_usd DECIMAL(12,2) NOT NULL DEFAULT 0,

    metodo VARCHAR(50) NULL,
    observacao TEXT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'pendente', -- pendente | aprovado | cancelado
    aprovado_por INT NULL,
    aprovado_em DATETIME NULL,

    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_comissao_pagamentos_janela (janela_id),
    INDEX idx_comissao_pagamentos_vendedor (vendedor_id),
    INDEX idx_comissao_pagamentos_janela_vendedor (janela_id, vendedor_id),
    INDEX idx_comissao_pagamentos_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Alocação de um pagamento em pedidos (para auditoria/histórico)
CREATE TABLE IF NOT EXISTS comissao_pagamento_pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pagamento_id INT NOT NULL,
    pedido_id INT NOT NULL,
    valor_comissao_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cpp_pagamento (pagamento_id),
    INDEX idx_cpp_pedido (pedido_id),
    UNIQUE KEY uq_cpp_pagamento_pedido (pagamento_id, pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
