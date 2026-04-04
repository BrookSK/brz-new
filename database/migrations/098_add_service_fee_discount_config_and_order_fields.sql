-- Adicionar campos de desconto na taxa de serviço ao carrinho
ALTER TABLE carrinhos
    ADD COLUMN taxa_servico_original DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN taxa_servico_desconto_tipo VARCHAR(20) DEFAULT NULL,
    ADD COLUMN taxa_servico_desconto_valor DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN taxa_servico_desconto_aplicado DECIMAL(12,2) DEFAULT NULL;

-- Adicionar campos de desconto na taxa de serviço ao pedido
ALTER TABLE pedidos
    ADD COLUMN taxa_servico_original DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN taxa_servico_desconto_tipo VARCHAR(20) DEFAULT NULL,
    ADD COLUMN taxa_servico_desconto_valor DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN taxa_servico_desconto_aplicado DECIMAL(12,2) DEFAULT NULL;

-- Inserir configurações padrão (desativado)
-- Modo chave_valor (chave completa)
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES ('promocao_taxa_servico_ativo', '0');
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES ('promocao_taxa_servico_tipo', 'percentual');
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES ('promocao_taxa_servico_valor', '0');
