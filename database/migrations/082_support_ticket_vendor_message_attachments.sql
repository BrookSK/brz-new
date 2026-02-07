-- Attachments for internal vendor chat messages

CREATE TABLE IF NOT EXISTS support_ticket_vendor_message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    ticket_id INT NOT NULL,
    vendedor_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    file_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_vendor_attach_msg (message_id),
    KEY idx_vendor_attach_ticket (ticket_id),
    KEY idx_vendor_attach_vendor (vendedor_id),
    CONSTRAINT fk_vendor_attach_msg_id FOREIGN KEY (message_id) REFERENCES support_ticket_vendor_messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_vendor_attach_ticket_id FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_vendor_attach_vendor_id FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
