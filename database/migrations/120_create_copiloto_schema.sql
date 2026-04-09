-- =====================================================
-- Co-Piloto Braziliana — Schema de banco de dados
-- =====================================================

-- Configurações do Co-Piloto (usa configuracoes_sistema existente — schema chave/valor)
INSERT IGNORE INTO configuracoes_sistema (chave, valor, descricao, tipo) VALUES
('copiloto_ativo', '0', 'Co-Piloto ativo no site (0=não, 1=sim)', 'boolean'),
('copiloto_modo', 'desativado', 'Modo do copiloto: desativado, somente_admins, publico', 'string'),
('copiloto_api_key_claude', '', 'API Key do Claude (Anthropic)', 'string'),
('copiloto_modelo_ia', 'claude-sonnet-4-5', 'Modelo de IA (fixo)', 'string'),
('copiloto_backend_url', 'https://copiloto.braziliana.com.br', 'URL do backend Node.js do copiloto', 'string'),
('copiloto_max_msgs_por_minuto', '20', 'Máximo de mensagens por minuto por sessão', 'number'),
('copiloto_timeout_claude_ms', '15000', 'Timeout da chamada ao Claude em ms', 'number'),
('copiloto_cambio_usd_brl', '5.80', 'Câmbio USD para BRL', 'number'),
('copiloto_gatilho_tempo_ms', '30000', 'Tempo para gatilho proativo em ms', 'number'),
('copiloto_max_historico_enviado', '10', 'Máximo de mensagens de histórico enviadas ao Claude', 'number');

-- Tabela de sessões do copiloto (analytics e histórico)
CREATE TABLE IF NOT EXISTS copiloto_sessoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sessao_id VARCHAR(128) NOT NULL,
    usuario_id INT UNSIGNED NULL,
    pagina_origem VARCHAR(500) NULL,
    ip VARCHAR(45) NULL,
    user_agent TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_interacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    total_mensagens INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_sessao_id (sessao_id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de mensagens do copiloto (log de conversas)
CREATE TABLE IF NOT EXISTS copiloto_mensagens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sessao_id VARCHAR(128) NOT NULL,
    role ENUM('user','assistant','system') NOT NULL,
    conteudo TEXT NOT NULL,
    acao VARCHAR(100) NULL,
    parametros_acao JSON NULL,
    contexto_pagina JSON NULL,
    tokens_usados INT UNSIGNED NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sessao_id (sessao_id),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de pendências de aprendizado da IA
CREATE TABLE IF NOT EXISTS copiloto_aprendizado (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status ENUM('pendente','aceita','recusada','editada_e_aceita') NOT NULL DEFAULT 'pendente',
    tipos JSON NOT NULL COMMENT '["lacuna_documento","falha_processo"]',
    resumo_problema TEXT NOT NULL,
    impacto_estimado ENUM('alto','medio','baixo') NOT NULL DEFAULT 'medio',
    frequencia INT UNSIGNED NOT NULL DEFAULT 1,
    -- Origem
    sessao_id VARCHAR(128) NULL,
    mensagem_usuario TEXT NULL,
    resposta_bri TEXT NULL,
    pagina_origem VARCHAR(500) NULL,
    numero_pedido VARCHAR(50) NULL,
    -- Sugestão de documento
    documento_afetado VARCHAR(100) NULL,
    topico_afetado VARCHAR(255) NULL,
    localizacao ENUM('antes','depois','substituir') NULL,
    texto_atual TEXT NULL,
    texto_sugerido TEXT NULL,
    justificativa TEXT NULL,
    texto_editado TEXT NULL,
    -- Sugestão de processo
    etapa_falhou VARCHAR(255) NULL,
    descricao_falha TEXT NULL,
    sugestao_melhoria TEXT NULL,
    area_responsavel ENUM('operacional','logistica','tecnologia','atendimento') NULL,
    -- Timestamps
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_impacto (impacto_estimado),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de conteúdo de referência (uploads de PDFs, docs, etc.)
CREATE TABLE IF NOT EXISTS copiloto_conteudo (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    categoria ENUM('vendas_e_conversao','comportamento_consumidor','produto_e_importacao','engajamento','precificacao','outro') NOT NULL DEFAULT 'outro',
    arquivo_nome VARCHAR(255) NOT NULL,
    arquivo_path VARCHAR(500) NOT NULL,
    arquivo_tamanho INT UNSIGNED NOT NULL DEFAULT 0,
    arquivo_tipo VARCHAR(50) NOT NULL,
    notas_ia TEXT NULL,
    status ENUM('processando','ativo','inativo','erro') NOT NULL DEFAULT 'processando',
    total_chunks INT UNSIGNED NOT NULL DEFAULT 0,
    total_paginas INT UNSIGNED NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_categoria (categoria),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de chunks de conteúdo (para busca semântica)
CREATE TABLE IF NOT EXISTS copiloto_conteudo_chunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conteudo_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    texto TEXT NOT NULL,
    embedding BLOB NULL COMMENT 'Vetor de embedding serializado',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conteudo_id (conteudo_id),
    FOREIGN KEY (conteudo_id) REFERENCES copiloto_conteudo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice cross-grupo de produtos (para inteligência de carrinho)
CREATE TABLE IF NOT EXISTS copiloto_produtos_index (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id VARCHAR(50) NULL,
    nome VARCHAR(500) NOT NULL,
    nome_normalizado VARCHAR(500) NOT NULL,
    preco_usd DECIMAL(10,2) NULL,
    peso_kg DECIMAL(8,3) NULL,
    grupo_slug VARCHAR(100) NOT NULL,
    imposto_local_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
    unidades_no_kit INT UNSIGNED NOT NULL DEFAULT 1,
    foto_url VARCHAR(500) NULL,
    disponivel TINYINT(1) NOT NULL DEFAULT 1,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grupo (grupo_slug),
    INDEX idx_nome_norm (nome_normalizado(100)),
    INDEX idx_preco (preco_usd),
    INDEX idx_disponivel (disponivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de cancelamentos via copiloto
CREATE TABLE IF NOT EXISTS copiloto_cancelamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_solicitacao VARCHAR(50) NOT NULL UNIQUE,
    numero_pedido VARCHAR(50) NOT NULL,
    usuario_id INT UNSIGNED NULL,
    status ENUM('aguardando_revisao','autorizado','recusado','processado','erro') NOT NULL DEFAULT 'aguardando_revisao',
    solicitado_via VARCHAR(50) NOT NULL DEFAULT 'copiloto',
    valor_pago_usd DECIMAL(10,2) NOT NULL,
    taxa_cancelamento_usd DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    valor_reembolso_usd DECIMAL(10,2) NOT NULL,
    valor_reembolso_brl DECIMAL(10,2) NULL,
    metodo_reembolso VARCHAR(50) NULL,
    referencia_pagamento VARCHAR(255) NULL,
    reembolso_id VARCHAR(255) NULL,
    motivo_recusa TEXT NULL,
    itens JSON NULL,
    solicitado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processado_em DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_numero_pedido (numero_pedido),
    INDEX idx_solicitado_em (solicitado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
