-- ============================================================
-- EMAIL MARKETING INTELIGENTE - Schema
-- ============================================================

-- Segmentações inteligentes
CREATE TABLE IF NOT EXISTS email_mkt_segmentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    tipo ENUM('automatico','manual') DEFAULT 'automatico',
    gatilho VARCHAR(100) DEFAULT NULL,
    criterios JSON DEFAULT NULL,
    total_clientes INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_gatilho (gatilho)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Campanhas
CREATE TABLE IF NOT EXISTS email_mkt_campanhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    tipo ENUM('reativacao','aniversario','pos_venda','categoria','vip','institucional','recompra','carrinho_abandonado') DEFAULT 'reativacao',
    gatilho VARCHAR(100) DEFAULT NULL,
    segmento_id INT DEFAULT NULL,
    status ENUM('rascunho_ia','pendente_revisao','aprovada','agendada','disparando','finalizada','rejeitada','cancelada') DEFAULT 'rascunho_ia',
    assunto VARCHAR(255) DEFAULT NULL,
    pre_header VARCHAR(255) DEFAULT NULL,
    html_content LONGTEXT DEFAULT NULL,
    variaveis_ia JSON DEFAULT NULL,
    total_clientes INT DEFAULT 0,
    total_enviado INT DEFAULT 0,
    total_entregue INT DEFAULT 0,
    total_aberto INT DEFAULT 0,
    total_clicado INT DEFAULT 0,
    total_convertido INT DEFAULT 0,
    aprovado_por INT DEFAULT NULL,
    data_aprovacao DATETIME DEFAULT NULL,
    data_agendamento DATETIME DEFAULT NULL,
    data_inicio_disparo DATETIME DEFAULT NULL,
    data_fim_disparo DATETIME DEFAULT NULL,
    observacoes_ia TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_tipo (tipo),
    INDEX idx_segmento (segmento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clientes vinculados a campanhas
CREATE TABLE IF NOT EXISTS email_mkt_campanha_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campanha_id INT NOT NULL,
    cliente_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    nome VARCHAR(255) DEFAULT NULL,
    status ENUM('aguardando','em_fila','enviando','enviado','entregue','aberto','clicado','convertido','falhou','rejeitado','cancelado') DEFAULT 'aguardando',
    data_envio DATETIME DEFAULT NULL,
    data_entrega DATETIME DEFAULT NULL,
    data_abertura DATETIME DEFAULT NULL,
    data_clique DATETIME DEFAULT NULL,
    data_conversao DATETIME DEFAULT NULL,
    erro_mensagem TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campanha (campanha_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Logs de disparo
CREATE TABLE IF NOT EXISTS email_mkt_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campanha_id INT NOT NULL,
    cliente_id INT DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    evento ENUM('enviado','entregue','aberto','clicado','convertido','falhou','rejeitado','cancelado','bounce','spam') NOT NULL,
    detalhes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campanha (campanha_id),
    INDEX idx_evento (evento),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configurações do módulo
CREATE TABLE IF NOT EXISTS email_mkt_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir configurações padrão
INSERT IGNORE INTO email_mkt_config (chave, valor) VALUES
('automacoes_ativas', '1'),
('tom_marca', 'humanizado, elegante, conversacional'),
('palavras_proibidas', 'urgente,última chance,corra,imperdível,grátis'),
('limite_diario', '200'),
('intervalo_campanhas_dias', '7'),
('max_campanhas_mes_cliente', '4'),
('dias_recompra_minimo', '30'),
('remetente_nome', 'Braziliana'),
('remetente_email', 'contato@brazilianashop.com.br'),
('velocidade_envio', '10'),
('limite_por_lote', '50');

-- Controle anti-duplicação
CREATE TABLE IF NOT EXISTS email_mkt_envio_controle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    campanha_id INT NOT NULL,
    tipo_campanha VARCHAR(50) NOT NULL,
    data_envio DATE NOT NULL,
    UNIQUE KEY uk_cliente_campanha (cliente_id, campanha_id),
    INDEX idx_cliente_data (cliente_id, data_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
