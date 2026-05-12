-- ============================================================
-- BEHAVIOR TRACKING - Cookies, Sessions, Events
-- Complementa o mapa de calor e segmentação existentes
-- ============================================================

-- Sessões de visitantes (cookies)
CREATE TABLE IF NOT EXISTS visitor_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    visitor_id VARCHAR(64) NOT NULL,
    session_id VARCHAR(64) NOT NULL,
    usuario_id INT DEFAULT NULL,
    landing_page VARCHAR(500) DEFAULT NULL,
    referrer_url VARCHAR(500) DEFAULT NULL,
    utm_source VARCHAR(100) DEFAULT NULL,
    utm_medium VARCHAR(100) DEFAULT NULL,
    utm_campaign VARCHAR(200) DEFAULT NULL,
    utm_content VARCHAR(200) DEFAULT NULL,
    utm_term VARCHAR(200) DEFAULT NULL,
    device_type ENUM('desktop','mobile','tablet') DEFAULT 'desktop',
    browser VARCHAR(100) DEFAULT NULL,
    os VARCHAR(100) DEFAULT NULL,
    country VARCHAR(50) DEFAULT NULL,
    first_visit_at DATETIME DEFAULT NULL,
    last_activity_at DATETIME DEFAULT NULL,
    pages_viewed INT DEFAULT 0,
    total_time_seconds INT DEFAULT 0,
    converted TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visitor (visitor_id),
    INDEX idx_session (session_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_utm_source (utm_source),
    INDEX idx_utm_campaign (utm_campaign(191)),
    INDEX idx_device (device_type),
    INDEX idx_converted (converted),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eventos comportamentais detalhados
CREATE TABLE IF NOT EXISTS behavior_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    visitor_id VARCHAR(64) NOT NULL,
    session_id VARCHAR(64) NOT NULL,
    usuario_id INT DEFAULT NULL,
    event_type VARCHAR(50) NOT NULL,
    page_url VARCHAR(500) DEFAULT NULL,
    page_type VARCHAR(50) DEFAULT NULL,
    product_id INT DEFAULT NULL,
    category_id INT DEFAULT NULL,
    element_text VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visitor (visitor_id),
    INDEX idx_session (session_id),
    INDEX idx_event_type (event_type),
    INDEX idx_product (product_id),
    INDEX idx_category (category_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Consentimento de cookies (LGPD)
CREATE TABLE IF NOT EXISTS cookie_consents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id VARCHAR(64) NOT NULL,
    usuario_id INT DEFAULT NULL,
    accepted_essential TINYINT(1) DEFAULT 1,
    accepted_analytics TINYINT(1) DEFAULT 0,
    accepted_marketing TINYINT(1) DEFAULT 0,
    policy_version VARCHAR(20) DEFAULT '1.0',
    ip_anonymized VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    consented_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME DEFAULT NULL,
    INDEX idx_visitor (visitor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Score comportamental do visitante
CREATE TABLE IF NOT EXISTS visitor_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id VARCHAR(64) NOT NULL,
    usuario_id INT DEFAULT NULL,
    score INT DEFAULT 0,
    classificacao ENUM('frio','morno','quente','muito_quente') DEFAULT 'frio',
    total_visitas INT DEFAULT 0,
    total_pageviews INT DEFAULT 0,
    total_product_views INT DEFAULT 0,
    total_add_to_cart INT DEFAULT 0,
    total_checkouts INT DEFAULT 0,
    total_purchases INT DEFAULT 0,
    ultima_visita DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_visitor (visitor_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_score (score),
    INDEX idx_classificacao (classificacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vínculo visitante anônimo → cliente
CREATE TABLE IF NOT EXISTS visitor_customer_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id VARCHAR(64) NOT NULL,
    usuario_id INT NOT NULL,
    linked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_visitor_user (visitor_id, usuario_id),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
