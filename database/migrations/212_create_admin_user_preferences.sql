-- Preferências por usuário admin (idioma, moeda, etc.)
-- Armazena configurações pessoais que persistem entre sessões

CREATE TABLE IF NOT EXISTS admin_user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    idioma VARCHAR(10) NOT NULL DEFAULT 'pt-BR' COMMENT 'pt-BR ou en',
    moeda VARCHAR(3) NOT NULL DEFAULT 'USD' COMMENT 'USD ou BRL - moeda de exibição padrão',
    configurado TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = já passou pelo wizard inicial',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario (usuario_id),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
