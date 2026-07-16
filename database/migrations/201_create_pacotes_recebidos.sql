-- Tabela de pacotes recebidos no armazém (Redirecionamento de Pacotes)
-- Quando um produto chega no armazém (EUA), o admin cadastra vinculado à suite do cliente

CREATE TABLE IF NOT EXISTS pacotes_recebidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_suite INT NOT NULL,
    usuario_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    fornecedor VARCHAR(255) NOT NULL,
    ncm VARCHAR(20) NULL,
    data_recebimento DATE NOT NULL,
    peso_kg DECIMAL(6,3) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    foto_url TEXT NULL,
    status ENUM(
        'pendente',
        'pedido_criado',
        'invoice_liberado',
        'invoice_confirmado',
        'invoice_contestado',
        'enviado',
        'fatura_pendente',
        'fatura_paga',
        'descartado'
    ) NOT NULL DEFAULT 'pendente',
    pedido_id INT NULL,
    produto_carrinho_id INT NULL,
    dias_armazenamento INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suite (numero_suite),
    INDEX idx_usuario (usuario_id),
    INDEX idx_status (status),
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
