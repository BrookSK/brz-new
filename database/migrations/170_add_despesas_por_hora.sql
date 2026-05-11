-- =====================================================
-- DESPESAS POR HORA - Adicionar tipo e colunas
-- =====================================================

-- Adicionar 'por_hora' ao ENUM de tipo
ALTER TABLE despesas MODIFY COLUMN tipo ENUM('avulsa','fixa','recorrente','parcelada','comissao','por_hora') NOT NULL DEFAULT 'avulsa';

-- Colunas específicas para despesas por hora
ALTER TABLE despesas ADD COLUMN pessoa_nome VARCHAR(255) NULL COMMENT 'Nome da pessoa contratada por hora' AFTER observacoes;
ALTER TABLE despesas ADD COLUMN horas_trabalhadas DECIMAL(8,2) NULL COMMENT 'Quantidade de horas trabalhadas' AFTER pessoa_nome;
ALTER TABLE despesas ADD COLUMN valor_hora DECIMAL(10,2) NULL COMMENT 'Valor cobrado por hora' AFTER horas_trabalhadas;

-- Índice para facilitar consultas por pessoa
ALTER TABLE despesas ADD INDEX idx_pessoa_nome (pessoa_nome);
ALTER TABLE despesas ADD INDEX idx_tipo_por_hora (tipo, pessoa_nome);
