-- Add benefits columns to produto_descricoes_ia
-- Benefits are JSON arrays with 4 items: [{icon, title, description}]

ALTER TABLE produto_descricoes_ia 
    ADD COLUMN benefits_gerados JSON NULL COMMENT 'Benefits gerados pela IA (array de objetos {icon, title, description})' AFTER descricao_editada_en,
    ADD COLUMN benefits_gerados_en JSON NULL COMMENT 'Benefits em inglês' AFTER benefits_gerados;
