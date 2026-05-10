-- =====================================================
-- MÓDULO DEMANDAS - Schema
-- =====================================================

CREATE TABLE IF NOT EXISTS demandas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(500) NOT NULL,
    solicitante VARCHAR(255) NOT NULL,
    status ENUM('pendente','em_analise','em_execucao','em_teste','bloqueado','concluido') NOT NULL DEFAULT 'pendente',

    -- Bloco 1
    bloco1_solicitante VARCHAR(255) NOT NULL,
    bloco1_titulo VARCHAR(500) NOT NULL,

    -- Bloco 2
    bloco2_problema TEXT NOT NULL,
    bloco2_melhoria TEXT NOT NULL,
    bloco2_consequencia TEXT NOT NULL,

    -- Bloco 3
    bloco3_financeiro TEXT NOT NULL,
    bloco3_capital_giro TEXT NOT NULL,
    bloco3_custos_operacionais TEXT NOT NULL,
    bloco3_jornada_cliente TEXT NOT NULL,
    bloco3_equipe TEXT NOT NULL,
    bloco3_conflitos TEXT NOT NULL,

    -- Bloco 4 (etapas JSON)
    bloco4_etapas JSON NOT NULL,

    -- Bloco 5
    bloco5_novo_ou_existente TEXT NOT NULL,
    bloco5_ferramentas TEXT NOT NULL,
    bloco5_regras TEXT NOT NULL,
    bloco5_usuarios TEXT NOT NULL,

    -- Admin
    nota_admin TEXT NULL,
    prazo_entrega DATE NULL,
    inicio_execucao DATETIME NULL,
    inicio_teste DATETIME NULL,

    -- Audit
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    concluido_em DATETIME NULL,

    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Histórico de mudanças de status
CREATE TABLE IF NOT EXISTS demanda_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demanda_id INT NOT NULL,
    status_anterior VARCHAR(50) NULL,
    status_novo VARCHAR(50) NOT NULL,
    usuario_id INT NULL,
    observacao TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_demanda (demanda_id),
    CONSTRAINT fk_hist_demanda FOREIGN KEY (demanda_id) REFERENCES demandas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
