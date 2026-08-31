-- Backup em segundo plano: rastreio de job assíncrono e usuário que disparou.
-- Amplia o ENUM de status para incluir 'processando' e adiciona colunas de rastreio.

ALTER TABLE backup_runs
  MODIFY COLUMN status ENUM('ok','erro','processando') NOT NULL DEFAULT 'ok';

-- Colunas de rastreio (idempotentes: rode apenas se ainda não existirem).
-- MySQL não suporta IF NOT EXISTS em ADD COLUMN em versões antigas, então
-- as instruções abaixo podem falhar silenciosamente se a coluna já existir.

ALTER TABLE backup_runs
  ADD COLUMN trigger_tipo VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER status;

ALTER TABLE backup_runs
  ADD COLUMN usuario_id INT UNSIGNED NULL DEFAULT NULL AFTER trigger_tipo;

ALTER TABLE backup_runs
  ADD COLUMN finished_at DATETIME NULL DEFAULT NULL AFTER created_at;

ALTER TABLE backup_runs
  ADD INDEX idx_backup_runs_status (status);
