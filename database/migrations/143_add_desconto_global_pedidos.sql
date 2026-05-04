-- Desconto global (sobre subtotal + taxa de serviço) para pedidos manuais
-- NÃO se aplica a impostos, imposto local ou frete

SET @has_pedidos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pedidos');

SET @has_dg_tipo := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'desconto_global_tipo');
SET @sql1 := IF(@has_pedidos > 0 AND @has_dg_tipo = 0,
  "ALTER TABLE `pedidos` ADD COLUMN `desconto_global_tipo` VARCHAR(20) DEFAULT NULL COMMENT 'percentual ou fixo'",
  'SELECT 1'
);
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @has_dg_valor := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'desconto_global_valor');
SET @sql2 := IF(@has_pedidos > 0 AND @has_dg_valor = 0,
  "ALTER TABLE `pedidos` ADD COLUMN `desconto_global_valor` DECIMAL(12,2) DEFAULT NULL COMMENT 'valor informado (% ou fixo)'",
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @has_dg_aplicado := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'desconto_global_aplicado');
SET @sql3 := IF(@has_pedidos > 0 AND @has_dg_aplicado = 0,
  "ALTER TABLE `pedidos` ADD COLUMN `desconto_global_aplicado` DECIMAL(12,2) DEFAULT NULL COMMENT 'valor efetivo descontado em USD'",
  'SELECT 1'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @has_dg_token := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'desconto_global_token');
SET @sql4 := IF(@has_pedidos > 0 AND @has_dg_token = 0,
  "ALTER TABLE `pedidos` ADD COLUMN `desconto_global_token` VARCHAR(64) DEFAULT NULL COMMENT 'token de autorização do desconto global'",
  'SELECT 1'
);
PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

-- Adicionar tipo 'global' na tabela de autorizações (se existir)
SET @has_da := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'desconto_autorizacoes');
SET @has_da_tipo_col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'desconto_autorizacoes' AND column_name = 'escopo');
SET @sql5 := IF(@has_da > 0 AND @has_da_tipo_col = 0,
  "ALTER TABLE `desconto_autorizacoes` ADD COLUMN `escopo` VARCHAR(20) DEFAULT 'produto' COMMENT 'produto ou global'",
  'SELECT 1'
);
PREPARE stmt5 FROM @sql5;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;
