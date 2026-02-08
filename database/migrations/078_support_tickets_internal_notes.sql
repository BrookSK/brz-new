-- Add internal notes field to support_tickets (admin-only)

ALTER TABLE support_tickets
    ADD COLUMN internal_notes LONGTEXT NULL AFTER motivo;
