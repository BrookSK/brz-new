<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$jobId = (string) ($argv[1] ?? '');
if ($jobId === '') {
    exit(1);
}

$sessionLifetime = 60 * 60 * 24 * 7;
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
ini_set('session.cookie_lifetime', (string) $sessionLifetime);

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
    $controller = new \App\Controllers\AssessoriaController();
    $controller->processarJobPorId($jobId);
    exit(0);
} catch (\Throwable $e) {
    exit(1);
}
