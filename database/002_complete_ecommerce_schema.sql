-- Schema completo para e-commerce logístico internacional
-- Use brz_logistics;

-- Tabela de usuários e perfis
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(50),
    documento VARCHAR(50) UNIQUE NOT NULL,
    perfil ENUM('admin', 'suporte', 'vendedor', 'cliente') NOT NULL DEFAULT 'cliente',
    status ENUM('ativo', 'inativo', 'bloqueado') DEFAULT 'ativo',
    email_verificado BOOLEAN DEFAULT FALSE,
    ultimo_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de endereços
CREATE TABLE enderecos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('cobranca', 'entrega') NOT NULL,
    cep VARCHAR(20) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100) NOT NULL,
    estado VARCHAR(50) NOT NULL,
    pais VARCHAR(50) DEFAULT 'BR',
    principal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de configurações de moeda e câmbio
CREATE TABLE configuracoes_moeda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moeda_origem VARCHAR(3) NOT NULL DEFAULT 'USD',
    moeda_destino VARCHAR(3) NOT NULL DEFAULT 'BRL',
    taxa_conversao DECIMAL(10,6) NOT NULL DEFAULT 5.500000,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    atualizado_por INT,
    FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
);

-- Tabela de categorias de produtos
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    descricao TEXT,
    tipo_fiscal VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de produtos (enhanced)
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(100) UNIQUE NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    categoria_id INT,
    peso DECIMAL(8,3) NOT NULL,
    comprimento DECIMAL(8,2),
    largura DECIMAL(8,2),
    altura DECIMAL(8,2),
    valor DECIMAL(12,2) NOT NULL,
    moeda VARCHAR(3) NOT NULL DEFAULT 'USD',
    tipo_fiscal VARCHAR(100),
    estoque INT DEFAULT 0,
    estoque_minimo INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

-- Tabela de carrinhos
CREATE TABLE carrinhos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    session_id VARCHAR(255),
    moeda VARCHAR(3) NOT NULL DEFAULT 'USD',
    taxa_conversao DECIMAL(10,6),
    frete_manual DECIMAL(10,2) DEFAULT 0,
    taxa_servico DECIMAL(10,2) DEFAULT 0,
    subtotal_produtos DECIMAL(12,2) DEFAULT 0,
    valor_impostos DECIMAL(12,2) DEFAULT 0,
    valor_total DECIMAL(12,2) DEFAULT 0,
    peso_total DECIMAL(8,3) DEFAULT 0,
    expira_em TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de itens do carrinho
CREATE TABLE carrinho_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrinho_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    valor_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carrinho_id) REFERENCES carrinhos(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

-- Tabela de pedidos (enhanced)
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_pedido VARCHAR(50) UNIQUE NOT NULL,
    usuario_id INT NOT NULL,
    endereco_entrega_id INT NOT NULL,
    endereco_cobranca_id INT NOT NULL,
    
    -- Dados do pedido
    moeda_original VARCHAR(3) NOT NULL,
    taxa_conversao_utilizada DECIMAL(10,6) NOT NULL,
    
    -- Valores
    subtotal_produtos DECIMAL(12,2) NOT NULL,
    valor_frete DECIMAL(12,2) DEFAULT 0,
    taxa_servico DECIMAL(12,2) DEFAULT 0,
    valor_impostos DECIMAL(12,2) DEFAULT 0,
    valor_total DECIMAL(12,2) NOT NULL,
    valor_total_brl DECIMAL(12,2),
    
    -- Dados de pagamento
    payment_gateway VARCHAR(50),
    payment_id VARCHAR(255),
    payment_status ENUM('pending', 'approved', 'rejected', 'refunded') DEFAULT 'pending',
    pago_em TIMESTAMP NULL,
    
    -- Status logístico
    status ENUM('pago', 'aguardando_processamento', 'consolidado', 'rascunho_etiqueta', 'etiqueta_efetivada', 'enviado', 'aguardando_lib_alfandegaria', 'finalizacao_embalagem', 'entrega_finalizada') DEFAULT 'pago',
    
    -- Consolidação
    pedido_consolidado_pai INT NULL,
    consolidado_em TIMESTAMP NULL,
    
    -- Etiqueta
    etiqueta_codigo VARCHAR(100) NULL,
    etiqueta_status ENUM('nao_gerada', 'rascunho', 'efetivada') DEFAULT 'nao_gerada',
    etiqueta_gerada_em TIMESTAMP NULL,
    etiqueta_efetivada_em TIMESTAMP NULL,
    
    -- Dados logísticos
    peso_total DECIMAL(8,3) NOT NULL,
    peso_cobrado DECIMAL(8,3),
    tracking_code VARCHAR(100) NULL,
    transportadora VARCHAR(100) NULL,
    
    -- Auditoria
    criado_por INT NOT NULL,
    atualizado_por INT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (endereco_entrega_id) REFERENCES enderecos(id),
    FOREIGN KEY (endereco_cobranca_id) REFERENCES enderecos(id),
    FOREIGN KEY (pedido_consolidado_pai) REFERENCES pedidos(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
);

-- Tabela de itens do pedido
CREATE TABLE pedido_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    sku VARCHAR(100) NOT NULL,
    nome_produto VARCHAR(255) NOT NULL,
    quantidade INT NOT NULL,
    valor_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

-- Tabela de histórico de status do pedido
CREATE TABLE pedido_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    status_anterior VARCHAR(50),
    status_novo VARCHAR(50) NOT NULL,
    observacao TEXT,
    alterado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (alterado_por) REFERENCES usuarios(id)
);

