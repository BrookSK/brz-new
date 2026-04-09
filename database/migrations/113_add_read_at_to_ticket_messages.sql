-- Indicador de leitura nas mensagens de ticket (estilo WhatsApp)
ALTER TABLE support_ticket_messages
    ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER created_at,
    ADD COLUMN IF NOT EXISTS read_by_user_id INT NULL AFTER read_at;
