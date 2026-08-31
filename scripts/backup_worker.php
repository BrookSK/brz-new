<?php
/**
 * Worker CLI do backup em segundo plano.
 *
 * Uso: php scripts/backup_worker.php <runId>
 *
 * Executa o dump do banco para um registro de backup_runs já criado com
 * status 'processando', finaliza o registro (ok/erro) e notifica o usuário
 * que disparou (via tabela admin_notificacoes / sino do admin).
 *
 * É disparado de forma desacoplada por BackupService::spawnWorker().
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$runId = (int) ($argv[1] ?? 0);
if ($runId <= 0) {
    fwrite(STDERR, "[backup_worker] runId inválido\n");
    exit(1);
}

// Backup pode demorar; sem limite de tempo e independente do usuário
@set_time_limit(0);
@ini_set('max_execution_time', '0');
if (function_exists('ignore_user_abort')) {
    ignore_user_abort(true);
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

spl_autoload_register(function ($class) {
    $prefix = 'Config\\';
    $base_dir = __DIR__ . '/../config/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

try {
    error_log('[backup_worker] Iniciando backup #' . $runId);
    $service = new \App\Services\BackupService();
    $service->runBackupJob($runId);
    error_log('[backup_worker] Backup #' . $runId . ' finalizado');
    exit(0);
} catch (\Throwable $e) {
    error_log('[backup_worker] FATAL #' . $runId . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit(1);
}
