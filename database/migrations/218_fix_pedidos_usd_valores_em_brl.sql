-- =============================================================================
-- 218_fix_pedidos_usd_valores_em_brl.sql
-- -----------------------------------------------------------------------------
-- Corrige o `total` dos pedidos em USD, deixando-o igual à soma dos componentes
-- (já convertidos para dólar): total = subtotal + servicos + impostos + frete - desconto.
--
-- >>> SÓ MEXE EM PEDIDOS USD. Pedidos BRL NÃO são tocados. <<<
--
-- Basta executar este arquivo inteiro (ex.: aba SQL do phpMyAdmin).
-- Cria um backup automático dos pedidos USD antes de alterar.
-- =============================================================================

-- 1) Backup automático (só cria se ainda não existir).
CREATE TABLE IF NOT EXISTS pedidos_backup_218 AS
SELECT * FROM pedidos
 WHERE UPPER(TRIM(COALESCE(moeda, ''))) IN ('USD', 'US$', 'US');

-- 2) Correção: recalcula o total dos pedidos USD com total divergente.
UPDATE pedidos
   SET total = ROUND(COALESCE(subtotal,0) + COALESCE(servicos,0) + COALESCE(impostos,0)
               + COALESCE(frete,0) - COALESCE(desconto,0), 2)
 WHERE UPPER(TRIM(COALESCE(moeda, ''))) IN ('USD', 'US$', 'US')
   AND ROUND(total, 2) <> ROUND(COALESCE(subtotal,0) + COALESCE(servicos,0)
          + COALESCE(impostos,0) + COALESCE(frete,0) - COALESCE(desconto,0), 2);
