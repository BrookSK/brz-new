-- Cria tabela de faixas de desconto do Clube Brasiliana (peso kg -> % desconto)

CREATE TABLE IF NOT EXISTS clube_descontos_faixas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peso_min_kg DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    peso_max_kg DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    percentual_desconto DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_clube_faixas_ativo_ordem (ativo, ordem),
    INDEX idx_clube_faixas_peso (peso_min_kg, peso_max_kg)
);
