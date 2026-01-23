<?php
require_once '../vendor/autoload.php';

use App\Core\Router;
use App\Core\Request;

$request = new Request();
$router = new Router();

require_once '../app/routes.php';

$router->dispatch($request);
