-- Histórico de splits: guarda snapshot dos itens antes de separar
CREATE TABLE IF NOT EXISTS pedido_split_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_original_id INT NOT NULL,
    pedido_novo_id INT NOT NULL,
    itens_originais JSON NOT NULL COMMENT 'Snapshot completo dos itens do pedido ANTES do split',
    itens_separados JSON NOT NULL COMMENT 'IDs e dados dos itens que foram movidos',
    usuario_id INT NULL COMMENT 'Admin que executou o split',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedido_original (pedido_original_id),
    INDEX idx_pedido_novo (pedido_novo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
