-- ============================================================
-- Migration 160: Live Shopping Module (TikTok-style)
-- ============================================================

-- Tabela principal de lives
CREATE TABLE IF NOT EXISTS lives (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    cover_url VARCHAR(500) NULL,
    scheduled_at DATETIME NULL,
    ingest_method ENUM('webrtc','obs') NOT NULL DEFAULT 'webrtc',
    
    -- Cloudflare Stream
    cf_live_input_id VARCHAR(100) NULL,
    cf_rtmps_url VARCHAR(500) NULL,
    cf_rtmps_key VARCHAR(255) NULL,
    cf_webrtc_url VARCHAR(500) NULL,
    cf_playback_url VARCHAR(500) NULL,
    
    -- Status
    status ENUM('scheduled','live','ended') NOT NULL DEFAULT 'scheduled',
    live_started_at DATETIME NULL,
    live_ended_at DATETIME NULL,
    
    -- Freemium (opcional)
    free_seconds INT NOT NULL DEFAULT 0,
    unlock_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    
    -- Live Shopping
    current_featured_product_id INT UNSIGNED NULL,
    
    -- Gravação
    recording_url VARCHAR(500) NULL,
    
    -- Métricas
    viewers_peak INT NOT NULL DEFAULT 0,
    viewers_current INT NOT NULL DEFAULT 0,
    likes_count INT NOT NULL DEFAULT 0,
    shares_count INT NOT NULL DEFAULT 0,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_lives_status (status),
    INDEX idx_lives_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produtos vinculados a uma live
CREATE TABLE IF NOT EXISTS live_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    position INT NOT NULL DEFAULT 0,
    override_name VARCHAR(255) NULL,
    override_price DECIMAL(10,2) NULL,
    override_weight DECIMAL(8,3) NULL,
    override_image VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_live_products_live_pos (live_id, position),
    UNIQUE KEY uk_live_product (live_id, product_id),
    CONSTRAINT fk_lp_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de produtos destacados durante a live
CREATE TABLE IF NOT EXISTS live_featured_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    
    INDEX idx_featured_live (live_id, started_at),
    CONSTRAINT fk_lfe_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat ao vivo
CREATE TABLE IF NOT EXISTS live_chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content VARCHAR(500) NOT NULL,
    hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_chat_live_time (live_id, created_at),
    CONSTRAINT fk_lcm_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Curtidas (agregado por usuário)
CREATE TABLE IF NOT EXISTS live_likes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    count INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_live_like_user (live_id, user_id),
    CONSTRAINT fk_ll_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compartilhamentos
CREATE TABLE IF NOT EXISTS live_shares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL DEFAULT 'link',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_shares_live (live_id),
    CONSTRAINT fk_ls_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Banimentos de chat
CREATE TABLE IF NOT EXISTS live_bans (
    live_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (live_id, user_id),
    CONSTRAINT fk_lb_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progresso de visualização (freemium)
CREATE TABLE IF NOT EXISTS live_watch_progress (
    live_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    seconds_watched INT NOT NULL DEFAULT 0,
    last_heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (live_id, user_id),
    CONSTRAINT fk_lwp_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Desbloqueios pagos (freemium)
CREATE TABLE IF NOT EXISTS live_paid_unlocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    gateway_payment_id VARCHAR(255) NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_unlock (live_id, user_id),
    CONSTRAINT fk_lpu_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedidos feitos durante a live
CREATE TABLE IF NOT EXISTS live_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    featured_event_id INT UNSIGNED NULL,
    idempotency_key VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_live_orders_live (live_id),
    UNIQUE KEY uk_idempotency (idempotency_key),
    CONSTRAINT fk_lo_live FOREIGN KEY (live_id) REFERENCES lives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uso mensal de streaming (cota)
CREATE TABLE IF NOT EXISTS streaming_usage (
    `year_month` CHAR(7) NOT NULL PRIMARY KEY,
    `minutes_streamed` INT NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Métodos de pagamento tokenizados dos clientes
CREATE TABLE IF NOT EXISTS customer_payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    token VARCHAR(255) NOT NULL,
    brand VARCHAR(50) NULL,
    last_four CHAR(4) NULL,
    holder_name VARCHAR(255) NULL,
    expiry_month TINYINT UNSIGNED NULL,
    expiry_year SMALLINT UNSIGNED NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_cpm_user (user_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar flag de elegibilidade para live nos produtos
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS is_live_eligible TINYINT(1) NOT NULL DEFAULT 0;

-- Seed de configurações do módulo Lives
INSERT IGNORE INTO configuracoes_sistema (chave, valor, descricao, tipo) VALUES
('lives_modo_operacao', 'desligado', 'Modo de operação do módulo Lives (online/teste/desligado)', 'string'),
('lives_minutos_inclusos', '300', 'Cota mensal de minutos de streaming inclusos', 'number'),
('lives_modo_excedente', 'block', 'Ação ao exceder cota (block/charge)', 'string'),
('lives_preco_minuto_excedente', '0.00', 'Preço por minuto excedente de streaming', 'number'),
('lives_cf_account_id', '', 'Cloudflare Stream Account ID', 'string'),
('lives_cf_api_token', '', 'Cloudflare Stream API Token (criptografado)', 'string'),
('lives_cf_stream_subdomain', '', 'Cloudflare Stream Subdomain', 'string');
