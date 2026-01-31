-- Inserir chaves de configuração para webhooks da Assessoria (início/conclusão)
-- Compatível com tabela no formato chave/valor (configuracoes_sistema/configuracoes/settings/config)

SET @db := DATABASE();

-- Detectar tabela de configuração existente
SET @cfg_table := NULL;

SET @t1 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='configuracoes_sistema');
SET @t2 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='configuracoes');
SET @t3 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='settings');
SET @t4 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='config');

SET @cfg_table := IF(@t1>0, 'configuracoes_sistema', IF(@t2>0, 'configuracoes', IF(@t3>0, 'settings', IF(@t4>0, 'config', NULL))));

-- Se não existir tabela de config, não faz nada
SET @do := IF(@cfg_table IS NULL, 0, 1);

-- Descobrir colunas do schema (categoria/chave ou chave/valor)
SET @has_categoria := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@cfg_table AND COLUMN_NAME='categoria'
);
SET @has_chave := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@cfg_table AND COLUMN_NAME='chave'
);

-- Caso categoria/chave (categoria + chave + valor)
SET @sql1 := IF(
    @do=1 AND @has_categoria>0 AND @has_chave>0,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (categoria, chave, valor, updated_at)\n',
        'SELECT ''assessoria'', ''webhook_inicio_url'', '''', NOW()\n',
        'WHERE NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' WHERE categoria=''assessoria'' AND chave=''webhook_inicio_url'');'
    ),
    'SELECT 1;'
);

SET @sql2 := IF(
    @do=1 AND @has_categoria>0 AND @has_chave>0,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (categoria, chave, valor, updated_at)\n',
        'SELECT ''assessoria'', ''webhook_conclusao_url'', '''', NOW()\n',
        'WHERE NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' WHERE categoria=''assessoria'' AND chave=''webhook_conclusao_url'');'
    ),
    'SELECT 1;'
);

-- Caso chave/valor (ex: chave + valor)
SET @has_key := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@cfg_table AND COLUMN_NAME IN ('chave','key','nome','config_key','slug','parametro')
);

-- Tentar descobrir nome da coluna de chave
SET @key_col := (
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@cfg_table AND COLUMN_NAME IN ('chave','key','nome','config_key','slug','parametro')
    ORDER BY FIELD(COLUMN_NAME,'chave','key','nome','config_key','slug','parametro')
    LIMIT 1
);

SET @value_col := (
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@cfg_table AND COLUMN_NAME IN ('valor','value','conteudo','content','config_value')
    ORDER BY FIELD(COLUMN_NAME,'valor','value','conteudo','content','config_value')
    LIMIT 1
);

SET @has_updated_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@cfg_table AND COLUMN_NAME='updated_at'
);

SET @sql3 := IF(
    @do=1 AND (@has_categoria=0 OR @has_chave=0) AND @key_col IS NOT NULL AND @value_col IS NOT NULL,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (', @key_col, ', ', @value_col, IF(@has_updated_at>0, ', updated_at', ''), ')\n',
        'SELECT ''assessoria_webhook_inicio_url'', ''''', IF(@has_updated_at>0, ', NOW()', ''), '\n',
        'WHERE NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' WHERE ', @key_col, '=''assessoria_webhook_inicio_url'');'
    ),
    'SELECT 1;'
);

SET @sql4 := IF(
    @do=1 AND (@has_categoria=0 OR @has_chave=0) AND @key_col IS NOT NULL AND @value_col IS NOT NULL,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (', @key_col, ', ', @value_col, IF(@has_updated_at>0, ', updated_at', ''), ')\n',
        'SELECT ''assessoria_webhook_conclusao_url'', ''''', IF(@has_updated_at>0, ', NOW()', ''), '\n',
        'WHERE NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' WHERE ', @key_col, '=''assessoria_webhook_conclusao_url'');'
    ),
    'SELECT 1;'
);

PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;
PREPARE s4 FROM @sql4; EXECUTE s4; DEALLOCATE PREPARE s4;
