-- Migration: deduplicate remessa_janelas by (data_inicio, data_fim) and prevent future duplicates

SET @db := DATABASE();

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janelas'
);

SET @links_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janela_pedidos'
);

-- Only run if both tables exist
SET @should_run := IF(@table_exists = 1 AND @links_exists = 1, 1, 0);

-- Move links to the primary janela (MIN(id)) for each period
SET @sql := IF(@should_run = 1,
  "INSERT IGNORE INTO remessa_janela_pedidos (janela_id, pedido_id, etiqueta_gerada, etiqueta_gerada_em, created_at)
   SELECT jmin.min_id AS janela_id,
          rjp.pedido_id,
          rjp.etiqueta_gerada,
          rjp.etiqueta_gerada_em,
          rjp.created_at
   FROM remessa_janela_pedidos rjp
   INNER JOIN remessa_janelas j ON j.id = rjp.janela_id
   INNER JOIN (
      SELECT data_inicio, data_fim, MIN(id) AS min_id
      FROM remessa_janelas
      GROUP BY data_inicio, data_fim
      HAVING COUNT(*) > 1
   ) jmin ON jmin.data_inicio = j.data_inicio AND jmin.data_fim = j.data_fim
   WHERE rjp.janela_id <> jmin.min_id",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Delete duplicate link rows that are not the primary janela_id
SET @sql := IF(@should_run = 1,
  "DELETE rjp
   FROM remessa_janela_pedidos rjp
   INNER JOIN remessa_janelas j ON j.id = rjp.janela_id
   INNER JOIN (
      SELECT data_inicio, data_fim, MIN(id) AS min_id
      FROM remessa_janelas
      GROUP BY data_inicio, data_fim
      HAVING COUNT(*) > 1
   ) jmin ON jmin.data_inicio = j.data_inicio AND jmin.data_fim = j.data_fim
   WHERE rjp.janela_id <> jmin.min_id",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Delete duplicate remessa_janelas rows (keep MIN(id))
SET @sql := IF(@should_run = 1,
  "DELETE j
   FROM remessa_janelas j
   INNER JOIN (
      SELECT data_inicio, data_fim, MIN(id) AS min_id
      FROM remessa_janelas
      GROUP BY data_inicio, data_fim
      HAVING COUNT(*) > 1
   ) jmin ON jmin.data_inicio = j.data_inicio AND jmin.data_fim = j.data_fim
   WHERE j.id <> jmin.min_id",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add unique index to prevent future duplicates
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'remessa_janelas' AND INDEX_NAME = 'uq_remessa_janelas_periodo'
);
SET @sql := IF(@table_exists = 1 AND @idx_exists = 0,
  'ALTER TABLE remessa_janelas ADD UNIQUE KEY uq_remessa_janelas_periodo (data_inicio, data_fim)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