-- Tabela de eventos do sistema
CREATE TABLE eventos_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de webhooks
CREATE TABLE webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    evento_id INT NOT NULL,
    metodo ENUM('GET', 'POST', 'PUT') DEFAULT 'POST',
    headers JSON,
    payload_template JSON,
    ativo BOOLEAN DEFAULT TRUE,
    retry_count INT DEFAULT 3,
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos_sistema(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

-- Tabela de disparos de webhooks
CREATE TABLE webhook_disparos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    pedido_id INT,
    payload JSON,
    response_code INT,
    response_body TEXT,
    status ENUM('sucesso', 'erro', 'pendente') DEFAULT 'pendente',
    tentativas INT DEFAULT 0,
    proxima_tentativa TIMESTAMP NULL,
    disparado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id),
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
);

-- Tabela de templates de e-mail
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    assunto VARCHAR(255) NOT NULL,
    corpo_html TEXT NOT NULL,
    corpo_texto TEXT,
    variáveis JSON,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos_sistema(id)
);

-- Tabela de histórico de e-mails
CREATE TABLE email_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT,
    pedido_id INT,
    usuario_id INT,
    para VARCHAR(255) NOT NULL,
    assunto VARCHAR(255) NOT NULL,
    corpo TEXT,
    status ENUM('enviado', 'erro', 'pendente') DEFAULT 'pendente',
    erro_mensagem TEXT,
    enviado_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES email_templates(id),
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de logs de auditoria
CREATE TABLE auditoria_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    acao VARCHAR(100) NOT NULL,
    tabela VARCHAR(100),
    registro_id INT,
    valores_antigos JSON,
    valores_novos JSON,
    ip VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de configurações do sistema
CREATE TABLE configuracoes_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao TEXT,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    atualizado_por INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
);

-- Tabela de consolidações
CREATE TABLE consolidacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_consolidacao VARCHAR(50) UNIQUE NOT NULL,
    usuario_id INT NOT NULL,
    pedidos_ids JSON NOT NULL,
    status ENUM('aberta', 'fechada') DEFAULT 'aberta',
    peso_total DECIMAL(8,3),
    valor_total DECIMAL(12,2),
    economia_frete DECIMAL(12,2) DEFAULT 0,
    observacoes TEXT,
    criado_por INT NOT NULL,
    fechada_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

-- Tabela de suporte
CREATE TABLE suporte_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pedido_id INT,
    assunto VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    status ENUM('aberto', 'em_andamento', 'resolvido', 'fechado') DEFAULT 'aberto',
    prioridade ENUM('baixa', 'media', 'alta', 'urgente') DEFAULT 'media',
    atendente_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (atendente_id) REFERENCES usuarios(id)
);

