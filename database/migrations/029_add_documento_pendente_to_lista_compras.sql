ALTER TABLE lista_compras
    ADD COLUMN documento_compra_status ENUM('nao_aplica','pendente_upload','ok') NOT NULL DEFAULT 'nao_aplica',
    ADD COLUMN documento_compra_id INT NULL;
