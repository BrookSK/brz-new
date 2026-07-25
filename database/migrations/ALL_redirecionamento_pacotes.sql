-- =============================================================
-- MIGRATIONS - Sistema de Redirecionamento de Pacotes
-- Rodar em produção uma única vez
-- =============================================================

-- 201: Tabela de pacotes recebidos no armazém
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

-- 202: Tabela de invoices de pedidos
CREATE TABLE IF NOT EXISTS pedido_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    status ENUM('liberado','confirmado','contestado') NOT NULL DEFAULT 'liberado',
    contestacao_motivo TEXT NULL,
    confirmado_em TIMESTAMP NULL,
    contestado_em TIMESTAMP NULL,
    liberado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 203: Itens do invoice
CREATE TABLE IF NOT EXISTS pedido_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    pedido_item_id INT NULL,
    pacote_id INT NULL,
    nome_produto VARCHAR(255) NOT NULL,
    ncm VARCHAR(20) NULL,
    declaration_value DECIMAL(10,2) NOT NULL,
    peso_kg DECIMAL(6,3) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    tem_bateria ENUM('S','N') DEFAULT 'N',
    tem_perfume ENUM('S','N') DEFAULT 'N',
    foto_url TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 204: Faturas adicionais
CREATE TABLE IF NOT EXISTS pedido_faturas_adicionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao TEXT NULL,
    status ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
    pago_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 205: Novas colunas em carrinho_items (ignorar erro se já existirem)
ALTER TABLE carrinho_items ADD COLUMN declaration_value DECIMAL(10,2) NULL;
ALTER TABLE carrinho_items ADD COLUMN tipo_item VARCHAR(30) NOT NULL DEFAULT 'produto';
ALTER TABLE carrinho_items ADD COLUMN pacote_id INT NULL;
ALTER TABLE carrinho_items ADD COLUMN fatura_adicional_id INT NULL;
ALTER TABLE carrinho_items ADD COLUMN nome_item VARCHAR(255) NULL;
ALTER TABLE carrinho_items ADD COLUMN peso_kg DECIMAL(6,3) NULL;
ALTER TABLE carrinho_items ADD COLUMN foto_url TEXT NULL;

-- 206: Configurações do sistema de pacotes
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_dias_multa_inicio', '15')
ON DUPLICATE KEY UPDATE chave = chave;
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_multa_valor_dia_usd', '2.00')
ON DUPLICATE KEY UPDATE chave = chave;
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_dias_descarte', '42')
ON DUPLICATE KEY UPDATE chave = chave;
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_lembrete_intervalo_dias', '5')
ON DUPLICATE KEY UPDATE chave = chave;
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_taxa_seguro_percentual', '3.00')
ON DUPLICATE KEY UPDATE chave = chave;

-- 207: Coluna para comprovante de compra
ALTER TABLE carrinho_items ADD COLUMN comprovante_url TEXT NULL;
