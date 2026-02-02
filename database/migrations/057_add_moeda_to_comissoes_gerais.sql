-- Adiciona coluna moeda para suportar multimoeda em comissões gerais

SET @db := DATABASE();

SET @has_ajustes := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='comissao_ajustes');
SET @has_pagamentos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='comissao_pagamentos');
SET @has_cpp := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='comissao_pagamento_pedidos');

-- comissao_ajustes.moeda
SET @col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='comissao_ajustes' AND column_name='moeda');
SET @sql := IF(@has_ajustes>0 AND @col=0,
  'ALTER TABLE comissao_ajustes ADD COLUMN moeda VARCHAR(3) NOT NULL DEFAULT \'USD\' AFTER tipo',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- comissao_pagamentos.moeda
SET @col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='comissao_pagamentos' AND column_name='moeda');
SET @sql := IF(@has_pagamentos>0 AND @col=0,
  'ALTER TABLE comissao_pagamentos ADD COLUMN moeda VARCHAR(3) NOT NULL DEFAULT \'USD\' AFTER vendedor_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- comissao_pagamento_pedidos.moeda
SET @col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='comissao_pagamento_pedidos' AND column_name='moeda');
SET @sql := IF(@has_cpp>0 AND @col=0,
  'ALTER TABLE comissao_pagamento_pedidos ADD COLUMN moeda VARCHAR(3) NOT NULL DEFAULT \'USD\' AFTER pedido_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
