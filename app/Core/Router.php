<?php
namespace App\Core;

use Config\Database;

class Router {
    private $routes = [];

    private function maskSensitive(array $data): array {
        $sensitive = ['senha', 'password', 'pass', 'token', 'access_token', 'secret', 'api_key', 'card_number', 'cpf', 'cnpj'];
        $out = [];
        foreach ($data as $k => $v) {
            $key = is_string($k) ? strtolower($k) : (string) $k;
            $isSens = false;
            foreach ($sensitive as $s) {
                if ($key === $s || strpos($key, $s) !== false) {
                    $isSens = true;
                    break;
                }
            }
            if ($isSens) {
                $out[$k] = '***';
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private function registrarAuditoriaAuto(string $httpMethod, string $path, $controllerClass, ?string $controllerMethod, array $params, Request $request): void {
        try {
            if (!in_array($httpMethod, ['POST', 'DELETE'], true)) {
                return;
            }
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $usuarioId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
            if (empty($usuarioId) || $usuarioId <= 0) {
                return;
            }

            $acao = $httpMethod . ' ' . $path;
            if (is_string($controllerClass) && $controllerMethod) {
                $acao .= ' -> ' . $controllerClass . '::' . $controllerMethod;
            } elseif ($controllerClass instanceof \Closure) {
                $acao .= ' -> closure';
            }

            $payload = [];
            try {
                $payload = $request->getParams();
                if (!is_array($payload)) $payload = [];
            } catch (\Exception $e) {
                $payload = [];
            }
            $payload = $this->maskSensitive((array) $payload);

            $meta = [
                'route_params' => $params,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'referer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
            ];

            $db = Database::getConnection();
            $st = $db->prepare('INSERT INTO auditoria_logs (usuario_id, acao, tabela, registro_id, valores_antigos, valores_novos, ip, user_agent) VALUES (:uid, :acao, NULL, NULL, NULL, :novos, :ip, :ua)');
            $st->bindValue(':uid', $usuarioId);
            $st->bindValue(':acao', $acao);
            $st->bindValue(':novos', json_encode(['payload' => $payload, 'meta' => $meta], JSON_UNESCAPED_UNICODE));
            $st->bindValue(':ip', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            $st->bindValue(':ua', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500));
            $st->execute();
        } catch (\Exception $e) {
        }
    }

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

        // Registrar auditoria automática para ações mutáveis
        $this->registrarAuditoriaAuto((string) $method, (string) $path, $controllerClass, $controllerMethod, $params, $request);

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

        // Chamar método com parâmetros
        if (!empty($params)) {
            $controller->$controllerMethod($request, ...array_values($params));
        } else {
            $controller->$controllerMethod($request);
        }
    }
}
