-- =====================================================
-- CARNÊ BRAZILIANA - Schema Completo
-- =====================================================

-- Tabela principal: carnês
CREATE TABLE IF NOT EXISTS carnes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    cliente_id INT NOT NULL,
    total_produtos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_taxas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_geral DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    quantidade_parcelas TINYINT NOT NULL DEFAULT 1,
    status ENUM(
        'aguardando_primeira_parcela',
        'ativo',
        'em_andamento',
        'com_atraso',
        'quitado',
        'inadimplente',
        'liberado_envio',
        'encerrado'
    ) NOT NULL DEFAULT 'aguardando_primeira_parcela',
    termos_aceitos TINYINT(1) NOT NULL DEFAULT 0,
    termos_aceitos_em DATETIME NULL,
    compra_interna_liberada TINYINT(1) NOT NULL DEFAULT 0,
    envio_liberado TINYINT(1) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_carnes_pedido (pedido_id),
    INDEX idx_carnes_cliente (cliente_id),
    INDEX idx_carnes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de parcelas do carnê
CREATE TABLE IF NOT EXISTS carne_parcelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carne_id INT NOT NULL,
    numero_parcela TINYINT NOT NULL,
    valor_produtos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    valor_taxas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    valor_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vencimento DATE NOT NULL,
    status ENUM(
        'pendente',
        'aguardando_pagamento',
        'parcialmente_paga',
        'paga',
        'vencida',
        'em_atraso',
        'reemitida'
    ) NOT NULL DEFAULT 'pendente',
    boleto_produtos_url TEXT NULL,
    boleto_produtos_codigo VARCHAR(255) NULL,
    boleto_produtos_id_externo VARCHAR(255) NULL,
    boleto_produtos_pago TINYINT(1) NOT NULL DEFAULT 0,
    boleto_produtos_pago_em DATETIME NULL,
    boleto_taxas_url TEXT NULL,
    boleto_taxas_codigo VARCHAR(255) NULL,
    boleto_taxas_id_externo VARCHAR(255) NULL,
    boleto_taxas_pago TINYINT(1) NOT NULL DEFAULT 0,
    boleto_taxas_pago_em DATETIME NULL,
    reemissao_count TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parcelas_carne (carne_id),
    INDEX idx_parcelas_status (status),
    INDEX idx_parcelas_vencimento (vencimento),
    CONSTRAINT fk_parcelas_carne FOREIGN KEY (carne_id) REFERENCES carnes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de histórico / log do carnê
CREATE TABLE IF NOT EXISTS carne_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carne_id INT NOT NULL,
    parcela_id INT NULL,
    tipo VARCHAR(50) NOT NULL,
    descricao TEXT NOT NULL,
    dados_extra JSON NULL,
    usuario_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_historico_carne (carne_id),
    CONSTRAINT fk_historico_carne FOREIGN KEY (carne_id) REFERENCES carnes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de notificações enviadas do carnê
CREATE TABLE IF NOT EXISTS carne_notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carne_id INT NOT NULL,
    parcela_id INT NULL,
    evento VARCHAR(100) NOT NULL,
    canal ENUM('email','webhook') NOT NULL,
    destinatario VARCHAR(255) NULL,
    payload JSON NULL,
    status ENUM('enviado','erro','pendente') NOT NULL DEFAULT 'pendente',
    erro_mensagem TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_carne (carne_id),
    INDEX idx_notif_evento (evento),
    CONSTRAINT fk_notif_carne FOREIGN KEY (carne_id) REFERENCES carnes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de compras internas do carnê
CREATE TABLE IF NOT EXISTS carne_compras_internas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carne_id INT NOT NULL,
    pedido_id INT NOT NULL,
    status ENUM(
        'aguardando_compra',
        'comprado',
        'recebido',
        'produto_indisponivel',
        'substituido'
    ) NOT NULL DEFAULT 'aguardando_compra',
    produto_indisponivel TINYINT(1) NOT NULL DEFAULT 0,
    acao_indisponibilidade ENUM('credito_carteira','pedido_complementar') NULL,
    pedido_complementar_id INT NULL,
    valor_credito DECIMAL(12,2) NULL,
    observacoes TEXT NULL,
    comprado_em DATETIME NULL,
    recebido_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_compras_carne (carne_id),
    INDEX idx_compras_status (status),
    CONSTRAINT fk_compras_carne FOREIGN KEY (carne_id) REFERENCES carnes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações do carnê no sistema (key-value na tabela configuracoes_sistema)
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES
('carne_ativo', '1'),
('carne_max_parcelas', '12'),
('carne_dias_vencimento', '7'),
('carne_webhook_url', ''),
('carne_webhook_ativo', '0'),
('carne_email_ativo', '1'),
('carne_eventos_webhook', 'carne_criado,parcela_paga,carne_quitado,parcela_vencida'),
('carne_eventos_email', 'carne_criado,parcela_gerada,parcela_proxima_vencimento,parcela_vencida,parcela_paga,carne_quitado,envio_liberado');

-- Token de segurança para o cron (gere um token aleatório e coloque aqui)
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES
('cron_secret', '');
