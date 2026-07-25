-- Tabela para o Calendário de Marketing
-- Armazena eventos/datas comemorativas gerenciáveis manualmente pelo admin
-- Pode ser populada via IA (ChatGPT) ou adicionada manualmente

CREATE TABLE IF NOT EXISTS marketing_calendario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    data_evento DATE NOT NULL,
    pais VARCHAR(5) NOT NULL DEFAULT 'BR' COMMENT 'BR, US, GLOBAL',
    emoji VARCHAR(10) NULL DEFAULT '📅',
    cor VARCHAR(7) NULL DEFAULT '#3b82f6',
    categoria VARCHAR(50) NULL DEFAULT 'comemorativa' COMMENT 'comemorativa, promocional, sazonal, custom',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    origem ENUM('manual','ia','sistema') NOT NULL DEFAULT 'manual',
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_data_evento (data_evento),
    INDEX idx_pais (pais),
    INDEX idx_ativo (ativo),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
