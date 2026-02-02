-- Inserir configuração padrão da taxa de conversão USD -> BRL
-- Chave:
--  - sistema_usd_brl_rate (float)
-- Compatível com schema categoria/chave/valor (configuracoes_sistema/configuracoes/settings/config)
-- e também com schema chave/valor (chave + valor).
-- Não altera migrations antigas.

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

-- 1) Caso schema categoria/chave (categoria + chave + valor)
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
SET @has_valor := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@cfg_table AND COLUMN_NAME='valor'
);
SET @has_updated_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@cfg_table AND COLUMN_NAME='updated_at'
);

-- 2) Caso schema chave/valor (chave + valor)
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

-- Default
SET @default_rate := '5.5';

SET @sql_cat_rate := IF(
    @do=1 AND @has_categoria>0 AND @has_chave>0 AND @has_valor>0,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (categoria, chave, valor', IF(@has_updated_at>0, ', updated_at', ''), ')\n',
        'SELECT ''sistema'', ''usd_brl_rate'', ''', @default_rate, '''', IF(@has_updated_at>0, ', NOW()', ''), '\n',
        'WHERE NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' WHERE categoria=''sistema'' AND chave=''usd_brl_rate'');'
    ),
    'SELECT 1;'
);

SET @sql_kv_rate := IF(
    @do=1 AND (@has_categoria=0 OR @has_chave=0) AND @key_col IS NOT NULL AND @value_col IS NOT NULL,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (', @key_col, ', ', @value_col, IF(@has_updated_at>0, ', updated_at', ''), ')\n',
        'SELECT ''sistema_usd_brl_rate'', ''', @default_rate, '''', IF(@has_updated_at>0, ', NOW()', ''), '\n',
        'WHERE NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' WHERE ', @key_col, '=''sistema_usd_brl_rate'');'
    ),
    'SELECT 1;'
);

PREPARE s1 FROM @sql_cat_rate; EXECUTE s1; DEALLOCATE PREPARE s1;
PREPARE s2 FROM @sql_kv_rate; EXECUTE s2; DEALLOCATE PREPARE s2;
