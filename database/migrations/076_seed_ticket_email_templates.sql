-- Seed email templates and system events for support tickets

-- Garantir que eventos_sistema existe com o schema correto (mesma def. da 192).
CREATE TABLE IF NOT EXISTS eventos_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_eventos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Corrigir estado ruim em bancos onde a tabela foi criada à mão sem
-- AUTO_INCREMENT: nesse caso os INSERT tentam gravar id=0 e colidem na PK
-- (erro 1062 "Duplicate entry '0'"). Promove a coluna id a AUTO_INCREMENT
-- quando ainda não for, sem apagar dados (a idempotência dos INSERT abaixo é
-- garantida pelo WHERE NOT EXISTS por `nome`).
SET @db := DATABASE();
SET @id_auto := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'eventos_sistema'
    AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%'
);
SET @sql := IF(@id_auto = 0,
  'ALTER TABLE eventos_sistema MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Eventos: ticket_created, ticket_admin_reply

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at)
SELECT 'ticket_created', 'Ticket criado (confirmação de recebimento ao cliente)', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'ticket_created');

INSERT INTO eventos_sistema (nome, descricao, ativo, created_at)
SELECT 'ticket_admin_reply', 'Resposta do admin no ticket (notificação ao cliente)', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM eventos_sistema WHERE nome = 'ticket_admin_reply');

-- Templates
INSERT INTO email_templates (evento_id, nome, assunto, corpo_html, ativo, created_at, updated_at)
SELECT e.id,
       'ticket_created',
       'Recebemos seu ticket #{{ticket_id}} (Pedido #{{codigo_pedido}})',
       'Olá {{nome}},<br><br>Recebemos sua solicitação de suporte e nosso time já vai analisar.<br><br><strong>Ticket:</strong> #{{ticket_id}}<br><strong>Pedido:</strong> #{{codigo_pedido}}<br><strong>Motivo:</strong> {{ticket_motivo}}<br><strong>Assunto:</strong> {{ticket_assunto}}<br><br>Você pode acompanhar e responder pelo link:<br><a href="{{ticket_url}}" target="_blank">{{ticket_url}}</a><br><br>Atenciosamente,<br>Braziliana',
       1,
       NOW(),
       NOW()
FROM eventos_sistema e
WHERE e.nome = 'ticket_created'
  AND NOT EXISTS (SELECT 1 FROM email_templates t WHERE t.nome = 'ticket_created');

INSERT INTO email_templates (evento_id, nome, assunto, corpo_html, ativo, created_at, updated_at)
SELECT e.id,
       'ticket_admin_reply',
       'Atualização no seu ticket #{{ticket_id}} (Pedido #{{codigo_pedido}})',
       'Olá {{nome}},<br><br>Você recebeu uma nova resposta no seu ticket.<br><br><strong>Ticket:</strong> #{{ticket_id}}<br><strong>Pedido:</strong> #{{codigo_pedido}}<br><strong>Respondido por:</strong> {{admin_nome}}<br><br><strong>Mensagem:</strong><br>{{ticket_mensagem}}<br><br>Para responder e acompanhar o atendimento, acesse:<br><a href="{{ticket_url}}" target="_blank">{{ticket_url}}</a><br><br>Atenciosamente,<br>Braziliana',
       1,
       NOW(),
       NOW()
FROM eventos_sistema e
WHERE e.nome = 'ticket_admin_reply'
  AND NOT EXISTS (SELECT 1 FROM email_templates t WHERE t.nome = 'ticket_admin_reply');
