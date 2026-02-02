-- Inserir configuração padrão de faixas de comissão para pedidos manuais (comissao_manual_faixas)
-- Compatível com schema chave/valor (configuracoes_sistema/configuracoes/settings/config)
-- e também com schema single-row (coluna direta em configuracoes_sistema).

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

SET @default_json := '[{"min":0,"max":999999999,"percent":0}]';

SET @sql_cat := IF(
    @do=1 AND @has_categoria>0 AND @has_chave>0 AND @has_valor>0,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (categoria, chave, valor', IF(@has_updated_at>0, ', updated_at', ''), ')\n',
        'SELECT ''comissao'', ''manual_faixas'', ''', @default_json, '''', IF(@has_updated_at>0, ', NOW()', ''), '\n',
        'WHERE NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' WHERE categoria=''comissao'' AND chave=''manual_faixas'');'
    ),
    'SELECT 1;'
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

SET @sql_kv := IF(
    @do=1 AND (@has_categoria=0 OR @has_chave=0) AND @key_col IS NOT NULL AND @value_col IS NOT NULL,
    CONCAT(
        'INSERT INTO ', @cfg_table, ' (', @key_col, ', ', @value_col, IF(@has_updated_at>0, ', updated_at', ''), ')\n',
        'SELECT ''comissao_manual_faixas'', ''', @default_json, '''', IF(@has_updated_at>0, ', NOW()', ''), '\n',
        'WHERE NOT EXISTS (SELECT 1 FROM ', @cfg_table, ' WHERE ', @key_col, '=''comissao_manual_faixas'');'
    ),
    'SELECT 1;'
);

-- 3) Caso schema single-row (coluna direta comissao_manual_faixas em configuracoes_sistema)
SET @is_single_row_cfg_sistema := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='configuracoes_sistema' AND COLUMN_NAME='id'
);
SET @sr_has_categoria := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='configuracoes_sistema' AND COLUMN_NAME='categoria'
);
SET @sr_has_chave := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='configuracoes_sistema' AND COLUMN_NAME='chave'
);
SET @sr_col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='configuracoes_sistema' AND COLUMN_NAME='comissao_manual_faixas'
);

SET @sql_sr := IF(
  @t1>0 AND @is_single_row_cfg_sistema>0 AND @sr_has_categoria=0 AND @sr_has_chave=0 AND @sr_col_exists=0,
  'ALTER TABLE configuracoes_sistema ADD COLUMN comissao_manual_faixas LONGTEXT NULL',
  'SELECT 1;'
);

PREPARE s1 FROM @sql_cat; EXECUTE s1; DEALLOCATE PREPARE s1;
PREPARE s2 FROM @sql_kv; EXECUTE s2; DEALLOCATE PREPARE s2;
PREPARE s3 FROM @sql_sr; EXECUTE s3; DEALLOCATE PREPARE s3;

-- Backfill default no schema single-row
SET @sql_sr_upd := IF(
  @t1>0 AND @is_single_row_cfg_sistema>0 AND @sr_has_categoria=0 AND @sr_has_chave=0 AND @sr_col_exists>0,
  CONCAT('UPDATE configuracoes_sistema SET comissao_manual_faixas = COALESCE(NULLIF(comissao_manual_faixas, \'\'), \'', @default_json, '\') WHERE id = (SELECT id FROM (SELECT id FROM configuracoes_sistema ORDER BY id ASC LIMIT 1) t);'),
  'SELECT 1;'
);
PREPARE s4 FROM @sql_sr_upd; EXECUTE s4; DEALLOCATE PREPARE s4;
