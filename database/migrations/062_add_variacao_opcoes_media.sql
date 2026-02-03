-- Adiciona campos opcionais de exibição (nome e imagem) para opções de variação
-- Não altera migrations existentes

-- nome_exibicao: permite exibir um nome diferente do "valor" (ex: "Azul Royal")
SET @col_nome_exibicao_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'variacao_opcoes'
      AND COLUMN_NAME = 'nome_exibicao'
);

SET @sql_add_nome_exibicao := IF(
    @col_nome_exibicao_exists = 0,
    'ALTER TABLE variacao_opcoes ADD COLUMN nome_exibicao VARCHAR(120) NULL AFTER valor',
    'SELECT 1'
);

PREPARE stmt_add_nome_exibicao FROM @sql_add_nome_exibicao;
EXECUTE stmt_add_nome_exibicao;
DEALLOCATE PREPARE stmt_add_nome_exibicao;

-- imagem: caminho (url/path) para miniatura da opção (ex: /uploads/variacoes/azul.png)
SET @col_imagem_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'variacao_opcoes'
      AND COLUMN_NAME = 'imagem'
);

SET @sql_add_imagem := IF(
    @col_imagem_exists = 0,
    'ALTER TABLE variacao_opcoes ADD COLUMN imagem VARCHAR(500) NULL AFTER nome_exibicao',
    'SELECT 1'
);

PREPARE stmt_add_imagem FROM @sql_add_imagem;
EXECUTE stmt_add_imagem;
DEALLOCATE PREPARE stmt_add_imagem;
