-- Substituir status 'bloqueado' por 'recusado' nas demandas
ALTER TABLE demandas MODIFY COLUMN status ENUM(
    'pendente',
    'em_analise',
    'em_execucao',
    'em_teste',
    'recusado',
    'concluido'
) NOT NULL DEFAULT 'pendente';

-- Migrar dados existentes de 'bloqueado' para 'recusado'
UPDATE demandas SET status = 'recusado' WHERE status = 'bloqueado';

-- Adicionar campo email do solicitante para notificações
ALTER TABLE demandas ADD COLUMN IF NOT EXISTS solicitante_email VARCHAR(255) NULL AFTER solicitante;

-- Adicionar campo motivo_recusa
ALTER TABLE demandas ADD COLUMN IF NOT EXISTS motivo_recusa TEXT NULL AFTER nota_admin;

-- Adicionar campo para controlar expiração do teste (em dias úteis)
ALTER TABLE demandas ADD COLUMN IF NOT EXISTS teste_expirado TINYINT(1) NOT NULL DEFAULT 0 AFTER inicio_teste;
