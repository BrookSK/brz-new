-- Seed de configurações para integração multi-site WordPress/WooCommerce (BR/RED/US)
--
-- Objetivo:
--  - Criar chaves por origem: wordpress_br/*, wordpress_red/*, wordpress_us/*
--  - Criar chaves por origem: woocommerce_br/*, woocommerce_red/*, woocommerce_us/*
--  - Copiar (seed) os valores antigos (wordpress/* e woocommerce/*) para BR, se BR estiver vazio
--
-- Observações:
--  - Não altera migrations antigas.
--  - Execute manualmente no banco.

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

-- Caso schema categoria/chave/valor
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

-- Caso schema chave/valor (fallback)
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

-- Categoria/chave: seed wordpress_* e woocommerce_*
SET @sql_seed_cat := IF(
    @do=1 AND @has_categoria>0 AND @has_chave>0 AND @has_valor>0,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (categoria, chave, valor', IF(@has_updated_at>0, ', updated_at', ''), ')\n',
        'SELECT categoria, chave, valor', IF(@has_updated_at>0, ', NOW()', ''), ' FROM (\n',
        '  SELECT ''wordpress_br'' AS categoria, ''db_host'' AS chave, src.valor AS valor\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.categoria=''wordpress'' AND src.chave=''db_host''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.categoria=''wordpress_br'' AND dst.chave=''db_host'')\n',
        '  UNION ALL\n',
        '  SELECT ''wordpress_br'', ''db_name'', src.valor\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.categoria=''wordpress'' AND src.chave=''db_name''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.categoria=''wordpress_br'' AND dst.chave=''db_name'')\n',
        '  UNION ALL\n',
        '  SELECT ''wordpress_br'', ''db_user'', src.valor\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.categoria=''wordpress'' AND src.chave=''db_user''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.categoria=''wordpress_br'' AND dst.chave=''db_user'')\n',
        '  UNION ALL\n',
        '  SELECT ''wordpress_br'', ''db_pass'', src.valor\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.categoria=''wordpress'' AND src.chave=''db_pass''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.categoria=''wordpress_br'' AND dst.chave=''db_pass'')\n',
        '  UNION ALL\n',
        '  SELECT ''wordpress_br'', ''table_prefix'', src.valor\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.categoria=''wordpress'' AND src.chave=''table_prefix''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.categoria=''wordpress_br'' AND dst.chave=''table_prefix'')\n',
        '  UNION ALL\n',
        '  SELECT ''woocommerce_br'', ''store_url'', src.valor\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.categoria=''woocommerce'' AND src.chave=''store_url''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.categoria=''woocommerce_br'' AND dst.chave=''store_url'')\n',
        '  UNION ALL\n',
        '  SELECT ''woocommerce_br'', ''consumer_key'', src.valor\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.categoria=''woocommerce'' AND src.chave=''consumer_key''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.categoria=''woocommerce_br'' AND dst.chave=''consumer_key'')\n',
        '  UNION ALL\n',
        '  SELECT ''woocommerce_br'', ''consumer_secret'', src.valor\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.categoria=''woocommerce'' AND src.chave=''consumer_secret''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.categoria=''woocommerce_br'' AND dst.chave=''consumer_secret'')\n',
        ') t;\n'
    ),
    'SELECT 1;'
);

-- Para schema chave/valor, não dá pra seedear de forma confiável (não existe categoria). Apenas cria chaves compatíveis (se quiser) via chave prefixada.
SET @sql_seed_kv := IF(
    @do=1 AND (@has_categoria=0 OR @has_chave=0) AND @key_col IS NOT NULL AND @value_col IS NOT NULL,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (', @key_col, ', ', @value_col, IF(@has_updated_at>0, ', updated_at', ''), ')\n',
        'SELECT k, v', IF(@has_updated_at>0, ', NOW()', ''), ' FROM (\n',
        '  SELECT ''wordpress_br_db_host'' AS k, src.', @value_col, ' AS v\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.', @key_col, ' = ''wordpress_db_host''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.', @key_col, ' = ''wordpress_br_db_host'')\n',
        '  UNION ALL\n',
        '  SELECT ''woocommerce_br_store_url'' AS k, src.', @value_col, ' AS v\n',
        '  FROM ', @cfg_table, ' src\n',
        '  WHERE src.', @key_col, ' = ''woocommerce_store_url''\n',
        '    AND NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' dst WHERE dst.', @key_col, ' = ''woocommerce_br_store_url'')\n',
        ') t;\n'
    ),
    'SELECT 1;'
);

PREPARE s1 FROM @sql_seed_cat; EXECUTE s1; DEALLOCATE PREPARE s1;
PREPARE s2 FROM @sql_seed_kv; EXECUTE s2; DEALLOCATE PREPARE s2;
