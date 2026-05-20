-- Migration: Inserir eventos de Carnê na tabela eventos_sistema e criar templates de webhook
-- Esses templates ficam visíveis em Configurações > Notificações > Webhook

-- Garantir que a tabela eventos_sistema existe
CREATE TABLE IF NOT EXISTS eventos_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_eventos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garantir que a tabela webhooks existe
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL DEFAULT '',
    evento_id INT NULL,
    metodo VARCHAR(10) NOT NULL DEFAULT 'POST',
    headers TEXT NULL,
    payload_template LONGTEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    retry_count INT NOT NULL DEFAULT 3,
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_webhooks_evento_id (evento_id),
    INDEX idx_webhooks_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garantir que a tabela webhook_disparos existe (logs)
CREATE TABLE IF NOT EXISTS webhook_disparos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NULL,
    pedido_id INT NULL,
    payload LONGTEXT NULL,
    response_code INT NULL,
    response_body LONGTEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pendente',
    disparado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook_disparos_webhook_id (webhook_id),
    INDEX idx_webhook_disparos_disparado_em (disparado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== EVENTOS DE CARNÊ ==========

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) 
SELECT 'carne_criado', 'Carnê criado para o cliente', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'carne_criado');

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) 
SELECT 'carne_cobranca', 'Cobrança de parcela do carnê (parcela em atraso)', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'carne_cobranca');

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) 
SELECT 'carne_parcela_proxima_vencimento', 'Lembrete de parcela próxima do vencimento', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'carne_parcela_proxima_vencimento');

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) 
SELECT 'carne_pagamento_confirmado', 'Pagamento de parcela confirmado', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'carne_pagamento_confirmado');

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) 
SELECT 'carne_quitado', 'Carnê totalmente quitado (todas parcelas pagas)', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'carne_quitado');

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) 
SELECT 'carne_envio_liberado', 'Envio do produto liberado após quitação', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'carne_envio_liberado');

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) 
SELECT 'carne_aviso_cancelamento', 'Aviso de cancelamento por inadimplência', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'carne_aviso_cancelamento');

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) 
SELECT 'carne_cancelado', 'Carnê cancelado por inadimplência', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'carne_cancelado');

-- ========== TEMPLATES DE WEBHOOK PARA CADA EVENTO ==========

-- carne_criado
INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, created_at, updated_at)
SELECT 'carne_criado', '', e.id, 'POST', '{"Content-Type":"application/json"}',
'{"evento":"carne_criado","carne_id":"{{carne_id}}","pedido_id":"{{pedido_id}}","cliente_nome":"{{cliente_nome}}","cliente_email":"{{cliente_email}}","quantidade_parcelas":"{{quantidade_parcelas}}","total_geral":"{{total_geral}}","data_criacao":"{{data_criacao}}"}',
0, 3, NOW(), NOW()
FROM eventos_sistema e WHERE e.nome = 'carne_criado'
AND NOT EXISTS (SELECT 1 FROM webhooks w WHERE w.evento_id = e.id);

-- carne_cobranca
INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, created_at, updated_at)
SELECT 'carne_cobranca', '', e.id, 'POST', '{"Content-Type":"application/json"}',
'{"evento":"carne_cobranca","carne_id":"{{carne_id}}","pedido_id":"{{pedido_id}}","cliente_nome":"{{cliente_nome}}","cliente_email":"{{cliente_email}}","numero_parcela":"{{numero_parcela}}","total_parcelas":"{{total_parcelas}}","valor_parcela":"{{valor_parcela}}","valor_produtos":"{{valor_produtos}}","valor_taxas":"{{valor_taxas}}","vencimento":"{{vencimento}}"}',
0, 3, NOW(), NOW()
FROM eventos_sistema e WHERE e.nome = 'carne_cobranca'
AND NOT EXISTS (SELECT 1 FROM webhooks w WHERE w.evento_id = e.id);

