-- Track last seen for internal vendor chat (suporte/admin viewer)

CREATE TABLE IF NOT EXISTS support_ticket_vendor_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    vendedor_id INT NOT NULL,
    viewer_type VARCHAR(20) NOT NULL, -- 'suporte'
    viewer_user_id INT NOT NULL,
    last_seen_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_vendor_view (ticket_id, vendedor_id, viewer_type, viewer_user_id),
    KEY idx_vendor_view_ticket (ticket_id),
    KEY idx_vendor_view_vendor (vendedor_id),
    CONSTRAINT fk_vendor_views_ticket_id FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_vendor_views_vendor_id FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
