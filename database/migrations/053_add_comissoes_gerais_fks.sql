-- Adiciona FKs para o schema de "Comissões gerais" quando possível
-- Compatível com bancos que podem ter variações de schema.
-- Não altera migrations antigas.

SET @db := DATABASE();

-- Guard: tabelas necessárias
SET @has_comissao_janelas := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='comissao_janelas');
SET @has_comissao_ajustes := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='comissao_ajustes');
SET @has_comissao_pagamentos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='comissao_pagamentos');
SET @has_comissao_pagamento_pedidos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='comissao_pagamento_pedidos');
SET @has_pedidos := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='pedidos');
SET @has_usuarios := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='usuarios');

-- FK: comissao_ajustes.janela_id -> comissao_janelas.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema=@db AND table_name='comissao_ajustes' AND constraint_name='fk_comissao_ajustes_janela'
);
SET @sql := IF(@has_comissao_ajustes>0 AND @has_comissao_janelas>0 AND @fk_exists=0,
  'ALTER TABLE comissao_ajustes ADD CONSTRAINT fk_comissao_ajustes_janela FOREIGN KEY (janela_id) REFERENCES comissao_janelas(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK: comissao_ajustes.vendedor_id -> usuarios.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema=@db AND table_name='comissao_ajustes' AND constraint_name='fk_comissao_ajustes_vendedor'
);
SET @sql := IF(@has_comissao_ajustes>0 AND @has_usuarios>0 AND @fk_exists=0,
  'ALTER TABLE comissao_ajustes ADD CONSTRAINT fk_comissao_ajustes_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK: comissao_ajustes.criado_por -> usuarios.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema=@db AND table_name='comissao_ajustes' AND constraint_name='fk_comissao_ajustes_criado_por'
);
SET @sql := IF(@has_comissao_ajustes>0 AND @has_usuarios>0 AND @fk_exists=0,
  'ALTER TABLE comissao_ajustes ADD CONSTRAINT fk_comissao_ajustes_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK: comissao_pagamentos.janela_id -> comissao_janelas.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema=@db AND table_name='comissao_pagamentos' AND constraint_name='fk_comissao_pagamentos_janela'
);
SET @sql := IF(@has_comissao_pagamentos>0 AND @has_comissao_janelas>0 AND @fk_exists=0,
  'ALTER TABLE comissao_pagamentos ADD CONSTRAINT fk_comissao_pagamentos_janela FOREIGN KEY (janela_id) REFERENCES comissao_janelas(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK: comissao_pagamentos.vendedor_id -> usuarios.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema=@db AND table_name='comissao_pagamentos' AND constraint_name='fk_comissao_pagamentos_vendedor'
);
SET @sql := IF(@has_comissao_pagamentos>0 AND @has_usuarios>0 AND @fk_exists=0,
  'ALTER TABLE comissao_pagamentos ADD CONSTRAINT fk_comissao_pagamentos_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK: comissao_pagamentos.aprovado_por -> usuarios.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema=@db AND table_name='comissao_pagamentos' AND constraint_name='fk_comissao_pagamentos_aprovado_por'
);
SET @sql := IF(@has_comissao_pagamentos>0 AND @has_usuarios>0 AND @fk_exists=0,
  'ALTER TABLE comissao_pagamentos ADD CONSTRAINT fk_comissao_pagamentos_aprovado_por FOREIGN KEY (aprovado_por) REFERENCES usuarios(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK: comissao_pagamento_pedidos.pagamento_id -> comissao_pagamentos.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema=@db AND table_name='comissao_pagamento_pedidos' AND constraint_name='fk_cpp_pagamento'
);
SET @sql := IF(@has_comissao_pagamento_pedidos>0 AND @has_comissao_pagamentos>0 AND @fk_exists=0,
  'ALTER TABLE comissao_pagamento_pedidos ADD CONSTRAINT fk_cpp_pagamento FOREIGN KEY (pagamento_id) REFERENCES comissao_pagamentos(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK: comissao_pagamento_pedidos.pedido_id -> pedidos.id
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema=@db AND table_name='comissao_pagamento_pedidos' AND constraint_name='fk_cpp_pedido'
);
SET @sql := IF(@has_comissao_pagamento_pedidos>0 AND @has_pedidos>0 AND @fk_exists=0,
  'ALTER TABLE comissao_pagamento_pedidos ADD CONSTRAINT fk_cpp_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
