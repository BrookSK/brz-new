-- Tabela de invoices de pedidos (fluxo de conferência pelo cliente antes do envio)
-- Admin libera invoice -> Cliente confere/edita dados -> Confirma ou Contesta

CREATE TABLE IF NOT EXISTS pedido_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    status ENUM('liberado','confirmado','contestado') NOT NULL DEFAULT 'liberado',
    contestacao_motivo TEXT NULL,
    confirmado_em TIMESTAMP NULL,
    contestado_em TIMESTAMP NULL,
    liberado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
