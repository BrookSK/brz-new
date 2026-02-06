-- Track last view per ticket for notification badges (unread updates)

CREATE TABLE IF NOT EXISTS support_ticket_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    viewer_type VARCHAR(20) NOT NULL, -- 'cliente' | 'admin'
    viewer_user_id INT NULL,
    last_seen_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_ticket_view (ticket_id, viewer_type, viewer_user_id),
    KEY idx_ticket_view_ticket (ticket_id),
    CONSTRAINT fk_ticket_views_ticket_id FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
