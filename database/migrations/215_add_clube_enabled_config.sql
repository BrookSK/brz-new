-- Configuração para ativar/desativar o Clube Braziliana (recargas)
-- Valor '1' = ativado (recargas liberadas), '0' = desativado (mostra aviso WhatsApp)
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('clube_enabled', '1')
ON DUPLICATE KEY UPDATE chave = chave;
