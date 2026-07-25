-- Adicionar categoria "Descontos" na tabela de categorias de despesas
-- Usada para classificar descontos concedidos que são lançados como despesa operacional

SET @has_table := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'despesa_categorias');
SET @has_cat := IF(@has_table > 0, 
    (SELECT COUNT(*) FROM despesa_categorias WHERE LOWER(nome) = 'descontos'),
    0
);
SET @sql := IF(@has_table > 0 AND @has_cat = 0,
    "INSERT INTO despesa_categorias (nome, grupo, cor, icone, ativa, inclui_relatorio) VALUES ('Descontos', 'despesa_operacional', '#f43f5e', 'fas fa-percent', 1, 1)",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
