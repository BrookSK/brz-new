-- Configurações do ChatGPT para o sistema de Assessoria
-- Execute este SQL para adicionar as configurações na tabela de configurações do sistema

INSERT IGNORE INTO configuracoes_sistema (chave, valor, descricao, tipo) VALUES
('chatgpt_api_key', '', 'Chave API do ChatGPT para análise de produtos', 'string'),
('chatgpt_model', 'gpt-3.5-turbo', 'Modelo do ChatGPT para análise', 'string'),
('chatgpt_temperature', '0.1', 'Temperatura do ChatGPT (consistência)', 'number'),
('chatgpt_max_tokens', '1000', 'Tokens máximos por requisição', 'number'),
('chatgpt_peso_margem', '15', 'Margem de segurança para estimativa de peso (%)', 'number');

-- Verificar se inseriu corretamente
SELECT * FROM configuracoes_sistema WHERE chave LIKE 'chatgpt_%' ORDER BY chave;
