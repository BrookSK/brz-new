-- Create support tickets tables

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pedido_id INT NULL,
    assunto VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    prioridade VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    KEY idx_ticket_usuario (usuario_id),
    KEY idx_ticket_pedido (pedido_id),
    KEY idx_ticket_status (status),
    CONSTRAINT fk_support_tickets_usuario_id FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    autor_tipo VARCHAR(20) NOT NULL, -- 'cliente' | 'admin'
    autor_usuario_id INT NULL,
    mensagem TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket_messages_ticket (ticket_id),
    KEY idx_ticket_messages_created (created_at),
    CONSTRAINT fk_support_ticket_messages_ticket_id FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
