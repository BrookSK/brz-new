-- Adicionar usuário admin
INSERT INTO usuarios (nome, email, senha, documento, perfil, status, email_verificado) VALUES
('Onsolutions', 'admin@onsolutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '12345678900', 'admin', 'ativo', TRUE);

-- Tabela de fotos dos produtos
CREATE TABLE produto_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    arquivo_original VARCHAR(255),
    legenda VARCHAR(255),
    ordem INT DEFAULT 0,
    principal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);

-- Adicionar colunas para fotos na tabela de produtos
ALTER TABLE produtos 
ADD COLUMN foto_principal VARCHAR(255) NULL AFTER descricao,
ADD COLUMN galeria_fotos JSON NULL AFTER foto_principal;

-- Índices para performance
CREATE INDEX idx_produto_fotos_produto ON produto_fotos(produto_id);
CREATE INDEX idx_produto_fotos_ordem ON produto_fotos(produto_id, ordem);

-- Atualizar produtos existentes com fotos de exemplo
UPDATE produtos SET foto_principal = 'https://via.placeholder.com/600x600/4e73df/ffffff?text=iPhone+15+Pro' WHERE sku = 'IPHONE15-256';
UPDATE produtos SET foto_principal = 'https://via.placeholder.com/600x600/1cc88a/ffffff?text=AirPods+Pro' WHERE sku = 'AIRPODS-PRO';
UPDATE produtos SET foto_principal = 'https://via.placeholder.com/600x600/36b9cc/ffffff?text=MacBook+Air' WHERE sku = 'MACBOOK-AIR';
UPDATE produtos SET foto_principal = 'https://via.placeholder.com/600x600/f6c23e/ffffff?text=iPad+Air' WHERE sku = 'IPAD-AIR';

-- Inserir fotos de exemplo para os produtos
INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, legenda, ordem, principal) VALUES
(1, 'iphone15-pro-front.jpg', 'iphone15-pro-front.jpg', 'iPhone 15 Pro - Frontal', 1, TRUE),
(1, 'iphone15-pro-back.jpg', 'iphone15-pro-back.jpg', 'iPhone 15 Pro - Traseira', 2, FALSE),
(1, 'iphone15-pro-side.jpg', 'iphone15-pro-side.jpg', 'iPhone 15 Pro - Lateral', 3, FALSE),
(1, 'iphone15-pro-box.jpg', 'iphone15-pro-box.jpg', 'iPhone 15 Pro - Caixa', 4, FALSE),

(2, 'airpods-pro-case.jpg', 'airpods-pro-case.jpg', 'AirPods Pro - Estojo', 1, TRUE),
(2, 'airpods-pro-pods.jpg', 'airpods-pro-pods.jpg', 'AirPods Pro - Fones', 2, FALSE),
(2, 'airpods-pro-tips.jpg', 'airpods-pro-tips.jpg', 'AirPods Pro - Ponteiras', 3, FALSE),

(3, 'macbook-air-open.jpg', 'macbook-air-open.jpg', 'MacBook Air - Aberto', 1, TRUE),
(3, 'macbook-air-close.jpg', 'macbook-air-close.jpg', 'MacBook Air - Fechado', 2, FALSE),
(3, 'macbook-air-ports.jpg', 'macbook-air-ports.jpg', 'MacBook Air - Portas', 3, FALSE),

(4, 'ipad-air-screen.jpg', 'ipad-air-screen.jpg', 'iPad Air - Tela', 1, TRUE),
(4, 'ipad-air-back.jpg', 'ipad-air-back.jpg', 'iPad Air - Traseira', 2, FALSE),
(4, 'ipad-air-pen.jpg', 'ipad-air-pen.jpg', 'iPad Air - Apple Pencil', 3, FALSE);
