-- Adiciona campo de destaque aos produtos

ALTER TABLE produtos
    ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0;

CREATE INDEX idx_produtos_featured ON produtos (featured);
