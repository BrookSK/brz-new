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

    private function registrarAuditoriaAuto(string $httpMethod, string $path, $controllerClass, ?string $controllerMethod, array $params, Request $request, ?int $statusCode = null, ?int $durationMs = null): void {
        try {
            // Registrar todos os acessos do admin
            if (strpos($path, '/admin') !== 0) {
                return;
            }
            // Evitar loop ao abrir a própria tela de logs
            if (strpos($path, '/admin/estoque/relatorios/movimentacao') === 0) {
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

            $handler = '';
            if (is_string($controllerClass) && $controllerMethod) {
                $handler = $controllerClass . '::' . $controllerMethod;
            }

            $db = Database::getConnection();

            $cols = [];
            try {
                $stCols = $db->query('DESCRIBE auditoria_logs');
                $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $insertCols = ['usuario_id', 'acao', 'tabela', 'registro_id', 'valores_antigos', 'valores_novos', 'ip', 'user_agent'];
            $place = [':uid', ':acao', 'NULL', 'NULL', 'NULL', ':novos', ':ip', ':ua'];
            $bind = [
                ':uid' => $usuarioId,
                ':acao' => $acao,
                ':novos' => json_encode(['payload' => $payload, 'meta' => $meta], JSON_UNESCAPED_UNICODE),
                ':ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                ':ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ];

            if (is_array($cols) && !empty($cols)) {
                if (in_array('http_method', $cols, true)) {
                    $insertCols[] = 'http_method';
                    $place[] = ':hm';
                    $bind[':hm'] = $httpMethod;
                }
                if (in_array('route', $cols, true)) {
                    $insertCols[] = 'route';
                    $place[] = ':route';
                    $bind[':route'] = $path;
                }
                if (in_array('handler', $cols, true)) {
                    $insertCols[] = 'handler';
                    $place[] = ':handler';
                    $bind[':handler'] = $handler;
                }
                if (in_array('status_code', $cols, true) && $statusCode !== null) {
                    $insertCols[] = 'status_code';
                    $place[] = ':sc';
                    $bind[':sc'] = (int) $statusCode;
                }
                if (in_array('duration_ms', $cols, true) && $durationMs !== null) {
                    $insertCols[] = 'duration_ms';
                    $place[] = ':dm';
                    $bind[':dm'] = (int) $durationMs;
                }
                if (in_array('session_id', $cols, true)) {
                    $insertCols[] = 'session_id';
                    $place[] = ':sid';
                    $bind[':sid'] = (string) session_id();
                }
                if (in_array('referer', $cols, true)) {
                    $insertCols[] = 'referer';
                    $place[] = ':ref';
                    $bind[':ref'] = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
                }
            }

            $sql = 'INSERT INTO auditoria_logs (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $place) . ')';
            $st = $db->prepare($sql);
            foreach ($bind as $k => $v) {
                $st->bindValue($k, $v);
            }
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

        $t0 = microtime(true);

        $matchedRoute = null;
        $params = [];

        // Procurar rota exata
        if (isset($this->routes[$method][$path])) {
            $matchedRoute = $this->routes[$method][$path];
        } else {
            // Procurar rota com parâmetros
            foreach ($this->routes[$method] as $routePath => $route) {
                $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
                $pattern = '#^' . $pattern . '/?$#';
                
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
            try {
                if (!headers_sent()) {
                    header('X-App-Route-Status: no-match');
                    header('X-App-Route-Method: ' . (string) $method);
                    header('X-App-Route-Path: ' . (string) $path);
                }
            } catch (\Throwable $e) {
            }
            try {
                error_log('[Router] no route match: ' . (string) $method . ' ' . (string) $path);
            } catch (\Throwable $e) {
            }
            $view404 = __DIR__ . '/../Views/errors/404.php';
            if (is_file($view404)) {
                require $view404;
            } else {
                echo "Página não encontrada";
            }
            return;
        }

        $controllerClass = $matchedRoute['controller'];
        $controllerMethod = $matchedRoute['method'];

        try {
            // Verificar se é uma função anônima/closure
            if ($controllerClass instanceof \Closure) {
                call_user_func($controllerClass, $request);
                $statusCode = http_response_code();
                $dt = (int) round((microtime(true) - $t0) * 1000);
                $this->registrarAuditoriaAuto((string) $method, (string) $path, $controllerClass, $controllerMethod, $params, $request, $statusCode, $dt);
                return;
            }

            if (is_string($controllerClass) && function_exists($controllerClass)) {
                call_user_func($controllerClass, $request);
                $statusCode = http_response_code();
                $dt = (int) round((microtime(true) - $t0) * 1000);
                $this->registrarAuditoriaAuto((string) $method, (string) $path, $controllerClass, $controllerMethod, $params, $request, $statusCode, $dt);
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
                $statusCode = http_response_code();
                $dt = (int) round((microtime(true) - $t0) * 1000);
                $this->registrarAuditoriaAuto((string) $method, (string) $path, $controllerClass, $controllerMethod, $params, $request, $statusCode, $dt);
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

            $statusCode = http_response_code();
            $dt = (int) round((microtime(true) - $t0) * 1000);
            $this->registrarAuditoriaAuto((string) $method, (string) $path, $controllerClass, $controllerMethod, $params, $request, $statusCode, $dt);
            return;
        } catch (\Throwable $e) {
            $statusCode = http_response_code();
            if (!$statusCode) {
                http_response_code(500);
                $statusCode = 500;
            }
            $dt = (int) round((microtime(true) - $t0) * 1000);
            $this->registrarAuditoriaAuto((string) $method, (string) $path, $controllerClass, $controllerMethod, $params, $request, $statusCode, $dt);
            throw $e;
        }
    }
}
