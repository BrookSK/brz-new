-- =============================================================================
-- 216_sync_pais_entrega_pedidos.sql
-- -----------------------------------------------------------------------------
-- Corrige a inconsistência do país exibido na LISTAGEM de pedidos.
--
-- Contexto do bug:
--   Ao editar o endereço de um pedido para um país internacional (ex.: Estados
--   Unidos = "US"), o valor era gravado na coluna `pais_entrega` da tabela
--   `pedidos` (e/ou no endereço vinculado por `endereco_entrega_id`), mas a
--   coluna legada `pais` continuava com o valor antigo ("BR" / "Brasil").
--   A listagem lia da coluna legada `pais` e exibia "Brazil" incorretamente.
--
-- Este script faz duas coisas:
--   A) Corrige TODOS os pedidos existentes de forma idempotente e segura.
--   B) Aplica também uma correção pontual explícita no pedido de teste #747.
--
-- Ordem de prioridade da fonte de verdade do país:
--   1) pais_entrega (atualizada na edição do pedido)
--   2) país do endereço vinculado (enderecos.pais via endereco_entrega_id)
--   O resultado é escrito de volta em `pais` e `pais_entrega` para manter as
--   duas colunas consistentes.
-- =============================================================================

SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'pedidos');

SET @has_pais := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pais');
SET @has_pais_entrega := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pais_entrega');
SET @has_end_id := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'endereco_entrega_id');
SET @has_enderecos_pais := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enderecos' AND COLUMN_NAME = 'pais');

-- =============================================================================
-- PARTE A — Correção geral (todos os pedidos)
-- =============================================================================

-- A.1) Se ambas as colunas `pais` e `pais_entrega` existem, copiar o valor de
-- `pais_entrega` para `pais` quando `pais_entrega` estiver preenchido.
-- (garante que a listagem, mesmo lendo a coluna legada, mostre o país correto)
SET @sql1 := IF(@table_exists = 1 AND @has_pais = 1 AND @has_pais_entrega = 1,
  'UPDATE pedidos
      SET pais = pais_entrega
    WHERE pais_entrega IS NOT NULL
      AND TRIM(pais_entrega) <> ''''
      AND (pais IS NULL OR TRIM(pais) = '''' OR pais <> pais_entrega)',
  'SELECT 1'
);
PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- A.2) Preencher pais_entrega a partir do endereço vinculado (se vazio)
SET @sql2a := IF(@table_exists = 1 AND @has_pais_entrega = 1 AND @has_end_id = 1 AND @has_enderecos_pais = 1,
  'UPDATE pedidos p
     INNER JOIN enderecos e ON e.id = p.endereco_entrega_id
        SET p.pais_entrega = e.pais
      WHERE e.pais IS NOT NULL
        AND TRIM(e.pais) <> ''''
        AND (p.pais_entrega IS NULL OR TRIM(p.pais_entrega) = '''')',
  'SELECT 1'
);
PREPARE stmt2a FROM @sql2a; EXECUTE stmt2a; DEALLOCATE PREPARE stmt2a;

-- A.3) Preencher pais (legada) a partir do endereço vinculado (se vazio)
SET @sql2b := IF(@table_exists = 1 AND @has_pais = 1 AND @has_end_id = 1 AND @has_enderecos_pais = 1,
  'UPDATE pedidos p
     INNER JOIN enderecos e ON e.id = p.endereco_entrega_id
        SET p.pais = e.pais
      WHERE e.pais IS NOT NULL
        AND TRIM(e.pais) <> ''''
        AND (p.pais IS NULL OR TRIM(p.pais) = '''')',
  'SELECT 1'
);
PREPARE stmt2b FROM @sql2b; EXECUTE stmt2b; DEALLOCATE PREPARE stmt2b;

