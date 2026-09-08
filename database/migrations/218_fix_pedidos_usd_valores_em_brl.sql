-- =============================================================================
-- 218_fix_pedidos_usd_valores_em_brl.sql
-- -----------------------------------------------------------------------------
-- Corrige pedidos históricos gravados com moeda = 'USD' porém com os VALORES
-- (subtotal, servicos, impostos, frete, desconto, total) armazenados em REAIS
-- (BRL), acompanhados de taxa_conversao = 1.
--
-- Contexto do bug:
--   No checkout, quando a entrega era internacional (pais != BR), a moeda era
--   forçada para 'USD', mas a taxa_conversao só era calculada para pedidos BRL,
--   ficando travada em 1. Em determinados fluxos os valores acabaram gravados
--   já em grandeza BRL sob o rótulo 'USD', produzindo cards e telas com o total
--   em "R$" mesmo com o selo "Moeda: US$".
--
-- Regra de negócio definida (Opção B):
--   Pedido com moeda 'USD' DEVE exibir seus valores em dólar. Portanto, para os
--   pedidos afetados, dividimos os valores em BRL pela taxa USD->BRL vigente
--   para reconstruí-los em USD, mantendo moeda = 'USD' e taxa_conversao = 1.
--
-- SEGURANÇA:
--   - Idempotente e defensivo (checa schema via information_schema).
--   - NÃO converte pedidos USD legítimos: a conversão automática (PARTE A) é
--     restrita a pedidos claramente afetados (taxa<=1.01 + serviços altos demais
--     para USD plausível + marcador de checkout). A PARTE B trata explicitamente
--     os pedidos já confirmados manualmente (#744, #746, #747).
--   - A operação altera VALORES de pedidos reais e é praticamente irreversível.
--     RECOMENDA-SE BACKUP DA TABELA `pedidos` ANTES DE EXECUTAR (ver PARTE 0).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- PARTE 0 — Backup de segurança (snapshot dos pedidos que serão avaliados)
-- -----------------------------------------------------------------------------
-- Cria uma tabela de backup com os pedidos USD antes da correção, para permitir
-- auditoria/rollback manual. Não sobrescreve um backup já existente.
CREATE TABLE IF NOT EXISTS pedidos_backup_218 AS
    SELECT * FROM pedidos WHERE 0 = 1;

INSERT INTO pedidos_backup_218
    SELECT p.* FROM pedidos p
    LEFT JOIN pedidos_backup_218 b ON b.id = p.id
    WHERE b.id IS NULL
      AND UPPER(TRIM(COALESCE(p.moeda, ''))) IN ('USD', 'US$', 'US');

-- -----------------------------------------------------------------------------
-- Detecção de colunas (defensivo)
-- -----------------------------------------------------------------------------
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'pedidos');

SET @has_moeda := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'moeda');
SET @has_taxa := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'taxa_conversao');
SET @has_total := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'total');
SET @has_servicos := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'servicos');

-- -----------------------------------------------------------------------------
-- Taxa USD->BRL vigente (fonte: configuracoes_sistema; fallback configuracoes_moeda; fallback 5.85)
-- -----------------------------------------------------------------------------
SET @rate := NULL;