-- carne_parcela_proxima_vencimento
INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, created_at, updated_at)
SELECT 'carne_parcela_proxima_vencimento', '', e.id, 'POST', '{"Content-Type":"application/json"}',
'{"evento":"carne_parcela_proxima_vencimento","carne_id":"{{carne_id}}","pedido_id":"{{pedido_id}}","cliente_nome":"{{cliente_nome}}","cliente_email":"{{cliente_email}}","numero_parcela":"{{numero_parcela}}","valor_parcela":"{{valor_parcela}}","vencimento":"{{vencimento}}"}',
0, 3, NOW(), NOW()
FROM eventos_sistema e WHERE e.nome = 'carne_parcela_proxima_vencimento'
AND NOT EXISTS (SELECT 1 FROM webhooks w WHERE w.evento_id = e.id);

-- carne_pagamento_confirmado
INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, created_at, updated_at)
SELECT 'carne_pagamento_confirmado', '', e.id, 'POST', '{"Content-Type":"application/json"}',
'{"evento":"carne_pagamento_confirmado","carne_id":"{{carne_id}}","pedido_id":"{{pedido_id}}","cliente_nome":"{{cliente_nome}}","cliente_email":"{{cliente_email}}","numero_parcela":"{{numero_parcela}}","valor_parcela":"{{valor_parcela}}","parcelas_pagas":"{{parcelas_pagas}}","total_parcelas":"{{total_parcelas}}"}',
0, 3, NOW(), NOW()
FROM eventos_sistema e WHERE e.nome = 'carne_pagamento_confirmado'
AND NOT EXISTS (SELECT 1 FROM webhooks w WHERE w.evento_id = e.id);

-- carne_quitado
INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, created_at, updated_at)
SELECT 'carne_quitado', '', e.id, 'POST', '{"Content-Type":"application/json"}',
'{"evento":"carne_quitado","carne_id":"{{carne_id}}","pedido_id":"{{pedido_id}}","cliente_nome":"{{cliente_nome}}","cliente_email":"{{cliente_email}}","total_geral":"{{total_geral}}","quantidade_parcelas":"{{quantidade_parcelas}}"}',
0, 3, NOW(), NOW()
FROM eventos_sistema e WHERE e.nome = 'carne_quitado'
AND NOT EXISTS (SELECT 1 FROM webhooks w WHERE w.evento_id = e.id);

-- carne_envio_liberado
INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, created_at, updated_at)
SELECT 'carne_envio_liberado', '', e.id, 'POST', '{"Content-Type":"application/json"}',
'{"evento":"carne_envio_liberado","carne_id":"{{carne_id}}","pedido_id":"{{pedido_id}}","cliente_nome":"{{cliente_nome}}","cliente_email":"{{cliente_email}}"}',
0, 3, NOW(), NOW()
FROM eventos_sistema e WHERE e.nome = 'carne_envio_liberado'
AND NOT EXISTS (SELECT 1 FROM webhooks w WHERE w.evento_id = e.id);

-- carne_aviso_cancelamento
INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, created_at, updated_at)
SELECT 'carne_aviso_cancelamento', '', e.id, 'POST', '{"Content-Type":"application/json"}',
'{"evento":"carne_aviso_cancelamento","carne_id":"{{carne_id}}","pedido_id":"{{pedido_id}}","cliente_nome":"{{cliente_nome}}","cliente_email":"{{cliente_email}}","parcelas_atrasadas":"{{parcelas_atrasadas}}","dias_atraso":"{{dias_atraso}}"}',
0, 3, NOW(), NOW()
FROM eventos_sistema e WHERE e.nome = 'carne_aviso_cancelamento'
AND NOT EXISTS (SELECT 1 FROM webhooks w WHERE w.evento_id = e.id);

-- carne_cancelado
INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, created_at, updated_at)
SELECT 'carne_cancelado', '', e.id, 'POST', '{"Content-Type":"application/json"}',
'{"evento":"carne_cancelado","carne_id":"{{carne_id}}","pedido_id":"{{pedido_id}}","cliente_nome":"{{cliente_nome}}","cliente_email":"{{cliente_email}}","motivo":"inadimplencia"}',
0, 3, NOW(), NOW()
FROM eventos_sistema e WHERE e.nome = 'carne_cancelado'
AND NOT EXISTS (SELECT 1 FROM webhooks w WHERE w.evento_id = e.id);