-- Tabela de mensagens de suporte
CREATE TABLE suporte_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensagem TEXT NOT NULL,
    anexo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES suporte_tickets(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Índices para performance
CREATE INDEX idx_pedidos_usuario ON pedidos(usuario_id);
CREATE INDEX idx_pedidos_status ON pedidos(status);
CREATE INDEX idx_pedidos_codigo ON pedidos(codigo_pedido);
CREATE INDEX idx_carrinhos_session ON carrinhos(session_id);
CREATE INDEX idx_carrinhos_usuario ON carrinhos(usuario_id);
CREATE INDEX idx_auditoria_usuario ON auditoria_logs(usuario_id);
CREATE INDEX idx_auditoria_acao ON auditoria_logs(acao);
CREATE INDEX idx_webhook_disparos_status ON webhook_disparos(status);

-- Inserir dados iniciais
INSERT INTO usuarios (nome, email, senha, documento, perfil, status) VALUES
('Admin Master', 'admin@brzlogistics.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '12345678900', 'admin', 'ativo');

INSERT INTO configuracoes_moeda (moeda_origem, moeda_destino, taxa_conversao) VALUES
('USD', 'BRL', 5.500000),
('BRL', 'USD', 0.181818);

INSERT INTO eventos_sistema (nome, descricao) VALUES
('pedido_criado', 'Pedido criado no sistema'),
('pagamento_aprovado', 'Pagamento aprovado'),
('pedido_consolidado', 'Pedido consolidado'),
('rascunho_etiqueta_gerado', 'Rascunho de etiqueta gerado'),
('etiqueta_efetivada', 'Etiqueta efetivada'),
('pedido_enviado', 'Pedido enviado'),
('status_alfandegario', 'Atualização de status alfandegário'),
('pedido_finalizado', 'Pedido finalizado');

INSERT INTO configuracoes_sistema (chave, valor, descricao, tipo) VALUES
('taxa_servico_usd_por_kg', '39.00', 'Taxa de serviço em USD por quilo', 'number'),
('icms_aliquota', '60.00', 'Alíquota do ICMS em percentual', 'number'),
('ipi_aliquota', '20.00', 'Alíquota do IPI em percentual', 'number'),
('asaas_api_key', '', 'Chave API do Asaas', 'string'),
('stripe_api_key', '', 'Chave API do Stripe', 'string'),
('consolidacao_ativa', 'true', 'Permitir consolidação de pedidos', 'boolean'),
('termo_legal_versao', '1.0', 'Versão do termo legal', 'string');

INSERT INTO categorias (nome, slug, descricao, tipo_fiscal) VALUES
('Eletrônicos', 'eletronicos', 'Produtos eletrônicos em geral', 'NCM 8517'),
('Vestuário', 'vestuario', 'Roupas e acessórios', 'NCM 6203'),
('Acessórios', 'acessorios', 'Acessórios diversos', 'NCM 8518'),
('Casa e Jardim', 'casa-jardim', 'Artigos para casa e jardim', 'NCM 7323');

-- Inserir produtos de exemplo
INSERT INTO produtos (sku, nome, descricao, categoria_id, peso, comprimento, largura, altura, valor, moeda, tipo_fiscal, estoque) VALUES
('IPHONE15-256', 'iPhone 15 Pro 256GB', 'Smartphone Apple iPhone 15 Pro com 256GB', 1, 0.187, 15.0, 7.5, 0.8, 999.00, 'USD', 'NCM 8517.13.00', 50),
('AIRPODS-PRO', 'AirPods Pro 2ª Geração', 'Fones de ouvido sem fio com noise cancelling', 1, 0.056, 6.0, 4.5, 2.5, 249.00, 'USD', 'NCM 8518.30.00', 100),
('MACBOOK-AIR', 'MacBook Air M2 13"', 'Notebook Apple MacBook Air com chip M2', 1, 1.240, 30.0, 21.0, 1.5, 1099.00, 'USD', 'NCM 8471.30.00', 30),
('IPAD-AIR', 'iPad Air 10.9"', 'Tablet Apple iPad Air de 10.9 polegadas', 1, 0.461, 25.0, 17.5, 0.6, 599.00, 'USD', 'NCM 8471.30.00', 40);
