<?php
namespace App\Core;

class Router {
    private $routes = [];

    public function get($path, $controller, $method) {
        $this->routes['GET'][$path] = ['controller' => $controller, 'method' => $method];
    }

    public function post($path, $controller, $method) {
        $this->routes['POST'][$path] = ['controller' => $controller, 'method' => $method];
    }

    public function dispatch(Request $request) {
        $method = $request->getMethod();
        $path = $request->getPath();

        if (!isset($this->routes[$method][$path])) {
            http_response_code(404);
            echo "Página não encontrada";
            return;
        }

        $route = $this->routes[$method][$path];
        $controllerClass = "App\\Controllers\\{$route['controller']}";
        $controllerMethod = $route['method'];

        if (!class_exists($controllerClass)) {
            http_response_code(500);
            echo "Controller não encontrado";
            return;
        }

        $controller = new $controllerClass();
        
        if (!method_exists($controller, $controllerMethod)) {
            http_response_code(500);
            echo "Método não encontrado";
            return;
        }

        $controller->$controllerMethod($request);
    }
}
