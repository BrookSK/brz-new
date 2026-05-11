CREATE TABLE IF NOT EXISTS produto_descricoes_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    descricao_gerada TEXT,
    descricao_editada TEXT,
    status_revisao ENUM('sem_descricao','gerando','pendente_revisao','aprovado','reprovado','erro') DEFAULT 'sem_descricao',
    erro_geracao TEXT,
    aprovado_por INT DEFAULT NULL,
    data_geracao DATETIME DEFAULT NULL,
    data_aprovacao DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_produto_id (produto_id),
    INDEX idx_status (status_revisao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
