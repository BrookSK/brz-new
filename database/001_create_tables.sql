-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS brz_logistics;
USE brz_logistics;

-- Tabela de Clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    telefone VARCHAR(50),
    documento VARCHAR(50) UNIQUE NOT NULL,
    endereco TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Produtos
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    peso DECIMAL(8,3),
    dimensao VARCHAR(50),
    categoria VARCHAR(100),
    estoque INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Pedidos
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    status ENUM('selecao', 'cobranca', 'despacho', 'transito', 'aduana', 'entrega', 'concluido') DEFAULT 'selecao',
    subtotal DECIMAL(10,2) NOT NULL,
    servicos DECIMAL(10,2) DEFAULT 0,
    impostos DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

-- Tabela de Itens do Pedido
CREATE TABLE pedido_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

-- Tabela de Rastreamento
CREATE TABLE rastreamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    etapa VARCHAR(100) NOT NULL,
    descricao TEXT,
    local VARCHAR(255),
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
);

-- Tabela de Serviços
CREATE TABLE servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    tipo ENUM('despacho', 'translado', 'armazenamento', 'envio') NOT NULL
);

-- Tabela de Impostos
CREATE TABLE impostos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    percentual DECIMAL(5,2) NOT NULL,
    tipo ENUM('importacao', 'estadual', 'federal') NOT NULL,
    base_calculo ENUM('produto', 'servico', 'total') DEFAULT 'produto'
);

-- Inserir dados iniciais
INSERT INTO servicos (nome, descricao, preco, tipo) VALUES
('Despacho para MIA', 'Processamento de despacho para Miami', 150.00, 'despacho'),
('Translado Miami-Brasil', 'Transporte aéreo Miami para Brasil', 350.00, 'translado'),
('Armazenamento', 'Armazenamento em armazém brasileiro', 50.00, 'armazenamento'),
('Envio Correios', 'Envio final via Correios', 25.00, 'envio');

INSERT INTO impostos (nome, percentual, tipo, base_calculo) VALUES
('Imposto de Importação', 60.00, 'importacao', 'produto'),
('ICMS', 17.00, 'estadual', 'total'),
('PIS', 0.65, 'federal', 'produto'),
('COFINS', 3.00, 'federal', 'produto');

INSERT INTO produtos (nome, descricao, preco, peso, categoria, estoque) VALUES
('iPhone 15 Pro', 'Smartphone Apple iPhone 15 Pro 256GB', 1200.00, 0.187, 'Eletrônicos', 50),
('AirPods Pro', 'Fones de ouvido sem fio Apple', 250.00, 0.056, 'Eletrônicos', 100),
('MacBook Air M2', 'Notebook Apple MacBook Air 13" M2', 1300.00, 1.240, 'Eletrônicos', 30),
('iPad Air', 'Tablet Apple iPad Air 10.9"', 600.00, 0.461, 'Eletrônicos', 40);
