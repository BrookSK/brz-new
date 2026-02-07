-- Internal chat between suporte/admin and vendedor per ticket

CREATE TABLE IF NOT EXISTS support_ticket_vendor_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    vendedor_id INT NOT NULL,
    sender_type VARCHAR(20) NOT NULL, -- 'suporte' | 'vendedor'
    sender_user_id INT NULL,
    mensagem LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket_vendor_msg_ticket (ticket_id),
    KEY idx_ticket_vendor_msg_vendor (vendedor_id),
    KEY idx_ticket_vendor_msg_created (created_at),
    CONSTRAINT fk_ticket_vendor_msg_ticket_id FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_vendor_msg_vendor_id FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
