-- =====================================================
-- MIGRATIONS CONSOLIDADAS - Branch Atual
-- Rodar em PRODUÇÃO para sincronizar com o código
-- Data: 2026-07-25
-- =====================================================
-- Inclui:
--   209 - Tipo de compra Marketing (índices)
--   210 - Categoria de despesas "Descontos"
--   211 - Calendário de Marketing
--   212 - Preferências de Usuário Admin
-- =====================================================

-- =====================================================
-- 209: Índices para tipo_compra (suporte a 'marketing')
-- =====================================================

SET @has_pedidos := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos');
SET @has_tipo_compra := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'tipo_compra');
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND INDEX_NAME = 'idx_pedidos_tipo_compra');
SET @sql_idx := IF(@has_pedidos > 0 AND @has_tipo_compra > 0 AND @has_idx = 0,
    'ALTER TABLE pedidos ADD INDEX idx_pedidos_tipo_compra (tipo_compra)',
    'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx; EXECUTE stmt_idx; DEALLOCATE PREPARE stmt_idx;

SET @has_lista := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras');
SET @has_tc_lista := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras' AND COLUMN_NAME = 'tipo_compra');
SET @has_idx_lista := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lista_compras' AND INDEX_NAME = 'idx_lista_compras_tipo_compra');
SET @sql_idx_lista := IF(@has_lista > 0 AND @has_tc_lista > 0 AND @has_idx_lista = 0,
    'ALTER TABLE lista_compras ADD INDEX idx_lista_compras_tipo_compra (tipo_compra)',
    'SELECT 1'
);
PREPARE stmt_idx_lista FROM @sql_idx_lista; EXECUTE stmt_idx_lista; DEALLOCATE PREPARE stmt_idx_lista;


-- =====================================================
-- 210: Categoria "Descontos" em despesa_categorias
-- =====================================================

SET @has_table := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'despesa_categorias');
SET @has_cat := IF(@has_table > 0,
    (SELECT COUNT(*) FROM despesa_categorias WHERE LOWER(nome) = 'descontos'),
    0
);
SET @sql_cat := IF(@has_table > 0 AND @has_cat = 0,
    "INSERT INTO despesa_categorias (nome, grupo, cor, icone, ativa, inclui_relatorio) VALUES ('Descontos', 'despesa_operacional', '#f43f5e', 'fas fa-percent', 1, 1)",
    'SELECT 1'
);
PREPARE stmt_cat FROM @sql_cat; EXECUTE stmt_cat; DEALLOCATE PREPARE stmt_cat;


-- =====================================================
-- 211: Calendário de Marketing
-- =====================================================

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


-- =====================================================
-- 212: Preferências de Usuário Admin
-- =====================================================

CREATE TABLE IF NOT EXISTS admin_user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    idioma VARCHAR(10) NOT NULL DEFAULT 'pt-BR' COMMENT 'pt-BR ou en',
    moeda VARCHAR(3) NOT NULL DEFAULT 'USD' COMMENT 'USD ou BRL - moeda de exibição padrão',
    configurado TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = já passou pelo wizard inicial',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario (usuario_id),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- Tabela de notificações admin (caso não exista ainda)
-- Usada pelo sino de notificações + demandas
-- =====================================================

CREATE TABLE IF NOT EXISTS admin_notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'demanda',
    titulo VARCHAR(500) NOT NULL,
    mensagem TEXT NULL,
    link VARCHAR(1000) NULL,
    lida TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_lida (usuario_id, lida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- FIM
-- =====================================================
