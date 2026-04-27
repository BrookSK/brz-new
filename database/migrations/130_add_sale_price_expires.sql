-- Migration: Adicionar data de expiração do preço promocional
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS sale_price_expires DATETIME NULL DEFAULT NULL COMMENT 'Data/hora limite do preço promocional (NULL = sem expiração)';
