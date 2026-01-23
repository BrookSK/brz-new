<?php
// Iniciar sessão antes de qualquer output
session_start();

// Autoload manual temporário (até executar composer install)
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

use App\Core\Router;
use App\Core\Request;

$request = new Request();
$router = new Router();

require_once __DIR__ . '/../app/routes.php';

$router->dispatch($request);
