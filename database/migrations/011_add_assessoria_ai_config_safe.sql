-- Migration segura: cria/insere chaves de configuração da Assessoria (ScrapingBee + ChatGPT)
-- IMPORTANT: não inclui nenhuma API Key (valores ficam vazios para preencher via Admin)

-- Garante tabela configuracoes_sistema (fallback simples)
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(255) NOT NULL UNIQUE,
  valor TEXT NULL,
  descricao TEXT NULL,
  tipo VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- ScrapingBee
INSERT IGNORE INTO configuracoes_sistema (chave, valor, descricao, tipo) VALUES
('scrapingbee_api_key', '', 'Chave API do ScrapingBee', 'string');

-- ChatGPT
INSERT IGNORE INTO configuracoes_sistema (chave, valor, descricao, tipo) VALUES
('chatgpt_api_key', '', 'Chave API do ChatGPT para análise de produtos', 'string'),
('chatgpt_model', 'gpt-3.5-turbo', 'Modelo do ChatGPT para análise', 'string'),
('chatgpt_temperature', '0.1', 'Temperatura do ChatGPT (consistência)', 'number'),
('chatgpt_max_tokens', '1000', 'Tokens máximos por requisição', 'number'),
('chatgpt_peso_margem', '15', 'Margem de segurança para estimativa de peso (%)', 'number');

-- Conferência
SELECT chave, CASE WHEN chave LIKE '%_api_key' THEN CONCAT(SUBSTRING(COALESCE(valor,''),1,4),'...',SUBSTRING(COALESCE(valor,''),-4)) ELSE valor END AS valor_preview
FROM configuracoes_sistema
WHERE chave IN (
  'scrapingbee_api_key',
  'chatgpt_api_key',
  'chatgpt_model',
  'chatgpt_temperature',
  'chatgpt_max_tokens',
  'chatgpt_peso_margem'
)
ORDER BY chave;
