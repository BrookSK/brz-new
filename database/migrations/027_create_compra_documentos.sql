CREATE TABLE IF NOT EXISTS compras_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loja_id INT NULL,
    sem_loja TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pendente_upload','ok') NOT NULL DEFAULT 'pendente_upload',
    arquivo_path VARCHAR(500) NULL,
    mime VARCHAR(120) NULL,
    uploaded_at TIMESTAMP NULL,
    usuario_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cd_loja (loja_id),
    INDEX idx_cd_status (status)
);
