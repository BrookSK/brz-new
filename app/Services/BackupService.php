<?php
namespace App\Services;

use Config\Database;

class BackupService {
    private function ensureDir(string $dir): void {
        if ($dir === '') {
            throw new \RuntimeException('Diretório de backup não configurado');
        }
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Não foi possível criar diretório de backup: ' . $dir);
            }
        }
    }

    private function normalizeBackupDir(?string $dir): string {
        $dir = is_string($dir) ? trim($dir) : '';
        $defaultDir = (string) (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups');

        if ($dir === '') {
            $dir = $defaultDir;
        }

        $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);

        // Se o caminho salvo no banco não existe (ex: domínio antigo), usar o padrão
        if (!is_dir($dir) && $dir !== rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $defaultDir), DIRECTORY_SEPARATOR)) {
            $dir = $defaultDir;
        }

        return $dir;
    }

    public function getConfig(): array {
        $pdo = Database::getConnection();
        $hasTable = false;
        try {
            $st = $pdo->query("SHOW TABLES LIKE 'backup_config'");
            $hasTable = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable $e) {
            $hasTable = false;
        }

        if (!$hasTable) {
            return [
                'recorrencia' => 'diaria',
                'horario' => '02:00:00',
                'reter_quantidade' => 10,
                'cron_token' => '',
                'pasta_backup' => $this->normalizeBackupDir(null),
                'last_run_at' => null,
            ];
        }

        $stmt = $pdo->query('SELECT * FROM backup_config WHERE id = 1');
        $row = $stmt ? ($stmt->fetch(\PDO::FETCH_ASSOC) ?: []) : [];

        return [
            'recorrencia' => (string) ($row['recorrencia'] ?? 'diaria'),
            'horario' => (string) ($row['horario'] ?? '02:00:00'),
            'reter_quantidade' => (int) ($row['reter_quantidade'] ?? 10),
            'cron_token' => (string) ($row['cron_token'] ?? ''),
            'pasta_backup' => $this->normalizeBackupDir((string) ($row['pasta_backup'] ?? '')),
            'last_run_at' => $row['last_run_at'] ?? null,
        ];
    }

    public function saveConfig(array $data): void {
        $pdo = Database::getConnection();

        $rec = isset($data['recorrencia']) ? (string) $data['recorrencia'] : 'diaria';
        if (!in_array($rec, ['diaria', 'semanal', 'mensal'], true)) {
            $rec = 'diaria';
        }

        $horario = isset($data['horario']) ? (string) $data['horario'] : '02:00';
        $horario = trim($horario);
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horario)) {
            $horario = '02:00:00';
        }
        if (strlen($horario) === 5) {
            $horario .= ':00';
        }

        $reter = isset($data['reter_quantidade']) ? (int) $data['reter_quantidade'] : 10;
        if ($reter < 1) $reter = 1;
        if ($reter > 365) $reter = 365;

        $token = isset($data['cron_token']) ? (string) $data['cron_token'] : '';
        $token = trim($token);
        if ($token === '') {
            $token = bin2hex(random_bytes(24));
        }

        $dir = $this->normalizeBackupDir(isset($data['pasta_backup']) ? (string) $data['pasta_backup'] : '');
        $this->ensureDir($dir);

        $hasTable = false;
        try {
            $st = $pdo->query("SHOW TABLES LIKE 'backup_config'");
            $hasTable = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable $e) {
            $hasTable = false;
        }
        if (!$hasTable) {
            throw new \RuntimeException('Tabela backup_config não existe. Rode as migrations.');
        }

        $stmt = $pdo->prepare('UPDATE backup_config SET recorrencia = ?, horario = ?, reter_quantidade = ?, cron_token = ?, pasta_backup = ? WHERE id = 1');
        $stmt->execute([$rec, $horario, $reter, $token, $dir]);
    }

    public function getCronUrl(): string {
        $cfg = $this->getConfig();
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $token = rawurlencode((string) ($cfg['cron_token'] ?? ''));
        return $scheme . '://' . $host . '/cron/backup?token=' . $token;
    }

    public function listBackups(int $limit = 50): array {
        $pdo = Database::getConnection();
        $limit = max(1, min(200, $limit));

        $hasTable = false;
        try {
            $st = $pdo->query("SHOW TABLES LIKE 'backup_runs'");
            $hasTable = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable $e) {
            $hasTable = false;
        }
        if (!$hasTable) {
            return [];
        }

        $stmt = $pdo->prepare('SELECT * FROM backup_runs ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Marca como 'erro' os backups presos em 'processando' há mais de N minutos.
     * Protege contra jobs que morreram sem finalizar o registro.
     */
    public function destravarBackupsPresos(int $minutos = 15): void {
        $pdo = Database::getConnection();
        if (!$this->tableHasColumn('backup_runs', 'status')) {
            return;
        }
        $minutos = max(0, (int) $minutos);
        try {
            $sql = "UPDATE backup_runs SET status = 'erro', erro = COALESCE(NULLIF(erro,''), 'Backup expirou (processo não finalizou a tempo). Verifique se o PHP tem permissão/tempo para gerar o dump.') WHERE status = 'processando' AND created_at < (NOW() - INTERVAL " . $minutos . " MINUTE)";
            $pdo->exec($sql);
        } catch (\Throwable $e) {
            // silencioso
        }
    }

    /**
     * Retorna o backup em andamento (status 'processando'), se houver.
     * Antes de consultar, destrava jobs presos há muito tempo.
     */
    public function getRunningBackup(): ?array {
        $pdo = Database::getConnection();
        try {
            $st = $pdo->query("SHOW TABLES LIKE 'backup_runs'");
            if (!($st && $st->fetchColumn())) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        if (!$this->tableHasColumn('backup_runs', 'status')) {
            return null;
        }

        // Auto-destravar jobs presos (ex.: worker morreu)
        $this->destravarBackupsPresos(15);

        try {
            $stmt = $pdo->query("SELECT * FROM backup_runs WHERE status = 'processando' ORDER BY id DESC LIMIT 1");
            $row = $stmt ? ($stmt->fetch(\PDO::FETCH_ASSOC) ?: null) : null;
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getBackupDir(): string {
        $cfg = $this->getConfig();
        return $this->normalizeBackupDir((string) ($cfg['pasta_backup'] ?? ''));
    }

    public function getBackupRun(int $runId): array {
        $runId = (int) $runId;
        if ($runId <= 0) {
            throw new \RuntimeException('Backup inválido');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM backup_runs WHERE id = ? LIMIT 1');
        $stmt->execute([$runId]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) {
            throw new \RuntimeException('Backup não encontrado');
        }
        return $r;
    }

    private function getDbCredentials(): array {
        $ref = new \ReflectionClass(\Config\Database::class);

        $getProp = function (string $name) use ($ref) {
            if (!$ref->hasProperty($name)) return null;
            $p = $ref->getProperty($name);
            $p->setAccessible(true);
            return $p->getValue();
        };

        // Database usa $hosts (array). Mantemos compatibilidade com $host (string) caso exista.
        $host = $getProp('host');
        if ($host === null) {
            $hosts = $getProp('hosts');
            if (is_array($hosts) && !empty($hosts)) {
                $host = (string) $hosts[0];
            }
        }
        $host = (string) ($host ?: '127.0.0.1');

        $db = (string) ($getProp('db_name') ?? '');
        $user = (string) ($getProp('username') ?? '');
        $pass = (string) ($getProp('password') ?? '');

        return [$host, $db, $user, $pass];
    }

    private function runCmd(string $cmd): array {
        $out = [];
        $ret = 0;

        // Verificar se exec está disponível
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $disabled, true)) {
            return [1, 'exec() está desabilitado no servidor'];
        }

        @\exec($cmd . ' 2>&1', $out, $ret);
        return [$ret, implode("\n", $out)];
    }

    /**
     * Dump do banco via PHP puro (fallback quando mysqldump/exec não estão disponíveis)
     */
    private function dumpDatabasePHP(string $host, string $db, string $user, string $pass, string $outputPath): void {
        $pdo = new \PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $fp = fopen($outputPath, 'w');
        if (!$fp) {
            throw new \RuntimeException('Não foi possível criar arquivo de dump: ' . $outputPath);
        }

        fwrite($fp, "-- Backup gerado em " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // CREATE TABLE
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
            $createSql = $create['Create Table'] ?? ($create['Create View'] ?? '');
            if ($createSql !== '') {
                fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fp, $createSql . ";\n\n");
            }

            // INSERT rows em lotes
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            if ($count === 0) continue;

            $batchSize = 500;
            $offset = 0;
            while ($offset < $count) {
                $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}")->fetchAll(\PDO::FETCH_ASSOC);
                if (empty($rows)) break;

                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote((string) $v);
                        }
                    }
                    $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
                    fwrite($fp, "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
                }
                $offset += $batchSize;
            }
            fwrite($fp, "\n");
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);
    }

    private function zipDirectory(string $sourceDir, string $zipPath, array $excludeDirs = ['.git', 'vendor', 'node_modules', 'storage', 'public/uploads']): void {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Extensão ZipArchive não disponível no PHP');
        }

        $sourceDir = rtrim($sourceDir, '/\\');
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar zip: ' . $zipPath);
        }

        $excludeDirsNorm = array_map(function ($d) {
            return trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $d), DIRECTORY_SEPARATOR);
        }, $excludeDirs);

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $file) {
            $path = (string) $file;
            $rel = ltrim(substr($path, strlen($sourceDir)), '/\\');

            $relNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
            $top = explode(DIRECTORY_SEPARATOR, $relNorm)[0] ?? '';
            if ($top !== '' && in_array($top, $excludeDirsNorm, true)) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir(str_replace('\\', '/', $relNorm));
            } else {
                $zip->addFile($path, str_replace('\\', '/', $relNorm));
            }
        }

        $zip->close();
    }

    private function tableHasColumn(string $table, string $column): bool {
        $pdo = Database::getConnection();
        try {
            $st = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ?');
            $st->execute([$column]);
            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureRunsTable(): void {
        $pdo = Database::getConnection();
        $hasRuns = false;
        try {
            $st = $pdo->query("SHOW TABLES LIKE 'backup_runs'");
            $hasRuns = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable $e) {
            $hasRuns = false;
        }
        if (!$hasRuns) {
            throw new \RuntimeException('Tabela backup_runs não existe. Rode as migrations.');
        }
    }

    /**
     * Cria uma notificação no sino do admin (tabela admin_notificacoes).
     * Silencioso em caso de falha — não deve quebrar o fluxo de backup.
     */
    private function criarNotificacao(int $usuarioId, string $tipo, string $titulo, string $mensagem, string $link): void {
        if ($usuarioId <= 0) {
            return;
        }
        try {
            $pdo = Database::getConnection();
            // Garantir tabela (mesmo padrão do módulo de demandas)
            try {
                $pdo->query('SELECT 1 FROM admin_notificacoes LIMIT 1');
            } catch (\Throwable $e) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notificacoes (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, tipo VARCHAR(50) NOT NULL DEFAULT 'demanda', titulo VARCHAR(500) NOT NULL, mensagem TEXT NULL, link VARCHAR(1000) NULL, lida TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_usuario_lida (usuario_id, lida))");
            }
            $pdo->prepare('INSERT INTO admin_notificacoes (usuario_id, tipo, titulo, mensagem, link) VALUES (?,?,?,?,?)')
                ->execute([$usuarioId, $tipo, $titulo, $mensagem, $link]);
        } catch (\Throwable $e) {
            error_log('[BACKUP] Falha ao criar notificação: ' . $e->getMessage());
        }
    }

    /**
     * Cria o registro do backup com status 'processando' e retorna o ID
     * imediatamente (não executa o dump aqui).
     *
     * A execução do dump é feita por quem chama, após liberar a resposta HTTP
     * (ver AdminBackupController::agora() usando fastcgi_finish_request), ou
     * pelo worker CLI (scripts/backup_worker.php) chamando runBackupJob().
     */
    public function startBackupAsync(string $trigger = 'manual', int $usuarioId = 0): int {
        $this->ensureRunsTable();
        $pdo = Database::getConnection();

        // Inserir registro placeholder em 'processando'
        $hasTrigger = $this->tableHasColumn('backup_runs', 'trigger_tipo');
        $hasUsuario = $this->tableHasColumn('backup_runs', 'usuario_id');

        $cols = ['db_sql_path', 'files_zip_path', 'db_size_bytes', 'files_size_bytes', 'status', 'created_at'];
        $vals = ['', '', 0, 0, 'processando'];
        $place = ['?', '?', '?', '?', '?', 'NOW()'];

        if ($hasTrigger) { $cols[] = 'trigger_tipo'; $vals[] = $trigger; }
        if ($hasUsuario) { $cols[] = 'usuario_id'; $vals[] = ($usuarioId > 0 ? $usuarioId : null); }

        // Reordenar placeholders: created_at usa NOW() e não recebe valor.
        $insertCols = [];
        $insertPlace = [];
        $insertVals = [];
        foreach ($cols as $c) {
            $insertCols[] = $c;
            if ($c === 'created_at') {
                $insertPlace[] = 'NOW()';
            } else {
                $insertPlace[] = '?';
                $insertVals[] = array_shift($vals);
            }
        }

        $sql = 'INSERT INTO backup_runs (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertPlace) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertVals);
        $runId = (int) $pdo->lastInsertId();

        return $runId;
    }

    /**
     * Executa o backup para um registro já existente (status 'processando'),
     * gera o dump, finaliza o registro e notifica o usuário que disparou.
     */
    public function runBackupJob(int $runId): void {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $this->ensureRunsTable();
        $pdo = Database::getConnection();

        $run = $this->getBackupRun($runId);
        $trigger = (string) ($run['trigger_tipo'] ?? 'manual');
        $usuarioId = (int) ($run['usuario_id'] ?? 0);

        $cfg = $this->getConfig();
        $dir = $this->normalizeBackupDir((string) ($cfg['pasta_backup'] ?? ''));
        $this->ensureDir($dir);

        $ts = date('Ymd_His');
        $dbSqlPath = $dir . DIRECTORY_SEPARATOR . 'db_' . $ts . '.sql';

        $dbSize = 0;
        $status = 'ok';
        $err = null;

        try {
            [$host, $db, $user, $pass] = $this->getDbCredentials();
            if ($db === '' || $user === '') {
                throw new \RuntimeException('Credenciais do banco inválidas');
            }

            $this->gerarDumpBanco($host, $db, $user, $pass, $dbSqlPath);

            if (!file_exists($dbSqlPath) || (int) (@filesize($dbSqlPath) ?: 0) === 0) {
                throw new \RuntimeException('Arquivo .sql não foi gerado ou ficou vazio');
            }
            $dbSize = (int) (@filesize($dbSqlPath) ?: 0);
        } catch (\Throwable $e) {
            $status = 'erro';
            $err = $e->getMessage();
        }

        // Atualizar o registro existente
        $hasFinished = $this->tableHasColumn('backup_runs', 'finished_at');
        $setFinished = $hasFinished ? ', finished_at = NOW()' : '';
        $upd = $pdo->prepare(
            'UPDATE backup_runs SET db_sql_path = ?, files_zip_path = ?, db_size_bytes = ?, files_size_bytes = ?, status = ?, erro = ?' . $setFinished . ' WHERE id = ?'
        );
        $upd->execute([$dbSqlPath, '', $dbSize, 0, $status, $err, $runId]);

        try {
            $pdo->prepare('UPDATE backup_config SET last_run_at = NOW() WHERE id = 1')->execute();
        } catch (\Throwable $e) {
        }

        $this->applyRetention((int) ($cfg['reter_quantidade'] ?? 10));

        // Notificar quem disparou
        if ($status === 'ok') {
            $this->criarNotificacao(
                $usuarioId,
                'backup',
                '✅ Backup concluído',
                'Seu backup do banco foi finalizado com sucesso (' . $this->fmtBytesPublic($dbSize) . ').',
                '/admin/backup'
            );
        } else {
            $this->criarNotificacao(
                $usuarioId,
                'backup',
                '⚠️ Falha no backup',
                'O backup não pôde ser concluído: ' . (string) $err,
                '/admin/backup'
            );
        }

        // Enviar cópia para servidor externo (apenas cron)
        if ($status === 'ok' && $trigger === 'cron') {
            try {
                $this->enviarBackupServidorExterno($dbSqlPath);
            } catch (\Throwable $e) {
                error_log('[BACKUP] Falha ao enviar para servidor externo: ' . $e->getMessage());
            }
        }
    }

    public function fmtBytesPublic(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        $kb = $bytes / 1024;
        if ($kb < 1024) return number_format($kb, 1, ',', '.') . ' KB';
        $mb = $kb / 1024;
        if ($mb < 1024) return number_format($mb, 1, ',', '.') . ' MB';
        $gb = $mb / 1024;
        return number_format($gb, 2, ',', '.') . ' GB';
    }

    /**
     * Gera o dump do banco no caminho informado. Lança exceção em caso de falha.
     */
    private function gerarDumpBanco(string $host, string $db, string $user, string $pass, string $dbSqlPath): void {
        if ($db === '' || $user === '') {
            throw new \RuntimeException('Credenciais do banco inválidas');
        }

        // Verificar se exec/shell estão disponíveis
        $canExec = true;
            $disabled = \array_map('trim', \explode(',', (string) \ini_get('disable_functions')));
            if (\in_array('exec', $disabled, true) || \in_array('escapeshellarg', $disabled, true)) {
                $canExec = false;
            }

            $usedPhpDump = false;
            $dumpErrors = [];

            if ($canExec) {
                // Tentar mysqldump em caminhos comuns (nem sempre está no PATH do PHP-FPM)
                $candidates = [
                    'mysqldump',
                    '/usr/bin/mysqldump',
                    '/usr/local/bin/mysqldump',
                    '/usr/local/mysql/bin/mysqldump',
                    '/opt/cpanel/ea-mysql57/root/usr/bin/mysqldump',
                    '/opt/cpanel/ea-mysql80/root/usr/bin/mysqldump',
                ];

                $ok = false;
                foreach ($candidates as $mysqldump) {
                    $cmd = '"' . $mysqldump . '"' .
                        ' --host=' . \escapeshellarg($host) .
                        ' --user=' . \escapeshellarg($user) .
                        ' --password=' . \escapeshellarg($pass) .
                        ' --single-transaction --routines --triggers --events --default-character-set=utf8mb4 ' .
                        \escapeshellarg($db) .
                        ' > ' . \escapeshellarg($dbSqlPath);

                    [$ret, $out] = $this->runCmd($cmd);
                    if ($ret === 0 && file_exists($dbSqlPath) && (int) (@filesize($dbSqlPath) ?: 0) > 0) {
                        $ok = true;
                        break;
                    }
                    $dumpErrors[] = $mysqldump . ' (ret=' . $ret . '): ' . trim((string) $out);
                }

                if (!$ok) {
                    // Fallback: dump em PHP puro
                    try {
                        $this->dumpDatabasePHP($host, $db, $user, $pass, $dbSqlPath);
                        $usedPhpDump = true;
                    } catch (\Throwable $e) {
                        throw new \RuntimeException(
                            'mysqldump falhou e o dump PHP também falhou. mysqldump: [' .
                            implode(' | ', $dumpErrors) . ']. PHP: ' . $e->getMessage()
                        );
                    }
                }
            } else {
                // exec/escapeshellarg desabilitados — usar dump PHP puro
                $this->dumpDatabasePHP($host, $db, $user, $pass, $dbSqlPath);
                $usedPhpDump = true;
            }

        if (!file_exists($dbSqlPath) || (int) (@filesize($dbSqlPath) ?: 0) === 0) {
            throw new \RuntimeException('Arquivo .sql não foi gerado ou ficou vazio');
        }
    }

    /**
     * Backup SÍNCRONO (bloqueante). Mantido para o cron, que não tem limite de
     * tempo e prefere aguardar o término para reportar o resultado.
     * Cria o registro já finalizado (ok/erro) e retorna o ID.
     *
     * NOTA: o zip dos arquivos do projeto foi desativado de propósito — os
     * arquivos são versionados via Git e zipar a raiz inteira estourava o
     * timeout do PHP-FPM. Este método gera apenas o dump do banco (.sql).
     */
    public function createBackupNow(string $trigger = 'manual', int $usuarioId = 0): int {
        $this->ensureRunsTable();
        $pdo = Database::getConnection();
        $cfg = $this->getConfig();
        $dir = $this->normalizeBackupDir((string) ($cfg['pasta_backup'] ?? ''));
        $this->ensureDir($dir);

        $ts = date('Ymd_His');
        $dbSqlPath = $dir . DIRECTORY_SEPARATOR . 'db_' . $ts . '.sql';

        $dbSize = 0;
        $status = 'ok';
        $err = null;

        try {
            [$host, $db, $user, $pass] = $this->getDbCredentials();
            $this->gerarDumpBanco($host, $db, $user, $pass, $dbSqlPath);
            $dbSize = (int) (@filesize($dbSqlPath) ?: 0);
        } catch (\Throwable $e) {
            $status = 'erro';
            $err = $e->getMessage();
        }

        $hasTrigger = $this->tableHasColumn('backup_runs', 'trigger_tipo');
        $hasUsuario = $this->tableHasColumn('backup_runs', 'usuario_id');
        $hasFinished = $this->tableHasColumn('backup_runs', 'finished_at');

        $cols = ['db_sql_path', 'files_zip_path', 'db_size_bytes', 'files_size_bytes', 'status', 'erro', 'created_at'];
        $place = ['?', '?', '?', '?', '?', '?', 'NOW()'];
        $vals = [$dbSqlPath, '', $dbSize, 0, $status, $err];

        if ($hasTrigger) { $cols[] = 'trigger_tipo'; $place[] = '?'; $vals[] = $trigger; }
        if ($hasUsuario) { $cols[] = 'usuario_id'; $place[] = '?'; $vals[] = ($usuarioId > 0 ? $usuarioId : null); }
        if ($hasFinished) { $cols[] = 'finished_at'; $place[] = 'NOW()'; }

        $stmt = $pdo->prepare('INSERT INTO backup_runs (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $place) . ')');
        $stmt->execute($vals);
        $id = (int) $pdo->lastInsertId();

        try {
            $pdo->prepare('UPDATE backup_config SET last_run_at = NOW() WHERE id = 1')->execute();
        } catch (\Throwable $e) {
        }

        $this->applyRetention((int) ($cfg['reter_quantidade'] ?? 10));

        if ($status === 'ok') {
            $this->criarNotificacao($usuarioId, 'backup', '✅ Backup concluído', 'Seu backup do banco foi finalizado com sucesso (' . $this->fmtBytesPublic($dbSize) . ').', '/admin/backup');
        } else {
            $this->criarNotificacao($usuarioId, 'backup', '⚠️ Falha no backup', 'O backup não pôde ser concluído: ' . (string) $err, '/admin/backup');
        }

        if ($status !== 'ok') {
            throw new \RuntimeException($err ?: 'Falha ao gerar backup');
        }

        if ($trigger === 'cron') {
            try {
                $this->enviarBackupServidorExterno($dbSqlPath);
            } catch (\Throwable $e) {
                error_log('[BACKUP] Falha ao enviar para servidor externo: ' . $e->getMessage());
            }
        }

        return $id;
    }

    public function applyRetention(int $keep): void {
        $keep = max(1, min(365, $keep));
        $pdo = Database::getConnection();

        $hasRuns = false;
        try {
            $st = $pdo->query("SHOW TABLES LIKE 'backup_runs'");
            $hasRuns = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable $e) {
            $hasRuns = false;
        }
        if (!$hasRuns) {
            return;
        }

        $stmt = $pdo->prepare('SELECT id, db_sql_path, files_zip_path FROM backup_runs ORDER BY created_at DESC, id DESC LIMIT 1000');
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (count($rows) <= $keep) {
            return;
        }

        $toDelete = array_slice($rows, $keep);
        foreach ($toDelete as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) continue;

            $db = (string) ($r['db_sql_path'] ?? '');
            $zip = (string) ($r['files_zip_path'] ?? '');

            if ($db !== '' && file_exists($db)) {
                @unlink($db);
            }
            if ($zip !== '' && file_exists($zip)) {
                @unlink($zip);
            }

            $stDel = $pdo->prepare('DELETE FROM backup_runs WHERE id = ?');
            $stDel->execute([$id]);
        }
    }

    public function deleteBackup(int $runId): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, db_sql_path, files_zip_path FROM backup_runs WHERE id = ? LIMIT 1');
        $stmt->execute([$runId]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) {
            throw new \RuntimeException('Backup não encontrado');
        }

        $db = (string) ($r['db_sql_path'] ?? '');
        $zip = (string) ($r['files_zip_path'] ?? '');

        if ($db !== '' && file_exists($db)) {
            @unlink($db);
        }
        if ($zip !== '' && file_exists($zip)) {
            @unlink($zip);
        }

        $stDel = $pdo->prepare('DELETE FROM backup_runs WHERE id = ?');
        $stDel->execute([(int) $r['id']]);
    }

    public function restoreDatabase(int $runId): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, db_sql_path FROM backup_runs WHERE id = ? LIMIT 1');
        $stmt->execute([$runId]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) {
            throw new \RuntimeException('Backup não encontrado');
        }

        $sqlPath = (string) ($r['db_sql_path'] ?? '');
        if ($sqlPath === '' || !file_exists($sqlPath)) {
            throw new \RuntimeException('Arquivo SQL do backup não encontrado');
        }

        [$host, $db, $user, $pass] = $this->getDbCredentials();
        $mysql = 'mysql';
        $cmd = '"' . $mysql . '"' .
            ' --host=' . escapeshellarg($host) .
            ' --user=' . escapeshellarg($user) .
            ' --password=' . escapeshellarg($pass) .
            ' --default-character-set=utf8mb4 ' .
            escapeshellarg($db) .
            ' < ' . escapeshellarg($sqlPath);

        [$ret, $out] = $this->runCmd($cmd);
        if ($ret !== 0) {
            throw new \RuntimeException('Falha ao restaurar via mysql: ' . $out);
        }
    }

    /**
     * Envia uma cópia do backup do banco de dados para o servidor externo.
     * O servidor externo apaga automaticamente backups antigos ao receber um novo.
     */
    private function enviarBackupServidorExterno(string $dbSqlPath): void {
        error_log('[BACKUP-EXTERNO] Iniciando envio para servidor externo...');
        error_log('[BACKUP-EXTERNO] Arquivo: ' . $dbSqlPath);

        if (!file_exists($dbSqlPath)) {
            error_log('[BACKUP-EXTERNO] ERRO: Arquivo não existe: ' . $dbSqlPath);
            return;
        }

        $fileSize = filesize($dbSqlPath);
        error_log('[BACKUP-EXTERNO] Tamanho do arquivo: ' . number_format($fileSize / 1048576, 2) . ' MB');

        if ($fileSize === 0) {
            error_log('[BACKUP-EXTERNO] ERRO: Arquivo vazio');
            return;
        }

        $url = 'https://media.onsolutionsbrasil.com.br/backup.php';

        // Comprimir o .sql em .gz para reduzir tamanho de upload
        $gzPath = $dbSqlPath . '.gz';
        $compressed = false;
        try {
            error_log('[BACKUP-EXTERNO] Comprimindo arquivo...');
            $fp = gzopen($gzPath, 'wb9');
            if ($fp) {
                $src = fopen($dbSqlPath, 'rb');
                if ($src) {
                    while (!feof($src)) {
                        gzwrite($fp, fread($src, 524288)); // 512KB chunks
                    }
                    fclose($src);
                }
                gzclose($fp);
                if (file_exists($gzPath) && filesize($gzPath) > 0) {
                    $compressed = true;
                    error_log('[BACKUP-EXTERNO] Comprimido: ' . number_format(filesize($gzPath) / 1048576, 2) . ' MB');
                }
            }
        } catch (\Throwable $e) {
            error_log('[BACKUP-EXTERNO] Falha na compressão: ' . $e->getMessage());
            $compressed = false;
        }

        $fileToSend = $compressed ? $gzPath : $dbSqlPath;
        $fileName = $compressed ? basename($dbSqlPath) . '.gz' : basename($dbSqlPath);
        $sendSize = filesize($fileToSend);

        error_log('[BACKUP-EXTERNO] Enviando arquivo: ' . $fileName . ' (' . number_format($sendSize / 1048576, 2) . ' MB)');
        error_log('[BACKUP-EXTERNO] URL destino: ' . $url);

        if (!function_exists('curl_init')) {
            error_log('[BACKUP-EXTERNO] ERRO: cURL não disponível no servidor');
            if ($compressed && file_exists($gzPath)) @unlink($gzPath);
            return;
        }

        // Enviar via cURL multipart/form-data
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600, // 10 minutos para upload
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false, // Servidor compartilhado pode ter SSL issues
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($fileToSend, 'application/octet-stream', $fileName),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Limpar arquivo comprimido temporário
        if ($compressed && file_exists($gzPath)) {
            @unlink($gzPath);
        }

        error_log('[BACKUP-EXTERNO] HTTP Code: ' . $httpCode);
        error_log('[BACKUP-EXTERNO] cURL errno: ' . $curlErrno);
        if ($curlError) {
            error_log('[BACKUP-EXTERNO] cURL error: ' . $curlError);
        }
        error_log('[BACKUP-EXTERNO] Resposta: ' . substr((string)$response, 0, 500));

        if ($httpCode !== 200 || $response === false) {
            error_log('[BACKUP-EXTERNO] FALHA: HTTP ' . $httpCode . ' - ' . ($curlError ?: 'Resposta inválida'));
            return;
        }

        $json = @json_decode((string) $response, true);
        if (!is_array($json) || ($json['status'] ?? '') !== 'success') {
            $msg = $json['msg'] ?? 'Resposta inesperada do servidor';
            error_log('[BACKUP-EXTERNO] FALHA: ' . $msg);
            return;
        }

        error_log('[BACKUP-EXTERNO] SUCESSO! URL: ' . ($json['url'] ?? ''));
    }
}
