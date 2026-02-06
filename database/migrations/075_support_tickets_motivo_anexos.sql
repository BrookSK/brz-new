-- Add motivo to support_tickets and create attachments table for support_ticket_messages

ALTER TABLE support_tickets
    ADD COLUMN motivo VARCHAR(120) NULL AFTER assunto;

CREATE TABLE IF NOT EXISTS support_ticket_message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    ticket_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket_attach_message (message_id),
    KEY idx_ticket_attach_ticket (ticket_id),
    CONSTRAINT fk_ticket_attach_message_id FOREIGN KEY (message_id) REFERENCES support_ticket_messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_attach_ticket_id FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
