<?php

if (PHP_SAPI !== 'cli') {
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
    $service = new \App\Services\PaymentService();
    $result = $service->processarRendimentoClube();

    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"success":false,"error":"json_encode_failed"}';
    }

    fwrite(STDOUT, $json . PHP_EOL);

    $success = (bool) ($result['success'] ?? false);
    $errors = (int) ($result['errors'] ?? 0);

    if ($success && $errors === 0) {
        exit(0);
    }

    exit(1);
} catch (\Throwable $e) {
    $out = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
    fwrite(STDOUT, json_encode($out, JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
