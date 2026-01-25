<?php
namespace App\Core;

class Router {
    private $routes = [];

    public function get($path, $handler, $method = null) {
        if ($handler instanceof \Closure) {
            // Para closures, armazenar diretamente
            $this->routes['GET'][$path] = ['controller' => $handler, 'method' => null];
        } else {
            // Para strings (nomes de controllers)
            $this->routes['GET'][$path] = ['controller' => $handler, 'method' => $method];
        }
    }

    public function post($path, $handler, $method = null) {
        if ($handler instanceof \Closure) {
            // Para closures, armazenar diretamente
            $this->routes['POST'][$path] = ['controller' => $handler, 'method' => null];
        } else {
            // Para strings (nomes de controllers)
            $this->routes['POST'][$path] = ['controller' => $handler, 'method' => $method];
        }
    }
    
    public function delete($path, $handler, $method = null) {
        if ($handler instanceof \Closure) {
            // Para closures, armazenar diretamente
            $this->routes['DELETE'][$path] = ['controller' => $handler, 'method' => null];
        } else {
            // Para strings (nomes de controllers)
            $this->routes['DELETE'][$path] = ['controller' => $handler, 'method' => $method];
        }
    }

    public function dispatch(Request $request) {
        $method = $request->getMethod();
        $path = $request->getPath();

        $matchedRoute = null;
        $params = [];

        // Procurar rota exata
        if (isset($this->routes[$method][$path])) {
            $matchedRoute = $this->routes[$method][$path];
        } else {
            // Procurar rota com parâmetros
            foreach ($this->routes[$method] as $routePath => $route) {
                $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
                $pattern = '#^' . $pattern . '$#';
                
                if (preg_match($pattern, $path, $matches)) {
                    $matchedRoute = $route;
                    // Extrair nomes dos parâmetros
                    preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
                    for ($i = 1; $i < count($matches); $i++) {
                        $params[$paramNames[1][$i-1]] = $matches[$i];
                    }
                    break;
                }
            }
        }

        if (!$matchedRoute) {
            http_response_code(404);
            echo "Página não encontrada";
            return;
        }

        $controllerClass = $matchedRoute['controller'];
        $controllerMethod = $matchedRoute['method'];

        // Verificar se é uma função anônima/closure
        if ($controllerClass instanceof \Closure) {
            // Executar função anônima diretamente
            call_user_func($controllerClass, $request);
            return;
        }

        // Verificar se é uma função (string)
        if (is_string($controllerClass) && function_exists($controllerClass)) {
            // Executar função diretamente
            call_user_func($controllerClass, $request);
            return;
        }

        // Se não for closure e não tiver método, é um erro
        if (!$controllerMethod) {
            http_response_code(500);
            echo "Método não especificado para controller";
            return;
        }

        // Tratar como classe de controller
        if (!class_exists("App\\Controllers\\{$controllerClass}")) {
            http_response_code(500);
            echo "Controller não encontrado";
            return;
        }

        $controller = new ("App\\Controllers\\{$controllerClass}")();
        
        if (!method_exists($controller, $controllerMethod)) {
            http_response_code(500);
            echo "Método não encontrado";
            return;
        }

        // Adicionar parâmetros ao request
        foreach ($params as $key => $value) {
            $request->setParam($key, $value);
        }

        $controller->$controllerMethod($request);
    }
}
