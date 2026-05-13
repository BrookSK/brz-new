-- Migration: Adicionar campo imposto_local_percent na tabela produtos
-- Permite definir imposto local por produto individual (mesma lógica do grupo de compras)

ALTER TABLE produtos ADD COLUMN imposto_local_percent DECIMAL(5,2) NOT NULL DEFAULT 0;
