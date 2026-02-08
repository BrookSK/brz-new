-- Ticket-level files (admin future reference)

CREATE TABLE IF NOT EXISTS support_ticket_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    uploader_type VARCHAR(20) NOT NULL, -- 'admin' | 'cliente'
    uploader_user_id INT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    file_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket_files_ticket (ticket_id),
    CONSTRAINT fk_ticket_files_ticket_id FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
