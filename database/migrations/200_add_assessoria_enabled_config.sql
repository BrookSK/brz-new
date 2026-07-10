-- Configuração para ativar/desativar a página de assessoria (redirecionamento)
-- Valor '0' = desativado (mostra mensagem WhatsApp), '1' = ativado (funciona normal)
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('assessoria_enabled', '0')
ON DUPLICATE KEY UPDATE chave = chave;
