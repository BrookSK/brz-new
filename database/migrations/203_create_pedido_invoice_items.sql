-- Itens do invoice - dados editáveis pelo cliente que vão na etiqueta/declaração aduaneira
-- O campo nome_produto é o que aparece na etiqueta como descrição do produto

CREATE TABLE IF NOT EXISTS pedido_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    pedido_item_id INT NULL,
    pacote_id INT NULL,
    nome_produto VARCHAR(255) NOT NULL,
    ncm VARCHAR(20) NULL,
    declaration_value DECIMAL(10,2) NOT NULL,
    peso_kg DECIMAL(6,3) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    tem_bateria ENUM('S','N') DEFAULT 'N',
    tem_perfume ENUM('S','N') DEFAULT 'N',
    foto_url TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
