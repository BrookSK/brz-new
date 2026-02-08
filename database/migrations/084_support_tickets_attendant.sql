-- Track current attendant (support/admin) for support tickets

ALTER TABLE support_tickets
    ADD COLUMN attendant_type VARCHAR(20) NULL,
    ADD COLUMN attendant_user_id INT NULL,
    ADD KEY idx_support_tickets_attendant (attendant_type, attendant_user_id),
    ADD CONSTRAINT fk_support_tickets_attendant_user_id FOREIGN KEY (attendant_user_id) REFERENCES usuarios(id) ON DELETE SET NULL;
