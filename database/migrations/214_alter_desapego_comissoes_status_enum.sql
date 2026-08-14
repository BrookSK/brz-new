-- Migration 214: Adicionar 'parcialmente_pago' ao ENUM de status da tabela desapego_comissoes
-- E adicionar coluna valor_pago

ALTER TABLE desapego_comissoes 
    MODIFY COLUMN status ENUM('pendente', 'aprovado', 'parcialmente_pago', 'pago', 'cancelado') NOT NULL DEFAULT 'pendente';

ALTER TABLE desapego_comissoes 
    ADD COLUMN IF NOT EXISTS valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER valor_comissao;
