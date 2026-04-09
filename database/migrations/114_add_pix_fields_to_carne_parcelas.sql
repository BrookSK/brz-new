-- Adicionar campos de PIX às parcelas do carnê (primeira parcela usa PIX)
ALTER TABLE carne_parcelas ADD COLUMN metodo_pagamento ENUM('boleto','pix') NOT NULL DEFAULT 'boleto' AFTER status;
ALTER TABLE carne_parcelas ADD COLUMN pix_produtos_qrcode TEXT NULL AFTER boleto_produtos_id_externo;
ALTER TABLE carne_parcelas ADD COLUMN pix_produtos_payload TEXT NULL AFTER pix_produtos_qrcode;
ALTER TABLE carne_parcelas ADD COLUMN pix_produtos_expiracao DATETIME NULL AFTER pix_produtos_payload;
ALTER TABLE carne_parcelas ADD COLUMN pix_taxas_qrcode TEXT NULL AFTER boleto_taxas_id_externo;
ALTER TABLE carne_parcelas ADD COLUMN pix_taxas_payload TEXT NULL AFTER pix_taxas_qrcode;
ALTER TABLE carne_parcelas ADD COLUMN pix_taxas_expiracao DATETIME NULL AFTER pix_taxas_payload;
