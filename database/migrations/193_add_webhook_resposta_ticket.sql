-- Migration: Adicionar campos para monitorar primeira resposta do ticket via webhook

ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS webhook_aguardando_resposta TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS webhook_resposta_deadline DATETIME NULL;
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS webhook_resposta_enviada TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS webhook_resposta_url VARCHAR(500) NULL;
