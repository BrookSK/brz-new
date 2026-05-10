-- =====================================================
-- MÓDULO DESPESAS - Schema Completo
-- =====================================================

-- Categorias de despesas
CREATE TABLE IF NOT EXISTS despesa_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    grupo ENUM('custo_produto','despesa_operacional','despesa_administrativa','despesa_financeira','tributos','comissoes','outras') NOT NULL DEFAULT 'despesa_operacional',
    cor VARCHAR(7) NULL DEFAULT '#6b7280',
    icone VARCHAR(50) NULL DEFAULT 'fas fa-tag',
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    inclui_relatorio TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_grupo (grupo),
    INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir categorias padrão
INSERT IGNORE INTO despesa_categorias (nome, grupo, cor, icone) VALUES
('Folha de pagamento', 'despesa_administrativa', '#3b82f6', 'fas fa-users'),
('Comissões', 'comissoes', '#8b5cf6', 'fas fa-hand-holding-usd'),
('Frete · Correios', 'custo_produto', '#f59e0b', 'fas fa-truck'),
('Software · SaaS', 'despesa_operacional', '#06b6d4', 'fas fa-laptop-code'),
('Marketing', 'despesa_operacional', '#ec4899', 'fas fa-bullhorn'),
('Aluguel', 'despesa_operacional', '#10b981', 'fas fa-building'),
('Tributos', 'tributos', '#ef4444', 'fas fa-landmark'),
('Equipamentos', 'despesa_operacional', '#64748b', 'fas fa-desktop'),
('Utilidades', 'despesa_operacional', '#14b8a6', 'fas fa-bolt'),
('Reembolso', 'outras', '#a855f7', 'fas fa-undo'),
('Fornecedores', 'custo_produto', '#f97316', 'fas fa-store'),
('Taxas bancárias', 'despesa_financeira', '#dc2626', 'fas fa-university'),
('Outros', 'outras', '#6b7280', 'fas fa-ellipsis-h');

-- Tabela principal de despesas
CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(500) NOT NULL,
    categoria_id INT NULL,
    tipo ENUM('avulsa','fixa','recorrente','parcelada','comissao') NOT NULL DEFAULT 'avulsa',
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    moeda VARCHAR(3) NOT NULL DEFAULT 'BRL',
    competencia DATE NULL COMMENT 'Mês de referência (primeiro dia do mês)',
    vencimento DATE NULL,
    data_pagamento DATE NULL,
    status ENUM('prevista','a_vencer','vencida','paga','parcialmente_paga','cancelada') NOT NULL DEFAULT 'prevista',
    forma_pagamento VARCHAR(50) NULL,
    favorecido VARCHAR(255) NULL,
    observacoes TEXT NULL,
    comprovante_path VARCHAR(500) NULL,
    -- Recorrência
    recorrencia_id INT NULL COMMENT 'FK para despesa_recorrencias',
    -- Parcelamento
    parcelamento_id INT NULL COMMENT 'FK para despesa_parcelamentos',
    parcela_numero TINYINT NULL,
    -- Comissão
    comissao_pedido_id INT NULL,
    comissao_usuario_id INT NULL,
    comissao_regra VARCHAR(100) NULL,
    comissao_base DECIMAL(12,2) NULL,
    -- Origem
    origem ENUM('manual','recorrencia','parcelamento','comissao','sistema') NOT NULL DEFAULT 'manual',
    -- Audit
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_tipo (tipo),
    INDEX idx_categoria (categoria_id),
    INDEX idx_vencimento (vencimento),
    INDEX idx_competencia (competencia),
    INDEX idx_recorrencia (recorrencia_id),
    INDEX idx_parcelamento (parcelamento_id),
    INDEX idx_origem (origem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recorrências
CREATE TABLE IF NOT EXISTS despesa_recorrencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(500) NOT NULL,
    categoria_id INT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    moeda VARCHAR(3) NOT NULL DEFAULT 'BRL',
    frequencia ENUM('semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') NOT NULL DEFAULT 'mensal',
    dia_vencimento TINYINT NOT NULL DEFAULT 1,
    forma_pagamento VARCHAR(50) NULL,
    favorecido VARCHAR(255) NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    ultima_geracao DATE NULL,
    proxima_geracao DATE NULL,
    observacoes TEXT NULL,
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativa (ativa),
    INDEX idx_proxima (proxima_geracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parcelamentos
CREATE TABLE IF NOT EXISTS despesa_parcelamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(500) NOT NULL,
    categoria_id INT NULL,
    valor_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    quantidade_parcelas TINYINT NOT NULL DEFAULT 1,
    valor_parcela DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    moeda VARCHAR(3) NOT NULL DEFAULT 'BRL',
    data_primeira_parcela DATE NOT NULL,
    frequencia ENUM('mensal','quinzenal','semanal') NOT NULL DEFAULT 'mensal',
    forma_pagamento VARCHAR(50) NULL,
    favorecido VARCHAR(255) NULL,
    parcelas_pagas TINYINT NOT NULL DEFAULT 0,
    saldo_restante DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('em_andamento','quitado','cancelado') NOT NULL DEFAULT 'em_andamento',
    observacoes TEXT NULL,
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Histórico de alterações
CREATE TABLE IF NOT EXISTS despesa_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    despesa_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL,
    descricao TEXT NULL,
    usuario_id INT NULL,
    dados_anteriores JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_despesa (despesa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
