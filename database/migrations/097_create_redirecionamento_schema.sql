-- Redirecionamento (portal) schema mínimo
-- Observação:
-- - A cobrança de diferença já existe em `pedidos.payment_diferenca_*` (migration 039).
-- - PACKET/etiquetas já existe em `correios_packet_etiquetas` (integração do correios mondial).

SET @db := DATABASE();

-- =========================
-- Colunas para divergência (informado vs real)
-- =========================

-- peso_informado
SET @pedidos_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema=@db AND table_name='pedidos'
);
SET @col_exists := IF(
  @pedidos_exists = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema=@db AND table_name='pedidos' AND column_name='peso_informado'),
  0
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN peso_informado DECIMAL(12,3) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- altura_informada
SET @col_exists := IF(
  @pedidos_exists = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema=@db AND table_name='pedidos' AND column_name='altura_informada'),
  0
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN altura_informada DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- largura_informada
SET @col_exists := IF(
  @pedidos_exists = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema=@db AND table_name='pedidos' AND column_name='largura_informada'),
  0
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN largura_informada DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- comprimento_informada
SET @col_exists := IF(
  @pedidos_exists = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema=@db AND table_name='pedidos' AND column_name='comprimento_informada'),
  0
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN comprimento_informada DECIMAL(12,2) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Redirecionamento: vínculo com cliente e pedido do cliente
SET @col_exists := IF(
  @pedidos_exists = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema=@db AND table_name='pedidos' AND column_name='redirecionamento_cliente_id'),
  0
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN redirecionamento_cliente_id INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := IF(
  @pedidos_exists = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema=@db AND table_name='pedidos' AND column_name='redirecionamento_pedido_cliente_id'),
  0
);
SET @sql := IF(@pedidos_exists=1 AND @col_exists=0,
  'ALTER TABLE pedidos ADD COLUMN redirecionamento_pedido_cliente_id VARCHAR(255) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índice para listagens do portal
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE table_schema=@db AND table_name='pedidos' AND index_name='idx_pedidos_redirecionamento_cliente_id'
);
SET @sql := IF(@pedidos_exists=1 AND @idx_exists=0,
  'ALTER TABLE pedidos ADD INDEX idx_pedidos_redirecionamento_cliente_id (redirecionamento_cliente_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================
-- Tabela: tabela de pesos e preços
-- =========================
-- Importante: eu NÃO semeei aqui os valores porque você não colou a tabela completa no trecho
-- que tenho acesso agora. Assim que você me mandar a tabela inteira (peso_min/peso_max ou lista por 0.5kg),
-- eu gero os INSERTs corretos.
CREATE TABLE IF NOT EXISTS redirecionamento_tabela_pesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  peso_min_kg DECIMAL(10,3) NOT NULL,
  peso_max_kg DECIMAL(10,3) NOT NULL,
  valor_usd DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_redir_tabela_peso_range (peso_min_kg, peso_max_kg),
  UNIQUE KEY uq_redir_tabela_pesos_range (peso_min_kg, peso_max_kg)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================
-- Tabela: clientes do redirecionador
-- =========================
CREATE TABLE IF NOT EXISTS redirecionamento_clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  redirecionador_usuario_id INT NOT NULL,
  nome VARCHAR(255) NOT NULL,
  documento VARCHAR(50) NOT NULL,
  numero_suite INT NULL,
  email VARCHAR(255) NULL,
  telefone VARCHAR(50) NULL,
  data_nascimento DATE NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'ativo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_redir_clientes_doc (redirecionador_usuario_id, documento),
  UNIQUE KEY uq_redir_clientes_suite (redirecionador_usuario_id, numero_suite),
  INDEX idx_redir_clientes_usuario (redirecionador_usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================
-- Tabela: endereços do cliente do redirecionador
-- =========================
CREATE TABLE IF NOT EXISTS redirecionamento_cliente_enderecos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  redirecionador_usuario_id INT NOT NULL,
  principal TINYINT(1) NOT NULL DEFAULT 0,
  logradouro VARCHAR(255) NOT NULL,
  numero VARCHAR(50) NOT NULL,
  complemento VARCHAR(100) NULL,
  bairro VARCHAR(100) NOT NULL,
  cidade VARCHAR(120) NOT NULL,
  estado VARCHAR(120) NOT NULL,
  cep VARCHAR(30) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_redir_enderecos_cliente (cliente_id),
  INDEX idx_redir_enderecos_usuario (redirecionador_usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================
-- Tabela: coletas (agenda)
-- =========================
CREATE TABLE IF NOT EXISTS redirecionamento_coletas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  redirecionador_usuario_id INT NOT NULL,
  pedido_id INT NULL,
  data_agendada DATE NOT NULL,
  horario_agendado TIME NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pendente',
  redirecionador_confirmado_em DATETIME NULL,
  admin_confirmado_por INT NULL,
  admin_confirmado_em DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_redir_coletas_usuario_data (redirecionador_usuario_id, data_agendada),
  INDEX idx_redir_coletas_pedido (pedido_id),
  INDEX idx_redir_coletas_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================
-- Tabela: comprovantes
-- =========================
CREATE TABLE IF NOT EXISTS redirecionamento_comprovantes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  tipo VARCHAR(30) NOT NULL COMMENT 'pagamento_inicial | diferenca | reembolso',
  usuario_id INT NULL COMMENT 'quem enviou o comprovante (redirecionador/admin)',
  nome_arquivo VARCHAR(255) NULL,
  arquivo_url TEXT NULL,
  mime_type VARCHAR(120) NULL,
  tamanho_bytes BIGINT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pendente',
  observacao TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_redir_comp_pedido (pedido_id),
  INDEX idx_redir_comp_tipo (tipo),
  INDEX idx_redir_comp_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