-- A.4) Normalizar valores textuais legados para código ISO de 2 letras em `pais`.
SET @sql3a := IF(@table_exists = 1 AND @has_pais = 1,
  'UPDATE pedidos SET pais = CASE UPPER(TRIM(pais))
        WHEN ''BRASIL'' THEN ''BR'' WHEN ''BRAZIL'' THEN ''BR'' WHEN ''BRA'' THEN ''BR''
        WHEN ''ESTADOS UNIDOS'' THEN ''US'' WHEN ''UNITED STATES'' THEN ''US'' WHEN ''USA'' THEN ''US''
        WHEN ''PORTUGAL'' THEN ''PT'' WHEN ''REINO UNIDO'' THEN ''GB'' WHEN ''UNITED KINGDOM'' THEN ''GB''
        WHEN ''ALEMANHA'' THEN ''DE'' WHEN ''GERMANY'' THEN ''DE''
        WHEN ''CANADA'' THEN ''CA'' WHEN ''MEXICO'' THEN ''MX''
        ELSE pais END
    WHERE pais IS NOT NULL AND TRIM(pais) <> ''''',
  'SELECT 1'
);
PREPARE stmt3a FROM @sql3a; EXECUTE stmt3a; DEALLOCATE PREPARE stmt3a;

-- A.5) Normalizar valores textuais legados para código ISO em `pais_entrega`.
SET @sql3b := IF(@table_exists = 1 AND @has_pais_entrega = 1,
  'UPDATE pedidos SET pais_entrega = CASE UPPER(TRIM(pais_entrega))
        WHEN ''BRASIL'' THEN ''BR'' WHEN ''BRAZIL'' THEN ''BR'' WHEN ''BRA'' THEN ''BR''
        WHEN ''ESTADOS UNIDOS'' THEN ''US'' WHEN ''UNITED STATES'' THEN ''US'' WHEN ''USA'' THEN ''US''
        WHEN ''PORTUGAL'' THEN ''PT'' WHEN ''REINO UNIDO'' THEN ''GB'' WHEN ''UNITED KINGDOM'' THEN ''GB''
        WHEN ''ALEMANHA'' THEN ''DE'' WHEN ''GERMANY'' THEN ''DE''
        WHEN ''CANADA'' THEN ''CA'' WHEN ''MEXICO'' THEN ''MX''
        ELSE pais_entrega END
    WHERE pais_entrega IS NOT NULL AND TRIM(pais_entrega) <> ''''',
  'SELECT 1'
);
PREPARE stmt3b FROM @sql3b; EXECUTE stmt3b; DEALLOCATE PREPARE stmt3b;

-- =============================================================================
-- PARTE B — Correção pontual do pedido de teste #747 (endereço = Estados Unidos)
-- =============================================================================

-- B.1) Forçar país = US no pedido 747 (nas colunas que existirem).
SET @sqlB1 := IF(@table_exists = 1 AND @has_pais = 1 AND @has_pais_entrega = 1,
  'UPDATE pedidos SET pais = ''US'', pais_entrega = ''US'' WHERE id = 747',
  IF(@table_exists = 1 AND @has_pais = 1,
     'UPDATE pedidos SET pais = ''US'' WHERE id = 747',
     IF(@table_exists = 1 AND @has_pais_entrega = 1,
        'UPDATE pedidos SET pais_entrega = ''US'' WHERE id = 747',
        'SELECT 1')));
PREPARE stmtB1 FROM @sqlB1; EXECUTE stmtB1; DEALLOCATE PREPARE stmtB1;

-- B.2) Se houver endereço vinculado ao pedido 747, garantir que ele também seja US.
SET @sqlB2 := IF(@table_exists = 1 AND @has_end_id = 1 AND @has_enderecos_pais = 1,
  'UPDATE enderecos e
     INNER JOIN pedidos p ON p.endereco_entrega_id = e.id
        SET e.pais = ''US''
      WHERE p.id = 747',
  'SELECT 1'
);
PREPARE stmtB2 FROM @sqlB2; EXECUTE stmtB2; DEALLOCATE PREPARE stmtB2;
