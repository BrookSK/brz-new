-- Migration: Adicionar campos de expiração e juros ao carnê
-- Regras:
--   1ª parcela: 30 min para pagar, senão cancela tudo
--   Demais parcelas: vence 23:59 do dia, depois corre juros por 7 dias, depois cancela

-- Campos na tabela carnes
ALTER TABLE carnes ADD COLUMN primeira_parcela_expira_em DATETIME NULL COMMENT 'Prazo limite para pagar a 1ª parcela (30 min após criação)';
ALTER TABLE carnes ADD COLUMN juros_diario_percent DECIMAL(5,3) NOT NULL DEFAULT 0.033 COMMENT 'Juros diário (padrão 0.033% = ~1% ao mês)';
ALTER TABLE carnes ADD COLUMN dias_tolerancia_atraso INT NOT NULL DEFAULT 7 COMMENT 'Dias de tolerância com juros antes de cancelar';

-- Campos na tabela carne_parcelas
ALTER TABLE carne_parcelas ADD COLUMN valor_juros DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de juros acumulado por atraso';
ALTER TABLE carne_parcelas ADD COLUMN dias_atraso INT NOT NULL DEFAULT 0 COMMENT 'Dias de atraso da parcela';
ALTER TABLE carne_parcelas ADD COLUMN expira_em DATETIME NULL COMMENT 'Data/hora limite para pagamento desta parcela';
ALTER TABLE carne_parcelas ADD COLUMN valor_produtos_original DECIMAL(10,2) NULL COMMENT 'Valor original de produtos (antes de juros)';
ALTER TABLE carne_parcelas ADD COLUMN valor_taxas_original DECIMAL(10,2) NULL COMMENT 'Valor original de taxas (antes de juros)';

-- Configurações do sistema
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES 
('carne_primeira_parcela_minutos', '30'),
('carne_juros_diario_percent', '0.033'),
('carne_dias_tolerancia_atraso', '7');
