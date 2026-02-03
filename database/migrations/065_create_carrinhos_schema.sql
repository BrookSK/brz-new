-- Criar tabelas de carrinho persistente (por login) se não existirem

CREATE TABLE IF NOT EXISTS carrinhos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    session_id VARCHAR(255) NULL,
    moeda VARCHAR(3) NOT NULL DEFAULT 'USD',
    taxa_conversao DECIMAL(10,6) NULL,
    frete_manual DECIMAL(10,2) DEFAULT 0,
    taxa_servico DECIMAL(10,2) DEFAULT 0,
    subtotal_produtos DECIMAL(12,2) DEFAULT 0,
    valor_impostos DECIMAL(12,2) DEFAULT 0,
    valor_total DECIMAL(12,2) DEFAULT 0,
    peso_total DECIMAL(8,3) DEFAULT 0,
    expira_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_carrinho_usuario (usuario_id),
    KEY idx_carrinho_session (session_id)
);

CREATE TABLE IF NOT EXISTS carrinho_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrinho_id INT NOT NULL,
    produto_id INT NOT NULL,
    produto_variacao_id INT NULL,
    variacao_descricao VARCHAR(255) NULL,
    quantidade INT NOT NULL DEFAULT 1,
    valor_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_carrinho_item (carrinho_id, produto_id, produto_variacao_id),
    KEY idx_carrinho_items_carrinho (carrinho_id),
    KEY idx_carrinho_items_produto (produto_id)
);
