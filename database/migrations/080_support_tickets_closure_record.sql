-- Closure record fields for support_tickets

ALTER TABLE support_tickets
    ADD COLUMN closure_decision LONGTEXT NULL AFTER closed_at,
    ADD COLUMN closed_by_type VARCHAR(20) NULL AFTER closure_decision,
    ADD COLUMN closed_by_user_id INT NULL AFTER closed_by_type;
