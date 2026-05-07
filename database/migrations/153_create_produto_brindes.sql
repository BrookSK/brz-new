-- Tabela de brindes vinculados a produtos
-- Quando o produto principal é adicionado ao carrinho, o brinde entra automaticamente
-- com preço = 0, taxa de serviço normal, impostos normais (devolvidos na carteira após pagamento)

CREATE TABLE IF NOT EXISTS produto_brindes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL COMMENT 'Produto principal (gatilho)',
    brinde_produto_id INT NOT NULL COMMENT 'Produto que será dado de brinde',
    quantidade_brinde INT NOT NULL DEFAULT 1,
    data_inicio DATETIME NOT NULL COMMENT 'Início da vigência do brinde',
    data_fim DATETIME NOT NULL COMMENT 'Fim da vigência do brinde',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT NULL COMMENT 'Admin que cadastrou',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_produto_brindes_produto (produto_id),
    INDEX idx_produto_brindes_brinde (brinde_produto_id),
    INDEX idx_produto_brindes_vigencia (ativo, data_inicio, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de devoluções de impostos de brindes na carteira
CREATE TABLE IF NOT EXISTS brinde_devolucao_impostos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    pedido_item_id INT NULL COMMENT 'ID do item brinde no pedido',
    usuario_id INT NOT NULL,
    produto_brinde_id INT NOT NULL COMMENT 'ID do produto brinde',
    valor_imposto_devolvido DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor II+ICMS devolvido (USD)',
    valor_imposto_devolvido_brl DECIMAL(10,2) NULL COMMENT 'Valor em BRL se aplicável',
    status ENUM('pendente','devolvido','cancelado') NOT NULL DEFAULT 'pendente',
    devolvido_em DATETIME NULL,
    carteira_transacao_id INT NULL COMMENT 'ID da transação na carteira',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_brinde_dev_pedido (pedido_id),
    INDEX idx_brinde_dev_usuario (usuario_id),
    INDEX idx_brinde_dev_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