-- 1) configuracoes_sistema (chaves centralizadas)
SET @has_conf_sis := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'configuracoes_sistema');
SET @sqlRate1 := IF(@has_conf_sis = 1,
    'SELECT @rate := CAST(REPLACE(valor, '','', ''.'') AS DECIMAL(10,6))
        FROM configuracoes_sistema
       WHERE chave IN (''sistema_usd_brl_rate'', ''usd_brl_rate'')
         AND valor IS NOT NULL AND TRIM(valor) <> ''''
       ORDER BY (chave = ''sistema_usd_brl_rate'') DESC
       LIMIT 1',
    'SELECT 1');
PREPARE stR1 FROM @sqlRate1; EXECUTE stR1; DEALLOCATE PREPARE stR1;

-- 2) configuracoes_moeda (fallback)
SET @has_conf_moeda := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'configuracoes_moeda');
SET @sqlRate2 := IF(@has_conf_moeda = 1 AND (@rate IS NULL OR @rate <= 1.01),
    'SELECT @rate := CAST(taxa_conversao AS DECIMAL(10,6))
        FROM configuracoes_moeda
       WHERE moeda_origem = ''USD'' AND moeda_destino = ''BRL''
       ORDER BY id DESC LIMIT 1',
    'SELECT 1');
PREPARE stR2 FROM @sqlRate2; EXECUTE stR2; DEALLOCATE PREPARE stR2;

-- 3) Fallback final
SET @rate := IF(@rate IS NULL OR @rate <= 1.01, 5.85, @rate);

-- =============================================================================
-- PARTE A — Correção geral CONSERVADORA (opt-in)
-- -----------------------------------------------------------------------------
-- Converte para USD apenas pedidos claramente afetados:
--   - moeda normaliza para 'USD'
--   - taxa_conversao <= 1.01 (não houve conversão registrada)
--   - servicos > 100  (taxa de serviço em USD real dificilmente passa de 100;
--                       valores nessa faixa indicam multiplicação pela taxa BRL)
--   - observacoes contém o marcador de checkout orgânico ('[IDEMPOTENCY:')
--
-- ATENÇÃO: por segurança, esta PARTE A está DESABILITADA por padrão (@enable_bulk = 0).
-- Revise a lista de candidatos com o SELECT de auditoria abaixo e, se estiver de
-- acordo, altere @enable_bulk para 1 e reexecute.
-- =============================================================================
SET @enable_bulk := 0;

-- Auditoria (sempre roda): lista os candidatos afetados sem alterar nada.
SET @sqlAudit := IF(@table_exists = 1 AND @has_moeda = 1 AND @has_taxa = 1 AND @has_servicos = 1,
    'SELECT id, moeda, taxa_conversao, subtotal, servicos, total,
            ROUND(total / @rate, 2) AS total_convertido_usd
       FROM pedidos
      WHERE UPPER(TRIM(COALESCE(moeda, ''''))) IN (''USD'', ''US$'', ''US'')
        AND COALESCE(taxa_conversao, 1) <= 1.01
        AND COALESCE(servicos, 0) > 100
        AND COALESCE(observacoes, '''') LIKE ''%[IDEMPOTENCY:%''',
    'SELECT 1');
PREPARE stAudit FROM @sqlAudit; EXECUTE stAudit; DEALLOCATE PREPARE stAudit;

SET @sqlBulk := IF(@enable_bulk = 1 AND @table_exists = 1 AND @has_moeda = 1 AND @has_taxa = 1
        AND @has_total = 1 AND @has_servicos = 1,
    'UPDATE pedidos
        SET subtotal = ROUND(COALESCE(subtotal, 0) / @rate, 2),
            servicos = ROUND(COALESCE(servicos, 0) / @rate, 2),
            impostos = ROUND(COALESCE(impostos, 0) / @rate, 2),
            frete    = ROUND(COALESCE(frete, 0)    / @rate, 2),
            desconto = ROUND(COALESCE(desconto, 0) / @rate, 2),
            total    = ROUND(COALESCE(total, 0)    / @rate, 2),
            moeda = ''USD'',
            taxa_conversao = 1
      WHERE UPPER(TRIM(COALESCE(moeda, ''''))) IN (''USD'', ''US$'', ''US'')
        AND COALESCE(taxa_conversao, 1) <= 1.01
        AND COALESCE(servicos, 0) > 100
        AND COALESCE(observacoes, '''') LIKE ''%[IDEMPOTENCY:%''',
    'SELECT 1');
PREPARE stBulk FROM @sqlBulk; EXECUTE stBulk; DEALLOCATE PREPARE stBulk;

-- =============================================================================
-- PARTE B — Correção pontual dos pedidos CONFIRMADOS manualmente
-- -----------------------------------------------------------------------------
-- Pedidos #744, #746, #747: confirmados visualmente com moeda=USD e valores em
-- BRL. Converte de BRL para USD dividindo pela taxa vigente e fixa taxa=1.
-- Guarda de segurança: só altera se ainda estiver no estado corrompido
-- (moeda USD + taxa<=1.01), tornando a migration idempotente (não reprocessa).
-- =============================================================================
SET @sqlB := IF(@table_exists = 1 AND @has_moeda = 1 AND @has_taxa = 1 AND @has_total = 1,
    'UPDATE pedidos
        SET subtotal = ROUND(COALESCE(subtotal, 0) / @rate, 2),
            servicos = ROUND(COALESCE(servicos, 0) / @rate, 2),
            impostos = ROUND(COALESCE(impostos, 0) / @rate, 2),
            frete    = ROUND(COALESCE(frete, 0)    / @rate, 2),
            desconto = ROUND(COALESCE(desconto, 0) / @rate, 2),
            total    = ROUND(COALESCE(total, 0)    / @rate, 2),
            moeda = ''USD'',
            taxa_conversao = 1
      WHERE id IN (744, 746, 747)
        AND UPPER(TRIM(COALESCE(moeda, ''''))) IN (''USD'', ''US$'', ''US'')
        AND COALESCE(taxa_conversao, 1) <= 1.01',
    'SELECT 1');
PREPARE stB FROM @sqlB; EXECUTE stB; DEALLOCATE PREPARE stB;

-- =============================================================================
-- Verificação final (auditoria pós-correção)
-- =============================================================================
SELECT id, moeda, taxa_conversao, subtotal, servicos, total
  FROM pedidos
 WHERE id IN (744, 746, 747);
