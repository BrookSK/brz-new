-- Chat/Mensagens entre solicitante e desenvolvedor na demanda
CREATE TABLE IF NOT EXISTS demanda_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demanda_id INT NOT NULL,
    usuario_id INT NULL,
    usuario_nome VARCHAR(255) NOT NULL,
    mensagem TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_demanda_msg (demanda_id),
    CONSTRAINT fk_msg_demanda FOREIGN KEY (demanda_id) REFERENCES demandas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Arquivos anexados (tanto no bug report quanto no chat)
CREATE TABLE IF NOT EXISTS demanda_arquivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demanda_id INT NOT NULL,
    mensagem_id INT NULL,
    usuario_id INT NULL,
    nome_original VARCHAR(500) NOT NULL,
    caminho VARCHAR(1000) NOT NULL,
    tipo VARCHAR(100) NULL,
    tamanho INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_demanda_arq (demanda_id),
    INDEX idx_msg_arq (mensagem_id),
    CONSTRAINT fk_arq_demanda FOREIGN KEY (demanda_id) REFERENCES demandas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
