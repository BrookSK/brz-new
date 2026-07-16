-- Configurações do sistema de pacotes/redirecionamento
-- Controla multa de armazenamento, prazos e taxas

INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_dias_multa_inicio', '15')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_multa_valor_dia_usd', '2.00')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_dias_descarte', '42')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_lembrete_intervalo_dias', '5')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes_sistema (chave, valor) VALUES ('pacote_taxa_seguro_percentual', '3.00')
ON DUPLICATE KEY UPDATE chave = chave;
