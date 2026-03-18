-- Cria tabelas para configurações e histórico de backups

CREATE TABLE IF NOT EXISTS backup_config (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recorrencia ENUM('diaria','semanal','mensal') NOT NULL DEFAULT 'diaria',
  horario TIME NOT NULL DEFAULT '02:00:00',
  reter_quantidade INT UNSIGNED NOT NULL DEFAULT 10,
  cron_token VARCHAR(255) NOT NULL DEFAULT '',
  pasta_backup VARCHAR(500) NOT NULL DEFAULT '',
  last_run_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO backup_config (id, recorrencia, horario, reter_quantidade, cron_token, pasta_backup)
SELECT 1, 'diaria', '02:00:00', 10, '', ''
WHERE NOT EXISTS (SELECT 1 FROM backup_config WHERE id = 1);

CREATE TABLE IF NOT EXISTS backup_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  db_sql_path VARCHAR(700) NOT NULL,
  files_zip_path VARCHAR(700) NOT NULL,
  db_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  files_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('ok','erro') NOT NULL DEFAULT 'ok',
  erro TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_backup_runs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
